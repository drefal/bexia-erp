<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CFDI nomina - timbrado real
    |--------------------------------------------------------------------------
    |
    | Por seguridad, el timbrado real de CFDI nomina requiere dos candados:
    |
    | 1) APP_ENV debe coincidir con stamping_allowed_env, normalmente production.
    | 2) PAYROLL_CFDI_STAMPING_ENABLED debe estar en true.
    |
    | En DEV debe permanecer false.
    |
    */

    'stamping_enabled' => env('PAYROLL_CFDI_STAMPING_ENABLED', false),

    'stamping_allowed_env' => env('PAYROLL_CFDI_STAMPING_ALLOWED_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | CFDI nomina - cancelacion real
    |--------------------------------------------------------------------------
    |
    | Igual que el timbrado, la cancelacion real requiere candado por ambiente
    | y una variable explicita. En DEV debe permanecer false.
    |
    */

    'cancellation_enabled' => env('PAYROLL_CFDI_CANCELLATION_ENABLED', false),

    'cancellation_allowed_env' => env('PAYROLL_CFDI_CANCELLATION_ALLOWED_ENV', 'production'),
];
