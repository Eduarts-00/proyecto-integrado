@extends('layouts.app')

@section('title', 'Punto de Venta (POS) — FarmaNet Pro')

@section('content')
@php
    $tieneConvenioActivo = $selectedConvenio && $selectedConvenio->esta_vigente && $selectedConvenio->porcentaje_descuento > 0;
@endphp
<div class="grid grid-cols-1 xl:grid-cols-12 gap-6">

    <!-- Columna Principal POS (Buscador + Carro + Catálogo) -->
    <div class="xl:col-span-8 space-y-5">

        <!-- Barra de Búsqueda Predictiva y Escáner de Código de Barras -->
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                <form action="{{ route('pos.index') }}" method="GET" class="md:col-span-7 flex gap-2">
                    <div class="relative flex-1">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 text-xs">🔍</span>
                        <input type="text" name="q" value="{{ $search }}"
                               placeholder="Buscar medicamento por nombre o código..."
                               class="w-full pl-9 pr-3 py-2 text-xs rounded-lg border border-slate-300 focus:border-[#1E40AF] outline-none">
                    </div>
                    <button type="submit" class="px-3.5 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold">
                        Buscar
                    </button>
                    @if($search !== '')
                        <a href="{{ route('pos.index') }}" class="px-3 py-2 rounded-lg bg-slate-100 text-slate-500 text-xs">Limpiar</a>
                    @endif
                </form>

                <form action="{{ route('pos.add') }}" method="POST" class="md:col-span-5 flex gap-2">
                    @csrf
                    <div class="relative flex-1">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 text-xs">🏷️</span>
                        <input type="text" name="codigo_barras" required
                               placeholder="Escanear código de barras..."
                               class="w-full pl-9 pr-3 py-2 text-xs rounded-lg border border-slate-300 focus:border-[#1E40AF] font-mono-code outline-none">
                    </div>
                    <button type="submit" class="px-3.5 py-2 rounded-lg bg-[#1E40AF] hover:bg-blue-800 text-white text-xs font-semibold">
                        Escanear
                    </button>
                </form>
            </div>
        </div>

        <!-- Alerta de Bloqueo Sanitario ISP cuando hay medicamentos controlados en el carro -->
        @if($hasControlledItems && !$allControlledAuthorized)
            <div class="p-4 rounded-xl bg-red-50 border-2 border-[#EF4444] flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <span class="w-8 h-8 rounded-lg bg-[#EF4444] text-white flex items-center justify-center font-bold shrink-0">🔒</span>
                    <div>
                        <h3 class="text-xs font-extrabold uppercase tracking-wide text-red-900">
                            Venta Restringida — Requiere Receta Retenida
                        </h3>
                        <p class="text-xs text-red-700 mt-0.5">
                            El carro contiene medicamentos controlados. El cobro permanece bloqueado hasta contar con la autorización del Químico Farmacéutico.
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" onclick="document.getElementById('modal-qf-pin').classList.remove('hidden')"
                            class="px-3.5 py-2 rounded-lg bg-[#EF4444] hover:bg-red-700 text-white text-xs font-bold shadow-sm transition">
                        🔑 Autorizar en Caja
                    </button>
                    <button type="button" onclick="document.getElementById('modal-cola-isp').classList.remove('hidden')"
                            class="px-3 py-2 rounded-lg bg-white border border-red-300 hover:bg-red-100 text-red-800 text-xs font-semibold transition">
                        📤 Enviar a Bandeja Q.F.
                    </button>
                </div>
            </div>
        @endif

        <!-- Tabla Dinámica de Carro de Compras -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            @if(empty($cartDetailed))
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 text-[11px] font-bold uppercase text-slate-400 bg-slate-50/50">
                                <th class="py-3 px-4">Medicamento / Cobertura Convenio</th>
                                <th class="py-3 px-4">Lote Vitrina</th>
                                <th class="py-3 px-4 text-center">Cant.</th>
                                <th class="py-3 px-4 text-right">P. Unitario</th>
                                <th class="py-3 px-4 text-right">Bonificación</th>
                                <th class="py-3 px-4 text-right">Copago Línea</th>
                                <th class="py-3 px-4 text-center">Estado ISP</th>
                            </tr>
                        </thead>
                    </table>
                </div>
                <div class="py-16 px-6 text-center">
                    <div class="text-3xl mb-2 text-slate-300">🛒</div>
                    <p class="text-xs font-medium text-slate-400">
                        El carro está vacío. Busca o escanea un medicamento.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 text-[11px] font-bold uppercase text-slate-400 bg-slate-50/50">
                                <th class="py-3 px-4">Medicamento / Cobertura Convenio</th>
                                <th class="py-3 px-4">Lote Vitrina</th>
                                <th class="py-3 px-4 text-center">Cant.</th>
                                <th class="py-3 px-4 text-right">P. Unitario</th>
                                <th class="py-3 px-4 text-right">Bonificación</th>
                                <th class="py-3 px-4 text-right">Copago Línea</th>
                                <th class="py-3 px-4 text-center">Estado ISP</th>
                                <th class="py-3 px-4 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            @foreach($cartDetailed as $item)
                                @php
                                    /** @var \App\Models\Medication $med */
                                    $med = $item['medicamento'];
                                    /** @var \App\Models\Batch|null $lote */
                                    $lote = $item['lote_fefo'];
                                    $cubierto = $item['cubierto_convenio'] ?? false;
                                    $pctLinea = $item['porcentaje_convenio'] ?? 0;
                                    $dctoLinea = $item['descuento_linea'] ?? 0;
                                    $copagoLinea = $item['total_copago_linea'] ?? $item['subtotal'];
                                @endphp
                                <tr class="hover:bg-slate-50/80">
                                    <td class="py-3 px-4">
                                        <div class="font-semibold text-slate-900">{{ $med->nombre_comercial }}</div>
                                        <div class="text-[11px] text-slate-400 font-mono-code">{{ $med->codigo_barras }} • {{ $med->laboratorio }}</div>
                                        @if($tieneConvenioActivo)
                                            <div class="mt-1">
                                                @if($cubierto)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold">
                                                        ✓ Cubierto por {{ $selectedConvenio->nombre_convenio }} (-{{ number_format($pctLinea, 0) }}%)
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-semibold">
                                                        ✕ Fuera de vademécum {{ $selectedConvenio->nombre_convenio }} (Precio normal)
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">
                                        @if($lote)
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-blue-50 text-blue-800 border border-blue-200 font-mono-code text-[11px]">
                                                {{ $lote->lote_codigo }}
                                            </span>
                                        @else
                                            <span class="text-red-500 font-semibold text-[11px]">Sin lote vitrina</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-center font-bold text-slate-800">
                                        {{ $item['cantidad'] }}
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono-code text-slate-600">
                                        ${{ number_format($item['precio_unitario'], 0, ',', '.') }}
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono-code">
                                        @if($dctoLinea > 0)
                                            <span class="text-emerald-600 font-bold">-${{ number_format($dctoLinea, 0, ',', '.') }}</span>
                                        @else
                                            <span class="text-slate-400">$0</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono-code">
                                        @if($dctoLinea > 0)
                                            <div class="text-[10px] text-slate-400 line-through">${{ number_format($item['subtotal'], 0, ',', '.') }}</div>
                                        @endif
                                        <div class="font-bold text-slate-900">${{ number_format($copagoLinea, 0, ',', '.') }}</div>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        @if($item['es_controlado'])
                                            @if($item['autorizacion_isp'])
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                                    ✓ Autorizado ({{ $item['autorizacion_isp']->folio_receta }})
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-[#EF4444] border border-red-300">
                                                    ▲ Requiere Receta
                                                </span>
                                            @endif
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                ✓ Venta Libre
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <form action="{{ route('pos.remove', $med->id_medicamento) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-500 hover:text-red-700 font-semibold text-xs">
                                                Quitar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Catálogo de Medicamentos Disponibles -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-100">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600">
                        Catálogo de Medicamentos Disponibles ({{ $medicamentos->count() }} productos)
                    </h3>
                    <span class="text-[11px] text-slate-400">
                        @if($tieneConvenioActivo)
                            Mostrando cobertura de vademécum para <strong>{{ $selectedConvenio->nombre_convenio }}</strong>
                        @else
                            Selecciona un producto o filtra por categoría terapéutica
                        @endif
                    </span>
                </div>

                <form action="{{ route('pos.index') }}" method="GET" class="flex flex-wrap items-center gap-2 text-xs">
                    @if($search !== '')
                        <input type="hidden" name="q" value="{{ $search }}">
                    @endif
                    <select name="categoria" onchange="this.form.submit()"
                            class="px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white text-[11px] font-medium text-slate-700">
                        <option value="">Todas las categorías ({{ $categoriasDisponibles->count() ?? 0 }})</option>
                        @foreach($categoriasDisponibles ?? [] as $cat)
                            <option value="{{ $cat }}" {{ ($categoriaFiltro ?? '') === $cat ? 'selected' : '' }}>
                                {{ $cat }}
                            </option>
                        @endforeach
                    </select>

                    @if($tieneConvenioActivo)
                        <label class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border {{ ($soloConvenio ?? false) ? 'border-blue-300 bg-blue-50 text-[#1E40AF] font-bold' : 'border-slate-300 bg-white text-slate-600' }} text-[11px] cursor-pointer">
                            <input type="checkbox" name="solo_convenio" value="1" {{ ($soloConvenio ?? false) ? 'checked' : '' }}
                                   onchange="this.form.submit()" class="rounded border-slate-300 text-[#1E40AF]">
                            <span>Solo cubiertos por convenio</span>
                        </label>
                    @endif

                    @if(($categoriaFiltro ?? '') !== '' || ($soloConvenio ?? false))
                        <a href="{{ route('pos.index', $search !== '' ? ['q' => $search] : []) }}"
                           class="px-2.5 py-1.5 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 text-[11px]">
                            Ver todos
                        </a>
                    @endif
                </form>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($medicamentos as $med)
                    @php
                        $stockVitrina = $med->stock_vitrina;
                        $primerLote = $med->lotes->first();
                        $pctCat = $tieneConvenioActivo ? $selectedConvenio->obtenerPorcentajeParaMedicamento($med) : 0.0;
                        $precioConDcto = $pctCat > 0 ? round($med->precio_unitario * (1 - ($pctCat / 100)), 0) : $med->precio_unitario;
                    @endphp
                    <div class="p-3.5 rounded-xl border {{ $med->tipo_controlado ? 'border-red-200 bg-red-50/20' : 'border-slate-200 bg-slate-50/40' }} flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-xs text-slate-900 truncate">{{ $med->nombre_comercial }}</span>
                                @if($med->tipo_controlado || $med->requiere_receta)
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-red-100 text-[#EF4444] border border-red-200">
                                        ▲ RECETA RETENIDA
                                    </span>
                                @else
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-emerald-100 text-emerald-700">
                                        OTC
                                    </span>
                                @endif

                                @if($tieneConvenioActivo)
                                    @if($pctCat > 0)
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-blue-100 text-[#1E40AF] border border-blue-200">
                                            🏷️ -{{ number_format($pctCat, 0) }}% Convenio
                                        </span>
                                    @else
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-medium bg-slate-200 text-slate-600">
                                            Sin cobertura
                                        </span>
                                    @endif
                                @endif
                            </div>
                            <div class="text-[11px] text-slate-500 mt-0.5 truncate">
                                {{ $med->principio_activo }} • <strong>{{ $med->laboratorio }}</strong> • <span class="text-slate-400">{{ $med->categoria }}</span>
                            </div>
                            <div class="text-[10px] font-mono-code text-slate-400">
                                EAN: {{ $med->codigo_barras }}
                            </div>
                            <div class="text-[11px] mt-1 flex items-center gap-2">
                                @if($stockVitrina > 0 && $primerLote)
                                    <span class="text-emerald-700 font-semibold">Vitrina: {{ $stockVitrina }} u.</span>
                                    <span class="text-slate-400">|</span>
                                    <span class="font-mono-code text-[10px] text-blue-700">Lote: {{ $primerLote->lote_codigo }}</span>
                                @else
                                    <span class="text-red-600 font-bold">⚠️ Sin stock en Vitrina (Bodega: {{ $med->stock_bodega }} u.)</span>
                                @endif
                            </div>
                        </div>

                        <div class="text-right shrink-0">
                            @if($pctCat > 0)
                                <div class="text-[10px] font-mono-code text-slate-400 line-through">
                                    ${{ number_format($med->precio_unitario, 0, ',', '.') }}
                                </div>
                                <div class="font-mono-code font-bold text-sm text-emerald-700 mb-1.5">
                                    ${{ number_format($precioConDcto, 0, ',', '.') }}
                                </div>
                            @else
                                <div class="font-mono-code font-bold text-sm text-slate-900 mb-1.5">
                                    ${{ number_format($med->precio_unitario, 0, ',', '.') }}
                                </div>
                            @endif

                            @if($stockVitrina > 0)
                                <form action="{{ route('pos.add') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="id_medicamento" value="{{ $med->id_medicamento }}">
                                    <input type="hidden" name="cantidad" value="1">
                                    <button type="submit"
                                            class="px-3 py-1.5 rounded-lg text-[11px] font-semibold {{ ($med->tipo_controlado || $med->requiere_receta) ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-[#1E40AF] hover:bg-blue-800 text-white' }} transition">
                                        + Agregar
                                    </button>
                                </form>
                            @else
                                <span class="px-2.5 py-1 rounded bg-slate-200 text-slate-500 text-[10px] font-semibold">
                                    Sin Stock
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Columna Derecha: Convenio de Salud y Resumen de Cobro -->
    <div class="xl:col-span-4 space-y-5">
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm flex flex-col justify-between">
            <div>
                <h3 class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">
                    Convenio de Salud (Vademécum por Medicamento)
                </h3>
                <form action="{{ route('pos.discount') }}" method="POST" class="mb-3">
                    @csrf
                    <select name="id_convenio" onchange="this.form.submit()"
                            class="w-full px-3 py-2.5 rounded-lg border border-slate-300 text-xs font-medium text-slate-800 focus:border-[#1E40AF] outline-none">
                        @foreach($convenios as $conv)
                            <option value="{{ $conv->id_convenio }}" {{ ($selectedConvenio?->id_convenio == $conv->id_convenio) ? 'selected' : '' }}>
                                {{ $conv->nombre_convenio }} ({{ number_format($conv->porcentaje_descuento, 0) }}% bonificación)
                            </option>
                        @endforeach
                    </select>
                </form>

                @if($tieneConvenioActivo)
                    <div class="mb-5 p-3 rounded-lg bg-blue-50/70 border border-blue-200 text-[11px] text-slate-700 space-y-1">
                        <div class="font-bold text-[#1E40AF] flex items-center justify-between">
                            <span>{{ $selectedConvenio->nombre_convenio }}</span>
                            <span>{{ number_format($selectedConvenio->porcentaje_descuento, 0) }}% dcto.</span>
                        </div>
                        @if($selectedConvenio->aplica_todo_catalogo)
                            <p class="text-slate-600">🌐 Cobertura general sobre todo el catálogo farmacéutico.</p>
                        @else
                            <p class="text-slate-600">
                                💊 <strong>Vademécum activo ({{ $selectedConvenio->medicamentos->count() }} medicamentos adheridos):</strong>
                                el descuento se aplica exclusivamente a los fármacos incluidos en el petitorio del convenio.
                            </p>
                        @endif
                    </div>
                @endif

                <h3 class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-3">
                    Resumen de Cobro y Copago
                </h3>
                <div class="space-y-2.5 text-xs border-b border-slate-200 pb-4">
                    <div class="flex justify-between text-slate-600">
                        <span>Subtotal Bruto ({{ count($cartDetailed) }} ítems)</span>
                        <span class="font-mono-code font-semibold">${{ number_format($liquidacion['subtotal'], 0, ',', '.') }}</span>
                    </div>

                    @if($tieneConvenioActivo && !empty($cartDetailed))
                        <div class="flex justify-between text-slate-500 text-[11px]">
                            <span>• Productos afectos a convenio ({{ $liquidacion['items_cubiertos_count'] ?? 0 }})</span>
                            <span class="font-mono-code">${{ number_format($liquidacion['monto_afecto_convenio'] ?? 0, 0, ',', '.') }}</span>
                        </div>
                        @if(($liquidacion['monto_no_cubierto'] ?? 0) > 0)
                            <div class="flex justify-between text-amber-700 text-[11px]">
                                <span>• Productos fuera de vademécum ({{ $liquidacion['items_no_cubiertos_count'] ?? 0 }})</span>
                                <span class="font-mono-code">${{ number_format($liquidacion['monto_no_cubierto'] ?? 0, 0, ',', '.') }}</span>
                            </div>
                        @endif
                    @endif

                    @if($liquidacion['descuento_total'] > 0)
                        <div class="flex justify-between text-emerald-600 font-semibold">
                            <span>Bonificación ({{ $selectedConvenio?->nombre_convenio }})</span>
                            <span class="font-mono-code">-${{ number_format($liquidacion['descuento_total'], 0, ',', '.') }}</span>
                        </div>
                    @elseif($tieneConvenioActivo && !empty($cartDetailed))
                        <div class="flex justify-between text-amber-700 font-medium text-[11px]">
                            <span>Bonificación ({{ $selectedConvenio?->nombre_convenio }})</span>
                            <span class="font-mono-code">$0 (Ningún ítem en vademécum)</span>
                        </div>
                    @endif

                    <div class="flex justify-between text-slate-400 text-[11px]">
                        <span>IVA 19% (incluido)</span>
                        <span class="font-mono-code">${{ number_format($liquidacion['iva'], 0, ',', '.') }}</span>
                    </div>
                </div>

                <div class="py-4 flex items-center justify-between">
                    <span class="text-sm font-extrabold text-slate-900">TOTAL A PAGAR</span>
                    <span class="text-2xl font-extrabold font-mono-code text-[#1E40AF]">
                        ${{ number_format($liquidacion['total'], 0, ',', '.') }}
                    </span>
                </div>
            </div>

            <!-- Botones de Acción de Caja -->
            <div class="mt-4 space-y-2.5">
                @if(empty($cartDetailed))
                    <button type="button" disabled
                            class="w-full py-3 px-4 rounded-xl bg-slate-200 text-slate-400 font-bold text-xs cursor-not-allowed">
                        💳 Procesar Pago y Emitir Boleta
                    </button>
                @elseif($hasControlledItems && !$allControlledAuthorized)
                    <button type="button" disabled
                            class="w-full py-3 px-4 rounded-xl bg-red-100 border border-red-300 text-[#EF4444] font-bold text-xs cursor-not-allowed">
                        🔒 Cobro Bloqueado — Requiere Autorización Q.F.
                    </button>
                    <button type="button" onclick="document.getElementById('modal-qf-pin').classList.remove('hidden')"
                            class="w-full py-2.5 px-4 rounded-xl bg-[#EF4444] hover:bg-red-700 text-white font-bold text-xs shadow-sm transition">
                        🛡️ Autorizar Receta con PIN Q.F.
                    </button>
                @else
                    <form action="{{ route('pos.checkout') }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="w-full py-3 px-4 rounded-xl bg-[#1E40AF] hover:bg-blue-800 text-white font-bold text-xs shadow-md transition">
                            💳 Procesar Pago y Emitir Boleta
                        </button>
                    </form>
                @endif

                <form action="{{ route('pos.clear') }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full py-2 text-center text-xs text-slate-400 hover:text-slate-600 font-medium">
                        Vaciar carro
                    </button>
                </form>
            </div>
        </div>

        <!-- Comprobante de Boleta Emitida -->
        @if(session('boleta_emitida'))
            @php /** @var \App\Models\Sale $boleta */ $boleta = session('boleta_emitida'); @endphp
            <div class="bg-emerald-50/90 rounded-xl border border-emerald-300 p-4 shadow-sm text-xs space-y-2">
                <div class="flex items-center justify-between border-b border-emerald-200 pb-2">
                    <span class="font-extrabold text-emerald-900">🧾 BOLETA ELECTRÓNICA #{{ $boleta->id_venta }}</span>
                    <span class="px-2 py-0.5 rounded bg-emerald-200 text-emerald-900 font-bold text-[10px]">EMITIDA</span>
                </div>
                <div class="text-[11px] text-emerald-800 space-y-1">
                    <div><strong>Vendedor:</strong> {{ $boleta->usuario?->nombre_completo }}</div>
                    <div><strong>Convenio:</strong> {{ $boleta->convenio?->nombre_convenio ?? 'Sin convenio' }}</div>
                    <div><strong>Descuento Aplicado:</strong> <span class="font-mono-code">-${{ number_format($boleta->descuento_total, 0, ',', '.') }}</span></div>
                    <div><strong>Total Pagado:</strong> <span class="font-mono-code font-bold">${{ number_format($boleta->total, 0, ',', '.') }}</span></div>
                </div>
            </div>
        @endif
    </div>

</div>

<!-- Modal de Autorización Q.F. en Mostrador -->
<div id="modal-qf-pin" class="hidden fixed inset-0 z-50 bg-slate-900/70 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full border-2 border-[#EF4444] shadow-2xl overflow-hidden">
        <div class="bg-[#EF4444] text-white px-5 py-4 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="text-lg">🛡️</span>
                <div>
                    <h3 class="font-extrabold text-sm">Autorización Químico Farmacéutico</h3>
                    <p class="text-[11px] text-red-100">Validación de Receta Retenida — Registro ISP</p>
                </div>
            </div>
            <button type="button" onclick="document.getElementById('modal-qf-pin').classList.add('hidden')" class="text-white font-bold">✕</button>
        </div>

        <form action="{{ route('pos.checkout') }}" method="POST" class="p-5 space-y-3.5 text-xs">
            @csrf
            <div>
                <label class="block font-bold text-slate-700 mb-1">Químico Farmacéutico Autorizador</label>
                <select name="autorizacion_isp[qf_user_id]" class="w-full px-3 py-2 rounded-lg border border-slate-300">
                    @foreach($qfUsers as $qf)
                        <option value="{{ $qf->id_usuario }}">{{ $qf->nombre_completo }} ({{ $qf->rut }})</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">RUN del Paciente</label>
                    <input type="text" name="autorizacion_isp[rut_paciente]" value="18.455.120-K" required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">RUN del Médico Prescriptor</label>
                    <input type="text" name="autorizacion_isp[rut_medico]" value="09.876.543-2" required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Folio Receta Retenida</label>
                    <input type="text" name="autorizacion_isp[folio_receta]" value="REC-2026-{{ rand(8850, 9999) }}" required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                </div>
                <div>
                    <label class="block font-bold text-[#EF4444] mb-1">PIN de Autorización Q.F.</label>
                    <input type="password" name="autorizacion_isp[pin_qf]" value="4421" required
                           placeholder="Ingrese PIN"
                           class="w-full px-3 py-2 rounded-lg border-2 border-red-300 bg-red-50/40 font-mono-code font-bold text-center">
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('modal-qf-pin').classList.add('hidden')"
                        class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600 font-semibold">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-[#EF4444] hover:bg-red-700 text-white font-bold shadow-md">
                    ✓ Autorizar y Emitir Boleta
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para Enviar a la Bandeja del Q.F. -->
<div id="modal-cola-isp" class="hidden fixed inset-0 z-50 bg-slate-900/70 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full border border-slate-200 shadow-2xl overflow-hidden">
        <div class="bg-[#0F172A] text-white px-5 py-4 flex items-center justify-between">
            <h3 class="font-bold text-sm">📤 Enviar Receta a Bandeja del Químico Farmacéutico</h3>
            <button type="button" onclick="document.getElementById('modal-cola-isp').classList.add('hidden')" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form action="{{ route('pos.request_isp') }}" method="POST" class="p-5 space-y-3.5 text-xs">
            @csrf
            <div>
                <label class="block font-bold text-slate-700 mb-1">Medicamento Controlado en Carro</label>
                <select name="id_medicamento" class="w-full px-3 py-2 rounded-lg border border-slate-300">
                    @foreach($cartDetailed as $item)
                        @if($item['es_controlado'])
                            <option value="{{ $item['medicamento']->id_medicamento }}">
                                {{ $item['medicamento']->nombre_comercial }} (Cant: {{ $item['cantidad'] }})
                            </option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-1">Folio Receta Retenida</label>
                <input type="text" name="folio_receta" value="REC-2026-{{ rand(8850, 9999) }}" required
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">RUN del Paciente</label>
                    <input type="text" name="rut_paciente" value="18.455.120-K" required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">RUN del Médico</label>
                    <input type="text" name="rut_medico" value="09.876.543-2" required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('modal-cola-isp').classList.add('hidden')"
                        class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-[#1E40AF] text-white font-bold">
                    Enviar a Bandeja Q.F.
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
