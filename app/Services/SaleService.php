<?php

namespace App\Services;

use App\Models\HealthAgreement;
use App\Models\IspLog;
use App\Models\Medication;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SaleService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly AuditLogService $auditLogService
    ) {}

    /**
     * Calcula subtotal, descuento por convenio, IVA (19% incluido) y copago final (CP-02).
     */
    public function calculateCopay(float $subtotal, ?HealthAgreement $convenio = null): array
    {
        $porcentaje = $convenio && $convenio->activo ? (float) $convenio->porcentaje_descuento : 0.0;
        $descuento = round($subtotal * ($porcentaje / 100), 0);
        $total = max(0.0, round($subtotal - $descuento, 0));
        $neto = round($total / 1.19, 0);
        $iva = round($total - $neto, 0);

        return [
            'subtotal' => round($subtotal, 0),
            'porcentaje_descuento' => $porcentaje,
            'descuento_total' => $descuento,
            'neto' => $neto,
            'iva' => $iva,
            'total' => $total,
        ];
    }

    public function verifyStockAvailability(int $idMedicamento, int $cantidad): bool
    {
        $med = Medication::find($idMedicamento);
        if (! $med) {
            return false;
        }

        return $med->stock_vitrina >= $cantidad;
    }

    /**
     * Ejecuta la transacción completa de venta en mostrador (POS) bajo integridad ACID (CP-03, CP-04, CP-05).
     *
     * @param  array<int, array{id_medicamento: int, cantidad: int}>  $items
     * @param  array{id_registro?: int, qf_user_id?: int, pin_qf?: string, rut_paciente?: string, rut_medico?: string, folio_receta?: string}|null  $autorizacionIsp
     */
    public function executeTransaction(
        array $items,
        User $vendedor,
        ?int $idConvenio = null,
        ?array $autorizacionIsp = null
    ): Sale {
        if (empty($items)) {
            throw new RuntimeException('El carro de compras está vacío.', 422);
        }

        return DB::transaction(function () use ($items, $vendedor, $idConvenio, $autorizacionIsp) {
            $subtotal = 0.0;
            $requiereControlIsp = false;
            $medicamentosMap = [];

            foreach ($items as $item) {
                $med = Medication::findOrFail((int) $item['id_medicamento']);
                $cantidad = (int) ($item['cantidad'] ?? 1);

                if ($cantidad <= 0) {
                    throw new RuntimeException('Cantidad inválida para ' . $med->nombre_comercial, 422);
                }

                if ($med->tipo_controlado || $med->requiere_receta) {
                    $requiereControlIsp = true;
                }

                $subtotal += $med->precio_unitario * $cantidad;
                $medicamentosMap[$med->id_medicamento] = $med;
            }

            $qfAutorizador = null;
            $registroIspExistente = null;

            if ($requiereControlIsp) {
                if (empty($autorizacionIsp)) {
                    throw new RuntimeException('Requiere Autorización Q.F.', 403);
                }

                if (! empty($autorizacionIsp['id_registro'])) {
                    $registroIspExistente = IspLog::where('id_registro', $autorizacionIsp['id_registro'])
                        ->where('estado', 'AUTORIZADO')
                        ->first();
                }

                if ($registroIspExistente) {
                    $qfAutorizador = $registroIspExistente->quimicoAutorizador;
                } else {
                    $qfUserId = $autorizacionIsp['qf_user_id'] ?? null;
                    $pinQf = $autorizacionIsp['pin_qf'] ?? '';
                    $rutPaciente = trim($autorizacionIsp['rut_paciente'] ?? '');
                    $rutMedico = trim($autorizacionIsp['rut_medico'] ?? '');
                    $folioReceta = trim($autorizacionIsp['folio_receta'] ?? '');

                    if (! $qfUserId || ! $pinQf || ! $rutPaciente || ! $rutMedico || ! $folioReceta) {
                        throw new RuntimeException('Requiere Autorización Q.F. con datos completos de receta retenida.', 403);
                    }

                    $qfAutorizador = User::with('rol')->find($qfUserId);
                    if (! $qfAutorizador || $qfAutorizador->role !== 'quimico_farmaceutico') {
                        throw new RuntimeException('El usuario autorizador no posee el rol de Químico Farmacéutico.', 403);
                    }

                    if (! $qfAutorizador->pin_qf || ! Hash::check($pinQf, $qfAutorizador->pin_qf)) {
                        throw new RuntimeException('PIN criptográfico del Químico Farmacéutico inválido.', 403);
                    }
                }
            }

            $convenio = $idConvenio ? HealthAgreement::find($idConvenio) : null;
            $liquidacion = $this->calculateCopay($subtotal, $convenio);

            $venta = Sale::create([
                'id_usuario' => $vendedor->id_usuario,
                'id_convenio' => $convenio?->id_convenio,
                'fecha_venta' => now(),
                'subtotal' => $liquidacion['subtotal'],
                'descuento_total' => $liquidacion['descuento_total'],
                'iva' => $liquidacion['iva'],
                'total' => $liquidacion['total'],
                'estado' => 'COMPLETADA',
            ]);

            foreach ($items as $item) {
                $med = $medicamentosMap[(int) $item['id_medicamento']];
                $cantidad = (int) $item['cantidad'];

                $descuentosLote = $this->inventoryService->deductByFefo($med->id_medicamento, $cantidad, 'VITRINA');

                foreach ($descuentosLote as $descuentoLote) {
                    /** @var \App\Models\Batch $batch */
                    $batch = $descuentoLote['batch'];
                    $cantLote = (int) $descuentoLote['cantidad'];

                    $detalle = SaleDetail::create([
                        'id_venta' => $venta->id_venta,
                        'id_lote' => $batch->id_lote,
                        'id_medicamento' => $med->id_medicamento,
                        'cantidad' => $cantLote,
                        'precio_unitario' => $med->precio_unitario,
                        'subtotal_linea' => round($med->precio_unitario * $cantLote, 0),
                    ]);

                    if (($med->tipo_controlado || $med->requiere_receta) && $qfAutorizador) {
                        $folio = $registroIspExistente?->folio_receta ?? ($autorizacionIsp['folio_receta'] ?? 'REC-' . now()->format('Y-His'));
                        $rutPac = $registroIspExistente?->rut_paciente ?? ($autorizacionIsp['rut_paciente'] ?? '');
                        $rutMed = $registroIspExistente?->rut_medico ?? ($autorizacionIsp['rut_medico'] ?? '');

                        $hashIntegridad = hash('sha256', implode('|', [
                            $folio,
                            $rutPac,
                            $rutMed,
                            $qfAutorizador->rut,
                            $med->nombre_comercial,
                            $batch->lote_codigo,
                            now()->toIso8601String(),
                        ]));

                        $qfSignature = 'SIG-QF-' . strtoupper(substr($hashIntegridad, 0, 16));

                        if ($registroIspExistente && ! $registroIspExistente->id_detalle) {
                            $registroIspExistente->update([
                                'id_detalle' => $detalle->id_detalle,
                                'id_venta' => $venta->id_venta,
                                'id_lote' => $batch->id_lote,
                                'qf_signature' => $qfSignature,
                                'hash_integridad' => $hashIntegridad,
                            ]);
                        } else {
                            IspLog::create([
                                'id_detalle' => $detalle->id_detalle,
                                'id_venta' => $venta->id_venta,
                                'id_medicamento' => $med->id_medicamento,
                                'id_lote' => $batch->id_lote,
                                'id_vendedor' => $vendedor->id_usuario,
                                'id_qf_autorizador' => $qfAutorizador->id_usuario,
                                'cantidad' => $cantLote,
                                'folio_receta' => $folio,
                                'rut_paciente' => $rutPac,
                                'rut_medico' => $rutMed,
                                'estado' => 'AUTORIZADO',
                                'fecha_autorizacion' => now(),
                                'qf_signature' => $qfSignature,
                                'hash_integridad' => $hashIntegridad,
                            ]);
                        }

                        $this->auditLogService->recordIspAuthorization($qfAutorizador, [
                            'folio_receta' => $folio,
                            'rut_paciente' => $rutPac,
                            'rut_medico' => $rutMed,
                            'medicamento' => $med->nombre_comercial,
                            'lote_codigo' => $batch->lote_codigo,
                        ]);
                    }
                }
            }

            return $venta->load(['detalles.medicamento', 'detalles.lote', 'convenio', 'usuario']);
        });
    }
}
