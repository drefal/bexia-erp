<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class SupplierStatementPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Cuentas por pagar';

    protected static ?string $navigationLabel = 'Estados de cuenta proveedor';

    protected static ?string $title = 'Estados de cuenta proveedor';

    protected static ?int $navigationSort = 40;

    protected static string $view = 'filament.pages.supplier-statement-page';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && method_exists($user, 'can') && $user->can('account_payables.view_supplier_statement');
    }
}
