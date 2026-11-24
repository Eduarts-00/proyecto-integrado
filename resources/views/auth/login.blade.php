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

            <!-- Selección Rápida de Operador Designado (Protegida con PIN de 4 dígitos) -->
            <div class="mt-8 pt-6 border-t border-slate-200">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                        Cambio Rápido de Operador (PIN 4 Dígitos)
                    </p>
                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-500 font-mono text-[10px]">Terminal POS</span>
                </div>

                @php
                    $colorMap = [
                        'vendedor' => 'border-blue-200 bg-blue-50/60 hover:bg-blue-100/80 text-blue-700',
                        'quimico_farmaceutico' => 'border-emerald-200 bg-emerald-50/60 hover:bg-emerald-100/80 text-emerald-700',
                        'bodeguero' => 'border-amber-200 bg-amber-50/60 hover:bg-amber-100/80 text-amber-800',
                        'administrador' => 'border-purple-200 bg-purple-50/60 hover:bg-purple-100/80 text-purple-700',
                    ];
                @endphp

                <div class="space-y-2">
                    @forelse($quickAccessUsers ?? [] as $qUser)
                        <button type="button"
                                onclick="openLoginPinModal({{ json_encode([
                                    'id_usuario' => $qUser->id_usuario,
                                    'nombre_completo' => $qUser->nombre_completo,
                                    'rut' => $qUser->rut,
                                    'role_label' => $qUser->role_label,
                                    'action_url' => route('login.demo', $qUser->id_usuario),
                                ]) }})"
                                class="w-full text-left px-3.5 py-2.5 rounded-lg border text-xs font-semibold flex items-center justify-between transition {{ $colorMap[$qUser->role] ?? 'border-slate-200 bg-slate-50 text-slate-700' }}">
                            <span>{{ $qUser->role_label }} — {{ $qUser->nombre_completo }}</span>
                            <span class="text-[10px] font-normal opacity-80">PIN 4 dígitos →</span>
                        </button>
                    @empty
                        <p class="text-xs text-slate-400 text-center py-2">No hay operadores designados en acceso rápido.</p>
                    @endforelse
                </div>
            </div>

            <p class="mt-6 text-center text-[11px] text-slate-400">
                FarmaNet Pro © 2026 • Reg. ISP #4421 • v2.4.1
            </p>
        </div>
    </div>

    <!-- Modal de Verificación de PIN de 4 dígitos en Login -->
    <div id="modal-login-pin" class="hidden fixed inset-0 z-50 bg-slate-900/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-sm w-full border border-slate-200 shadow-2xl overflow-hidden">
            <div class="bg-[#0F172A] text-white px-5 py-4 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-sm">Acceso Rápido de Operador</h3>
                    <p class="text-[11px] text-slate-400">Ingrese su PIN de 4 dígitos</p>
                </div>
                <button type="button" onclick="document.getElementById('modal-login-pin').classList.add('hidden')" class="text-slate-400 hover:text-white font-bold">✕</button>
            </div>
            <form id="form-login-pin" method="POST" class="p-5 space-y-4 text-xs">
                @csrf
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center gap-3">
                    <div id="login-pin-avatar" class="w-10 h-10 rounded-full bg-[#1E40AF] text-white font-extrabold text-sm flex items-center justify-center uppercase shrink-0">
                        U
                    </div>
                    <div class="min-w-0">
                        <div id="login-pin-nombre" class="font-bold text-slate-900 text-sm truncate">Funcionario</div>
                        <div class="text-[11px] text-slate-500">
                            <span id="login-pin-rol" class="font-semibold text-[#1E40AF]">Cargo</span> •
                            <span id="login-pin-rut" class="font-mono">RUN</span>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="input-login-pin-acceso" class="block font-bold text-slate-700 mb-1.5 text-center">
                        PIN de Seguridad (4 dígitos)
                    </label>
                    <input type="password"
                           id="input-login-pin-acceso"
                           name="pin_acceso"
                           inputmode="numeric"
                           minlength="4"
                           maxlength="4"
                           pattern="^[0-9]{4}$"
                           required
                           autocomplete="off"
                           placeholder="••••"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4)"
                           class="w-full px-4 py-3 rounded-xl border-2 border-slate-300 focus:border-[#1E40AF] font-mono font-extrabold text-xl tracking-[0.5em] text-center text-slate-900 outline-none">
                    <p class="text-[10px] text-slate-400 text-center mt-1.5">
                        PIN inicial por defecto: <strong class="font-mono text-slate-600">1234</strong>
                    </p>
                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <button type="button"
                            onclick="document.getElementById('modal-login-pin').classList.add('hidden')"
                            class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600 font-semibold">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg bg-[#1E40AF] hover:bg-blue-800 text-white font-bold shadow-sm transition">
                        Ingresar con PIN
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openLoginPinModal(user) {
            const modal = document.getElementById('modal-login-pin');
            const form = document.getElementById('form-login-pin');
            const pinInput = document.getElementById('input-login-pin-acceso');
            if (!modal || !form) return;

            form.action = user.action_url;
            document.getElementById('login-pin-nombre').textContent = user.nombre_completo || '';
            document.getElementById('login-pin-rol').textContent = user.role_label || '';
            document.getElementById('login-pin-rut').textContent = user.rut || '';
            document.getElementById('login-pin-avatar').textContent = (user.nombre_completo || 'U').charAt(0).toUpperCase();

            if (pinInput) {
                pinInput.value = '';
            }
            modal.classList.remove('hidden');
            setTimeout(() => {
                if (pinInput) pinInput.focus();
            }, 60);
        }
    </script>
</body>
</html>
