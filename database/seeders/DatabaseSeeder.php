<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Sembrar tenants, permisos y roles por empresa
        $this->call([
            TenantBaseSeeder::class,
        ]);

        // 2) (Opcional) Crear/asegurar usuario admin y asignarlo SOLO en grupol7
        $admin = \App\Models\User::firstOrCreate(
            ['email' => 'admin@bexiaerp.com'],
            ['name' => 'Admin Bexia', 'password' => bcrypt('Bexia#Adm2025')]
        );

        $companyId = \App\Models\Company::where('slug', 'grupol7')->value('id');
        if ($companyId) {
            setPermissionsTeamId($companyId);           // fija el team (company)
            if (! $admin->hasRole('admin')) {
                $admin->assignRole('admin');            // se asigna solo en grupol7
            }
        }
    }
}
