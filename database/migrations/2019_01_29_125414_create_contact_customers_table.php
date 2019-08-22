<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateContactCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id'); // required
            $table->uuid('contact_id')->nullable(); // can be assigned later by client

            $table->string('email')->nullable();
            $table->string('payment_id');
            $table->string('payment_type')->default('stripe');
            $table->json('meta');
            $table->index(['payment_id', 'payment_type']);

            // TODO: think about stripe connect and what fields they might require
            // $table->string('stripe_id')->nullable();
            $table->string('card_brand')->nullable();
            $table->string('card_last_four')->nullable();
            $table->timestamp('trial_ends_at')->nullable();

            $table->timestamps();

            $table
                ->foreign('client_id', 'client_fk')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table
                ->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customers');
    }
}
