<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectByRole(Auth::user());
        }

        $quickAccessUsers = User::with('rol')
            ->where('activo', true)
            ->where('acceso_rapido', true)
            ->orderBy('id_usuario', 'asc')
            ->get();

        $demoUsers = $quickAccessUsers->keyBy(fn (User $u) => $u->role);

        return view('auth.login', compact('demoUsers', 'quickAccessUsers'));
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'rut' => ['required', 'string', 'min:3', 'max:80'],
            'password' => ['required', 'string', 'min:4', 'max:64'],
        ]);

        $rutInput = strtoupper(trim($credentials['rut']));
        $cleanInput = preg_replace('/[^0-9K]/', '', $rutInput);

        $user = User::with('rol')
            ->get()
            ->first(function (User $u) use ($cleanInput, $rutInput) {
                $cleanDb = preg_replace('/[^0-9K]/', '', strtoupper($u->rut));

                return $cleanDb === $cleanInput || strtolower($u->email) === strtolower($rutInput);
            });

        if (! $user || ! $user->activo || ! Hash::check($credentials['password'], $user->password_hash)) {
            $this->auditLogService->recordSecurityEvent('LOGIN_FALLIDO', [
                'rut_intentado' => $credentials['rut'],
            ], $user);

            return back()->withErrors([
                'rut' => 'Credenciales institucionales inválidas o cuenta inactiva.',
            ])->onlyInput('rut');
        }

        Auth::login($user);
        $request->session()->regenerate();

        $this->auditLogService->recordSecurityEvent('LOGIN_EXITOSO', [
            'rol' => $user->role,
        ], $user);

        return $this->redirectByRole($user);
    }

    /**
     * Cambio Rápido de Operador / Turno validado con PIN de 4 dígitos.
     * Acepta tanto el ID del usuario designado por el Administrador como el código de rol.
     */
    public function quickLogin(Request $request, string $role): RedirectResponse
    {
        $data = $request->validate([
            'pin_acceso' => ['required', 'string', 'size:4', 'regex:/^[0-9]{4}$/'],
        ], [
            'pin_acceso.required' => 'Debe ingresar el PIN de 4 dígitos para cambiar de sesión.',
            'pin_acceso.size' => 'El PIN de cambio de sesión debe tener exactamente 4 dígitos.',
            'pin_acceso.regex' => 'El PIN de cambio de sesión solo debe contener 4 números.',
        ]);

        $query = User::with('rol')
            ->where('activo', true)
            ->where('acceso_rapido', true);

        if (ctype_digit($role)) {
            $user = $query->where('id_usuario', (int) $role)->first();
        } else {
            $user = $query->whereHas('rol', fn ($q) => $q->where('codigo_rol', $role))->first();
        }

        if (! $user) {
            return back()->with('error', 'El funcionario seleccionado no está designado en el panel de cambio rápido de sesión.');
        }

        $pinInput = $data['pin_acceso'];
        $pinValido = ($user->pin_acceso && Hash::check($pinInput, $user->pin_acceso))
            || ($user->pin_qf && Hash::check($pinInput, $user->pin_qf));

        if (! $pinValido) {
            $this->auditLogService->recordSecurityEvent('CAMBIO_SESION_PIN_INVALIDO', [
                'usuario_destino' => $user->nombre_completo,
                'rut_destino' => $user->rut,
                'rol_destino' => $user->role,
            ], Auth::user() ?? $user);

            if (! Auth::check()) {
                return back()->withErrors([
                    'rut' => "PIN de 4 dígitos incorrecto para acceder como {$user->nombre_completo}.",
                ]);
            }

            return back()->with('error', "PIN de 4 dígitos incorrecto para cambiar a la sesión de {$user->nombre_completo}.");
        }

        Auth::login($user);
        $request->session()->regenerate();

        $this->auditLogService->recordSecurityEvent('CAMBIO_SESION_TURNO_PIN', [
            'rol' => $user->role,
            'usuario' => $user->nombre_completo,
            'rut' => $user->rut,
        ], $user);

        return $this->redirectByRole($user)
            ->with('success', "Sesión iniciada como {$user->nombre_completo} ({$user->role_label}).");
    }

    public function validateMFA(Request $request)
    {
        $data = $request->validate([
            'pin_qf' => ['required', 'string', 'min:4', 'max:10'],
        ]);

        $qf = User::with('rol')
            ->whereHas('rol', fn ($q) => $q->where('codigo_rol', 'quimico_farmaceutico'))
            ->where('activo', true)
            ->first();

        if (! $qf || ! Hash::check($data['pin_qf'], $qf->pin_qf)) {
            $this->auditLogService->recordSecurityEvent('MFA_PIN_QF_FALLIDO', [
                'intento' => 'PIN Q.F. rechazado',
            ], Auth::user());

            return response()->json(['valid' => false, 'message' => 'PIN criptográfico Q.F. inválido.'], 403);
        }

        return response()->json([
            'valid' => true,
            'qf_user_id' => $qf->id_usuario,
            'qf_nombre' => $qf->nombre_completo,
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user) {
            $this->auditLogService->recordSecurityEvent('CIERRE_SESION', [
                'rol' => $user->role,
            ], $user);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function redirectByRole(User $user): RedirectResponse
    {
        return match ($user->role) {
            'administrador' => redirect()->route('dashboard.index'),
            'quimico_farmaceutico' => redirect()->route('autorizaciones.index'),
            'bodeguero' => redirect()->route('inventario.index'),
            default => redirect()->route('pos.index'),
        };
    }
}
