<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChatMensajesTable extends Migration
{
    public function up()
    {
        Schema::create('chat_mensajes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipo_id');
            $table->unsignedBigInteger('user_id');
            $table->text('mensaje');
            $table->timestamps();

            $table->foreign('equipo_id')
                  ->references('id')
                  ->on('equipos')
                  ->onDelete('cascade');
                  
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_mensajes');
    }
}