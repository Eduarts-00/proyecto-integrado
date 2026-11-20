<?php

namespace App\Http\Controllers;

use App\Models\HealthAgreement;
use App\Models\IspLog;
use App\Models\Medication;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\RutValidator;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $saleService,
        private readonly InventoryService $inventoryService
    ) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $categoriaFiltro = trim((string) $request->query('categoria', ''));
        $soloConvenio = (bool) $request->boolean('solo_convenio', false);

        $categoriasDisponibles = Medication::where('activo', true)
            ->select('categoria')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria')
            ->filter()
            ->values();

        $medicamentosQuery = Medication::where('activo', true)->with(['lotes' => function ($q) {
            $q->where('activo', true)
                ->where('ubicacion', 'VITRINA')
                ->where('cantidad', '>', 0)
                ->whereDate('fecha_vencimiento', '>', now()->toDateString())
                ->orderBy('fecha_vencimiento', 'asc');
        }])->orderBy('nombre_comercial');

        if ($search !== '') {
            $medicamentosQuery->where(function ($q) use ($search) {
                $q->where('nombre_comercial', 'like', "%{$search}%")
                    ->orWhere('principio_activo', 'like', "%{$search}%")
                    ->orWhere('laboratorio', 'like', "%{$search}%")
                    ->orWhere('categoria', 'like', "%{$search}%")
                    ->orWhere('codigo_barras', 'like', "%{$search}%");
            });
        }

        if ($categoriaFiltro !== '') {
            $medicamentosQuery->where('categoria', $categoriaFiltro);
        }

        $medicamentos = $medicamentosQuery->get();
        $convenios = HealthAgreement::with('medicamentos')->vigentes()->orderBy('porcentaje_descuento', 'desc')->get();

        $cart = session('pos_cart', []);
        $selectedConvenioId = session('pos_convenio_id');
        $selectedConvenio = $selectedConvenioId ? HealthAgreement::with('medicamentos')->find($selectedConvenioId) : null;

        if ($soloConvenio && $selectedConvenio && $selectedConvenio->porcentaje_descuento > 0) {
            $medicamentos = $medicamentos->filter(fn (Medication $m) => $selectedConvenio->cubreMedicamento($m))->values();
        }

        $subtotal = 0.0;
        $hasControlledItems = false;
        $cartDetailed = [];

        foreach ($cart as $idMed => $row) {
            $med = Medication::find($idMed);
            if (! $med) {
                continue;
            }
            $fefoBatch = $this->inventoryService->getFefoBatch($med->id_medicamento, 'VITRINA');
            $qty = (int) $row['cantidad'];
            $lineSubtotal = round($med->precio_unitario * $qty, 0);
            $subtotal += $lineSubtotal;

            $pctConvenioMed = $selectedConvenio ? $selectedConvenio->obtenerPorcentajeParaMedicamento($med) : 0.0;
            $descuentoLinea = round($lineSubtotal * ($pctConvenioMed / 100), 0);
            $copagoLinea = max(0.0, $lineSubtotal - $descuentoLinea);

            $isControlled = $med->tipo_controlado || $med->requiere_receta;
            if ($isControlled) {
                $hasControlledItems = true;
            }

            // Verifica si existe una autorización ISP aprobada para este medicamento hoy sin usar
            $authorizedIsp = null;
            if ($isControlled) {
                $authorizedIsp = IspLog::where('id_medicamento', $med->id_medicamento)
                    ->where('estado', 'AUTORIZADO')
                    ->whereNull('id_venta')
                    ->latest('id_registro')
                    ->first();
            }

            $cartDetailed[] = [
                'medicamento' => $med,
                'lote_fefo' => $fefoBatch,
                'cantidad' => $qty,
                'precio_unitario' => $med->precio_unitario,
                'subtotal' => $lineSubtotal,
                'cubierto_convenio' => $pctConvenioMed > 0,
                'porcentaje_convenio' => $pctConvenioMed,
                'descuento_linea' => $descuentoLinea,
                'total_copago_linea' => $copagoLinea,
                'es_controlado' => $isControlled,
                'autorizacion_isp' => $authorizedIsp,
            ];
        }

        $allControlledAuthorized = true;
        foreach ($cartDetailed as $item) {
            if ($item['es_controlado'] && ! $item['autorizacion_isp']) {
                $allControlledAuthorized = false;
                break;
            }
        }

        $liquidacion = $this->saleService->calculateCartCopay($cartDetailed, $selectedConvenio);
        $pendientesIspHoy = IspLog::with(['medicamento', 'vendedor'])
            ->where('estado', 'PENDIENTE')
            ->latest('id_registro')
            ->take(5)
            ->get();

        $autorizadasDisponibles = IspLog::with(['medicamento', 'quimicoAutorizador'])
            ->where('estado', 'AUTORIZADO')
            ->whereNull('id_venta')
            ->latest('id_registro')
            ->get();

        $qfUsers = User::whereHas('rol', fn ($q) => $q->where('codigo_rol', 'quimico_farmaceutico'))->get();

        $tieneConvActivo = $selectedConvenio && $selectedConvenio->esta_vigente && $selectedConvenio->porcentaje_descuento > 0;
        $catalogoAutocompletado = Medication::where('activo', true)
            ->with(['lotes' => function ($q) {
                $q->where('activo', true)
                    ->where('ubicacion', 'VITRINA')
                    ->where('cantidad', '>', 0)
                    ->whereDate('fecha_vencimiento', '>', now()->toDateString())
                    ->orderBy('fecha_vencimiento', 'asc');
            }])
            ->orderBy('nombre_comercial')
            ->get()
            ->map(function (Medication $m) use ($tieneConvActivo, $selectedConvenio) {
                $pct = $tieneConvActivo ? $selectedConvenio->obtenerPorcentajeParaMedicamento($m) : 0.0;
                $precioFinal = $pct > 0 ? round($m->precio_unitario * (1 - ($pct / 100)), 0) : $m->precio_unitario;

                return [
                    'id_medicamento' => $m->id_medicamento,
                    'codigo_barras' => $m->codigo_barras,
                    'nombre_comercial' => $m->nombre_comercial,
                    'principio_activo' => $m->principio_activo,
                    'laboratorio' => $m->laboratorio,
                    'categoria' => $m->categoria ?: 'General',
                    'precio_unitario' => (int) round($m->precio_unitario),
                    'precio_con_descuento' => (int) round($precioFinal),
                    'pct_convenio' => (int) round($pct),
                    'stock_vitrina' => (int) $m->stock_vitrina,
                    'stock_bodega' => (int) $m->stock_bodega,
                    'es_controlado' => (bool) ($m->tipo_controlado || $m->requiere_receta),
                    'lote_codigo' => $m->lotes->first()?->lote_codigo ?? '',
                ];
            })
            ->values();

        return view('pos.index', compact(
            'medicamentos',
            'catalogoAutocompletado',
            'convenios',
            'cartDetailed',
            'selectedConvenio',
            'liquidacion',
            'hasControlledItems',
            'allControlledAuthorized',
            'pendientesIspHoy',
            'autorizadasDisponibles',
            'qfUsers',
            'search',
            'categoriasDisponibles',
            'categoriaFiltro',
            'soloConvenio'
        ));
    }

    public function addItem(Request $request)
    {
        $data = $request->validate([
            'id_medicamento' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'codigo_barras' => ['nullable', 'string', 'max:60'],
            'cantidad' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        $med = null;
        $cantidadMultiplicador = null;
        if (! empty($data['id_medicamento'])) {
            $med = Medication::where('activo', true)->find($data['id_medicamento']);
        } elseif (! empty($data['codigo_barras'])) {
            $term = trim($data['codigo_barras']);
            // Soporte para multiplicador de pistola láser farmacéutica (ej: "3*780000100104" o "5x780000100104")
            if (preg_match('/^(\d{1,3})\s*[\*xX]\s*(.+)$/', $term, $matches)) {
                $cantidadMultiplicador = min(999, max(1, (int) $matches[1]));
                $term = trim($matches[2]);
            }
            $med = Medication::where('activo', true)
                ->where(function ($q) use ($term) {
                    $q->where('codigo_barras', $term)
                        ->orWhere('nombre_comercial', 'like', "%{$term}%");
                })
                ->first();
        }

        if (! $med) {
            return back()->with('error', 'Medicamento no encontrado en el catálogo.');
        }

        $cantidadAgregar = $cantidadMultiplicador ?? (int) ($data['cantidad'] ?? 1);
        $cart = session('pos_cart', []);
        $cantidadActual = isset($cart[$med->id_medicamento]) ? (int) $cart[$med->id_medicamento]['cantidad'] : 0;
        $nuevaCantidad = $cantidadActual + $cantidadAgregar;

        if ($med->stock_vitrina < $nuevaCantidad) {
            return back()->with('error', "Stock insuficiente en Vitrina para {$med->nombre_comercial} (Disponible: {$med->stock_vitrina}). Solicite traspaso FEFO desde Bodega.");
        }

        $cart[$med->id_medicamento] = [
            'id_medicamento' => $med->id_medicamento,
            'cantidad' => $nuevaCantidad,
        ];

        session(['pos_cart' => $cart]);

        $msg = "{$cantidadAgregar} u. de {$med->nombre_comercial} agregada(s) al carro (Total: {$nuevaCantidad} u.).";
        if ($med->tipo_controlado || $med->requiere_receta) {
            return redirect()->route('pos.index')->with('warning', "ALERTA ISP: {$med->nombre_comercial} es un medicamento controlado. El cobro ha sido bloqueado hasta contar con Autorización del Químico Farmacéutico.");
        }

        return redirect()->route('pos.index')->with('success', $msg);
    }

    public function updateItemQuantity(Request $request, int $idMedicamento)
    {
        $data = $request->validate([
            'cantidad' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        $nuevaCantidad = (int) $data['cantidad'];
        $cart = session('pos_cart', []);

        if ($nuevaCantidad <= 0) {
            unset($cart[$idMedicamento]);
            session(['pos_cart' => $cart]);

            return redirect()->route('pos.index')->with('success', 'Medicamento retirado del carro.');
        }

        $med = Medication::where('activo', true)->find($idMedicamento);
        if (! $med) {
            return back()->with('error', 'Medicamento no encontrado.');
        }

        if ($med->stock_vitrina < $nuevaCantidad) {
            return back()->with('error', "Stock insuficiente en Vitrina para {$med->nombre_comercial} (Máximo disponible: {$med->stock_vitrina} u.).");
        }

        $cart[$idMedicamento] = [
            'id_medicamento' => $idMedicamento,
            'cantidad' => $nuevaCantidad,
        ];
        session(['pos_cart' => $cart]);

        return redirect()->route('pos.index');
    }

    public function removeItem(int $idMedicamento)
    {
        $cart = session('pos_cart', []);
        unset($cart[$idMedicamento]);
        session(['pos_cart' => $cart]);

        return redirect()->route('pos.index')->with('success', 'Ítem retirado del carro.');
    }

    public function applyHealthDiscount(Request $request)
    {
        $request->validate([
            'id_convenio' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ]);
        $idConvenio = $request->input('id_convenio');
        session(['pos_convenio_id' => $idConvenio ?: null]);

        return redirect()->route('pos.index')->with('success', 'Convenio de salud actualizado en tiempo real.');
    }

    public function clearCart()
    {
        session()->forget(['pos_cart', 'pos_convenio_id']);

        return redirect()->route('pos.index')->with('success', 'Carro de compras vaciado.');
    }

    /**
     * Envía una solicitud de validación de receta retenida a la Bandeja en vivo del Q.F. (Pantalla 4).
     */
    public function requestPrescriptionQueue(Request $request)
    {
        $data = $request->validate([
            'id_medicamento' => ['required', 'integer', 'min:1', 'max:2147483647', 'exists:medicamentos,id_medicamento'],
            'rut_paciente' => ['required', 'string', 'max:15'],
            'rut_medico' => ['required', 'string', 'max:15'],
            'folio_receta' => ['required', 'string', 'max:60'],
        ]);

        $med = Medication::findOrFail($data['id_medicamento']);
        $loteFefo = $this->inventoryService->getFefoBatch($med->id_medicamento, 'VITRINA');
        $cart = session('pos_cart', []);
        $cantidad = isset($cart[$med->id_medicamento]) ? (int) $cart[$med->id_medicamento]['cantidad'] : 1;

        IspLog::create([
            'id_medicamento' => $med->id_medicamento,
            'id_lote' => $loteFefo?->id_lote,
            'id_vendedor' => Auth::id(),
            'cantidad' => $cantidad,
            'folio_receta' => strtoupper(trim($data['folio_receta'])),
            'rut_paciente' => RutValidator::format($data['rut_paciente']),
            'rut_medico' => RutValidator::format($data['rut_medico']),
            'estado' => 'PENDIENTE',
        ]);

        return redirect()->route('pos.index')->with(
            'success',
            "Solicitud enviada a la Bandeja de Autorización Q.F. para {$med->nombre_comercial} (Folio {$data['folio_receta']})."
        );
    }

    /**
     * Procesa el cobro y emite la boleta aplicando descuento de stock FEFO y bloqueo ACID.
     */
    public function processCheckout(Request $request)
    {
        try {
            $items = $request->input('items');
            if (! is_array($items) || empty($items)) {
                $cart = session('pos_cart', []);
                $items = array_values($cart);
            }

            $idConvenio = $request->input('id_convenio', session('pos_convenio_id'));
            $autorizacionIsp = $request->input('autorizacion_isp');

            // Si no viene autorización manual en request, revisamos si hay una autorización Q.F. aprobada en cola
            if (empty($autorizacionIsp)) {
                foreach ($items as $it) {
                    $med = Medication::find($it['id_medicamento'] ?? 0);
                    if ($med && ($med->tipo_controlado || $med->requiere_receta)) {
                        $aprobada = IspLog::where('id_medicamento', $med->id_medicamento)
                            ->where('estado', 'AUTORIZADO')
                            ->whereNull('id_venta')
                            ->latest('id_registro')
                            ->first();

                        if ($aprobada) {
                            $autorizacionIsp = [
                                'id_registro' => $aprobada->id_registro,
                            ];
                        }
                    }
                }
            }

            $venta = $this->saleService->executeTransaction(
                $items,
                $request->user(),
                $idConvenio ? (int) $idConvenio : null,
                $autorizacionIsp
            );

            session()->forget(['pos_cart', 'pos_convenio_id']);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'ok',
                    'message' => 'Boleta emitida exitosamente.',
                    'venta' => $venta,
                ], 200);
            }

            return redirect()->route('pos.index')
                ->with('success', "Boleta Electrónica #{$venta->id_venta} emitida con éxito por $" . number_format($venta->total, 0, ',', '.') . ".")
                ->with('boleta_emitida', $venta);
        } catch (RuntimeException $e) {
            $status = in_array($e->getCode(), [401, 403, 409, 422], true) ? $e->getCode() : 422;

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => $e->getMessage(),
                ], $status);
            }

            return redirect()->route('pos.index')->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Fallo en transacción transaccional POS', [
                'user' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json(['error' => 'Error interno procesando la venta. Notifique a soporte.'], 500);
            }

            return redirect()->route('pos.index')->with('error', 'Error interno procesando la venta. Notifique a soporte.');
        }
    }
}
