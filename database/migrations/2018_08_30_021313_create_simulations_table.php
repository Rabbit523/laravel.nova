<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSimulationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('simulations', function (Blueprint $table) {
            $table->uuid('id');
            $table->uuid('project_id');
            $table->bigInteger('market_size');
            $table->bigInteger('market_share');
            $table->unsignedInteger('apc')->comment('average purchase frequency');
            $table->unsignedInteger('mi')->comment('market interactions');
            $table->enum('growth', ['log', 'exp', 'lin', 'smooth'])->default('lin');
            $table->double('growth_rate', 4, 2)->default(1); // percent 99.5=9950%
            $table->double('conversion_rate', 5, 2)->comment('conversion index'); // percent 0.01-100%
            $table->decimal('avp', 10, 2)->comment('average unit price');
            $table->decimal('initial_cost', 15, 2)->comment('1st cogs');
            $table->unsignedInteger('launch_period')->comment('launch period length');
            $table->decimal('cogs', 4, 2)->comment('cost of goods %'); //percent 0.01-99.99
            $table->decimal('cpmi', 10, 2)->comment('cost per marketing interaction');

            $table->timestamps();

            $table->primary('id');
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
        Schema::dropIfExists('simulations');
    }
}
