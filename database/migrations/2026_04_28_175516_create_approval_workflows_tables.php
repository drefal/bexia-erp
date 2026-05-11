<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'approval_manager_user_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unsignedBigInteger('approval_manager_user_id')->nullable()->index()->after('id');
            });
        }

        if (! Schema::hasTable('approval_workflows')) {
            Schema::create('approval_workflows', function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();

                $table->string('name');
                $table->string('document_type', 80)->index();

                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('priority')->default(100)->index();

                $table->decimal('amount_min', 18, 4)->nullable()->index();
                $table->decimal('amount_max', 18, 4)->nullable()->index();

                $table->unsignedBigInteger('applies_to_user_id')->nullable()->index();
                $table->string('applies_to_role_name')->nullable()->index();
                $table->unsignedBigInteger('applies_to_warehouse_id')->nullable()->index();

                $table->text('notes')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'document_type', 'is_active']);
            });
        }

        if (! Schema::hasTable('approval_workflow_steps')) {
            Schema::create('approval_workflow_steps', function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger('approval_workflow_id')->index();

                $table->unsignedInteger('sort_order')->default(1)->index();
                $table->string('name');
                $table->boolean('is_active')->default(true)->index();

                $table->string('approver_type', 80)->index();
                $table->unsignedBigInteger('approver_user_id')->nullable()->index();
                $table->string('approver_role_name')->nullable()->index();

                $table->boolean('require_all')->default(false);

                $table->decimal('amount_min', 18, 4)->nullable();
                $table->decimal('amount_max', 18, 4)->nullable();

                $table->text('notes')->nullable();

                $table->timestamps();

                $table->foreign('approval_workflow_id')
                    ->references('id')
                    ->on('approval_workflows')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('approval_requests')) {
            Schema::create('approval_requests', function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('approval_workflow_id')->nullable()->index();

                $table->string('approvable_type')->index();
                $table->unsignedBigInteger('approvable_id')->index();

                $table->string('document_type', 80)->index();
                $table->string('document_number')->nullable()->index();

                $table->unsignedBigInteger('requester_user_id')->nullable()->index();
                $table->string('requester_name')->nullable();

                $table->string('status', 40)->default('pending')->index();
                $table->unsignedInteger('current_step_order')->nullable();

                $table->decimal('amount_total', 18, 4)->nullable();

                $table->timestamp('sent_at')->nullable();
                $table->timestamp('completed_at')->nullable();

                $table->text('notes')->nullable();

                $table->timestamps();

                $table->index(['approvable_type', 'approvable_id']);
                $table->index(['company_id', 'document_type', 'status']);
            });
        }

        if (! Schema::hasTable('approval_request_steps')) {
            Schema::create('approval_request_steps', function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger('approval_request_id')->index();
                $table->unsignedBigInteger('approval_workflow_step_id')->nullable()->index();

                $table->unsignedInteger('step_order')->default(1)->index();
                $table->string('step_name');

                $table->string('approver_type', 80)->index();
                $table->unsignedBigInteger('approver_user_id')->nullable()->index();
                $table->string('approver_role_name')->nullable()->index();

                $table->string('status', 40)->default('pending')->index();

                $table->unsignedBigInteger('acted_by_user_id')->nullable()->index();
                $table->string('acted_by_name')->nullable();
                $table->timestamp('acted_at')->nullable();

                $table->text('comments')->nullable();

                $table->timestamps();

                $table->foreign('approval_request_id')
                    ->references('id')
                    ->on('approval_requests')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_request_steps');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_workflow_steps');
        Schema::dropIfExists('approval_workflows');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'approval_manager_user_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('approval_manager_user_id');
            });
        }
    }
};
