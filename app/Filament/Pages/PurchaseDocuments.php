<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;

class PurchaseDocuments extends Page
{
    protected static ?string $navigationGroup = 'Compras';

    protected static ?string $navigationLabel = 'Solicitudes / Órdenes';

    protected static ?string $title = 'Solicitudes / Órdenes';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?int $navigationSort = 200;

    protected static string $view = 'filament.pages.purchase-documents';

    public static function shouldRegisterNavigation(): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
            return true;
        }

        if (method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin()) {
            return true;
        }

        return method_exists($user, 'can') && (
            $user->can('purchases.view')
            || $user->can('inventory.view')
        );
    }
}
