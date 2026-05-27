<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->extendTreasuryAccounts();
        $this->extendTreasuryMovements();
        $this->extendPosCashMovements();
        $this->extendPosOrderPayments();
        $this->createCashTransferRequests();
        $this->createCashTransferApprovalLogs();
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_cash_transfer_approval_logs');
        Schema::dropIfExists('treasury_cash_transfer_requests');

        if (Schema::hasTable('pos_order_payments')) {
            Schema::table('pos_order_payments', function (Blueprint $table): void {
                foreach ([
                    'treasury_movement_id',
                    'treasury_account_id',
                    'treasury_posted_at',
                ] as $column) {
                    if (Schema::hasColumn('pos_order_payments', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('pos_cash_movements')) {
            Schema::table('pos_cash_movements', function (Blueprint $table): void {
                foreach ([
                    'treasury_movement_id',
                    'treasury_transfer_request_id',
                    'destination_treasury_account_id',
                    'treasury_status',
                    'approved_by_user_id',
                    'approved_at',
                ] as $column) {
                    if (Schema::hasColumn('pos_cash_movements', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('treasury_movements')) {
            Schema::table('treasury_movements', function (Blueprint $table): void {
                foreach ([
                    'source_treasury_account_id',
                    'destination_treasury_account_id',
                    'pos_cash_movement_id',
                    'pos_order_payment_id',
                    'pos_session_id',
                    'pos_point_id',
                    'branch_id',
                    'warehouse_id',
                    'requested_by_user_id',
                    'approved_by_user_id',
                    'rejected_by_user_id',
                    'received_by_user_id',
                    'approved_at',
                    'rejected_at',
                    'received_at',
                    'approval_notes',
                    'rejection_reason',
                ] as $column) {
                    if (Schema::hasColumn('treasury_movements', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('treasury_accounts')) {
            Schema::table('treasury_accounts', function (Blueprint $table): void {
                foreach ([
                    'branch_id',
                    'warehouse_id',
                    'pos_point_id',
                    'parent_treasury_account_id',
                    'cash_scope',
                    'requires_approval',
                ] as $column) {
                    if (Schema::hasColumn('treasury_accounts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function extendTreasuryAccounts(): void
    {
        if (! Schema::hasTable('treasury_accounts')) {
            return;
        }

        Schema::table('treasury_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('treasury_accounts', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->index('ta_branch_idx');
            }

            if (! Schema::hasColumn('treasury_accounts', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->index('ta_warehouse_idx');
            }

            if (! Schema::hasColumn('treasury_accounts', 'pos_point_id')) {
                $table->unsignedBigInteger('pos_point_id')->nullable()->index('ta_pos_point_idx');
            }

            if (! Schema::hasColumn('treasury_accounts', 'parent_treasury_account_id')) {
                $table->unsignedBigInteger('parent_treasury_account_id')->nullable()->index('ta_parent_idx');
            }

            if (! Schema::hasColumn('treasury_accounts', 'cash_scope')) {
                $table->string('cash_scope', 40)->nullable()->index('ta_cash_scope_idx');
            }

            if (! Schema::hasColumn('treasury_accounts', 'requires_approval')) {
                $table->boolean('requires_approval')->default(false);
            }
        });

        DB::table('treasury_accounts')
            ->whereNull('cash_scope')
            ->update([
                'cash_scope' => DB::raw("case when type = 'bank' then 'bank' else 'general_cash' end"),
                'updated_at' => now(),
            ]);
    }

    private function extendTreasuryMovements(): void
    {
        if (! Schema::hasTable('treasury_movements')) {
            return;
        }

        Schema::table('treasury_movements', function (Blueprint $table): void {
            if (! Schema::hasColumn('treasury_movements', 'source_treasury_account_id')) {
                $table->unsignedBigInteger('source_treasury_account_id')->nullable()->index('tm_source_account_idx');
            }

            if (! Schema::hasColumn('treasury_movements', 'destination_treasury_account_id')) {
                $table->unsignedBigInteger('destination_treasury_account_id')->nullable()->index('tm_dest_account_idx');
            }

            if (! Schema::hasColumn('treasury_movements', 'pos_cash_movement_id')) {
                $table->unsignedBigInteger('pos_cash_movement_id')->nullable()->index('tm_pos_cash_mov_idx');
            }

            if (! Schema::hasColumn('treasury_movements', 'pos_order_payment_id')) {
                $table->unsignedBigInteger('pos_order_payment_id')->nullable()->index('tm_pos_payment_idx');
            }

            if (! Schema::hasColumn('treasury_movements', 'pos_session_id')) {
                $table->unsignedBigInteger('pos_session_id')->nullable()->index('tm_pos_session_idx');
            }

            if (! Schema::hasColumn('treasury_movements', 'pos_point_id')) {
                $table->unsignedBigInteger('pos_point_id')->nullable()->index('tm_pos_point_idx');
            }

            if (! Schema::hasColumn('treasury_movements', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->index('tm_branch_idx');
            }

            if (! Schema::hasColumn('treasury_movements', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->index('tm_warehouse_idx');
            }

            if (! Schema::hasColumn('treasury_movements', 'requested_by_user_id')) {
                $table->unsignedBigInteger('requested_by_user_id')->nullable()->index('tm_requested_by_idx');
            }

            if (! Schema::hasColumn('treasury_movements', 'approved_by_user_id')) {
                $table->unsignedBigInteger('approved_by_user_id')->nullable()->index('tm_approved_by_idx');
            }

            if (! Schema::hasColumn('treasury_movements', 'rejected_by_user_id')) {
                $table->unsignedBigInteger('rejected_by_user_id')->nullable()->index('tm_rejected_by_idx');
            }

            if (! Schema::hasColumn('treasury_movements', 'received_by_user_id')) {
                $table->unsignedBigInteger('received_by_user_id')->nullable()->index('tm_received_by_idx');
            }

            if (! Schema::hasColumn('treasury_movements', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }

            if (! Schema::hasColumn('treasury_movements', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }

            if (! Schema::hasColumn('treasury_movements', 'received_at')) {
                $table->timestamp('received_at')->nullable();
            }

            if (! Schema::hasColumn('treasury_movements', 'approval_notes')) {
                $table->text('approval_notes')->nullable();
            }

            if (! Schema::hasColumn('treasury_movements', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }
        });
    }

    private function extendPosCashMovements(): void
    {
        if (! Schema::hasTable('pos_cash_movements')) {
            return;
        }

        Schema::table('pos_cash_movements', function (Blueprint $table): void {
            if (! Schema::hasColumn('pos_cash_movements', 'treasury_movement_id')) {
                $table->unsignedBigInteger('treasury_movement_id')->nullable()->index('pcm_treasury_mov_idx');
            }

            if (! Schema::hasColumn('pos_cash_movements', 'treasury_transfer_request_id')) {
                $table->unsignedBigInteger('treasury_transfer_request_id')->nullable()->index('pcm_transfer_req_idx');
            }

            if (! Schema::hasColumn('pos_cash_movements', 'destination_treasury_account_id')) {
                $table->unsignedBigInteger('destination_treasury_account_id')->nullable()->index('pcm_dest_account_idx');
            }

            if (! Schema::hasColumn('pos_cash_movements', 'treasury_status')) {
                $table->string('treasury_status', 40)->default('not_linked')->index('pcm_treasury_status_idx');
            }

            if (! Schema::hasColumn('pos_cash_movements', 'approved_by_user_id')) {
                $table->unsignedBigInteger('approved_by_user_id')->nullable()->index('pcm_approved_by_idx');
            }

            if (! Schema::hasColumn('pos_cash_movements', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
        });
    }

    private function extendPosOrderPayments(): void
    {
        if (! Schema::hasTable('pos_order_payments')) {
            return;
        }

        Schema::table('pos_order_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('pos_order_payments', 'treasury_movement_id')) {
                $table->unsignedBigInteger('treasury_movement_id')->nullable()->index('pop_treasury_mov_idx');
            }

            if (! Schema::hasColumn('pos_order_payments', 'treasury_account_id')) {
                $table->unsignedBigInteger('treasury_account_id')->nullable()->index('pop_treasury_account_idx');
            }

            if (! Schema::hasColumn('pos_order_payments', 'treasury_posted_at')) {
                $table->timestamp('treasury_posted_at')->nullable();
            }
        });
    }

    private function createCashTransferRequests(): void
    {
        if (Schema::hasTable('treasury_cash_transfer_requests')) {
            return;
        }

        Schema::create('treasury_cash_transfer_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->index('tctr_company_idx');
            $table->unsignedBigInteger('branch_id')->nullable()->index('tctr_branch_idx');
            $table->unsignedBigInteger('warehouse_id')->nullable()->index('tctr_warehouse_idx');
            $table->unsignedBigInteger('pos_point_id')->nullable()->index('tctr_pos_point_idx');
            $table->unsignedBigInteger('pos_session_id')->nullable()->index('tctr_pos_session_idx');
            $table->unsignedBigInteger('pos_cash_movement_id')->nullable()->index('tctr_pos_cash_mov_idx');

            $table->unsignedBigInteger('source_treasury_account_id')->nullable()->index('tctr_source_account_idx');
            $table->unsignedBigInteger('destination_treasury_account_id')->nullable()->index('tctr_dest_account_idx');

            $table->string('number', 80)->nullable()->index('tctr_number_idx');
            $table->string('type', 50)->default('transfer')->index('tctr_type_idx');
            $table->string('status', 50)->default('draft')->index('tctr_status_idx');

            $table->decimal('amount', 18, 6);
            $table->string('currency_code', 10)->default('MXN');

            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->unsignedBigInteger('requested_by_user_id')->nullable()->index('tctr_requested_by_idx');
            $table->unsignedBigInteger('approved_by_user_id')->nullable()->index('tctr_approved_by_idx');
            $table->unsignedBigInteger('rejected_by_user_id')->nullable()->index('tctr_rejected_by_idx');
            $table->unsignedBigInteger('delivered_by_user_id')->nullable()->index('tctr_delivered_by_idx');
            $table->unsignedBigInteger('received_by_user_id')->nullable()->index('tctr_received_by_idx');

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('posted_at')->nullable();

            $table->unsignedBigInteger('outflow_treasury_movement_id')->nullable()->index('tctr_outflow_mov_idx');
            $table->unsignedBigInteger('inflow_treasury_movement_id')->nullable()->index('tctr_inflow_mov_idx');

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'tctr_company_status_idx');
            $table->index(['company_id', 'type'], 'tctr_company_type_idx');
        });
    }

    private function createCashTransferApprovalLogs(): void
    {
        if (Schema::hasTable('treasury_cash_transfer_approval_logs')) {
            return;
        }

        Schema::create('treasury_cash_transfer_approval_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->index('tctal_company_idx');
            $table->unsignedBigInteger('treasury_cash_transfer_request_id')->index('tctal_request_idx');
            $table->string('action', 60)->index('tctal_action_idx');
            $table->string('from_status', 60)->nullable();
            $table->string('to_status', 60)->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index('tctal_user_idx');
            $table->string('signer_name')->nullable();
            $table->string('signature_hash', 128)->nullable();
            $table->string('ip_address', 80)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }
};
