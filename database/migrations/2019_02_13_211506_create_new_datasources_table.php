<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNewDatasourcesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('datasources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('project_id')->nullable();
            $table->uuid('integration_id')->nullable();
            $table->unsignedBigInteger('webhook_id')->nullable();

            $table->string('name');
            $table->string('hash')->nullable();
            $table->char('type', 10)->default('csv'); // stripe, freee, csv...
            $table->json('meta')->nullable();
            $table->json('record')->nullable(); // [type, planned]
            $table->unsignedInteger('records_count')->default(0);
            $table
                ->enum('status', [
                    'received',
                    'matched',
                    'processing',
                    'error',
                    'warning',
                    'success'
                ])
                ->default('received');

            $table->timestamps();
            $table->softDeletes();

            $table
                ->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table
                ->foreign('project_id')
                ->references('id')
                ->on('projects')
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
        Schema::dropIfExists('datasources');
    }
}
