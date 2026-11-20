<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'FarmaNet Pro — Sistema de Gestión Farmacéutica')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #F8FAFC; color: #0F172A; }
        .font-mono-code { font-family: 'JetBrains Mono', monospace; }
        @keyframes pulse-fast {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.45; }
        }
        .animate-pulse-badge { animation: pulse-fast 1.6s infinite; }
    </style>
</head>
<body class="min-h-screen flex flex-col lg:flex-row bg-[#F8FAFC] text-[#0F172A]">

    @php
        /** @var \App\Models\User $currentUser */
        $currentUser = auth()->user();
        $role = $currentUser?->role ?? 'vendedor';
        $pendingIspCount = \App\Models\IspLog::where('estado', 'PENDIENTE')->count();

        $badgeColors = [
            'vendedor' => 'bg-blue-600/30 text-blue-300 border-blue-400/30',
            'quimico_farmaceutico' => 'bg-emerald-600/30 text-emerald-300 border-emerald-400/30',
            'bodeguero' => 'bg-amber-600/30 text-amber-300 border-amber-400/30',
            'administrador' => 'bg-purple-600/30 text-purple-300 border-purple-400/30',
        ];
    @endphp

    <!-- Barra superior Mobile -->
    <div class="lg:hidden bg-[#0F172A] text-white px-4 py-3 flex items-center justify-between border-b border-slate-800">
        <div class="flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-lg bg-[#1E40AF] flex items-center justify-center font-bold text-sm">Rx</span>
            <div>
                <div class="font-bold text-sm leading-tight">FarmaNet Pro</div>
                <div class="text-[11px] text-slate-400">{{ $currentUser?->nombre_completo }} ({{ $currentUser?->role_label }})</div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-red-500/20 border border-red-400/30 text-red-200 text-xs font-semibold">
                    ↪ Salir
                </button>
            </form>
            <button onclick="document.getElementById('sidebar-menu').classList.toggle('hidden')" class="p-2 rounded-lg bg-slate-800 text-slate-200 text-xs">
                ☰ Menú
            </button>
        </div>
    </div>

    <!-- Sidebar -->
    <aside id="sidebar-menu" class="hidden lg:flex flex-col w-full lg:w-64 lg:sticky lg:top-0 lg:h-screen bg-[#0F172A] text-white shrink-0 justify-between border-r border-slate-800 overflow-y-auto">
        <div>
            <!-- Brand Header -->
            <div class="p-5 flex items-center gap-3 border-b border-slate-800/80">
                <div class="w-9 h-9 rounded-lg bg-[#1E40AF] flex items-center justify-center font-extrabold text-white shadow-inner">
                    Rx
                </div>
                <div>
                    <h1 class="font-bold text-sm tracking-tight text-white">FarmaNet Pro</h1>
                    <p class="text-[11px] text-slate-400">Sistema de Farmacia</p>
                </div>
            </div>

            <!-- Perfil Activo (con acceso directo a Cerrar Sesión arriba) -->
            <div class="p-3.5 mx-3 my-3 rounded-xl bg-slate-800/60 border border-slate-700/60">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-[#1E40AF] flex items-center justify-center font-bold text-xs text-white uppercase shrink-0">
                        {{ substr($currentUser?->nombre_completo ?? 'U', 0, 1) }}
                    </div>
                    <div class="overflow-hidden flex-1">
                        <p class="font-semibold text-xs text-white truncate">{{ $currentUser?->nombre_completo }}</p>
                        <p class="text-[10px] text-slate-400 font-mono-code">{{ $currentUser?->rut }}</p>
                    </div>
                </div>
                <div class="mt-2.5 flex items-center justify-between gap-2">
                    <span class="inline-block text-[10px] font-semibold px-2.5 py-0.5 rounded-md border {{ $badgeColors[$role] ?? 'bg-blue-600/30 text-blue-200' }}">
                        {{ $currentUser?->role_label }}
                    </span>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit"
                                title="Cerrar sesión actual"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-red-500/15 hover:bg-red-500/30 text-red-300 border border-red-500/30 text-[10px] font-semibold transition">
                            ↪ Salir
                        </button>
                    </form>
                </div>
            </div>

            <!-- Navegación -->
            <div class="px-3 py-2">
                <p class="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Módulos</p>
                <nav class="space-y-1">
                    @if($role === 'administrador')
                        <a href="{{ route('dashboard.index') }}"
                           class="flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-medium transition {{ request()->routeIs('dashboard.*') ? 'bg-[#1E40AF] text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <span class="flex items-center gap-2.5">📊 Dashboard</span>
                        </a>
                    @endif

                    @if(in_array($role, ['vendedor', 'quimico_farmaceutico', 'administrador'], true))
                        <a href="{{ route('pos.index') }}"
                           class="flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-medium transition {{ request()->routeIs('pos.*') ? 'bg-[#1E40AF] text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <span class="flex items-center gap-2.5">🛒 Punto de Venta</span>
                        </a>
                    @endif

                    @if(in_array($role, ['quimico_farmaceutico', 'administrador'], true))
                        <a href="{{ route('autorizaciones.index') }}"
                           class="flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-medium transition {{ request()->routeIs('autorizaciones.*') ? 'bg-[#1E40AF] text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <span class="flex items-center gap-2.5">🛡️ Autorización</span>
                            @if($pendingIspCount > 0)
                                <span class="px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-[#EF4444] text-white animate-pulse-badge">
                                    {{ $pendingIspCount }}
                                </span>
                            @endif
                        </a>
                    @endif

                    @if(in_array($role, ['bodeguero', 'quimico_farmaceutico', 'vendedor', 'administrador'], true))
                        <a href="{{ route('inventario.index') }}"
                           class="flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventario.*') ? 'bg-[#1E40AF] text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <span class="flex items-center gap-2.5">📦 Inventario</span>
                        </a>
                    @endif

                    @if(in_array($role, ['vendedor', 'quimico_farmaceutico', 'administrador'], true))
                        <a href="{{ route('convenios.index') }}"
                           class="flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-medium transition {{ request()->routeIs('convenios.*') ? 'bg-[#1E40AF] text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <span class="flex items-center gap-2.5">🏥 Convenios</span>
                        </a>
                    @endif

                    @if($role === 'administrador')
                        <a href="{{ route('usuarios.index') }}"
                           class="flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-medium transition {{ request()->routeIs('usuarios.*') ? 'bg-[#1E40AF] text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <span class="flex items-center gap-2.5">👥 Usuarios</span>
                        </a>
                    @endif
                </nav>
            </div>
        </div>

        <!-- Cambio de Perfil & Cierre de Sesión -->
        <div class="p-4 border-t border-slate-800 text-xs space-y-3">
            <div class="bg-slate-900/90 p-2.5 rounded-lg border border-slate-800">
                <p class="text-[10px] font-semibold uppercase text-slate-400 mb-1.5">Cambiar Perfil</p>
                <div class="grid grid-cols-2 gap-1">
                    <form action="{{ route('login.demo', 'vendedor') }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full text-left px-2 py-1 rounded text-[10px] {{ $role === 'vendedor' ? 'bg-blue-600 text-white font-semibold' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">
                            Vendedor
                        </button>
                    </form>
                    <form action="{{ route('login.demo', 'quimico_farmaceutico') }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full text-left px-2 py-1 rounded text-[10px] {{ $role === 'quimico_farmaceutico' ? 'bg-emerald-600 text-white font-semibold' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">
                            Q. Farmacéutico
                        </button>
                    </form>
                    <form action="{{ route('login.demo', 'bodeguero') }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full text-left px-2 py-1 rounded text-[10px] {{ $role === 'bodeguero' ? 'bg-amber-600 text-white font-semibold' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">
                            Bodeguero
                        </button>
                    </form>
                    <form action="{{ route('login.demo', 'administrador') }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full text-left px-2 py-1 rounded text-[10px] {{ $role === 'administrador' ? 'bg-purple-600 text-white font-semibold' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">
                            Administrador
                        </button>
                    </form>
                </div>
            </div>

            <div class="flex items-center justify-between text-[11px] text-slate-400 px-1">
                <span>v2.4.1</span>
                <span>ISP Reg. #4421</span>
            </div>
        </div>
    </aside>

    <!-- Contenedor Principal -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Top Status Bar (Fija arriba con Cerrar Sesión rápido) -->
        <header class="sticky top-0 z-30 bg-white/95 backdrop-blur border-b border-slate-200 px-6 py-2.5 flex items-center justify-between text-xs text-slate-500">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 text-emerald-600 font-medium">
                    <span class="w-2 h-2 rounded-full bg-[#10B981]"></span>
                    Sistema en línea
                </span>
                <span class="text-slate-300">|</span>
                <span id="live-clock" class="font-mono-code text-slate-500">{{ now()->format('H:i:s') }}</span>
            </div>
            <div class="flex items-center gap-3">
                @if($pendingIspCount > 0 && in_array($role, ['quimico_farmaceutico', 'administrador'], true))
                    <a href="{{ route('autorizaciones.index') }}" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-red-50 border border-red-200 text-[#EF4444] font-semibold text-[11px] animate-pulse-badge">
                        ⚠️ {{ $pendingIspCount }} Receta(s) Pendiente(s)
                    </a>
                @endif
                <span class="hidden sm:inline font-mono-code text-slate-400">ISP Reg. #4421</span>
                <span class="hidden sm:inline text-slate-200">|</span>
                <form action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-red-200 bg-red-50/80 hover:bg-red-100 text-red-700 font-bold text-xs transition shadow-sm">
                        <span>↪</span>
                        <span>Cerrar Sesión</span>
                    </button>
                </form>
            </div>
        </header>

        <!-- Flash Alerts -->
        <div class="px-6 pt-4 space-y-2">
            @if(session('success'))
                <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-medium flex items-center justify-between">
                    <span>✅ {{ session('success') }}</span>
                    <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900 font-bold">✕</button>
                </div>
            @endif
            @if(session('warning'))
                <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-300 text-amber-900 text-xs font-semibold flex items-center justify-between">
                    <span>⚠️ {{ session('warning') }}</span>
                    <button onclick="this.parentElement.remove()" class="text-amber-700 hover:text-amber-900 font-bold">✕</button>
                </div>
            @endif
            @if(session('error'))
                <div class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-900 text-xs font-semibold flex items-center justify-between">
                    <span>🚨 {{ session('error') }}</span>
                    <button onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-900 font-bold">✕</button>
                </div>
            @endif
            @if($errors->any())
                <div class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-900 text-xs">
                    <p class="font-bold mb-1">Verifique los datos ingresados:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <!-- Contenido de la Vista -->
        <main class="flex-1 p-6">
            @yield('content')
        </main>
    </div>

    <script>
        setInterval(() => {
            const el = document.getElementById('live-clock');
            if (el) {
                const now = new Date();
                el.textContent = now.toLocaleTimeString('es-CL');
            }
        }, 1000);

        // Protección Global de Inputs (Límites de Fechas, Números y Caracteres)
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Regular todos los campos de fecha para evitar años de 5 dígitos o fuera de rango
            document.querySelectorAll('input[type="date"]').forEach(dateInput => {
                if (!dateInput.getAttribute('min')) {
                    dateInput.setAttribute('min', '2020-01-01');
                }
                if (!dateInput.getAttribute('max')) {
                    dateInput.setAttribute('max', '2036-12-31');
                }

                dateInput.addEventListener('change', function() {
                    const val = this.value;
                    if (!val) return;
                    const minVal = this.getAttribute('min') || '2020-01-01';
                    const maxVal = this.getAttribute('max') || '2036-12-31';

                    if (!/^\d{4}-\d{2}-\d{2}$/.test(val)) {
                        this.value = '';
                        return;
                    }
                    if (val < minVal) this.value = minVal;
                    if (val > maxVal) this.value = maxVal;
                });
            });

            // 2. Vincular pares de fechas (Inicio -> Fin / Desde -> Hasta) en el mismo formulario
            document.querySelectorAll('form').forEach(form => {
                const startInput = form.querySelector('input[name="fecha_inicio"], input[name="desde"], input[name="fecha_desde"]');
                const endInput = form.querySelector('input[name="fecha_fin"], input[name="hasta"], input[name="fecha_hasta"]');

                if (startInput && endInput) {
                    startInput.addEventListener('change', function() {
                        if (this.value) {
                            endInput.setAttribute('min', this.value);
                            if (endInput.value && endInput.value < this.value) {
                                endInput.value = this.value;
                            }
                        }
                    });
                    endInput.addEventListener('change', function() {
                        if (this.value && startInput.value && this.value < startInput.value) {
                            this.value = startInput.value;
                        }
                    });
                }
            });

            // 3. Regular campos numéricos para impedir desbordamientos (overflow) al escribir
            document.querySelectorAll('input[type="number"]').forEach(numInput => {
                numInput.addEventListener('input', function() {
                    const maxAttr = this.getAttribute('max');
                    const minAttr = this.getAttribute('min');
                    if (maxAttr !== null && this.value !== '' && Number(this.value) > Number(maxAttr)) {
                        this.value = maxAttr;
                    }
                    if (minAttr !== null && this.value !== '' && Number(this.value) < 0 && Number(minAttr) >= 0) {
                        this.value = minAttr;
                    }
                });
            });
        });
    </script>
</body>
</html>
