@extends('layouts.app')

@section('title', 'Gestión de Convenios de Salud — FarmaNet Pro')

@section('content')
<div class="space-y-5">

    <!-- Cabecera Pantalla 3 (Página 27) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Gestión de Convenios de Salud</h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Define los convenios disponibles en el POS con su porcentaje de descuento y vista previa de copago.
            </p>
        </div>

        <button type="button" onclick="document.getElementById('modal-nuevo-convenio').classList.remove('hidden')"
                class="px-4 py-2.5 rounded-lg bg-[#1E40AF] hover:bg-blue-800 text-white text-xs font-bold shadow-sm transition">
            + Nuevo Convenio
        </button>
    </div>

    <!-- Tabla de Convenios -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 text-[11px] font-bold uppercase text-slate-400 bg-slate-50/60">
                        <th class="py-3.5 px-6">Nombre del Convenio</th>
                        <th class="py-3.5 px-6 text-center">Descuento (%)</th>
                        <th class="py-3.5 px-6 text-center">Vista previa</th>
                        <th class="py-3.5 px-6 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @foreach($convenios as $conv)
                        @php
                            $pct = (float) $conv->porcentaje_descuento;
                            $ahorro = $conv->calcularAhorroEjemplo(10000);
                            $badgeClass = match (true) {
                                $pct >= 80 => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                $pct >= 40 => 'bg-blue-50 text-blue-700 border-blue-200',
                                $pct > 0 => 'bg-amber-50 text-amber-700 border-amber-200',
                                default => 'bg-slate-100 text-slate-500 border-slate-200',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/80">
                            <td class="py-3.5 px-6 font-medium text-slate-900">
                                {{ $conv->nombre_convenio }}
                            </td>
                            <td class="py-3.5 px-6 text-center">
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-bold border {{ $badgeClass }}">
                                    {{ number_format($pct, 0) }}%
                                </span>
                            </td>
                            <td class="py-3.5 px-6 text-center text-slate-500">
                                @if($pct <= 0)
                                    <span class="text-slate-400">Sin descuento</span>
                                @else
                                    En $10.000 &rarr; <strong class="text-emerald-700">ahorra ${{ number_format($ahorro, 0, ',', '.') }}</strong>
                                @endif
                            </td>
                            <td class="py-3.5 px-6 text-right space-x-3">
                                <button type="button"
                                        onclick="openEditConvenio({{ $conv->id_convenio }}, '{{ addslashes($conv->nombre_convenio) }}', {{ $pct }})"
                                        class="text-blue-600 hover:text-blue-800 font-semibold">
                                    Editar
                                </button>
                                @if($pct > 0)
                                    <form action="{{ route('convenios.destroy', $conv->id_convenio) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 font-semibold">
                                            Eliminar
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal Nuevo Convenio -->
<div id="modal-nuevo-convenio" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full border border-slate-200 shadow-xl overflow-hidden">
        <div class="bg-[#1E40AF] text-white px-5 py-4 flex items-center justify-between">
            <h3 class="font-bold text-sm">+ Nuevo Convenio de Salud</h3>
            <button type="button" onclick="document.getElementById('modal-nuevo-convenio').classList.add('hidden')" class="text-white">✕</button>
        </div>
        <form action="{{ route('convenios.store') }}" method="POST" class="p-5 space-y-4 text-xs">
            @csrf
            <div>
                <label class="block font-bold text-slate-700 mb-1">Nombre del Convenio (Fonasa / Isapre / GES)</label>
                <input type="text" name="nombre_convenio" required placeholder="Ej: Isapre Vida Tres"
                       class="w-full px-3 py-2 rounded-lg border border-slate-300">
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-1">Porcentaje de Cobertura / Descuento (%)</label>
                <input type="number" name="porcentaje_descuento" min="0" max="100" step="1" value="35" required
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
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
    <div class="bg-white rounded-2xl max-w-md w-full border border-slate-200 shadow-xl overflow-hidden">
        <div class="bg-[#0F172A] text-white px-5 py-4 flex items-center justify-between">
            <h3 class="font-bold text-sm">Editar Convenio de Salud</h3>
            <button type="button" onclick="document.getElementById('modal-editar-convenio').classList.add('hidden')" class="text-white">✕</button>
        </div>
        <form id="form-editar-convenio" method="POST" class="p-5 space-y-4 text-xs">
            @csrf
            @method('PUT')
            <div>
                <label class="block font-bold text-slate-700 mb-1">Nombre del Convenio</label>
                <input type="text" id="edit-nombre-convenio" name="nombre_convenio" required
                       class="w-full px-3 py-2 rounded-lg border border-slate-300">
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-1">Descuento (%)</label>
                <input type="number" id="edit-pct-convenio" name="porcentaje_descuento" min="0" max="100" step="1" required
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('modal-editar-convenio').classList.add('hidden')"
                        class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-[#1E40AF] text-white font-bold">Actualizar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditConvenio(id, nombre, pct) {
        document.getElementById('form-editar-convenio').action = '/convenios/' + id;
        document.getElementById('edit-nombre-convenio').value = nombre;
        document.getElementById('edit-pct-convenio').value = pct;
        document.getElementById('modal-editar-convenio').classList.remove('hidden');
    }
</script>
@endsection
