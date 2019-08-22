<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMonthlyRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('monthly_records', function (Blueprint $table) {
            $table->uuid('record_id');
            $table->date('date')->index('date');
            $table->decimal('price', 16, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->json('meta')->nullable();

            $table->primary(['record_id', 'date']);
            $table
                ->foreign('record_id')
                ->references('id')
                ->on('records')
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
        Schema::dropIfExists('monthly_records');
    }
}
