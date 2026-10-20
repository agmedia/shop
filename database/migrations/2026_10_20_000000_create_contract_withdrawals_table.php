<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_withdrawals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('customer_name', 191);
            $table->string('email', 191);
            $table->string('order_number', 80);
            $table->text('items')->nullable();
            $table->text('statement');
            $table->string('locale', 10);
            $table->timestampTz('received_at');
            $table->timestampTz('receipt_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_withdrawals');
    }
};
