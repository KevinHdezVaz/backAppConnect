<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
    Schema::create('torneo_configuraciones', function (Blueprint $table) {
        $table->id();
        $table->foreignId('torneo_id')->constrained('torneos')->onDelete('cascade');
        $table->integer('duracion_partido')->default(90); // En minutos
        $table->integer('puntos_victoria')->default(3);
        $table->integer('puntos_empate')->default(1);
        $table->integer('puntos_derrota')->default(0);
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracion_torneos');
    }
};
