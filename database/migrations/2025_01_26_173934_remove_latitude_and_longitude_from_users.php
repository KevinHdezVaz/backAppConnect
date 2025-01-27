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
    Schema::table('users', function (Blueprint $table) {
        // Elimina las columnas 'latitude' y 'longitude'
        $table->dropColumn(['latitude', 'longitude']);
    });
}

public function down()
{
    Schema::table('users', function (Blueprint $table) {
        // Si se revierte la migración, vuelve a agregar 'latitude' y 'longitude'
        $table->decimal('latitude', 8, 6)->nullable();
        $table->decimal('longitude', 8, 6)->nullable();
    });
}

};
