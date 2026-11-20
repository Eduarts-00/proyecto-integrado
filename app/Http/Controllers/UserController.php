<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\RutValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

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
            'id_rol' => ['required', 'integer', 'min:1', 'max:2147483647', 'exists:roles,id_rol'],
            'password' => ['required', 'string', 'min:8', 'max:120'],
            'pin_qf' => ['nullable', 'string', 'max:10'],
        ]);

        if (! RutValidator::validate($data['rut'])) {
            return back()->with('error', 'El RUN ingresado no es válido.');
        }

        $formattedRut = RutValidator::format($data['rut']);
        if (User::where('rut', $formattedRut)->exists()) {
            return back()->with('error', 'Ya existe un funcionario registrado con ese RUN.');
        }

        $rolSeleccionado = Role::findOrFail((int) $data['id_rol']);
        $esQuimico = $rolSeleccionado->codigo_rol === 'quimico_farmaceutico';

        if ($esQuimico && empty($data['pin_qf'])) {
            return back()->with('error', 'Debe asignar un PIN de autorización para el perfil de Químico Farmacéutico.');
        }

        $nuevo = User::create([
            'rut' => $formattedRut,
            'nombre_completo' => trim($data['nombre_completo']),
            'email' => strtolower(trim($data['email'])),
            'id_rol' => (int) $data['id_rol'],
            'password_hash' => Hash::make($data['password']),
            'pin_qf' => ($esQuimico && ! empty($data['pin_qf'])) ? Hash::make($data['pin_qf']) : null,
            'activo' => true,
        ]);

        $this->auditLogService->recordSecurityEvent('CREACION_USUARIO_RBAC', [
            'nuevo_usuario_rut' => $nuevo->rut,
            'nombre' => $nuevo->nombre_completo,
            'id_rol' => $nuevo->id_rol,
        ], $request->user());

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario '{$nuevo->nombre_completo}' registrado correctamente.");
    }

    public function update(Request $request, int $idUsuario)
    {
        $usuario = User::findOrFail($idUsuario);

        $data = $request->validate([
            'rut' => ['required', 'string', 'max:15'],
            'nombre_completo' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:120',
                Rule::unique('usuarios', 'email')->ignore($usuario->id_usuario, 'id_usuario'),
            ],
            'id_rol' => ['required', 'integer', 'min:1', 'max:2147483647', 'exists:roles,id_rol'],
            'activo' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'max:120'],
            'pin_qf' => ['nullable', 'string', 'max:10'],
        ]);

        if (! RutValidator::validate($data['rut'])) {
            return back()->with('error', 'El RUN ingresado no es válido.');
        }

        $formattedRut = RutValidator::format($data['rut']);
        if (User::where('rut', $formattedRut)->where('id_usuario', '!=', $usuario->id_usuario)->exists()) {
            return back()->with('error', 'Ya existe otro funcionario registrado con ese RUN.');
        }

        if ($usuario->id_usuario === $request->user()?->id_usuario && ! (bool) $data['activo']) {
            return back()->with('error', 'No puede deshabilitar su propia cuenta de administrador en uso.');
        }

        $rolSeleccionado = Role::findOrFail((int) $data['id_rol']);
        $esQuimico = $rolSeleccionado->codigo_rol === 'quimico_farmaceutico';

        if ($esQuimico && empty($data['pin_qf']) && empty($usuario->pin_qf)) {
            return back()->with('error', 'Debe asignar un PIN de autorización para el perfil de Químico Farmacéutico.');
        }

        $pinQfActualizado = $usuario->pin_qf;
        if (! $esQuimico) {
            $pinQfActualizado = null;
        } elseif (! empty($data['pin_qf'])) {
            $pinQfActualizado = Hash::make($data['pin_qf']);
        }

        $camposActualizar = [
            'rut' => $formattedRut,
            'nombre_completo' => trim($data['nombre_completo']),
            'email' => strtolower(trim($data['email'])),
            'id_rol' => (int) $data['id_rol'],
            'activo' => (bool) $data['activo'],
            'pin_qf' => $pinQfActualizado,
        ];

        if (! empty($data['password'])) {
            $camposActualizar['password_hash'] = Hash::make($data['password']);
        }

        $usuario->update($camposActualizar);
        $usuario->load('rol');

        $this->auditLogService->recordSecurityEvent('ACTUALIZACION_DATOS_PERSONAL', [
            'usuario_afectado' => $usuario->rut,
            'nombre' => $usuario->nombre_completo,
            'email' => $usuario->email,
            'rol' => $usuario->role,
            'activo' => $usuario->activo,
        ], $request->user());

        return redirect()->route('usuarios.index')
            ->with('success', "Datos de '{$usuario->nombre_completo}' actualizados correctamente.");
    }

    public function updateRole(Request $request, int $idUsuario)
    {
        $data = $request->validate([
            'id_rol' => ['required', 'integer', 'min:1', 'max:2147483647', 'exists:roles,id_rol'],
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
            ->with('success', "Cargo actualizado para {$usuario->nombre_completo}.");
    }

    public function toggleStatus(Request $request, int $idUsuario)
    {
        $usuario = User::findOrFail($idUsuario);

        if ($usuario->id_usuario === $request->user()?->id_usuario) {
            return back()->with('error', 'No puede deshabilitar su propia cuenta de administrador en uso.');
        }

        $usuario->activo = ! $usuario->activo;
        $usuario->save();

        $evento = $usuario->activo ? 'REACTIVACION_PERSONAL_FARMACIA' : 'BAJA_PERSONAL_FARMACIA';

        $this->auditLogService->recordSecurityEvent($evento, [
            'usuario_afectado' => $usuario->rut,
            'nombre' => $usuario->nombre_completo,
            'nuevo_estado' => $usuario->activo ? 'ACTIVO' : 'INACTIVO',
        ], $request->user());

        $mensaje = $usuario->activo
            ? "La cuenta de '{$usuario->nombre_completo}' ha sido habilitada nuevamente."
            : "La cuenta de '{$usuario->nombre_completo}' ha sido deshabilitada.";

        return redirect()->route('usuarios.index')->with('success', $mensaje);
    }
}
