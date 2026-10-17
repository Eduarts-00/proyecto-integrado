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
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id_rol');
            $table->string('codigo_rol', 40)->unique();
            $table->string('nombre_rol', 80);
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('usuarios', function (Blueprint $table) {
            $table->increments('id_usuario');
            $table->unsignedInteger('id_rol');
            $table->string('rut', 12)->unique()->index();
            $table->string('nombre_completo', 120);
            $table->string('email', 120)->unique();
            $table->string('password_hash', 255);
            $table->string('pin_qf', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->rememberToken();
            $table->timestamps();

            $table->foreign('id_rol')
                ->references('id_rol')
                ->on('roles')
                ->restrictOnDelete();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('roles');
    }
};
