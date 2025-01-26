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
        Schema::table('fields', function (Blueprint $table) {
            // Eliminar el campo anterior
            $table->dropColumn('image_url');
            
            // Añadir el nuevo campo con tipo JSON
            $table->json('images')->nullable();
        });
    }
    
    public function down()
    {
        Schema::table('fields', function (Blueprint $table) {
            // En la función down, revertimos los cambios (volvemos al tipo anterior)
            $table->string('image_url')->nullable();
            
            // Eliminar el campo de tipo JSON
            $table->dropColumn('images');
        });
    }
    
};
