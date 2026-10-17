<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Consolidado Normativo y Comercial — FarmaNet Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="bg-white text-slate-900 p-8 max-w-5xl mx-auto">

    <div class="no-print mb-6 flex justify-between items-center bg-slate-100 p-4 rounded-xl border border-slate-300">
        <span class="text-xs font-semibold text-slate-700">Vista de Impresión / Exportación PDF Vectorial — FarmaNet Pro</span>
        <div class="flex gap-2">
            <button onclick="window.print()" class="px-4 py-2 rounded-lg bg-[#1E40AF] text-white text-xs font-bold">
                🖨️ Imprimir / Guardar como PDF
            </button>
            <a href="{{ route('dashboard.index') }}" class="px-4 py-2 rounded-lg bg-white border border-slate-300 text-xs font-semibold">
                Volver al Dashboard
            </a>
        </div>
    </div>

    <div class="border-b-2 border-[#1E40AF] pb-4 mb-6 flex justify-between items-end">
        <div>
            <h1 class="text-2xl font-extrabold text-[#0F172A]">FarmaNet Pro — Reporte Consolidado ISP & Comercial</h1>
            <p class="text-xs text-slate-500">Establecimiento Farmacéutico Privado • Reg. ISP #4421 • Generado: {{ now()->format('d/m/Y H:i:s') }}</p>
        </div>
        <div class="text-right text-xs text-slate-500">
            <div>Emitido por: <strong>{{ auth()->user()->nombre_completo }}</strong></div>
            <div>RUN: {{ auth()->user()->rut }}</div>
        </div>
    </div>

    <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700 mb-2">1. Transacciones Comerciales POS</h2>
    <table class="w-full text-left border border-slate-300 text-xs mb-6">
        <thead class="bg-slate-100">
            <tr>
                <th class="p-2 border">ID Boleta</th>
                <th class="p-2 border">Fecha</th>
                <th class="p-2 border">Vendedor</th>
                <th class="p-2 border">Convenio</th>
                <th class="p-2 border text-right">Subtotal</th>
                <th class="p-2 border text-right">Descuento</th>
                <th class="p-2 border text-right">Total Pagado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ventas as $v)
                <tr>
                    <td class="p-2 border font-mono">#{{ $v->id_venta }}</td>
                    <td class="p-2 border">{{ $v->fecha_venta?->format('d-m-Y H:i') }}</td>
                    <td class="p-2 border">{{ $v->usuario?->nombre_completo }}</td>
                    <td class="p-2 border">{{ $v->convenio?->nombre_convenio ?? 'Sin convenio' }}</td>
                    <td class="p-2 border text-right font-mono">${{ number_format($v->subtotal, 0, ',', '.') }}</td>
                    <td class="p-2 border text-right font-mono">-${{ number_format($v->descuento_total, 0, ',', '.') }}</td>
                    <td class="p-2 border text-right font-mono font-bold">${{ number_format($v->total, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700 mb-2">2. Libro Oficial de Control ISP (Recetas Retenidas)</h2>
    <table class="w-full text-left border border-slate-300 text-xs">
        <thead class="bg-slate-100">
            <tr>
                <th class="p-2 border">Folio Receta</th>
                <th class="p-2 border">Medicamento</th>
                <th class="p-2 border">RUN Paciente</th>
                <th class="p-2 border">RUN Médico</th>
                <th class="p-2 border">Estado</th>
                <th class="p-2 border">Hash SHA-256</th>
            </tr>
        </thead>
        <tbody>
            @foreach($registrosIsp as $r)
                <tr>
                    <td class="p-2 border font-mono">{{ $r->folio_receta }}</td>
                    <td class="p-2 border">{{ $r->medicamento?->nombre_comercial }}</td>
                    <td class="p-2 border font-mono">{{ $r->rut_paciente }}</td>
                    <td class="p-2 border font-mono">{{ $r->rut_medico }}</td>
                    <td class="p-2 border font-bold">{{ $r->estado }}</td>
                    <td class="p-2 border font-mono text-[10px]">{{ $r->hash_integridad ? substr($r->hash_integridad, 0, 28) . '...' : 'Pendiente' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>
