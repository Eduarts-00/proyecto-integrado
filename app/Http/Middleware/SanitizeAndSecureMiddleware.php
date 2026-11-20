<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Capa de blindaje entre el Frontend HTTP y el Backend/Base de Datos:
 * 1. Sanitiza entradas de texto eliminando etiquetas HTML/JS (prevención XSS almacenado).
 * 2. Inyecta cabeceras de seguridad HTTP recomendadas por OWASP.
 */
class SanitizeAndSecureMiddleware
{
    /**
     * Campos excluidos de alteración de caracteres (contraseñas y hashes).
     *
     * @var array<int, string>
     */
    private array $except = [
        'password',
        'password_confirmation',
        'pin_qf',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();
        if (! empty($input)) {
            $request->merge($this->sanitizeArray($input));
        }

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function sanitizeArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array((string) $key, $this->except, true)) {
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                // Elimina etiquetas HTML/Script y caracteres nulos antes de tocar controladores o BD
                $clean = str_replace("\0", '', $value);
                $data[$key] = trim(strip_tags($clean));
            }
        }

        return $data;
    }
}
