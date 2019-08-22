<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModifyContactsAddParentIdDropCustomerId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table
                ->uuid('parent_id')
                ->nullable()
                ->after('client_id');
            $table
                ->foreign('parent_id')
                ->references('id')
                ->on('contacts')
                ->onDelete('cascade');

            $table->dropColumn('payment_provider');
            $table->dropColumn('customer_id');
            $table->dropColumn('stripe_id');
            $table->dropColumn('card_last_four');
            $table->dropColumn('card_brand');
            $table->dropColumn('trial_ends_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
