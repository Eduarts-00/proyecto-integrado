<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Medication;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Capa de Servicio de Dominio y Persistencia de Inventario.
 * Separa y aísla todas las lecturas/escrituras de Base de Datos respecto del Frontend y Controladores HTTP.
 */
class InventoryService
{
    public const MAX_PRECIO_UNITARIO = 9999999;

    public const MAX_CANTIDAD_LOTE = 100000;

    /**
     * Retorna el lote disponible con fecha de vencimiento más próxima (FEFO).
     * Emplea lockForUpdate() cuando se invoca dentro de una transacción ACID (CP-01 / CP-05).
     */
    public function getFefoBatch(
        int $idMedicamento,
        string $ubicacion = 'VITRINA',
        bool $lockForUpdate = false
    ): ?Batch {
        $query = Batch::query()
            ->where('id_medicamento', $idMedicamento)
            ->where('activo', true)
            ->where('ubicacion', $ubicacion)
            ->where('cantidad', '>', 0)
            ->whereDate('fecha_vencimiento', '>', Carbon::today()->toDateString())
            ->orderBy('fecha_vencimiento', 'asc')
            ->orderBy('id_lote', 'asc');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Descuenta unidades del inventario aplicando estrictamente la regla FEFO con bloqueo pesimista.
     * Retorna el detalle de lotes afectados: [['batch' => Batch, 'cantidad' => int], ...]
     */
    public function deductByFefo(
        int $idMedicamento,
        int $cantidadRequerida,
        string $ubicacion = 'VITRINA'
    ): array {
        if ($cantidadRequerida <= 0 || $cantidadRequerida > self::MAX_CANTIDAD_LOTE) {
            throw new RuntimeException('La cantidad requerida está fuera del rango operativo permitido.', 422);
        }

        $lotesVigentes = Batch::query()
            ->where('id_medicamento', $idMedicamento)
            ->where('activo', true)
            ->where('ubicacion', $ubicacion)
            ->where('cantidad', '>', 0)
            ->whereDate('fecha_vencimiento', '>', Carbon::today()->toDateString())
            ->orderBy('fecha_vencimiento', 'asc')
            ->orderBy('id_lote', 'asc')
            ->lockForUpdate()
            ->get();

        $stockTotalDisponible = $lotesVigentes->sum('cantidad');

        if ($stockTotalDisponible < $cantidadRequerida) {
            throw new RuntimeException('Stock insuficiente en ' . $ubicacion . ' para completar la operación.', 409);
        }

        $restante = $cantidadRequerida;
        $descuentosAplicados = [];

        foreach ($lotesVigentes as $lote) {
            if ($restante <= 0) {
                break;
            }

            $descontarDeEsteLote = min($lote->cantidad, $restante);
            $lote->cantidad -= $descontarDeEsteLote;
            $lote->save();

            $restante -= $descontarDeEsteLote;
            $descuentosAplicados[] = [
                'batch' => $lote,
                'cantidad' => $descontarDeEsteLote,
            ];
        }

        return $descuentosAplicados;
    }

    /**
     * Registra una nueva remesa física de lote en Bodega o Vitrina bajo transacción ACID.
     *
     * @param  array<string, mixed>  $data
     */
    public function registerBatch(array $data): Batch
    {
        $cantidad = (int) ($data['cantidad'] ?? 0);
        if ($cantidad < 1 || $cantidad > self::MAX_CANTIDAD_LOTE) {
            throw new RuntimeException('La cantidad del lote excede los límites permitidos (1 a 100.000 unidades).', 422);
        }

        return DB::transaction(function () use ($data, $cantidad) {
            return Batch::create([
                'id_medicamento' => (int) $data['id_medicamento'],
                'lote_codigo' => strtoupper(trim((string) $data['lote_codigo'])),
                'ubicacion' => (string) $data['ubicacion'],
                'cantidad' => $cantidad,
                'fecha_vencimiento' => (string) $data['fecha_vencimiento'],
                'fecha_ingreso' => now()->toDateString(),
                'activo' => true,
            ]);
        });
    }

    /**
     * Traspasa unidades desde un lote en BODEGA hacia VITRINA validando que no esté vencido.
     */
    public function transferToDisplay(int $idLoteBodega, int $cantidad): Batch
    {
        if ($cantidad <= 0 || $cantidad > self::MAX_CANTIDAD_LOTE) {
            throw new RuntimeException('Cantidad de traspaso fuera del rango permitido.', 422);
        }

        return DB::transaction(function () use ($idLoteBodega, $cantidad) {
            $loteBodega = Batch::query()
                ->where('id_lote', $idLoteBodega)
                ->where('activo', true)
                ->where('ubicacion', 'BODEGA')
                ->lockForUpdate()
                ->firstOrFail();

            if ($loteBodega->dias_restantes <= 0) {
                throw new RuntimeException('Bloqueo sanitario: No se puede traspasar a vitrina un lote vencido.', 422);
            }

            if ($loteBodega->cantidad < $cantidad) {
                throw new RuntimeException('Cantidad de traspaso inválida o superior al stock en bodega.', 409);
            }

            $loteBodega->cantidad -= $cantidad;
            $loteBodega->save();

            $loteVitrina = Batch::query()
                ->where('id_medicamento', $loteBodega->id_medicamento)
                ->where('lote_codigo', $loteBodega->lote_codigo)
                ->where('ubicacion', 'VITRINA')
                ->lockForUpdate()
                ->first();

            if ($loteVitrina) {
                $loteVitrina->cantidad += $cantidad;
                $loteVitrina->save();

                return $loteVitrina;
            }

            return Batch::create([
                'id_medicamento' => $loteBodega->id_medicamento,
                'lote_codigo' => $loteBodega->lote_codigo,
                'ubicacion' => 'VITRINA',
                'cantidad' => $cantidad,
                'fecha_vencimiento' => $loteBodega->fecha_vencimiento->toDateString(),
                'fecha_ingreso' => Carbon::today()->toDateString(),
            ]);
        });
    }

    /**
     * Retira o devuelve unidades desde un lote en VITRINA hacia BODEGA.
     */
    public function transferToStorage(int $idLoteVitrina, int $cantidad): Batch
    {
        if ($cantidad <= 0 || $cantidad > self::MAX_CANTIDAD_LOTE) {
            throw new RuntimeException('Cantidad a retirar fuera del rango permitido.', 422);
        }

        return DB::transaction(function () use ($idLoteVitrina, $cantidad) {
            $loteVitrina = Batch::query()
                ->where('id_lote', $idLoteVitrina)
                ->where('ubicacion', 'VITRINA')
                ->lockForUpdate()
                ->firstOrFail();

            if ($loteVitrina->cantidad < $cantidad) {
                throw new RuntimeException('Cantidad a retirar inválida o superior al stock disponible en Vitrina.', 409);
            }

            $loteVitrina->cantidad -= $cantidad;
            $loteVitrina->save();

            $loteBodega = Batch::query()
                ->where('id_medicamento', $loteVitrina->id_medicamento)
                ->where('lote_codigo', $loteVitrina->lote_codigo)
                ->where('ubicacion', 'BODEGA')
                ->lockForUpdate()
                ->first();

            if ($loteBodega) {
                $loteBodega->cantidad += $cantidad;
                $loteBodega->save();

                return $loteBodega;
            }

            return Batch::create([
                'id_medicamento' => $loteVitrina->id_medicamento,
                'lote_codigo' => $loteVitrina->lote_codigo,
                'ubicacion' => 'BODEGA',
                'cantidad' => $cantidad,
                'fecha_vencimiento' => $loteVitrina->fecha_vencimiento->toDateString(),
                'fecha_ingreso' => Carbon::today()->toDateString(),
                'activo' => $loteVitrina->activo,
            ]);
        });
    }

    /**
     * Da de baja sanitaria un lote o lo rehabilita dentro de una transacción segura.
     */
    public function toggleBatchStatus(int $idLote, ?string $motivoInput = null): Batch
    {
        return DB::transaction(function () use ($idLote, $motivoInput) {
            $lote = Batch::with('medicamento')->lockForUpdate()->findOrFail($idLote);
            $nuevoActivo = ! $lote->activo;

            if (! $nuevoActivo) {
                $motivoPorDefecto = $lote->dias_restantes <= 30
                    ? 'Vencimiento / Producto Caducado'
                    : 'Merma / Rotura o Deterioro de Envase';
                $motivo = trim((string) ($motivoInput ?? $motivoPorDefecto));

                $lote->activo = false;
                $lote->motivo_baja = $motivo !== '' ? $motivo : $motivoPorDefecto;
                $lote->fecha_baja = now();
            } else {
                $lote->activo = true;
                $lote->motivo_baja = null;
                $lote->fecha_baja = null;
            }

            $lote->save();

            return $lote;
        });
    }

    /**
     * Crea un medicamento en el catálogo (y su lote inicial opcional) validando cotas de dominio.
     *
     * @param  array<string, mixed>  $data
     */
    public function createMedicationWithInitialBatch(array $data): Medication
    {
        $precio = (float) ($data['precio_unitario'] ?? 0);
        if ($precio < 1 || $precio > self::MAX_PRECIO_UNITARIO) {
            throw new RuntimeException('El precio unitario está fuera del rango permitido ($1 a $9.999.999 CLP).', 422);
        }

        return DB::transaction(function () use ($data, $precio) {
            $esRestringido = ($data['condicion_receta'] ?? '') === 'receta_retenida';
            $categoria = ! empty($data['categoria'])
                ? trim((string) $data['categoria'])
                : ($esRestringido ? 'Controlado (Receta Retenida ISP)' : 'Venta Directa');

            $stockMinimo = isset($data['stock_minimo']) ? min(10000, max(1, (int) $data['stock_minimo'])) : 25;

            $medicamento = Medication::create([
                'codigo_barras' => trim((string) $data['codigo_barras']),
                'nombre_comercial' => trim((string) $data['nombre_comercial']),
                'principio_activo' => trim((string) $data['principio_activo']),
                'laboratorio' => trim((string) $data['laboratorio']),
                'categoria' => $categoria,
                'precio_unitario' => round($precio, 2),
                'requiere_receta' => $esRestringido,
                'tipo_controlado' => $esRestringido,
                'stock_minimo' => $stockMinimo,
                'activo' => true,
            ]);

            $cantidadInicial = min(self::MAX_CANTIDAD_LOTE, max(0, (int) ($data['cantidad_inicial'] ?? 0)));
            if ($cantidadInicial > 0 && ! empty($data['lote_codigo']) && ! empty($data['fecha_vencimiento'])) {
                Batch::create([
                    'id_medicamento' => $medicamento->id_medicamento,
                    'lote_codigo' => strtoupper(trim((string) $data['lote_codigo'])),
                    'ubicacion' => (string) ($data['ubicacion_inicial'] ?? 'BODEGA'),
                    'cantidad' => $cantidadInicial,
                    'fecha_vencimiento' => (string) $data['fecha_vencimiento'],
                    'fecha_ingreso' => now()->toDateString(),
                    'activo' => true,
                ]);
            }

            return $medicamento;
        });
    }

    /**
     * Actualiza un medicamento existente garantizando límites de columna DECIMAL(10,2) y aislamiento transaccional.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateMedication(int $idMedicamento, array $data): Medication
    {
        $precio = (float) ($data['precio_unitario'] ?? 0);
        if ($precio < 1 || $precio > self::MAX_PRECIO_UNITARIO) {
            throw new RuntimeException('El precio unitario está fuera del rango permitido ($1 a $9.999.999 CLP).', 422);
        }

        return DB::transaction(function () use ($idMedicamento, $data, $precio) {
            $medicamento = Medication::lockForUpdate()->findOrFail($idMedicamento);
            $esRestringido = ($data['condicion_receta'] ?? '') === 'receta_retenida';

            $stockMinimo = isset($data['stock_minimo']) && $data['stock_minimo'] !== ''
                ? min(10000, max(1, (int) $data['stock_minimo']))
                : ($medicamento->stock_minimo ?: 25);

            $medicamento->update([
                'nombre_comercial' => trim((string) $data['nombre_comercial']),
                'principio_activo' => trim((string) $data['principio_activo']),
                'laboratorio' => trim((string) $data['laboratorio']),
                'categoria' => ! empty($data['categoria']) ? trim((string) $data['categoria']) : $medicamento->categoria,
                'precio_unitario' => round($precio, 2),
                'requiere_receta' => $esRestringido,
                'tipo_controlado' => $esRestringido,
                'stock_minimo' => $stockMinimo,
                'activo' => (bool) $data['activo'],
            ]);

            return $medicamento;
        });
    }

    /**
     * Alterna la condición de venta restringida (Receta Retenida ISP vs. Venta Libre).
     */
    public function togglePrescriptionRestriction(int $idMedicamento): Medication
    {
        return DB::transaction(function () use ($idMedicamento) {
            $medicamento = Medication::lockForUpdate()->findOrFail($idMedicamento);
            $nuevoEstadoControlado = ! ($medicamento->requiere_receta || $medicamento->tipo_controlado);

            $medicamento->update([
                'requiere_receta' => $nuevoEstadoControlado,
                'tipo_controlado' => $nuevoEstadoControlado,
            ]);

            return $medicamento;
        });
    }

    /**
     * Obtiene la colección de lotes próximos a vencer (<= $daysThreshold días) ordenados por FEFO.
     */
    public function checkExpiryAlerts(int $daysThreshold = 30): Collection
    {
        $limite = Carbon::today()->addDays($daysThreshold)->toDateString();

        return Batch::with('medicamento')
            ->where('activo', true)
            ->where('cantidad', '>', 0)
            ->whereDate('fecha_vencimiento', '<=', $limite)
            ->orderBy('fecha_vencimiento', 'asc')
            ->get();
    }

    /**
     * Obtiene los medicamentos activos con stock bajo o agotado en Vitrina (<= umbral_stock_bajo),
     * ordenados de menor a mayor disponibilidad en Vitrina para priorizar su reposición.
     */
    public function checkLowStockAlerts(): \Illuminate\Support\Collection
    {
        return Medication::with('lotes')
            ->where('activo', true)
            ->get()
            ->filter(fn (Medication $m) => $m->tiene_stock_bajo)
            ->sortBy([
                fn (Medication $a, Medication $b) => $a->stock_vitrina <=> $b->stock_vitrina,
                fn (Medication $a, Medication $b) => $a->stock_total <=> $b->stock_total,
                fn (Medication $a, Medication $b) => strcmp($a->nombre_comercial, $b->nombre_comercial),
            ])
            ->values();
    }
}
