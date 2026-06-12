<?php

namespace App\Filament\Pages;

use App\Support\Security\BexiaAccess;
use Filament\Pages\Dashboard as BaseDashboard;

class BexiaDashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Escritorio';

    protected static ?string $title = 'Escritorio';

    public static function shouldRegisterNavigation(): bool
    {
        return BexiaAccess::dashboard();
    }

    public static function canAccess(): bool
    {
        return BexiaAccess::dashboard();
    }
}
