<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_payment_requests', function (Blueprint $table) {
            $table->id();

            $table->uuid('request_uuid')->unique(
                'pos_payment_requests_uuid_unique'
            );

            $table->unsignedBigInteger('pos_order_id')->index();

            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('pos_session_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable();

            $table->string('payload_hash', 64);

            $table->string('status', 30)->default('processing');

            $table->json('response_payload')->nullable();

            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_payment_requests');
    }
};
