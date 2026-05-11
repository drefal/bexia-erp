<?php

return [

    'models' => [

        /*
         * Usa tus modelos extendidos para poder agregar relación company()
         * y para que Filament pueda hacer tenancy sin romperse.
         */
        'permission' => App\Models\Permission::class,
        'role'       => App\Models\Role::class,
    ],

    'table_names' => [
        'roles'                 => 'roles',
        'permissions'           => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles'       => 'model_has_roles',
        'role_has_permissions'  => 'role_has_permissions',
    ],

    /*
     * ✅ SOLO UNA VEZ (antes lo tenías duplicado)
     */
    'column_names' => [
        'role_pivot_key'       => null, // default 'role_id'
        'permission_pivot_key' => null, // default 'permission_id'
        'model_morph_key'      => 'model_id',

        // ✅ Clave del team (tenant) en BD:
        'team_foreign_key'     => 'company_id',
    ],

    'register_permission_check_method' => true,
    'register_octane_reset_listener'   => false,

    'events_enabled' => false,

    /*
     * ✅ Teams activado (Spatie Teams)
     */
    'teams'            => true,

    /*
     * ✅ ESTE debe ser company_id (no team_id)
     */
    'team_foreign_key' => 'company_id',

    /*
     * Resolver del team id
     */
    'team_resolver' => \Spatie\Permission\DefaultTeamResolver::class,

    'use_passport_client_credentials' => false,

    'display_permission_in_exception' => false,
    'display_role_in_exception'       => false,

    'enable_wildcard_permission' => false,

    'cache' => [
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),
        'key'             => 'spatie.permission.cache',
        'store'           => 'default',
    ],
];
