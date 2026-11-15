<?php

namespace App\Http\Controllers;

use App\Models\IspLog;
use App\Services\AuditLogService;
use App\Services\RutValidator;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class IspValidationController extends Controller
{
    public function __construct(
        private readonly SaleService $saleService,
        private readonly AuditLogService $auditLogService
    ) {}

    public function index(Request $request)
    {
        $pendientes = IspLog::with(['medicamento', 'lote', 'vendedor'])
            ->where('estado', 'PENDIENTE')
            ->orderBy('created_at', 'asc')
            ->get();

        $autorizadasHoyCount = IspLog::where('estado', 'AUTORIZADO')
            ->whereDate('fecha_autorizacion', now()->toDateString())
            ->count();

        $historialIsp = IspLog::with(['medicamento', 'lote', 'vendedor', 'quimicoAutorizador', 'venta'])
            ->whereIn('estado', ['AUTORIZADO', 'RECHAZADO'])
            ->latest('updated_at')
            ->take(25)
            ->get();

        $selectedId = $request->query('id_registro', $pendientes->first()?->id_registro);
        $selectedValidation = $selectedId
            ? IspLog::with(['medicamento', 'lote', 'vendedor'])->find($selectedId)
            : null;

        return view('autorizaciones.index', compact(
            'pendientes',
            'autorizadasHoyCount',
            'historialIsp',
            'selectedValidation'
        ));
    }

    public function listPending()
    {
        $pendientes = IspLog::with(['medicamento', 'lote', 'vendedor'])
            ->where('estado', 'PENDIENTE')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json(['data' => $pendientes]);
    }

    /**
     * Aprueba la receta retenida validando el PIN criptográfico del Q.F., descuenta stock FEFO,
     * emite la boleta y sella el registro inmutable SHA-256 en el Libro ISP y MongoDB (CP-04).
     */
    public function authorizePrescription(Request $request, int $idRegistro)
    {
        $data = $request->validate([
            'rut_paciente' => ['required', 'string', 'max:15'],
            'rut_medico' => ['required', 'string', 'max:15'],
            'folio_receta' => ['required', 'string', 'max:60'],
            'pin_qf' => ['required', 'string'],
        ]);

        $user = $request->user();
        if ($user->role !== 'quimico_farmaceutico') {
            abort(403, 'Solo el Químico Farmacéutico titular o delegado puede autorizar recetas retenidas.');
        }

        if (! $user->pin_qf || ! Hash::check($data['pin_qf'], $user->pin_qf)) {
            $this->auditLogService->recordSecurityEvent('FIRMA_QF_PIN_INVALIDO', [
                'id_registro' => $idRegistro,
                'folio_receta' => $data['folio_receta'],
            ], $user);

            if ($request->expectsJson()) {
                return response()->json(['error' => 'PIN criptográfico del Químico Farmacéutico inválido.'], 403);
            }

            return back()->with('error', 'PIN criptográfico inválido. Transacción bloqueada y reportada a auditoría.');
        }

        $registro = IspLog::with(['medicamento', 'vendedor'])->findOrFail($idRegistro);
        if ($registro->estado !== 'PENDIENTE') {
            return back()->with('error', 'Esta solicitud ya fue procesada anteriormente.');
        }

        $registro->update([
            'rut_paciente' => RutValidator::format($data['rut_paciente']),
            'rut_medico' => RutValidator::format($data['rut_medico']),
            'folio_receta' => strtoupper(trim($data['folio_receta'])),
            'id_qf_autorizador' => $user->id_usuario,
            'estado' => 'AUTORIZADO',
            'fecha_autorizacion' => now(),
        ]);

        try {
            $vendedor = $registro->vendedor ?? $user;
            $venta = $this->saleService->executeTransaction(
                [
                    [
                        'id_medicamento' => $registro->id_medicamento,
                        'cantidad' => $registro->cantidad ?: 1,
                    ],
                ],
                $vendedor,
                null,
                [
                    'id_registro' => $registro->id_registro,
                ]
            );

            $registro->refresh();

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'AUTORIZADO',
                    'venta' => $venta,
                    'registro_isp' => $registro,
                ], 200);
            }

            return redirect()->route('autorizaciones.index')->with(
                'success',
                "Receta Folio {$registro->folio_receta} autorizada correctamente. Boleta #{$venta->id_venta} emitida y registrada en el Libro Oficial ISP."
            );
        } catch (RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    public function rejectPrescription(Request $request, int $idRegistro)
    {
        $data = $request->validate([
            'motivo_rechazo' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $registro = IspLog::with('medicamento')->findOrFail($idRegistro);

        $registro->update([
            'id_qf_autorizador' => $user->id_usuario,
            'estado' => 'RECHAZADO',
            'motivo_rechazo' => $data['motivo_rechazo'],
            'fecha_autorizacion' => now(),
        ]);

        $this->auditLogService->recordSecurityEvent('RECHAZO_RECETA_ISP', [
            'id_registro' => $registro->id_registro,
            'folio_receta' => $registro->folio_receta,
            'medicamento' => $registro->medicamento?->nombre_comercial,
            'motivo' => $data['motivo_rechazo'],
        ], $user);

        return redirect()->route('autorizaciones.index')
            ->with('warning', "Receta Folio {$registro->folio_receta} fue RECHAZADA sanitariamente.");
    }
}
