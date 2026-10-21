<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'hr_payroll_attendance_review_batches',
            function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->index();
                $table->foreignId('payroll_run_id')->unique();
                $table->date('period_start');
                $table->date('period_end');
                $table->string('status', 32)->default('draft');
                $table->unsignedInteger('version')->default(1);
                $table->foreignId(
                    'created_by_user_id'
                )->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('consolidated_at')->nullable();
                $table->foreignId(
                    'consolidated_by_user_id'
                )->nullable();
                $table->timestamps();
            }
        );

        Schema::create(
            'hr_payroll_attendance_reviews',
            function (Blueprint $table) {
                $table->id();
                $table->foreignId('batch_id')->index();
                $table->foreignId('company_id')->index();
                $table->foreignId('reviewer_user_id')->index();
                $table->string('status', 32)->default('draft');
                $table->unsignedInteger('version')->default(1);
                $table->string('snapshot_hash', 64)->nullable();
                $table->text('reviewer_comment')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId(
                    'reviewed_by_user_id'
                )->nullable();
                $table->timestamps();

                $table->unique(
                    ['batch_id', 'reviewer_user_id'],
                    'hr_payroll_att_reviews_batch_user_unique'
                );
            }
        );

        Schema::create(
            'hr_payroll_attendance_review_items',
            function (Blueprint $table) {
                $table->id();
                $table->foreignId('batch_id')->index();
                $table->foreignId(
                    'review_id'
                )->nullable()->index();
                $table->foreignId('company_id')->index();
                $table->foreignId('employee_id')->index();
                $table->foreignId(
                    'department_id'
                )->nullable();
                $table->foreignId(
                    'reviewer_user_id'
                )->nullable();
                $table->string('assignment_source', 32);
                $table->string(
                    'assignment_issue',
                    255
                )->nullable();
                $table->jsonb(
                    'attendance_snapshot'
                )->nullable();
                $table->string(
                    'snapshot_hash',
                    64
                )->nullable();
                $table->timestamps();

                $table->unique(
                    ['batch_id', 'employee_id'],
                    'hr_payroll_att_items_batch_employee_unique'
                );
            }
        );

        Schema::create(
            'hr_payroll_attendance_review_events',
            function (Blueprint $table) {
                $table->id();
                $table->foreignId('batch_id')->index();
                $table->foreignId(
                    'review_id'
                )->nullable()->index();
                $table->foreignId('company_id')->index();
                $table->foreignId(
                    'actor_user_id'
                )->nullable();
                $table->string('action', 48);
                $table->string(
                    'previous_status',
                    32
                )->nullable();
                $table->string(
                    'new_status',
                    32
                )->nullable();
                $table->unsignedInteger(
                    'review_version'
                )->nullable();
                $table->string(
                    'snapshot_hash',
                    64
                )->nullable();
                $table->text('comment')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'hr_payroll_attendance_review_events'
        );
        Schema::dropIfExists(
            'hr_payroll_attendance_review_items'
        );
        Schema::dropIfExists(
            'hr_payroll_attendance_reviews'
        );
        Schema::dropIfExists(
            'hr_payroll_attendance_review_batches'
        );
    }
};
