<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'stripe_payment_intent_id')) {
                $table->string('stripe_payment_intent_id')->nullable()->after('transaction_id');
            }
            if (!Schema::hasColumn('payments', 'currency')) {
                $table->string('currency', 3)->default('USD')->after('stripe_payment_intent_id');
            }
            if (!Schema::hasColumn('payments', 'payment_date')) {
                $table->timestamp('payment_date')->nullable()->after('currency');
            }
            if (!Schema::hasColumn('payments', 'metadata')) {
                $table->json('metadata')->nullable()->after('confirmations');
            }
            if (!Schema::hasColumn('payments', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('metadata');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $columnsToDrop = [];
            
            if (Schema::hasColumn('payments', 'stripe_payment_intent_id')) {
                $columnsToDrop[] = 'stripe_payment_intent_id';
            }
            if (Schema::hasColumn('payments', 'currency')) {
                $columnsToDrop[] = 'currency';
            }
            if (Schema::hasColumn('payments', 'payment_date')) {
                $columnsToDrop[] = 'payment_date';
            }
            if (Schema::hasColumn('payments', 'metadata')) {
                $columnsToDrop[] = 'metadata';
            }
            if (Schema::hasColumn('payments', 'paid_at')) {
                $columnsToDrop[] = 'paid_at';
            }
            
            if (count($columnsToDrop) > 0) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
