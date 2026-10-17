<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\RutValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function index()
    {
        $usuarios = User::with('rol')->orderBy('id_usuario', 'asc')->get();
        $roles = Role::orderBy('id_rol', 'asc')->get();
        $auditLogs = $this->auditLogService->getLogs();

        return view('usuarios.index', compact('usuarios', 'roles', 'auditLogs'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'rut' => ['required', 'string', 'max:15'],
            'nombre_completo' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', 'unique:usuarios,email'],
            'id_rol' => ['required', 'integer', 'exists:roles,id_rol'],
            'password' => ['required', 'string', 'min:8'],
            'pin_qf' => ['nullable', 'string', 'max:10'],
        ]);

        if (! RutValidator::validate($data['rut'])) {
            return back()->with('error', 'El RUN ingresado no supera la validación matemática Módulo 11.');
        }

        $formattedRut = RutValidator::format($data['rut']);
        if (User::where('rut', $formattedRut)->exists()) {
            return back()->with('error', 'Ya existe un funcionario registrado con ese RUN.');
        }

        $nuevo = User::create([
            'rut' => $formattedRut,
            'nombre_completo' => trim($data['nombre_completo']),
            'email' => strtolower(trim($data['email'])),
            'id_rol' => (int) $data['id_rol'],
            'password_hash' => Hash::make($data['password']),
            'pin_qf' => ! empty($data['pin_qf']) ? Hash::make($data['pin_qf']) : null,
            'activo' => true,
        ]);

        $this->auditLogService->recordSecurityEvent('CREACION_USUARIO_RBAC', [
            'nuevo_usuario_rut' => $nuevo->rut,
            'nombre' => $nuevo->nombre_completo,
            'id_rol' => $nuevo->id_rol,
        ], $request->user());

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario '{$nuevo->nombre_completo}' creado con rol asignado bajo política RBAC.");
    }

    public function updateRole(Request $request, int $idUsuario)
    {
        $data = $request->validate([
            'id_rol' => ['required', 'integer', 'exists:roles,id_rol'],
            'activo' => ['required', 'boolean'],
        ]);

        $usuario = User::findOrFail($idUsuario);
        $rolAnterior = $usuario->role;

        $usuario->update([
            'id_rol' => (int) $data['id_rol'],
            'activo' => (bool) $data['activo'],
        ]);

        $usuario->load('rol');

        $this->auditLogService->recordSecurityEvent('MODIFICACION_PRIVILEGIOS_RBAC', [
            'usuario_afectado' => $usuario->rut,
            'rol_anterior' => $rolAnterior,
            'rol_nuevo' => $usuario->role,
            'activo' => $usuario->activo,
        ], $request->user());

        return redirect()->route('usuarios.index')
            ->with('success', "Privilegios RBAC actualizados para {$usuario->nombre_completo}.");
    }
}
