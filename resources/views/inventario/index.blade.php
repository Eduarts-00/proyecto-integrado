@extends('layouts.app')

@section('title', 'Gestión de Inventario — FarmaNet Pro')

@section('content')
@php
    $canManageStock = auth()->user()->hasPermission('Can_Manage_Stock');
    $canManageMedications = auth()->user()->hasPermission('Can_Manage_Medications');
    $canToggleLote = $canManageStock || $canManageMedications || auth()->user()->role === 'administrador';
@endphp

<div class="space-y-5">

    <!-- Cabecera -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Gestión de Inventario y Catálogo</h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Control de lotes por vencimiento (FEFO), traspasos entre Vitrina y Bodega, y clasificación de recetas restringidas.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <span class="px-3.5 py-2 rounded-lg bg-amber-50 border border-amber-300 text-amber-800 text-xs font-bold flex items-center gap-1.5">
                ⚠️ {{ $alertasFefo->count() }} lotes próx. a vencer
            </span>

            @if($ubicacion === 'CATALOGO')
                @if($canManageMedications)
                    <button type="button" onclick="document.getElementById('modal-nuevo-medicamento').classList.remove('hidden')"
                            class="px-4 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold shadow-sm transition">
                        💊 + Nuevo Producto al Catálogo
                    </button>
                @endif
            @else
                @if($canToggleLote)
                    <button type="button" onclick="document.getElementById('modal-retiro-vitrina').classList.remove('hidden')"
                            class="px-3.5 py-2 rounded-lg border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-900 text-xs font-bold shadow-sm transition">
                        ↓ Retirar Vitrina &rarr; Bodega
                    </button>
                @endif

                @if($canManageStock)
                    <button type="button" onclick="document.getElementById('modal-traspaso-fefo').classList.remove('hidden')"
                            class="px-3.5 py-2 rounded-lg bg-[#1E40AF] hover:bg-blue-800 text-white text-xs font-bold shadow-sm transition">
                        ↑ Traspaso Bodega &rarr; Vitrina
                    </button>
                    <button type="button" onclick="document.getElementById('modal-nueva-remesa').classList.remove('hidden')"
                            class="px-3.5 py-2 rounded-lg bg-[#10B981] hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition">
                        📦 + Ingresar Lote (Stock)
                    </button>
                @endif
            @endif
        </div>
    </div>

    <!-- Pestañas de Segregación Física y Catálogo -->
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('inventario.index', ['ubicacion' => 'VITRINA']) }}"
               class="px-4 py-2 rounded-lg text-xs font-bold border transition flex items-center gap-2 {{ $ubicacion === 'VITRINA' ? 'bg-white border-slate-300 text-slate-900 shadow-sm' : 'bg-slate-100 border-transparent text-slate-500 hover:text-slate-800' }}">
                🏪 Vitrina
            </a>
            <a href="{{ route('inventario.index', ['ubicacion' => 'BODEGA']) }}"
               class="px-4 py-2 rounded-lg text-xs font-bold border transition flex items-center gap-2 {{ $ubicacion === 'BODEGA' ? 'bg-white border-slate-300 text-slate-900 shadow-sm' : 'bg-slate-100 border-transparent text-slate-500 hover:text-slate-800' }}">
                📦 Bodega
            </a>
            <a href="{{ route('inventario.index', ['ubicacion' => 'TODOS']) }}"
               class="px-4 py-2 rounded-lg text-xs font-bold border transition flex items-center gap-2 {{ $ubicacion === 'TODOS' ? 'bg-white border-slate-300 text-slate-900 shadow-sm' : 'bg-slate-100 border-transparent text-slate-500 hover:text-slate-800' }}">
                📋 Todos los Lotes
            </a>
            <a href="{{ route('inventario.index', ['ubicacion' => 'BAJAS']) }}"
               class="px-4 py-2 rounded-lg text-xs font-bold border transition flex items-center gap-2 {{ $ubicacion === 'BAJAS' ? 'bg-red-50 border-red-300 text-red-800 shadow-sm' : 'bg-slate-100 border-transparent text-slate-500 hover:text-slate-800' }}">
                🗑️ Bajas y Caducados ({{ $bajasCount ?? 0 }})
            </a>
            <a href="{{ route('inventario.index', ['ubicacion' => 'CATALOGO']) }}"
               class="px-4 py-2 rounded-lg text-xs font-bold border transition flex items-center gap-2 {{ $ubicacion === 'CATALOGO' ? 'bg-white border-blue-300 text-[#1E40AF] shadow-sm' : 'bg-slate-100 border-transparent text-slate-500 hover:text-slate-800' }}">
                💊 Catálogo y Recetas ({{ $medicamentosCatalogo->count() }})
            </a>
        </div>
    </div>

    <!-- Barra de Búsqueda Predictiva y Filtros de Inventario -->
    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm space-y-3">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-end">
            <!-- Buscador con Autocompletado en Vivo -->
            <div class="lg:col-span-6 relative" id="inv-search-wrapper">
                <label for="inv-live-search" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                    Buscador de Inventario y Lotes (Sugerencias en vivo)
                </label>
                <form action="{{ route('inventario.index') }}" method="GET" onsubmit="event.preventDefault(); filterInventoryTable();" autocomplete="off">
                    <input type="hidden" name="ubicacion" value="{{ $ubicacion }}">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 text-xs pointer-events-none">🔍</span>
                        <input type="text"
                               id="inv-live-search"
                               name="q"
                               value="{{ $search ?? '' }}"
                               autocomplete="off"
                               oninput="handleInventorySearchInput(this.value)"
                               onfocus="handleInventorySearchInput(this.value)"
                               placeholder="Escribe medicamento, principio activo, laboratorio o código de lote (ej: Paracetamol, Losartán, LOT-2026)..."
                               class="w-full pl-9 pr-8 py-2.5 rounded-lg border border-slate-300 bg-white text-xs font-medium text-slate-800 focus:border-[#1E40AF] focus:ring-2 focus:ring-blue-100 outline-none">
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

            <!-- Filtro por Condición de Venta -->
            <div class="lg:col-span-3">
                <label for="inv-filter-condicion" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                    Condición de Venta
                </label>
                <select id="inv-filter-condicion"
                        onchange="filterInventoryTable()"
                        class="w-full px-3 py-2.5 rounded-lg border border-slate-300 bg-white text-xs font-semibold text-slate-700 focus:border-[#1E40AF] outline-none">
                    <option value="">Todas las condiciones</option>
                    <option value="libre">✓ Solo Venta Libre</option>
                    <option value="retenida">🔒 Solo Receta Retenida ISP</option>
                </select>
            </div>

            <!-- Filtro por Semáforo FEFO o Estado de Catálogo -->
            <div class="lg:col-span-3">
                @if($ubicacion === 'CATALOGO')
                    <label for="inv-filter-fefo" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                        Estado en Catálogo
                    </label>
                    <select id="inv-filter-fefo"
                            onchange="filterInventoryTable()"
                            class="w-full px-3 py-2.5 rounded-lg border border-slate-300 bg-white text-xs font-semibold text-slate-700 focus:border-[#1E40AF] outline-none">
                        <option value="">Todos los estados</option>
                        <option value="activo">✓ Solo Activos</option>
                        <option value="inactivo">✕ Solo Deshabilitados</option>
                    </select>
                @else
                    <label for="inv-filter-fefo" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                        Alerta de Vencimiento (FEFO)
                    </label>
                    <select id="inv-filter-fefo"
                            onchange="filterInventoryTable()"
                            class="w-full px-3 py-2.5 rounded-lg border border-slate-300 bg-white text-xs font-semibold text-slate-700 focus:border-[#1E40AF] outline-none">
                        <option value="">Todos los vencimientos FEFO</option>
                        <option value="critico">● Crítico / Por vencer (≤ 30 días)</option>
                        <option value="alerta">▲ Alerta preventiva (31 a 90 días)</option>
                        <option value="optimo">✓ Vigencia óptima (&gt; 90 días)</option>
                    </select>
                @endif
            </div>
        </div>

        <!-- Barra inferior de estado de búsqueda y contador -->
        <div class="pt-2 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-xs">
            <div class="flex items-center gap-2 text-slate-500">
                <span class="inline-flex items-center gap-1 font-medium">
                    Vista activa: <strong class="text-slate-800">{{ $ubicacion }}</strong>
                </span>
                <button type="button"
                        id="btn-reset-inv-filters"
                        onclick="clearInventoryFilters()"
                        class="hidden px-2.5 py-1 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[11px] transition">
                    ↺ Mostrar todos
                </button>
            </div>
            <span id="inv-visible-counter" class="text-[11px] font-semibold text-slate-500">
                Mostrando {{ $ubicacion === 'CATALOGO' ? $medicamentosCatalogo->count() : $lotes->count() }} registros
            </span>
        </div>
    </div>

    @if($ubicacion === 'CATALOGO')
        <!-- Vista de Catálogo de Medicamentos y Restricción de Receta -->
        <div class="p-3 rounded-xl bg-blue-50/90 border border-blue-200 text-blue-800 text-xs font-medium flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <span>💊 Catálogo maestro de medicamentos y condición de venta.</span>
            @if(! $canManageMedications)
                <span class="px-2.5 py-1 rounded-lg bg-white/80 border border-blue-200 text-slate-600 font-semibold text-[11px]">
                    🔒 Modo Consulta — Clasificación exclusiva Q. Farmacéutico y Administrador
                </span>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 text-[11px] font-bold uppercase text-slate-400 bg-slate-50/60 whitespace-nowrap">
                            <th class="py-3.5 px-4">Código</th>
                            <th class="py-3.5 px-4">Medicamento / Principio Activo</th>
                            <th class="py-3.5 px-4">Laboratorio</th>
                            <th class="py-3.5 px-4 text-right">Precio Venta</th>
                            <th class="py-3.5 px-4 text-center">Stock (Vitrina / Bodega)</th>
                            <th class="py-3.5 px-4 text-center">Condición Venta</th>
                            <th class="py-3.5 px-4 text-center">Estado</th>
                            @if($canManageMedications)
                                <th class="py-3.5 px-4 text-right">Acciones</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody id="inv-table-body" class="divide-y divide-slate-100 text-xs">
                        @foreach($medicamentosCatalogo as $med)
                            @php
                                $esRestringido = $med->requiere_receta || $med->tipo_controlado;
                            @endphp
                            <tr class="inv-filterable-row hover:bg-slate-50/80 {{ ! $med->activo ? 'bg-slate-50/60 opacity-70' : '' }}"
                                data-name="{{ $med->nombre_comercial }}"
                                data-sub="{{ $med->principio_activo }}"
                                data-code="{{ $med->codigo_barras }}"
                                data-lab="{{ $med->laboratorio }}"
                                data-condicion="{{ $esRestringido ? 'retenida' : 'libre' }}"
                                data-fefo="{{ $med->activo ? 'activo' : 'inactivo' }}"
                                data-badge="{{ $esRestringido ? '🔒 Retenida ISP' : '✓ Venta Libre' }}"
                                data-stock="Vit: {{ $med->stock_vitrina }} / Bod: {{ $med->stock_bodega }}">
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
                                    <span class="px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-bold">{{ $med->stock_vitrina }} vit.</span>
                                    <span class="text-slate-300 mx-1">/</span>
                                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-semibold">{{ $med->stock_bodega }} bod.</span>
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
                                @if($canManageMedications)
                                    <td class="py-3.5 px-5 text-right">
                                        <div class="inline-flex items-center justify-end gap-1.5">
                                            <button type="button"
                                                    onclick="openEditMedicationModal({{ json_encode([
                                                        'id_medicamento' => $med->id_medicamento,
                                                        'nombre_comercial' => $med->nombre_comercial,
                                                        'principio_activo' => $med->principio_activo,
                                                        'laboratorio' => $med->laboratorio,
                                                        'categoria' => $med->categoria,
                                                        'precio_unitario' => $med->precio_unitario,
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
                                                        Pasar a Venta Libre
                                                    </button>
                                                @else
                                                    <button type="submit"
                                                            class="px-2.5 py-1.5 rounded-lg border border-amber-300 bg-amber-50/80 hover:bg-amber-100 text-amber-800 font-bold text-[11px] transition"
                                                            title="Marcar con restricción de Receta Retenida ISP">
                                                        Exigir Receta ISP
                                                    </button>
                                                @endif
                                            </form>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                        <tr id="inv-no-results-row" class="hidden">
                            <td colspan="{{ $canManageMedications ? 8 : 7 }}" class="py-10 text-center text-slate-500 text-xs">
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
        <!-- Banner Informativo FEFO / Bajas Sanitarias -->
        @if($ubicacion === 'BAJAS')
            <div class="p-3 rounded-xl bg-red-50/90 border border-red-200 text-red-900 text-xs font-medium flex items-center gap-2">
                <span>🗑️</span>
                <span><strong>Registro de Bajas y Productos Caducados:</strong> Lotes retirados de Vitrina y Bodega por vencimiento, merma o alerta sanitaria.</span>
            </div>
        @else
            <div class="p-3 rounded-xl bg-blue-50/90 border border-blue-200 text-blue-800 text-xs font-medium flex items-center gap-2">
                <span>⬇️</span>
                <span>Lotes activos ordenados por fecha de vencimiento (FEFO): los primeros en expirar se muestran primero.</span>
            </div>
        @endif

        <!-- Tabla Interactiva Clasificada por FEFO -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 text-[11px] font-bold uppercase text-slate-400 bg-slate-50/60 whitespace-nowrap">
                            <th class="py-3.5 px-4">Medicamento</th>
                            <th class="py-3.5 px-4">Condición Venta</th>
                            <th class="py-3.5 px-4">Código Lote</th>
                            <th class="py-3.5 px-4">Laboratorio</th>
                            <th class="py-3.5 px-4">{{ $ubicacion === 'BAJAS' ? 'Origen / Motivo de Baja' : 'Ubicación' }}</th>
                            <th class="py-3.5 px-4 text-center">Cantidad</th>
                            <th class="py-3.5 px-4 text-center">Vencimiento</th>
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
                                $esControlado = $lote->medicamento?->requiere_receta || $lote->medicamento?->tipo_controlado;
                                $fefoKey = ($sem === 'critico' || $sem === 'vencido') ? 'critico' : ($sem === 'alerta' ? 'alerta' : 'optimo');
                                $rowBg = ! $lote->activo
                                    ? 'bg-red-50/30 opacity-85'
                                    : match ($sem) {
                                        'vencido', 'critico' => 'bg-red-50/40 hover:bg-red-50/70',
                                        'alerta' => 'bg-amber-50/40 hover:bg-amber-50/70',
                                        default => 'hover:bg-slate-50/80',
                                    };
                            @endphp
                            <tr class="inv-filterable-row {{ $rowBg }} transition"
                                data-name="{{ $lote->medicamento?->nombre_comercial }}"
                                data-sub="{{ $lote->medicamento?->principio_activo }}"
                                data-code="{{ $lote->lote_codigo }}"
                                data-lab="{{ $lote->medicamento?->laboratorio }}"
                                data-condicion="{{ $esControlado ? 'retenida' : 'libre' }}"
                                data-fefo="{{ $fefoKey }}"
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
                                            <div class="font-bold">{{ $lote->medicamento?->nombre_comercial }}</div>
                                            <div class="text-[11px] text-slate-400 font-normal">{{ $lote->medicamento?->principio_activo }}</div>
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
                                    {{ $lote->medicamento?->laboratorio }}
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
                                <td class="py-3.5 px-4 text-center font-bold text-slate-900 whitespace-nowrap">
                                    {{ $lote->cantidad }}
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
                                            @if($lote->activo && $lote->ubicacion === 'VITRINA' && $lote->cantidad > 0)
                                                <button type="button"
                                                        onclick="openRetiroVitrinaModal({{ $lote->id_lote }}, {{ $lote->cantidad }})"
                                                        class="px-2.5 py-1.5 rounded-lg border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-900 font-bold text-[11px] transition"
                                                        title="Devolver unidades o retirar este medicamento de Vitrina hacia Bodega">
                                                    &darr; Pasar a Bodega
                                                </button>
                                            @elseif($lote->activo && $lote->ubicacion === 'BODEGA' && $canManageStock && $lote->dias_restantes > 0)
                                                <button type="button"
                                                        onclick="openTraspasoVitrinaModal({{ $lote->id_lote }}, {{ min(25, $lote->cantidad) }})"
                                                        class="px-2.5 py-1.5 rounded-lg border border-blue-200 bg-blue-50 hover:bg-blue-100 text-[#1E40AF] font-bold text-[11px] transition"
                                                        title="Abastecer Vitrina desde este lote de Bodega">
                                                    &uarr; Pasar a Vitrina
                                                </button>
                                            @endif

                                            @if($lote->activo)
                                                <button type="button"
                                                        onclick="openBajaCaducidadModal({{ json_encode([
                                                            'id_lote' => $lote->id_lote,
                                                            'lote_codigo' => $lote->lote_codigo,
                                                            'medicamento' => $lote->medicamento?->nombre_comercial,
                                                            'ubicacion' => $lote->ubicacion,
                                                            'cantidad' => $lote->cantidad,
                                                            'dias' => $dias,
                                                            'action_url' => route('inventario.toggle_batch', $lote->id_lote),
                                                        ]) }})"
                                                        class="px-2.5 py-1.5 rounded-lg border border-red-200 bg-red-50/80 hover:bg-red-100 text-red-700 font-bold text-[11px] transition"
                                                        title="Retirar de {{ $lote->ubicacion }} por caducidad o merma sanitaria">
                                                    {{ $dias <= 30 ? '🗑️ Quitar por Caducado' : '🗑️ Dar de Baja' }}
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

@if($canManageMedications)
    <!-- Modal Nuevo Medicamento (Adquisición al Catálogo + Condición de Receta) -->
    <div id="modal-nuevo-medicamento" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full border border-slate-200 shadow-xl overflow-hidden">
            <div class="bg-slate-900 text-white px-5 py-4 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-sm">+ Registrar Nuevo Medicamento</h3>
                    <p class="text-[11px] text-slate-300">Incorpora un nuevo producto al catálogo de la farmacia</p>
                </div>
                <button type="button" onclick="document.getElementById('modal-nuevo-medicamento').classList.add('hidden')" class="text-white">✕</button>
            </div>
            <form action="{{ route('inventario.medications.store') }}" method="POST" class="p-5 space-y-3.5 text-xs">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nombre Comercial</label>
                        <input type="text" name="nombre_comercial" required placeholder="Ej: Zolpidem 10mg"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Principio Activo</label>
                        <input type="text" name="principio_activo" required placeholder="Ej: Zolpidem Tartrato"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Código de Barras</label>
                        <input type="text" name="codigo_barras" value="78000010{{ rand(1120, 9999) }}" required
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Laboratorio</label>
                        <input type="text" name="laboratorio" required placeholder="Ej: Laboratorio Saval"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Precio Unitario ($)</label>
                        <input type="number" name="precio_unitario" min="100" step="10" value="6990" required
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                    </div>
                </div>

                <div class="p-3.5 rounded-xl bg-amber-50/70 border border-amber-200 space-y-1.5">
                    <label class="block font-bold text-amber-950">Condición de Venta</label>
                    <select name="condicion_receta" class="w-full px-3 py-2 rounded-lg border border-amber-300 bg-white font-semibold text-slate-800">
                        <option value="venta_libre">✓ Venta Libre</option>
                        <option value="receta_retenida">🔒 Receta Retenida</option>
                    </select>
                </div>

                <div class="border-t border-slate-200 pt-3">
                    <div class="font-bold text-slate-700 mb-2">Stock Inicial (Opcional)</div>
                    <div class="grid grid-cols-4 gap-2.5">
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1">Cantidad</label>
                            <input type="number" name="cantidad_inicial" min="0" value="50"
                                   class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 font-mono-code">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1">Código Lote</label>
                            <input type="text" name="lote_codigo" value="LOT-2026-{{ rand(300, 999) }}"
                                   class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 font-mono-code">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1">Destino</label>
                            <select name="ubicacion_inicial" class="w-full px-2 py-1.5 rounded-lg border border-slate-300">
                                <option value="BODEGA">BODEGA</option>
                                <option value="VITRINA">VITRINA</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1">Vencimiento</label>
                            <input type="date" name="fecha_vencimiento" value="{{ now()->addMonths(8)->toDateString() }}"
                                   class="w-full px-2 py-1.5 rounded-lg border border-slate-300 font-mono-code">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modal-nuevo-medicamento').classList.add('hidden')"
                            class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-bold">
                        Guardar Medicamento
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Medicamento y Condición de Receta -->
    <div id="modal-editar-medicamento" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full border border-slate-200 shadow-xl overflow-hidden">
            <div class="bg-[#1E40AF] text-white px-5 py-4 flex items-center justify-between">
                <h3 class="font-bold text-sm">Editar Medicamento y Restricción de Receta</h3>
                <button type="button" onclick="document.getElementById('modal-editar-medicamento').classList.add('hidden')" class="text-white">✕</button>
            </div>
            <form id="form-editar-medicamento" method="POST" class="p-5 space-y-3.5 text-xs">
                @csrf
                @method('PUT')
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Nombre Comercial</label>
                    <input type="text" id="edit-med-nombre" name="nombre_comercial" required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Principio Activo</label>
                        <input type="text" id="edit-med-principio" name="principio_activo" required
                               class="w-full px-3 py-2 rounded-lg border border-slate-300">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Laboratorio</label>
                        <input type="text" id="edit-med-laboratorio" name="laboratorio" required
                               class="w-full px-3 py-2 rounded-lg border border-slate-300">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Precio Unitario ($)</label>
                        <input type="number" id="edit-med-precio" name="precio_unitario" min="1" step="1" required
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Estado en Catálogo</label>
                        <select id="edit-med-activo" name="activo"
                                class="w-full px-3 py-2 rounded-lg border border-slate-300">
                            <option value="1">Activo</option>
                            <option value="0">Deshabilitado</option>
                        </select>
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
            if (sel) sel.value = String(idLote);
            if (input) input.value = cantSugerida;
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
                    <h3 class="font-bold text-sm">Traspaso Bodega &rarr; Vitrina</h3>
                    <p class="text-[11px] text-blue-200">Prioriza automáticamente los lotes con vencimiento más próximo</p>
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
                    <label class="block font-bold text-slate-700 mb-1">Cantidad a Traspasar</label>
                    <input type="number" id="traspaso-bodega-cantidad" name="cantidad" min="1" value="25" required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
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

    <!-- Modal Nueva Remesa / Ingreso de Lote de Stock -->
    <div id="modal-nueva-remesa" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full border border-slate-200 shadow-xl overflow-hidden">
            <div class="bg-[#10B981] text-white px-5 py-4 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-sm">📦 + Ingresar Lote de Stock</h3>
                    <p class="text-[11px] text-emerald-100">Abastecimiento de unidades de un medicamento del catálogo</p>
                </div>
                <button type="button" onclick="document.getElementById('modal-nueva-remesa').classList.add('hidden')" class="text-white">✕</button>
            </div>
            <form action="{{ route('inventario.register') }}" method="POST" class="p-5 space-y-3.5 text-xs">
                @csrf
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block font-bold text-slate-700">Medicamento del Catálogo</label>
                        @if($canManageMedications)
                            <button type="button"
                                    onclick="document.getElementById('modal-nueva-remesa').classList.add('hidden'); document.getElementById('modal-nuevo-medicamento').classList.remove('hidden');"
                                    class="text-[11px] font-bold text-[#1E40AF] hover:underline">
                                ¿Producto nuevo? Crear en Catálogo &rarr;
                            </button>
                        @endif
                    </div>
                    <select name="id_medicamento" required class="w-full px-3 py-2 rounded-lg border border-slate-300">
                        @foreach($medicamentos as $m)
                            <option value="{{ $m->id_medicamento }}">
                                {{ $m->nombre_comercial }} ({{ $m->laboratorio }}) {{ ($m->requiere_receta || $m->tipo_controlado) ? '— [Receta Retenida]' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Código de Lote</label>
                        <input type="text" name="lote_codigo" value="LOT-2026-{{ rand(300, 999) }}" required
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Ubicación Inicial</label>
                        <select name="ubicacion" class="w-full px-3 py-2 rounded-lg border border-slate-300">
                            <option value="BODEGA">BODEGA</option>
                            <option value="VITRINA">VITRINA</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Cantidad Unidades</label>
                        <input type="number" name="cantidad" min="1" value="100" required
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Fecha Vencimiento</label>
                        <input type="date" name="fecha_vencimiento" value="{{ now()->addMonths(6)->toDateString() }}" required
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modal-nueva-remesa').classList.add('hidden')"
                            class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-[#10B981] text-white font-bold">
                        Registrar Lote
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

<script>
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

    function filterInventoryTable(optionalQuery) {
        const searchInput = document.getElementById('inv-live-search');
        const condSelect = document.getElementById('inv-filter-condicion');
        const fefoSelect = document.getElementById('inv-filter-fefo');
        const clearBtn = document.getElementById('btn-clear-inv-search');
        const resetBtn = document.getElementById('btn-reset-inv-filters');
        const counterEl = document.getElementById('inv-visible-counter');
        const noResultsRow = document.getElementById('inv-no-results-row');

        const rawQ = optionalQuery !== undefined ? optionalQuery : (searchInput ? searchInput.value : '');
        const q = normalizeInvStr(rawQ);
        const condVal = condSelect ? condSelect.value : '';
        const fefoVal = fefoSelect ? fefoSelect.value : '';

        if (clearBtn) {
            clearBtn.classList.toggle('hidden', q.length === 0);
        }
        if (resetBtn) {
            resetBtn.classList.toggle('hidden', q.length === 0 && !condVal && !fefoVal);
        }

        const rows = document.querySelectorAll('tr.inv-filterable-row');
        let visibleCount = 0;

        rows.forEach(tr => {
            const name = normalizeInvStr(tr.getAttribute('data-name'));
            const sub = normalizeInvStr(tr.getAttribute('data-sub'));
            const code = normalizeInvStr(tr.getAttribute('data-code'));
            const lab = normalizeInvStr(tr.getAttribute('data-lab'));
            const txt = normalizeInvStr(tr.textContent);
            const rowCond = tr.getAttribute('data-condicion') || '';
            const rowFefo = tr.getAttribute('data-fefo') || '';

            const matchesSearch = !q || name.includes(q) || sub.includes(q) || code.includes(q) || lab.includes(q) || txt.includes(q);
            const matchesCond = !condVal || rowCond === condVal;
            const matchesFefo = !fefoVal || rowFefo === fefoVal;

            if (matchesSearch && matchesCond && matchesFefo) {
                tr.style.display = '';
                visibleCount++;
            } else {
                tr.style.display = 'none';
            }
        });

        if (noResultsRow) {
            noResultsRow.classList.toggle('hidden', rows.length === 0 || visibleCount > 0);
        }
        if (counterEl) {
            counterEl.textContent = `Mostrando ${visibleCount} de ${rows.length} registros`;
        }
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
            const rawLab = tr.getAttribute('data-lab') || '';
            const rawBadge = tr.getAttribute('data-badge') || '';
            const rawStock = tr.getAttribute('data-stock') || '';
            const rowCond = tr.getAttribute('data-condicion') || '';

            const nName = normalizeInvStr(rawName);
            const nSub = normalizeInvStr(rawSub);
            const nCode = normalizeInvStr(rawCode);
            const nLab = normalizeInvStr(rawLab);

            const item = { rawName, rawSub, rawCode, rawLab, rawBadge, rawStock, rowCond };

            if (nName.startsWith(q) || nCode.startsWith(q) || nSub.startsWith(q)) {
                startsWithMatches.push(item);
            } else if (nName.includes(q) || nSub.includes(q) || nCode.includes(q) || nLab.includes(q)) {
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
        const dropdown = document.getElementById('inv-autocomplete-dropdown');

        if (input) input.value = '';
        if (condSelect) condSelect.value = '';
        if (fefoSelect) fefoSelect.value = '';
        if (dropdown) dropdown.classList.add('hidden');

        filterInventoryTable('');
    }

    document.addEventListener('click', function(e) {
        const wrapper = document.getElementById('inv-search-wrapper');
        const dropdown = document.getElementById('inv-autocomplete-dropdown');
        if (wrapper && dropdown && !wrapper.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('inv-live-search');
        if (input && input.value.trim() !== '') {
            filterInventoryTable(input.value);
        }
    });
</script>
@endsection
