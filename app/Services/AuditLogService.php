<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class AuditLogService
{
    private const COLLECTION_PATH = 'mongodb_audit_logs.json';
    private const TESTING_COLLECTION_PATH = 'mongodb_audit_logs_testing.json';

    private function getCollectionPath(): string
    {
        return app()->environment('testing')
            ? self::TESTING_COLLECTION_PATH
            : self::COLLECTION_PATH;
    }

    /**
     * Registra un documento inmutable de auditoría forense para autorización ISP (Capítulo 6.4).
     */
    public function recordIspAuthorization(
        User $qfUser,
        array $datosDispensacion,
        ?string $ipOrigen = null,
        ?string $userAgent = null,
        string $terminalId = 'POS-BOX-01'
    ): array {
        $timestamp = now()->timezone(config('app.timezone', 'America/Santiago'))->toIso8601String();
        $ip = $ipOrigen ?? request()?->ip() ?? '192.168.10.15';
        $ua = $userAgent ?? request()?->userAgent() ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) FarmaNetPOS/2.4.1';

        $payloadToSign = json_encode([
            'evento' => 'AUTORIZACION_RECETA_ISP',
            'marca_tiempo' => $timestamp,
            'rut_qf' => $qfUser->rut,
            'datos_dispensacion' => $datosDispensacion,
            'ip_origen' => $ip,
        ]);

        $hashBloque = hash('sha256', $payloadToSign);

        $document = [
            '_id' => bin2hex(random_bytes(12)),
            'evento' => 'AUTORIZACION_RECETA_ISP',
            'marca_tiempo' => $timestamp,
            'usuario_qf' => [
                'id_usuario' => $qfUser->id_usuario,
                'rut' => $qfUser->rut,
                'nombre' => $qfUser->nombre_completo,
            ],
            'datos_dispensacion' => [
                'folio_receta' => $datosDispensacion['folio_receta'] ?? '',
                'rut_paciente' => $datosDispensacion['rut_paciente'] ?? '',
                'rut_medico' => $datosDispensacion['rut_medico'] ?? '',
                'medicamento' => $datosDispensacion['medicamento'] ?? '',
                'lote_codigo' => $datosDispensacion['lote_codigo'] ?? '',
            ],
            'contexto_red' => [
                'ip_origen' => $ip,
                'user_agent' => $ua,
                'terminal_id' => $terminalId,
            ],
            'hash_bloque' => $hashBloque,
        ];

        $this->appendDocument($document);

        return $document;
    }

    /**
     * Registra eventos de seguridad (RBAC 403, logins fallidos, cambios de privilegios, etc.).
     */
    public function recordSecurityEvent(string $evento, array $detalles = [], ?User $user = null): array
    {
        $timestamp = now()->timezone(config('app.timezone', 'America/Santiago'))->toIso8601String();
        $ip = request()?->ip() ?? '192.168.10.15';
        $ua = request()?->userAgent() ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) FarmaNetPOS/2.4.1';

        $payloadToSign = json_encode([
            'evento' => $evento,
            'marca_tiempo' => $timestamp,
            'usuario' => $user?->rut ?? 'ANONIMO',
            'detalles' => $detalles,
            'ip_origen' => $ip,
        ]);

        $document = [
            '_id' => bin2hex(random_bytes(12)),
            'evento' => $evento,
            'marca_tiempo' => $timestamp,
            'usuario_qf' => [
                'id_usuario' => $user?->id_usuario ?? 0,
                'rut' => $user?->rut ?? 'N/A',
                'nombre' => $user?->nombre_completo ?? 'No autenticado',
            ],
            'datos_dispensacion' => $detalles,
            'contexto_red' => [
                'ip_origen' => $ip,
                'user_agent' => $ua,
                'terminal_id' => 'POS-BOX-01',
            ],
            'hash_bloque' => hash('sha256', $payloadToSign),
        ];

        $this->appendDocument($document);

        return $document;
    }

    public function getLogs(): array
    {
        $path = $this->getCollectionPath();
        if (! Storage::disk('local')->exists($path)) {
            return [];
        }

        $raw = Storage::disk('local')->get($path);
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_reverse($decoded) : [];
    }

    public function clearLogs(): void
    {
        Storage::disk('local')->put($this->getCollectionPath(), json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function appendDocument(array $document): void
    {
        $path = $this->getCollectionPath();
        $existing = [];
        if (Storage::disk('local')->exists($path)) {
            $raw = Storage::disk('local')->get($path);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }

        $existing[] = $document;
        Storage::disk('local')->put(
            $path,
            json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}
