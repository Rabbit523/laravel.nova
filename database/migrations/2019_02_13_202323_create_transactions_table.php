<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('record_id');
            $table->uuid('payment_source_id')->nullable();
            $table->uuid('refund_id')->nullable();

            $table->uuid('source_id');
            $table->string('source_type'); // datasource, user, etc...

            $table->string('remote_id')->nullable(); // remote transaction id

            $table->decimal('price', 16, 2);
            $table->unsignedSmallInteger('quantity')->default(1);

            $table->boolean('refunded')->default(false);
            $table->json('meta')->nullable();

            $table->timestamp('date')->nullable();
            $table->softDeletes();
            $table->timestamps();

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
        Schema::dropIfExists('transactions');
    }
}
