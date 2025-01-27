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
            $table->enum('role', ['player', 'admin'])->default('player')->after('password');
            $table->string('phone')->nullable()->after('role');
            $table->string('profile_image')->nullable()->after('phone');
            $table->decimal('latitude', 10, 8)->nullable()->after('profile_image');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->string('business_name')->nullable()->after('longitude');
            $table->string('rfc')->nullable()->after('business_name');
            $table->string('business_address')->nullable()->after('rfc');
            $table->boolean('verified')->default(false)->after('business_address');
        });
    }
    
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone', 'profile_image', 'latitude', 'longitude', 'business_name', 'rfc', 'business_address', 'verified']);
        });
    }
};
