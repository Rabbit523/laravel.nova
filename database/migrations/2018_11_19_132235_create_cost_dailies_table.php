<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCostDailiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('costs_daily', function (Blueprint $table) {
            $table->uuid('cost_id');
            $table->date('date')->index('date');
            $table->decimal('price', 15, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->json('meta')->nullable();

            $table->primary(['cost_id', 'date']);
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
        Schema::dropIfExists('costs_daily');
    }
}
