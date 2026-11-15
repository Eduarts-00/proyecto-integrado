<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('convenios_salud')) {
            Schema::table('convenios_salud', function (Blueprint $table) {
                if (! Schema::hasColumn('convenios_salud', 'fecha_inicio')) {
                    $table->date('fecha_inicio')->nullable()->after('porcentaje_descuento');
                }
                if (! Schema::hasColumn('convenios_salud', 'fecha_fin')) {
                    $table->date('fecha_fin')->nullable()->after('fecha_inicio');
                }
            });

            // Asignar fechas de vigencia institucionales a los convenios existentes con descuento > 0
            DB::table('convenios_salud')
                ->where('porcentaje_descuento', '>', 0)
                ->whereNull('fecha_inicio')
                ->update([
                    'fecha_inicio' => now()->startOfYear()->toDateString(),
                    'fecha_fin' => now()->endOfYear()->addMonths(6)->toDateString(),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('convenios_salud')) {
            Schema::table('convenios_salud', function (Blueprint $table) {
                $cols = [];
                if (Schema::hasColumn('convenios_salud', 'fecha_inicio')) {
                    $cols[] = 'fecha_inicio';
                }
                if (Schema::hasColumn('convenios_salud', 'fecha_fin')) {
                    $cols[] = 'fecha_fin';
                }
                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
