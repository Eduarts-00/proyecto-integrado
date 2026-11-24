<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medication extends Model
{
    use HasFactory;

    protected $table = 'medicamentos';
    protected $primaryKey = 'id_medicamento';

    protected $fillable = [
        'codigo_barras',
        'nombre_comercial',
        'principio_activo',
        'laboratorio',
        'categoria',
        'precio_unitario',
        'requiere_receta',
        'tipo_controlado',
        'stock_minimo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio_unitario' => 'float',
            'requiere_receta' => 'boolean',
            'tipo_controlado' => 'boolean',
            'stock_minimo' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Batch::class, 'id_medicamento', 'id_medicamento');
    }

    public function convenios(): BelongsToMany
    {
        return $this->belongsToMany(
            HealthAgreement::class,
            'convenio_medicamento',
            'id_medicamento',
            'id_convenio',
            'id_medicamento',
            'id_convenio'
        )->withPivot(['porcentaje_especifico', 'activo'])
            ->wherePivot('activo', true)
            ->withTimestamps();
    }

    public function getBarcodeAttribute(): string
    {
        return $this->codigo_barras;
    }

    public function getRequiresPrescriptionAttribute(): bool
    {
        return (bool) ($this->requiere_receta || $this->tipo_controlado);
    }

    public function getStockVitrinaAttribute(): int
    {
        if ($this->relationLoaded('lotes')) {
            return (int) $this->lotes
                ->where('activo', true)
                ->where('ubicacion', 'VITRINA')
                ->where('cantidad', '>', 0)
                ->sum('cantidad');
        }

        return (int) $this->lotes()
            ->where('activo', true)
            ->where('ubicacion', 'VITRINA')
            ->where('cantidad', '>', 0)
            ->sum('cantidad');
    }

    public function getStockBodegaAttribute(): int
    {
        if ($this->relationLoaded('lotes')) {
            return (int) $this->lotes
                ->where('activo', true)
                ->where('ubicacion', 'BODEGA')
                ->where('cantidad', '>', 0)
                ->sum('cantidad');
        }

        return (int) $this->lotes()
            ->where('activo', true)
            ->where('ubicacion', 'BODEGA')
            ->where('cantidad', '>', 0)
            ->sum('cantidad');
    }

    public function getStockTotalAttribute(): int
    {
        return $this->stock_vitrina + $this->stock_bodega;
    }

    public function getUmbralStockBajoAttribute(): int
    {
        return max(15, (int) ($this->stock_minimo ?: 25));
    }

    public function getTieneStockBajoAttribute(): bool
    {
        if (! $this->activo) {
            return false;
        }

        return $this->stock_vitrina <= $this->umbral_stock_bajo;
    }

    public function getNivelStockAttribute(): string
    {
        $vitrina = $this->stock_vitrina;
        $bodega = $this->stock_bodega;
        $umbral = $this->umbral_stock_bajo;

        if ($vitrina <= 0 && $bodega <= 0) {
            return 'agotado';
        }

        if ($vitrina <= 0 && $bodega > 0) {
            return 'sin_vitrina';
        }

        if ($vitrina <= $umbral) {
            return 'bajo';
        }

        return 'optimo';
    }
}
