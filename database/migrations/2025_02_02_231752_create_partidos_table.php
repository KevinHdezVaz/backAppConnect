<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('partidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('torneo_id')->constrained('torneos')->onDelete('cascade');
            $table->foreignId('equipo_local_id')->constrained('equipos')->onDelete('cascade');
            $table->foreignId('equipo_visitante_id')->constrained('equipos')->onDelete('cascade');
            $table->foreignId('cancha_id')->constrained('fields')->onDelete('cascade');
            $table->dateTime('fecha_programada');
            $table->integer('goles_local')->nullable();
            $table->integer('goles_visitante')->nullable();
            $table->enum('estado', ['programado', 'en_progreso', 'completado', 'cancelado']);
            $table->integer('ronda')->nullable();
            $table->integer('grupo')->nullable();
            $table->json('detalles_partido')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('partidos');
    }
};