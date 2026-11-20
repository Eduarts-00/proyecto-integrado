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

        $demoUsers = User::with('rol')->where('activo', true)->get()->keyBy(fn (User $u) => $u->role);

        return view('auth.login', compact('demoUsers'));
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
     * Acceso Rápido por Rol para demostración académica (Capítulo 5.1 - Pantalla 1).
     */
    public function quickLogin(Request $request, string $role): RedirectResponse
    {
        $user = User::with('rol')
            ->whereHas('rol', fn ($q) => $q->where('codigo_rol', $role))
            ->where('activo', true)
            ->firstOrFail();

        Auth::login($user);
        $request->session()->regenerate();

        $this->auditLogService->recordSecurityEvent('ACCESO_RAPIDO_DEMO_ROL', [
            'rol' => $user->role,
            'usuario' => $user->nombre_completo,
        ], $user);

        return $this->redirectByRole($user);
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
