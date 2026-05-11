<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use App\Models\Company;
use App\Models\User;

class TenantBaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1) Asegura una empresa base
            $company = Company::firstOrCreate(
                ['slug' => 'acme'],
                ['name' => 'Acme, Inc.']
            );

            // 2) Team/tenant actual para Spatie = esta empresa
            app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

            // 3) Rol admin en el contexto de esta empresa
            $adminRole = Role::firstOrCreate([
                'name'       => 'admin',
                'guard_name' => 'web',
                'company_id' => $company->id, // clave de multi-empresa
            ]);

            // 4) Usuario admin
            $user = User::firstOrCreate(
                ['email' => 'admin@bexiaerp.com'],
                ['name' => 'Admin', 'password' => bcrypt('Cambiar123!')]
            );

            // 5) Vincula usuario ↔ empresa (pivot company_user)
            if (method_exists($user, 'companies')) {
                $user->companies()->syncWithoutDetaching([$company->id]);
            }

            // 6) Asigna rol en el team/empresa actual
            $user->assignRole($adminRole);
        });
    }
}
