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
        return (int) $this->lotes()
            ->where('activo', true)
            ->where('ubicacion', 'VITRINA')
            ->where('cantidad', '>', 0)
            ->whereDate('fecha_vencimiento', '>', now()->toDateString())
            ->sum('cantidad');
    }

    public function getStockBodegaAttribute(): int
    {
        return (int) $this->lotes()
            ->where('activo', true)
            ->where('ubicacion', 'BODEGA')
            ->where('cantidad', '>', 0)
            ->whereDate('fecha_vencimiento', '>', now()->toDateString())
            ->sum('cantidad');
    }
}
