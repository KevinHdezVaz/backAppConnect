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
            $table->decimal('price_per_hour', 8, 2)->after('location'); // Nuevo campo de precio por hora
            $table->integer('duration_per_match')->default(60)->after('price_per_hour'); // Duración por partido
            $table->decimal('latitude', 10, 8)->nullable()->after('duration_per_match'); // Latitud
            $table->decimal('longitude', 10, 8)->nullable()->after('latitude'); // Longitud
            $table->boolean('is_active')->default(true)->after('longitude'); // Estado activo
            $table->enum('type', ['futbol5', 'futbol7', 'futbol11'])->after('is_active'); // Tipo de cancha
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('fields', function (Blueprint $table) {
            $table->dropColumn([
                'price_per_hour',
                'duration_per_match',
                'latitude',
                'longitude',
                'is_active',
                'type',
            ]);
        });
    }
};
