<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCustomerPlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_plans', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('customer_id');
            $table->uuid('plan_id');
            $table->timestamps();
            $table->datetime('ends_at')->nullable();
            $table->index(['customer_id', 'plan_id', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customer_plans');
    }
}
