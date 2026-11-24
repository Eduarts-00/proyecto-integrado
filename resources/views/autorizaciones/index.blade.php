@extends('layouts.app')

@section('title', 'Autorización de Recetas Retenidas — FarmaNet Pro')

@section('content')
<div class="space-y-6">

    <!-- Cabecera -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Autorización de Recetas Retenidas</h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Registro exigido por el ISP — Instituto de Salud Pública
            </p>
        </div>

        <div class="flex items-center gap-3">
            <div class="px-5 py-2.5 rounded-xl bg-amber-50 border border-amber-200 text-center min-w-[100px]">
                <div class="text-xl font-extrabold text-amber-600">{{ $pendientes->count() }}</div>
                <div class="text-[10px] font-bold uppercase text-amber-700">Pendientes</div>
            </div>
            <div class="px-5 py-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-center min-w-[115px]">
                <div class="text-xl font-extrabold text-emerald-600">{{ $autorizadasHoyCount }}</div>
                <div class="text-[10px] font-bold uppercase text-emerald-700">Autorizadas hoy</div>
            </div>
        </div>
    </div>

    <!-- Grilla Principal: Cola de Validaciones (Izquierda) + Formulario de Captura ISP (Derecha) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- COLA DE VALIDACIONES -->
        <div class="lg:col-span-5 space-y-3">
            <h2 class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                Cola de Validaciones ({{ $pendientes->count() }})
            </h2>

            @forelse($pendientes as $item)
                @php
                    $isSelected = $selectedValidation && $selectedValidation->id_registro === $item->id_registro;
                @endphp
                <a href="{{ route('autorizaciones.index', ['id_registro' => $item->id_registro]) }}"
                   class="block p-4 rounded-xl bg-white border-2 transition shadow-sm {{ $isSelected ? 'border-[#1E40AF] ring-2 ring-blue-500/15' : 'border-slate-200 hover:border-red-300' }}">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-red-100 text-[#EF4444] border border-red-200">
                                ▲ CTL
                            </span>
                            <span class="font-bold text-sm text-slate-900">
                                {{ $item->medicamento?->nombre_comercial }}
                            </span>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 border border-amber-200">
                            ⏳ Pendiente
                        </span>
                    </div>
                    <div class="text-xs text-slate-500 space-y-0.5">
                        <div>Vendedor: <strong class="text-slate-700">{{ $item->vendedor?->nombre_completo ?? 'Ana Torres' }}</strong></div>
                        <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1">
                            <span>Hora: {{ $item->created_at?->format('H:i') ?? '09:14' }}</span>
                            <span class="font-mono-code text-slate-600">Folio: {{ $item->folio_receta }}</span>
                        </div>
                    </div>
                </a>
            @empty
                <div class="p-8 rounded-xl bg-white border border-slate-200 text-center">
                    <div class="text-2xl mb-1">✅</div>
                    <p class="text-xs font-semibold text-slate-600">No hay recetas retenidas pendientes en cola.</p>
                </div>
            @endforelse
        </div>

        <!-- PANEL DE FORMULARIO DE CAPTURA OBLIGATORIA ISP -->
        <div class="lg:col-span-7">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 min-h-[340px] flex flex-col justify-center">
                @if(!$selectedValidation)
                    <div class="text-center py-12">
                        <div class="text-4xl mb-3">⚖️</div>
                        <p class="text-xs font-medium text-slate-400">
                            Selecciona una validación pendiente<br>para completar el formulario ISP
                        </p>
                    </div>
                @else
                    <div class="border-b border-slate-200 pb-4 mb-5 flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-red-100 text-[#EF4444]">▲ CONTROLADO</span>
                                <h3 class="text-base font-extrabold text-slate-900">
                                    {{ $selectedValidation->medicamento?->nombre_comercial }}
                                </h3>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">
                                Solicitado por <strong>{{ $selectedValidation->vendedor?->nombre_completo }}</strong> • Lote asignado:
                                <span class="font-mono-code font-semibold text-blue-700">{{ $selectedValidation->lote?->lote_codigo ?? 'LOT-2024-005' }}</span>
                            </p>
                        </div>
                        <div class="text-right">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Precio Unitario</div>
                            <div class="text-lg font-extrabold font-mono-code text-slate-900">
                                ${{ number_format($selectedValidation->medicamento?->precio_unitario ?? 0, 0, ',', '.') }}
                            </div>
                        </div>
                    </div>

                    @if(auth()->user()->role === 'quimico_farmaceutico')
                        <form action="{{ route('autorizaciones.approve', $selectedValidation->id_registro) }}" method="POST" class="space-y-4 text-xs">
                            @csrf
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">RUN del Paciente</label>
                                    <input type="text" name="rut_paciente" value="{{ $selectedValidation->rut_paciente }}" required
                                           minlength="8" maxlength="12"
                                           class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 font-mono-code focus:border-[#1E40AF] outline-none">
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">RUN del Médico Prescriptor</label>
                                    <input type="text" name="rut_medico" value="{{ $selectedValidation->rut_medico }}" required
                                           minlength="8" maxlength="12"
                                           class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 font-mono-code focus:border-[#1E40AF] outline-none">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">Folio de Receta Retenida</label>
                                    <input type="text" name="folio_receta" value="{{ $selectedValidation->folio_receta }}" required
                                           minlength="4" maxlength="25"
                                           class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 font-mono-code focus:border-[#1E40AF] outline-none">
                                </div>
                                <div>
                                    <label class="block font-bold text-[#EF4444] mb-1">PIN de Autorización Q.F.</label>
                                    <input type="password" name="pin_qf" required
                                           minlength="4" maxlength="6"
                                           placeholder="••••"
                                           class="w-full px-3.5 py-2.5 rounded-lg border-2 border-red-300 bg-red-50/40 font-mono-code font-bold text-center focus:border-[#EF4444] outline-none">
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                                <button type="submit"
                                        class="px-5 py-2.5 rounded-xl bg-[#10B981] hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition">
                                    ✓ Autorizar Receta y Emitir Boleta
                                </button>
                            </div>
                        </form>

                        <form action="{{ route('autorizaciones.reject', $selectedValidation->id_registro) }}" method="POST" class="mt-4 pt-3 border-t border-slate-100 flex items-center gap-2 text-xs">
                            @csrf
                            <input type="text" name="motivo_rechazo" required placeholder="Motivo de rechazo..."
                                   minlength="4" maxlength="120"
                                   class="flex-1 px-3 py-2 rounded-lg border border-slate-200 text-xs">
                            <button type="submit" class="px-3.5 py-2 rounded-lg bg-red-50 hover:bg-red-100 text-[#EF4444] font-bold">
                                ✕ Rechazar Receta
                            </button>
                        </form>
                    @else
                        <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs">
                            👁️ <strong>Modo Consulta:</strong> Solo el <strong>Químico Farmacéutico</strong> titular o de turno está facultado para autorizar la dispensación de recetas retenidas.
                        </div>
                    @endif
                @endif
            </div>
        </div>

    </div>

    <!-- Libro Oficial Digital de Control ISP -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/60">
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">
                    📖 Libro Oficial de Control de Estupefacientes y Psicotrópicos (ISP)
                </h3>
                <p class="text-[11px] text-slate-400">Registro histórico de dispensación de medicamentos sometidos a control sanitario</p>
            </div>
            <span class="px-2.5 py-1 rounded bg-slate-200 text-slate-700 font-mono-code text-[10px] font-bold">
                DECRETO SUPREMO N° 404
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 text-[11px] font-bold uppercase text-slate-400 bg-slate-50/40">
                        <th class="py-3 px-4">Folio Receta</th>
                        <th class="py-3 px-4">Medicamento / Lote</th>
                        <th class="py-3 px-4">RUN Paciente</th>
                        <th class="py-3 px-4">RUN Médico</th>
                        <th class="py-3 px-4">Q.F. Autorizador</th>
                        <th class="py-3 px-4">Estado</th>
                        <th class="py-3 px-4">Sello de Verificación</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($historialIsp as $acta)
                        <tr class="hover:bg-slate-50/80">
                            <td class="py-3 px-4 font-mono-code font-bold text-slate-900">{{ $acta->folio_receta }}</td>
                            <td class="py-3 px-4">
                                <div class="font-semibold text-slate-800">{{ $acta->medicamento?->nombre_comercial }}</div>
                                <div class="text-[10px] font-mono-code text-slate-400">{{ $acta->lote?->lote_codigo ?? 'LOT-FEFO' }}</div>
                            </td>
                            <td class="py-3 px-4 font-mono-code text-slate-600">{{ $acta->rut_paciente }}</td>
                            <td class="py-3 px-4 font-mono-code text-slate-600">{{ $acta->rut_medico }}</td>
                            <td class="py-3 px-4 text-slate-700">{{ $acta->quimicoAutorizador?->nombre_completo ?? 'Dr. Carlos Vega' }}</td>
                            <td class="py-3 px-4">
                                @if($acta->estado === 'AUTORIZADO')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                        ✓ AUTORIZADO
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700">
                                        ✕ RECHAZADO
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 font-mono-code text-[10px] text-slate-500">
                                @if($acta->hash_integridad)
                                    <span title="{{ $acta->hash_integridad }}" class="px-2 py-0.5 rounded bg-slate-100 border border-slate-200">
                                        {{ $acta->qf_signature ?? substr($acta->hash_integridad, 0, 16) }}
                                    </span>
                                @else
                                    <span class="text-slate-400">{{ $acta->motivo_rechazo ?? '—' }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400 text-xs">
                                Aún no se registran autorizaciones en esta jornada.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
