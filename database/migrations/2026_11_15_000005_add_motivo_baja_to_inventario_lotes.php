<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventario_lotes')) {
            Schema::table('inventario_lotes', function (Blueprint $table) {
                if (! Schema::hasColumn('inventario_lotes', 'motivo_baja')) {
                    $table->string('motivo_baja', 150)->nullable()->after('activo');
                }
                if (! Schema::hasColumn('inventario_lotes', 'fecha_baja')) {
                    $table->timestamp('fecha_baja')->nullable()->after('motivo_baja');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventario_lotes')) {
            Schema::table('inventario_lotes', function (Blueprint $table) {
                $cols = [];
                if (Schema::hasColumn('inventario_lotes', 'motivo_baja')) {
                    $cols[] = 'motivo_baja';
                }
                if (Schema::hasColumn('inventario_lotes', 'fecha_baja')) {
                    $cols[] = 'fecha_baja';
                }
                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
