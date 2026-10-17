@extends('layouts.app')

@section('title', 'Gestión de Inventario FEFO — FarmaNet Pro')

@section('content')
<div class="space-y-5">

    <!-- Cabecera Pantalla 5 (Página 29) -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Gestión de Inventario</h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Método FEFO — First Expired, First Out (Rotación automática por caducidad)
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <span class="px-3.5 py-2 rounded-lg bg-amber-50 border border-amber-300 text-amber-800 text-xs font-bold flex items-center gap-1.5">
                ⚠️ {{ $alertasFefo->count() }} lotes próx. a vencer
            </span>

            @if(auth()->user()->hasPermission('Can_Manage_Stock'))
                <button type="button" onclick="document.getElementById('modal-traspaso-fefo').classList.remove('hidden')"
                        class="px-4 py-2 rounded-lg bg-[#1E40AF] hover:bg-blue-800 text-white text-xs font-bold shadow-sm transition">
                    + Traspaso Bodega &rarr; Vitrina
                </button>
                <button type="button" onclick="document.getElementById('modal-nueva-remesa').classList.remove('hidden')"
                        class="px-4 py-2 rounded-lg bg-[#10B981] hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition">
                    + Nueva Remesa
                </button>
            @endif
        </div>
    </div>

    <!-- Pestañas de Segregación Física: Vitrina / Bodega -->
    <div class="flex items-center gap-2">
        <a href="{{ route('inventario.index', ['ubicacion' => 'VITRINA']) }}"
           class="px-4 py-2 rounded-lg text-xs font-bold border transition flex items-center gap-2 {{ $ubicacion === 'VITRINA' ? 'bg-white border-slate-300 text-slate-900 shadow-sm' : 'bg-slate-100 border-transparent text-slate-500 hover:text-slate-800' }}">
            🏪 Vitrina
        </a>
        <a href="{{ route('inventario.index', ['ubicacion' => 'BODEGA']) }}"
           class="px-4 py-2 rounded-lg text-xs font-bold border transition flex items-center gap-2 {{ $ubicacion === 'BODEGA' ? 'bg-white border-slate-300 text-slate-900 shadow-sm' : 'bg-slate-100 border-transparent text-slate-500 hover:text-slate-800' }}">
            📦 Bodega Central
        </a>
        <a href="{{ route('inventario.index', ['ubicacion' => 'TODOS']) }}"
           class="px-4 py-2 rounded-lg text-xs font-bold border transition flex items-center gap-2 {{ $ubicacion === 'TODOS' ? 'bg-white border-slate-300 text-slate-900 shadow-sm' : 'bg-slate-100 border-transparent text-slate-500 hover:text-slate-800' }}">
            📋 Todos los Lotes
        </a>
    </div>

    <!-- Banner Informativo FEFO -->
    <div class="p-3 rounded-xl bg-blue-50/90 border border-blue-200 text-blue-800 text-xs font-medium flex items-center gap-2">
        <span>⬇️</span>
        <span>Lotes ordenados por <strong>FEFO</strong>: los primeros en expirar se muestran primero. Los marcados en rojo tienen menos de 20 días para vencer y los ámbar entre 20 y 30 días.</span>
    </div>

    <!-- Tabla Interactiva Clasificada por FEFO (Página 29) -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 text-[11px] font-bold uppercase text-slate-400 bg-slate-50/60">
                        <th class="py-3.5 px-5">Medicamento</th>
                        <th class="py-3.5 px-5">Código Lote</th>
                        <th class="py-3.5 px-5">Laboratorio</th>
                        <th class="py-3.5 px-5">Ubicación</th>
                        <th class="py-3.5 px-5 text-center">Cantidad</th>
                        <th class="py-3.5 px-5 text-center">Vencimiento</th>
                        <th class="py-3.5 px-5 text-center">Alerta FEFO</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @foreach($lotes as $lote)
                        @php
                            $dias = $lote->dias_restantes;
                            $sem = $lote->semaforo_fefo;
                            $rowBg = match ($sem) {
                                'vencido', 'critico' => 'bg-red-50/40 hover:bg-red-50/70',
                                'alerta' => 'bg-amber-50/40 hover:bg-amber-50/70',
                                default => 'hover:bg-slate-50/80',
                            };
                        @endphp
                        <tr class="{{ $rowBg }} transition">
                            <td class="py-3.5 px-5 font-medium text-slate-900 flex items-center gap-2">
                                @if($sem === 'critico' || $sem === 'vencido')
                                    <span class="w-2 h-2 rounded-full bg-[#EF4444]"></span>
                                @elseif($sem === 'alerta')
                                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                @else
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                @endif
                                <span>{{ $lote->medicamento?->nombre_comercial }}</span>
                            </td>
                            <td class="py-3.5 px-5 font-mono-code text-[11px] text-slate-500">
                                <span class="px-2 py-0.5 rounded bg-slate-100 border border-slate-200">
                                    {{ $lote->lote_codigo }}
                                </span>
                            </td>
                            <td class="py-3.5 px-5 text-slate-500">
                                {{ $lote->medicamento?->laboratorio }}
                            </td>
                            <td class="py-3.5 px-5">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $lote->ubicacion === 'VITRINA' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-slate-100 text-slate-700 border border-slate-300' }}">
                                    {{ $lote->ubicacion }}
                                </span>
                            </td>
                            <td class="py-3.5 px-5 text-center font-bold text-slate-900">
                                {{ $lote->cantidad }}
                            </td>
                            <td class="py-3.5 px-5 text-center font-mono-code text-slate-600">
                                {{ $lote->fecha_vencimiento?->format('d-m-Y') }}
                            </td>
                            <td class="py-3.5 px-5 text-center">
                                @if($sem === 'critico' || $sem === 'vencido')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-red-100 text-[#EF4444] border border-red-200">
                                        ● {{ $dias }}d
                                    </span>
                                @elseif($sem === 'alerta')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                        ▲ {{ $dias }}d
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        ✓ {{ $dias }}d
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>

@if(auth()->user()->hasPermission('Can_Manage_Stock'))
    <!-- Modal Traspaso Rápido Asistido Bodega -> Vitrina (Escenario B) -->
    <div id="modal-traspaso-fefo" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full border border-slate-200 shadow-xl overflow-hidden">
            <div class="bg-[#1E40AF] text-white px-5 py-4 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-sm">+ Traspaso Asistido Bodega &rarr; Vitrina</h3>
                    <p class="text-[11px] text-blue-200">Sugiere automáticamente el lote con caducidad más próxima (FEFO)</p>
                </div>
                <button type="button" onclick="document.getElementById('modal-traspaso-fefo').classList.add('hidden')" class="text-white">✕</button>
            </div>
            <form action="{{ route('inventario.transfer') }}" method="POST" class="p-5 space-y-4 text-xs">
                @csrf
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Lote Disponible en Bodega Central (Ordenado por FEFO)</label>
                    <select name="id_lote" required class="w-full px-3 py-2.5 rounded-lg border border-slate-300">
                        @foreach($lotesBodega as $lb)
                            <option value="{{ $lb->id_lote }}">
                                {{ $lb->medicamento?->nombre_comercial }} — {{ $lb->lote_codigo }} (Stock: {{ $lb->cantidad }} u. | Vence en {{ $lb->dias_restantes }}d)
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Cantidad a Traspasar a Vitrina</label>
                    <input type="number" name="cantidad" min="1" value="25" required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modal-traspaso-fefo').classList.add('hidden')"
                            class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-[#1E40AF] text-white font-bold">
                        Ejecutar Traspaso FEFO
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Nueva Remesa (HU-04) -->
    <div id="modal-nueva-remesa" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full border border-slate-200 shadow-xl overflow-hidden">
            <div class="bg-[#10B981] text-white px-5 py-4 flex items-center justify-between">
                <h3 class="font-bold text-sm">+ Recepción de Nueva Remesa de Lote (HU-04)</h3>
                <button type="button" onclick="document.getElementById('modal-nueva-remesa').classList.add('hidden')" class="text-white">✕</button>
            </div>
            <form action="{{ route('inventario.register') }}" method="POST" class="p-5 space-y-3.5 text-xs">
                @csrf
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Medicamento</label>
                    <select name="id_medicamento" required class="w-full px-3 py-2 rounded-lg border border-slate-300">
                        @foreach($medicamentos as $m)
                            <option value="{{ $m->id_medicamento }}">{{ $m->nombre_comercial }} ({{ $m->laboratorio }})</option>
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
@endsection
