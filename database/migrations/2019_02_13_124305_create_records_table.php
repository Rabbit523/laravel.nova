<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('contact_id')->nullable();
            $table->uuid('product_id')->nullable(); // used in cogs/opex/revenue
            $table->uuid('plan_id')->nullable(); // used in revenue only
            $table->uuid('category_id')->nullable();

            $table->string('category_code');
            $table->string('name');
            $table->boolean('direct')->default(true);
            $table->boolean('planned')->default(true);
            $table->enum('type', ['cogs', 'revenue', 'launch', 'opex'])->default('cogs');
            $table->json('autofill')->nullable();
            $table->json('meta')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table
                ->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->onDelete('cascade');

            $table
                ->foreign('contact_id')
                ->references('id')
                ->on('contacts')
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
        Schema::dropIfExists('records');
    }
}
