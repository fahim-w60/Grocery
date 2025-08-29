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
            $table->string('stripe_payment_intent_id')->nullable()->after('transaction_id');
            $table->string('currency', 3)->default('USD')->after('stripe_payment_intent_id');
            $table->timestamp('payment_date')->nullable()->after('currency');
            $table->json('metadata')->nullable()->after('confirmations');
            $table->timestamp('paid_at')->nullable()->after('metadata');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_payment_intent_id',
                'currency',
                'payment_date',
                'metadata',
                'paid_at'
            ]);
        });
    }
};
