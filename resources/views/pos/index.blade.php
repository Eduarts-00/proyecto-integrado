@extends('layouts.app')

@section('title', 'Punto de Venta (POS) — FarmaNet Pro')

@section('content')
@php
    $tieneConvenioActivo = $selectedConvenio && $selectedConvenio->esta_vigente && $selectedConvenio->porcentaje_descuento > 0;
    $totalUnidadesCarro = collect($cartDetailed)->sum('cantidad');
@endphp

<div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">

    <!-- ========================================================================= -->
    <!-- COLUMNA IZQUIERDA (7 cols): BUSCADOR PREDICTIVO + CATÁLOGO DE PRODUCTOS   -->
    <!-- ========================================================================= -->
    <div class="xl:col-span-7 space-y-4">

        <!-- Barra Superior: Buscador con Autocompletado en Vivo + Escáner EAN -->
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm space-y-3">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">

                <!-- Buscador Predictivo con Sugerencias Instantáneas -->
                <div class="lg:col-span-7 relative" id="search-autocomplete-wrapper">
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                        Buscador de Medicamentos (Sugerencias en vivo)
                    </label>
                    <form action="{{ route('pos.index') }}" method="GET" class="flex gap-1.5" autocomplete="off">
                        @if(($categoriaFiltro ?? '') !== '')
                            <input type="hidden" name="categoria" value="{{ $categoriaFiltro }}">
                        @endif
                        @if($soloConvenio ?? false)
                            <input type="hidden" name="solo_convenio" value="1">
                        @endif
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 text-xs pointer-events-none">🔍</span>
                            <input type="text"
                                   id="pos-search-input"
                                   name="q"
                                   value="{{ $search }}"
                                   maxlength="100"
                                   autocomplete="off"
                                   placeholder="Escribe las primeras letras (ej: Paracetamol, Losartán, Amox...)"
                                   class="w-full pl-9 pr-8 py-2.5 text-xs rounded-lg border border-slate-300 focus:border-[#1E40AF] focus:ring-2 focus:ring-blue-100 outline-none font-medium text-slate-800">
                            <button type="button"
                                    id="btn-clear-search"
                                    onclick="clearLiveSearch()"
                                    class="{{ $search === '' ? 'hidden' : '' }} absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600 text-xs font-bold"
                                    title="Limpiar búsqueda">
                                ✕
                            </button>
                        </div>
                    </form>

                    <!-- Desplegable de Sugerencias de Autocompletado -->
                    <div id="autocomplete-dropdown"
                         class="hidden absolute left-0 right-0 top-full mt-1.5 z-40 bg-white rounded-xl border border-slate-200 shadow-2xl overflow-hidden">
                        <div class="px-3.5 py-2 bg-slate-50 border-b border-slate-200 flex items-center justify-between text-[11px]">
                            <span id="autocomplete-header-text" class="font-bold text-slate-600">Sugerencias encontradas</span>
                            <span class="text-slate-400">Elige cantidad y presiona + Agregar</span>
                        </div>
                        <div id="autocomplete-results" class="max-h-80 overflow-y-auto divide-y divide-slate-100"></div>
                    </div>
                </div>

                <!-- Lector de Código de Barras (Modo Global HID USB + Multiplicador N*EAN) -->
                <div class="lg:col-span-5">
                    <div class="flex items-center justify-between mb-1">
                        <label for="pos-barcode-input" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            Lector Código de Barras (EAN)
                        </label>
                        <span class="text-[10px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full"
                              title="Escucha global activa: puedes escanear directamente o presionar F2">
                            Escáner Activo (F2)
                        </span>
                    </div>
                    <form id="pos-barcode-form" action="{{ route('pos.add') }}" method="POST" class="flex gap-1.5" onsubmit="return handleBarcodeFormSubmit(event)">
                        @csrf
                        <div class="relative flex-1">
                            <input type="text"
                                   id="pos-barcode-input"
                                   name="codigo_barras"
                                   required
                                   maxlength="30"
                                   autocomplete="off"
                                   placeholder="Código EAN o 3*EAN..."
                                   class="w-full px-3 py-2.5 text-xs rounded-lg border border-slate-300 focus:border-[#1E40AF] focus:ring-2 focus:ring-blue-100 font-mono-code font-bold text-slate-900 outline-none transition">
                        </div>
                        <input type="number"
                               id="pos-barcode-qty"
                               name="cantidad"
                               min="1"
                               max="999"
                               value="1"
                               title="Cantidad de unidades a escanear (o usa 3*EAN)"
                               class="w-14 px-2 py-2.5 text-xs text-center font-mono-code font-bold rounded-lg border border-slate-300 focus:border-[#1E40AF] outline-none">
                        <button type="submit" class="px-3 py-2.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold shrink-0 transition">
                            + EAN
                        </button>
                    </form>
                </div>
            </div>

            <!-- Filtros Rápidos por Categoría Terapéutica y Cobertura de Convenio -->
            <div class="pt-2 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
                <form action="{{ route('pos.index') }}" method="GET" class="flex flex-wrap items-center gap-2 text-xs w-full sm:w-auto">
                    @if($search !== '')
                        <input type="hidden" name="q" value="{{ $search }}">
                    @endif
                    <select id="pos-category-select" name="categoria" onchange="filterCatalogInPage()"
                            class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-xs font-semibold text-slate-700">
                        <option value="">📂 Todas las categorías ({{ $categoriasDisponibles->count() ?? 0 }})</option>
                        @foreach($categoriasDisponibles ?? [] as $cat)
                            <option value="{{ $cat }}" {{ ($categoriaFiltro ?? '') === $cat ? 'selected' : '' }}>
                                {{ $cat }}
                            </option>
                        @endforeach
                    </select>

                    @if($tieneConvenioActivo)
                        <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50/70 text-[#1E40AF] font-bold text-xs cursor-pointer select-none">
                            <input type="checkbox" id="pos-only-covered" name="solo_convenio" value="1"
                                   {{ ($soloConvenio ?? false) ? 'checked' : '' }}
                                   onchange="filterCatalogInPage()"
                                   class="rounded border-blue-300 text-[#1E40AF]">
                            <span>🏷️ Solo cubiertos por {{ $selectedConvenio->nombre_convenio }}</span>
                        </label>
                    @endif
                </form>

                <span id="catalog-visible-counter" class="text-[11px] font-semibold text-slate-400">
                    Mostrando {{ $medicamentos->count() }} medicamentos
                </span>
            </div>
        </div>

        <!-- Catálogo de Medicamentos con Selector de Cantidad Directa -->
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div id="catalog-grid" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($medicamentos as $med)
                    @php
                        $stockVitrina = $med->stock_vitrina;
                        $primerLote = $med->lotes->first();
                        $pctCat = $tieneConvenioActivo ? $selectedConvenio->obtenerPorcentajeParaMedicamento($med) : 0.0;
                        $precioConDcto = $pctCat > 0 ? round($med->precio_unitario * (1 - ($pctCat / 100)), 0) : $med->precio_unitario;
                        $esRestringido = $med->tipo_controlado || $med->requiere_receta;
                    @endphp
                    <div class="catalog-card p-3.5 rounded-xl border {{ $esRestringido ? 'border-red-200 bg-red-50/15' : 'border-slate-200 bg-slate-50/40 hover:border-blue-300' }} flex flex-col justify-between gap-3 transition"
                         data-nombre="{{ mb_strtolower($med->nombre_comercial) }}"
                         data-principio="{{ mb_strtolower($med->principio_activo) }}"
                         data-lab="{{ mb_strtolower($med->laboratorio) }}"
                         data-categoria="{{ $med->categoria }}"
                         data-ean="{{ $med->codigo_barras }}"
                         data-cubierto="{{ $pctCat > 0 ? '1' : '0' }}">

                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="font-bold text-xs text-slate-900">{{ $med->nombre_comercial }}</span>
                                    @if($esRestringido)
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-red-100 text-red-700 border border-red-200 whitespace-nowrap">
                                            🔒 Retenida
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-semibold bg-emerald-100 text-emerald-700 whitespace-nowrap">
                                            Venta Libre
                                        </span>
                                    @endif

                                    @if($tieneConvenioActivo)
                                        @if($pctCat > 0)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-blue-100 text-[#1E40AF] border border-blue-200 whitespace-nowrap">
                                                🏷️ -{{ number_format($pctCat, 0) }}%
                                            </span>
                                        @else
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-medium bg-slate-200 text-slate-600 whitespace-nowrap">
                                                Sin cobertura
                                            </span>
                                        @endif
                                    @endif
                                </div>

                                <div class="text-[11px] text-slate-500 mt-0.5 truncate">
                                    {{ $med->principio_activo }} • <strong>{{ $med->laboratorio }}</strong>
                                </div>
                                <div class="text-[10px] text-slate-400 mt-0.5 flex items-center gap-2">
                                    <span class="font-mono-code">{{ $med->codigo_barras }}</span>
                                    <span>•</span>
                                    <span>{{ $med->categoria }}</span>
                                </div>
                            </div>

                            <!-- Precio -->
                            <div class="text-right shrink-0">
                                @if($pctCat > 0)
                                    <div class="text-[10px] font-mono-code text-slate-400 line-through">
                                        ${{ number_format($med->precio_unitario, 0, ',', '.') }}
                                    </div>
                                    <div class="font-mono-code font-extrabold text-sm text-emerald-700">
                                        ${{ number_format($precioConDcto, 0, ',', '.') }}
                                    </div>
                                @else
                                    <div class="font-mono-code font-extrabold text-sm text-slate-900">
                                        ${{ number_format($med->precio_unitario, 0, ',', '.') }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Pie de Tarjeta: Stock Vitrina + Selector de Cantidad + Botón Agregar -->
                        <div class="pt-2.5 border-t border-slate-200/70 flex items-center justify-between gap-2">
                            <div class="text-[11px]">
                                @if($stockVitrina > 0 && $primerLote)
                                    <span class="text-emerald-700 font-bold">Vitrina: {{ $stockVitrina }} u.</span>
                                    <span class="text-slate-300 mx-1">|</span>
                                    <span class="font-mono-code text-[10px] text-slate-500">{{ $primerLote->lote_codigo }}</span>
                                @else
                                    <span class="text-red-600 font-bold text-[10px]">⚠️ Sin stock vitrina (Bod: {{ $med->stock_bodega }})</span>
                                @endif
                            </div>

                            @if($stockVitrina > 0)
                                <form action="{{ route('pos.add') }}" method="POST" class="flex items-center gap-1.5 shrink-0">
                                    @csrf
                                    <input type="hidden" name="id_medicamento" value="{{ $med->id_medicamento }}">

                                    <!-- Selector de cantidad [- 1 +] -->
                                    <div class="inline-flex items-center rounded-lg border border-slate-300 bg-white overflow-hidden">
                                        <button type="button"
                                                onclick="stepInputQty(this, -1, {{ $stockVitrina }})"
                                                class="px-2 py-1 text-slate-600 hover:bg-slate-100 font-bold text-xs leading-none">
                                            &minus;
                                        </button>
                                        <input type="number"
                                               name="cantidad"
                                               value="1"
                                               min="1"
                                               max="{{ $stockVitrina }}"
                                               class="w-10 text-center text-xs font-mono-code font-bold text-slate-900 py-1 border-x border-slate-200 outline-none">
                                        <button type="button"
                                                onclick="stepInputQty(this, 1, {{ $stockVitrina }})"
                                                class="px-2 py-1 text-slate-600 hover:bg-slate-100 font-bold text-xs leading-none">
                                            +
                                        </button>
                                    </div>

                                    <button type="submit"
                                            class="px-3 py-1.5 rounded-lg text-[11px] font-bold {{ $esRestringido ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-[#1E40AF] hover:bg-blue-800 text-white' }} shadow-sm transition whitespace-nowrap">
                                        + Agregar
                                    </button>
                                </form>
                            @else
                                <span class="px-2.5 py-1 rounded-lg bg-slate-200 text-slate-500 text-[10px] font-bold">
                                    Agotado en Vitrina
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div id="catalog-empty-msg" class="hidden py-12 text-center text-slate-400 text-xs">
                No se encontraron medicamentos con ese criterio.
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- COLUMNA DERECHA FIJA (5 cols): TICKET / CARRITO DE COMPRAS + COBRO        -->
    <!-- ========================================================================= -->
    <div class="xl:col-span-5 xl:sticky xl:top-16 space-y-4">

        <div class="bg-white rounded-2xl border border-slate-200 shadow-md overflow-hidden flex flex-col">

            <!-- Cabecera del Ticket / Carrito -->
            <div class="bg-[#0F172A] text-white px-5 py-3.5 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-[#1E40AF] flex items-center justify-center text-sm font-bold">🛒</span>
                    <div>
                        <h2 class="font-bold text-sm leading-tight">Carrito de Dispensación</h2>
                        <p class="text-[11px] text-slate-400">
                            {{ count($cartDetailed) }} producto(s) • {{ $totalUnidadesCarro }} unidad(es)
                        </p>
                    </div>
                </div>

                @if(!empty($cartDetailed))
                    <form action="{{ route('pos.clear') }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="px-2.5 py-1 rounded-lg bg-red-500/20 hover:bg-red-500/30 border border-red-400/30 text-red-200 text-[11px] font-semibold transition">
                            🗑️ Vaciar
                        </button>
                    </form>
                @endif
            </div>

            <!-- Selector de Convenio de Salud -->
            <div class="p-4 bg-slate-50 border-b border-slate-200">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">
                    🏥 Convenio de Salud / Previsión del Paciente
                </label>
                <form action="{{ route('pos.discount') }}" method="POST">
                    @csrf
                    <select name="id_convenio" onchange="this.form.submit()"
                            class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-xs font-semibold text-slate-800 focus:border-[#1E40AF] outline-none">
                        @foreach($convenios as $conv)
                            <option value="{{ $conv->id_convenio }}" {{ ($selectedConvenio?->id_convenio == $conv->id_convenio) ? 'selected' : '' }}>
                                {{ $conv->nombre_convenio }} ({{ number_format($conv->porcentaje_descuento, 0) }}% bonificación)
                            </option>
                        @endforeach
                    </select>
                </form>
                @if($tieneConvenioActivo)
                    <div class="mt-2 flex items-center justify-between text-[11px] text-[#1E40AF] bg-blue-50/80 border border-blue-200 rounded-lg px-2.5 py-1.5">
                        <span class="font-semibold">
                            @if($selectedConvenio->aplica_todo_catalogo)
                                🌐 Descuento aplica a todo el catálogo
                            @else
                                💊 Cubre {{ $selectedConvenio->medicamentos->count() }} medicamentos adheridos
                            @endif
                        </span>
                        <span class="font-extrabold">-{{ number_format($selectedConvenio->porcentaje_descuento, 0) }}%</span>
                    </div>
                @endif
            </div>

            <!-- Alerta de Bloqueo Sanitario ISP si hay Receta Retenida en el carrito -->
            @if($hasControlledItems && !$allControlledAuthorized)
                <div class="mx-4 mt-3 p-3 rounded-xl bg-red-50 border-2 border-[#EF4444] space-y-2">
                    <div class="flex items-start gap-2.5">
                        <span class="w-6 h-6 rounded-md bg-[#EF4444] text-white flex items-center justify-center text-xs font-bold shrink-0">🔒</span>
                        <div>
                            <h3 class="text-[11px] font-extrabold uppercase text-red-900">
                                Venta Restringida — Requiere Receta Retenida
                            </h3>
                            <p class="text-[11px] text-red-700 leading-snug">
                                El carrito incluye medicamentos controlados. Autorice con PIN del Químico Farmacéutico para habilitar el cobro.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 pt-1">
                        <button type="button" onclick="document.getElementById('modal-qf-pin').classList.remove('hidden')"
                                class="flex-1 py-1.5 px-3 rounded-lg bg-[#EF4444] hover:bg-red-700 text-white text-[11px] font-bold shadow-sm transition">
                            🔑 Autorizar en Caja (PIN Q.F.)
                        </button>
                        <button type="button" onclick="document.getElementById('modal-cola-isp').classList.remove('hidden')"
                                class="py-1.5 px-2.5 rounded-lg bg-white border border-red-300 hover:bg-red-100 text-red-800 text-[11px] font-semibold transition">
                            📤 Enviar a Q.F.
                        </button>
                    </div>
                </div>
            @endif

            <!-- Lista de Ítems del Carrito (con edición directa de cantidad) -->
            <div class="divide-y divide-slate-100 max-h-[380px] overflow-y-auto">
                @forelse($cartDetailed as $item)
                    @php
                        /** @var \App\Models\Medication $med */
                        $med = $item['medicamento'];
                        /** @var \App\Models\Batch|null $lote */
                        $lote = $item['lote_fefo'];
                        $cubierto = $item['cubierto_convenio'] ?? false;
                        $pctLinea = $item['porcentaje_convenio'] ?? 0;
                        $dctoLinea = $item['descuento_linea'] ?? 0;
                        $copagoLinea = $item['total_copago_linea'] ?? $item['subtotal'];
                        $maxStockItem = max($item['cantidad'], $med->stock_vitrina);
                    @endphp
                    <div class="p-3.5 hover:bg-slate-50/80 transition space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="font-bold text-xs text-slate-900 flex items-center gap-1.5 flex-wrap">
                                    <span>{{ $med->nombre_comercial }}</span>
                                    @if($item['es_controlado'])
                                        @if($item['autorizacion_isp'])
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-100 text-emerald-800">
                                                ✓ Visado ({{ $item['autorizacion_isp']->folio_receta }})
                                            </span>
                                        @else
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-red-100 text-[#EF4444]">
                                                🔒 Receta Retenida
                                            </span>
                                        @endif
                                    @endif
                                </div>
                                <div class="text-[11px] text-slate-400 flex items-center gap-1.5 mt-0.5">
                                    <span>{{ $med->laboratorio }}</span>
                                    @if($lote)
                                        <span>•</span>
                                        <span class="font-mono-code text-[10px] text-blue-700 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
                                            {{ $lote->lote_codigo }}
                                        </span>
                                    @endif
                                </div>
                                @if($tieneConvenioActivo)
                                    <div class="mt-1">
                                        @if($cubierto)
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold">
                                                ✓ -{{ number_format($pctLinea, 0) }}% {{ $selectedConvenio->nombre_convenio }} (Ahorra ${{ number_format($dctoLinea, 0, ',', '.') }})
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 text-[10px]">
                                                Sin cobertura en {{ $selectedConvenio->nombre_convenio }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <!-- Botón eliminar ítem -->
                            <form action="{{ route('pos.remove', $med->id_medicamento) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        title="Quitar del carrito"
                                        class="text-slate-400 hover:text-red-600 text-xs font-bold p-1">
                                    ✕
                                </button>
                            </form>
                        </div>

                        <!-- Control de Cantidad en vivo + Subtotal Línea -->
                        <div class="flex items-center justify-between pt-1">
                            <form action="{{ route('pos.update_qty', $med->id_medicamento) }}" method="POST" class="flex items-center gap-1.5">
                                @csrf
                                @method('PATCH')
                                <span class="text-[11px] text-slate-500">Cant.:</span>
                                <div class="inline-flex items-center rounded-lg border border-slate-300 bg-white overflow-hidden">
                                    <button type="button"
                                            onclick="changeCartItemQty(this, -1, {{ $maxStockItem }})"
                                            class="px-2 py-1 text-slate-600 hover:bg-slate-100 font-bold text-xs leading-none">
                                        &minus;
                                    </button>
                                    <input type="number"
                                           name="cantidad"
                                           value="{{ $item['cantidad'] }}"
                                           min="1"
                                           max="{{ $maxStockItem }}"
                                           onchange="this.form.submit()"
                                           class="w-11 text-center text-xs font-mono-code font-bold text-slate-900 py-1 border-x border-slate-200 outline-none">
                                    <button type="button"
                                            onclick="changeCartItemQty(this, 1, {{ $maxStockItem }})"
                                            class="px-2 py-1 text-slate-600 hover:bg-slate-100 font-bold text-xs leading-none">
                                        +
                                    </button>
                                </div>
                                <span class="text-[10px] text-slate-400 font-mono-code">
                                    x ${{ number_format($item['precio_unitario'], 0, ',', '.') }}
                                </span>
                            </form>

                            <div class="text-right font-mono-code">
                                @if($dctoLinea > 0)
                                    <span class="text-[10px] text-slate-400 line-through mr-1">
                                        ${{ number_format($item['subtotal'], 0, ',', '.') }}
                                    </span>
                                @endif
                                <span class="font-extrabold text-xs text-slate-900">
                                    ${{ number_format($copagoLinea, 0, ',', '.') }}
                                </span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="py-12 px-6 text-center">
                        <div class="text-3xl mb-2 text-slate-300">🛒</div>
                        <p class="text-xs font-bold text-slate-500">El carrito de compras está vacío</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">
                            Usa el buscador predictivo o selecciona la cantidad en el catálogo izquierdo.
                        </p>
                    </div>
                @endforelse
            </div>

            <!-- Desglose de Cobro y Botón de Pago -->
            <div class="p-5 bg-slate-50 border-t border-slate-200 space-y-3">
                <div class="space-y-1.5 text-xs border-b border-slate-200 pb-3">
                    <div class="flex justify-between text-slate-600">
                        <span>Subtotal Bruto ({{ $totalUnidadesCarro }} u.)</span>
                        <span class="font-mono-code font-semibold">${{ number_format($liquidacion['subtotal'], 0, ',', '.') }}</span>
                    </div>

                    @if($liquidacion['descuento_total'] > 0)
                        <div class="flex justify-between text-emerald-700 font-bold">
                            <span>Bonificación {{ $selectedConvenio?->nombre_convenio }}</span>
                            <span class="font-mono-code">-${{ number_format($liquidacion['descuento_total'], 0, ',', '.') }}</span>
                        </div>
                    @endif

                    <div class="flex justify-between text-slate-400 text-[11px]">
                        <span>IVA Débito Fiscal (19% incluido)</span>
                        <span class="font-mono-code">${{ number_format($liquidacion['iva'], 0, ',', '.') }}</span>
                    </div>
                </div>

                <div class="flex items-center justify-between py-1">
                    <span class="text-sm font-extrabold text-slate-900">TOTAL A PAGAR</span>
                    <span class="text-2xl font-extrabold font-mono-code text-[#1E40AF]">
                        ${{ number_format($liquidacion['total'], 0, ',', '.') }}
                    </span>
                </div>

                @if(empty($cartDetailed))
                    <button type="button" disabled
                            class="w-full py-3 px-4 rounded-xl bg-slate-200 text-slate-400 font-bold text-xs cursor-not-allowed">
                        💳 Procesar Pago y Emitir Boleta
                    </button>
                @elseif($hasControlledItems && !$allControlledAuthorized)
                    <button type="button" onclick="document.getElementById('modal-qf-pin').classList.remove('hidden')"
                            class="w-full py-3 px-4 rounded-xl bg-[#EF4444] hover:bg-red-700 text-white font-bold text-xs shadow-sm transition">
                        🛡️ Autorizar Receta con PIN Q.F. y Cobrar
                    </button>
                @else
                    <form action="{{ route('pos.checkout') }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="w-full py-3 px-4 rounded-xl bg-[#10B981] hover:bg-emerald-700 text-white font-bold text-xs shadow-md transition">
                            💳 Procesar Pago y Emitir Boleta
                        </button>
                    </form>
                @endif
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
                           minlength="8" maxlength="12"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">RUN del Médico Prescriptor</label>
                    <input type="text" name="autorizacion_isp[rut_medico]" value="09.876.543-2" required
                           minlength="8" maxlength="12"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Folio Receta Retenida</label>
                    <input type="text" name="autorizacion_isp[folio_receta]" value="REC-2026-{{ rand(8850, 9999) }}" required
                           minlength="4" maxlength="25"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                </div>
                <div>
                    <label class="block font-bold text-[#EF4444] mb-1">PIN de Autorización Q.F.</label>
                    <input type="password" name="autorizacion_isp[pin_qf]" value="4421" required
                           minlength="4" maxlength="6"
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
                       minlength="4" maxlength="25"
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">RUN del Paciente</label>
                    <input type="text" name="rut_paciente" value="18.455.120-K" required
                           minlength="8" maxlength="12"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">RUN del Médico</label>
                    <input type="text" name="rut_medico" value="09.876.543-2" required
                           minlength="8" maxlength="12"
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

<script>
    const POS_CATALOG = @json($catalogoAutocompletado ?? []);
    const POS_ADD_URL = "{{ route('pos.add') }}";
    const CSRF_TOKEN = "{{ csrf_token() }}";

    // Incrementar/disminuir cantidad en las tarjetas del catálogo
    function stepInputQty(btn, delta, maxStock) {
        const input = btn.parentElement.querySelector('input[name="cantidad"]');
        if (!input) return;
        let next = parseInt(input.value || '1', 10) + delta;
        if (next < 1) next = 1;
        if (next > maxStock) next = maxStock;
        input.value = next;
    }

    // Cambiar cantidad directamente en un ítem del carrito y enviar actualización
    function changeCartItemQty(btn, delta, maxStock) {
        const form = btn.closest('form');
        const input = form.querySelector('input[name="cantidad"]');
        if (!input) return;
        let next = parseInt(input.value || '1', 10) + delta;
        if (next < 1) next = 1;
        if (next > maxStock) next = maxStock;
        input.value = next;
        form.submit();
    }

    // Normalizar texto sin tildes para búsqueda predictiva
    function normStr(str) {
        return String(str || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function fmtClpJs(n) {
        return '$' + Math.round(Number(n || 0)).toLocaleString('es-CL');
    }

    // Filtrar tarjetas del catálogo en la página y mostrar sugerencias que empiezan con esas letras
    function filterCatalogInPage() {
        const searchInput = document.getElementById('pos-search-input');
        const clearBtn = document.getElementById('btn-clear-search');
        const catSelect = document.getElementById('pos-category-select');
        const onlyCovCheck = document.getElementById('pos-only-covered');
        const rawQuery = (searchInput ? searchInput.value : '').trim();
        const q = normStr(rawQuery);
        const cat = catSelect ? catSelect.value : '';
        const onlyCovered = onlyCovCheck ? onlyCovCheck.checked : false;

        if (clearBtn) {
            clearBtn.classList.toggle('hidden', rawQuery === '');
        }

        let visibleCount = 0;
        document.querySelectorAll('.catalog-card').forEach(card => {
            const nombre = normStr(card.getAttribute('data-nombre'));
            const principio = normStr(card.getAttribute('data-principio'));
            const lab = normStr(card.getAttribute('data-lab'));
            const ean = normStr(card.getAttribute('data-ean'));
            const cardCat = card.getAttribute('data-categoria') || '';
            const cardCubierto = card.getAttribute('data-cubierto') === '1';

            const matchQ = !q || nombre.startsWith(q) || principio.startsWith(q) || nombre.includes(q) || principio.includes(q) || lab.includes(q) || ean.includes(q);
            const matchCat = !cat || cardCat === cat;
            const matchCov = !onlyCovered || cardCubierto;

            if (matchQ && matchCat && matchCov) {
                card.classList.remove('hidden');
                visibleCount++;
            } else {
                card.classList.add('hidden');
            }
        });

        const counterEl = document.getElementById('catalog-visible-counter');
        if (counterEl) {
            counterEl.textContent = `Mostrando ${visibleCount} medicamento(s)`;
        }
        const emptyEl = document.getElementById('catalog-empty-msg');
        if (emptyEl) {
            emptyEl.classList.toggle('hidden', visibleCount > 0);
        }

        renderAutocompleteSuggestions(rawQuery, q);
    }

    function renderAutocompleteSuggestions(rawQuery, q) {
        const dropdown = document.getElementById('autocomplete-dropdown');
        const resultsBox = document.getElementById('autocomplete-results');
        const headerText = document.getElementById('autocomplete-header-text');
        if (!dropdown || !resultsBox) return;

        if (!q || q.length < 1) {
            dropdown.classList.add('hidden');
            return;
        }

        // Prioridad 1: Medicamentos cuyo nombre comercial o principio activo COMIENZA con las letras escritas
        const startsWithMatches = [];
        // Prioridad 2: Medicamentos que contienen las letras en cualquier parte o por código EAN
        const containsMatches = [];

        POS_CATALOG.forEach(item => {
            const nNombre = normStr(item.nombre_comercial);
            const nPrincipio = normStr(item.principio_activo);
            const nEan = normStr(item.codigo_barras);

            if (nNombre.startsWith(q) || nPrincipio.startsWith(q)) {
                startsWithMatches.push({ ...item, matchType: 'starts' });
            } else if (nNombre.includes(q) || nPrincipio.includes(q) || nEan.includes(q)) {
                containsMatches.push({ ...item, matchType: 'contains' });
            }
        });

        const combined = [...startsWithMatches, ...containsMatches].slice(0, 8);

        if (combined.length === 0) {
            headerText.textContent = `Sin coincidencias para "${rawQuery}"`;
            resultsBox.innerHTML = `<div class="p-4 text-center text-xs text-slate-400">No se encontraron medicamentos con ese nombre o principio activo.</div>`;
            dropdown.classList.remove('hidden');
            return;
        }

        headerText.textContent = startsWithMatches.length > 0
            ? `Sugerencias que comienzan con "${rawQuery}" (${combined.length})`
            : `Coincidencias para "${rawQuery}" (${combined.length})`;

        resultsBox.innerHTML = combined.map(m => {
            const badgeReceta = m.es_controlado
                ? `<span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-red-100 text-red-700 border border-red-200">🔒 Retenida</span>`
                : `<span class="px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-emerald-100 text-emerald-700">Venta Libre</span>`;

            const badgeConvenio = m.pct_convenio > 0
                ? `<span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-blue-100 text-[#1E40AF]">🏷️ -${m.pct_convenio}%</span>`
                : '';

            const precioHtml = m.pct_convenio > 0
                ? `<div class="text-[10px] font-mono-code text-slate-400 line-through">${fmtClpJs(m.precio_unitario)}</div>
                   <div class="font-mono-code font-extrabold text-xs text-emerald-700">${fmtClpJs(m.precio_con_descuento)}</div>`
                : `<div class="font-mono-code font-extrabold text-xs text-slate-900">${fmtClpJs(m.precio_unitario)}</div>`;

            const actionHtml = m.stock_vitrina > 0
                ? `<form action="${POS_ADD_URL}" method="POST" class="flex items-center gap-1.5 shrink-0">
                       <input type="hidden" name="_token" value="${CSRF_TOKEN}">
                       <input type="hidden" name="id_medicamento" value="${m.id_medicamento}">
                       <input type="number" name="cantidad" value="1" min="1" max="${m.stock_vitrina}"
                              title="Cantidad a agregar"
                              class="w-12 px-1.5 py-1 text-center text-xs font-mono-code font-bold rounded-lg border border-slate-300">
                       <button type="submit" class="px-2.5 py-1.5 rounded-lg ${m.es_controlado ? 'bg-red-600 hover:bg-red-700' : 'bg-[#1E40AF] hover:bg-blue-800'} text-white font-bold text-[11px] whitespace-nowrap">
                           + Agregar
                       </button>
                   </form>`
                : `<span class="px-2 py-1 rounded bg-slate-100 text-slate-400 text-[10px] font-bold">Sin stock vitrina</span>`;

            return `
                <div class="px-3.5 py-2.5 hover:bg-blue-50/50 flex items-center justify-between gap-3 transition">
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="font-bold text-xs text-slate-900">${m.nombre_comercial}</span>
                            ${badgeReceta}
                            ${badgeConvenio}
                        </div>
                        <div class="text-[11px] text-slate-500 truncate">
                            ${m.principio_activo} • <strong>${m.laboratorio}</strong> • <span class="text-emerald-700 font-semibold">Vitrina: ${m.stock_vitrina} u.</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <div class="text-right">${precioHtml}</div>
                        ${actionHtml}
                    </div>
                </div>
            `;
        }).join('');

        dropdown.classList.remove('hidden');
    }

    function clearLiveSearch() {
        const searchInput = document.getElementById('pos-search-input');
        if (searchInput) {
            searchInput.value = '';
            filterCatalogInPage();
            searchInput.focus();
        }
    }

    // =========================================================================
    // MOTOR DE LECTOR DE CÓDIGO DE BARRAS EAN-13 (MODO KEYBOARD WEDGE HID)
    // =========================================================================
    function playScannerBeep(mode) {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();

            if (mode === 'controlled') {
                // Doble tono de alerta sanitaria para Receta Retenida ISP / Psicotrópico
                [580, 440].forEach((freq, idx) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(freq, ctx.currentTime + idx * 0.13);
                    gain.gain.setValueAtTime(0.14, ctx.currentTime + idx * 0.13);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + idx * 0.13 + 0.11);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(ctx.currentTime + idx * 0.13);
                    osc.stop(ctx.currentTime + idx * 0.13 + 0.11);
                });
            } else if (mode === 'error') {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'square';
                osc.frequency.setValueAtTime(220, ctx.currentTime);
                gain.gain.setValueAtTime(0.15, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.25);
            } else {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(1350, ctx.currentTime);
                gain.gain.setValueAtTime(0.18, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.085);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.085);
            }
        } catch (err) {}
    }

    function parseBarcodeToken(rawInput) {
        const clean = String(rawInput || '').trim();
        let qty = null;
        let term = clean;

        const multMatch = clean.match(/^(\d{1,3})\s*[\*xX]\s*(.+)$/);
        if (multMatch) {
            qty = Math.max(1, parseInt(multMatch[1], 10));
            term = multMatch[2].trim();
        }

        const nTerm = normStr(term);
        const med = POS_CATALOG.find(item =>
            String(item.codigo_barras).toLowerCase() === nTerm ||
            normStr(item.nombre_comercial) === nTerm
        ) || POS_CATALOG.find(item =>
            normStr(item.nombre_comercial).includes(nTerm)
        );

        return { qty, term, med };
    }

    function handleBarcodeFormSubmit(e) {
        const codeInput = document.getElementById('pos-barcode-input');
        const qtyInput = document.getElementById('pos-barcode-qty');
        if (!codeInput) return true;

        const rawVal = codeInput.value.trim();
        const parsed = parseBarcodeToken(rawVal);
        const finalQty = parsed.qty !== null ? parsed.qty : parseInt(qtyInput?.value || '1', 10);

        if (parsed.qty !== null && qtyInput) {
            qtyInput.value = finalQty;
        }

        if (!parsed.med) {
            playScannerBeep('error');
        } else if (parsed.med.es_controlado) {
            playScannerBeep('controlled');
        } else {
            playScannerBeep('ok');
        }

        return true;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('pos-search-input');
        const wrapper = document.getElementById('search-autocomplete-wrapper');
        const dropdown = document.getElementById('autocomplete-dropdown');
        const barcodeInput = document.getElementById('pos-barcode-input');
        const barcodeForm = document.getElementById('pos-barcode-form');

        if (searchInput) {
            searchInput.addEventListener('input', filterCatalogInPage);
            searchInput.addEventListener('focus', function() {
                if (searchInput.value.trim().length > 0) {
                    filterCatalogInPage();
                }
            });
        }

        document.addEventListener('click', function(e) {
            if (wrapper && dropdown && !wrapper.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });

        // Buffer Global para Pistola Lectora USB en modo Keyboard Wedge (ráfaga < 45ms + Enter) y atajos F2 / F3
        let wedgeBuffer = '';
        let lastKeyTime = 0;

        document.addEventListener('keydown', function(e) {
            // F2: Enfocar directamente el Lector de Código de Barras EAN
            if (e.key === 'F2') {
                e.preventDefault();
                if (barcodeInput) {
                    barcodeInput.focus();
                    barcodeInput.select();
                }
                return;
            }
            // F3: Enfocar directamente el Buscador Predictivo
            if (e.key === 'F3') {
                e.preventDefault();
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
                return;
            }

            const activeEl = document.activeElement;
            const isTypingInInput = activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.tagName === 'SELECT');
            if (isTypingInInput) {
                return;
            }

            const now = Date.now();
            if (now - lastKeyTime > 80) {
                wedgeBuffer = '';
            }
            lastKeyTime = now;

            if (e.key === 'Enter' && wedgeBuffer.length >= 6) {
                e.preventDefault();
                const scannedCode = wedgeBuffer;
                wedgeBuffer = '';
                if (barcodeInput && barcodeForm) {
                    barcodeInput.value = scannedCode;
                    handleBarcodeFormSubmit();
                    barcodeForm.submit();
                }
            } else if (e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
                wedgeBuffer += e.key;
            }
        });
    });
</script>
@endsection
