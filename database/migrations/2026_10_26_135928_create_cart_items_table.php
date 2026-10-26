<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->json('selection')->nullable();       // [groupIndex => [choiceIndex, ...]]
            $table->string('selection_key')->default(''); // normalised selection, makes identical lines merge
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['table_session_id', 'menu_item_id', 'selection_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
