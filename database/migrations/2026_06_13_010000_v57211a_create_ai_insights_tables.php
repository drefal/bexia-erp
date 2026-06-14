<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_insight_conversations')) {
            Schema::create('ai_insight_conversations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->nullable()->index();
                $table->foreignId('company_group_id')->nullable()->index();
                $table->foreignId('user_id')->index();
                $table->string('title')->nullable();
                $table->json('allowed_company_ids')->nullable();
                $table->string('status')->default('open')->index();
                $table->timestamp('last_message_at')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('ai_insight_messages')) {
            Schema::create('ai_insight_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('conversation_id')->constrained('ai_insight_conversations')->cascadeOnDelete();
                $table->foreignId('company_id')->nullable()->index();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('role')->index(); // user, assistant, system, tool
                $table->longText('content')->nullable();
                $table->json('metadata')->nullable();
                $table->unsignedInteger('tokens_prompt')->default(0);
                $table->unsignedInteger('tokens_completion')->default(0);
                $table->decimal('estimated_cost', 12, 6)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_insight_tool_runs')) {
            Schema::create('ai_insight_tool_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('conversation_id')->nullable()->constrained('ai_insight_conversations')->nullOnDelete();
                $table->foreignId('message_id')->nullable()->constrained('ai_insight_messages')->nullOnDelete();
                $table->foreignId('company_id')->nullable()->index();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('tool_name')->index();
                $table->json('allowed_company_ids')->nullable();
                $table->json('input')->nullable();
                $table->json('output_summary')->nullable();
                $table->string('status')->default('pending')->index();
                $table->text('error_message')->nullable();
                $table->unsignedInteger('duration_ms')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_insight_audit_logs')) {
            Schema::create('ai_insight_audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('conversation_id')->nullable()->constrained('ai_insight_conversations')->nullOnDelete();
                $table->foreignId('message_id')->nullable()->constrained('ai_insight_messages')->nullOnDelete();
                $table->foreignId('company_id')->nullable()->index();
                $table->foreignId('company_group_id')->nullable()->index();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('event')->index();
                $table->json('allowed_company_ids')->nullable();
                $table->json('payload')->nullable();
                $table->ipAddress('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        }

        $permissions = [
            'ai_insights.access',
            'ai_insights.director',
            'ai_insights.admin',
            'ai_insights.view_audit',
            'ai_insights.configure',
        ];

        if (Schema::hasTable('permissions')) {
            foreach ($permissions as $permission) {
                $exists = DB::table('permissions')
                    ->where('name', $permission)
                    ->where('guard_name', 'web')
                    ->exists();

                if (! $exists) {
                    DB::table('permissions')->insert([
                        'name' => $permission,
                        'guard_name' => 'web',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->whereIn('name', [
                    'ai_insights.access',
                    'ai_insights.director',
                    'ai_insights.admin',
                    'ai_insights.view_audit',
                    'ai_insights.configure',
                ])
                ->delete();
        }

        Schema::dropIfExists('ai_insight_audit_logs');
        Schema::dropIfExists('ai_insight_tool_runs');
        Schema::dropIfExists('ai_insight_messages');
        Schema::dropIfExists('ai_insight_conversations');
    }
};
