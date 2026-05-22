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

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.purchase-documents';

        public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('purchases.view')
            );
    }

}
