<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePaymentSourcesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contact_id');

            $table->string('remote_id');
            $table->string('type')->default('stripe');
            $table->string('card_brand')->nullable();
            $table->string('card_last_four')->nullable();
            $table->boolean('default')->default(false);
            $table->json('meta');

            $table->index(['remote_id', 'type']);
            $table
                ->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_sources');
    }
}
