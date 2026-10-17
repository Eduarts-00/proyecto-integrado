<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $table = 'ventas';
    protected $primaryKey = 'id_venta';

    protected $fillable = [
        'id_usuario',
        'id_convenio',
        'fecha_venta',
        'subtotal',
        'descuento_total',
        'iva',
        'total',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_venta' => 'datetime',
            'subtotal' => 'float',
            'descuento_total' => 'float',
            'iva' => 'float',
            'total' => 'float',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(HealthAgreement::class, 'id_convenio', 'id_convenio');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(SaleDetail::class, 'id_venta', 'id_venta');
    }

    public function getFechaAttribute()
    {
        return $this->fecha_venta;
    }
}
