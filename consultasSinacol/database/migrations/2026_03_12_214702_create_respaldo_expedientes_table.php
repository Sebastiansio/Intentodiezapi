<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRespaldoExpedientesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('respaldo_expedientes', function (Blueprint $table) {
            $table->id();
            $table->string('numero_expediente')->unique();
            $table->string('fecha_apertura')->nullable();
            $table->string('fecha_cierre')->nullable();
            $table->string('tipo_tramite')->nullable();
            $table->string('tipo_solicitud')->nullable();
            $table->text('nombre_trabajador')->nullable();
            $table->text('nombre_empresa')->nullable();
            $table->string('resultado_audiencia')->nullable();
            $table->string('asesor_atendio')->nullable();
            $table->string('conciliador_atendio')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('respaldo_expedientes');
    }
}
