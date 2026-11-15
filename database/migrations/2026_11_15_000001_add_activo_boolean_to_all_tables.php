<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega el campo booleano `activo` (borrado lógico / habilitación) a todas las tablas
     * del modelo relacional que aún no lo tenían, preservando los datos existentes.
     */
    public function up(): void
    {
        $tables = [
            'roles',
            'medicamentos',
            'inventario_lotes',
            'convenios_salud',
            'usuarios',
            'ventas',
            'detalle_venta',
            'libro_control_isp',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'activo')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->boolean('activo')->default(true)->index();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'roles',
            'medicamentos',
            'inventario_lotes',
            'ventas',
            'detalle_venta',
            'libro_control_isp',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'activo')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('activo');
                });
            }
        }
    }
};
