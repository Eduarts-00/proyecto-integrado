@extends('layouts.app')

@section('title', 'Gestión de Usuarios — FarmaNet Pro')

@section('content')
<div class="space-y-6">

    <!-- Cabecera Mantenedor de Usuarios -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Gestión de Usuarios y Registro de Actividad</h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Administración de cuentas del personal, designación de operadores para cambio rápido de sesión con PIN de 4 dígitos y bitácora del sistema.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <button type="button" onclick="document.getElementById('modal-config-acceso-rapido').classList.remove('hidden')"
                    class="px-4 py-2.5 rounded-lg border border-blue-200 bg-blue-50 hover:bg-blue-100 text-[#1E40AF] text-xs font-bold shadow-2xs transition">
                Configurar Cambio Rápido y PINs
            </button>
            <button type="button" onclick="document.getElementById('modal-nuevo-usuario').classList.remove('hidden')"
                    class="px-4 py-2.5 rounded-lg bg-[#1E40AF] hover:bg-blue-800 text-white text-xs font-bold shadow-sm transition">
                + Nuevo Usuario
            </button>
        </div>
    </div>

    <!-- Tabla de Usuarios y Asignación de Cargos -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-3.5 border-b border-slate-200 bg-slate-50/60 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600">
                    Personal Registrado
                </h2>
                <p class="text-[11px] text-slate-400">
                    Designa qué funcionarios aparecen en el acceso rápido lateral de cambio de turno (protegido por PIN de 4 dígitos).
                </p>
            </div>
            <span class="text-[11px] text-slate-400">{{ count($usuarios) }} cuentas registradas</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 text-[11px] font-bold uppercase text-slate-400 bg-slate-50/40 whitespace-nowrap">
                        <th class="py-3 px-4">ID</th>
                        <th class="py-3 px-4">RUN</th>
                        <th class="py-3 px-4">Nombre Completo</th>
                        <th class="py-3 px-4">Correo Electrónico</th>
                        <th class="py-3 px-4">Cargo / Perfil</th>
                        <th class="py-3 px-4 text-center">Estado</th>
                        <th class="py-3 px-4 text-center">Cambio Rápido (PIN)</th>
                        <th class="py-3 px-4 text-center">Firma ISP</th>
                        <th class="py-3 px-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @foreach($usuarios as $u)
                        <tr class="hover:bg-slate-50/80 {{ ! $u->activo ? 'bg-slate-50/60 opacity-75' : '' }}">
                            <td class="py-3 px-4 font-mono-code text-slate-400">#{{ $u->id_usuario }}</td>
                            <td class="py-3 px-4 font-mono-code font-bold text-slate-900 whitespace-nowrap">{{ $u->rut }}</td>
                            <td class="py-3 px-4 font-semibold {{ $u->activo ? 'text-slate-800' : 'text-slate-400 line-through' }}">
                                {{ $u->nombre_completo }}
                            </td>
                            <td class="py-3 px-4 text-slate-500">{{ $u->email }}</td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 font-semibold text-[11px]">
                                    {{ $u->rol?->nombre_rol ?? 'Sin cargo' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold {{ $u->activo ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700' }}">
                                    {{ $u->activo ? 'ACTIVO' : 'DESHABILITADO' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                @if($u->activo)
                                    <form action="{{ route('usuarios.toggle_quick_access', $u->id_usuario) }}" method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        @if($u->acceso_rapido)
                                            <button type="submit"
                                                    title="Designado en el panel de Cambio Rápido (Haz clic para retirar)"
                                                    class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-blue-50 hover:bg-red-50 text-[#1E40AF] hover:text-red-700 border border-blue-200 hover:border-red-200 transition">
                                                ✓ Designado (PIN 4d)
                                            </button>
                                        @else
                                            <button type="submit"
                                                    title="Haz clic para agregar a este funcionario al panel de Cambio Rápido de Usuario"
                                                    class="px-2.5 py-1 rounded-full text-[10px] font-semibold bg-slate-100 hover:bg-blue-50 text-slate-500 hover:text-[#1E40AF] border border-slate-200 transition">
                                                + Designar
                                            </button>
                                        @endif
                                    </form>
                                @else
                                    <span class="text-[10px] text-slate-400">Inactivo</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center text-[11px] font-mono-code text-slate-400 whitespace-nowrap">
                                {{ $u->pin_qf ? '🔑 Habilitado Q.F.' : 'Estándar' }}
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <button type="button"
                                            onclick="openEditUserModal({{ json_encode([
                                                'id_usuario' => $u->id_usuario,
                                                'rut' => $u->rut,
                                                'nombre_completo' => $u->nombre_completo,
                                                'email' => $u->email,
                                                'id_rol' => $u->id_rol,
                                                'activo' => (bool) $u->activo,
                                                'acceso_rapido' => (bool) $u->acceso_rapido,
                                                'tiene_pin' => ! empty($u->pin_qf),
                                                'update_url' => route('usuarios.update', $u->id_usuario),
                                            ]) }})"
                                            class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-blue-50 text-[#1E40AF] font-bold text-[11px] transition">
                                        Editar
                                    </button>

                                    @if(auth()->id() !== $u->id_usuario)
                                        <form action="{{ route('usuarios.toggle_status', $u->id_usuario) }}" method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            @if($u->activo)
                                                <button type="submit"
                                                        onclick="return confirm('¿Desea deshabilitar el acceso de {{ addslashes($u->nombre_completo) }}? Ya no podrá iniciar sesión en el sistema.')"
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
                                    @else
                                        <span class="px-2.5 py-1.5 rounded-lg bg-slate-100 text-slate-400 text-[10px] font-semibold" title="No puede deshabilitar su propia sesión activa">
                                            Sesión actual
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bitácora de Actividad del Sistema -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/60 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">
                    📋 Registro de Actividad y Auditoría del Sistema
                </h2>
                <p class="text-[11px] text-slate-400">
                    Historial de autorizaciones sanitarias, cambios de sesión con PIN y modificaciones administrativas.
                </p>
            </div>
            <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-600 border border-slate-200 font-mono-code text-[11px]">
                {{ count($auditLogs) }} registro(s)
            </span>
        </div>

        <div class="overflow-x-auto max-h-[420px]">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 text-[11px] font-bold uppercase text-slate-400 bg-slate-50/40">
                        <th class="py-3 px-5">Fecha / Hora</th>
                        <th class="py-3 px-5">Evento</th>
                        <th class="py-3 px-5">Funcionario</th>
                        <th class="py-3 px-5">Terminal / IP</th>
                        <th class="py-3 px-5">Código de Verificación</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($auditLogs as $doc)
                        <tr class="hover:bg-slate-50/80">
                            <td class="py-3 px-5 font-mono-code text-[11px] text-slate-500">
                                {{ isset($doc['marca_tiempo']) ? \Carbon\Carbon::parse($doc['marca_tiempo'])->timezone(config('app.timezone', 'America/Santiago'))->format('d-m-Y H:i:s') : '—' }}
                            </td>
                            <td class="py-3 px-5">
                                <span class="px-2 py-0.5 rounded bg-blue-50 text-blue-800 border border-blue-200 font-semibold text-[11px]">
                                    {{ str_replace('_', ' ', $doc['evento'] ?? 'EVENTO') }}
                                </span>
                            </td>
                            <td class="py-3 px-5">
                                <div class="font-semibold text-slate-800">{{ $doc['usuario_qf']['nombre'] ?? 'Sistema' }}</div>
                                <div class="text-[10px] font-mono-code text-slate-400">{{ $doc['usuario_qf']['rut'] ?? '' }}</div>
                            </td>
                            <td class="py-3 px-5 font-mono-code text-[11px] text-slate-500">
                                {{ $doc['contexto_red']['terminal_id'] ?? 'POS-BOX-01' }} ({{ $doc['contexto_red']['ip_origen'] ?? '127.0.0.1' }})
                            </td>
                            <td class="py-3 px-5 font-mono-code text-[10px] text-slate-400">
                                {{ isset($doc['hash_bloque']) ? substr($doc['hash_bloque'], 0, 20) . '...' : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-400 text-xs">
                                Sin registros de actividad recientes.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal Configurar Panel de Cambio Rápido de Usuario y PINs de 4 Dígitos -->
<div id="modal-config-acceso-rapido" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full border border-slate-200 shadow-xl overflow-hidden max-h-[92vh] flex flex-col">
        <div class="bg-[#0F172A] text-white px-5 py-4 flex items-center justify-between">
            <div>
                <h3 class="font-bold text-sm">Configurar Panel de Cambio Rápido de Usuario y PINs</h3>
                <p class="text-[11px] text-slate-300">Designa qué funcionarios aparecen en el acceso rápido lateral y asigna su PIN de 4 dígitos</p>
            </div>
            <button type="button" onclick="document.getElementById('modal-config-acceso-rapido').classList.add('hidden')" class="text-white">✕</button>
        </div>

        <form action="{{ route('usuarios.update_quick_access_list') }}" method="POST" class="p-5 space-y-4 text-xs overflow-y-auto">
            @csrf
            <div class="p-3 rounded-xl bg-blue-50/80 border border-blue-200 text-blue-900 text-[11px]">
                Marca los funcionarios que deseas habilitar en el bloque <strong>"Cambiar Usuario (PIN)"</strong> del menú lateral y de la pantalla de inicio. Para cambiar el PIN de 4 dígitos de un funcionario, escríbelo en su casilla correspondiente.
            </div>

            <div class="border border-slate-200 rounded-xl overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold uppercase text-slate-500">
                            <th class="py-2.5 px-3 text-center">Incluir</th>
                            <th class="py-2.5 px-3">Funcionario</th>
                            <th class="py-2.5 px-3">Cargo / Rol</th>
                            <th class="py-2.5 px-3">Nuevo PIN (4 dígitos)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($usuarios->where('activo', true) as $uActivo)
                            <tr class="hover:bg-slate-50/80">
                                <td class="py-2.5 px-3 text-center">
                                    <input type="checkbox"
                                           name="usuarios_acceso_ids[]"
                                           value="{{ $uActivo->id_usuario }}"
                                           {{ $uActivo->acceso_rapido ? 'checked' : '' }}
                                           class="rounded border-slate-300 text-[#1E40AF] w-4 h-4 cursor-pointer">
                                </td>
                                <td class="py-2.5 px-3">
                                    <div class="font-bold text-slate-900">{{ $uActivo->nombre_completo }}</div>
                                    <div class="font-mono-code text-[10px] text-slate-400">{{ $uActivo->rut }}</div>
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-semibold text-[10px]">
                                        {{ $uActivo->role_label }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3">
                                    <input type="password"
                                           name="pins_acceso[{{ $uActivo->id_usuario }}]"
                                           inputmode="numeric"
                                           minlength="4"
                                           maxlength="4"
                                           pattern="^[0-9]{4}$"
                                           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4)"
                                           placeholder="Mantener actual"
                                           class="w-36 px-2.5 py-1.5 rounded-lg border border-slate-300 font-mono-code text-center text-xs focus:border-[#1E40AF] outline-none">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('modal-config-acceso-rapido').classList.add('hidden')"
                        class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-[#1E40AF] hover:bg-blue-800 text-white font-bold">
                    Guardar Configuración y PINs
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Nuevo Usuario -->
<div id="modal-nuevo-usuario" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full border border-slate-200 shadow-xl overflow-hidden max-h-[92vh] flex flex-col">
        <div class="bg-[#1E40AF] text-white px-5 py-4 flex items-center justify-between">
            <h3 class="font-bold text-sm">+ Nuevo Usuario</h3>
            <button type="button" onclick="document.getElementById('modal-nuevo-usuario').classList.add('hidden')" class="text-white">✕</button>
        </div>
        <form action="{{ route('usuarios.store') }}" method="POST" class="p-5 space-y-3.5 text-xs overflow-y-auto">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">RUN</label>
                    <input type="text" name="rut" placeholder="Ej: 18.455.120-K" required
                           minlength="8" maxlength="12"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Cargo / Perfil</label>
                    <select id="select-rol-nuevo-usuario" name="id_rol" onchange="togglePinQfField()"
                            class="w-full px-3 py-2 rounded-lg border border-slate-300">
                        @foreach($roles as $r)
                            <option value="{{ $r->id_rol }}" data-codigo="{{ $r->codigo_rol }}">{{ $r->nombre_rol }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-1">Nombre Completo</label>
                <input type="text" name="nombre_completo" required placeholder="Ej: Dra. Camila Fuentes"
                       minlength="3" maxlength="80"
                       class="w-full px-3 py-2 rounded-lg border border-slate-300">
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-1">Correo Electrónico</label>
                <input type="email" name="email" required placeholder="camila.fuentes@farmanet.cl"
                       minlength="5" maxlength="80"
                       class="w-full px-3 py-2 rounded-lg border border-slate-300">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Contraseña</label>
                    <input type="password" name="password" required placeholder="••••••••"
                           minlength="8" maxlength="32"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">PIN Cambio Sesión</label>
                    <input type="password" name="pin_acceso" required placeholder="••••"
                           inputmode="numeric" minlength="4" maxlength="4" pattern="^[0-9]{4}$"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4)"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code text-center font-bold">
                </div>
            </div>
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="acceso_rapido" value="1" checked
                           class="rounded border-slate-300 text-[#1E40AF] w-4 h-4">
                    <span class="font-semibold text-slate-800">Designar en el panel de Cambio Rápido de Usuario</span>
                </label>
            </div>
            <div id="contenedor-pin-qf" class="hidden p-3.5 rounded-xl bg-emerald-50/70 border border-emerald-200 space-y-1.5">
                <label class="block font-bold text-emerald-900">PIN de Autorización de Recetas</label>
                <input type="password" id="input-pin-qf" name="pin_qf" placeholder="••••" disabled
                       minlength="4" maxlength="6" pattern="^[0-9]{4,6}$"
                       class="w-full px-3 py-2 rounded-lg border border-emerald-300 bg-white font-mono-code">
                <p class="text-[11px] text-emerald-700">Requerido para autorizar la dispensación de recetas retenidas en el sistema.</p>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('modal-nuevo-usuario').classList.add('hidden')"
                        class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-[#1E40AF] text-white font-bold">Crear Usuario</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Usuario -->
<div id="modal-editar-usuario" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full border border-slate-200 shadow-xl overflow-hidden max-h-[92vh] flex flex-col">
        <div class="bg-[#1E40AF] text-white px-5 py-4 flex items-center justify-between">
            <h3 class="font-bold text-sm">Editar Datos del Personal</h3>
            <button type="button" onclick="document.getElementById('modal-editar-usuario').classList.add('hidden')" class="text-white">✕</button>
        </div>
        <form id="form-editar-usuario" method="POST" class="p-5 space-y-3.5 text-xs overflow-y-auto">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">RUN</label>
                    <input type="text" id="edit-rut" name="rut" required
                           minlength="8" maxlength="12"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Cargo / Perfil</label>
                    <select id="edit-id-rol" name="id_rol" onchange="toggleEditPinQfField()"
                            class="w-full px-3 py-2 rounded-lg border border-slate-300">
                        @foreach($roles as $r)
                            <option value="{{ $r->id_rol }}" data-codigo="{{ $r->codigo_rol }}">{{ $r->nombre_rol }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-1">Nombre Completo</label>
                <input type="text" id="edit-nombre-completo" name="nombre_completo" required
                       minlength="3" maxlength="80"
                       class="w-full px-3 py-2 rounded-lg border border-slate-300">
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-1">Correo Electrónico</label>
                <input type="email" id="edit-email" name="email" required
                       minlength="5" maxlength="80"
                       class="w-full px-3 py-2 rounded-lg border border-slate-300">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Estado de la Cuenta</label>
                    <select id="edit-activo" name="activo"
                            class="w-full px-3 py-2 rounded-lg border border-slate-300">
                        <option value="1">Activo (Habilitado)</option>
                        <option value="0">Deshabilitado (Baja / Renuncia)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Nueva Contraseña</label>
                    <input type="password" id="edit-password" name="password" placeholder="Dejar vacío para mantener"
                           minlength="8" maxlength="32"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300">
                </div>
            </div>
            <div class="p-3 rounded-xl bg-blue-50/70 border border-blue-200 space-y-2">
                <div class="flex items-center justify-between">
                    <label class="font-bold text-slate-800">Cambio Rápido de Sesión en Terminal</label>
                    <select id="edit-acceso-rapido" name="acceso_rapido" class="px-2.5 py-1 rounded-lg border border-blue-300 bg-white font-semibold text-slate-800">
                        <option value="1">✓ Designado en panel lateral</option>
                        <option value="0">No incluido</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Nuevo PIN de Cambio de Sesión</label>
                    <input type="password" id="edit-pin-acceso" name="pin_acceso"
                           inputmode="numeric" minlength="4" maxlength="4" pattern="^[0-9]{4}$"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4)"
                           placeholder="Dejar vacío para mantener PIN actual"
                           class="w-full px-3 py-1.5 rounded-lg border border-blue-300 bg-white font-mono-code">
                </div>
            </div>
            <div id="edit-contenedor-pin-qf" class="hidden p-3.5 rounded-xl bg-emerald-50/70 border border-emerald-200 space-y-1.5">
                <label class="block font-bold text-emerald-900">PIN de Autorización de Recetas</label>
                <input type="password" id="edit-input-pin-qf" name="pin_qf" placeholder="Ingrese nuevo PIN o deje vacío para mantener actual" disabled
                       minlength="4" maxlength="6" pattern="^[0-9]{4,6}$"
                       class="w-full px-3 py-2 rounded-lg border border-emerald-300 bg-white font-mono-code">
                <p id="edit-ayuda-pin-qf" class="text-[11px] text-emerald-700">Requerido para autorizar la dispensación de recetas retenidas en el sistema.</p>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('modal-editar-usuario').classList.add('hidden')"
                        class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-[#1E40AF] text-white font-bold">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
    let editUserHasExistingPin = false;

    function togglePinQfField() {
        const select = document.getElementById('select-rol-nuevo-usuario');
        const container = document.getElementById('contenedor-pin-qf');
        const input = document.getElementById('input-pin-qf');
        if (!select || !container || !input) return;

        const selectedOption = select.options[select.selectedIndex];
        const isQf = selectedOption && selectedOption.getAttribute('data-codigo') === 'quimico_farmaceutico';

        if (isQf) {
            container.classList.remove('hidden');
            input.disabled = false;
            input.required = true;
        } else {
            container.classList.add('hidden');
            input.disabled = true;
            input.required = false;
            input.value = '';
        }
    }

    function openEditUserModal(user) {
        const modal = document.getElementById('modal-editar-usuario');
        const form = document.getElementById('form-editar-usuario');
        if (!modal || !form) return;

        form.action = user.update_url;
        document.getElementById('edit-rut').value = user.rut || '';
        document.getElementById('edit-nombre-completo').value = user.nombre_completo || '';
        document.getElementById('edit-email').value = user.email || '';
        document.getElementById('edit-id-rol').value = user.id_rol;
        document.getElementById('edit-activo').value = user.activo ? '1' : '0';
        document.getElementById('edit-acceso-rapido').value = user.acceso_rapido ? '1' : '0';
        document.getElementById('edit-password').value = '';
        document.getElementById('edit-pin-acceso').value = '';
        document.getElementById('edit-input-pin-qf').value = '';

        editUserHasExistingPin = Boolean(user.tiene_pin);
        toggleEditPinQfField();

        modal.classList.remove('hidden');
    }

    function toggleEditPinQfField() {
        const select = document.getElementById('edit-id-rol');
        const container = document.getElementById('edit-contenedor-pin-qf');
        const input = document.getElementById('edit-input-pin-qf');
        const help = document.getElementById('edit-ayuda-pin-qf');
        if (!select || !container || !input) return;

        const selectedOption = select.options[select.selectedIndex];
        const isQf = selectedOption && selectedOption.getAttribute('data-codigo') === 'quimico_farmaceutico';

        if (isQf) {
            container.classList.remove('hidden');
            input.disabled = false;
            input.required = !editUserHasExistingPin;
            input.placeholder = editUserHasExistingPin
                ? 'Dejar vacío para mantener PIN actual'
                : 'Ingrese PIN de 4 dígitos (ej: 4421)';
            if (help) {
                help.textContent = editUserHasExistingPin
                    ? 'Este funcionario ya cuenta con PIN Q.F. Ingrese uno nuevo solo si desea cambiarlo.'
                    : 'Debe asignar un PIN para habilitar la firma de recetas retenidas.';
            }
        } else {
            container.classList.add('hidden');
            input.disabled = true;
            input.required = false;
            input.value = '';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        togglePinQfField();
        @if(request()->boolean('config_acceso'))
            const modalConfig = document.getElementById('modal-config-acceso-rapido');
            if (modalConfig) modalConfig.classList.remove('hidden');
        @endif
    });
</script>
@endsection
