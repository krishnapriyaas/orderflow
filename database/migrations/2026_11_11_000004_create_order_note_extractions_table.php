<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_note_extractions', function (Blueprint $table): void {
            $table->id();
            // Unique: one extraction per order, so a re-run job cannot add a second row.
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('delivery_date')->nullable();
            $table->string('po_number')->nullable();
            $table->text('invoice_address')->nullable();
            // Null when the extraction failed before the model returned a result.
            $table->decimal('confidence', 4, 3)->nullable();
            $table->boolean('instructions_detected')->default(false);
            $table->string('status')->index();
            $table->string('review_reason')->nullable();
            $table->longText('raw_response')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_note_extractions');
    }
};
