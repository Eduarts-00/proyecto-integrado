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
                if (! Schema::hasColumn('convenios_salud', 'aplica_todo_catalogo')) {
                    $table->boolean('aplica_todo_catalogo')->default(true)->after('porcentaje_descuento');
                }
            });
        }

        if (Schema::hasTable('convenio_medicamento')) {
            Schema::dropIfExists('convenio_medicamento');
        }

        Schema::create('convenio_medicamento', function (Blueprint $table) {
            $table->increments('id_convenio_medicamento');
            $table->unsignedInteger('id_convenio');
            $table->unsignedInteger('id_medicamento');
            $table->decimal('porcentaje_especifico', 5, 2)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('id_convenio')
                ->references('id_convenio')
                ->on('convenios_salud')
                ->cascadeOnDelete();

            $table->foreign('id_medicamento')
                ->references('id_medicamento')
                ->on('medicamentos')
                ->cascadeOnDelete();

            $table->unique(['id_convenio', 'id_medicamento']);
        });

        // Poblar Vademécum realista para los convenios existentes en la base de datos activa
        if (Schema::hasTable('convenios_salud') && Schema::hasTable('medicamentos')) {
            $meds = DB::table('medicamentos')->pluck('id_medicamento', 'codigo_barras');
            if ($meds->isNotEmpty()) {
                $vademecumPorConvenio = [
                    // GES / AUGE: Exclusivo canasta de patologías crónicas GES (Hipertensión, Diabetes, Dislipidemia, Gastritis crónica)
                    'GES / AUGE' => ['780000100102', '780000100107', '780000100109', '780000100110'],
                    // Fonasa Ley Cenabast: Petitorio de medicamentos esenciales y crónicos (excluye psicotrópicos de marca sin bioequivalencia)
                    'Fonasa A' => ['780000100102', '780000100104', '780000100106', '780000100107', '780000100108', '780000100109', '780000100110', '780000100111'],
                    'Fonasa B' => ['780000100102', '780000100104', '780000100106', '780000100107', '780000100108', '780000100109', '780000100110', '780000100111'],
                    'Fonasa C' => ['780000100102', '780000100104', '780000100106', '780000100107', '780000100108', '780000100109', '780000100110', '780000100111'],
                    'Fonasa D' => ['780000100102', '780000100104', '780000100106', '780000100107', '780000100108', '780000100109', '780000100110', '780000100111'],
                    // Isapres: Vademécum ambulatorio (cubre tratamientos bajo prescripción, antibióticos, crónicos y controlados; excluye OTC básicos como Paracetamol/Ibuprofeno)
                    'Isapre Cruz Blanca' => ['780000100101', '780000100102', '780000100103', '780000100105', '780000100106', '780000100107', '780000100109', '780000100110', '780000100111'],
                    'Isapre Banmédica' => ['780000100101', '780000100102', '780000100103', '780000100105', '780000100106', '780000100107', '780000100109', '780000100110', '780000100111'],
                    'Isapre Colmena' => ['780000100101', '780000100102', '780000100103', '780000100105', '780000100106', '780000100107', '780000100109', '780000100110', '780000100111'],
                ];

                foreach ($vademecumPorConvenio as $nombreConv => $codigos) {
                    $conv = DB::table('convenios_salud')->where('nombre_convenio', $nombreConv)->first();
                    if (! $conv) {
                        continue;
                    }

                    DB::table('convenios_salud')
                        ->where('id_convenio', $conv->id_convenio)
                        ->update(['aplica_todo_catalogo' => false]);

                    foreach ($codigos as $codigo) {
                        $idMed = $meds[$codigo] ?? null;
                        if ($idMed) {
                            DB::table('convenio_medicamento')->updateOrInsert(
                                [
                                    'id_convenio' => $conv->id_convenio,
                                    'id_medicamento' => $idMed,
                                ],
                                [
                                    'activo' => true,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]
                            );
                        }
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('convenio_medicamento');

        if (Schema::hasTable('convenios_salud') && Schema::hasColumn('convenios_salud', 'aplica_todo_catalogo')) {
            Schema::table('convenios_salud', function (Blueprint $table) {
                $table->dropColumn('aplica_todo_catalogo');
            });
        }
    }
};
