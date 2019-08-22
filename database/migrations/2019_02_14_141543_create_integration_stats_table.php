<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIntegrationStatsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('integration_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('integration_id');
            $table->unsignedInteger('added_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('deleted_count')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table
                ->foreign('integration_id')
                ->references('id')
                ->on('integrations')
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
        Schema::dropIfExists('integration_stats');
    }
}
