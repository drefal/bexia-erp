<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_channels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->boolean('is_active')->default(false);
            $table->jsonb('capabilities')->nullable();
            $table->timestamps();
        });

        Schema::create('commerce_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->foreignId('commerce_channel_id')
                ->constrained('commerce_channels')
                ->restrictOnDelete();

            $table->string('name', 150);
            $table->string('external_account_id', 180)->nullable();
            $table->string('country', 2)->default('MX');
            $table->string('currency', 3)->default('MXN');
            $table->string('status', 30)->default('draft');
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_successful_sync_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['company_id', 'commerce_channel_id', 'external_account_id'],
                'commerce_account_external_unique'
            );

            $table->index(
                ['company_id', 'commerce_channel_id', 'status'],
                'commerce_account_company_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_accounts');
        Schema::dropIfExists('commerce_channels');
    }
};
