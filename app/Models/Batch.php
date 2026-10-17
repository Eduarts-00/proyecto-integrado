<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Batch extends Model
{
    use HasFactory;

    protected $table = 'inventario_lotes';
    protected $primaryKey = 'id_lote';

    protected $fillable = [
        'id_medicamento',
        'lote_codigo',
        'ubicacion',
        'cantidad',
        'fecha_vencimiento',
        'fecha_ingreso',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'fecha_vencimiento' => 'date',
            'fecha_ingreso' => 'date',
        ];
    }

    public function medicamento(): BelongsTo
    {
        return $this->belongsTo(Medication::class, 'id_medicamento', 'id_medicamento');
    }

    public function getExpiryDateAttribute(): ?Carbon
    {
        return $this->fecha_vencimiento;
    }

    public function getQuantityAttribute(): int
    {
        return (int) $this->cantidad;
    }

    public function getLocationAttribute(): string
    {
        return $this->ubicacion;
    }

    public function getDiasRestantesAttribute(): int
    {
        if (! $this->fecha_vencimiento) {
            return 0;
        }

        return (int) Carbon::today()->diffInDays($this->fecha_vencimiento->copy()->startOfDay(), false);
    }

    public function getSemaforoFefoAttribute(): string
    {
        $dias = $this->dias_restantes;
        if ($dias <= 0) {
            return 'vencido';
        }
        if ($dias < 20) {
            return 'critico';
        }
        if ($dias <= 30) {
            return 'alerta';
        }

        return 'optimo';
    }
}
