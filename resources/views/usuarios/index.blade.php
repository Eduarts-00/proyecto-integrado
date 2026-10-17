@extends('layouts.app')

@section('title', 'Usuarios RBAC y Auditoría Forense NoSQL — FarmaNet Pro')

@section('content')
<div class="space-y-6">

    <!-- Cabecera Mantenedor de Usuarios RBAC (HU-05) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Mantenedor de Usuarios RBAC & Auditoría Forense</h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Control de Acceso Basado en Roles (NIST SP 800-63B) y Persistencia Documental NoSQL (MongoDB / JSON-BSON)
            </p>
        </div>

        <button type="button" onclick="document.getElementById('modal-nuevo-usuario').classList.remove('hidden')"
                class="px-4 py-2.5 rounded-lg bg-[#1E40AF] hover:bg-blue-800 text-white text-xs font-bold shadow-sm transition">
            + Registrar Funcionario RBAC
        </button>
    </div>

    <!-- Tabla de Usuarios y Asignación de Roles -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-3.5 border-b border-slate-200 bg-slate-50/60 flex items-center justify-between">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600">
                Nómina Institucional de Funcionarios (Segregación de Funciones)
            </h2>
            <span class="text-[11px] text-slate-400">Validación RUN Módulo 11 + Bcrypt/Argon2id</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 text-[11px] font-bold uppercase text-slate-400 bg-slate-50/40">
                        <th class="py-3 px-5">ID</th>
                        <th class="py-3 px-5">RUN (Módulo 11)</th>
                        <th class="py-3 px-5">Nombre Completo</th>
                        <th class="py-3 px-5">Correo Institucional</th>
                        <th class="py-3 px-5">Rol RBAC Asignado</th>
                        <th class="py-3 px-5 text-center">Estado</th>
                        <th class="py-3 px-5 text-right">Actualizar Privilegio</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @foreach($usuarios as $u)
                        <tr class="hover:bg-slate-50/80">
                            <td class="py-3 px-5 font-mono-code text-slate-400">#{{ $u->id_usuario }}</td>
                            <td class="py-3 px-5 font-mono-code font-bold text-slate-900">{{ $u->rut }}</td>
                            <td class="py-3 px-5 font-semibold text-slate-800">{{ $u->nombre_completo }}</td>
                            <td class="py-3 px-5 text-slate-500">{{ $u->email }}</td>
                            <td class="py-3 px-5">
                                <form action="{{ route('usuarios.update_role', $u->id_usuario) }}" method="POST" class="flex items-center gap-2">
                                    @csrf
                                    @method('PUT')
                                    <select name="id_rol" class="px-2.5 py-1.5 rounded-lg border border-slate-300 text-xs">
                                        @foreach($roles as $r)
                                            <option value="{{ $r->id_rol }}" {{ $u->id_rol === $r->id_rol ? 'selected' : '' }}>
                                                {{ $r->nombre_rol }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="activo" value="{{ $u->activo ? 1 : 0 }}">
                                    <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-blue-50 text-[#1E40AF] font-bold text-[11px]">
                                        Guardar
                                    </button>
                                </form>
                            </td>
                            <td class="py-3 px-5 text-center">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold {{ $u->activo ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700' }}">
                                    {{ $u->activo ? 'ACTIVO' : 'INACTIVO' }}
                                </span>
                            </td>
                            <td class="py-3 px-5 text-right text-[11px] font-mono-code text-slate-400">
                                {{ $u->pin_qf ? '🔑 PIN Q.F. Activo' : 'Estándar' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Visor de Documentos No Estructurados de Auditoría Forense (MongoDB - BSON/JSON - Página 39) -->
    <div class="bg-[#0F172A] rounded-2xl border border-slate-800 p-6 text-white shadow-lg">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 border-b border-slate-800 mb-4">
            <div>
                <h2 class="text-sm font-bold text-white flex items-center gap-2">
                    <span>🛡️</span> Colección Documental Inmutable de Auditoría Forense (MongoDB BSON/JSON)
                </h2>
                <p class="text-[11px] text-slate-400">
                    Registros encadenados con firma criptográfica <code>SHA-256 (hash_bloque)</code> para inspección sanitaria ISP y peritaje informático.
                </p>
            </div>
            <span class="px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-mono-code text-[11px]">
                {{ count($auditLogs) }} documento(s) inmutables
            </span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 max-h-[480px] overflow-y-auto pr-1">
            @foreach($auditLogs as $doc)
                <pre class="p-4 rounded-xl bg-slate-900/90 border border-slate-800 text-[11px] font-mono-code text-emerald-300 overflow-x-auto leading-relaxed">{{ json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            @endforeach
        </div>
    </div>

</div>

<!-- Modal Nuevo Usuario RBAC -->
<div id="modal-nuevo-usuario" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full border border-slate-200 shadow-xl overflow-hidden">
        <div class="bg-[#1E40AF] text-white px-5 py-4 flex items-center justify-between">
            <h3 class="font-bold text-sm">+ Registrar Nuevo Funcionario (RBAC)</h3>
            <button type="button" onclick="document.getElementById('modal-nuevo-usuario').classList.add('hidden')" class="text-white">✕</button>
        </div>
        <form action="{{ route('usuarios.store') }}" method="POST" class="p-5 space-y-3.5 text-xs">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">RUN (Validación Módulo 11)</label>
                    <input type="text" name="rut" placeholder="Ej: 18.455.120-K" required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Rol Institucional RBAC</label>
                    <select name="id_rol" class="w-full px-3 py-2 rounded-lg border border-slate-300">
                        @foreach($roles as $r)
                            <option value="{{ $r->id_rol }}">{{ $r->nombre_rol }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-1">Nombre Completo</label>
                <input type="text" name="nombre_completo" required placeholder="Ej: Dra. Camila Fuentes"
                       class="w-full px-3 py-2 rounded-lg border border-slate-300">
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-1">Correo Institucional</label>
                <input type="email" name="email" required placeholder="camila.fuentes@farmanet.cl"
                       class="w-full px-3 py-2 rounded-lg border border-slate-300">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Contraseña (NIST 800-63B)</label>
                    <input type="password" name="password" value="Password123!" required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">PIN Q.F. (Opcional)</label>
                    <input type="password" name="pin_qf" placeholder="Ej: 4421"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code">
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('modal-nuevo-usuario').classList.add('hidden')"
                        class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-[#1E40AF] text-white font-bold">Crear Usuario</button>
            </div>
        </form>
    </div>
</div>
@endsection
