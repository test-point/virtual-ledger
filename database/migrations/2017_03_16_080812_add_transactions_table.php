<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->string('from_party');
            $table->string('to_party');
            $table->string('message_hash');
            $table->string('encripted_payload');
            $table->string('decripted_payload');
            $table->string('notarized_message');
            $table->string('message_type');
            $table->string('schema');
            $table->string('validation_status');
            $table->string('validation_message');
            $table->string('conversation_id')->nullable();
            $table->timestamps();
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
        });

        Schema::table('transactions', function($table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('conversations');
    }
}
