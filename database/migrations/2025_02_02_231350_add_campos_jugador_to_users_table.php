<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('equipo_id')->nullable()->constrained('equipos')->onDelete('set null');
            $table->string('apodo')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('posicion')->nullable();
            $table->string('numero_camiseta')->nullable();
            $table->boolean('es_capitan')->default(false);
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['equipo_id']);
            $table->dropColumn([
                'equipo_id',
                'apodo',
                'fecha_nacimiento',
                'posicion',
                'numero_camiseta',
                'es_capitan'
            ]);
        });
    }
};