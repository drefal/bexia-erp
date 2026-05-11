<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Si ya tienes estos modelos, descomenta las líneas y úsalo por compañía.
// use App\Models\Company;
// use App\Models\User;

class RolesPerCompanySeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);

        // ======= Opción simple (sin depender todavía de Company): roles “globales” (team = 0) =======
        $registrar->setPermissionsTeamId(0); // 0 = global (acorde a nuestra migración de pivotes)
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'company_id' => null]);
        Role::firstOrCreate(['name' => 'user',  'guard_name' => 'web', 'company_id' => null]);

        // ======= Opción por compañía (cuando ya tengas tabla y modelo Company) =======
        /*
        Company::query()->each(function (Company $company) use ($registrar) {
            $registrar->setPermissionsTeamId($company->id);

            Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'company_id' => $company->id]);
            Role::firstOrCreate(['name' => 'user',  'guard_name' => 'web', 'company_id' => $company->id]);
        });
        */

        $registrar->forgetCachedPermissions();
    }
}
