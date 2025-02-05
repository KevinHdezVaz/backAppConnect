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
        Schema::create('equipo_usuarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('rol', ['capitan', 'miembro'])->default('miembro');
            $table->enum('estado', ['pendiente', 'activo', 'inactivo'])->default('pendiente');
            $table->timestamps();
            
            // Índices para mejorar el rendimiento
            $table->index(['equipo_id', 'user_id']);
            $table->index(['rol', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipo_usuarios');
    }
};
