@extends('layouts.app')

@section('title', 'Dashboard Gerencial — FarmaNet Pro')

@section('content')
@php
    $periodoActual = $periodo ?? 'hoy';
    $periodoTitulos = [
        'hoy' => 'Turno del Día',
        '7dias' => 'Últimos 7 Días',
        'mes' => 'Mes Actual',
        'todo' => 'Consolidado Histórico',
    ];
    $maxVentaSemanal = collect($tendenciaSemanal)->max('monto') ?: 1;
    $totalSemanal = collect($tendenciaSemanal)->sum('monto');
    $promedioSemanal = count($tendenciaSemanal) > 0 ? round($totalSemanal / count($tendenciaSemanal), 0) : 0;
    $maxUnidadesPareto = $rotacionReal->max('total_unidades') ?: 1;
@endphp

<div class="space-y-6">

    <!-- Cabecera Ejecutiva + Selector de Período -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold text-slate-900">Dashboard Gerencial y Control Sanitario</h1>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-[#1E40AF] border border-blue-200">
                    {{ $periodoTitulos[$periodoActual] ?? 'En vivo' }}
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-0.5">
                Arqueo tributario SII (IVA 19%), valorización de inventario FEFO, liquidación de convenios y auditoría ISP • {{ now()->translatedFormat('l, d \d\e F \d\e Y') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <!-- Selector de Período de Visualización -->
            <div class="inline-flex rounded-lg border border-slate-200 bg-white p-1 shadow-2xs text-xs">
                @foreach(['hoy' => 'Hoy', '7dias' => '7 Días', 'mes' => 'Mes', 'todo' => 'Consolidado'] as $key => $label)
                    <a href="{{ route('dashboard.index', ['periodo' => $key]) }}"
                       class="px-3 py-1.5 rounded-md font-bold transition {{ $periodoActual === $key ? 'bg-slate-900 text-white' : 'text-slate-600 hover:text-slate-900' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <!-- Acceso rápido a exportación integral -->
            <a href="{{ route('dashboard.export.pdf', ['tipo' => 'integral', 'periodo' => $periodoActual]) }}" target="_blank"
               class="px-3.5 py-2 rounded-lg border border-red-200 bg-red-50/80 hover:bg-red-100 text-red-700 text-xs font-bold transition">
                Exportar PDF
            </a>
            <a href="{{ route('dashboard.export.excel', ['tipo' => 'integral', 'periodo' => $periodoActual]) }}"
               class="px-3.5 py-2 rounded-lg border border-emerald-200 bg-emerald-50/80 hover:bg-emerald-100 text-emerald-700 text-xs font-bold transition">
                Exportar Excel (.xlsx)
            </a>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- CENTRO DE EXPORTACIÓN Y REPORTES OFICIALES (SII / ISP / INVENTARIO)       -->
    <!-- ========================================================================= -->
    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-2 pb-3 mb-3 border-b border-slate-100">
            <div>
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                    Centro de Exportación Oficial y Reportes Reglamentarios (SII / ISP / Gestión)
                </h2>
                <p class="text-[11px] text-slate-500">
                    Selecciona el tipo de libro oficial y rango de fechas para descargar en Microsoft Excel (.xlsx) o generar acta oficial en PDF para firma.
                </p>
            </div>
            <span class="text-[11px] font-mono-code text-slate-400">Res. Sanitaria ISP #4421 • IVA 19% SII</span>
        </div>

        <form id="export-center-form" method="GET" action="{{ route('dashboard.export.excel') }}" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end text-xs">
            <!-- Tipo de Reporte -->
            <div class="md:col-span-4">
                <label for="exp-tipo" class="block text-[11px] font-bold text-slate-600 mb-1">Libro o Reporte a Emitir</label>
                <select id="exp-tipo" name="tipo" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white font-semibold text-slate-800 focus:border-[#1E40AF] outline-none">
                    <option value="integral">Reporte Integral Consolidado (4 Hojas: Ventas, Stock, ISP, Convenios)</option>
                    <option value="ventas">1. Libro de Ventas y Arqueo Tributario SII (IVA 19%)</option>
                    <option value="inventario">2. Catálogo, Valorización de Stock y Vencimientos FEFO</option>
                    <option value="isp">3. Libro Oficial de Control ISP (Recetas Retenidas D.S. 404/405)</option>
                    <option value="convenios">4. Liquidación de Convenios de Salud y Copagos</option>
                </select>
            </div>

            <!-- Período Predefinido -->
            <div class="md:col-span-2">
                <label for="exp-periodo" class="block text-[11px] font-bold text-slate-600 mb-1">Período</label>
                <select id="exp-periodo" name="periodo" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white font-semibold text-slate-700 focus:border-[#1E40AF] outline-none">
                    <option value="todo" {{ $periodoActual === 'todo' ? 'selected' : '' }}>Histórico Completo</option>
                    <option value="hoy" {{ $periodoActual === 'hoy' ? 'selected' : '' }}>Turno de Hoy</option>
                    <option value="7dias" {{ $periodoActual === '7dias' ? 'selected' : '' }}>Últimos 7 Días</option>
                    <option value="mes" {{ $periodoActual === 'mes' ? 'selected' : '' }}>Mes Actual</option>
                </select>
            </div>

            <!-- Fecha Desde -->
            <div class="md:col-span-2">
                <label for="exp-desde" class="block text-[11px] font-bold text-slate-600 mb-1">Desde (Opcional)</label>
                <input type="date" id="exp-desde" name="fecha_desde"
                       class="w-full px-2.5 py-2 rounded-lg border border-slate-300 bg-white font-mono-code text-slate-700 focus:border-[#1E40AF] outline-none">
            </div>

            <!-- Fecha Hasta -->
            <div class="md:col-span-2">
                <label for="exp-hasta" class="block text-[11px] font-bold text-slate-600 mb-1">Hasta (Opcional)</label>
                <input type="date" id="exp-hasta" name="fecha_hasta"
                       class="w-full px-2.5 py-2 rounded-lg border border-slate-300 bg-white font-mono-code text-slate-700 focus:border-[#1E40AF] outline-none">
            </div>

            <!-- Botones de Exportación Excel / PDF -->
            <div class="md:col-span-2 flex gap-1.5">
                <button type="submit"
                        onclick="setExportTarget('{{ route('dashboard.export.excel') }}', false)"
                        class="flex-1 px-3 py-2 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-center transition"
                        title="Descargar libro Microsoft Excel (.xlsx) con formato contable">
                    Excel (.xlsx)
                </button>
                <button type="submit"
                        onclick="setExportTarget('{{ route('dashboard.export.pdf') }}', true)"
                        class="flex-1 px-3 py-2 rounded-lg bg-[#1E40AF] hover:bg-blue-900 text-white font-bold text-center transition"
                        title="Abrir documento oficial PDF listo para imprimir o guardar">
                    Acta PDF
                </button>
            </div>
        </form>
    </div>

    <!-- ========================================================================= -->
    <!-- 6 TARJETAS KPI FINANCIERAS, LOGÍSTICAS Y SANITARIAS EN TIEMPO REAL        -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">

        <!-- 1. Recaudación y Desglose Tributario SII -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Recaudación Caja (SII)</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">IVA 19% Incluido</span>
            </div>
            <div class="text-2xl font-extrabold text-slate-900 font-mono-code">
                ${{ number_format($totalVentasDia, 0, ',', '.') }}
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <span>Neto Afecto: <strong class="font-mono-code text-slate-800">${{ number_format($subtotalNetoDia, 0, ',', '.') }}</strong></span>
                <span>IVA Débito: <strong class="font-mono-code text-slate-800">${{ number_format($totalIvaDia, 0, ',', '.') }}</strong></span>
            </div>
        </div>

        <!-- 2. Boletas Emitidas y Ticket Promedio -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Boletas y Ticket Promedio</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-[#1E40AF] border border-blue-200">POS Activo</span>
            </div>
            <div class="flex items-baseline gap-3">
                <div class="text-2xl font-extrabold text-slate-900 font-mono-code">
                    {{ $boletasEmitidasDia }} <span class="text-xs font-bold text-slate-500">boletas</span>
                </div>
                <div class="text-xs font-semibold text-slate-500">
                    ({{ number_format($unidadesDispensadasDia, 0, ',', '.') }} u. dispensadas)
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <span>Ticket Promedio: <strong class="font-mono-code text-slate-800">${{ number_format($ticketPromedio, 0, ',', '.') }}</strong></span>
                <a href="{{ route('pos.index') }}" class="text-[#1E40AF] font-bold hover:underline">Ir al POS &rarr;</a>
            </div>
        </div>

        <!-- 3. Bonificaciones y Convenios de Salud -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Bonificación Convenios</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                    {{ $pctVentasConvenio }}% de las ventas
                </span>
            </div>
            <div class="text-2xl font-extrabold text-emerald-700 font-mono-code">
                -${{ number_format($totalDescuentosDia, 0, ',', '.') }}
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <span>Ventas con convenio: <strong class="text-slate-800">{{ $ventasConConvenioCount }} de {{ $boletasEmitidasDia }}</strong></span>
                <a href="{{ route('convenios.index') }}" class="text-[#1E40AF] font-bold hover:underline">Ver Convenios &rarr;</a>
            </div>
        </div>

        <!-- 4. Valorización Total del Inventario Activo -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Valorización de Inventario</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                    {{ number_format($stockVitrinaUnidades + $stockBodegaUnidades, 0, ',', '.') }} u. totales
                </span>
            </div>
            <div class="text-2xl font-extrabold text-slate-900 font-mono-code">
                ${{ number_format($valorizacionTotalInventario, 0, ',', '.') }}
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <span>Vitrina: <strong class="font-mono-code text-blue-700">${{ number_format($valorizacionVitrina, 0, ',', '.') }}</strong> ({{ $stockVitrinaUnidades }} u.)</span>
                <span>Bodega: <strong class="font-mono-code text-slate-700">${{ number_format($valorizacionBodega, 0, ',', '.') }}</strong> ({{ $stockBodegaUnidades }} u.)</span>
            </div>
        </div>

        <!-- 5. Quiebres de Stock en Vitrina y Respaldo en Bodega -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Quiebres de Stock en Vitrina</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $quiebresCount > 0 ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">
                    {{ $quiebresCount > 0 ? 'Requiere reposición' : '100% Abastecido' }}
                </span>
            </div>
            <div class="text-2xl font-extrabold text-slate-900 font-mono-code">
                {{ $quiebresCount }} <span class="text-xs font-bold text-slate-500">productos sin vitrina</span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <span>Con respaldo en Bodega: <strong class="text-amber-700">{{ $quiebresConBodega->count() }} disponibles</strong></span>
                <a href="{{ route('inventario.index', ['ubicacion' => 'BODEGA']) }}" class="text-[#1E40AF] font-bold hover:underline">Abastecer &rarr;</a>
            </div>
        </div>

        <!-- 6. Riesgo FEFO y Fiscalización ISP -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Vencimientos FEFO e ISP</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-50 text-red-700 border border-red-200">
                    Riesgo: ${{ number_format($valorizacionRiesgoFefo, 0, ',', '.') }}
                </span>
            </div>
            <div class="flex items-baseline justify-between">
                <div class="text-2xl font-extrabold text-red-600 font-mono-code">
                    {{ $lotesProxVencerCount }} <span class="text-xs font-bold text-slate-500">lotes ≤ 30d</span>
                </div>
                <div class="text-xs font-semibold text-slate-600">
                    ISP: <strong class="text-emerald-700">{{ $ispAutorizadasCount }} aut.</strong> / <strong class="text-amber-700">{{ $ispPendientesCount }} pend.</strong>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <a href="{{ route('inventario.index', ['ubicacion' => 'VITRINA']) }}" class="text-red-700 font-bold hover:underline">Controlar lotes FEFO &rarr;</a>
                <a href="{{ route('autorizaciones.index') }}" class="text-[#1E40AF] font-bold hover:underline">Bandeja Q.F. &rarr;</a>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- GRÁFICOS GERENCIALES: EVOLUCIÓN 7 DÍAS + RANKING PARETO DE ROTACIÓN REAL  -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Evolución de Ventas Diarias — Últimos 7 días -->
        <div class="lg:col-span-6 bg-white rounded-xl border border-slate-200 p-5 shadow-sm flex flex-col justify-between">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Evolución de Ingresos — Últimos 7 Días</h2>
                    <p class="text-[11px] text-slate-400">
                        Total 7 días: <strong class="text-slate-700 font-mono-code">${{ number_format($totalSemanal, 0, ',', '.') }}</strong> • Promedio diario: <strong class="text-slate-700 font-mono-code">${{ number_format($promedioSemanal, 0, ',', '.') }}</strong>
                    </p>
                </div>
                <span class="px-2.5 py-1 rounded bg-blue-50 text-[#1E40AF] text-[10px] font-bold">Tendencia Semanal</span>
            </div>

            <!-- Gráfico de Barras Comparativo Proporcional -->
            <div class="h-52 flex items-end justify-between gap-3 pt-6 pb-2 px-2 border-b border-slate-100">
                @foreach($tendenciaSemanal as $dia)
                    @php
                        $alturaPct = max(12, min(100, round(($dia['monto'] / $maxVentaSemanal) * 100)));
                        $esHoy = $dia['es_hoy'] ?? false;
                    @endphp
                    <div class="flex-1 flex flex-col items-center justify-end h-full group">
                        <div class="text-[10px] font-mono-code font-bold {{ $esHoy ? 'text-emerald-700' : 'text-slate-600' }} mb-1">
                            ${{ number_format($dia['monto'] / 1000, 0) }}k
                        </div>
                        <div class="w-full max-w-[42px] rounded-t-lg transition-all {{ $esHoy ? 'bg-emerald-600 shadow-sm' : 'bg-[#1E40AF]/85 hover:bg-[#1E40AF]' }}"
                             style="height: {{ $alturaPct }}%"
                             title="{{ $dia['label'] }} ({{ $dia['fecha'] }}): ${{ number_format($dia['monto'], 0, ',', '.') }} ({{ $dia['boletas'] }} boletas)">
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-7 text-center text-[11px] text-slate-500 pt-2.5">
                @foreach($tendenciaSemanal as $dia)
                    <div>
                        <div class="font-bold {{ ($dia['es_hoy'] ?? false) ? 'text-emerald-700' : 'text-slate-700' }}">
                            {{ $dia['label'] }}
                        </div>
                        <div class="text-[10px] text-slate-400 font-mono-code">{{ $dia['fecha'] }} • {{ $dia['boletas'] }} bol.</div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Mayor Rotación de Medicamentos (Pareto Real desde Base de Datos) -->
        <div class="lg:col-span-6 bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Ranking Pareto — Medicamentos de Mayor Rotación</h2>
                    <p class="text-[11px] text-slate-400">Calculado sobre unidades reales dispensadas e ingresos generados</p>
                </div>
                <span class="px-2.5 py-1 rounded bg-blue-50 text-[#1E40AF] text-[10px] font-bold">Top Rotación</span>
            </div>

            <div class="space-y-3">
                @forelse($rotacionReal as $idx => $item)
                    @php
                        $med = $item->medicamento;
                        $unidades = (int) $item->total_unidades;
                        $ingresos = (int) round($item->total_ingresos ?? 0);
                        $pct = max(8, min(100, round(($unidades / $maxUnidadesPareto) * 100)));
                        $esControlado = $med?->requiere_receta || $med?->tipo_controlado;
                    @endphp
                    <div class="space-y-1">
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="w-5 h-5 rounded bg-slate-100 text-slate-700 font-mono-code font-bold text-[10px] flex items-center justify-center shrink-0">
                                    #{{ $idx + 1 }}
                                </span>
                                <span class="font-bold text-slate-800 truncate">{{ $med?->nombre_comercial ?? 'Medicamento' }}</span>
                                @if($esControlado)
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-red-50 text-red-700 border border-red-200 shrink-0">ISP</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-3 shrink-0 font-mono-code">
                                <span class="text-[11px] text-slate-500">${{ number_format($ingresos, 0, ',', '.') }}</span>
                                <span class="font-bold text-slate-900 w-14 text-right">{{ $unidades }} u.</span>
                            </div>
                        </div>
                        <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                            <div class="{{ $esControlado ? 'bg-red-600' : 'bg-[#1E40AF]' }} h-full rounded-full" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-slate-400">Sin registros de dispensación en el período.</div>
                @endforelse
            </div>
        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- LIBRO DIARIO DE BOLETAS EMITIDAS EN POS (ARQUEO DE CAJA EN TIEMPO REAL)   -->
    <!-- ========================================================================= -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Arqueo de Caja — Últimas Boletas Emitidas en POS</h2>
                <p class="text-[11px] text-slate-500">Trazabilidad completa por boleta, vendedor, lote FEFO dispensado, convenio aplicado e IVA 19% SII.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('dashboard.export.excel', ['tipo' => 'ventas', 'periodo' => $periodoActual]) }}"
                   class="px-3 py-1.5 rounded-lg border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-[11px] font-bold transition">
                    Descargar Libro Ventas (.xlsx)
                </a>
                <a href="{{ route('dashboard.export.pdf', ['tipo' => 'ventas', 'periodo' => $periodoActual]) }}" target="_blank"
                   class="px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 text-[11px] font-bold transition">
                    Imprimir Arqueo PDF
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 text-[10px] font-bold uppercase text-slate-400 bg-slate-50/70 whitespace-nowrap">
                        <th class="py-3 px-4">Boleta</th>
                        <th class="py-3 px-4">Fecha / Hora</th>
                        <th class="py-3 px-4">Vendedor</th>
                        <th class="py-3 px-4">Medicamentos y Lotes FEFO Dispensados</th>
                        <th class="py-3 px-4">Convenio</th>
                        <th class="py-3 px-4 text-right">Neto</th>
                        <th class="py-3 px-4 text-right">IVA (19%)</th>
                        <th class="py-3 px-4 text-right">Bonificación</th>
                        <th class="py-3 px-4 text-right">Total Pagado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($ultimasVentas as $v)
                        @php
                            $itemsStr = $v->detalles->map(function ($d) {
                                $medName = $d->medicamento?->nombre_comercial ?? 'Medicamento';
                                $loteCod = $d->lote?->lote_codigo ? " [{$d->lote->lote_codigo}]" : '';
                                return "{$d->cantidad}x {$medName}{$loteCod}";
                            })->implode(' • ');
                        @endphp
                        <tr class="hover:bg-slate-50/80">
                            <td class="py-3 px-4 font-mono-code font-bold text-slate-900 whitespace-nowrap">#{{ $v->id_venta }}</td>
                            <td class="py-3 px-4 font-mono-code text-[11px] text-slate-500 whitespace-nowrap">{{ $v->fecha_venta?->format('d-m-Y H:i') }}</td>
                            <td class="py-3 px-4 text-slate-700 font-medium whitespace-nowrap">{{ $v->usuario?->nombre_completo ?? 'Mesón POS' }}</td>
                            <td class="py-3 px-4 text-[11px] text-slate-600 max-w-md truncate" title="{{ $itemsStr }}">
                                {{ $itemsStr ?: 'Dispensación en mesón' }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                @if($v->convenio)
                                    <span class="px-2 py-0.5 rounded bg-blue-50 text-[#1E40AF] font-bold text-[10px] border border-blue-200">
                                        {{ $v->convenio->nombre_convenio }} ({{ number_format($v->convenio->porcentaje_descuento, 0) }}%)
                                    </span>
                                @else
                                    <span class="text-[11px] text-slate-400">Particular</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right font-mono-code text-slate-600 whitespace-nowrap">
                                ${{ number_format($v->subtotal, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono-code text-slate-600 whitespace-nowrap">
                                ${{ number_format($v->iva, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono-code font-semibold text-emerald-700 whitespace-nowrap">
                                -${{ number_format($v->descuento_total, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono-code font-extrabold text-slate-900 whitespace-nowrap">
                                ${{ number_format($v->total, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-slate-400 text-xs">No hay boletas registradas en este período.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN INFERIOR: ALERTAS FEFO & QUIEBRES + LIQUIDACIÓN DE CONVENIOS      -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Alertas Activas FEFO y Reposición de Quiebres -->
        <div class="lg:col-span-5 bg-white rounded-xl border border-slate-200 p-5 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Alertas Sanitarias FEFO (&le; 30 días)</h2>
                    <p class="text-[11px] text-slate-400">Lotes que requieren rotación prioritaria o baja preventiva</p>
                </div>
                <a href="{{ route('inventario.index', ['ubicacion' => 'VITRINA']) }}"
                   class="text-[11px] font-bold text-[#1E40AF] hover:underline">
                    Gestionar lotes &rarr;
                </a>
            </div>

            <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                @forelse($alertasFefo as $alerta)
                    @php
                        $dias = $alerta->dias_restantes;
                        $isCrit = $dias < 20;
                        $valRiesgo = (int) round(($alerta->medicamento?->precio_unitario ?? 0) * $alerta->cantidad);
                    @endphp
                    <div class="p-3 rounded-xl border {{ $isCrit ? 'bg-red-50/50 border-red-200' : 'bg-amber-50/60 border-amber-200' }} flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-slate-900 truncate">{{ $alerta->medicamento?->nombre_comercial }}</div>
                            <div class="text-[11px] font-mono-code text-slate-500">
                                {{ $alerta->lote_codigo }} • {{ $alerta->ubicacion }} ({{ $alerta->cantidad }} u. • ${{ number_format($valRiesgo, 0, ',', '.') }})
                            </div>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-extrabold shrink-0 {{ $isCrit ? 'bg-red-100 text-[#EF4444]' : 'bg-amber-100 text-amber-800' }}">
                            {{ $dias }}d
                        </span>
                    </div>
                @empty
                    <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold">
                        Sin lotes en riesgo crítico de vencimiento (&le; 30 días).
                    </div>
                @endforelse
            </div>

            @if($quiebresConBodega->isNotEmpty())
                <div class="pt-3 border-t border-slate-100">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-amber-900">Reposición Urgente: Sin Vitrina pero con Bodega</span>
                        <a href="{{ route('inventario.index', ['ubicacion' => 'BODEGA']) }}" class="text-[11px] font-bold text-[#1E40AF] hover:underline">
                            Traspasar a Vitrina &rarr;
                        </a>
                    </div>
                    <div class="space-y-1.5">
                        @foreach($quiebresConBodega->take(4) as $qb)
                            <div class="px-3 py-2 rounded-lg bg-amber-50/70 border border-amber-200 flex items-center justify-between text-xs">
                                <span class="font-semibold text-slate-800 truncate">{{ $qb->nombre_comercial }}</span>
                                <span class="font-mono-code text-[11px] font-bold text-amber-800 shrink-0">
                                    Vitrina: 0 u. | Bodega: {{ $qb->stock_bodega }} u.
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- Resumen de Liquidación de Convenios de Salud -->
        <div class="lg:col-span-7 bg-white rounded-xl border border-slate-200 p-5 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Liquidación por Convenio de Salud</h2>
                        <p class="text-[11px] text-slate-400">Bonificaciones otorgadas y montos recaudados por institución en convenio</p>
                    </div>
                    <a href="{{ route('dashboard.export.excel', ['tipo' => 'convenios']) }}"
                       class="px-2.5 py-1 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 text-[11px] font-bold transition">
                        Exportar Convenios (.xlsx)
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 text-[10px] font-bold uppercase text-slate-400">
                                <th class="py-2.5 px-3">Convenio / Institución</th>
                                <th class="py-2.5 px-3 text-center">Cobertura</th>
                                <th class="py-2.5 px-3 text-center">Estado</th>
                                <th class="py-2.5 px-3 text-center">Boletas</th>
                                <th class="py-2.5 px-3 text-right">Bonificaciones</th>
                                <th class="py-2.5 px-3 text-right">Recaudado Copagos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            @foreach($conveniosResumen->take(7) as $c)
                                <tr class="hover:bg-slate-50/80">
                                    <td class="py-2.5 px-3 font-semibold text-slate-800">{{ $c->nombre_convenio }}</td>
                                    <td class="py-2.5 px-3 text-center">
                                        <span class="px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-bold text-[10px]">
                                            {{ number_format($c->porcentaje_descuento, 0) }}%
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $c->esta_vigente ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                            {{ $c->estado_vigencia }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-mono-code font-bold text-slate-700">
                                        {{ (int) $c->transacciones_hoy }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono-code font-semibold text-emerald-700">
                                        -${{ number_format($c->descuentos_hoy ?? 0, 0, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono-code font-bold text-slate-900">
                                        ${{ number_format($c->neto_hoy ?? 0, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <span>Total de convenios institucionales configurados: <strong>{{ $conveniosResumen->count() }}</strong></span>
                <a href="{{ route('convenios.index') }}" class="text-[#1E40AF] font-bold hover:underline">Administrar Convenios y Vademécum &rarr;</a>
            </div>
        </div>

    </div>

</div>

<script>
    function setExportTarget(url, openInNewTab) {
        const form = document.getElementById('export-center-form');
        if (!form) return;
        form.action = url;
        if (openInNewTab) {
            form.target = '_blank';
        } else {
            form.removeAttribute('target');
        }
    }
</script>
@endsection
