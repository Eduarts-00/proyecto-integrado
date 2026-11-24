<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            if (! Schema::hasColumn('usuarios', 'acceso_rapido')) {
                $table->boolean('acceso_rapido')->default(false)->after('pin_qf');
            }
            if (! Schema::hasColumn('usuarios', 'pin_acceso')) {
                $table->string('pin_acceso')->nullable()->after('acceso_rapido');
            }
        });

        // Asignar PIN de 4 dígitos por defecto (1234) y habilitar en acceso rápido a los usuarios iniciales activos
        $defaultPinHash = Hash::make('1234');
        DB::table('usuarios')->whereNull('pin_acceso')->update([
            'acceso_rapido' => true,
            'pin_acceso' => $defaultPinHash,
        ]);
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            if (Schema::hasColumn('usuarios', 'pin_acceso')) {
                $table->dropColumn('pin_acceso');
            }
            if (Schema::hasColumn('usuarios', 'acceso_rapido')) {
                $table->dropColumn('acceso_rapido');
            }
        });
    }
};
