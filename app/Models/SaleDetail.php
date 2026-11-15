<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SaleDetail extends Model
{
    use HasFactory;

    protected $table = 'detalle_venta';
    protected $primaryKey = 'id_detalle';

    protected $fillable = [
        'id_venta',
        'id_lote',
        'id_medicamento',
        'cantidad',
        'precio_unitario',
        'subtotal_linea',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'precio_unitario' => 'float',
            'subtotal_linea' => 'float',
            'activo' => 'boolean',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'id_venta', 'id_venta');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'id_lote', 'id_lote');
    }

    public function medicamento(): BelongsTo
    {
        return $this->belongsTo(Medication::class, 'id_medicamento', 'id_medicamento');
    }

    public function registroIsp(): HasOne
    {
        return $this->hasOne(IspLog::class, 'id_detalle', 'id_detalle');
    }
}
