<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — FarmaNet Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen flex flex-col lg:flex-row bg-[#F8FAFC] text-[#0F172A]">

    <!-- Panel Izquierdo Institucional -->
    <div class="lg:w-7/12 bg-gradient-to-br from-[#0F172A] via-[#172554] to-[#1E40AF] text-white p-10 lg:p-16 flex flex-col justify-center items-center text-center relative overflow-hidden">
        <div class="max-w-lg z-10">
            <div class="w-16 h-16 rounded-2xl bg-blue-500/20 border border-blue-400/30 flex items-center justify-center mx-auto mb-6 text-2xl font-extrabold text-blue-200 shadow-lg">
                Rx
            </div>
            <h1 class="text-3xl lg:text-4xl font-extrabold tracking-tight mb-3">FarmaNet Pro</h1>
            <p class="text-blue-200/90 text-sm lg:text-base leading-relaxed mb-10">
                Sistema de Gestión Integral para Farmacia Privada. Dispensación en mostrador, control de inventario por vencimiento y registro sanitario ISP.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-left">
                <div class="p-3.5 rounded-xl bg-white/5 border border-white/10 flex items-center gap-3">
                    <span class="text-lg">🔒</span>
                    <div>
                        <div class="text-xs font-bold text-white">Control por Perfil</div>
                        <div class="text-[11px] text-blue-200/70">Accesos según cargo clínico</div>
                    </div>
                </div>
                <div class="p-3.5 rounded-xl bg-white/5 border border-white/10 flex items-center gap-3">
                    <span class="text-lg">📋</span>
                    <div>
                        <div class="text-xs font-bold text-white">Recetas Retenidas</div>
                        <div class="text-[11px] text-blue-200/70">Autorización Q.F. y Libro ISP</div>
                    </div>
                </div>
                <div class="p-3.5 rounded-xl bg-white/5 border border-white/10 flex items-center gap-3">
                    <span class="text-lg">📦</span>
                    <div>
                        <div class="text-xs font-bold text-white">Inventario FEFO</div>
                        <div class="text-[11px] text-blue-200/70">Control de lotes y vencimientos</div>
                    </div>
                </div>
                <div class="p-3.5 rounded-xl bg-white/5 border border-white/10 flex items-center gap-3">
                    <span class="text-lg">📊</span>
                    <div>
                        <div class="text-xs font-bold text-white">Dashboard Gerencial</div>
                        <div class="text-[11px] text-blue-200/70">Indicadores y reportes del día</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel Derecho: Credenciales -->
    <div class="lg:w-5/12 bg-white p-8 lg:p-14 flex flex-col justify-center">
        <div class="max-w-md w-full mx-auto">
            <h2 class="text-2xl font-bold text-slate-900">Iniciar Sesión</h2>
            <p class="text-xs text-slate-500 mt-1 mb-6">Ingresa tus credenciales institucionales</p>

            @if($errors->any())
                <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs font-medium">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('login.submit') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">RUT Profesional</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 text-xs">👤</span>
                        <input type="text" name="rut" value="{{ old('rut', '12.345.678-5') }}" required
                               minlength="3" maxlength="80"
                               placeholder="12.345.678-5"
                               class="w-full pl-9 pr-3.5 py-2.5 text-sm rounded-lg border border-slate-300 focus:border-[#1E40AF] focus:ring-2 focus:ring-blue-500/20 outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Contraseña</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 text-xs">🔑</span>
                        <input type="password" name="password" value="Password123!" required
                               minlength="4" maxlength="32"
                               class="w-full pl-9 pr-3.5 py-2.5 text-sm rounded-lg border border-slate-300 focus:border-[#1E40AF] focus:ring-2 focus:ring-blue-500/20 outline-none">
                    </div>
                </div>

                <button type="submit"
                        class="w-full py-2.5 px-4 rounded-lg bg-[#1E40AF] hover:bg-blue-800 text-white font-semibold text-sm shadow-sm transition">
                    Ingresar al Sistema
                </button>
            </form>

            <!-- Selección Rápida por Rol -->
            <div class="mt-8 pt-6 border-t border-slate-200">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-3">
                    Acceso Rápido por Perfil
                </p>

                <div class="space-y-2">
                    <form action="{{ route('login.demo', 'vendedor') }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full text-left px-3.5 py-2.5 rounded-lg border border-blue-200 bg-blue-50/60 hover:bg-blue-100/80 text-blue-700 text-xs font-semibold flex items-center justify-between transition">
                            <span>Vendedor — Ana Torres</span>
                            <span class="text-[10px] font-normal text-blue-500">Ingresar →</span>
                        </button>
                    </form>

                    <form action="{{ route('login.demo', 'quimico_farmaceutico') }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full text-left px-3.5 py-2.5 rounded-lg border border-emerald-200 bg-emerald-50/60 hover:bg-emerald-100/80 text-emerald-700 text-xs font-semibold flex items-center justify-between transition">
                            <span>Q. Farmacéutico — Dr. Carlos Vega</span>
                            <span class="text-[10px] font-normal text-emerald-600">Ingresar →</span>
                        </button>
                    </form>

                    <form action="{{ route('login.demo', 'bodeguero') }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full text-left px-3.5 py-2.5 rounded-lg border border-amber-200 bg-amber-50/60 hover:bg-amber-100/80 text-amber-800 text-xs font-semibold flex items-center justify-between transition">
                            <span>Bodeguero — Luis Pinto</span>
                            <span class="text-[10px] font-normal text-amber-600">Ingresar →</span>
                        </button>
                    </form>

                    <form action="{{ route('login.demo', 'administrador') }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full text-left px-3.5 py-2.5 rounded-lg border border-purple-200 bg-purple-50/60 hover:bg-purple-100/80 text-purple-700 text-xs font-semibold flex items-center justify-between transition">
                            <span>Administrador — María Salinas</span>
                            <span class="text-[10px] font-normal text-purple-600">Ingresar →</span>
                        </button>
                    </form>
                </div>
            </div>

            <p class="mt-6 text-center text-[11px] text-slate-400">
                FarmaNet Pro © 2026 • Reg. ISP #4421 • v2.4.1
            </p>
        </div>
    </div>

</body>
</html>
