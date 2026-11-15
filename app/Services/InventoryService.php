<?php

namespace App\Services;

use App\Models\Batch;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryService
{
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
        if ($cantidadRequerida <= 0) {
            throw new RuntimeException('La cantidad requerida debe ser mayor a cero.', 422);
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
     * Traspasa unidades desde un lote en BODEGA hacia VITRINA validando que no esté vencido.
     */
    public function transferToDisplay(int $idLoteBodega, int $cantidad): Batch
    {
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

            if ($loteBodega->cantidad < $cantidad || $cantidad <= 0) {
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
     * Retira o devuelve unidades desde un lote en VITRINA hacia BODEGA
     * (por sobrestock, reorganización de mesón, custodia Q.F. o retiro preventivo FEFO).
     */
    public function transferToStorage(int $idLoteVitrina, int $cantidad): Batch
    {
        return DB::transaction(function () use ($idLoteVitrina, $cantidad) {
            $loteVitrina = Batch::query()
                ->where('id_lote', $idLoteVitrina)
                ->where('ubicacion', 'VITRINA')
                ->lockForUpdate()
                ->firstOrFail();

            if ($loteVitrina->cantidad < $cantidad || $cantidad <= 0) {
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
}
