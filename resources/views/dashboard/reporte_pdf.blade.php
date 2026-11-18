<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Oficial de Farmacia Privada (ISP & SII) — FarmaNet Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { padding: 0 !important; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body class="bg-white text-slate-900 p-8 max-w-6xl mx-auto">

    @php
        $tipo = $tipoReporte ?? 'integral';
        $titulosReporte = [
            'integral' => 'FarmaNet Pro — Reporte Integral de Gestión y Fiscalización',
            'ventas' => 'FarmaNet Pro — Libro de Ventas, Boletas y Arqueo Tributario SII (IVA 19%)',
            'inventario' => 'FarmaNet Pro — Valorización de Inventario, Catálogo y Control de Lotes FEFO',
            'isp' => 'FarmaNet Pro — Libro Oficial de Control ISP (Recetas Retenidas D.S. N° 404/405)',
            'convenios' => 'FarmaNet Pro — Liquidación de Convenios de Salud y Copagos',
        ];
        $tituloPrincipal = $titulosReporte[$tipo] ?? $titulosReporte['integral'];
    @endphp

    <!-- Barra de acciones (No se imprime) -->
    <div class="no-print mb-6 flex flex-wrap justify-between items-center gap-3 bg-slate-100 p-4 rounded-xl border border-slate-300">
        <div>
            <span class="text-xs font-bold text-slate-800 block">Vista Oficial de Impresión / Exportación PDF — FarmaNet Pro</span>
            <span class="text-[11px] text-slate-500">
                Período: <strong>{{ $periodoLabel ?? 'Consolidado Histórico General' }}</strong> • Ámbito: <strong class="uppercase">{{ $tipo }}</strong>
            </span>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('dashboard.export.excel', request()->query()) }}" class="px-4 py-2 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold">
                Descargar Excel (.xlsx) de este Reporte
            </a>
            <button onclick="window.print()" class="px-4 py-2 rounded-lg bg-[#1E40AF] hover:bg-blue-900 text-white text-xs font-bold">
                Imprimir / Guardar PDF
            </button>
            <a href="{{ route('dashboard.index') }}" class="px-4 py-2 rounded-lg bg-white border border-slate-300 text-xs font-semibold text-slate-700">
                Volver al Dashboard
            </a>
        </div>
    </div>

    <!-- Encabezado Institucional -->
    <div class="border-b-2 border-[#1E40AF] pb-4 mb-6 flex justify-between items-end">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-widest text-[#1E40AF]">Establecimiento Farmacéutico Privado • Normativa MINSAL / ISP / SII</div>
            <h1 class="text-2xl font-extrabold text-[#0F172A] mt-0.5">{{ $tituloPrincipal }}</h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Resolución Sanitaria ISP #4421 • D.S. N° 466 / D.S. N° 404 • Período: <strong>{{ $periodoLabel ?? 'Consolidado General' }}</strong> • Emitido: {{ now()->format('d/m/Y H:i:s') }}
            </p>
        </div>
        <div class="text-right text-xs text-slate-600 bg-slate-50 px-3.5 py-2 rounded-lg border border-slate-200">
            <div>Emitido por: <strong>{{ auth()->user()->nombre_completo }}</strong></div>
            <div>RUN: <span class="font-mono">{{ auth()->user()->rut }}</span></div>
            <div>Cargo: <span class="uppercase font-semibold text-[#1E40AF]">{{ str_replace('_', ' ', auth()->user()->rol?->nombre_rol ?? 'Administrador') }}</span></div>
        </div>
    </div>

    @if(in_array($tipo, ['integral', 'ventas'], true))
        <!-- Resumen Ejecutivo Financiero y Tributario (SII) -->
        <div class="mb-6">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2.5">Resumen Financiero y Tributario SII (Arqueo de Caja)</h2>
            <div class="grid grid-cols-5 gap-3">
                <div class="p-3 rounded-lg border border-slate-200 bg-slate-50">
                    <div class="text-[10px] font-bold uppercase text-slate-500">Boletas Emitidas</div>
                    <div class="text-lg font-extrabold text-slate-900 font-mono mt-0.5">{{ $resumenFinanciero['boletas_count'] }}</div>
                </div>
                <div class="p-3 rounded-lg border border-slate-200 bg-slate-50">
                    <div class="text-[10px] font-bold uppercase text-slate-500">Monto Neto Afecto</div>
                    <div class="text-lg font-extrabold text-slate-900 font-mono mt-0.5">${{ number_format($resumenFinanciero['subtotal_neto'], 0, ',', '.') }}</div>
                </div>
                <div class="p-3 rounded-lg border border-slate-200 bg-slate-50">
                    <div class="text-[10px] font-bold uppercase text-slate-500">IVA Débito (19% SII)</div>
                    <div class="text-lg font-extrabold text-slate-900 font-mono mt-0.5">${{ number_format($resumenFinanciero['iva_total'], 0, ',', '.') }}</div>
                </div>
                <div class="p-3 rounded-lg border border-emerald-200 bg-emerald-50/50">
                    <div class="text-[10px] font-bold uppercase text-emerald-700">Bonif. Convenios</div>
                    <div class="text-lg font-extrabold text-emerald-700 font-mono mt-0.5">-${{ number_format($resumenFinanciero['descuentos_total'], 0, ',', '.') }}</div>
                </div>
                <div class="p-3 rounded-lg border border-blue-200 bg-blue-50/60">
                    <div class="text-[10px] font-bold uppercase text-[#1E40AF]">Total Recaudado Caja</div>
                    <div class="text-lg font-extrabold text-[#1E40AF] font-mono mt-0.5">${{ number_format($resumenFinanciero['recaudacion_total'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>

        <!-- 1. Libro de Ventas y Boletas Emitidas -->
        <h2 class="text-xs font-bold uppercase tracking-wider text-[#1E40AF] mb-2">1. Libro de Ventas POS, Detalle de Dispensación y Desglose IVA (19%)</h2>
        <table class="w-full text-left border border-slate-300 text-[11px] mb-6">
            <thead class="bg-slate-100 text-slate-700">
                <tr>
                    <th class="p-2 border">Boleta</th>
                    <th class="p-2 border">Fecha / Hora</th>
                    <th class="p-2 border">Vendedor</th>
                    <th class="p-2 border">Medicamentos y Lotes Dispensados</th>
                    <th class="p-2 border">Convenio Aplicado</th>
                    <th class="p-2 border text-right">Neto</th>
                    <th class="p-2 border text-right">IVA 19%</th>
                    <th class="p-2 border text-right">Descuento</th>
                    <th class="p-2 border text-right">Total Pagado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ventas as $v)
                    @php
                        $detalleItems = $v->detalles->map(function($d) {
                            $med = $d->medicamento?->nombre_comercial ?? 'Producto';
                            $lote = $d->lote?->lote_codigo ? " ({$d->lote->lote_codigo})" : '';
                            return "{$d->cantidad}x {$med}{$lote}";
                        })->implode(', ');
                    @endphp
                    <tr>
                        <td class="p-1.5 border font-mono font-bold">#{{ $v->id_venta }}</td>
                        <td class="p-1.5 border whitespace-nowrap">{{ $v->fecha_venta?->format('d-m-Y H:i') }}</td>
                        <td class="p-1.5 border">{{ $v->usuario?->nombre_completo ?? 'Mesón' }}</td>
                        <td class="p-1.5 border text-[10px] text-slate-600">{{ $detalleItems ?: 'Dispensación en mesón' }}</td>
                        <td class="p-1.5 border">{{ $v->convenio?->nombre_convenio ?? 'Particular' }}</td>
                        <td class="p-1.5 border text-right font-mono">${{ number_format($v->subtotal, 0, ',', '.') }}</td>
                        <td class="p-1.5 border text-right font-mono">${{ number_format($v->iva, 0, ',', '.') }}</td>
                        <td class="p-1.5 border text-right font-mono text-emerald-700">-${{ number_format($v->descuento_total, 0, ',', '.') }}</td>
                        <td class="p-1.5 border text-right font-mono font-bold">${{ number_format($v->total, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="p-4 border text-center text-slate-400">No se registran boletas en el período seleccionado.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="bg-slate-100 font-bold">
                <tr>
                    <td colspan="5" class="p-2 border text-right uppercase text-[10px]">Totales Consolidados de Caja:</td>
                    <td class="p-2 border text-right font-mono">${{ number_format($resumenFinanciero['subtotal_neto'], 0, ',', '.') }}</td>
                    <td class="p-2 border text-right font-mono">${{ number_format($resumenFinanciero['iva_total'], 0, ',', '.') }}</td>
                    <td class="p-2 border text-right font-mono text-emerald-700">-${{ number_format($resumenFinanciero['descuentos_total'], 0, ',', '.') }}</td>
                    <td class="p-2 border text-right font-mono text-[#1E40AF]">${{ number_format($resumenFinanciero['recaudacion_total'], 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    @if(in_array($tipo, ['integral', 'convenios'], true))
        <!-- 2. Liquidación de Convenios de Salud -->
        <h2 class="text-xs font-bold uppercase tracking-wider text-[#1E40AF] mb-2">2. Liquidación de Convenios de Salud y Vigencias Contractuales</h2>
        <table class="w-full text-left border border-slate-300 text-[11px] mb-6">
            <thead class="bg-slate-100 text-slate-700">
                <tr>
                    <th class="p-2 border">Convenio / Institución</th>
                    <th class="p-2 border text-center">% Cobertura</th>
                    <th class="p-2 border text-center">Vigencia Desde</th>
                    <th class="p-2 border text-center">Vigencia Hasta</th>
                    <th class="p-2 border text-center">Estado</th>
                    <th class="p-2 border text-center">Ventas</th>
                    <th class="p-2 border text-right">Bonificaciones Otorgadas</th>
                    <th class="p-2 border text-right">Recaudado Copagos</th>
                </tr>
            </thead>
            <tbody>
                @foreach($convenios as $c)
                    <tr>
                        <td class="p-1.5 border font-semibold">{{ $c->nombre_convenio }}</td>
                        <td class="p-1.5 border text-center font-mono font-bold">{{ number_format($c->porcentaje_descuento, 0) }}%</td>
                        <td class="p-1.5 border text-center font-mono">{{ $c->fecha_inicio ? $c->fecha_inicio->format('d-m-Y') : 'Permanente' }}</td>
                        <td class="p-1.5 border text-center font-mono">{{ $c->fecha_fin ? $c->fecha_fin->format('d-m-Y') : 'Indefinido' }}</td>
                        <td class="p-1.5 border text-center font-bold text-[10px]">{{ $c->estado_vigencia }}</td>
                        <td class="p-1.5 border text-center font-mono">{{ $c->ventas_count }}</td>
                        <td class="p-1.5 border text-right font-mono text-emerald-700">-${{ number_format($c->total_descuentos ?? 0, 0, ',', '.') }}</td>
                        <td class="p-1.5 border text-right font-mono font-bold">${{ number_format($c->total_recaudado ?? 0, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(in_array($tipo, ['integral', 'inventario'], true))
        <!-- 3. Catálogo e Inventario por Lotes FEFO -->
        <h2 class="text-xs font-bold uppercase tracking-wider text-[#1E40AF] mb-2">3. Catálogo Farmacéutico, Lotes FEFO y Control de Caducidad</h2>
        <table class="w-full text-left border border-slate-300 text-[11px] mb-6">
            <thead class="bg-slate-100 text-slate-700">
                <tr>
                    <th class="p-2 border">Cód. Barras</th>
                    <th class="p-2 border">Medicamento</th>
                    <th class="p-2 border">Condición ISP</th>
                    <th class="p-2 border">Lote</th>
                    <th class="p-2 border text-center">Ubicación</th>
                    <th class="p-2 border text-right">Stock</th>
                    <th class="p-2 border text-right">Valorización</th>
                    <th class="p-2 border text-center">Vencimiento</th>
                    <th class="p-2 border text-center">Estado FEFO</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lotesInventario as $lote)
                    @php
                        $med = $lote->medicamento;
                        $valorLote = (int) round(($med?->precio_unitario ?? 0) * $lote->cantidad);
                        $dias = $lote->dias_restantes;
                    @endphp
                    <tr>
                        <td class="p-1.5 border font-mono text-[10px]">{{ $med?->codigo_barras }}</td>
                        <td class="p-1.5 border font-semibold">{{ $med?->nombre_comercial }}</td>
                        <td class="p-1.5 border text-[10px]">
                            {{ ($med?->requiere_receta || $med?->tipo_controlado) ? 'Receta Retenida ISP' : 'Venta Directa' }}
                        </td>
                        <td class="p-1.5 border font-mono">{{ $lote->lote_codigo }}</td>
                        <td class="p-1.5 border text-center">{{ $lote->ubicacion }}</td>
                        <td class="p-1.5 border text-right font-mono font-bold">{{ $lote->cantidad }} u.</td>
                        <td class="p-1.5 border text-right font-mono">${{ number_format($valorLote, 0, ',', '.') }}</td>
                        <td class="p-1.5 border text-center font-mono">{{ $lote->fecha_vencimiento?->format('d-m-Y') }}</td>
                        <td class="p-1.5 border text-center font-bold text-[10px]">
                            @if(!$lote->activo)
                                DADO DE BAJA
                            @elseif($dias <= 0)
                                VENCIDO ({{ $dias }}d)
                            @elseif($dias <= 30)
                                POR VENCER ({{ $dias }}d)
                            @else
                                VIGENTE ({{ $dias }}d)
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(in_array($tipo, ['integral', 'isp'], true))
        <!-- 4. Libro Oficial de Control ISP -->
        <h2 class="text-xs font-bold uppercase tracking-wider text-[#1E40AF] mb-2">4. Libro Oficial de Control ISP — Psicotrópicos y Estupefacientes (D.S. N° 404/405)</h2>
        <table class="w-full text-left border border-slate-300 text-[11px] mb-10">
            <thead class="bg-slate-100 text-slate-700">
                <tr>
                    <th class="p-2 border">Folio Receta</th>
                    <th class="p-2 border">Fecha / Hora</th>
                    <th class="p-2 border">Medicamento Controlado</th>
                    <th class="p-2 border">Lote</th>
                    <th class="p-2 border text-center">Cant.</th>
                    <th class="p-2 border">RUN Paciente</th>
                    <th class="p-2 border">RUN Médico</th>
                    <th class="p-2 border">Q.F. Autorizador</th>
                    <th class="p-2 border">Estado</th>
                    <th class="p-2 border">Respaldo / Sello SHA-256</th>
                </tr>
            </thead>
            <tbody>
                @forelse($registrosIsp as $r)
                    <tr>
                        <td class="p-1.5 border font-mono font-bold">{{ $r->folio_receta }}</td>
                        <td class="p-1.5 border whitespace-nowrap">{{ ($r->fecha_autorizacion ?? $r->created_at)?->format('d-m-Y H:i') }}</td>
                        <td class="p-1.5 border font-semibold">{{ $r->medicamento?->nombre_comercial }}</td>
                        <td class="p-1.5 border font-mono">{{ $r->lote?->lote_codigo ?? 'S/L' }}</td>
                        <td class="p-1.5 border text-center font-mono font-bold">{{ $r->cantidad }}</td>
                        <td class="p-1.5 border font-mono">{{ $r->rut_paciente }}</td>
                        <td class="p-1.5 border font-mono">{{ $r->rut_medico }}</td>
                        <td class="p-1.5 border">{{ $r->quimicoAutorizador?->nombre_completo ?? 'Sin firma Q.F.' }}</td>
                        <td class="p-1.5 border font-bold text-[10px]">{{ $r->estado }}</td>
                        <td class="p-1.5 border font-mono text-[10px]">
                            @if($r->estado === 'RECHAZADO')
                                {{ $r->motivo_rechazo ?? 'Rechazo sanitario' }}
                            @else
                                {{ $r->hash_integridad ? substr($r->hash_integridad, 0, 22) . '...' : 'Verificado' }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="p-4 border text-center text-slate-400">Sin registros ISP en el período seleccionado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif

    <!-- Pie de Firmas Reglamentarias -->
    <div class="grid grid-cols-2 gap-12 pt-8 border-t border-slate-300 text-center text-xs text-slate-600">
        <div>
            <div class="border-b border-slate-400 w-64 mx-auto mb-2 h-10"></div>
            <div class="font-bold text-slate-900">Firma y Timbre Director Técnico (Q.F.)</div>
            <div class="text-[11px] text-slate-500">Responsable Sanitario D.S. N° 466 / ISP de Chile</div>
        </div>
        <div>
            <div class="border-b border-slate-400 w-64 mx-auto mb-2 h-10"></div>
            <div class="font-bold text-slate-900">Firma Administración y Control de Caja</div>
            <div class="text-[11px] text-slate-500">Respaldo Tributario SII y Liquidación de Convenios</div>
        </div>
    </div>

</body>
</html>
