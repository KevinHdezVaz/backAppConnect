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
        // Agregar el campo "fotoperfile" a la tabla "users"
        Schema::table('users', function (Blueprint $table) {
            $table->string('fotoperfile')->nullable()->after('profile_image'); // Columna nueva después de 'profile_image'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar el campo "fotoperfile" si se realiza un rollback
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('fotoperfile');
        });
    }
};
