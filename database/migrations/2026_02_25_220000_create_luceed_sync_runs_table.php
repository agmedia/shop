<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('luceed_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('action_key', 120)->index();
            $table->string('action_label', 191);
            $table->string('status', 24)->default('started')->index();
            $table->text('summary')->nullable();
            $table->json('stats')->nullable();
            $table->longText('error_message')->nullable();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable()->index();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['action_key', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('luceed_sync_runs');
    }
};
