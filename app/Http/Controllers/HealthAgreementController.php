<?php

namespace App\Http\Controllers;

use App\Models\HealthAgreement;
use App\Models\Medication;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HealthAgreementController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function index(Request $request)
    {
        $filtroEstado = (string) $request->query('estado', 'todos');
        $fechaDesde = $request->query('desde');
        $fechaHasta = $request->query('hasta');

        $query = HealthAgreement::with('medicamentos')->orderBy('id_convenio', 'asc');
        $hoy = Carbon::today()->toDateString();

        if ($filtroEstado === 'vigentes') {
            $query->vigentes();
        } elseif ($filtroEstado === 'vencidos') {
            $query->where('activo', true)
                ->whereNotNull('fecha_fin')
                ->whereDate('fecha_fin', '<', $hoy);
        } elseif ($filtroEstado === 'programados') {
            $query->where('activo', true)
                ->whereNotNull('fecha_inicio')
                ->whereDate('fecha_inicio', '>', $hoy);
        } elseif ($filtroEstado === 'deshabilitados') {
            $query->where('activo', false);
        }

        if (! empty($fechaDesde)) {
            $query->where(function ($q) use ($fechaDesde) {
                $q->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $fechaDesde);
            });
        }

        if (! empty($fechaHasta)) {
            $query->where(function ($q) use ($fechaHasta) {
                $q->whereNull('fecha_inicio')
                    ->orWhereDate('fecha_inicio', '<=', $fechaHasta);
            });
        }

        $convenios = $query->get();
        $medicamentosCatalogo = Medication::where('activo', true)->orderBy('nombre_comercial')->get();

        return view('convenios.index', compact(
            'convenios',
            'medicamentosCatalogo',
            'filtroEstado',
            'fechaDesde',
            'fechaHasta'
        ));
    }

    public function store(Request $request)
    {
        if (! $request->user()?->hasPermission('convenios.manage')) {
            abort(403, 'Solo el Administrador General está facultado para agregar nuevos convenios de salud.');
        }

        $data = $request->validate([
            'nombre_convenio' => ['required', 'string', 'max:100'],
            'porcentaje_descuento' => ['required', 'numeric', 'min:0', 'max:100'],
            'aplica_todo_catalogo' => ['nullable', 'boolean'],
            'medicamentos_ids' => ['nullable', 'array', 'max:500'],
            'medicamentos_ids.*' => ['integer', 'min:1', 'max:2147483647', 'exists:medicamentos,id_medicamento'],
            'fecha_inicio' => ['nullable', 'date', 'after:2000-01-01', 'before:2100-01-01'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio', 'before:2100-01-01'],
        ]);

        $aplicaTodo = isset($data['aplica_todo_catalogo']) ? (bool) $data['aplica_todo_catalogo'] : true;

        $convenio = HealthAgreement::create([
            'nombre_convenio' => trim($data['nombre_convenio']),
            'porcentaje_descuento' => (float) $data['porcentaje_descuento'],
            'aplica_todo_catalogo' => $aplicaTodo,
            'fecha_inicio' => $data['fecha_inicio'] ?? null,
            'fecha_fin' => $data['fecha_fin'] ?? null,
            'activo' => true,
        ]);

        if (! $aplicaTodo && ! empty($data['medicamentos_ids'])) {
            $syncData = [];
            foreach ($data['medicamentos_ids'] as $idMed) {
                $syncData[(int) $idMed] = ['activo' => true];
            }
            $convenio->medicamentos()->sync($syncData);
        }

        $this->auditLogService->recordSecurityEvent('CREACION_CONVENIO_SALUD', [
            'id_convenio' => $convenio->id_convenio,
            'nombre_convenio' => $convenio->nombre_convenio,
            'porcentaje_descuento' => $convenio->porcentaje_descuento,
            'aplica_todo_catalogo' => $convenio->aplica_todo_catalogo,
            'medicamentos_vademecum_count' => $aplicaTodo ? 'TODO_EL_CATALOGO' : count($data['medicamentos_ids'] ?? []),
            'fecha_inicio' => $convenio->fecha_inicio?->toDateString(),
            'fecha_fin' => $convenio->fecha_fin?->toDateString(),
        ], $request->user());

        return redirect()->route('convenios.index')
            ->with('success', "Convenio '{$data['nombre_convenio']}' registrado exitosamente con su cobertura de vademécum.");
    }

    public function update(Request $request, int $idConvenio)
    {
        if (! $request->user()?->hasPermission('convenios.manage')) {
            abort(403, 'Solo el Administrador General está facultado para editar convenios de salud.');
        }

        $data = $request->validate([
            'nombre_convenio' => ['required', 'string', 'max:100'],
            'porcentaje_descuento' => ['required', 'numeric', 'min:0', 'max:100'],
            'aplica_todo_catalogo' => ['nullable', 'boolean'],
            'medicamentos_ids' => ['nullable', 'array', 'max:500'],
            'medicamentos_ids.*' => ['integer', 'min:1', 'max:2147483647', 'exists:medicamentos,id_medicamento'],
            'fecha_inicio' => ['nullable', 'date', 'after:2000-01-01', 'before:2100-01-01'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio', 'before:2100-01-01'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $convenio = HealthAgreement::findOrFail($idConvenio);
        $aplicaTodo = isset($data['aplica_todo_catalogo'])
            ? (bool) $data['aplica_todo_catalogo']
            : $convenio->aplica_todo_catalogo;

        $convenio->update([
            'nombre_convenio' => trim($data['nombre_convenio']),
            'porcentaje_descuento' => (float) $data['porcentaje_descuento'],
            'aplica_todo_catalogo' => $aplicaTodo,
            'fecha_inicio' => $data['fecha_inicio'] ?? null,
            'fecha_fin' => $data['fecha_fin'] ?? null,
            'activo' => isset($data['activo']) ? (bool) $data['activo'] : $convenio->activo,
        ]);

        if ($request->has('aplica_todo_catalogo') || $request->has('medicamentos_ids')) {
            if ($aplicaTodo) {
                $convenio->medicamentos()->detach();
            } else {
                $syncData = [];
                foreach ($data['medicamentos_ids'] ?? [] as $idMed) {
                    $syncData[(int) $idMed] = ['activo' => true];
                }
                $convenio->medicamentos()->sync($syncData);
            }
        }

        $this->auditLogService->recordSecurityEvent('ACTUALIZACION_CONVENIO_SALUD', [
            'id_convenio' => $convenio->id_convenio,
            'nombre_convenio' => $convenio->nombre_convenio,
            'porcentaje_descuento' => $convenio->porcentaje_descuento,
            'aplica_todo_catalogo' => $convenio->aplica_todo_catalogo,
            'fecha_inicio' => $convenio->fecha_inicio?->toDateString(),
            'fecha_fin' => $convenio->fecha_fin?->toDateString(),
            'activo' => $convenio->activo,
        ], $request->user());

        return redirect()->route('convenios.index')
            ->with('success', "Convenio '{$convenio->nombre_convenio}' y su vademécum de medicamentos actualizados.");
    }

    public function toggleStatus(Request $request, int $idConvenio)
    {
        if (! $request->user()?->hasPermission('convenios.manage')) {
            abort(403, 'Solo el Administrador General está facultado para modificar el estado de convenios de salud.');
        }

        $convenio = HealthAgreement::findOrFail($idConvenio);
        $convenio->activo = ! $convenio->activo;
        $convenio->save();

        $evento = $convenio->activo ? 'HABILITACION_CONVENIO_SALUD' : 'DESHABILITACION_CONVENIO_SALUD';

        $this->auditLogService->recordSecurityEvent($evento, [
            'id_convenio' => $convenio->id_convenio,
            'nombre_convenio' => $convenio->nombre_convenio,
            'activo' => $convenio->activo,
        ], $request->user());

        $msg = $convenio->activo
            ? "Convenio '{$convenio->nombre_convenio}' habilitado para su uso en el Punto de Venta."
            : "Convenio '{$convenio->nombre_convenio}' deshabilitado.";

        return redirect()->route('convenios.index')->with('success', $msg);
    }

    public function destroy(Request $request, int $idConvenio)
    {
        if (! $request->user()?->hasPermission('convenios.manage')) {
            abort(403, 'Solo el Administrador General está facultado para deshabilitar convenios de salud.');
        }

        $convenio = HealthAgreement::findOrFail($idConvenio);
        $convenio->update(['activo' => false]);

        $this->auditLogService->recordSecurityEvent('DESHABILITACION_CONVENIO_SALUD', [
            'id_convenio' => $idConvenio,
            'nombre_convenio' => $convenio->nombre_convenio,
            'activo' => false,
        ], $request->user());

        return redirect()->route('convenios.index')
            ->with('success', "Convenio '{$convenio->nombre_convenio}' deshabilitado.");
    }
}
