<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class AccountPayableAgingReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Cuentas por pagar';

    protected static ?string $navigationLabel = 'Antigüedad de saldos';

    protected static ?string $title = 'Antigüedad de saldos';

    protected static ?int $navigationSort = 30;

    protected static string $view = 'filament.pages.account-payable-aging-report';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && method_exists($user, 'can') && $user->can('account_payables.view_aging');
    }
}
