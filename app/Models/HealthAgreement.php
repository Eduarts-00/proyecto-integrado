<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HealthAgreement extends Model
{
    use HasFactory;

    protected $table = 'convenios_salud';
    protected $primaryKey = 'id_convenio';

    protected $fillable = [
        'nombre_convenio',
        'porcentaje_descuento',
        'aplica_todo_catalogo',
        'fecha_inicio',
        'fecha_fin',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'porcentaje_descuento' => 'float',
            'aplica_todo_catalogo' => 'boolean',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'activo' => 'boolean',
        ];
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Sale::class, 'id_convenio', 'id_convenio');
    }

    /**
     * Medicamentos adheridos al Vademécum / Petitorio de este Convenio de Salud.
     */
    public function medicamentos(): BelongsToMany
    {
        return $this->belongsToMany(
            Medication::class,
            'convenio_medicamento',
            'id_convenio',
            'id_medicamento',
            'id_convenio',
            'id_medicamento'
        )->withPivot(['porcentaje_especifico', 'activo'])
            ->wherePivot('activo', true)
            ->withTimestamps();
    }

    /**
     * Determina si un medicamento específico está cubierto por el Vademécum del convenio.
     */
    public function cubreMedicamento(int|Medication $medicamento): bool
    {
        return $this->obtenerPorcentajeParaMedicamento($medicamento) > 0;
    }

    /**
     * Retorna el porcentaje de cobertura aplicable a un medicamento específico según el Vademécum.
     */
    public function obtenerPorcentajeParaMedicamento(int|Medication $medicamento): float
    {
        if (! $this->esta_vigente || $this->porcentaje_descuento <= 0) {
            return 0.0;
        }

        if ($this->aplica_todo_catalogo) {
            return (float) $this->porcentaje_descuento;
        }

        $idMed = $medicamento instanceof Medication ? $medicamento->id_medicamento : (int) $medicamento;

        if ($this->relationLoaded('medicamentos')) {
            $medAdherido = $this->medicamentos->firstWhere('id_medicamento', $idMed);
        } else {
            $medAdherido = $this->medicamentos()->where('medicamentos.id_medicamento', $idMed)->first();
        }

        if (! $medAdherido) {
            return 0.0;
        }

        $pctEspecifico = $medAdherido->pivot?->porcentaje_especifico;

        return $pctEspecifico !== null ? (float) $pctEspecifico : (float) $this->porcentaje_descuento;
    }

    /**
     * Filtra únicamente los convenios habilitados y dentro de su rango de fechas de vigencia.
     */
    public function scopeVigentes(Builder $query): Builder
    {
        $hoy = Carbon::today()->toDateString();

        return $query->where('activo', true)
            ->where(function (Builder $q) use ($hoy) {
                $q->whereNull('fecha_inicio')
                    ->orWhereDate('fecha_inicio', '<=', $hoy);
            })
            ->where(function (Builder $q) use ($hoy) {
                $q->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $hoy);
            });
    }

    public function getEstaVigenteAttribute(): bool
    {
        if (! $this->activo) {
            return false;
        }

        $hoy = Carbon::today();

        if ($this->fecha_inicio && $hoy->lt($this->fecha_inicio->copy()->startOfDay())) {
            return false;
        }

        if ($this->fecha_fin && $hoy->gt($this->fecha_fin->copy()->endOfDay())) {
            return false;
        }

        return true;
    }

    public function getEstadoVigenciaAttribute(): string
    {
        if (! $this->activo) {
            return 'DESHABILITADO';
        }

        $hoy = Carbon::today();

        if ($this->fecha_inicio && $hoy->lt($this->fecha_inicio->copy()->startOfDay())) {
            return 'PROGRAMADO';
        }

        if ($this->fecha_fin && $hoy->gt($this->fecha_fin->copy()->endOfDay())) {
            return 'VENCIDO';
        }

        return 'VIGENTE';
    }

    public function calcularAhorroEjemplo(float $montoBase = 10000): float
    {
        return round($montoBase * ($this->porcentaje_descuento / 100), 0);
    }
}
