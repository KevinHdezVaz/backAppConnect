<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('torneos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->enum('estado', ['borrador', 'abierto', 'en_progreso', 'completado', 'cancelado']);
            $table->integer('maximo_equipos');
            $table->integer('minimo_equipos');
            $table->decimal('cuota_inscripcion', 10, 2);
            $table->string('premio')->nullable();
            $table->enum('formato', ['liga', 'eliminacion', 'grupos_eliminacion']);
            $table->json('reglas')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('torneos');
    }
};