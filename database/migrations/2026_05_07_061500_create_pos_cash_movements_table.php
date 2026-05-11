<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_cash_movements')) {
            Schema::create('pos_cash_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('pos_point_id')->nullable()->index();
                $table->unsignedBigInteger('pos_session_id')->index();
                $table->string('number')->nullable()->index();
                $table->string('type', 30)->index(); // cash_in / cash_out
                $table->decimal('amount', 18, 6)->default(0);
                $table->string('reason')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('performed_by_user_id')->nullable();
                $table->string('performed_by_name')->nullable();
                $table->string('supervisor_name')->nullable();
                $table->timestamp('movement_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['pos_session_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_cash_movements');
    }
};
