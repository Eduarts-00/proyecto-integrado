@extends('layouts.app')

@section('title', 'Gestión de Inventario — FarmaNet Pro')

@section('content')
@php
    $canManageStock = auth()->user()->hasPermission('Can_Manage_Stock');
    $canManageMedications = auth()->user()->hasPermission('Can_Manage_Medications');
    $canDarDeBaja = auth()->user()->role === 'quimico_farmaceutico';
    $canToggleLote = $canManageStock || $canManageMedications || $canDarDeBaja || auth()->user()->role === 'administrador';
    $stockFilter = $stockFilter ?? '';
    $orden = $orden ?? '';
    $reponerMedId = $reponerMedId ?? 0;
    $alertasStockBajo = $alertasStockBajo ?? collect();

    // Mapa rápido de lote en Bodega por id_medicamento para traspaso en 1 clic
    $loteBodegaPorMed = ($lotesBodega ?? collect())->keyBy('id_medicamento');
@endphp

<div class="space-y-4">

    <!-- ========================================================================= -->
    <!-- CABECERA Y BOTONES DE OPERACIÓN (LIMPIA Y SIN DUPLICAR ALERTAS SUPERIORES) -->
    <!-- ========================================================================= -->
    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Gestión de Inventario y Catálogo</h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Control de lotes por vencimiento (FEFO), ordenamiento por stock y clasificación de recetas restringidas.
            </p>
        </div>

        <!-- Botonera de Acciones según Permisos RBAC -->
        <div class="flex flex-wrap items-center gap-2">
            @if($canToggleLote)
                <button type="button"
                        onclick="document.getElementById('modal-retiro-vitrina').classList.remove('hidden')"
                        class="px-3.5 py-2 rounded-lg border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-900 text-xs font-bold shadow-2xs transition">
                    &darr; Retirar a Bodega
                </button>
            @endif

            @if($canManageStock)
                <button type="button"
                        onclick="document.getElementById('modal-traspaso-fefo').classList.remove('hidden')"
                        class="px-3.5 py-2 rounded-lg bg-[#1E40AF] hover:bg-blue-800 text-white text-xs font-bold shadow-sm transition">
                    &uarr; Traspasar Bodega &rarr; Vitrina
                </button>

                <button type="button"
                        onclick="openNuevaRemesaForMedication(null)"
                        class="px-3.5 py-2 rounded-lg bg-[#10B981] hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition">
                    📦 + Ingresar Lote (Stock)
                </button>
            @endif

            @if($canManageMedications)
                <button type="button"
                        onclick="document.getElementById('modal-nuevo-medicamento').classList.remove('hidden')"
                        class="px-3.5 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold shadow-sm transition">
                    💊 + Nuevo Producto
                </button>
            @endif

            @if(! $canManageStock && ! $canManageMedications)
                <span class="px-3 py-2 rounded-lg bg-slate-100 border border-slate-200 text-slate-600 text-[11px] font-semibold">
                    🔒 Modo Consulta
                </span>
            @endif
        </div>
    </div>

    <!-- Aviso de Permisos cuando un rol sin Can_Manage_Stock pincha una alerta de reposición -->
    <div id="low-stock-permission-notice"
         class="hidden p-3.5 rounded-xl bg-amber-50 border border-amber-300 text-amber-950 text-xs flex items-center justify-between gap-3 shadow-2xs">
        <div class="flex items-center gap-2">
            <span class="text-base">🔒</span>
            <span id="low-stock-permission-text" class="font-medium"></span>
        </div>
        <button type="button"
                onclick="document.getElementById('low-stock-permission-notice').classList.add('hidden')"
                class="px-2.5 py-1 rounded-md bg-amber-100 hover:bg-amber-200 text-amber-900 font-bold text-[11px]">
            Entendido
        </button>
    </div>

    <!-- ========================================================================= -->
    <!-- PESTAÑAS DE SEGREGACIÓN FÍSICA Y CATÁLOGO MAESTRO                         -->
    <!-- ========================================================================= -->
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('inventario.index', ['ubicacion' => 'VITRINA']) }}"
           class="px-4 py-2 rounded-lg text-xs font-bold border transition flex items-center gap-2 {{ $ubicacion === 'VITRINA' ? 'bg-white border-slate-300 text-slate-900 shadow-sm' : 'bg-slate-100 border-transparent text-slate-500 hover:text-slate-800' }}">
            🏪 Vitrina ({{ ($lotesVitrina ?? collect())->count() }})
        </a>
        <a href="{{ route('inventario.index', ['ubicacion' => 'BODEGA']) }}"
           class="px-4 py-2 rounded-lg text-xs font-bold border transition flex items-center gap-2 {{ $ubicacion === 'BODEGA' ? 'bg-white border-slate-300 text-slate-900 shadow-sm' : 'bg-slate-100 border-transparent text-slate-500 hover:text-slate-800' }}">
            📦 Bodega ({{ ($lotesBodega ?? collect())->count() }})
        </a>
        <a href="{{ route('inventario.index', ['ubicacion' => 'TODOS']) }}"
           class="px-4 py-2 rounded-lg text-xs font-bold border transition flex items-center gap-2 {{ $ubicacion === 'TODOS' ? 'bg-white border-slate-300 text-slate-900 shadow-sm' : 'bg-slate-100 border-transparent text-slate-500 hover:text-slate-800' }}">
            📋 Todos los Lotes
        </a>
        <a href="{{ route('inventario.index', ['ubicacion' => 'CATALOGO']) }}"
           class="px-4 py-2 rounded-lg text-xs font-bold border transition flex items-center gap-2 {{ $ubicacion === 'CATALOGO' ? 'bg-white border-blue-300 text-[#1E40AF] shadow-sm' : 'bg-slate-100 border-transparent text-slate-500 hover:text-slate-800' }}">
            💊 Catálogo y Stock por Producto ({{ $medicamentosCatalogo->count() }})
        </a>
        <a href="{{ route('inventario.index', ['ubicacion' => 'BAJAS']) }}"
           class="px-4 py-2 rounded-lg text-xs font-bold border transition flex items-center gap-2 {{ $ubicacion === 'BAJAS' ? 'bg-red-50 border-red-300 text-red-800 shadow-sm' : 'bg-slate-100 border-transparent text-slate-500 hover:text-slate-800' }}">
            🗑️ Bajas y Caducados ({{ $bajasCount ?? 0 }})
        </a>
    </div>

    <!-- ========================================================================= -->
    <!-- BARRA DE BÚSQUEDA PREDICTIVA, ORDENAMIENTO POR STOCK Y FILTROS            -->
    <!-- ========================================================================= -->
    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
            <!-- 1. Buscador con Autocompletado en Vivo + Lector EAN -->
            <div class="lg:col-span-4 relative" id="inv-search-wrapper">
                <div class="flex items-center justify-between mb-1">
                    <label for="inv-live-search" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        Buscar Medicamento, Principio, Lote o EAN
                    </label>
                    <span class="text-[10px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full"
                          title="Puedes escanear cualquier caja física o presionar F2 para buscar instantáneamente por código EAN-13">
                        Escáner Activo (F2)
                    </span>
                </div>
                <form action="{{ route('inventario.index') }}" method="GET" onsubmit="event.preventDefault(); filterInventoryTable();" autocomplete="off">
                    <input type="hidden" name="ubicacion" value="{{ $ubicacion }}">
                    <div class="relative">
                        <input type="text"
                               id="inv-live-search"
                               name="q"
                               value="{{ $search ?? '' }}"
                               autocomplete="off"
                               oninput="handleInventorySearchInput(this.value)"
                               onfocus="handleInventorySearchInput(this.value)"
                               placeholder="Escanear EAN-13 o escribir nombre, principio activo o lote..."
                               class="w-full pl-3.5 pr-8 py-2.5 rounded-lg border border-slate-300 bg-white text-xs font-medium text-slate-800 focus:border-[#1E40AF] focus:ring-2 focus:ring-blue-100 outline-none transition">
                        <button type="button"
                                id="btn-clear-inv-search"
                                onclick="clearInventoryFilters()"
                                class="{{ empty($search) ? 'hidden' : '' }} absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600 text-xs font-bold"
                                title="Limpiar búsqueda">
                            ✕
                        </button>
                    </div>
                </form>

                <!-- Desplegable de Sugerencias Predictivas de Inventario -->
                <div id="inv-autocomplete-dropdown"
                     class="hidden absolute left-0 right-0 top-full mt-1.5 z-40 bg-white rounded-xl border border-slate-200 shadow-2xl overflow-hidden">
                    <div class="px-3.5 py-2 bg-slate-50 border-b border-slate-200 flex items-center justify-between text-[11px]">
                        <span id="inv-autocomplete-header" class="font-bold text-slate-600">Sugerencias en {{ $ubicacion }}</span>
                        <span class="text-slate-400">Haz clic para filtrar</span>
                    </div>
                    <div id="inv-autocomplete-results" class="max-h-72 overflow-y-auto divide-y divide-slate-100"></div>
                </div>
            </div>

            <!-- 2. Ordenar por (Stock Ascendente, Stock Descendente, Vencimiento FEFO, Alfabético, Precio) -->
            <div class="lg:col-span-3">
                <label for="inv-sort-order" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                    Ordenar Productos Por
                </label>
                <select id="inv-sort-order"
                        onchange="sortAndFilterInventoryTable()"
                        class="w-full px-3 py-2.5 rounded-lg border border-slate-300 bg-white text-xs font-semibold text-slate-800 focus:border-[#1E40AF] outline-none">
                    @if($ubicacion === 'CATALOGO')
                        <option value="nombre_asc" {{ $orden === '' || $orden === 'nombre_asc' ? 'selected' : '' }}>🔤 Nombre Comercial (A &rarr; Z)</option>
                        <option value="stock_asc" {{ $orden === 'stock_asc' ? 'selected' : '' }}>📉 Cantidad de Stock: Menor a Mayor</option>
                        <option value="stock_desc" {{ $orden === 'stock_desc' ? 'selected' : '' }}>📈 Cantidad de Stock: Mayor a Menor</option>
                        <option value="precio_desc" {{ $orden === 'precio_desc' ? 'selected' : '' }}>💲 Precio Venta: Mayor a Menor</option>
                    @else
                        <option value="fefo_asc" {{ $orden === '' || $orden === 'fefo_asc' ? 'selected' : '' }}>⏳ Vencimiento FEFO (Próximos primero)</option>
                        <option value="stock_asc" {{ $orden === 'stock_asc' ? 'selected' : '' }}>📉 Cantidad de Stock: Menor a Mayor</option>
                        <option value="stock_desc" {{ $orden === 'stock_desc' ? 'selected' : '' }}>📈 Cantidad de Stock: Mayor a Menor</option>
                        <option value="nombre_asc" {{ $orden === 'nombre_asc' ? 'selected' : '' }}>🔤 Medicamento (A &rarr; Z)</option>
                    @endif
                </select>
            </div>

            <!-- 3. Filtro por Nivel de Stock (Alerta Poco Stock vs Óptimo) -->
            <div class="lg:col-span-2">
                <label for="inv-filter-stock" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                    Nivel de Stock
                </label>
                <select id="inv-filter-stock"
                        onchange="filterInventoryTable()"
                        class="w-full px-3 py-2.5 rounded-lg border border-slate-300 bg-white text-xs font-semibold text-slate-700 focus:border-[#1E40AF] outline-none">
                    <option value="" {{ $stockFilter === '' ? 'selected' : '' }}>Todos los niveles</option>
                    <option value="bajo" {{ $stockFilter === 'bajo' ? 'selected' : '' }}>📉 Poco Stock / Crítico (&le; 25 u.)</option>
                    <option value="optimo" {{ $stockFilter === 'optimo' ? 'selected' : '' }}>✓ Stock Suficiente (&gt; 25 u.)</option>
                </select>
            </div>

            <!-- 4. Filtro por Condición de Venta -->
            <div class="lg:col-span-1">
                <label for="inv-filter-condicion" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                    Receta
                </label>
                <select id="inv-filter-condicion"
                        onchange="filterInventoryTable()"
                        class="w-full px-2.5 py-2.5 rounded-lg border border-slate-300 bg-white text-xs font-semibold text-slate-700 focus:border-[#1E40AF] outline-none">
                    <option value="">Todas</option>
                    <option value="libre">✓ Libre</option>
                    <option value="retenida">🔒 ISP</option>
                </select>
            </div>

            <!-- 5. Filtro por Semáforo FEFO o Estado de Catálogo -->
            <div class="lg:col-span-2">
                @if($ubicacion === 'CATALOGO')
                    <label for="inv-filter-fefo" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                        Estado Catálogo
                    </label>
                    <select id="inv-filter-fefo"
                            onchange="filterInventoryTable()"
                            class="w-full px-3 py-2.5 rounded-lg border border-slate-300 bg-white text-xs font-semibold text-slate-700 focus:border-[#1E40AF] outline-none">
                        <option value="">Todos los estados</option>
                        <option value="activo">✓ Solo Activos</option>
                        <option value="inactivo">✕ Deshabilitados</option>
                    </select>
                @else
                    <label for="inv-filter-fefo" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                        Vencimiento FEFO
                    </label>
                    <select id="inv-filter-fefo"
                            onchange="filterInventoryTable()"
                            class="w-full px-3 py-2.5 rounded-lg border border-slate-300 bg-white text-xs font-semibold text-slate-700 focus:border-[#1E40AF] outline-none">
                        <option value="" {{ ($fefo ?? '') === '' ? 'selected' : '' }}>Todos (FEFO)</option>
                        <option value="por_vencer" {{ ($fefo ?? '') === 'por_vencer' ? 'selected' : '' }}>⚠️ Por vencer (&le; 30d)</option>
                        <option value="critico" {{ ($fefo ?? '') === 'critico' ? 'selected' : '' }}>● Crítico (&lt; 20d)</option>
                        <option value="alerta" {{ ($fefo ?? '') === 'alerta' ? 'selected' : '' }}>▲ Alerta (20-30d)</option>
                        <option value="optimo" {{ ($fefo ?? '') === 'optimo' ? 'selected' : '' }}>✓ Óptimo (&gt; 30d)</option>
                    </select>
                @endif
            </div>
        </div>

        <!-- Barra inferior de estado de búsqueda y contador -->
        <div class="pt-2 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-xs">
            <div class="flex flex-wrap items-center gap-2 text-slate-500">
                <span class="inline-flex items-center gap-1 font-medium">
                    Vista activa: <strong class="text-slate-800">{{ $ubicacion }}</strong>
                </span>
                <span class="text-slate-300">•</span>
                <span class="text-[11px] text-slate-500">
                    Tip: Haz clic en el encabezado <strong>"Cantidad / Stock ↕"</strong> de la tabla para ordenar de menor a mayor o de mayor a menor stock.
                </span>
                <button type="button"
                        id="btn-reset-inv-filters"
                        onclick="clearInventoryFilters()"
                        class="hidden px-2.5 py-1 rounded-md bg-slate-900 hover:bg-slate-800 text-white font-bold text-[11px] transition">
                    ↺ Mostrar todos
                </button>
            </div>
            <span id="inv-visible-counter" class="text-[11px] font-semibold text-slate-500">
                Mostrando {{ $ubicacion === 'CATALOGO' ? $medicamentosCatalogo->count() : $lotes->count() }} registros
            </span>
        </div>
    </div>

    @if($ubicacion === 'CATALOGO')
        <!-- ========================================================================= -->
        <!-- VISTA DE CATÁLOGO MAESTRO DE MEDICAMENTOS, STOCK CONSOLIDADO Y RECETAS    -->
        <!-- ========================================================================= -->
        <div class="p-3 rounded-xl bg-blue-50/90 border border-blue-200 text-blue-900 text-xs font-medium flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <span>💊 <strong>Catálogo Maestro de Productos:</strong> Consulta el stock consolidado (Vitrina + Bodega), alertas de stock mínimo y condición de Receta Retenida ISP.</span>
            <div class="flex flex-wrap items-center gap-2">
                @if($canManageStock)
                    <span class="px-2.5 py-1 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 font-bold text-[11px]">
                        📦 Usa "+ Añadir Lote" en cualquier fila para abastecer ese producto directamente
                    </span>
                @endif
                @if(! $canManageMedications)
                    <span class="px-2.5 py-1 rounded-lg bg-white/80 border border-blue-200 text-slate-600 font-semibold text-[11px]">
                        🔒 Creación/Edición de catálogo exclusiva Q. Farmacéutico y Administrador
                    </span>
                @endif
            </div>
        </div>

        <div id="tabla-lotes-fefo" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 text-[11px] font-bold uppercase text-slate-400 bg-slate-50/60 whitespace-nowrap">
                            <th class="py-3.5 px-4">Código EAN</th>
                            <th class="py-3.5 px-4">
                                <button type="button" onclick="toggleColumnSort('nombre')" class="inline-flex items-center gap-1 hover:text-slate-800 uppercase font-bold">
                                    <span>Medicamento / Principio Activo</span>
                                    <span class="text-slate-400">↕</span>
                                </button>
                            </th>
                            <th class="py-3.5 px-4">Laboratorio</th>
                            <th class="py-3.5 px-4 text-right">
                                <button type="button" onclick="toggleColumnSort('precio')" class="inline-flex items-center gap-1 hover:text-slate-800 uppercase font-bold">
                                    <span>Precio Venta</span>
                                    <span class="text-slate-400">↕</span>
                                </button>
                            </th>
                            <th class="py-3.5 px-4 text-center">
                                <button type="button" onclick="toggleColumnSort('stock')" class="inline-flex items-center gap-1 text-[#1E40AF] hover:text-blue-900 uppercase font-extrabold" title="Ordenar por cantidad de stock">
                                    <span>Stock (Vitrina / Bodega)</span>
                                    <span id="th-stock-sort-indicator">↕</span>
                                </button>
                            </th>
                            <th class="py-3.5 px-4 text-center">Alerta de Stock</th>
                            <th class="py-3.5 px-4 text-center">Condición Venta</th>
                            <th class="py-3.5 px-4 text-center">Estado</th>
                            @if($canManageMedications || $canManageStock)
                                <th class="py-3.5 px-4 text-right">Acciones de Farmacia</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody id="inv-table-body" class="divide-y divide-slate-100 text-xs">
                        @foreach($medicamentosCatalogo as $med)
                            @php
                                $esRestringido = $med->requiere_receta || $med->tipo_controlado;
                                $vitStock = $med->stock_vitrina;
                                $bodStock = $med->stock_bodega;
                                $totStock = $vitStock + $bodStock;
                                $umbralMed = $med->umbral_stock_bajo;
                                $esStockBajo = $med->tiene_stock_bajo;
                                $loteBodMed = $loteBodegaPorMed->get($med->id_medicamento);
                            @endphp
                            <tr id="med-row-{{ $med->id_medicamento }}"
                                class="inv-filterable-row transition {{ ! $med->activo ? 'bg-slate-50/60 opacity-70' : ($vitStock <= 0 ? 'bg-red-50/35 hover:bg-red-50/60' : ($esStockBajo ? 'bg-amber-50/35 hover:bg-amber-50/60' : 'hover:bg-slate-50/80')) }}"
                                data-med-id="{{ $med->id_medicamento }}"
                                data-name="{{ $med->nombre_comercial }}"
                                data-sub="{{ $med->principio_activo }}"
                                data-code="{{ $med->codigo_barras }}"
                                data-ean="{{ $med->codigo_barras }}"
                                data-lab="{{ $med->laboratorio }}"
                                data-condicion="{{ $esRestringido ? 'retenida' : 'libre' }}"
                                data-fefo="{{ $med->activo ? 'activo' : 'inactivo' }}"
                                data-stock-level="{{ $esStockBajo ? 'bajo' : 'optimo' }}"
                                data-qty="{{ $vitStock }}"
                                data-total-qty="{{ $totStock }}"
                                data-price="{{ (float) $med->precio_unitario }}"
                                data-days="999"
                                data-badge="{{ $esRestringido ? '🔒 Retenida ISP' : '✓ Venta Libre' }}"
                                data-stock="Vit: {{ $vitStock }} / Bod: {{ $bodStock }}">
                                <td class="py-3.5 px-4 font-mono-code text-[11px] text-slate-500 whitespace-nowrap">
                                    {{ $med->codigo_barras }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold {{ $med->activo ? 'text-slate-900' : 'text-slate-400 line-through' }}">
                                        {{ $med->nombre_comercial }}
                                    </div>
                                    <div class="text-[11px] text-slate-400">{{ $med->principio_activo }}</div>
                                </td>
                                <td class="py-3.5 px-4 text-slate-600 whitespace-nowrap">
                                    {{ $med->laboratorio }}
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono-code font-bold text-slate-900 whitespace-nowrap">
                                    ${{ number_format($med->precio_unitario, 0, ',', '.') }}
                                </td>
                                <td class="py-3.5 px-4 text-center font-mono-code text-[11px] whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded {{ $vitStock <= 0 ? 'bg-red-100 text-red-700' : ($esStockBajo ? 'bg-amber-100 text-amber-800' : 'bg-blue-50 text-blue-700') }} font-bold">
                                        {{ $vitStock }} vit.
                                    </span>
                                    <span class="text-slate-300 mx-1">/</span>
                                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-semibold">
                                        {{ $bodStock }} bod.
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    @if($vitStock <= 0)
                                        <button type="button"
                                                onclick="handleLowStockMedicationClick({{ json_encode([
                                                    'id_medicamento' => $med->id_medicamento,
                                                    'nombre_comercial' => $med->nombre_comercial,
                                                    'codigo_barras' => $med->codigo_barras,
                                                    'stock_vitrina' => $vitStock,
                                                    'stock_bodega' => $bodStock,
                                                    'umbral' => $umbralMed,
                                                    'id_lote_bodega' => $loteBodMed?->id_lote,
                                                    'cant_lote_bodega' => $loteBodMed?->cantidad ?? 0,
                                                ]) }})"
                                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-red-100 hover:bg-red-200 text-red-700 border border-red-300 transition cursor-pointer"
                                                title="Pincha para abastecer este medicamento">
                                            🚨 Sin Vitrina {{ $canManageStock ? '• + Abastecer' : '' }}
                                        </button>
                                    @elseif($esStockBajo)
                                        <button type="button"
                                                onclick="handleLowStockMedicationClick({{ json_encode([
                                                    'id_medicamento' => $med->id_medicamento,
                                                    'nombre_comercial' => $med->nombre_comercial,
                                                    'codigo_barras' => $med->codigo_barras,
                                                    'stock_vitrina' => $vitStock,
                                                    'stock_bodega' => $bodStock,
                                                    'umbral' => $umbralMed,
                                                    'id_lote_bodega' => $loteBodMed?->id_lote,
                                                    'cant_lote_bodega' => $loteBodMed?->cantidad ?? 0,
                                                ]) }})"
                                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 hover:bg-amber-200 text-amber-800 border border-amber-300 transition cursor-pointer"
                                                title="Queda poco stock en vitrina (Mínimo {{ $umbralMed }} u.). Pincha para añadir más">
                                            📉 Poco Stock (&le; {{ $umbralMed }}) {{ $canManageStock ? '• + Añadir' : '' }}
                                        </button>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            ✓ Stock Óptimo
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    @if($esRestringido)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700 border border-red-200 whitespace-nowrap">
                                            🔒 Retenida ISP
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 whitespace-nowrap">
                                            ✓ Venta Libre
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold {{ $med->activo ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700' }}">
                                        {{ $med->activo ? 'ACTIVO' : 'DESHABILITADO' }}
                                    </span>
                                </td>
                                @if($canManageMedications || $canManageStock)
                                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                        <div class="inline-flex items-center justify-end gap-1.5">
                                            @if($canManageStock && $med->activo)
                                                <button type="button"
                                                        onclick="openNuevaRemesaForMedication({{ $med->id_medicamento }})"
                                                        class="px-2.5 py-1.5 rounded-lg bg-[#10B981] hover:bg-emerald-700 text-white font-bold text-[11px] shadow-2xs transition"
                                                        title="Ingresar un nuevo lote de stock para {{ $med->nombre_comercial }}">
                                                    📦 + Añadir Lote
                                                </button>
                                            @endif

                                            @if($canManageMedications)
                                                <button type="button"
                                                        onclick="openEditMedicationModal({{ json_encode([
                                                            'id_medicamento' => $med->id_medicamento,
                                                            'nombre_comercial' => $med->nombre_comercial,
                                                            'principio_activo' => $med->principio_activo,
                                                            'laboratorio' => $med->laboratorio,
                                                            'categoria' => $med->categoria,
                                                            'precio_unitario' => $med->precio_unitario,
                                                            'stock_minimo' => $med->umbral_stock_bajo,
                                                            'condicion_receta' => $esRestringido ? 'receta_retenida' : 'venta_libre',
                                                            'activo' => $med->activo ? '1' : '0',
                                                            'update_url' => route('inventario.medications.update', $med->id_medicamento),
                                                        ]) }})"
                                                        class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-blue-50 text-[#1E40AF] font-bold text-[11px] transition">
                                                    Editar
                                                </button>

                                                <form action="{{ route('inventario.medications.toggle_prescription', $med->id_medicamento) }}" method="POST" class="inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    @if($esRestringido)
                                                        <button type="submit"
                                                                class="px-2.5 py-1.5 rounded-lg border border-emerald-200 bg-emerald-50/70 hover:bg-emerald-100 text-emerald-700 font-bold text-[11px] transition"
                                                                title="Cambiar a Venta Directa sin bloqueo de receta">
                                                            Venta Libre
                                                        </button>
                                                    @else
                                                        <button type="submit"
                                                                class="px-2.5 py-1.5 rounded-lg border border-amber-300 bg-amber-50/80 hover:bg-amber-100 text-amber-800 font-bold text-[11px] transition"
                                                                title="Marcar con restricción de Receta Retenida ISP">
                                                            Receta ISP
                                                        </button>
                                                    @endif
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                        <tr id="inv-no-results-row" class="hidden">
                            <td colspan="{{ ($canManageMedications || $canManageStock) ? 9 : 8 }}" class="py-10 text-center text-slate-500 text-xs">
                                <div class="font-bold text-slate-700 mb-1">🔍 No se encontraron medicamentos con esos criterios de búsqueda</div>
                                <p class="text-[11px] text-slate-400 mb-3">Intenta con otro nombre comercial, principio activo, código de barras o limpia los filtros activos.</p>
                                <button type="button" onclick="clearInventoryFilters()" class="px-3.5 py-1.5 rounded-lg bg-[#1E40AF] text-white font-bold text-xs">
                                    Limpiar búsqueda y filtros
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <!-- ========================================================================= -->
        <!-- VISTA DE LOTES FÍSICOS (VITRINA / BODEGA / TODOS / BAJAS)                 -->
        <!-- ========================================================================= -->
        @if($ubicacion === 'BAJAS')
            <div class="p-3 rounded-xl bg-red-50/90 border border-red-200 text-red-900 text-xs font-medium flex items-center gap-2">
                <span>🗑️</span>
                <span><strong>Registro de Bajas y Productos Caducados:</strong> Lotes retirados de Vitrina y Bodega por vencimiento, merma o alerta sanitaria.</span>
            </div>
        @else
            <div class="p-3 rounded-xl bg-blue-50/90 border border-blue-200 text-blue-800 text-xs font-medium flex items-center gap-2">
                <span>⬇️</span>
                <span><strong>Control de Lotes Activos:</strong> Puedes ordenar por <strong>Cantidad de Stock</strong> o por <strong>Vencimiento FEFO</strong>. Pincha cualquier etiqueta de <em>Poco Stock</em> para añadir más unidades.</span>
            </div>
        @endif

        <!-- Tabla Interactiva Clasificada por FEFO y Ordenable por Cantidad de Stock -->
        <div id="tabla-lotes-fefo" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 text-[11px] font-bold uppercase text-slate-400 bg-slate-50/60 whitespace-nowrap">
                            <th class="py-3.5 px-4">
                                <button type="button" onclick="toggleColumnSort('nombre')" class="inline-flex items-center gap-1 hover:text-slate-800 uppercase font-bold">
                                    <span>Medicamento</span>
                                    <span class="text-slate-400">↕</span>
                                </button>
                            </th>
                            <th class="py-3.5 px-4">Condición Venta</th>
                            <th class="py-3.5 px-4">Código Lote</th>
                            <th class="py-3.5 px-4">Laboratorio</th>
                            <th class="py-3.5 px-4">{{ $ubicacion === 'BAJAS' ? 'Origen / Motivo de Baja' : 'Ubicación' }}</th>
                            <th class="py-3.5 px-4 text-center">
                                <button type="button" onclick="toggleColumnSort('stock')" class="inline-flex items-center gap-1 text-[#1E40AF] hover:text-blue-900 uppercase font-extrabold" title="Haz clic para ordenar por cantidad de stock (Menor a mayor / Mayor a menor)">
                                    <span>Cantidad Stock</span>
                                    <span id="th-stock-sort-indicator">↕</span>
                                </button>
                            </th>
                            <th class="py-3.5 px-4 text-center">
                                <button type="button" onclick="toggleColumnSort('fefo')" class="inline-flex items-center gap-1 hover:text-slate-800 uppercase font-bold" title="Ordenar por fecha de vencimiento FEFO">
                                    <span>Vencimiento</span>
                                    <span class="text-slate-400">↕</span>
                                </button>
                            </th>
                            <th class="py-3.5 px-4 text-center">Alerta FEFO</th>
                            <th class="py-3.5 px-4 text-center">Estado</th>
                            @if($canToggleLote)
                                <th class="py-3.5 px-4 text-right">Acciones</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody id="inv-table-body" class="divide-y divide-slate-100 text-xs">
                        @forelse($lotes as $lote)
                            @php
                                $dias = $lote->dias_restantes;
                                $sem = $lote->semaforo_fefo;
                                $medLote = $lote->medicamento;
                                $esControlado = $medLote?->requiere_receta || $medLote?->tipo_controlado;
                                $fefoKey = ($sem === 'critico' || $sem === 'vencido') ? 'critico' : ($sem === 'alerta' ? 'alerta' : 'optimo');
                                $umbralLote = $medLote ? $medLote->umbral_stock_bajo : 25;
                                $esLoteStockBajo = $lote->activo && ($lote->cantidad <= $umbralLote || ($medLote && $medLote->tiene_stock_bajo));
                                $loteBodRef = $medLote ? $loteBodegaPorMed->get($medLote->id_medicamento) : null;

                                $rowBg = ! $lote->activo
                                    ? 'bg-red-50/30 opacity-85'
                                    : match ($sem) {
                                        'vencido', 'critico' => 'bg-red-50/40 hover:bg-red-50/70',
                                        'alerta' => 'bg-amber-50/40 hover:bg-amber-50/70',
                                        default => $esLoteStockBajo ? 'bg-amber-50/25 hover:bg-amber-50/50' : 'hover:bg-slate-50/80',
                                    };
                            @endphp
                            <tr id="lote-row-{{ $lote->id_lote }}"
                                class="inv-filterable-row {{ $rowBg }} transition"
                                data-med-id="{{ $lote->id_medicamento }}"
                                data-name="{{ $medLote?->nombre_comercial }}"
                                data-sub="{{ $medLote?->principio_activo }}"
                                data-code="{{ $lote->lote_codigo }}"
                                data-ean="{{ $medLote?->codigo_barras }}"
                                data-lab="{{ $medLote?->laboratorio }}"
                                data-condicion="{{ $esControlado ? 'retenida' : 'libre' }}"
                                data-fefo="{{ $fefoKey }}"
                                data-stock-level="{{ $esLoteStockBajo ? 'bajo' : 'optimo' }}"
                                data-qty="{{ (int) $lote->cantidad }}"
                                data-total-qty="{{ (int) $lote->cantidad }}"
                                data-price="{{ (float) ($medLote?->precio_unitario ?? 0) }}"
                                data-days="{{ (int) $dias }}"
                                data-badge="{{ $esControlado ? '🔒 Retenida' : 'Venta Libre' }}"
                                data-stock="{{ $lote->ubicacion }}: {{ $lote->cantidad }} u. ({{ $dias }}d)">
                                <td class="py-3.5 px-4 font-medium {{ $lote->activo ? 'text-slate-900' : 'text-slate-500 line-through' }}">
                                    <div class="flex items-center gap-2">
                                        @if(! $lote->activo)
                                            <span class="w-2 h-2 rounded-full bg-red-400 shrink-0"></span>
                                        @elseif($sem === 'critico' || $sem === 'vencido')
                                            <span class="w-2 h-2 rounded-full bg-[#EF4444] shrink-0"></span>
                                        @elseif($sem === 'alerta')
                                            <span class="w-2 h-2 rounded-full bg-amber-500 shrink-0"></span>
                                        @else
                                            <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                                        @endif
                                        <div>
                                            <div class="font-bold">{{ $medLote?->nombre_comercial }}</div>
                                            <div class="text-[11px] text-slate-400 font-normal">
                                                {{ $medLote?->principio_activo }}
                                                @if($medLote?->codigo_barras)
                                                    <span class="font-mono-code text-[10px] text-slate-400">• EAN {{ $medLote->codigo_barras }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    @if($esControlado)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-red-50 text-red-700 border border-red-200 whitespace-nowrap"
                                              title="Medicamento sujeto a Receta Retenida y autorización del Químico Farmacéutico">
                                            🔒 Retenida
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 whitespace-nowrap">
                                            Venta Libre
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 font-mono-code text-[11px] text-slate-500 whitespace-nowrap">
                                    <span class="inline-block px-2 py-0.5 rounded bg-slate-100 border border-slate-200 whitespace-nowrap">
                                        {{ $lote->lote_codigo }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-slate-500 whitespace-nowrap">
                                    {{ $medLote?->laboratorio }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $lote->ubicacion === 'VITRINA' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-slate-100 text-slate-700 border border-slate-300' }}">
                                        {{ $lote->ubicacion }}
                                    </span>
                                    @if(! $lote->activo && $lote->motivo_baja)
                                        <div class="text-[10px] text-red-700 font-semibold mt-1">
                                            {{ $lote->motivo_baja }}
                                        </div>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <div class="inline-flex flex-col items-center gap-1">
                                        <span class="font-mono-code font-extrabold text-sm {{ $esLoteStockBajo ? 'text-red-600' : 'text-slate-900' }}">
                                            {{ $lote->cantidad }} <span class="text-[10px] font-normal text-slate-400">u.</span>
                                        </span>
                                        @if($esLoteStockBajo && $medLote)
                                            <button type="button"
                                                    onclick="handleLowStockMedicationClick({{ json_encode([
                                                        'id_medicamento' => $medLote->id_medicamento,
                                                        'nombre_comercial' => $medLote->nombre_comercial,
                                                        'codigo_barras' => $medLote->codigo_barras,
                                                        'stock_vitrina' => $medLote->stock_vitrina,
                                                        'stock_bodega' => $medLote->stock_bodega,
                                                        'umbral' => $umbralLote,
                                                        'id_lote_bodega' => $loteBodRef?->id_lote,
                                                        'cant_lote_bodega' => $loteBodRef?->cantidad ?? 0,
                                                    ]) }})"
                                                    class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 hover:bg-amber-200 text-amber-900 border border-amber-300 transition cursor-pointer"
                                                    title="Alerta de poco stock: pincha para añadir más unidades a {{ $medLote->nombre_comercial }}">
                                                📉 Poco stock {{ $canManageStock ? '• + Añadir' : '' }}
                                            </button>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-center font-mono-code text-slate-600 whitespace-nowrap">
                                    {{ $lote->fecha_vencimiento?->format('d-m-Y') }}
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    @if($sem === 'critico' || $sem === 'vencido')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-red-100 text-[#EF4444] border border-red-200 whitespace-nowrap">
                                            ● {{ $dias }}d
                                        </span>
                                    @elseif($sem === 'alerta')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-300 whitespace-nowrap">
                                            ▲ {{ $dias }}d
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 whitespace-nowrap">
                                            ✓ {{ $dias }}d
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold {{ $lote->activo ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700' }}">
                                        {{ $lote->activo ? 'DISPONIBLE' : 'DADO DE BAJA' }}
                                    </span>
                                </td>
                                @if($canToggleLote)
                                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                        <div class="inline-flex items-center justify-end gap-1.5">
                                            @if($canManageStock && $lote->activo && $medLote)
                                                <button type="button"
                                                        onclick="openNuevaRemesaForMedication({{ $medLote->id_medicamento }})"
                                                        class="px-2.5 py-1.5 rounded-lg border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 font-bold text-[11px] transition"
                                                        title="Ingresar un nuevo lote de stock para {{ $medLote->nombre_comercial }}">
                                                    + Añadir Lote
                                                </button>
                                            @endif

                                            @if($lote->activo && $lote->ubicacion === 'VITRINA' && $lote->cantidad > 0)
                                                <button type="button"
                                                        onclick="openRetiroVitrinaModal({{ $lote->id_lote }}, {{ $lote->cantidad }})"
                                                        class="px-2.5 py-1.5 rounded-lg border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-900 font-bold text-[11px] transition"
                                                        title="Devolver unidades o retirar este medicamento de Vitrina hacia Bodega">
                                                    &darr; A Bodega
                                                </button>
                                            @elseif($lote->activo && $lote->ubicacion === 'BODEGA' && $canManageStock && $lote->dias_restantes > 0)
                                                <button type="button"
                                                        onclick="openTraspasoVitrinaModal({{ $lote->id_lote }}, {{ min(25, $lote->cantidad) }})"
                                                        class="px-2.5 py-1.5 rounded-lg border border-blue-200 bg-blue-50 hover:bg-blue-100 text-[#1E40AF] font-bold text-[11px] transition"
                                                        title="Abastecer Vitrina desde este lote de Bodega">
                                                    &uarr; A Vitrina
                                                </button>
                                            @endif

                                            @if($canDarDeBaja)
                                                @if($lote->activo)
                                                    <button type="button"
                                                            onclick="openBajaCaducidadModal({{ json_encode([
                                                                'id_lote' => $lote->id_lote,
                                                                'lote_codigo' => $lote->lote_codigo,
                                                                'medicamento' => $medLote?->nombre_comercial,
                                                                'ubicacion' => $lote->ubicacion,
                                                                'cantidad' => $lote->cantidad,
                                                                'dias' => $dias,
                                                                'action_url' => route('inventario.toggle_batch', $lote->id_lote),
                                                            ]) }})"
                                                            class="px-2.5 py-1.5 rounded-lg border border-red-200 bg-red-50/80 hover:bg-red-100 text-red-700 font-bold text-[11px] transition"
                                                            title="Exclusivo Químico Farmacéutico: Retirar de {{ $lote->ubicacion }} por caducidad o merma sanitaria">
                                                        {{ $dias <= 30 ? '🗑️ Caducado' : '🗑️ Baja' }}
                                                    </button>
                                                @else
                                                    <form action="{{ route('inventario.toggle_batch', $lote->id_lote) }}" method="POST" class="inline">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit"
                                                                class="px-2.5 py-1.5 rounded-lg border border-emerald-200 bg-emerald-50/70 hover:bg-emerald-100 text-emerald-700 font-bold text-[11px] transition">
                                                            ↺ Restaurar a {{ $lote->ubicacion }}
                                                        </button>
                                                    </form>
                                                @endif
                                            @else
                                                <span class="px-2 py-1 rounded-lg bg-slate-100 border border-slate-200 text-slate-400 font-semibold text-[10px]"
                                                      title="Por normativa sanitaria ISP, solo el Químico Farmacéutico está facultado para dar de baja medicamentos">
                                                    🔒 Baja solo Q.F.
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canToggleLote ? 10 : 9 }}" class="py-10 text-center text-slate-400 text-xs">
                                    @if($ubicacion === 'BAJAS')
                                        No hay lotes dados de baja por caducidad o merma sanitaria en este momento.
                                    @else
                                        No hay lotes activos en {{ $ubicacion }}.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                        <tr id="inv-no-results-row" class="hidden">
                            <td colspan="{{ $canToggleLote ? 10 : 9 }}" class="py-10 text-center text-slate-500 text-xs">
                                <div class="font-bold text-slate-700 mb-1">🔍 No se encontraron lotes en {{ $ubicacion }} con esos criterios</div>
                                <p class="text-[11px] text-slate-400 mb-3">Intenta con otro nombre de medicamento, código de lote, laboratorio o limpia los filtros activos.</p>
                                <button type="button" onclick="clearInventoryFilters()" class="px-3.5 py-1.5 rounded-lg bg-[#1E40AF] text-white font-bold text-xs">
                                    Limpiar búsqueda y filtros
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>

<!-- ========================================================================= -->
<!-- MODALES DE GESTIÓN DE CATÁLOGO Y LOTES DE FARMACIA                        -->
<!-- ========================================================================= -->

@if($canManageMedications)
    <!-- Modal Nuevo Medicamento (Adquisición al Catálogo + Stock Inicial + Umbral Alerta) -->
    <div id="modal-nuevo-medicamento" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-xl w-full border border-slate-200 shadow-2xl overflow-hidden">
            <div class="bg-slate-900 text-white px-5 py-4 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-sm">💊 + Registrar Nuevo Producto en el Catálogo</h3>
                    <p class="text-[11px] text-slate-300">Incorpora un nuevo medicamento con su código EAN-13, alerta de stock mínimo y lote inicial</p>
                </div>
                <button type="button" onclick="document.getElementById('modal-nuevo-medicamento').classList.add('hidden')" class="text-slate-300 hover:text-white text-sm font-bold">✕</button>
            </div>
            <form action="{{ route('inventario.medications.store') }}" method="POST" class="p-5 space-y-4 text-xs">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nombre Comercial (3 a 100 caract.)</label>
                        <input type="text" id="nuevo-med-nombre" name="nombre_comercial" required placeholder="Ej: Zolpidem 10mg"
                               minlength="3" maxlength="100"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:border-[#1E40AF] outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Principio Activo / DCI (3 a 100 caract.)</label>
                        <input type="text" name="principio_activo" required placeholder="Ej: Zolpidem Tartrato"
                               minlength="3" maxlength="100"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:border-[#1E40AF] outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="font-bold text-slate-700">Código EAN-13</label>
                            <button type="button"
                                    onclick="document.getElementById('nuevo-med-ean').value = '78000010' + Math.floor(1000 + Math.random() * 8999)"
                                    class="text-[10px] font-bold text-[#1E40AF] hover:underline">
                                Generar EAN
                            </button>
                        </div>
                        <input type="text" id="nuevo-med-ean" name="codigo_barras" value="78000010{{ rand(1120, 9999) }}" required
                               minlength="6" maxlength="20"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Laboratorio</label>
                        <input type="text" name="laboratorio" list="datalist-laboratorios" required placeholder="Ej: Laboratorio Chile"
                               minlength="2" maxlength="80"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300">
                        <datalist id="datalist-laboratorios">
                            <option value="Laboratorio Chile"></option>
                            <option value="Saval"></option>
                            <option value="Mintlab"></option>
                            <option value="Bagó"></option>
                            <option value="Andrómaco"></option>
                            <option value="Pfizer"></option>
                            <option value="Roche"></option>
                            <option value="Grünenthal"></option>
                        </datalist>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Categoría Terapéutica</label>
                        <input type="text" name="categoria" list="datalist-categorias" placeholder="Ej: Analgésico OTC"
                               minlength="2" maxlength="60"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300">
                        <datalist id="datalist-categorias">
                            <option value="Analgésico OTC"></option>
                            <option value="Antiinflamatorio OTC"></option>
                            <option value="Antibiótico"></option>
                            <option value="Cardiovascular / GES"></option>
                            <option value="Antidiabético GES"></option>
                            <option value="Gastroenterología"></option>
                            <option value="Psicotrópico (Receta Retenida ISP)"></option>
                        </datalist>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Precio Venta ($ CLP)</label>
                        <input type="number" name="precio_unitario" min="100" max="9999999" step="10" value="6990" required
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white font-mono-code font-bold">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1" title="Cuando el stock en vitrina sea igual o menor a este número, se activará la alerta de poco stock">
                            Alerta Stock Mínimo (u.)
                        </label>
                        <input type="number" name="stock_minimo" min="1" max="10000" value="25"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white font-mono-code">
                    </div>
                    <div>
                        <label class="block font-bold text-amber-950 mb-1">Condición de Venta ISP</label>
                        <select name="condicion_receta" class="w-full px-3 py-2 rounded-lg border border-amber-300 bg-white font-semibold text-slate-800">
                            <option value="venta_libre">✓ Venta Libre / Directa</option>
                            <option value="receta_retenida">🔒 Receta Retenida ISP</option>
                        </select>
                    </div>
                </div>

                <div class="p-3.5 rounded-xl bg-emerald-50/70 border border-emerald-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="font-bold text-emerald-950">📦 Primer Lote de Stock Inicial (Inmediato)</div>
                        <span class="text-[10px] font-semibold text-emerald-700">Deja cantidad en 0 si aún no ingresa stock físico</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Cantidad Inicial</label>
                            <input type="number" name="cantidad_inicial" min="0" max="100000" value="50"
                                   class="w-full px-2.5 py-1.5 rounded-lg border border-emerald-300 bg-white font-mono-code font-bold">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Código Lote</label>
                            <input type="text" name="lote_codigo" value="LOT-2026-{{ rand(300, 999) }}"
                                   minlength="4" maxlength="25"
                                   class="w-full px-2.5 py-1.5 rounded-lg border border-emerald-300 bg-white font-mono-code">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Ubicación Destino</label>
                            <select name="ubicacion_inicial" class="w-full px-2 py-1.5 rounded-lg border border-emerald-300 bg-white font-semibold">
                                <option value="VITRINA">VITRINA (Venta)</option>
                                <option value="BODEGA">BODEGA (Reserva)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Vencimiento</label>
                            <input type="date" name="fecha_vencimiento" value="{{ now()->addMonths(12)->toDateString() }}"
                                   min="{{ now()->addDay()->toDateString() }}" max="{{ now()->addYears(10)->toDateString() }}"
                                   class="w-full px-2 py-1.5 rounded-lg border border-emerald-300 bg-white font-mono-code">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" onclick="document.getElementById('modal-nuevo-medicamento').classList.add('hidden')"
                            class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600 font-semibold">Cancelar</button>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-bold shadow-sm">
                        Guardar Producto en Catálogo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Medicamento y Condición de Receta -->
    <div id="modal-editar-medicamento" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full border border-slate-200 shadow-xl overflow-hidden">
            <div class="bg-[#1E40AF] text-white px-5 py-4 flex items-center justify-between">
                <h3 class="font-bold text-sm">Editar Medicamento y Umbral de Stock</h3>
                <button type="button" onclick="document.getElementById('modal-editar-medicamento').classList.add('hidden')" class="text-white">✕</button>
            </div>
            <form id="form-editar-medicamento" method="POST" class="p-5 space-y-3.5 text-xs">
                @csrf
                @method('PUT')
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Nombre Comercial (3 a 100 caract.)</label>
                    <input type="text" id="edit-med-nombre" name="nombre_comercial" required
                           minlength="3" maxlength="100"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Principio Activo (3 a 100 caract.)</label>
                        <input type="text" id="edit-med-principio" name="principio_activo" required
                               minlength="3" maxlength="100"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Laboratorio (2 a 80 caract.)</label>
                        <input type="text" id="edit-med-laboratorio" name="laboratorio" required
                               minlength="2" maxlength="80"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300">
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2.5">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Precio Venta ($)</label>
                        <input type="number" id="edit-med-precio" name="precio_unitario" min="1" max="9999999" step="1" required
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Stock Mínimo</label>
                        <input type="number" id="edit-med-stock-min" name="stock_minimo" min="1" max="10000" value="25"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Estado</label>
                        @if($canDarDeBaja)
                            <select id="edit-med-activo" name="activo"
                                    class="w-full px-2.5 py-2 rounded-lg border border-slate-300">
                                <option value="1">Activo</option>
                                <option value="0">Deshabilitado (Dar de baja)</option>
                            </select>
                        @else
                            <input type="hidden" id="edit-med-activo" name="activo" value="1">
                            <div class="w-full px-2.5 py-2 rounded-lg border border-slate-200 bg-slate-100 text-slate-500 text-[11px] font-semibold"
                                 title="Solo el Químico Farmacéutico está facultado para dar de baja medicamentos">
                                🔒 Activo (Baja solo Q.F.)
                            </div>
                        @endif
                    </div>
                </div>
                <div class="p-3.5 rounded-xl bg-amber-50/70 border border-amber-200 space-y-1.5">
                    <label class="block font-bold text-amber-950">Condición de Venta</label>
                    <select id="edit-med-receta" name="condicion_receta"
                            class="w-full px-3 py-2 rounded-lg border border-amber-300 bg-white font-semibold text-slate-800">
                        <option value="venta_libre">✓ Venta Libre</option>
                        <option value="receta_retenida">🔒 Receta Retenida</option>
                    </select>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modal-editar-medicamento').classList.add('hidden')"
                            class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-[#1E40AF] text-white font-bold">
                        Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditMedicationModal(med) {
            const modal = document.getElementById('modal-editar-medicamento');
            const form = document.getElementById('form-editar-medicamento');
            if (!modal || !form) return;

            form.action = med.update_url;
            document.getElementById('edit-med-nombre').value = med.nombre_comercial || '';
            document.getElementById('edit-med-principio').value = med.principio_activo || '';
            document.getElementById('edit-med-laboratorio').value = med.laboratorio || '';
            document.getElementById('edit-med-precio').value = Math.round(med.precio_unitario || 0);
            const stockMinEl = document.getElementById('edit-med-stock-min');
            if (stockMinEl) stockMinEl.value = med.stock_minimo || 25;
            document.getElementById('edit-med-receta').value = med.condicion_receta || 'venta_libre';
            document.getElementById('edit-med-activo').value = String(med.activo ?? '1');

            modal.classList.remove('hidden');
        }
    </script>
@endif

@if($canToggleLote)
    <!-- Modal Retiro / Devolución Vitrina -> Bodega -->
    <div id="modal-retiro-vitrina" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full border border-slate-200 shadow-xl overflow-hidden">
            <div class="bg-amber-600 text-white px-5 py-4 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-sm">&darr; Retirar o Devolver Vitrina &rarr; Bodega</h3>
                    <p class="text-[11px] text-amber-100">Retira unidades o quita completamente el lote de la vitrina de ventas</p>
                </div>
                <button type="button" onclick="document.getElementById('modal-retiro-vitrina').classList.add('hidden')" class="text-white">✕</button>
            </div>
            <form action="{{ route('inventario.return_to_storage') }}" method="POST" class="p-5 space-y-4 text-xs">
                @csrf
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Lote Actualmente en Vitrina</label>
                    <select id="retiro-id-lote" name="id_lote" required onchange="syncMaxRetiroVitrina()"
                            class="w-full px-3 py-2.5 rounded-lg border border-slate-300">
                        @foreach($lotesVitrina ?? [] as $lv)
                            <option value="{{ $lv->id_lote }}" data-stock="{{ $lv->cantidad }}">
                                {{ $lv->medicamento?->nombre_comercial }} — {{ $lv->lote_codigo }} (En vitrina: {{ $lv->cantidad }} u. | Vence en {{ $lv->dias_restantes }}d)
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block font-bold text-slate-700">Cantidad de Unidades a Retirar</label>
                        <button type="button" onclick="setRetirarTodoLote()"
                                class="text-[11px] font-bold text-amber-700 hover:underline">
                            Retirar el 100% de vitrina
                        </button>
                    </div>
                    <input type="number" id="retiro-cantidad" name="cantidad" min="1" value="10" required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code font-bold">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Motivo del Retiro</label>
                    <select name="motivo" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                        <option value="Sobrestock / Reorganización de mesón">Sobrestock / Reorganización de mesón</option>
                        <option value="Retiro preventivo por vencimiento próximo">Retiro preventivo por vencimiento próximo</option>
                        <option value="Resguardo sanitario en bodega">Resguardo sanitario en bodega</option>
                        <option value="Envase secundario deteriorado">Envase secundario deteriorado</option>
                        <option value="Corrección de traspaso interno">Corrección de traspaso interno</option>
                    </select>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modal-retiro-vitrina').classList.add('hidden')"
                            class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-bold">
                        Confirmar Retiro a Bodega
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function syncMaxRetiroVitrina() {
            const sel = document.getElementById('retiro-id-lote');
            const input = document.getElementById('retiro-cantidad');
            if (!sel || !input || !sel.selectedOptions[0]) return;
            const maxStock = parseInt(sel.selectedOptions[0].getAttribute('data-stock') || '1', 10);
            input.max = maxStock;
            if (parseInt(input.value || '0', 10) > maxStock) {
                input.value = maxStock;
            }
        }

        function setRetirarTodoLote() {
            const sel = document.getElementById('retiro-id-lote');
            const input = document.getElementById('retiro-cantidad');
            if (!sel || !input || !sel.selectedOptions[0]) return;
            const maxStock = parseInt(sel.selectedOptions[0].getAttribute('data-stock') || '1', 10);
            input.max = maxStock;
            input.value = maxStock;
        }

        function openRetiroVitrinaModal(idLote, stockActual) {
            const modal = document.getElementById('modal-retiro-vitrina');
            const sel = document.getElementById('retiro-id-lote');
            const input = document.getElementById('retiro-cantidad');
            if (!modal || !sel || !input) return;

            sel.value = String(idLote);
            input.max = stockActual;
            input.value = stockActual;
            modal.classList.remove('hidden');
        }

        function openTraspasoVitrinaModal(idLote, cantSugerida) {
            const modal = document.getElementById('modal-traspaso-fefo');
            const sel = document.getElementById('traspaso-bodega-id-lote');
            const input = document.getElementById('traspaso-bodega-cantidad');
            if (!modal) return;
            if (sel && idLote) sel.value = String(idLote);
            if (input && cantSugerida) input.value = cantSugerida;
            modal.classList.remove('hidden');
        }

        function openBajaCaducidadModal(lote) {
            const modal = document.getElementById('modal-baja-caducidad');
            const form = document.getElementById('form-baja-caducidad');
            const titleEl = document.getElementById('baja-med-nombre');
            const detailEl = document.getElementById('baja-lote-detalle');
            const selectEl = document.getElementById('baja-motivo-select');
            if (!modal || !form) return;

            form.action = lote.action_url;
            if (titleEl) titleEl.textContent = lote.medicamento || 'Medicamento';
            if (detailEl) {
                detailEl.textContent = `Lote ${lote.lote_codigo} • Ubicación: ${lote.ubicacion} • ${lote.cantidad} u.`;
            }
            if (selectEl) {
                if (lote.dias <= 0) {
                    selectEl.value = 'Vencimiento / Producto Caducado';
                } else if (lote.dias <= 30) {
                    selectEl.value = 'Retiro Preventivo por Vencimiento Próximo';
                } else {
                    selectEl.value = 'Merma / Rotura o Deterioro de Envase';
                }
            }
            modal.classList.remove('hidden');
        }
    </script>

    <!-- Modal Baja por Caducidad / Merma Sanitaria -->
    <div id="modal-baja-caducidad" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full border border-slate-200 shadow-xl overflow-hidden">
            <div class="bg-red-700 text-white px-5 py-4 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-sm">🗑️ Quitar por Caducado / Dar de Baja</h3>
                    <p class="text-[11px] text-red-100">Retira el lote de Vitrina o Bodega y lo mueve al registro de Bajas y Caducados</p>
                </div>
                <button type="button" onclick="document.getElementById('modal-baja-caducidad').classList.add('hidden')" class="text-white">✕</button>
            </div>
            <form id="form-baja-caducidad" method="POST" class="p-5 space-y-4 text-xs">
                @csrf
                @method('PATCH')

                <div class="p-3 rounded-xl bg-red-50 border border-red-200">
                    <div id="baja-med-nombre" class="font-bold text-slate-900 text-sm"></div>
                    <div id="baja-lote-detalle" class="text-[11px] text-red-800 font-mono-code mt-0.5"></div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Motivo de Baja</label>
                    <select id="baja-motivo-select" name="motivo_baja" required class="w-full px-3 py-2.5 rounded-lg border border-slate-300 bg-white">
                        <option value="Vencimiento / Producto Caducado">Vencimiento / Producto Caducado</option>
                        <option value="Retiro Preventivo por Vencimiento Próximo">Retiro Preventivo por Vencimiento Próximo</option>
                        <option value="Merma / Rotura o Deterioro de Envase">Merma / Rotura o Deterioro de Envase</option>
                        <option value="Alerta Sanitaria / Retiro de Lote ISP">Alerta Sanitaria / Retiro de Lote ISP</option>
                    </select>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modal-baja-caducidad').classList.add('hidden')"
                            class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white font-bold">
                        Confirmar Baja
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

@if($canManageStock)
    <!-- Modal Traspaso Bodega -> Vitrina -->
    <div id="modal-traspaso-fefo" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full border border-slate-200 shadow-xl overflow-hidden">
            <div class="bg-[#1E40AF] text-white px-5 py-4 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-sm">&uarr; Traspaso Bodega &rarr; Vitrina</h3>
                    <p class="text-[11px] text-blue-200">Prioriza automáticamente los lotes con vencimiento más próximo (FEFO)</p>
                </div>
                <button type="button" onclick="document.getElementById('modal-traspaso-fefo').classList.add('hidden')" class="text-white">✕</button>
            </div>
            <form action="{{ route('inventario.transfer') }}" method="POST" class="p-5 space-y-4 text-xs">
                @csrf
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Lote Disponible en Bodega</label>
                    <select id="traspaso-bodega-id-lote" name="id_lote" required class="w-full px-3 py-2.5 rounded-lg border border-slate-300">
                        @foreach($lotesBodega as $lb)
                            <option value="{{ $lb->id_lote }}">
                                {{ $lb->medicamento?->nombre_comercial }} — {{ $lb->lote_codigo }} (Stock: {{ $lb->cantidad }} u. | Vence en {{ $lb->dias_restantes }}d)
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block font-bold text-slate-700">Cantidad a Traspasar</label>
                        <div class="flex items-center gap-1">
                            @foreach([15, 25, 50] as $qTraspaso)
                                <button type="button"
                                        onclick="document.getElementById('traspaso-bodega-cantidad').value = {{ $qTraspaso }}"
                                        class="px-2 py-0.5 rounded bg-blue-50 hover:bg-blue-100 text-[#1E40AF] font-bold text-[10px]">
                                    {{ $qTraspaso }} u.
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <input type="number" id="traspaso-bodega-cantidad" name="cantidad" min="1" value="25" required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code font-bold">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modal-traspaso-fefo').classList.add('hidden')"
                            class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-[#1E40AF] text-white font-bold">
                        Confirmar Traspaso
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Nueva Remesa / Ingreso Intuitivo de Lote de Stock -->
    <div id="modal-nueva-remesa" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full border border-slate-200 shadow-2xl overflow-hidden">
            <div class="bg-[#10B981] text-white px-5 py-4 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-sm">📦 + Ingresar Lote de Stock (Abastecimiento)</h3>
                    <p class="text-[11px] text-emerald-100">Busca por nombre o escanea el código EAN para añadir unidades a Vitrina o Bodega</p>
                </div>
                <button type="button" onclick="document.getElementById('modal-nueva-remesa').classList.add('hidden')" class="text-white font-bold">✕</button>
            </div>
            <form action="{{ route('inventario.register') }}" method="POST" class="p-5 space-y-3.5 text-xs">
                @csrf

                <!-- Buscador Rápido por Nombre, Principio Activo o Código EAN dentro del Modal -->
                <div class="p-3 rounded-xl bg-emerald-50/80 border border-emerald-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <label for="remesa-scan-ean" class="font-bold text-emerald-950">
                            🔍 Buscar Medicamento por Nombre, Laboratorio o Código EAN-13
                        </label>
                        <span class="text-[10px] font-bold text-emerald-700">Filtro instantáneo</span>
                    </div>
                    <input type="text"
                           id="remesa-scan-ean"
                           oninput="filterRemesaMedicationSelect(this.value)"
                           placeholder="Escribe ej: 'Lorazepam', 'Paracetamol' o escanea EAN 780000..."
                           class="w-full px-3 py-2 rounded-lg border border-emerald-300 bg-white text-xs font-medium outline-none focus:border-emerald-600">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="remesa-select-med" class="block font-bold text-slate-700">
                            Medicamento Seleccionado (<span id="remesa-med-count">{{ $medicamentos->count() }}</span> disponibles)
                        </label>
                        @if($canManageMedications)
                            <button type="button"
                                    onclick="switchRemesaToNuevoMedicamento()"
                                    class="text-[11px] font-bold text-[#1E40AF] hover:underline">
                                💊 ¿Producto nuevo? Crear en Catálogo &rarr;
                            </button>
                        @endif
                    </div>
                    <select id="remesa-select-med"
                            name="id_medicamento"
                            required
                            onchange="updateRemesaSelectedMedCard()"
                            class="w-full px-3 py-2.5 rounded-lg border border-slate-300 bg-white font-semibold text-slate-800">
                        @foreach($medicamentos as $m)
                            @php
                                $vSt = $m->stock_vitrina;
                                $bSt = $m->stock_bodega;
                                $umb = $m->umbral_stock_bajo;
                                $lBod = $loteBodegaPorMed->get($m->id_medicamento);
                            @endphp
                            <option value="{{ $m->id_medicamento }}"
                                    data-ean="{{ $m->codigo_barras }}"
                                    data-nombre="{{ $m->nombre_comercial }}"
                                    data-principio="{{ $m->principio_activo }}"
                                    data-lab="{{ $m->laboratorio }}"
                                    data-vitrina="{{ $vSt }}"
                                    data-bodega="{{ $bSt }}"
                                    data-umbral="{{ $umb }}"
                                    data-lote-bodega-id="{{ $lBod?->id_lote ?? '' }}"
                                    data-lote-bodega-cant="{{ $lBod?->cantidad ?? 0 }}"
                                    data-controlado="{{ ($m->requiere_receta || $m->tipo_controlado) ? '1' : '0' }}">
                                {{ $m->nombre_comercial }} ({{ $m->laboratorio }}) — [Vit: {{ $vSt }} u. | Bod: {{ $bSt }} u.] — EAN {{ $m->codigo_barras }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Tarjeta Informativa en Vivo del Medicamento Seleccionado -->
                <div id="remesa-selected-med-card" class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <div id="remesa-card-title" class="font-bold text-slate-900 text-xs">Selecciona un medicamento</div>
                        <div id="remesa-card-sub" class="text-[11px] text-slate-500 font-mono-code"></div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span id="remesa-card-stock-badge" class="px-2.5 py-1 rounded-lg font-mono-code font-bold text-[11px] bg-blue-50 text-[#1E40AF] border border-blue-200"></span>
                        <button type="button"
                                id="remesa-card-traspaso-btn"
                                onclick="switchFromRemesaToTraspasoBodega()"
                                class="hidden px-2.5 py-1 rounded-lg bg-[#1E40AF] hover:bg-blue-800 text-white font-bold text-[10px] transition">
                            &uarr; Traspasar de Bodega
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block font-bold text-slate-700">Código de Lote</label>
                            <button type="button"
                                    onclick="document.getElementById('remesa-lote-codigo').value = 'LOT-2026-' + Math.floor(100 + Math.random() * 899)"
                                    class="text-[10px] font-bold text-emerald-700 hover:underline">
                                Generar código
                            </button>
                        </div>
                        <input type="text" id="remesa-lote-codigo" name="lote_codigo" value="LOT-2026-{{ rand(300, 999) }}" required
                               minlength="4" maxlength="25"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Ubicación Destino</label>
                        <select id="remesa-ubicacion" name="ubicacion" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-semibold text-slate-800">
                            <option value="VITRINA" {{ $ubicacion === 'VITRINA' ? 'selected' : '' }}>🏪 VITRINA (Disponible para venta POS)</option>
                            <option value="BODEGA" {{ $ubicacion === 'BODEGA' ? 'selected' : '' }}>📦 BODEGA (Almacenamiento / Reserva)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block font-bold text-slate-700">Cantidad Unidades</label>
                            <div class="flex items-center gap-1">
                                @foreach([25, 50, 100, 200] as $qPreset)
                                    <button type="button"
                                            onclick="document.getElementById('remesa-cantidad').value = {{ $qPreset }}"
                                            class="px-1.5 py-0.5 rounded bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 font-bold text-[10px]">
                                        +{{ $qPreset }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <input type="number" id="remesa-cantidad" name="cantidad" min="1" max="100000" value="50" required
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code font-bold text-sm">
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block font-bold text-slate-700">Fecha Vencimiento</label>
                            <div class="flex items-center gap-1">
                                <button type="button"
                                        onclick="document.getElementById('remesa-vencimiento').value = '{{ now()->addMonths(6)->toDateString() }}'"
                                        class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[10px]">
                                    +6m
                                </button>
                                <button type="button"
                                        onclick="document.getElementById('remesa-vencimiento').value = '{{ now()->addYear()->toDateString() }}'"
                                        class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[10px]">
                                    +1a
                                </button>
                                <button type="button"
                                        onclick="document.getElementById('remesa-vencimiento').value = '{{ now()->addYears(2)->toDateString() }}'"
                                        class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[10px]">
                                    +2a
                                </button>
                            </div>
                        </div>
                        <input type="date" id="remesa-vencimiento" name="fecha_vencimiento" value="{{ now()->addYear()->toDateString() }}" required
                               min="{{ now()->addDay()->toDateString() }}" max="{{ now()->addYears(10)->toDateString() }}"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modal-nueva-remesa').classList.add('hidden')"
                            class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600 font-semibold">Cancelar</button>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-[#10B981] hover:bg-emerald-700 text-white font-bold shadow-sm">
                        Registrar Lote y Actualizar Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

<script>
    const CAN_MANAGE_STOCK = {{ $canManageStock ? 'true' : 'false' }};
    const CURRENT_ROLE_LABEL = @json(auth()->user()->role_label);
    const INITIAL_REPONER_MED_ID = {{ (int) ($reponerMedId ?? 0) }};

    function normalizeInvStr(str) {
        return String(str || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function escapeInvHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function handleInventorySearchInput(rawQuery) {
        filterInventoryTable(rawQuery);
        renderInventoryAutocomplete(rawQuery);
    }

    /**
     * Ordena dinámicamente las filas de la tabla por Cantidad de Stock, Vencimiento FEFO, Nombre o Precio,
     * y luego aplica los filtros activos.
     */
    function sortAndFilterInventoryTable() {
        const sortSelect = document.getElementById('inv-sort-order');
        const tbody = document.getElementById('inv-table-body');
        if (!sortSelect || !tbody) {
            filterInventoryTable();
            return;
        }

        const sortMode = sortSelect.value;
        const rows = Array.from(tbody.querySelectorAll('tr.inv-filterable-row'));

        rows.sort((a, b) => {
            const qtyA = parseInt(a.getAttribute('data-qty') || '0', 10);
            const qtyB = parseInt(b.getAttribute('data-qty') || '0', 10);
            const totA = parseInt(a.getAttribute('data-total-qty') || '0', 10);
            const totB = parseInt(b.getAttribute('data-total-qty') || '0', 10);
            const daysA = parseInt(a.getAttribute('data-days') || '999', 10);
            const daysB = parseInt(b.getAttribute('data-days') || '999', 10);
            const priceA = parseFloat(a.getAttribute('data-price') || '0');
            const priceB = parseFloat(b.getAttribute('data-price') || '0');
            const nameA = normalizeInvStr(a.getAttribute('data-name'));
            const nameB = normalizeInvStr(b.getAttribute('data-name'));

            if (sortMode === 'stock_asc') {
                if (qtyA !== qtyB) return qtyA - qtyB;
                if (totA !== totB) return totA - totB;
                return daysA - daysB;
            }
            if (sortMode === 'stock_desc') {
                if (qtyA !== qtyB) return qtyB - qtyA;
                if (totA !== totB) return totB - totA;
                return daysA - daysB;
            }
            if (sortMode === 'nombre_asc') {
                return nameA.localeCompare(nameB);
            }
            if (sortMode === 'precio_desc') {
                return priceB - priceA;
            }
            // Default: fefo_asc
            if (daysA !== daysB) return daysA - daysB;
            return qtyA - qtyB;
        });

        const noResultsRow = document.getElementById('inv-no-results-row');
        rows.forEach(tr => tbody.appendChild(tr));
        if (noResultsRow) tbody.appendChild(noResultsRow);

        const indicator = document.getElementById('th-stock-sort-indicator');
        if (indicator) {
            indicator.textContent = sortMode === 'stock_asc' ? '↑ (Menor)' : (sortMode === 'stock_desc' ? '↓ (Mayor)' : '↕');
        }

        filterInventoryTable();
    }

    function setQuickStockSort(mode) {
        const sortSelect = document.getElementById('inv-sort-order');
        if (sortSelect) {
            sortSelect.value = mode;
        }
        sortAndFilterInventoryTable();
    }

    function toggleColumnSort(column) {
        const sortSelect = document.getElementById('inv-sort-order');
        if (!sortSelect) return;

        if (column === 'stock') {
            sortSelect.value = sortSelect.value === 'stock_asc' ? 'stock_desc' : 'stock_asc';
        } else if (column === 'nombre') {
            sortSelect.value = 'nombre_asc';
        } else if (column === 'precio') {
            sortSelect.value = 'precio_desc';
        } else if (column === 'fefo') {
            sortSelect.value = 'fefo_asc';
        }
        sortAndFilterInventoryTable();
    }

    function filterInventoryTable(optionalQuery) {
        const searchInput = document.getElementById('inv-live-search');
        const condSelect = document.getElementById('inv-filter-condicion');
        const fefoSelect = document.getElementById('inv-filter-fefo');
        const stockSelect = document.getElementById('inv-filter-stock');
        const clearBtn = document.getElementById('btn-clear-inv-search');
        const resetBtn = document.getElementById('btn-reset-inv-filters');
        const counterEl = document.getElementById('inv-visible-counter');
        const noResultsRow = document.getElementById('inv-no-results-row');

        const rawQ = optionalQuery !== undefined ? optionalQuery : (searchInput ? searchInput.value : '');
        const q = normalizeInvStr(rawQ);
        const condVal = condSelect ? condSelect.value : '';
        const fefoVal = fefoSelect ? fefoSelect.value : '';
        const stockVal = stockSelect ? stockSelect.value : '';

        if (clearBtn) {
            clearBtn.classList.toggle('hidden', q.length === 0);
        }
        if (resetBtn) {
            resetBtn.classList.toggle('hidden', q.length === 0 && !condVal && !fefoVal && !stockVal);
        }

        const rows = document.querySelectorAll('tr.inv-filterable-row');
        let visibleCount = 0;

        rows.forEach(tr => {
            const name = normalizeInvStr(tr.getAttribute('data-name'));
            const sub = normalizeInvStr(tr.getAttribute('data-sub'));
            const code = normalizeInvStr(tr.getAttribute('data-code'));
            const ean = normalizeInvStr(tr.getAttribute('data-ean'));
            const lab = normalizeInvStr(tr.getAttribute('data-lab'));
            const txt = normalizeInvStr(tr.textContent);
            const rowCond = tr.getAttribute('data-condicion') || '';
            const rowFefo = tr.getAttribute('data-fefo') || '';
            const rowStockLevel = tr.getAttribute('data-stock-level') || '';

            const matchesSearch = !q || name.includes(q) || sub.includes(q) || code.includes(q) || ean.includes(q) || lab.includes(q) || txt.includes(q);
            const matchesCond = !condVal || rowCond === condVal;
            const matchesFefo = !fefoVal
                || (fefoVal === 'por_vencer' ? (rowFefo === 'critico' || rowFefo === 'alerta') : rowFefo === fefoVal);
            const matchesStock = !stockVal || rowStockLevel === stockVal;

            if (matchesSearch && matchesCond && matchesFefo && matchesStock) {
                tr.style.display = '';
                visibleCount++;
            } else {
                tr.style.display = 'none';
            }
        });

        // Sincronizar estado visual de botón superior FEFO
        const topFefoBtn = document.getElementById('btn-alerta-fefo-top');
        const topFefoAction = document.getElementById('btn-alerta-fefo-action');
        if (topFefoBtn && topFefoAction) {
            const isFefoActive = fefoVal === 'por_vencer' || fefoVal === 'critico';
            topFefoBtn.className = 'px-3.5 py-2 rounded-lg border text-xs font-bold flex items-center gap-1.5 shadow-2xs transition cursor-pointer ' +
                (isFefoActive ? 'bg-amber-600 border-amber-700 text-white ring-2 ring-amber-300' : 'bg-amber-50 hover:bg-amber-100 border-amber-300 text-amber-800');
            topFefoAction.textContent = isFefoActive ? '(Ver todos)' : 'Ver →';
        }

        // Sincronizar estado visual de botón superior Poco Stock
        const topStockBtn = document.getElementById('btn-alerta-stock-bajo-top');
        const topStockAction = document.getElementById('btn-alerta-stock-action');
        if (topStockBtn && topStockAction) {
            const isStockActive = stockVal === 'bajo';
            topStockBtn.className = 'px-3.5 py-2 rounded-lg border text-xs font-bold flex items-center gap-1.5 shadow-2xs transition cursor-pointer ' +
                (isStockActive ? 'bg-red-600 border-red-700 text-white ring-2 ring-red-300' : 'bg-red-50 hover:bg-red-100 border-red-300 text-red-800');
            topStockAction.textContent = isStockActive ? '(Ver todos)' : 'Abastecer →';
        }

        if (noResultsRow) {
            noResultsRow.classList.toggle('hidden', rows.length === 0 || visibleCount > 0);
        }
        if (counterEl) {
            counterEl.textContent = `Mostrando ${visibleCount} de ${rows.length} registros`;
        }
    }

    function toggleLowStockAlertPanel() {
        const panel = document.getElementById('panel-alertas-stock-bajo');
        const stockSelect = document.getElementById('inv-filter-stock');

        if (panel && panel.classList.contains('hidden')) {
            panel.classList.remove('hidden');
            applyLowStockTableFilter();
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else if (stockSelect && stockSelect.value === 'bajo') {
            stockSelect.value = '';
            if (panel) panel.classList.add('hidden');
            filterInventoryTable();
        } else {
            applyLowStockTableFilter();
        }
    }

    function applyLowStockTableFilter() {
        const stockSelect = document.getElementById('inv-filter-stock');
        const sortSelect = document.getElementById('inv-sort-order');
        const searchInput = document.getElementById('inv-live-search');
        const fefoSelect = document.getElementById('inv-filter-fefo');

        if (searchInput) searchInput.value = '';
        if (fefoSelect) fefoSelect.value = '';
        if (stockSelect) stockSelect.value = 'bajo';
        if (sortSelect) sortSelect.value = 'stock_asc';

        sortAndFilterInventoryTable();
    }

    /**
     * Al pinchar un medicamento con alerta de poco stock:
     * 1. Lo localiza y resalta en la tabla de inventario.
     * 2. Si el usuario tiene permisos Can_Manage_Stock (Bodeguero / Administrador), abre el modal
     *    para añadirle más stock con ese medicamento preseleccionado.
     * 3. Si no tiene permisos (Vendedor / Q.F.), muestra aviso de permisos y resalta el producto.
     */
    function handleLowStockMedicationClick(medInfo) {
        if (!medInfo) return;

        const searchInput = document.getElementById('inv-live-search');
        const stockSelect = document.getElementById('inv-filter-stock');
        const fefoSelect = document.getElementById('inv-filter-fefo');
        const condSelect = document.getElementById('inv-filter-condicion');

        if (stockSelect) stockSelect.value = '';
        if (fefoSelect) fefoSelect.value = '';
        if (condSelect) condSelect.value = '';
        if (searchInput) {
            searchInput.value = medInfo.nombre_comercial || medInfo.codigo_barras || '';
        }

        filterInventoryTable(searchInput ? searchInput.value : '');

        // Resaltar la fila del medicamento en la tabla actual
        const matchingRows = Array.from(document.querySelectorAll(`tr.inv-filterable-row[data-med-id="${medInfo.id_medicamento}"]`));
        if (matchingRows.length > 0) {
            matchingRows[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            matchingRows.forEach(tr => {
                tr.classList.add('ring-2', 'ring-red-500', 'bg-amber-100/80');
                setTimeout(() => {
                    tr.classList.remove('ring-2', 'ring-red-500', 'bg-amber-100/80');
                }, 2600);
            });
        }

        if (CAN_MANAGE_STOCK) {
            openNuevaRemesaForMedication(medInfo.id_medicamento, 'VITRINA');
        } else {
            const notice = document.getElementById('low-stock-permission-notice');
            const textEl = document.getElementById('low-stock-permission-text');
            if (notice && textEl) {
                textEl.innerHTML = `Medicamento localizado: <strong>${escapeInvHtml(medInfo.nombre_comercial)}</strong> (Vitrina: <strong>${medInfo.stock_vitrina} u.</strong> | Bodega: <strong>${medInfo.stock_bodega} u.</strong>). Tu perfil actual (<strong>${escapeInvHtml(CURRENT_ROLE_LABEL)}</strong>) tiene acceso de consulta; para ingresar nuevos lotes o traspasar stock se requiere perfil <strong>Bodeguero</strong> o <strong>Administrador</strong>.`;
                notice.classList.remove('hidden');
                notice.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }

    /**
     * Abre el modal "+ Ingresar Lote de Stock" preseleccionando el medicamento indicado.
     */
    function openNuevaRemesaForMedication(idMedicamento, defaultUbicacion) {
        const modal = document.getElementById('modal-nueva-remesa');
        const select = document.getElementById('remesa-select-med');
        const scanInput = document.getElementById('remesa-scan-ean');
        const ubicSelect = document.getElementById('remesa-ubicacion');
        const cantInput = document.getElementById('remesa-cantidad');

        if (!modal) return;

        // Restaurar todas las opciones del selector antes de seleccionar
        if (scanInput) scanInput.value = '';
        filterRemesaMedicationSelect('');

        if (select && idMedicamento) {
            select.value = String(idMedicamento);
        }
        if (ubicSelect && defaultUbicacion) {
            ubicSelect.value = defaultUbicacion;
        }

        updateRemesaSelectedMedCard();
        modal.classList.remove('hidden');

        if (cantInput) {
            setTimeout(() => {
                cantInput.focus();
                cantInput.select();
            }, 80);
        }
    }

    /**
     * Filtra en vivo las opciones del selector de medicamentos dentro del modal de Nuevo Lote
     * por nombre comercial, principio activo, laboratorio o código EAN-13.
     */
    function filterRemesaMedicationSelect(rawQuery) {
        const select = document.getElementById('remesa-select-med');
        const countEl = document.getElementById('remesa-med-count');
        if (!select) return;

        const q = normalizeInvStr(rawQuery);
        let firstMatchValue = null;
        let exactEanValue = null;
        let visibleOptions = 0;

        Array.from(select.options).forEach(opt => {
            const ean = normalizeInvStr(opt.getAttribute('data-ean'));
            const nombre = normalizeInvStr(opt.getAttribute('data-nombre'));
            const principio = normalizeInvStr(opt.getAttribute('data-principio'));
            const lab = normalizeInvStr(opt.getAttribute('data-lab'));
            const fullText = normalizeInvStr(opt.textContent);

            const matches = !q || ean.includes(q) || nombre.includes(q) || principio.includes(q) || lab.includes(q) || fullText.includes(q);
            opt.hidden = !matches;

            if (matches) {
                visibleOptions++;
                if (firstMatchValue === null) firstMatchValue = opt.value;
                if (q && ean === q) exactEanValue = opt.value;
            }
        });

        if (countEl) {
            countEl.textContent = String(visibleOptions);
        }

        if (exactEanValue !== null) {
            select.value = exactEanValue;
            playInvScannerBeep(false);
        } else if (q && firstMatchValue !== null) {
            const currentOpt = select.selectedOptions[0];
            if (!currentOpt || currentOpt.hidden) {
                select.value = firstMatchValue;
            }
        }

        updateRemesaSelectedMedCard();
    }

    function updateRemesaSelectedMedCard() {
        const select = document.getElementById('remesa-select-med');
        const titleEl = document.getElementById('remesa-card-title');
        const subEl = document.getElementById('remesa-card-sub');
        const badgeEl = document.getElementById('remesa-card-stock-badge');
        const traspasoBtn = document.getElementById('remesa-card-traspaso-btn');

        if (!select || !select.selectedOptions[0]) return;
        const opt = select.selectedOptions[0];

        const nombre = opt.getAttribute('data-nombre') || '';
        const principio = opt.getAttribute('data-principio') || '';
        const lab = opt.getAttribute('data-lab') || '';
        const ean = opt.getAttribute('data-ean') || '';
        const vit = parseInt(opt.getAttribute('data-vitrina') || '0', 10);
        const bod = parseInt(opt.getAttribute('data-bodega') || '0', 10);
        const umbral = parseInt(opt.getAttribute('data-umbral') || '25', 10);
        const loteBodId = opt.getAttribute('data-lote-bodega-id') || '';
        const loteBodCant = parseInt(opt.getAttribute('data-lote-bodega-cant') || '0', 10);
        const controlado = opt.getAttribute('data-controlado') === '1';

        if (titleEl) {
            titleEl.textContent = `${nombre} (${lab}) ${controlado ? '• 🔒 Receta Retenida ISP' : '• ✓ Venta Libre'}`;
        }
        if (subEl) {
            subEl.textContent = `${principio} • EAN: ${ean} • Mínimo alerta: ${umbral} u.`;
        }
        if (badgeEl) {
            badgeEl.textContent = `Vitrina: ${vit} u. | Bodega: ${bod} u.`;
            badgeEl.className = 'px-2.5 py-1 rounded-lg font-mono-code font-bold text-[11px] border ' +
                (vit <= 0
                    ? 'bg-red-100 text-red-700 border-red-300'
                    : (vit <= umbral ? 'bg-amber-100 text-amber-800 border-amber-300' : 'bg-blue-50 text-[#1E40AF] border-blue-200'));
        }
        if (traspasoBtn) {
            if (loteBodId && loteBodCant > 0 && vit <= umbral) {
                traspasoBtn.classList.remove('hidden');
                traspasoBtn.setAttribute('data-lote-id', loteBodId);
                traspasoBtn.setAttribute('data-lote-cant', String(Math.min(30, loteBodCant)));
            } else {
                traspasoBtn.classList.add('hidden');
            }
        }
    }

    function switchFromRemesaToTraspasoBodega() {
        const traspasoBtn = document.getElementById('remesa-card-traspaso-btn');
        const remesaModal = document.getElementById('modal-nueva-remesa');
        if (!traspasoBtn) return;

        const idLote = traspasoBtn.getAttribute('data-lote-id');
        const cant = parseInt(traspasoBtn.getAttribute('data-lote-cant') || '25', 10);

        if (remesaModal) remesaModal.classList.add('hidden');
        openTraspasoVitrinaModal(idLote, cant);
    }

    function switchRemesaToNuevoMedicamento() {
        const remesaModal = document.getElementById('modal-nueva-remesa');
        const nuevoMedModal = document.getElementById('modal-nuevo-medicamento');
        const scanInput = document.getElementById('remesa-scan-ean');
        const nuevoNombreInput = document.getElementById('nuevo-med-nombre');
        const nuevoEanInput = document.getElementById('nuevo-med-ean');

        const typed = scanInput ? scanInput.value.trim() : '';
        if (remesaModal) remesaModal.classList.add('hidden');
        if (nuevoMedModal) {
            nuevoMedModal.classList.remove('hidden');
            if (typed) {
                if (/^[0-9]{6,20}$/.test(typed) && nuevoEanInput) {
                    nuevoEanInput.value = typed;
                } else if (nuevoNombreInput) {
                    nuevoNombreInput.value = typed;
                }
            }
        }
    }

    function handleFefoAlertBadgeClick(e) {
        const ubicacionActual = "{{ $ubicacion }}";
        const totalAlertasFefo = {{ $alertasFefo->count() }};
        const fefoSelect = document.getElementById('inv-filter-fefo');
        const searchInput = document.getElementById('inv-live-search');
        const condSelect = document.getElementById('inv-filter-condicion');
        const stockSelect = document.getElementById('inv-filter-stock');

        if (ubicacionActual === 'CATALOGO' || ubicacionActual === 'BAJAS' || !fefoSelect) {
            return true;
        }

        const expiringRowsInCurrentView = Array.from(document.querySelectorAll('tr.inv-filterable-row')).filter(tr => {
            const rf = tr.getAttribute('data-fefo');
            return rf === 'critico' || rf === 'alerta';
        });

        if (expiringRowsInCurrentView.length < totalAlertasFefo && ubicacionActual !== 'TODOS') {
            return true;
        }

        if (e) e.preventDefault();

        const alreadyFiltering = fefoSelect.value === 'por_vencer';
        if (searchInput) searchInput.value = '';
        if (condSelect) condSelect.value = '';
        if (stockSelect) stockSelect.value = '';
        fefoSelect.value = alreadyFiltering ? '' : 'por_vencer';

        filterInventoryTable('');

        const tableEl = document.getElementById('tabla-lotes-fefo');
        if (tableEl) {
            tableEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        return false;
    }

    function renderInventoryAutocomplete(rawQuery) {
        const dropdown = document.getElementById('inv-autocomplete-dropdown');
        const resultsBox = document.getElementById('inv-autocomplete-results');
        const headerEl = document.getElementById('inv-autocomplete-header');
        if (!dropdown || !resultsBox) return;

        const q = normalizeInvStr(rawQuery);
        if (!q) {
            dropdown.classList.add('hidden');
            resultsBox.innerHTML = '';
            return;
        }

        const rows = Array.from(document.querySelectorAll('tr.inv-filterable-row'));
        const startsWithMatches = [];
        const containsMatches = [];

        rows.forEach(tr => {
            const rawName = tr.getAttribute('data-name') || '';
            const rawSub = tr.getAttribute('data-sub') || '';
            const rawCode = tr.getAttribute('data-code') || '';
            const rawEan = tr.getAttribute('data-ean') || '';
            const rawLab = tr.getAttribute('data-lab') || '';
            const rawBadge = tr.getAttribute('data-badge') || '';
            const rawStock = tr.getAttribute('data-stock') || '';
            const rowCond = tr.getAttribute('data-condicion') || '';

            const nName = normalizeInvStr(rawName);
            const nSub = normalizeInvStr(rawSub);
            const nCode = normalizeInvStr(rawCode);
            const nEan = normalizeInvStr(rawEan);
            const nLab = normalizeInvStr(rawLab);

            const item = { rawName, rawSub, rawCode, rawEan, rawLab, rawBadge, rawStock, rowCond };

            if (nName.startsWith(q) || nCode.startsWith(q) || nEan.startsWith(q) || nSub.startsWith(q)) {
                startsWithMatches.push(item);
            } else if (nName.includes(q) || nSub.includes(q) || nCode.includes(q) || nEan.includes(q) || nLab.includes(q)) {
                containsMatches.push(item);
            }
        });

        const combined = startsWithMatches.concat(containsMatches).slice(0, 8);
        if (combined.length === 0) {
            dropdown.classList.add('hidden');
            resultsBox.innerHTML = '';
            return;
        }

        if (headerEl) {
            headerEl.textContent = startsWithMatches.length > 0
                ? `Sugerencias que comienzan con "${rawQuery.trim()}" (${combined.length})`
                : `Coincidencias encontradas (${combined.length})`;
        }

        resultsBox.innerHTML = combined.map(item => {
            const badgeClass = item.rowCond === 'retenida'
                ? 'bg-red-50 text-red-700 border-red-200'
                : 'bg-emerald-50 text-emerald-700 border-emerald-200';
            const safeFilterVal = escapeInvHtml(item.rawCode || item.rawName);

            return `
                <button type="button"
                        onclick="selectInventorySuggestion('${safeFilterVal.replace(/'/g, "\\'")}')"
                        class="w-full px-3.5 py-2.5 text-left hover:bg-blue-50/70 transition flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-bold text-slate-900 text-xs truncate">${escapeInvHtml(item.rawName)}</div>
                        <div class="text-[11px] text-slate-500 truncate">
                            ${escapeInvHtml(item.rawSub)} • <span class="text-slate-600 font-medium">${escapeInvHtml(item.rawLab)}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="px-2 py-0.5 rounded bg-slate-100 border border-slate-200 font-mono-code text-[10px] text-slate-700">
                            ${escapeInvHtml(item.rawCode)}
                        </span>
                        <span class="px-2 py-0.5 rounded bg-blue-50 text-[#1E40AF] font-mono-code font-bold text-[10px]">
                            ${escapeInvHtml(item.rawStock)}
                        </span>
                        <span class="px-2 py-0.5 rounded-full border text-[10px] font-bold ${badgeClass}">
                            ${escapeInvHtml(item.rawBadge)}
                        </span>
                    </div>
                </button>
            `;
        }).join('');

        dropdown.classList.remove('hidden');
    }

    function selectInventorySuggestion(val) {
        const input = document.getElementById('inv-live-search');
        const dropdown = document.getElementById('inv-autocomplete-dropdown');
        if (input) {
            input.value = val;
        }
        if (dropdown) {
            dropdown.classList.add('hidden');
        }
        filterInventoryTable(val);
    }

    function clearInventoryFilters() {
        const input = document.getElementById('inv-live-search');
        const condSelect = document.getElementById('inv-filter-condicion');
        const fefoSelect = document.getElementById('inv-filter-fefo');
        const stockSelect = document.getElementById('inv-filter-stock');
        const dropdown = document.getElementById('inv-autocomplete-dropdown');

        if (input) input.value = '';
        if (condSelect) condSelect.value = '';
        if (fefoSelect) fefoSelect.value = '';
        if (stockSelect) stockSelect.value = '';
        if (dropdown) dropdown.classList.add('hidden');

        filterInventoryTable('');
    }

    function playInvScannerBeep(isAlert) {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = isAlert ? 'sawtooth' : 'sine';
            osc.frequency.setValueAtTime(isAlert ? 520 : 1350, ctx.currentTime);
            gain.gain.setValueAtTime(0.16, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.09);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.09);
        } catch (err) {}
    }

    function simulateInventoryScan(eanCode) {
        const remesaModal = document.getElementById('modal-nueva-remesa');
        if (remesaModal && !remesaModal.classList.contains('hidden')) {
            const scanInput = document.getElementById('remesa-scan-ean');
            if (scanInput) scanInput.value = eanCode;
            filterRemesaMedicationSelect(eanCode);
            return;
        }

        const input = document.getElementById('inv-live-search');
        const dropdown = document.getElementById('inv-autocomplete-dropdown');
        if (!input) return;

        input.value = eanCode;
        if (dropdown) dropdown.classList.add('hidden');
        input.classList.add('ring-2', 'ring-emerald-400', 'bg-emerald-50/40');
        setTimeout(() => {
            input.classList.remove('ring-2', 'ring-emerald-400', 'bg-emerald-50/40');
        }, 450);

        playInvScannerBeep(eanCode === '780000100105');
        filterInventoryTable(eanCode);
    }

    document.addEventListener('click', function(e) {
        const wrapper = document.getElementById('inv-search-wrapper');
        const dropdown = document.getElementById('inv-autocomplete-dropdown');
        if (wrapper && dropdown && !wrapper.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        updateRemesaSelectedMedCard();

        const input = document.getElementById('inv-live-search');
        const fefoSelect = document.getElementById('inv-filter-fefo');
        const stockSelect = document.getElementById('inv-filter-stock');
        const sortSelect = document.getElementById('inv-sort-order');

        if ((sortSelect && sortSelect.value !== '' && sortSelect.value !== 'fefo_asc' && sortSelect.value !== 'nombre_asc') ||
            (input && input.value.trim() !== '') ||
            (fefoSelect && fefoSelect.value !== '') ||
            (stockSelect && stockSelect.value !== '')) {
            sortAndFilterInventoryTable();
        }

        // Si viene desde un enlace directo de reposición (?reponer_med=ID), abrir el flujo respetando permisos
        if (INITIAL_REPONER_MED_ID > 0) {
            if (CAN_MANAGE_STOCK) {
                openNuevaRemesaForMedication(INITIAL_REPONER_MED_ID, 'VITRINA');
            } else {
                const notice = document.getElementById('low-stock-permission-notice');
                const textEl = document.getElementById('low-stock-permission-text');
                if (notice && textEl) {
                    textEl.innerHTML = `Visualizando producto con alerta de stock bajo. Tu perfil actual (<strong>${escapeInvHtml(CURRENT_ROLE_LABEL)}</strong>) es de consulta; solicita a <strong>Bodega</strong> o <strong>Administración</strong> el ingreso de un nuevo lote.`;
                    notice.classList.remove('hidden');
                }
            }
        }

        // Escucha Global de Pistola Lectora USB en Inventario (Keyboard Wedge < 45ms + Enter) y atajo F2
        let invWedgeBuffer = '';
        let invLastKeyTime = 0;

        document.addEventListener('keydown', function(e) {
            if (e.key === 'F2') {
                e.preventDefault();
                if (input) {
                    input.focus();
                    input.select();
                }
                return;
            }

            const activeEl = document.activeElement;
            const isTypingInInput = activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.tagName === 'SELECT');
            if (isTypingInInput) return;

            const now = Date.now();
            if (now - invLastKeyTime > 80) {
                invWedgeBuffer = '';
            }
            invLastKeyTime = now;

            if (e.key === 'Enter' && invWedgeBuffer.length >= 6) {
                e.preventDefault();
                const code = invWedgeBuffer;
                invWedgeBuffer = '';
                simulateInventoryScan(code);
            } else if (e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
                invWedgeBuffer += e.key;
            }
        });
    });
</script>
@endsection
