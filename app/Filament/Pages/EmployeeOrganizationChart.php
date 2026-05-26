<?php

namespace App\Filament\Pages;

use App\Support\EmployeeOrganizationResolver;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class EmployeeOrganizationChart extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'RRHH';

    protected static ?string $navigationLabel = 'Organigrama';

    protected static ?string $title = 'Organigrama';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.employee-organization-chart';

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return (bool) ($user->is_system_admin ?? false)
            || ($user->email ?? null) === 'admin@bexiaerp.com'
            || $user->can('company.update')
            || $user->can('rrhh.empleados.ver')
            || $user->can('rrhh.contratos.ver');
    }

    public function rows(): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        return EmployeeOrganizationResolver::organizationRows($tenantId ? (int) $tenantId : null);
    }
}
