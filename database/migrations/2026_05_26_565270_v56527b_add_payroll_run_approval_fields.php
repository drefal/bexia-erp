<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_runs')) {
            return;
        }

        Schema::table('payroll_runs', function (Blueprint $table): void {
            if (! Schema::hasColumn('payroll_runs', 'approval_status')) {
                $table->string('approval_status', 40)->default('not_requested')->index()->after('status');
            }

            if (! Schema::hasColumn('payroll_runs', 'approval_request_id')) {
                $table->foreignId('approval_request_id')->nullable()->after('approval_status')->constrained('approval_requests')->nullOnDelete();
            }

            if (! Schema::hasColumn('payroll_runs', 'approval_requested_by_user_id')) {
                $table->foreignId('approval_requested_by_user_id')->nullable()->after('approval_request_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('payroll_runs', 'approval_requested_at')) {
                $table->timestamp('approval_requested_at')->nullable()->after('approval_requested_by_user_id');
            }

            if (! Schema::hasColumn('payroll_runs', 'approved_by_user_id')) {
                $table->foreignId('approved_by_user_id')->nullable()->after('approval_requested_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('payroll_runs', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            }

            if (! Schema::hasColumn('payroll_runs', 'rejected_by_user_id')) {
                $table->foreignId('rejected_by_user_id')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('payroll_runs', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by_user_id');
            }

            if (! Schema::hasColumn('payroll_runs', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payroll_runs')) {
            return;
        }

        Schema::table('payroll_runs', function (Blueprint $table): void {
            foreach ([
                'rejection_reason',
                'rejected_at',
                'rejected_by_user_id',
                'approved_at',
                'approved_by_user_id',
                'approval_requested_at',
                'approval_requested_by_user_id',
                'approval_request_id',
                'approval_status',
            ] as $column) {
                if (Schema::hasColumn('payroll_runs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
