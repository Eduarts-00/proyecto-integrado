<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Medication;
use App\Services\AuditLogService;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use RuntimeException;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly AuditLogService $auditLogService
    ) {}

    public function index(Request $request)
    {
        $ubicacion = strtoupper((string) $request->query('ubicacion', 'VITRINA'));
        if (! in_array($ubicacion, ['VITRINA', 'BODEGA', 'TODOS', 'BAJAS', 'CATALOGO'], true)) {
            $ubicacion = 'VITRINA';
        }
        $search = trim((string) $request->query('q', ''));

        $query = Batch::with('medicamento')
            ->where('cantidad', '>', 0);

        if ($ubicacion === 'BAJAS') {
            $query->where(function ($q) {
                $q->where('activo', false)
                    ->orWhereDate('fecha_vencimiento', '<=', now()->toDateString());
            })->orderByDesc('fecha_baja')->orderBy('fecha_vencimiento', 'asc');
        } else {
            // En Vitrina, Bodega y Todos los Lotes solo se exhiben los lotes activos (no dados de baja por caducidad/merma)
            $query->where('activo', true)
                ->orderBy('fecha_vencimiento', 'asc')
                ->orderBy('id_lote', 'asc');

            if (in_array($ubicacion, ['VITRINA', 'BODEGA'], true)) {
                $query->where('ubicacion', $ubicacion);
            }
        }

        $lotes = $query->get();
        $bajasCount = Batch::where('cantidad', '>', 0)
            ->where(function ($q) {
                $q->where('activo', false)
                    ->orWhereDate('fecha_vencimiento', '<=', now()->toDateString());
            })->count();

        $alertasFefo = $this->inventoryService->checkExpiryAlerts(30);
        $lotesBodega = Batch::with('medicamento')
            ->where('activo', true)
            ->where('ubicacion', 'BODEGA')
            ->where('cantidad', '>', 0)
            ->orderBy('fecha_vencimiento', 'asc')
            ->get();

        $lotesVitrina = Batch::with('medicamento')
            ->where('activo', true)
            ->where('ubicacion', 'VITRINA')
            ->where('cantidad', '>', 0)
            ->orderBy('fecha_vencimiento', 'asc')
            ->get();

        $medicamentos = Medication::where('activo', true)->orderBy('nombre_comercial')->get();
        $medicamentosCatalogo = Medication::with('lotes')->orderByDesc('activo')->orderBy('nombre_comercial')->get();

        return view('inventario.index', compact(
            'lotes',
            'ubicacion',
            'search',
            'bajasCount',
            'alertasFefo',
            'lotesBodega',
            'lotesVitrina',
            'medicamentos',
            'medicamentosCatalogo'
        ));
    }

    public function getFefoList()
    {
        $lotes = Batch::with('medicamento')
            ->where('cantidad', '>', 0)
            ->orderBy('fecha_vencimiento', 'asc')
            ->get();

        return response()->json(['data' => $lotes]);
    }

    /**
     * Registra una nueva remesa física de lote (HU-04).
     */
    public function registerBatch(Request $request)
    {
        if (! $request->user()->hasPermission('Can_Manage_Stock')) {
            abort(403, 'Solo el perfil Bodeguero puede registrar nuevas remesas.');
        }

        $data = $request->validate([
            'id_medicamento' => ['required', 'integer', 'exists:medicamentos,id_medicamento'],
            'lote_codigo' => ['required', 'string', 'max:60'],
            'ubicacion' => ['required', 'in:BODEGA,VITRINA'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'fecha_vencimiento' => ['required', 'date', 'after:today'],
        ]);

        $batch = Batch::create([
            'id_medicamento' => $data['id_medicamento'],
            'lote_codigo' => strtoupper(trim($data['lote_codigo'])),
            'ubicacion' => $data['ubicacion'],
            'cantidad' => $data['cantidad'],
            'fecha_vencimiento' => $data['fecha_vencimiento'],
            'fecha_ingreso' => now()->toDateString(),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['data' => $batch], 201);
        }

        return redirect()->route('inventario.index', ['ubicacion' => $data['ubicacion']])
            ->with('success', "Remesa {$batch->lote_codigo} registrada correctamente en {$batch->ubicacion} bajo ordenamiento FEFO.");
    }

    /**
     * Traspasa unidades desde Bodega hacia Vitrina priorizando el lote FEFO (Escenario B).
     */
    public function transferToDisplay(Request $request)
    {
        if (! $request->user()->hasPermission('Can_Manage_Stock')) {
            abort(403, 'Solo el perfil Bodeguero está facultado para traspasar lotes a Vitrina.');
        }

        $data = $request->validate([
            'id_lote' => ['required', 'integer', 'exists:inventario_lotes,id_lote'],
            'cantidad' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $loteVitrina = $this->inventoryService->transferToDisplay(
                (int) $data['id_lote'],
                (int) $data['cantidad']
            );

            return redirect()->route('inventario.index', ['ubicacion' => 'VITRINA'])
                ->with('success', "Traspaso FEFO completado: {$data['cantidad']} unidades del lote {$loteVitrina->lote_codigo} disponibles en Vitrina.");
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Retira o devuelve unidades desde Vitrina hacia Bodega (por sobrestock, retiro preventivo FEFO o custodia Q.F.).
     */
    public function transferToStorage(Request $request)
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('Can_Manage_Stock') && ! $user->hasPermission('Can_Manage_Medications'))) {
            abort(403, 'Solo Bodega, Químico Farmacéutico o Administración pueden retirar medicamentos de Vitrina hacia Bodega.');
        }

        $data = $request->validate([
            'id_lote' => ['required', 'integer', 'exists:inventario_lotes,id_lote'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'motivo' => ['nullable', 'string', 'max:150'],
        ]);

        try {
            $loteOrigen = Batch::with('medicamento')->findOrFail((int) $data['id_lote']);
            $loteBodega = $this->inventoryService->transferToStorage(
                (int) $data['id_lote'],
                (int) $data['cantidad']
            );

            $motivo = ! empty($data['motivo']) ? trim($data['motivo']) : 'Retiro / devolución interna a Bodega';

            $this->auditLogService->recordSecurityEvent('RETIRO_VITRINA_A_BODEGA', [
                'id_lote_origen' => $loteOrigen->id_lote,
                'lote_codigo' => $loteBodega->lote_codigo,
                'medicamento' => $loteOrigen->medicamento?->nombre_comercial,
                'cantidad_retirada' => (int) $data['cantidad'],
                'motivo' => $motivo,
            ], $user);

            return redirect()->route('inventario.index', ['ubicacion' => 'VITRINA'])
                ->with('success', "Retiro completado: {$data['cantidad']} u. de {$loteOrigen->medicamento?->nombre_comercial} (Lote {$loteBodega->lote_codigo}) traspasadas desde Vitrina hacia Bodega.");
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Da de baja sanitaria un lote (por caducidad/vencimiento FEFO, merma o alerta ISP)
     * retirándolo de Vitrina/Bodega activa hacia la bandeja de Bajas y Caducados, o lo rehabilita.
     */
    public function toggleBatchStatus(Request $request, int $idLote)
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('Can_Manage_Stock') && ! $user->hasPermission('Can_Manage_Medications') && $user->role !== 'administrador')) {
            abort(403, 'Solo Bodega, Químico Farmacéutico o Administración pueden dar de baja o rehabilitar un lote.');
        }

        $lote = Batch::with('medicamento')->findOrFail($idLote);
        $nuevoActivo = ! $lote->activo;

        if (! $nuevoActivo) {
            $motivoPorDefecto = $lote->dias_restantes <= 30
                ? 'Vencimiento / Producto Caducado'
                : 'Merma / Rotura o Deterioro de Envase';
            $motivo = trim((string) $request->input('motivo_baja', $motivoPorDefecto));

            $lote->activo = false;
            $lote->motivo_baja = $motivo !== '' ? $motivo : $motivoPorDefecto;
            $lote->fecha_baja = now();
            $lote->save();
        } else {
            $lote->activo = true;
            $lote->motivo_baja = null;
            $lote->fecha_baja = null;
            $lote->save();
        }

        $evento = $lote->activo ? 'HABILITACION_LOTE_INVENTARIO' : 'BAJA_SANITARIA_POR_CADUCIDAD';

        $this->auditLogService->recordSecurityEvent($evento, [
            'id_lote' => $lote->id_lote,
            'lote_codigo' => $lote->lote_codigo,
            'medicamento' => $lote->medicamento?->nombre_comercial,
            'ubicacion_origen' => $lote->ubicacion,
            'motivo_baja' => $lote->motivo_baja,
            'activo' => $lote->activo,
        ], $user);

        $msg = $lote->activo
            ? "Lote {$lote->lote_codigo} ({$lote->medicamento?->nombre_comercial}) rehabilitado y devuelto a {$lote->ubicacion}."
            : "Lote {$lote->lote_codigo} ({$lote->medicamento?->nombre_comercial}) retirado de {$lote->ubicacion} y movido a 'Bajas y Caducados' ({$lote->motivo_baja}).";

        return back()->with('success', $msg);
    }

    /**
     * Registra un nuevo medicamento adquirido en el catálogo (con su condición de receta y lote opcional).
     */
    public function storeMedication(Request $request)
    {
        if (! $request->user()?->hasPermission('Can_Manage_Medications')) {
            abort(403, 'No tiene permisos para registrar nuevos medicamentos en el catálogo.');
        }

        $data = $request->validate([
            'codigo_barras' => ['required', 'string', 'max:50', 'unique:medicamentos,codigo_barras'],
            'nombre_comercial' => ['required', 'string', 'max:150'],
            'principio_activo' => ['required', 'string', 'max:150'],
            'laboratorio' => ['required', 'string', 'max:100'],
            'categoria' => ['nullable', 'string', 'max:80'],
            'precio_unitario' => ['required', 'numeric', 'min:1'],
            'condicion_receta' => ['required', 'in:venta_libre,receta_retenida'],
            'cantidad_inicial' => ['nullable', 'integer', 'min:0'],
            'lote_codigo' => ['nullable', 'string', 'max:60'],
            'ubicacion_inicial' => ['nullable', 'in:BODEGA,VITRINA'],
            'fecha_vencimiento' => ['nullable', 'date', 'after:today'],
        ]);

        $esRestringido = $data['condicion_receta'] === 'receta_retenida';
        $categoria = ! empty($data['categoria'])
            ? trim($data['categoria'])
            : ($esRestringido ? 'Controlado (Receta Retenida ISP)' : 'Venta Directa');

        $medicamento = Medication::create([
            'codigo_barras' => trim($data['codigo_barras']),
            'nombre_comercial' => trim($data['nombre_comercial']),
            'principio_activo' => trim($data['principio_activo']),
            'laboratorio' => trim($data['laboratorio']),
            'categoria' => $categoria,
            'precio_unitario' => (float) $data['precio_unitario'],
            'requiere_receta' => $esRestringido,
            'tipo_controlado' => $esRestringido,
            'activo' => true,
        ]);

        $cantidadInicial = (int) ($data['cantidad_inicial'] ?? 0);
        if ($cantidadInicial > 0 && ! empty($data['lote_codigo']) && ! empty($data['fecha_vencimiento'])) {
            Batch::create([
                'id_medicamento' => $medicamento->id_medicamento,
                'lote_codigo' => strtoupper(trim($data['lote_codigo'])),
                'ubicacion' => $data['ubicacion_inicial'] ?? 'BODEGA',
                'cantidad' => $cantidadInicial,
                'fecha_vencimiento' => $data['fecha_vencimiento'],
                'fecha_ingreso' => now()->toDateString(),
                'activo' => true,
            ]);
        }

        $this->auditLogService->recordSecurityEvent('ADQUISICION_NUEVO_MEDICAMENTO', [
            'id_medicamento' => $medicamento->id_medicamento,
            'nombre_comercial' => $medicamento->nombre_comercial,
            'receta_retenida' => $esRestringido,
            'cantidad_inicial' => $cantidadInicial,
        ], $request->user());

        $tipoMsg = $esRestringido ? 'con Receta Retenida (Controlado ISP)' : 'de Venta Directa';

        return redirect()->route('inventario.index', ['ubicacion' => 'CATALOGO'])
            ->with('success', "Medicamento '{$medicamento->nombre_comercial}' ({$tipoMsg}) añadido exitosamente al catálogo.");
    }

    /**
     * Actualiza los datos de un medicamento y su condición de receta restringida.
     */
    public function updateMedication(Request $request, int $idMedicamento)
    {
        if (! $request->user()?->hasPermission('Can_Manage_Medications')) {
            abort(403, 'No tiene permisos para editar medicamentos del catálogo.');
        }

        $medicamento = Medication::findOrFail($idMedicamento);

        $data = $request->validate([
            'nombre_comercial' => ['required', 'string', 'max:150'],
            'principio_activo' => ['required', 'string', 'max:150'],
            'laboratorio' => ['required', 'string', 'max:100'],
            'categoria' => ['nullable', 'string', 'max:80'],
            'precio_unitario' => ['required', 'numeric', 'min:1'],
            'condicion_receta' => ['required', 'in:venta_libre,receta_retenida'],
            'activo' => ['required', 'boolean'],
        ]);

        $esRestringido = $data['condicion_receta'] === 'receta_retenida';

        $medicamento->update([
            'nombre_comercial' => trim($data['nombre_comercial']),
            'principio_activo' => trim($data['principio_activo']),
            'laboratorio' => trim($data['laboratorio']),
            'categoria' => ! empty($data['categoria']) ? trim($data['categoria']) : $medicamento->categoria,
            'precio_unitario' => (float) $data['precio_unitario'],
            'requiere_receta' => $esRestringido,
            'tipo_controlado' => $esRestringido,
            'activo' => (bool) $data['activo'],
        ]);

        $this->auditLogService->recordSecurityEvent('ACTUALIZACION_MEDICAMENTO_CATALOGO', [
            'id_medicamento' => $medicamento->id_medicamento,
            'nombre_comercial' => $medicamento->nombre_comercial,
            'receta_retenida' => $esRestringido,
            'activo' => $medicamento->activo,
        ], $request->user());

        return redirect()->route('inventario.index', ['ubicacion' => 'CATALOGO'])
            ->with('success', "Medicamento '{$medicamento->nombre_comercial}' actualizado correctamente.");
    }

    /**
     * Cambia rápidamente si un medicamento requiere Receta Retenida (Controlado ISP) o es de Venta Libre.
     */
    public function togglePrescriptionRestriction(Request $request, int $idMedicamento)
    {
        if (! $request->user()?->hasPermission('Can_Manage_Medications')) {
            abort(403, 'No tiene permisos para modificar la restricción de receta.');
        }

        $medicamento = Medication::findOrFail($idMedicamento);
        $nuevoEstadoControlado = ! ($medicamento->requiere_receta || $medicamento->tipo_controlado);

        $medicamento->update([
            'requiere_receta' => $nuevoEstadoControlado,
            'tipo_controlado' => $nuevoEstadoControlado,
        ]);

        $this->auditLogService->recordSecurityEvent('CAMBIO_RESTRICCION_RECETA_ISP', [
            'id_medicamento' => $medicamento->id_medicamento,
            'nombre_comercial' => $medicamento->nombre_comercial,
            'requiere_receta_retenida' => $nuevoEstadoControlado,
        ], $request->user());

        $msg = $nuevoEstadoControlado
            ? "'{$medicamento->nombre_comercial}' ahora exige Receta Retenida y autorización del Químico Farmacéutico para su venta."
            : "'{$medicamento->nombre_comercial}' cambió a condición de Venta Directa (sin bloqueo de receta retenida).";

        return back()->with('success', $msg);
    }
}
