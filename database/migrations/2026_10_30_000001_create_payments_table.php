<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What guests pay towards a table's bill. One table can pay in several goes (everything at once, equal shares, or dish by dish).
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_cents');             // the part of the bill this payment covers
            $table->unsignedInteger('tip_cents')->default(0);    // on top of the bill, never part of it
            $table->string('kind', 12);                          // whole | share | items
            $table->string('label', 60)->nullable();             // e.g. "Share 1 of 3"
            $table->string('card_brand', 20)->nullable();
            $table->string('card_last4', 4)->nullable();         // only the last four digits are ever kept
            $table->string('reference', 40);                     // the gateway's receipt number
            $table->timestamp('paid_at');
            $table->timestamps();
        });

        // A dish that was paid for on its own points at the payment that covered it
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('payment_id')->nullable()->after('is_ready')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_id');
        });
        Schema::dropIfExists('payments');
    }
};
