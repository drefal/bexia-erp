<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CashCounter extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Contador de efectivo';

    protected static ?string $title = 'Contador de efectivo';

    protected static string $view = 'filament.pages.cash-counter';

    public array $denominations = [];

    public function mount(): void
    {
        $companyId = $this->companyId();

        if (
            ! $companyId
            || ! Schema::hasTable('cash_denominations')
        ) {
            $this->denominations = [];

            return;
        }

        $this->denominations = DB::table('cash_denominations')
            ->where(function ($query) use ($companyId): void {
                $query
                    ->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            })
            ->where('is_active', true)
            ->orderByRaw(
                "case when type = 'bill' then 0 else 1 end"
            )
            ->orderByDesc('value')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'value',
                'type',
            ])
            ->unique(
                fn ($row): string =>
                    (string) $row->type
                    . '|'
                    . number_format((float) $row->value, 2, '.', '')
            )
            ->values()
            ->map(
                fn ($row): array => [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'value' => round((float) $row->value, 2),
                    'type' => (string) $row->type,
                ]
            )
            ->all();
    }

    protected static function canUseExpenses(): bool
    {
        try {
            return \App\Filament\Resources\PettyCashFundResource::canViewAny();
        } catch (\Throwable) {
            return false;
        }
    }

    protected static function canUsePos(): bool
    {
        try {
            return \App\Filament\Pages\PointOfSale::shouldRegisterNavigation();
        } catch (\Throwable) {
            return false;
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canUseExpenses()
            || static::canUsePos();
    }

    public static function canAccess(): bool
    {
        return static::canUseExpenses()
            || static::canUsePos();
    }

    public static function getNavigationItems(): array
    {
        $items = [];

        if (static::canUseExpenses()) {
            $items[] = NavigationItem::make(
                'Contador de efectivo'
            )
                ->group('Gastos')
                ->icon('heroicon-o-calculator')
                ->sort(15)
                ->url(static::getUrl())
                ->isActiveWhen(
                    fn (): bool =>
                        request()->routeIs(
                            'filament.admin.pages.cash-counter'
                        )
                );
        }

        if (static::canUsePos()) {
            $items[] = NavigationItem::make(
                'Contador de efectivo'
            )
                ->group('Punto de Venta')
                ->icon('heroicon-o-calculator')
                ->sort(65)
                ->url(static::getUrl())
                ->isActiveWhen(
                    fn (): bool =>
                        request()->routeIs(
                            'filament.admin.pages.cash-counter'
                        )
                );
        }

        return $items;
    }

    protected function companyId(): ?int
    {
        try {
            $tenant = Filament::getTenant();

            if (
                is_object($tenant)
                && method_exists($tenant, 'getKey')
            ) {
                return (int) $tenant->getKey();
            }

            if (is_numeric($tenant)) {
                return (int) $tenant;
            }
        } catch (\Throwable $e) {
        }

        $tenant = request()->route('tenant');

        if (
            is_object($tenant)
            && method_exists($tenant, 'getKey')
        ) {
            return (int) $tenant->getKey();
        }

        if (is_numeric($tenant)) {
            return (int) $tenant;
        }

        return auth()->user()?->company_id
            ? (int) auth()->user()->company_id
            : null;
    }
}
