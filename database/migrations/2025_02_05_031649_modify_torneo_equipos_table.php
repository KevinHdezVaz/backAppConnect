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
        Schema::table('torneo_equipos', function (Blueprint $table) {
            // Asegurarse de que las llaves foráneas estén configuradas correctamente
            $table->foreign('torneo_id')
                  ->references('id')
                  ->on('torneos')
                  ->onDelete('cascade');
                  
            $table->foreign('equipo_id')
                  ->references('id')
                  ->on('equipos')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('torneo_equipos', function (Blueprint $table) {
            $table->dropForeign(['torneo_id']);
            $table->dropForeign(['equipo_id']);
        });
    }
};
