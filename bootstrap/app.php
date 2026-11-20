<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SanitizeAndSecureMiddleware;
use App\Services\AuditLogService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            SanitizeAndSecureMiddleware::class,
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Blindaje de Base de Datos: Jamás exponer consultas SQL, SQLSTATE ni estructura de tablas al Frontend
        $exceptions->render(function (QueryException|\PDOException $e, Request $request) {
            Log::error('Excepción de base de datos interceptada por capa de seguridad', [
                'code' => $e->getCode(),
                'url' => $request->fullUrl(),
                'user_id' => $request->user()?->id_usuario,
            ]);

            try {
                app(AuditLogService::class)->recordSecurityEvent('BLOQUEO_EXCEPCION_INTEGRIDAD_BD', [
                    'ruta' => $request->path(),
                    'metodo' => $request->method(),
                    'codigo_sql' => (string) $e->getCode(),
                ], $request->user());
            } catch (\Throwable) {
                // Continuar de forma segura si la bitácora no está disponible
            }

            $mensajeSeguro = 'Operación rechazada por validación de integridad y rango de datos. Verifique que los valores ingresados sean válidos.';

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => $mensajeSeguro,
                ], 422);
            }

            return back()->withInput()->with('error', $mensajeSeguro);
        });

        $exceptions->render(function (NotFoundHttpException|MethodNotAllowedHttpException $e, Request $request) {
            if (! $request->expectsJson()) {
                return redirect()->route('login');
            }
        });
    })->create();
