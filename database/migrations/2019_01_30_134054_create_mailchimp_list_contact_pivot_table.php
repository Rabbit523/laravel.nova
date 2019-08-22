<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMailchimpListContactPivotTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mailchimp_list_contact', function (Blueprint $table) {
            $table->uuid('mailchimp_list_id')->index();
            $table
                ->foreign('mailchimp_list_id')
                ->references('id')
                ->on('mailchimp_lists')
                ->onDelete('cascade');
            $table->uuid('contact_id')->index();
            $table
                ->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->onDelete('cascade');
            $table->primary(['mailchimp_list_id', 'contact_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mailchimp_list_contact');
    }
}
