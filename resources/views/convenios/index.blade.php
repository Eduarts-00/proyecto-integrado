@extends('layouts.app')

@section('title', 'Gestión de Convenios de Salud — FarmaNet Pro')

@section('content')
@php
    $canManageConvenios = auth()->user()?->hasPermission('convenios.manage') ?? false;
@endphp

<div class="space-y-5">

    <!-- Cabecera Pantalla 3 -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Gestión de Convenios de Salud</h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Administración de convenios, porcentajes de descuento, fechas de vigencia y medicamentos cubiertos.
            </p>
        </div>

        @if($canManageConvenios)
            <button type="button" onclick="document.getElementById('modal-nuevo-convenio').classList.remove('hidden')"
                    class="px-4 py-2.5 rounded-lg bg-[#1E40AF] hover:bg-blue-800 text-white text-xs font-bold shadow-sm transition">
                + Nuevo Convenio
            </button>
        @else
            <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-100 border border-slate-200 text-slate-600 text-xs font-semibold">
                🔒 Modo Consulta ({{ auth()->user()?->role_label }}) — Edición exclusiva Administrador
            </span>
        @endif
    </div>

    <!-- Barra de Filtros por Estado y Fechas de Vigencia -->
    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
        <form action="{{ route('convenios.index') }}" method="GET" class="flex flex-wrap items-end gap-3 text-xs">
            <div>
                <label class="block font-bold text-slate-600 mb-1">Estado de Vigencia</label>
                <select name="estado" class="px-3 py-2 rounded-lg border border-slate-300 bg-white">
                    <option value="todos" {{ ($filtroEstado ?? 'todos') === 'todos' ? 'selected' : '' }}>Todos los convenios</option>
                    <option value="vigentes" {{ ($filtroEstado ?? '') === 'vigentes' ? 'selected' : '' }}>Solo vigentes hoy</option>
                    <option value="programados" {{ ($filtroEstado ?? '') === 'programados' ? 'selected' : '' }}>Programados</option>
                    <option value="vencidos" {{ ($filtroEstado ?? '') === 'vencidos' ? 'selected' : '' }}>Vencidos por fecha</option>
                    <option value="deshabilitados" {{ ($filtroEstado ?? '') === 'deshabilitados' ? 'selected' : '' }}>Deshabilitados</option>
                </select>
            </div>
            <div>
                <label class="block font-bold text-slate-600 mb-1">Vigentes desde</label>
                <input type="date" name="desde" value="{{ $fechaDesde ?? '' }}"
                       min="2020-01-01" max="{{ now()->addYears(5)->toDateString() }}"
                       class="px-3 py-1.5 rounded-lg border border-slate-300 font-mono-code">
            </div>
            <div>
                <label class="block font-bold text-slate-600 mb-1">Vigentes hasta</label>
                <input type="date" name="hasta" value="{{ $fechaHasta ?? '' }}"
                       min="2020-01-01" max="{{ now()->addYears(5)->toDateString() }}"
                       class="px-3 py-1.5 rounded-lg border border-slate-300 font-mono-code">
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="px-4 py-2 rounded-lg bg-[#1E40AF] hover:bg-blue-800 text-white font-bold transition">
                    Filtrar por Fechas
                </button>
                @if(($filtroEstado ?? 'todos') !== 'todos' || !empty($fechaDesde) || !empty($fechaHasta))
                    <a href="{{ route('convenios.index') }}" class="px-3 py-2 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50">
                        Limpiar
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Tabla de Convenios -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 text-[11px] font-bold uppercase text-slate-400 bg-slate-50/60">
                        <th class="py-3.5 px-5">Nombre del Convenio</th>
                        <th class="py-3.5 px-4 text-center">Bonificación (%)</th>
                        <th class="py-3.5 px-5">Medicamentos Cubiertos</th>
                        <th class="py-3.5 px-4 text-center">Período de Vigencia</th>
                        <th class="py-3.5 px-4 text-center">Estado</th>
                        @if($canManageConvenios)
                            <th class="py-3.5 px-5 text-right">Acciones</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($convenios as $conv)
                        @php
                            $pct = (float) $conv->porcentaje_descuento;
                            $estadoVigencia = $conv->estado_vigencia;
                            $medsAdheridos = $conv->medicamentos;
                            $badgeClass = match (true) {
                                $pct >= 80 => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                $pct >= 40 => 'bg-blue-50 text-blue-700 border-blue-200',
                                $pct > 0 => 'bg-amber-50 text-amber-700 border-amber-200',
                                default => 'bg-slate-100 text-slate-500 border-slate-200',
                            };
                            $estadoBadgeClass = match ($estadoVigencia) {
                                'VIGENTE' => 'bg-emerald-100 text-emerald-800',
                                'PROGRAMADO' => 'bg-blue-100 text-blue-800',
                                'VENCIDO' => 'bg-amber-100 text-amber-800',
                                default => 'bg-red-100 text-red-700',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/80 {{ $estadoVigencia !== 'VIGENTE' ? 'bg-slate-50/60 opacity-80' : '' }}">
                            <td class="py-3.5 px-5">
                                <div class="font-bold {{ $conv->activo ? 'text-slate-900' : 'text-slate-400 line-through' }}">
                                    {{ $conv->nombre_convenio }}
                                </div>
                                @if($pct > 0)
                                    <div class="text-[11px] text-slate-400 mt-0.5">
                                        Ej. en $10.000 cubiertos &rarr; <span class="text-emerald-700 font-semibold">ahorra ${{ number_format($conv->calcularAhorroEjemplo(10000), 0, ',', '.') }}</span>
                                    </div>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-bold border {{ $badgeClass }}">
                                    {{ number_format($pct, 0) }}%
                                </span>
                            </td>
                            <td class="py-3.5 px-5 max-w-md">
                                @if($pct <= 0)
                                    <span class="text-slate-400 text-[11px]">Venta particular sin bonificación</span>
                                @elseif($conv->aplica_todo_catalogo)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md bg-blue-50 text-[#1E40AF] border border-blue-200 text-[11px] font-semibold">
                                        🌐 Todo el catálogo
                                    </span>
                                @else
                                    <div class="space-y-1">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-indigo-50 text-indigo-800 border border-indigo-200 text-[10px] font-bold">
                                            💊 {{ $medsAdheridos->count() }} medicamentos cubiertos
                                        </span>
                                        <div class="flex flex-wrap gap-1">
                                            @forelse($medsAdheridos->take(5) as $medItem)
                                                <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200 text-[10px]">
                                                    {{ $medItem->nombre_comercial }}
                                                </span>
                                            @empty
                                                <span class="text-[10px] text-amber-600 font-semibold">Sin medicamentos asignados aún</span>
                                            @endforelse
                                            @if($medsAdheridos->count() > 5)
                                                <span class="px-1.5 py-0.5 rounded bg-slate-200 text-slate-700 text-[10px] font-bold">
                                                    +{{ $medsAdheridos->count() - 5 }} más
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center font-mono-code text-[11px] text-slate-600 whitespace-nowrap">
                                @if(! $conv->fecha_inicio && ! $conv->fecha_fin)
                                    <span class="text-slate-400 font-sans">Permanente</span>
                                @else
                                    <span>{{ $conv->fecha_inicio ? $conv->fecha_inicio->format('d-m-Y') : 'Desde inicio' }}</span>
                                    <span class="text-slate-400 mx-1">&rarr;</span>
                                    <span>{{ $conv->fecha_fin ? $conv->fecha_fin->format('d-m-Y') : 'Indefinido' }}</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold {{ $estadoBadgeClass }}">
                                    {{ $estadoVigencia }}
                                </span>
                            </td>
                            @if($canManageConvenios)
                                <td class="py-3.5 px-5 text-right whitespace-nowrap">
                                    @if($pct <= 0)
                                        <span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-400 text-[10px] font-semibold">
                                            Tarifa base
                                        </span>
                                    @else
                                        <div class="inline-flex items-center justify-end gap-1.5">
                                            <button type="button"
                                                    onclick="openEditConvenio({{ json_encode([
                                                        'id_convenio' => $conv->id_convenio,
                                                        'nombre_convenio' => $conv->nombre_convenio,
                                                        'porcentaje_descuento' => $pct,
                                                        'aplica_todo_catalogo' => $conv->aplica_todo_catalogo ? '1' : '0',
                                                        'medicamentos_ids' => $medsAdheridos->pluck('id_medicamento')->values()->all(),
                                                        'fecha_inicio' => $conv->fecha_inicio?->format('Y-m-d') ?? '',
                                                        'fecha_fin' => $conv->fecha_fin?->format('Y-m-d') ?? '',
                                                        'activo' => $conv->activo ? '1' : '0',
                                                    ]) }})"
                                                    class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-blue-50 text-[#1E40AF] font-bold text-[11px] transition">
                                                Editar
                                            </button>
                                            <form action="{{ route('convenios.toggle_status', $conv->id_convenio) }}" method="POST" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                @if($conv->activo)
                                                    <button type="submit"
                                                            class="px-2.5 py-1.5 rounded-lg border border-red-200 bg-red-50/70 hover:bg-red-100 text-red-700 font-bold text-[11px] transition">
                                                        Deshabilitar
                                                    </button>
                                                @else
                                                    <button type="submit"
                                                            class="px-2.5 py-1.5 rounded-lg border border-emerald-200 bg-emerald-50/70 hover:bg-emerald-100 text-emerald-700 font-bold text-[11px] transition">
                                                        Habilitar
                                                    </button>
                                                @endif
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManageConvenios ? 6 : 5 }}" class="py-8 text-center text-slate-400 text-xs">
                                No se encontraron convenios para el rango de fechas o estado seleccionado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@if($canManageConvenios)
<!-- Modal Nuevo Convenio -->
<div id="modal-nuevo-convenio" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full border border-slate-200 shadow-xl overflow-hidden max-h-[92vh] flex flex-col">
        <div class="bg-[#1E40AF] text-white px-5 py-4 flex items-center justify-between">
            <div>
                <h3 class="font-bold text-sm">+ Nuevo Convenio de Salud</h3>
                <p class="text-[11px] text-blue-100">Configura porcentaje de descuento, vigencia y medicamentos cubiertos</p>
            </div>
            <button type="button" onclick="document.getElementById('modal-nuevo-convenio').classList.add('hidden')" class="text-white">✕</button>
        </div>
        <form action="{{ route('convenios.store') }}" method="POST" class="p-5 space-y-4 text-xs overflow-y-auto">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="block font-bold text-slate-700 mb-1">Nombre del Convenio (3 a 80 caract.)</label>
                    <input type="text" name="nombre_convenio" required placeholder="Ej: Isapre Vida Tres"
                           minlength="3" maxlength="80"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Bonificación (%)</label>
                    <input type="number" name="porcentaje_descuento" min="0" max="100" step="1" value="35" required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code font-bold">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Fecha de Inicio</label>
                    <input type="date" name="fecha_inicio" value="{{ now()->toDateString() }}"
                           min="2020-01-01" max="{{ now()->addYears(5)->toDateString() }}"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Fecha de Término</label>
                    <input type="date" name="fecha_fin" value="{{ now()->addYear()->toDateString() }}"
                           min="{{ now()->toDateString() }}" max="{{ now()->addYears(5)->toDateString() }}"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
                <div>
                    <label class="block font-bold text-slate-800 mb-1">Cobertura de Productos</label>
                    <select id="nuevo-aplica-todo" name="aplica_todo_catalogo" onchange="toggleVademecumBox('nuevo')"
                            class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white font-semibold text-slate-800">
                        <option value="0" selected>💊 Selección de medicamentos específicos</option>
                        <option value="1">🌐 Todos los medicamentos de la farmacia</option>
                    </select>
                </div>

                <div id="nuevo-vademecum-box" class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-700">Medicamentos cubiertos:</span>
                        <div class="space-x-2 text-[11px]">
                            <button type="button" onclick="checkAllMeds('nuevo', true)" class="text-[#1E40AF] font-semibold hover:underline">Marcar todos</button>
                            <span class="text-slate-300">|</span>
                            <button type="button" onclick="checkAllMeds('nuevo', false)" class="text-slate-500 hover:underline">Desmarcar</button>
                        </div>
                    </div>
                    <div class="max-h-44 overflow-y-auto border border-slate-200 rounded-lg bg-white divide-y divide-slate-100 p-1">
                        @foreach($medicamentosCatalogo ?? [] as $med)
                            <label class="flex items-center justify-between px-2.5 py-1.5 hover:bg-blue-50/50 cursor-pointer rounded">
                                <span class="flex items-center gap-2">
                                    <input type="checkbox" name="medicamentos_ids[]" value="{{ $med->id_medicamento }}" checked
                                           class="nuevo-med-check rounded border-slate-300 text-[#1E40AF]">
                                    <span class="font-semibold text-slate-800">{{ $med->nombre_comercial }}</span>
                                    <span class="text-[10px] text-slate-400">({{ $med->laboratorio }})</span>
                                </span>
                                <span class="font-mono-code text-[11px] text-slate-600">${{ number_format($med->precio_unitario, 0, ',', '.') }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('modal-nuevo-convenio').classList.add('hidden')"
                        class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-[#1E40AF] text-white font-bold">Guardar Convenio</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Convenio -->
<div id="modal-editar-convenio" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full border border-slate-200 shadow-xl overflow-hidden max-h-[92vh] flex flex-col">
        <div class="bg-[#0F172A] text-white px-5 py-4 flex items-center justify-between">
            <div>
                <h3 class="font-bold text-sm">Editar Convenio de Salud</h3>
                <p class="text-[11px] text-slate-300">Modifica datos del convenio y medicamentos con descuento</p>
            </div>
            <button type="button" onclick="document.getElementById('modal-editar-convenio').classList.add('hidden')" class="text-white">✕</button>
        </div>
        <form id="form-editar-convenio" method="POST" class="p-5 space-y-4 text-xs overflow-y-auto">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="block font-bold text-slate-700 mb-1">Nombre del Convenio (3 a 80 caract.)</label>
                    <input type="text" id="edit-nombre-convenio" name="nombre_convenio" required
                           minlength="3" maxlength="80"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Bonificación (%)</label>
                    <input type="number" id="edit-pct-convenio" name="porcentaje_descuento" min="0" max="100" step="1" required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code font-bold">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Fecha de Inicio</label>
                    <input type="date" id="edit-fecha-inicio" name="fecha_inicio"
                           min="2020-01-01" max="{{ now()->addYears(5)->toDateString() }}"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Fecha de Término</label>
                    <input type="date" id="edit-fecha-fin" name="fecha_fin"
                           min="2020-01-01" max="{{ now()->addYears(5)->toDateString() }}"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
                <div>
                    <label class="block font-bold text-slate-800 mb-1">Cobertura de Productos</label>
                    <select id="edit-aplica-todo" name="aplica_todo_catalogo" onchange="toggleVademecumBox('edit')"
                            class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white font-semibold text-slate-800">
                        <option value="0">💊 Selección de medicamentos específicos</option>
                        <option value="1">🌐 Todos los medicamentos de la farmacia</option>
                    </select>
                </div>

                <div id="edit-vademecum-box" class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-700">Medicamentos cubiertos por este convenio:</span>
                        <div class="space-x-2 text-[11px]">
                            <button type="button" onclick="checkAllMeds('edit', true)" class="text-[#1E40AF] font-semibold hover:underline">Marcar todos</button>
                            <span class="text-slate-300">|</span>
                            <button type="button" onclick="checkAllMeds('edit', false)" class="text-slate-500 hover:underline">Desmarcar</button>
                        </div>
                    </div>
                    <div class="max-h-44 overflow-y-auto border border-slate-200 rounded-lg bg-white divide-y divide-slate-100 p-1">
                        @foreach($medicamentosCatalogo ?? [] as $med)
                            <label class="flex items-center justify-between px-2.5 py-1.5 hover:bg-blue-50/50 cursor-pointer rounded">
                                <span class="flex items-center gap-2">
                                    <input type="checkbox" name="medicamentos_ids[]" value="{{ $med->id_medicamento }}"
                                           class="edit-med-check rounded border-slate-300 text-[#1E40AF]">
                                    <span class="font-semibold text-slate-800">{{ $med->nombre_comercial }}</span>
                                    <span class="text-[10px] text-slate-400">({{ $med->laboratorio }})</span>
                                </span>
                                <span class="font-mono-code text-[11px] text-slate-600">${{ number_format($med->precio_unitario, 0, ',', '.') }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Estado del Convenio</label>
                <select id="edit-activo-convenio" name="activo"
                        class="w-full px-3 py-2 rounded-lg border border-slate-300">
                    <option value="1">Activo</option>
                    <option value="0">Deshabilitado</option>
                </select>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('modal-editar-convenio').classList.add('hidden')"
                        class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-[#1E40AF] text-white font-bold">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleVademecumBox(prefix) {
        const selectEl = document.getElementById(prefix + '-aplica-todo');
        const boxEl = document.getElementById(prefix + '-vademecum-box');
        if (!selectEl || !boxEl) return;
        if (selectEl.value === '1') {
            boxEl.classList.add('hidden');
        } else {
            boxEl.classList.remove('hidden');
        }
    }

    function checkAllMeds(prefix, checked) {
        document.querySelectorAll('.' + prefix + '-med-check').forEach(cb => {
            cb.checked = checked;
        });
    }

    function openEditConvenio(conv) {
        document.getElementById('form-editar-convenio').action = '/convenios/' + conv.id_convenio;
        document.getElementById('edit-nombre-convenio').value = conv.nombre_convenio || '';
        document.getElementById('edit-pct-convenio').value = conv.porcentaje_descuento ?? 0;
        document.getElementById('edit-fecha-inicio').value = conv.fecha_inicio || '';
        document.getElementById('edit-fecha-fin').value = conv.fecha_fin || '';
        document.getElementById('edit-activo-convenio').value = String(conv.activo ?? '1');
        document.getElementById('edit-aplica-todo').value = String(conv.aplica_todo_catalogo ?? '0');

        const selectedIds = (conv.medicamentos_ids || []).map(Number);
        document.querySelectorAll('.edit-med-check').forEach(cb => {
            cb.checked = selectedIds.includes(Number(cb.value));
        });

        toggleVademecumBox('edit');
        document.getElementById('modal-editar-convenio').classList.remove('hidden');
    }
</script>
@endif
@endsection
