<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_channels', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('documentation_url', 500)->nullable();
            $table->jsonb('supported_countries')->nullable();
            $table->string('lifecycle_status', 30)
                ->default('draft');
        });

        Schema::create('commerce_connector_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commerce_channel_id')
                ->constrained('commerce_channels')
                ->restrictOnDelete();

            $table->string('version', 60);
            $table->string('adapter_key', 120);
            $table->string('api_version', 100)->nullable();
            $table->string('auth_type', 50);
            $table->string('status', 30)->default('draft');
            $table->jsonb('capabilities')->nullable();
            $table->jsonb('settings_schema')->nullable();
            $table->text('connection_instructions')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();

            $table->unique(
                ['commerce_channel_id', 'version'],
                'commerce_connector_version_unique'
            );
        });

        Schema::create('commerce_auth_fields', function (Blueprint $table) {
            $table->id();

            $table->foreignId('commerce_connector_version_id')
                ->constrained('commerce_connector_versions')
                ->cascadeOnDelete();

            $table->string('field_key', 100);
            $table->string('label', 150);
            $table->string('field_type', 30)->default('text');
            $table->string('credential_owner', 20);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_secret')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('help_text')->nullable();
            $table->jsonb('validation_rules')->nullable();
            $table->timestamps();

            $table->unique(
                ['commerce_connector_version_id', 'field_key'],
                'commerce_auth_field_unique'
            );
        });

        Schema::create('commerce_app_credentials', function (Blueprint $table) {
            $table->id();

            $table->foreignId('commerce_connector_version_id')
                ->constrained('commerce_connector_versions')
                ->restrictOnDelete();

            $table->string('field_key', 100);
            $table->text('secret_value');
            $table->timestamps();

            $table->unique(
                ['commerce_connector_version_id', 'field_key'],
                'commerce_app_credential_unique'
            );
        });

        Schema::create('commerce_account_credentials', function (Blueprint $table) {
            $table->id();

            $table->foreignId('commerce_account_id')
                ->constrained('commerce_accounts')
                ->cascadeOnDelete();

            $table->string('field_key', 100);
            $table->text('secret_value');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['commerce_account_id', 'field_key'],
                'commerce_account_credential_unique'
            );
        });

        Schema::create('commerce_account_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('commerce_account_id')
                ->constrained('commerce_accounts')
                ->cascadeOnDelete();

            $table->string('setting_key', 100);
            $table->jsonb('setting_value')->nullable();
            $table->timestamps();

            $table->unique(
                ['commerce_account_id', 'setting_key'],
                'commerce_account_setting_unique'
            );
        });

        Schema::create('commerce_company_channels', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->foreignId('commerce_channel_id')
                ->constrained('commerce_channels')
                ->restrictOnDelete();

            $table->boolean('is_enabled')->default(false);
            $table->string('status', 30)->default('available');
            $table->timestamps();

            $table->unique(
                ['company_id', 'commerce_channel_id'],
                'commerce_company_channel_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_company_channels');
        Schema::dropIfExists('commerce_account_settings');
        Schema::dropIfExists('commerce_account_credentials');
        Schema::dropIfExists('commerce_app_credentials');
        Schema::dropIfExists('commerce_auth_fields');
        Schema::dropIfExists('commerce_connector_versions');

        Schema::table('commerce_channels', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'logo_path',
                'documentation_url',
                'supported_countries',
                'lifecycle_status',
            ]);
        });
    }
};
