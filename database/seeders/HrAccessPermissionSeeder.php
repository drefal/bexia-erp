<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Attendance\AttendanceAccessAuthorization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class HrAccessPermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {

            foreach (
                AttendanceAccessAuthorization::permissions()
                as $permission
            ) {

                Permission::query()->firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'web',
                ]);
            }
        });

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}
