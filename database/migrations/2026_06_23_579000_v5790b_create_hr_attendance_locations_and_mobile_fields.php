<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_attendance_locations')) {
            Schema::create('hr_attendance_locations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('name');
                $table->string('code')->nullable();
                $table->text('address')->nullable();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->unsignedInteger('radius_meters')->default(100);
                $table->unsignedInteger('accuracy_required_meters')->nullable();
                $table->boolean('allow_mobile_clock_in')->default(true);
                $table->boolean('requires_review_when_outside')->default(true);
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['company_id', 'is_active']);
                $table->index(['company_id', 'branch_id']);
                $table->unique(['company_id', 'code']);
            });
        }

        Schema::table('employee_attendances', function (Blueprint $table): void {
            if (! Schema::hasColumn('employee_attendances', 'clock_in_method')) {
                $table->string('clock_in_method')->nullable()->after('source');
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_out_method')) {
                $table->string('clock_out_method')->nullable()->after('clock_in_method');
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_in_hr_attendance_location_id')) {
                $table->foreignId('clock_in_hr_attendance_location_id')
                    ->nullable()
                    ->after('clock_out_method')
                    ->constrained('hr_attendance_locations')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_out_hr_attendance_location_id')) {
                $table->foreignId('clock_out_hr_attendance_location_id')
                    ->nullable()
                    ->after('clock_in_hr_attendance_location_id')
                    ->constrained('hr_attendance_locations')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_in_latitude')) {
                $table->decimal('clock_in_latitude', 10, 7)->nullable()->after('clock_out_hr_attendance_location_id');
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_in_longitude')) {
                $table->decimal('clock_in_longitude', 10, 7)->nullable()->after('clock_in_latitude');
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_in_accuracy_meters')) {
                $table->unsignedInteger('clock_in_accuracy_meters')->nullable()->after('clock_in_longitude');
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_in_distance_meters')) {
                $table->unsignedInteger('clock_in_distance_meters')->nullable()->after('clock_in_accuracy_meters');
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_in_location_status')) {
                $table->string('clock_in_location_status')->nullable()->after('clock_in_distance_meters');
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_out_latitude')) {
                $table->decimal('clock_out_latitude', 10, 7)->nullable()->after('clock_in_location_status');
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_out_longitude')) {
                $table->decimal('clock_out_longitude', 10, 7)->nullable()->after('clock_out_latitude');
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_out_accuracy_meters')) {
                $table->unsignedInteger('clock_out_accuracy_meters')->nullable()->after('clock_out_longitude');
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_out_distance_meters')) {
                $table->unsignedInteger('clock_out_distance_meters')->nullable()->after('clock_out_accuracy_meters');
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_out_location_status')) {
                $table->string('clock_out_location_status')->nullable()->after('clock_out_distance_meters');
            }

            if (! Schema::hasColumn('employee_attendances', 'mobile_review_status')) {
                $table->string('mobile_review_status')->nullable()->after('clock_out_location_status');
            }

            if (! Schema::hasColumn('employee_attendances', 'mobile_reviewed_by_user_id')) {
                $table->foreignId('mobile_reviewed_by_user_id')
                    ->nullable()
                    ->after('mobile_review_status')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('employee_attendances', 'mobile_reviewed_at')) {
                $table->timestamp('mobile_reviewed_at')->nullable()->after('mobile_reviewed_by_user_id');
            }

            if (! Schema::hasColumn('employee_attendances', 'mobile_review_notes')) {
                $table->text('mobile_review_notes')->nullable()->after('mobile_reviewed_at');
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_in_ip_address')) {
                $table->string('clock_in_ip_address')->nullable()->after('mobile_review_notes');
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_out_ip_address')) {
                $table->string('clock_out_ip_address')->nullable()->after('clock_in_ip_address');
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_in_user_agent')) {
                $table->text('clock_in_user_agent')->nullable()->after('clock_out_ip_address');
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_out_user_agent')) {
                $table->text('clock_out_user_agent')->nullable()->after('clock_in_user_agent');
            }
        });

        if (Schema::hasTable('permissions')) {
            $now = now();
            $permissions = [
                'rrhh.geocercas.ver',
                'rrhh.geocercas.crear',
                'rrhh.geocercas.editar',
                'rrhh.geocercas.eliminar',
                'rrhh.asistencias.mobile_clock',
                'rrhh.asistencias.revisar_geocerca',
            ];

            foreach ($permissions as $permission) {
                $exists = DB::table('permissions')
                    ->where('name', $permission)
                    ->where('guard_name', 'web')
                    ->exists();

                if ($exists) {
                    DB::table('permissions')
                        ->where('name', $permission)
                        ->where('guard_name', 'web')
                        ->update([
                            'updated_at' => $now,
                        ]);
                } else {
                    DB::table('permissions')->insert([
                        'name' => $permission,
                        'guard_name' => 'web',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('employee_attendances', function (Blueprint $table): void {
            $foreignColumns = [
                'clock_in_hr_attendance_location_id',
                'clock_out_hr_attendance_location_id',
                'mobile_reviewed_by_user_id',
            ];

            foreach ($foreignColumns as $column) {
                if (Schema::hasColumn('employee_attendances', $column)) {
                    try {
                        $table->dropConstrainedForeignId($column);
                    } catch (Throwable $e) {
                        $table->dropColumn($column);
                    }
                }
            }

            $columns = [
                'clock_in_method',
                'clock_out_method',
                'clock_in_latitude',
                'clock_in_longitude',
                'clock_in_accuracy_meters',
                'clock_in_distance_meters',
                'clock_in_location_status',
                'clock_out_latitude',
                'clock_out_longitude',
                'clock_out_accuracy_meters',
                'clock_out_distance_meters',
                'clock_out_location_status',
                'mobile_review_status',
                'mobile_reviewed_at',
                'mobile_review_notes',
                'clock_in_ip_address',
                'clock_out_ip_address',
                'clock_in_user_agent',
                'clock_out_user_agent',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('employee_attendances', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('hr_attendance_locations');
    }
};
