<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCostsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('costs', function (Blueprint $table) {
            $table->uuid('id');
            $table->uuid('project_id');
            $table->string('category_code');

            $table->string('name');
            $table->decimal('price', 15, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->boolean('direct')->default(true);

            $table->boolean('planned')->default(true);
            $table->enum('type', ['cogs', 'revenue', 'launch', 'opex'])->default('cogs');

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->primary('id');
            $table->index(['project_id', 'type', 'planned']);
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
        Schema::dropIfExists('costs');
    }
}
