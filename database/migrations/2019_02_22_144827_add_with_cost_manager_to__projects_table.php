<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddWithCostManagerToProjectsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('projects', function (Blueprint $table) {
            $table
                ->boolean('with_cost_manager')
                ->default(true)
                ->after('with_launch');
            $table->dropColumn('end_date');
            $table->dropColumn('financial_month');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('_projects', function (Blueprint $table) {
            //
        });
    }
}
