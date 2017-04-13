<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAbnTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('abn', function (Blueprint $table) {
            $table->increments('id');
            $table->string('abn');
            $table->timestamps();
        });

        $handle = fopen(resource_path('data/files/abns_list.txt'), "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                \App\Abn::create([
                    'abn' => trim($line)
                ]);
            }

            fclose($handle);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('abn');
    }
}
