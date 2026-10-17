<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Medication;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use RuntimeException;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    public function index(Request $request)
    {
        $ubicacion = strtoupper((string) $request->query('ubicacion', 'VITRINA'));
        if (! in_array($ubicacion, ['VITRINA', 'BODEGA', 'TODOS'], true)) {
            $ubicacion = 'VITRINA';
        }

        $query = Batch::with('medicamento')
            ->where('cantidad', '>', 0)
            ->orderBy('fecha_vencimiento', 'asc')
            ->orderBy('id_lote', 'asc');

        if ($ubicacion !== 'TODOS') {
            $query->where('ubicacion', $ubicacion);
        }

        $lotes = $query->get();
        $alertasFefo = $this->inventoryService->checkExpiryAlerts(30);
        $lotesBodega = Batch::with('medicamento')
            ->where('ubicacion', 'BODEGA')
            ->where('cantidad', '>', 0)
            ->orderBy('fecha_vencimiento', 'asc')
            ->get();

        $medicamentos = Medication::orderBy('nombre_comercial')->get();

        return view('inventario.index', compact(
            'lotes',
            'ubicacion',
            'alertasFefo',
            'lotesBodega',
            'medicamentos'
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
}
