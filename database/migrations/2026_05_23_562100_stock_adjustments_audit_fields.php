<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_adjustments')) {
            Schema::table('stock_adjustments', function (Blueprint $table): void {
                if (! Schema::hasColumn('stock_adjustments', 'cancellation_reason')) {
                    $table->text('cancellation_reason')->nullable();
                }

                if (! Schema::hasColumn('stock_adjustments', 'cancelled_by')) {
                    $table->unsignedBigInteger('cancelled_by')->nullable()->index();
                }

                if (! Schema::hasColumn('stock_adjustments', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->index();
                }
            });
        }

        if (! Schema::hasTable('stock_adjustment_status_logs')) {
            Schema::create('stock_adjustment_status_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('stock_adjustment_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('from_status')->nullable()->index();
                $table->string('to_status')->index();
                $table->string('action')->index();
                $table->text('reason')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('stock_adjustment_id', 'sasl_adjustment_fk')
                    ->references('id')
                    ->on('stock_adjustments')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_status_logs');

        if (Schema::hasTable('stock_adjustments')) {
            Schema::table('stock_adjustments', function (Blueprint $table): void {
                if (Schema::hasColumn('stock_adjustments', 'cancelled_at')) {
                    $table->dropColumn('cancelled_at');
                }

                if (Schema::hasColumn('stock_adjustments', 'cancelled_by')) {
                    $table->dropColumn('cancelled_by');
                }

                if (Schema::hasColumn('stock_adjustments', 'cancellation_reason')) {
                    $table->dropColumn('cancellation_reason');
                }
            });
        }
    }
};
