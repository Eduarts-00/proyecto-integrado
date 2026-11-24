<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\RutValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'rut' => ['required', 'string', 'min:8', 'max:15'],
            'nombre_completo' => ['required', 'string', 'min:3', 'max:80', 'regex:/^[\pL\s\.\-]+$/u'],
            'email' => ['required', 'email', 'min:5', 'max:80', 'unique:usuarios,email'],
            'id_rol' => ['required', 'integer', 'min:1', 'max:2147483647', 'exists:roles,id_rol'],
            'password' => ['required', 'string', 'min:8', 'max:32'],
            'pin_qf' => ['nullable', 'string', 'min:4', 'max:6', 'regex:/^[0-9]{4,6}$/'],
            'acceso_rapido' => ['nullable', 'boolean'],
            'pin_acceso' => ['nullable', 'string', 'size:4', 'regex:/^[0-9]{4}$/'],
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

        $pinAccesoPlain = ! empty($data['pin_acceso']) ? $data['pin_acceso'] : '1234';
        $accesoRapido = isset($data['acceso_rapido']) ? (bool) $data['acceso_rapido'] : false;

        $nuevo = User::create([
            'rut' => $formattedRut,
            'nombre_completo' => trim($data['nombre_completo']),
            'email' => strtolower(trim($data['email'])),
            'id_rol' => (int) $data['id_rol'],
            'password_hash' => Hash::make($data['password']),
            'pin_qf' => ($esQuimico && ! empty($data['pin_qf'])) ? Hash::make($data['pin_qf']) : null,
            'acceso_rapido' => $accesoRapido,
            'pin_acceso' => Hash::make($pinAccesoPlain),
            'activo' => true,
        ]);

        $this->auditLogService->recordSecurityEvent('CREACION_USUARIO_RBAC', [
            'nuevo_usuario_rut' => $nuevo->rut,
            'nombre' => $nuevo->nombre_completo,
            'id_rol' => $nuevo->id_rol,
            'acceso_rapido' => $nuevo->acceso_rapido,
        ], $request->user());

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario '{$nuevo->nombre_completo}' registrado correctamente.");
    }

    public function update(Request $request, int $idUsuario)
    {
        $usuario = User::findOrFail($idUsuario);

        $data = $request->validate([
            'rut' => ['required', 'string', 'min:8', 'max:15'],
            'nombre_completo' => ['required', 'string', 'min:3', 'max:80', 'regex:/^[\pL\s\.\-]+$/u'],
            'email' => [
                'required',
                'email',
                'min:5',
                'max:80',
                Rule::unique('usuarios', 'email')->ignore($usuario->id_usuario, 'id_usuario'),
            ],
            'id_rol' => ['required', 'integer', 'min:1', 'max:2147483647', 'exists:roles,id_rol'],
            'activo' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'max:32'],
            'pin_qf' => ['nullable', 'string', 'min:4', 'max:6', 'regex:/^[0-9]{4,6}$/'],
            'acceso_rapido' => ['nullable', 'boolean'],
            'pin_acceso' => ['nullable', 'string', 'size:4', 'regex:/^[0-9]{4}$/'],
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

        $accesoRapido = $request->has('acceso_rapido')
            ? (bool) $request->boolean('acceso_rapido')
            : $usuario->acceso_rapido;

        if (! (bool) $data['activo']) {
            $accesoRapido = false;
        }

        $pinAccesoActualizado = $usuario->pin_acceso ?: Hash::make('1234');
        if (! empty($data['pin_acceso'])) {
            $pinAccesoActualizado = Hash::make($data['pin_acceso']);
        }

        $camposActualizar = [
            'rut' => $formattedRut,
            'nombre_completo' => trim($data['nombre_completo']),
            'email' => strtolower(trim($data['email'])),
            'id_rol' => (int) $data['id_rol'],
            'activo' => (bool) $data['activo'],
            'pin_qf' => $pinQfActualizado,
            'acceso_rapido' => $accesoRapido,
            'pin_acceso' => $pinAccesoActualizado,
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
            'acceso_rapido' => $usuario->acceso_rapido,
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
        if (! $usuario->activo) {
            $usuario->acceso_rapido = false;
        }
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

    /**
     * Designa o retira a un funcionario del panel de Cambio Rápido de Usuario en el terminal.
     */
    public function toggleQuickAccess(Request $request, int $idUsuario)
    {
        $usuario = User::findOrFail($idUsuario);

        if (! $usuario->activo && ! $usuario->acceso_rapido) {
            return back()->with('error', 'Solo se pueden designar funcionarios con cuenta activa para el cambio rápido de sesión.');
        }

        $usuario->acceso_rapido = ! $usuario->acceso_rapido;
        if ($usuario->acceso_rapido && empty($usuario->pin_acceso)) {
            $usuario->pin_acceso = Hash::make('1234');
        }
        $usuario->save();

        $this->auditLogService->recordSecurityEvent('CONFIGURACION_ACCESO_RAPIDO_TERMINAL', [
            'usuario_afectado' => $usuario->rut,
            'nombre' => $usuario->nombre_completo,
            'designado_en_acceso_rapido' => $usuario->acceso_rapido,
        ], $request->user());

        $msg = $usuario->acceso_rapido
            ? "'{$usuario->nombre_completo}' fue designado en el panel de Cambio Rápido de Usuario (protegido con PIN de 4 dígitos)."
            : "'{$usuario->nombre_completo}' fue retirado del panel de Cambio Rápido de Usuario.";

        return redirect()->route('usuarios.index')->with('success', $msg);
    }

    /**
     * Actualiza masivamente quiénes aparecen en el panel de Cambio Rápido de Usuario y sus PINs de 4 dígitos.
     */
    public function updateQuickAccessList(Request $request)
    {
        $data = $request->validate([
            'usuarios_acceso_ids' => ['nullable', 'array', 'max:20'],
            'usuarios_acceso_ids.*' => ['integer', 'min:1', 'max:2147483647', 'exists:usuarios,id_usuario'],
            'pins_acceso' => ['nullable', 'array'],
            'pins_acceso.*' => ['nullable', 'string', 'size:4', 'regex:/^[0-9]{4}$/'],
        ], [
            'pins_acceso.*.size' => 'Cada PIN de cambio de sesión debe tener exactamente 4 dígitos numéricos.',
            'pins_acceso.*.regex' => 'Cada PIN de cambio de sesión solo debe contener 4 números (0-9).',
        ]);

        $designadosIds = collect($data['usuarios_acceso_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
        $pinsInput = $data['pins_acceso'] ?? [];

        DB::transaction(function () use ($designadosIds, $pinsInput) {
            $usuarios = User::all();
            foreach ($usuarios as $u) {
                $esDesignado = $u->activo && in_array((int) $u->id_usuario, $designadosIds, true);
                $u->acceso_rapido = $esDesignado;

                $nuevoPin = isset($pinsInput[$u->id_usuario]) ? trim((string) $pinsInput[$u->id_usuario]) : '';
                if ($nuevoPin !== '') {
                    $u->pin_acceso = Hash::make($nuevoPin);
                } elseif (empty($u->pin_acceso)) {
                    $u->pin_acceso = Hash::make('1234');
                }

                $u->save();
            }
        });

        $this->auditLogService->recordSecurityEvent('ACTUALIZACION_PANEL_CAMBIO_USUARIO', [
            'total_designados' => count($designadosIds),
            'ids_designados' => $designadosIds,
        ], $request->user());

        return redirect()->route('usuarios.index')
            ->with('success', 'Panel de Cambio Rápido de Usuario y PINs de 4 dígitos actualizados correctamente.');
    }
}
