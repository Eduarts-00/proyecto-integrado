<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /**
     * Verifica que el usuario autenticado posea alguno de los roles permitidos.
     * Registra en la bitácora forense cualquier intento de acceso no autorizado (HU-05 CA-03).
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $user->loadMissing('rol');

        if (! in_array($user->role, $roles, true)) {
            $this->auditLogService->recordSecurityEvent('ACCESO_NO_AUTORIZADO_RBAC_403', [
                'ruta' => $request->path(),
                'metodo' => $request->method(),
                'rol_actual' => $user->role,
                'roles_requeridos' => implode(',', $roles),
            ], $user);

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Acceso denegado (403 Forbidden). Su rol (' . $user->role_label . ') no tiene privilegios para este módulo.',
                ], 403);
            }

            abort(403, 'Acceso denegado por política RBAC de Mínimo Privilegio.');
        }

        return $next($request);
    }
}
