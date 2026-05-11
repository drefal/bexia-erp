<?php

namespace App\Providers;

use Filament\Support\Facades\FilamentView;
use Illuminate\Support\ServiceProvider;

class ApprovalUiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! class_exists(FilamentView::class)) {
            return;
        }

        $hook = 'panels::user-menu.before';

        if (class_exists(\Filament\View\PanelsRenderHook::class)) {
            $enumClass = \Filament\View\PanelsRenderHook::class;

            if (defined($enumClass . '::USER_MENU_BEFORE')) {
                $hook = constant($enumClass . '::USER_MENU_BEFORE');
            }
        }

        FilamentView::registerRenderHook(
            $hook,
            fn (): string => view('filament.components.approval-topbar-badge')->render(),
        );
    }
}
