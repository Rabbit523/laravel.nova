<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAddresses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('model_id');
            $table->string('model_type');

            $table->string('role')->default('main');

            $table->string('street')->nullable();
            $table->string('other')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->char('country', 2)->nullable();
            $table->string('postcode')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
            $table->unique(['model_id', 'model_type', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('addresses');
    }
}
