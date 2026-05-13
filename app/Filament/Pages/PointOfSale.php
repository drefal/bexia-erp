<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;

class PointOfSale extends Page
{
    protected static ?string $navigationGroup = 'Punto de Venta';

    protected static ?string $navigationLabel = 'Punto de Venta';

    protected static ?string $title = 'Punto de Venta';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.point-of-sale';

public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('pos.menu.view')
            );
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('pos.menu.view')
            );
    }

}
