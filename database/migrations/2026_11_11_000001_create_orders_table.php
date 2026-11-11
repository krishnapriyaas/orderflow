<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cart_id')->unique()->constrained()->restrictOnDelete();
            $table->string('customer_email');
            $table->text('note')->nullable();
            $table->string('status')->index();
            $table->unsignedBigInteger('total_cents');
            $table->char('currency', 3)->default('EUR');
            $table->string('payment_reference')->nullable()->unique();
            $table->string('payment_redirect_url')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
