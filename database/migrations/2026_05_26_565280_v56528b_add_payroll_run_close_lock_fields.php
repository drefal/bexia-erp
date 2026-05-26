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
            if (! Schema::hasColumn('payroll_runs', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('rejection_reason');
            }

            if (! Schema::hasColumn('payroll_runs', 'closed_by_user_id')) {
                $table->foreignId('closed_by_user_id')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('payroll_runs', 'close_reason')) {
                $table->text('close_reason')->nullable()->after('closed_by_user_id');
            }

            if (! Schema::hasColumn('payroll_runs', 'is_locked')) {
                $table->boolean('is_locked')->default(false)->index()->after('close_reason');
            }

            if (! Schema::hasColumn('payroll_runs', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('is_locked');
            }

            if (! Schema::hasColumn('payroll_runs', 'locked_by_user_id')) {
                $table->foreignId('locked_by_user_id')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('payroll_runs', 'lock_reason')) {
                $table->text('lock_reason')->nullable()->after('locked_by_user_id');
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
                'lock_reason',
                'locked_at',
                'is_locked',
                'close_reason',
                'closed_at',
            ] as $column) {
                if (Schema::hasColumn('payroll_runs', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('payroll_runs', 'locked_by_user_id')) {
                $table->dropConstrainedForeignId('locked_by_user_id');
            }

            if (Schema::hasColumn('payroll_runs', 'closed_by_user_id')) {
                $table->dropConstrainedForeignId('closed_by_user_id');
            }
        });
    }
};
