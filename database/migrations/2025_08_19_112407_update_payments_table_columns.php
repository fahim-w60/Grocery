<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Add columns if they don't exist
        if (!Schema::hasColumn('payments', 'stripe_payment_intent_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('stripe_payment_intent_id')->nullable()->after('transaction_id');
            });
        }
        
        if (!Schema::hasColumn('payments', 'currency')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('currency', 3)->default('USD')->after('stripe_payment_intent_id');
            });
        }
        
        if (!Schema::hasColumn('payments', 'payment_date')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->timestamp('payment_date')->nullable()->after('currency');
            });
        }
        
        if (!Schema::hasColumn('payments', 'metadata')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->json('metadata')->nullable()->after('confirmations');
            });
        }
        
        if (!Schema::hasColumn('payments', 'paid_at')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->timestamp('paid_at')->nullable()->after('metadata');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // Drop columns if they exist
        if (Schema::hasColumn('payments', 'stripe_payment_intent_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('stripe_payment_intent_id');
            });
        }
        
        if (Schema::hasColumn('payments', 'currency')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('currency');
            });
        }
        
        if (Schema::hasColumn('payments', 'payment_date')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('payment_date');
            });
        }
        
        if (Schema::hasColumn('payments', 'metadata')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('metadata');
            });
        }
        
        if (Schema::hasColumn('payments', 'paid_at')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('paid_at');
            });
        }
    }
};
