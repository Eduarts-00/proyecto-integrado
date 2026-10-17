<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HealthAgreement extends Model
{
    use HasFactory;

    protected $table = 'convenios_salud';
    protected $primaryKey = 'id_convenio';

    protected $fillable = [
        'nombre_convenio',
        'porcentaje_descuento',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'porcentaje_descuento' => 'float',
            'activo' => 'boolean',
        ];
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Sale::class, 'id_convenio', 'id_convenio');
    }

    public function calcularAhorroEjemplo(float $montoBase = 10000): float
    {
        return round($montoBase * ($this->porcentaje_descuento / 100), 0);
    }
}
