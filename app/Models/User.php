<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';

    protected $fillable = [
        'id_rol',
        'rut',
        'nombre_completo',
        'email',
        'password_hash',
        'pin_qf',
        'acceso_rapido',
        'pin_acceso',
        'activo',
    ];

    protected $hidden = [
        'password_hash',
        'pin_qf',
        'pin_acceso',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'acceso_rapido' => 'boolean',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'id_rol', 'id_rol');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Sale::class, 'id_usuario', 'id_usuario');
    }

    public function getRoleAttribute(): string
    {
        return $this->rol?->codigo_rol ?? 'vendedor';
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'vendedor' => 'Vendedor',
            'quimico_farmaceutico' => 'Q. Farmacéutico',
            'bodeguero' => 'Bodeguero',
            'administrador' => 'Administrador',
            default => ucfirst($this->role),
        };
    }

    public function getNameAttribute(): string
    {
        return $this->nombre_completo;
    }

    /**
     * Verifica permisos RBAC bajo el principio de menor privilegio (Capítulo 7.1).
     */
    public function hasPermission(string $permission): bool
    {
        $role = $this->role;

        $matrix = [
            'vendedor' => [
                'Can_Sell_OTC',
                'pos.view',
                'pos.sell',
                'convenios.view',
                'inventario.view_vitrina',
            ],
            'quimico_farmaceutico' => [
                'Can_Sell_OTC',
                'Can_Authorize_Prescription',
                'Can_Manage_Medications',
                'Can_Deactivate_Medications',
                'pos.view',
                'pos.sell',
                'autorizaciones.view',
                'autorizaciones.manage',
                'libro_isp.view',
                'inventario.view',
                'convenios.view',
            ],
            'bodeguero' => [
                'Can_Manage_Stock',
                'inventario.view',
                'inventario.manage',
            ],
            'administrador' => [
                'Can_Audit_Logs',
                'Can_Manage_Users',
                'Can_Manage_Stock',
                'Can_Manage_Medications',
                'dashboard.view',
                'dashboard.export',
                'pos.view',
                'autorizaciones.view',
                'libro_isp.view',
                'inventario.view',
                'inventario.manage',
                'convenios.view',
                'convenios.manage',
                'usuarios.manage',
                'auditoria.view',
            ],
        ];

        return in_array($permission, $matrix[$role] ?? [], true);
    }
}
