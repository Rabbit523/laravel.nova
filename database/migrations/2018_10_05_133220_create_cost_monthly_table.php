<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCostMonthlyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('costs_monthly', function (Blueprint $table) {
            $table->uuid('cost_id');
            $table->smallInteger('month')->unsigned();
            // $table->smallInteger('year')->unsigned();
            $table->decimal('price', 15, 2);
            $table->unsignedInteger('quantity')->default(1);
            // $table->timestamps();
            $table->primary(['cost_id', 'month']);

            $table
                ->foreign('cost_id')
                ->references('id')
                ->on('costs')
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
        Schema::dropIfExists('costs_monthly');
    }
}
