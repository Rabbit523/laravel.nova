<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id');
            $table->uuid('product_id');

            $table->string('name');
            $table->string('interval');
            $table->string('payment_id')->nullable();
            $table->string('payment_type')->nullable();
            $table->string('currency')->default('jpy');

            $table->unsignedInteger('amount')->default(0);
            $table->unsignedInteger('interval_count')->default(1);
            $table->unsignedInteger('trial_days')->default(0);
            $table->unsignedInteger('billing_day')->nullable();
            $table->enum('billing_scheme', ['per_group_of', 'per_unit'])->default('per_unit');

            $table->json('meta');
            $table->timestamps();
            $table->softDeletes();

            $table->primary('id');
            $table
                ->foreign('product_id')
                ->references('id')
                ->on('products')
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
        Schema::dropIfExists('plans');
    }
}
