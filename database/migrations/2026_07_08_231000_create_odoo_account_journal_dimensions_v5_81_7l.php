<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function ensureColumn(string $table, string $column, string $definition): void
    {
        if (! Schema::hasColumn($table, $column)) {
            DB::statement("alter table {$table} add column {$column} {$definition}");
        }
    }

    private function ensureAccountDimensions(): void
    {
        if (! Schema::hasTable('odoo_account_dimensions')) {
            Schema::create('odoo_account_dimensions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('odoo_account_id')->unique();
                $table->string('code')->nullable();
                $table->string('name')->nullable();
                $table->unsignedBigInteger('refs')->default(0);
                $table->decimal('debit_total', 20, 2)->default(0);
                $table->decimal('credit_total', 20, 2)->default(0);
                $table->decimal('balance_total', 20, 2)->default(0);
                $table->date('first_date')->nullable();
                $table->date('last_date')->nullable();
                $table->string('status', 100)->nullable();
                $table->text('notes')->nullable();
                $table->jsonb('raw_json')->nullable();
                $table->timestamps();
            });
        }

        foreach ([
            ['odoo_account_id', 'bigint'],
            ['code', 'varchar(255)'],
            ['name', 'varchar(255)'],
            ['refs', 'bigint default 0'],
            ['debit_total', 'numeric(20, 2) default 0'],
            ['credit_total', 'numeric(20, 2) default 0'],
            ['balance_total', 'numeric(20, 2) default 0'],
            ['first_date', 'date'],
            ['last_date', 'date'],
            ['status', 'varchar(100)'],
            ['notes', 'text'],
            ['raw_json', 'jsonb'],
            ['created_at', 'timestamp null'],
            ['updated_at', 'timestamp null'],
        ] as [$column, $definition]) {
            $this->ensureColumn('odoo_account_dimensions', $column, $definition);
        }

        DB::statement('create unique index if not exists odoo_account_dimensions_odoo_account_id_uidx on odoo_account_dimensions (odoo_account_id)');
    }

    private function ensureJournalDimensions(): void
    {
        if (! Schema::hasTable('odoo_journal_dimensions')) {
            Schema::create('odoo_journal_dimensions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('odoo_journal_id')->unique();
                $table->string('code')->nullable();
                $table->string('name')->nullable();
                $table->unsignedBigInteger('refs')->default(0);
                $table->decimal('move_amount_total', 20, 2)->default(0);
                $table->decimal('payment_amount_total', 20, 2)->default(0);
                $table->date('first_date')->nullable();
                $table->date('last_date')->nullable();
                $table->string('status', 100)->nullable();
                $table->text('notes')->nullable();
                $table->jsonb('raw_json')->nullable();
                $table->timestamps();
            });
        }

        foreach ([
            ['odoo_journal_id', 'bigint'],
            ['code', 'varchar(255)'],
            ['name', 'varchar(255)'],
            ['refs', 'bigint default 0'],
            ['move_amount_total', 'numeric(20, 2) default 0'],
            ['payment_amount_total', 'numeric(20, 2) default 0'],
            ['first_date', 'date'],
            ['last_date', 'date'],
            ['status', 'varchar(100)'],
            ['notes', 'text'],
            ['raw_json', 'jsonb'],
            ['created_at', 'timestamp null'],
            ['updated_at', 'timestamp null'],
        ] as [$column, $definition]) {
            $this->ensureColumn('odoo_journal_dimensions', $column, $definition);
        }

        DB::statement('create unique index if not exists odoo_journal_dimensions_odoo_journal_id_uidx on odoo_journal_dimensions (odoo_journal_id)');
    }

    private function ensureAccountMaps(): void
    {
        if (! Schema::hasTable('odoo_account_maps')) {
            Schema::create('odoo_account_maps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('odoo_account_id')->unique();
                $table->unsignedBigInteger('bexia_account_dimension_id')->nullable();
                $table->string('code')->nullable();
                $table->string('name')->nullable();
                $table->string('status', 100)->nullable();
                $table->string('match_method')->nullable();
                $table->integer('confidence')->nullable();
                $table->text('notes')->nullable();
                $table->jsonb('raw_json')->nullable();
                $table->timestamps();
            });
        }

        foreach ([
            ['odoo_account_id', 'bigint'],
            ['bexia_account_dimension_id', 'bigint'],
            ['code', 'varchar(255)'],
            ['name', 'varchar(255)'],
            ['status', 'varchar(100)'],
            ['match_method', 'varchar(255)'],
            ['confidence', 'integer'],
            ['notes', 'text'],
            ['raw_json', 'jsonb'],
            ['created_at', 'timestamp null'],
            ['updated_at', 'timestamp null'],
        ] as [$column, $definition]) {
            $this->ensureColumn('odoo_account_maps', $column, $definition);
        }

        DB::statement('create unique index if not exists odoo_account_maps_odoo_account_id_uidx on odoo_account_maps (odoo_account_id)');
    }

    private function ensureJournalMaps(): void
    {
        if (! Schema::hasTable('odoo_journal_maps')) {
            Schema::create('odoo_journal_maps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('odoo_journal_id')->unique();
                $table->unsignedBigInteger('bexia_journal_dimension_id')->nullable();
                $table->string('code')->nullable();
                $table->string('name')->nullable();
                $table->string('status', 100)->nullable();
                $table->string('match_method')->nullable();
                $table->integer('confidence')->nullable();
                $table->text('notes')->nullable();
                $table->jsonb('raw_json')->nullable();
                $table->timestamps();
            });
        }

        foreach ([
            ['odoo_journal_id', 'bigint'],
            ['bexia_journal_dimension_id', 'bigint'],
            ['code', 'varchar(255)'],
            ['name', 'varchar(255)'],
            ['status', 'varchar(100)'],
            ['match_method', 'varchar(255)'],
            ['confidence', 'integer'],
            ['notes', 'text'],
            ['raw_json', 'jsonb'],
            ['created_at', 'timestamp null'],
            ['updated_at', 'timestamp null'],
        ] as [$column, $definition]) {
            $this->ensureColumn('odoo_journal_maps', $column, $definition);
        }

        DB::statement('create unique index if not exists odoo_journal_maps_odoo_journal_id_uidx on odoo_journal_maps (odoo_journal_id)');
    }

    public function up(): void
    {
        $this->ensureAccountDimensions();
        $this->ensureJournalDimensions();
        $this->ensureAccountMaps();
        $this->ensureJournalMaps();
    }

    public function down(): void
    {
        // No-op intencional: estas tablas preservan trazabilidad historica de Odoo.
    }
};
