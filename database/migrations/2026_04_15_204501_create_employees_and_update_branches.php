<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

                $table->string('name');
                $table->string('employee_number')->nullable();
                $table->string('position')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->boolean('active')->default(true);
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->index('company_id');
            });
        }

        if (! Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();

                $table->string('name');
                $table->string('code')->nullable();
                $table->boolean('active')->default(true);

                $table->string('address_line1')->nullable();
                $table->string('address_line2')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('postal_code')->nullable();
                $table->string('country')->nullable();

                $table->text('notes')->nullable();

                $table->timestamps();

                $table->index('company_id');
                $table->unique(['company_id', 'name']);
            });
        } else {
            Schema::table('branches', function (Blueprint $table) {
                if (! Schema::hasColumn('branches', 'manager_employee_id')) {
                    $table->foreignId('manager_employee_id')
                        ->nullable()
                        ->after('company_id')
                        ->constrained('employees')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('branches') && Schema::hasColumn('branches', 'manager_employee_id')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropConstrainedForeignId('manager_employee_id');
            });
        }

        Schema::dropIfExists('employees');
    }
};
