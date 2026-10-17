<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('medicamentos', function (Blueprint $table) {
            $table->increments('id_medicamento');
            $table->string('codigo_barras', 50)->unique()->index();
            $table->string('nombre_comercial', 150);
            $table->string('principio_activo', 150);
            $table->string('laboratorio', 100)->default('Genérico');
            $table->string('categoria', 80)->default('General');
            $table->decimal('precio_unitario', 10, 2);
            $table->boolean('requiere_receta')->default(false);
            $table->boolean('tipo_controlado')->default(false);
            $table->unsignedInteger('stock_minimo')->default(10);
            $table->timestamps();
        });

        Schema::create('inventario_lotes', function (Blueprint $table) {
            $table->increments('id_lote');
            $table->unsignedInteger('id_medicamento');
            $table->string('lote_codigo', 60)->index();
            $table->enum('ubicacion', ['BODEGA', 'VITRINA'])->default('BODEGA')->index();
            $table->integer('cantidad')->default(0);
            $table->date('fecha_vencimiento')->index();
            $table->date('fecha_ingreso');
            $table->timestamps();

            $table->foreign('id_medicamento')
                ->references('id_medicamento')
                ->on('medicamentos')
                ->restrictOnDelete();
        });

        Schema::create('convenios_salud', function (Blueprint $table) {
            $table->increments('id_convenio');
            $table->string('nombre_convenio', 100);
            $table->decimal('porcentaje_descuento', 5, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('ventas', function (Blueprint $table) {
            $table->increments('id_venta');
            $table->unsignedInteger('id_usuario');
            $table->unsignedInteger('id_convenio')->nullable();
            $table->timestamp('fecha_venta')->useCurrent();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('descuento_total', 12, 2)->default(0);
            $table->decimal('iva', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('estado', 30)->default('COMPLETADA');
            $table->timestamps();

            $table->foreign('id_usuario')
                ->references('id_usuario')
                ->on('usuarios')
                ->restrictOnDelete();

            $table->foreign('id_convenio')
                ->references('id_convenio')
                ->on('convenios_salud')
                ->nullOnDelete();
        });

        Schema::create('detalle_venta', function (Blueprint $table) {
            $table->increments('id_detalle');
            $table->unsignedInteger('id_venta');
            $table->unsignedInteger('id_lote');
            $table->unsignedInteger('id_medicamento');
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('subtotal_linea', 12, 2);
            $table->timestamps();

            $table->foreign('id_venta')
                ->references('id_venta')
                ->on('ventas')
                ->cascadeOnDelete();

            $table->foreign('id_lote')
                ->references('id_lote')
                ->on('inventario_lotes')
                ->restrictOnDelete();

            $table->foreign('id_medicamento')
                ->references('id_medicamento')
                ->on('medicamentos')
                ->restrictOnDelete();
        });

        Schema::create('libro_control_isp', function (Blueprint $table) {
            $table->increments('id_registro');
            $table->unsignedInteger('id_detalle')->nullable()->unique();
            $table->unsignedInteger('id_venta')->nullable();
            $table->unsignedInteger('id_medicamento');
            $table->unsignedInteger('id_lote')->nullable();
            $table->unsignedInteger('id_vendedor');
            $table->unsignedInteger('id_qf_autorizador')->nullable();
            $table->integer('cantidad')->default(1);
            $table->string('folio_receta', 60);
            $table->string('rut_paciente', 15);
            $table->string('rut_medico', 15);
            $table->enum('estado', ['PENDIENTE', 'AUTORIZADO', 'RECHAZADO'])->default('PENDIENTE')->index();
            $table->string('motivo_rechazo', 255)->nullable();
            $table->timestamp('fecha_autorizacion')->nullable();
            $table->string('qf_signature', 150)->nullable();
            $table->char('hash_integridad', 64)->nullable();
            $table->text('datos_carro_json')->nullable();
            $table->timestamps();

            $table->foreign('id_detalle')
                ->references('id_detalle')
                ->on('detalle_venta')
                ->nullOnDelete();

            $table->foreign('id_venta')
                ->references('id_venta')
                ->on('ventas')
                ->nullOnDelete();

            $table->foreign('id_medicamento')
                ->references('id_medicamento')
                ->on('medicamentos')
                ->restrictOnDelete();

            $table->foreign('id_lote')
                ->references('id_lote')
                ->on('inventario_lotes')
                ->nullOnDelete();

            $table->foreign('id_vendedor')
                ->references('id_usuario')
                ->on('usuarios')
                ->restrictOnDelete();

            $table->foreign('id_qf_autorizador')
                ->references('id_usuario')
                ->on('usuarios')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('libro_control_isp');
        Schema::dropIfExists('detalle_venta');
        Schema::dropIfExists('ventas');
        Schema::dropIfExists('convenios_salud');
        Schema::dropIfExists('inventario_lotes');
        Schema::dropIfExists('medicamentos');
    }
};
