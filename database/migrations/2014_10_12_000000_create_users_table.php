<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  // Nueva migración para actualizar users
public function up()
{
   Schema::create('users', function (Blueprint $table) {
       $table->id();
       $table->string('name');
       $table->string('email')->unique();
       $table->string('password');
       $table->enum('role', ['player', 'admin'])->default('player');
       $table->string('phone')->nullable();
       $table->string('profile_image')->nullable();
       $table->decimal('latitude', 10, 8)->nullable();
       $table->decimal('longitude', 11, 8)->nullable();
       
       // Para administradores
       $table->string('business_name')->nullable();
       $table->string('business_address')->nullable();
       $table->boolean('verified')->default(false);
       
       $table->rememberToken();
       $table->timestamps();
   });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
