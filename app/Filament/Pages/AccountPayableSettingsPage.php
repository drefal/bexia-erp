<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class AccountPayableSettingsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Cuentas por pagar';

    protected static ?string $navigationLabel = 'Configuración CxP';

    protected static ?string $title = 'Configuración CxP';

    protected static ?int $navigationSort = 50;

    protected static string $view = 'filament.pages.account-payable-settings-page';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && method_exists($user, 'can') && $user->can('account_payables.configure');
    }
}
