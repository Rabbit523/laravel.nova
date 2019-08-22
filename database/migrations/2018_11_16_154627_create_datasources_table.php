<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDatasourcesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('datasources', function (Blueprint $table) {
            $table->uuid('id');
            $table->uuid('user_id');
            $table->uuid('project_id');
            $table->uuid('preset_id')->nullable();
            $table->string('name');
            $table->string('hash')->nullable();
            $table->json('meta')->nullable();
            $table->char('type', 10)->default('csv');
            $table
                ->enum('record_type', ['cogs', 'revenue', 'launch', 'opex'])
                ->default('cogs');
            $table
                ->enum('status', [
                    'uploaded',
                    'matched',
                    'processing',
                    'error',
                    'warning',
                    'success'
                ])
                ->default('uploaded');

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

            $table->primary('id');

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
        Schema::dropIfExists('datasources');
    }
}
