<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProjectTeamPivotTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('project_team', function (Blueprint $table) {
            $table->uuid('project_id')->index();
            $table
                ->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->onDelete('cascade');
            $table->uuid('team_id')->index();
            $table
                ->foreign('team_id')
                ->references('id')
                ->on('teams')
                ->onDelete('cascade');
            $table->primary(['team_id', 'project_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('project_team');
    }
}
