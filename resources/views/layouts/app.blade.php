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
        $invServiceLayout = app(\App\Services\InventoryService::class);
        $lowStockAlertsLayout = $invServiceLayout->checkLowStockAlerts();
        $lowStockCountLayout = $lowStockAlertsLayout->count();
        $fefoAlertsCountLayout = $invServiceLayout->checkExpiryAlerts(30)->count();
        $quickSwitchUsers = \App\Models\User::with('rol')
            ->where('activo', true)
            ->where('acceso_rapido', true)
            ->orderBy('id_usuario', 'asc')
            ->get();

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
                            @if($lowStockCountLayout > 0)
                                <span class="px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-amber-500 text-slate-950 animate-pulse-badge"
                                      title="{{ $lowStockCountLayout }} medicamento(s) en alerta de control de stock">
                                    {{ $lowStockCountLayout }}
                                </span>
                            @endif
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

        <!-- Cambio Rápido de Usuario (Protegido por PIN de 4 dígitos y Configurable por Admin) -->
        <div class="p-4 border-t border-slate-800 text-xs space-y-3">
            <div class="bg-slate-900/90 p-2.5 rounded-lg border border-slate-800">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-[10px] font-semibold uppercase text-slate-400">Cambiar Usuario (PIN)</p>
                    @if($role === 'administrador')
                        <a href="{{ route('usuarios.index', ['config_acceso' => 1]) }}"
                           class="text-[10px] font-bold text-blue-400 hover:text-blue-300 hover:underline"
                           title="Designar qué usuarios aparecen aquí y configurar sus PINs">
                            Editar
                        </a>
                    @endif
                </div>
                <div class="space-y-1 max-h-40 overflow-y-auto pr-0.5">
                    @php
                        $roleActiveColors = [
                            'vendedor' => 'bg-blue-600 text-white font-semibold',
                            'quimico_farmaceutico' => 'bg-emerald-600 text-white font-semibold',
                            'bodeguero' => 'bg-amber-600 text-white font-semibold',
                            'administrador' => 'bg-purple-600 text-white font-semibold',
                        ];
                    @endphp
                    @forelse($quickSwitchUsers as $qUser)
                        @php
                            $isCurrent = $currentUser && $currentUser->id_usuario === $qUser->id_usuario;
                            $qRole = $qUser->role;
                        @endphp
                        <button type="button"
                                onclick="openQuickSwitchPinModal({{ json_encode([
                                    'id_usuario' => $qUser->id_usuario,
                                    'nombre_completo' => $qUser->nombre_completo,
                                    'rut' => $qUser->rut,
                                    'role_label' => $qUser->role_label,
                                    'action_url' => route('login.demo', $qUser->id_usuario),
                                ]) }})"
                                class="w-full text-left px-2.5 py-1.5 rounded text-[10px] flex items-center justify-between gap-1.5 transition {{ $isCurrent ? ($roleActiveColors[$qRole] ?? 'bg-blue-600 text-white font-semibold') : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">
                            <span class="truncate">{{ $qUser->nombre_completo }}</span>
                            <span class="text-[9px] opacity-80 shrink-0">{{ $qUser->role_label }}</span>
                        </button>
                    @empty
                        <p class="text-[10px] text-slate-500 py-1">Sin operadores designados.</p>
                    @endforelse
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
        <!-- Top Status Bar (Fija arriba con Alertas Globales de Recetas ISP, Control de Stock, FEFO y Cerrar Sesión) -->
        <header class="sticky top-0 z-30 bg-white/95 backdrop-blur border-b border-slate-200 px-6 py-2.5 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 text-emerald-600 font-medium">
                    <span class="w-2 h-2 rounded-full bg-[#10B981]"></span>
                    Sistema en línea
                </span>
                <span class="text-slate-300">|</span>
                <span id="live-clock" class="font-mono-code text-slate-500">{{ now()->format('H:i:s') }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                {{-- 1. Alerta Superior de Control de Stock (Igual que la alerta de Receta Retenida del Q.F.) --}}
                @if($lowStockCountLayout > 0 && in_array($role, ['bodeguero', 'quimico_farmaceutico', 'administrador', 'vendedor'], true))
                    <div class="relative" id="top-stock-alert-wrapper">
                        <div class="inline-flex items-center rounded-full bg-amber-50 border border-amber-300 shadow-2xs overflow-hidden">
                            <a href="{{ route('inventario.index', ['ubicacion' => 'CATALOGO', 'stock' => 'bajo', 'orden' => 'stock_asc']) }}#panel-alertas-stock-bajo"
                               class="inline-flex items-center gap-1.5 pl-2.5 pr-2 py-1 text-amber-900 hover:bg-amber-100/80 font-bold text-[11px] animate-pulse-badge transition"
                               title="Ir a los {{ $lowStockCountLayout }} medicamentos con poco stock o agotados en vitrina">
                                <span>📉 {{ $lowStockCountLayout }} Control de Stock (Poco Stock)</span>
                            </a>
                            <button type="button"
                                    onclick="document.getElementById('top-stock-alert-dropdown').classList.toggle('hidden')"
                                    class="px-2 py-1 border-l border-amber-300 bg-amber-100/60 hover:bg-amber-200 text-amber-900 font-bold text-[10px] transition"
                                    title="Desplegar lista rápida de medicamentos con poco stock">
                                ▼
                            </button>
                        </div>

                        <!-- Desplegable Rápido Superior de Control de Stock (Permite pinchar cualquier medicamento directo desde arriba) -->
                        <div id="top-stock-alert-dropdown"
                             class="hidden absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-2xl border border-slate-200 shadow-2xl z-50 overflow-hidden">
                            <div class="bg-slate-900 text-white px-4 py-3 flex items-center justify-between">
                                <div>
                                    <div class="font-bold text-xs">📉 Alertas de Control de Stock ({{ $lowStockCountLayout }})</div>
                                    <div class="text-[10px] text-slate-300">
                                        @if($currentUser?->hasPermission('Can_Manage_Stock'))
                                            Pincha un medicamento para añadirle más stock
                                        @else
                                            Pincha un medicamento para consultarlo en Inventario
                                        @endif
                                    </div>
                                </div>
                                <button type="button"
                                        onclick="document.getElementById('top-stock-alert-dropdown').classList.add('hidden')"
                                        class="text-slate-400 hover:text-white font-bold text-xs">✕</button>
                            </div>
                            <div class="max-h-72 overflow-y-auto divide-y divide-slate-100">
                                @foreach($lowStockAlertsLayout->take(8) as $medTop)
                                    @php
                                        $vTop = $medTop->stock_vitrina;
                                        $bTop = $medTop->stock_bodega;
                                    @endphp
                                    <a href="{{ route('inventario.index', ['ubicacion' => 'VITRINA', 'q' => $medTop->nombre_comercial, 'reponer_med' => $medTop->id_medicamento]) }}#tabla-lotes-fefo"
                                       class="px-4 py-2.5 hover:bg-amber-50/70 transition flex items-center justify-between gap-2">
                                        <div class="min-w-0">
                                            <div class="font-bold text-slate-900 text-xs truncate">{{ $medTop->nombre_comercial }}</div>
                                            <div class="text-[10px] text-slate-500 font-mono-code truncate">
                                                {{ $medTop->laboratorio }} • EAN {{ $medTop->codigo_barras }}
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-extrabold {{ $vTop <= 0 ? 'bg-red-100 text-red-700 border border-red-200' : 'bg-amber-100 text-amber-800 border border-amber-300' }}">
                                                {{ $vTop <= 0 ? '🚨 0 en Vitrina' : '📉 ' . $vTop . ' u. en Vitrina' }}
                                            </span>
                                            <div class="text-[10px] text-slate-500 font-mono-code mt-0.5">
                                                Bodega: {{ $bTop }} u. &rarr;
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                            <div class="px-4 py-2.5 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                                <a href="{{ route('inventario.index', ['ubicacion' => 'CATALOGO', 'stock' => 'bajo', 'orden' => 'stock_asc']) }}#panel-alertas-stock-bajo"
                                   class="text-[11px] font-bold text-[#1E40AF] hover:underline">
                                    Ver todos ordenados por menor stock &rarr;
                                </a>
                                <span class="text-[10px] text-slate-400">{{ $currentUser?->role_label }}</span>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- 2. Alerta Superior de Lotes Próximos a Vencer (FEFO <= 30 días) --}}
                @if($fefoAlertsCountLayout > 0 && in_array($role, ['bodeguero', 'quimico_farmaceutico', 'administrador'], true))
                    <a href="{{ route('inventario.index', ['ubicacion' => 'TODOS', 'fefo' => 'por_vencer']) }}#tabla-lotes-fefo"
                       class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-orange-50 hover:bg-orange-100 border border-orange-300 text-orange-800 font-bold text-[11px] transition"
                       title="Ver los {{ $fefoAlertsCountLayout }} lotes próximos a vencer (≤ 30 días)">
                        ⏳ {{ $fefoAlertsCountLayout }} Lote(s) FEFO &le; 30d
                    </a>
                @endif

                {{-- 3. Alerta Superior de Recetas Retenidas Pendientes (Q. Farmacéutico y Administrador) --}}
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

    <!-- Modal de Cambio Rápido de Sesión con PIN de 4 Dígitos -->
    <div id="modal-cambio-sesion-pin" class="hidden fixed inset-0 z-50 bg-slate-900/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-sm w-full border border-slate-200 shadow-2xl overflow-hidden">
            <div class="bg-[#0F172A] text-white px-5 py-4 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-sm">Cambio de Sesión / Turno</h3>
                    <p class="text-[11px] text-slate-400">Verificación de seguridad con PIN de 4 dígitos</p>
                </div>
                <button type="button" onclick="document.getElementById('modal-cambio-sesion-pin').classList.add('hidden')" class="text-slate-400 hover:text-white font-bold">✕</button>
            </div>
            <form id="form-cambio-sesion-pin" method="POST" class="p-5 space-y-4 text-xs">
                @csrf
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center gap-3">
                    <div id="switch-pin-avatar" class="w-10 h-10 rounded-full bg-[#1E40AF] text-white font-extrabold text-sm flex items-center justify-center uppercase shrink-0">
                        U
                    </div>
                    <div class="min-w-0">
                        <div id="switch-pin-nombre" class="font-bold text-slate-900 text-sm truncate">Funcionario</div>
                        <div class="text-[11px] text-slate-500">
                            <span id="switch-pin-rol" class="font-semibold text-[#1E40AF]">Cargo</span> •
                            <span id="switch-pin-rut" class="font-mono-code">RUN</span>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="input-switch-pin-acceso" class="block font-bold text-slate-700 mb-1.5 text-center">
                        Ingrese su PIN de 4 dígitos
                    </label>
                    <input type="password"
                           id="input-switch-pin-acceso"
                           name="pin_acceso"
                           inputmode="numeric"
                           minlength="4"
                           maxlength="4"
                           pattern="^[0-9]{4}$"
                           required
                           autocomplete="off"
                           placeholder="••••"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4)"
                           class="w-full px-4 py-3 rounded-xl border-2 border-slate-300 focus:border-[#1E40AF] font-mono-code font-extrabold text-xl tracking-[0.5em] text-center text-slate-900 outline-none">
                    <p class="text-[10px] text-slate-400 text-center mt-1.5">
                        PIN de terminal de 4 dígitos (Por defecto inicial: <strong class="font-mono-code text-slate-600">1234</strong>)
                    </p>
                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <button type="button"
                            onclick="document.getElementById('modal-cambio-sesion-pin').classList.add('hidden')"
                            class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600 font-semibold">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg bg-[#1E40AF] hover:bg-blue-800 text-white font-bold shadow-sm transition">
                        Confirmar Cambio
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openQuickSwitchPinModal(user) {
            const modal = document.getElementById('modal-cambio-sesion-pin');
            const form = document.getElementById('form-cambio-sesion-pin');
            const pinInput = document.getElementById('input-switch-pin-acceso');
            if (!modal || !form) return;

            form.action = user.action_url;
            document.getElementById('switch-pin-nombre').textContent = user.nombre_completo || '';
            document.getElementById('switch-pin-rol').textContent = user.role_label || '';
            document.getElementById('switch-pin-rut').textContent = user.rut || '';
            document.getElementById('switch-pin-avatar').textContent = (user.nombre_completo || 'U').charAt(0).toUpperCase();

            if (pinInput) {
                pinInput.value = '';
            }
            modal.classList.remove('hidden');
            setTimeout(() => {
                if (pinInput) pinInput.focus();
            }, 60);
        }

        setInterval(() => {
            const el = document.getElementById('live-clock');
            if (el) {
                const now = new Date();
                el.textContent = now.toLocaleTimeString('es-CL');
            }
        }, 1000);

        // Protección Global de Inputs (Límites de Fechas, Números y Caracteres)
        document.addEventListener('click', function(e) {
            const topStockWrapper = document.getElementById('top-stock-alert-wrapper');
            const topStockDropdown = document.getElementById('top-stock-alert-dropdown');
            if (topStockWrapper && topStockDropdown && !topStockWrapper.contains(e.target)) {
                topStockDropdown.classList.add('hidden');
            }
        });

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
