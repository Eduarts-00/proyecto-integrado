<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IspLog extends Model
{
    use HasFactory;

    protected $table = 'libro_control_isp';
    protected $primaryKey = 'id_registro';

    protected $fillable = [
        'id_detalle',
        'id_venta',
        'id_medicamento',
        'id_lote',
        'id_vendedor',
        'id_qf_autorizador',
        'cantidad',
        'folio_receta',
        'rut_paciente',
        'rut_medico',
        'estado',
        'motivo_rechazo',
        'fecha_autorizacion',
        'qf_signature',
        'hash_integridad',
        'datos_carro_json',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'fecha_autorizacion' => 'datetime',
            'activo' => 'boolean',
        ];
    }

    public function detalle(): BelongsTo
    {
        return $this->belongsTo(SaleDetail::class, 'id_detalle', 'id_detalle');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'id_venta', 'id_venta');
    }

    public function medicamento(): BelongsTo
    {
        return $this->belongsTo(Medication::class, 'id_medicamento', 'id_medicamento');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'id_lote', 'id_lote');
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_vendedor', 'id_usuario');
    }

    public function quimicoAutorizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_qf_autorizador', 'id_usuario');
    }
}
