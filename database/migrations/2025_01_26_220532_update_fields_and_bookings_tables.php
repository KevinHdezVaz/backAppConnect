<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   // En la nueva migración:
public function up()
{
   // Actualizar tabla fields
   Schema::table('fields', function (Blueprint $table) {
       $table->decimal('price_per_match', 8, 2);
       $table->dropColumn('price_per_hour');

     });
  

   // Actualizar tabla bookings
   Schema::table('bookings', function (Blueprint $table) {
       $table->enum('payment_status', ['pending', 'completed', 'refunded'])->after('status');
       $table->string('payment_method')->nullable()->after('payment_status');
       $table->boolean('is_recurring')->default(false)->after('payment_method');
       $table->text('cancellation_reason')->nullable()->after('is_recurring');
       $table->boolean('allow_joining')->default(false)->after('cancellation_reason');
       $table->integer('players_needed')->nullable()->after('allow_joining');
       $table->json('player_list')->nullable()->after('players_needed');
   });
}
};
