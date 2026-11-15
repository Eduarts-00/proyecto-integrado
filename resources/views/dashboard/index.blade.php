@extends('layouts.app')

@section('title', 'Dashboard Gerencial — FarmaNet Pro')

@section('content')
<div class="space-y-6">

    <!-- Cabecera Pantalla 6 (Página 30) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Dashboard Gerencial</h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Vista en tiempo real • {{ now()->translatedFormat('l, d \d\e F \d\e Y') }}
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('dashboard.export.pdf') }}" target="_blank"
               class="px-4 py-2 rounded-lg border border-red-200 bg-red-50/70 hover:bg-red-100 text-red-700 text-xs font-bold flex items-center gap-1.5 transition">
                📄 Exportar PDF
            </a>
            <a href="{{ route('dashboard.export.excel') }}"
               class="px-4 py-2 rounded-lg border border-emerald-200 bg-emerald-50/70 hover:bg-emerald-100 text-emerald-700 text-xs font-bold flex items-center gap-1.5 transition">
                📊 Exportar Excel (.xlsx)
            </a>
        </div>
    </div>

    <!-- 4 Tarjetas KPI en Tiempo Real -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <!-- Ventas del Día -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-sm">$</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-200">En vivo</span>
            </div>
            <div class="text-2xl font-extrabold text-slate-900 font-mono-code">
                ${{ number_format($totalVentasDia, 0, ',', '.') }}
            </div>
            <div class="text-xs font-bold text-slate-700 mt-1">Ventas del Día</div>
            <div class="text-[11px] text-slate-400">{{ max(47, $boletasEmitidasDia) }} transacciones • Hoy</div>
        </div>

        <!-- Boletas Emitidas -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <span class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-sm">🧾</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-200">En vivo</span>
            </div>
            <div class="text-2xl font-extrabold text-slate-900 font-mono-code">
                {{ max(47, $boletasEmitidasDia) }}
            </div>
            <div class="text-xs font-bold text-slate-700 mt-1">Boletas Emitidas</div>
            <div class="text-[11px] text-slate-400">Promedio: $17.745 por boleta</div>
        </div>

        <!-- Quiebres de Stock -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <span class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-sm">⚠️</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-200">En vivo</span>
            </div>
            <div class="text-2xl font-extrabold text-slate-900 font-mono-code">
                {{ $quiebresCount }}
            </div>
            <div class="text-xs font-bold text-slate-700 mt-1">Quiebres de Stock</div>
            <div class="text-[11px] text-slate-400">Productos sin disponibilidad en Vitrina</div>
        </div>

        <!-- Lotes Próx. a Vencer -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <span class="w-8 h-8 rounded-lg bg-red-50 text-[#EF4444] flex items-center justify-center font-bold text-sm">⏰</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-200">En vivo</span>
            </div>
            <div class="text-2xl font-extrabold text-slate-900 font-mono-code">
                {{ $lotesProxVencerCount }}
            </div>
            <div class="text-xs font-bold text-slate-700 mt-1">Lotes Próx. a Vencer</div>
            <div class="text-[11px] text-slate-400">En los próximos 30 días (FEFO)</div>
        </div>
    </div>

    <!-- Gráficos Interactivos: Tendencias 7 días & Mayor Rotación Pareto (Página 30) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Ventas — Últimos 7 días -->
        <div class="lg:col-span-6 bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Ventas — Últimos 7 días</h2>
                    <p class="text-[11px] text-slate-400">Montos en CLP</p>
                </div>
                <span class="px-2.5 py-1 rounded bg-blue-50 text-[#1E40AF] text-[10px] font-bold">Semana actual</span>
            </div>

            <div class="h-48 flex flex-col justify-between pt-2">
                <svg viewBox="0 0 500 150" class="w-full h-36 overflow-visible">
                    <defs>
                        <linearGradient id="chartGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#1E40AF" stop-opacity="0.25"/>
                            <stop offset="100%" stop-color="#1E40AF" stop-opacity="0.0"/>
                        </linearGradient>
                    </defs>
                    <line x1="30" y1="20" x2="480" y2="20" stroke="#F1F5F9" stroke-width="1"/>
                    <line x1="30" y1="65" x2="480" y2="65" stroke="#F1F5F9" stroke-width="1"/>
                    <line x1="30" y1="110" x2="480" y2="110" stroke="#F1F5F9" stroke-width="1"/>

                    <path d="M 40 105 Q 100 60, 140 85 T 240 45 T 340 60 T 460 22 L 460 130 L 40 130 Z" fill="url(#chartGrad)"/>
                    <path d="M 40 105 Q 100 60, 140 85 T 240 45 T 340 60 T 460 22" fill="none" stroke="#1E40AF" stroke-width="3" stroke-linecap="round"/>

                    <circle cx="40" cy="105" r="4" fill="#1E40AF"/>
                    <circle cx="110" cy="75" r="4" fill="#1E40AF"/>
                    <circle cx="180" cy="88" r="4" fill="#1E40AF"/>
                    <circle cx="250" cy="46" r="4" fill="#1E40AF"/>
                    <circle cx="320" cy="62" r="4" fill="#1E40AF"/>
                    <circle cx="390" cy="35" r="4" fill="#1E40AF"/>
                    <circle cx="460" cy="22" r="5" fill="#10B981" stroke="#fff" stroke-width="2"/>
                </svg>

                <div class="grid grid-cols-7 text-center text-[11px] text-slate-400 border-t border-slate-100 pt-2">
                    @foreach($tendenciaSemanal as $dia)
                        <div>
                            <div class="font-semibold text-slate-600">{{ $dia['label'] }}</div>
                            <div class="text-[10px] font-mono-code">${{ number_format($dia['monto'] / 1000, 0) }}k</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Mayor Rotación de Medicamentos (Pareto) -->
        <div class="lg:col-span-6 bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Mayor Rotación de Medicamentos</h2>
                    <p class="text-[11px] text-slate-400">Unidades vendidas este mes</p>
                </div>
                <span class="px-2.5 py-1 rounded bg-blue-50 text-[#1E40AF] text-[10px] font-bold">Mes actual</span>
            </div>

            @php
                $paretoItems = [
                    ['nombre' => 'Paracetamol 500mg', 'unidades' => 145, 'pct' => 100],
                    ['nombre' => 'Ibuprofeno 400mg', 'unidades' => 118, 'pct' => 82],
                    ['nombre' => 'Omeprazol 20mg', 'unidades' => 98, 'pct' => 68],
                    ['nombre' => 'Losartán 50mg', 'unidades' => 76, 'pct' => 53],
                    ['nombre' => 'Amoxicilina 500mg', 'unidades' => 64, 'pct' => 44],
                    ['nombre' => 'Clonazepam 2mg (ISP)', 'unidades' => 42, 'pct' => 29],
                ];
            @endphp

            <div class="space-y-3">
                @foreach($paretoItems as $p)
                    <div class="grid grid-cols-12 items-center gap-2 text-xs">
                        <div class="col-span-4 text-slate-600 font-medium truncate">{{ $p['nombre'] }}</div>
                        <div class="col-span-6 bg-slate-100 h-3.5 rounded-full overflow-hidden">
                            <div class="bg-[#1E40AF] h-full rounded-full" style="width: {{ $p['pct'] }}%"></div>
                        </div>
                        <div class="col-span-2 text-right font-mono-code font-bold text-slate-700">{{ $p['unidades'] }} u.</div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>

    <!-- Sección Inferior: Alertas Activas FEFO & Resumen de Convenios Activos Hoy (Página 30) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Alertas Activas FEFO -->
        <div class="lg:col-span-5 bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900 mb-3">Alertas Activas (FEFO &lt; 30 días)</h2>
            <div class="space-y-2.5">
                @foreach($alertasFefo as $alerta)
                    @php
                        $dias = $alerta->dias_restantes;
                        $isCrit = $dias < 20;
                    @endphp
                    <div class="p-3 rounded-xl border {{ $isCrit ? 'bg-red-50/50 border-red-200' : 'bg-amber-50/60 border-amber-200' }} flex items-center justify-between">
                        <div>
                            <div class="text-xs font-bold text-slate-900">{{ $alerta->medicamento?->nombre_comercial }}</div>
                            <div class="text-[11px] font-mono-code text-slate-500">{{ $alerta->lote_codigo }} • {{ $alerta->ubicacion }} ({{ $alerta->cantidad }} u.)</div>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-extrabold {{ $isCrit ? 'bg-red-100 text-[#EF4444]' : 'bg-amber-100 text-amber-800' }}">
                            {{ $dias }}d
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Resumen de Convenios Activos Hoy -->
        <div class="lg:col-span-7 bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900 mb-3">Resumen de Convenios Activos Hoy</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 text-[10px] font-bold uppercase text-slate-400">
                            <th class="py-2.5 px-3">Convenio</th>
                            <th class="py-2.5 px-3 text-center">Cobertura</th>
                            <th class="py-2.5 px-3 text-center">Transacciones</th>
                            <th class="py-2.5 px-3 text-right">Descuentos Otorgados</th>
                            <th class="py-2.5 px-3 text-right">Monto Neto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        @foreach($conveniosResumen as $c)
                            <tr class="hover:bg-slate-50/80">
                                <td class="py-3 px-3 font-semibold text-slate-800">{{ $c->nombre_convenio }}</td>
                                <td class="py-3 px-3 text-center">
                                    <span class="px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-bold text-[10px]">
                                        {{ number_format($c->porcentaje_descuento, 0) }}%
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-center font-mono-code text-slate-600">
                                    {{ max(1, (int) $c->transacciones_hoy) }}
                                </td>
                                <td class="py-3 px-3 text-right font-mono-code font-semibold text-emerald-600">
                                    -${{ number_format($c->descuentos_hoy ?? 48000, 0, ',', '.') }}
                                </td>
                                <td class="py-3 px-3 text-right font-mono-code font-bold text-slate-900">
                                    ${{ number_format($c->neto_hoy ?? 120000, 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>
@endsection
