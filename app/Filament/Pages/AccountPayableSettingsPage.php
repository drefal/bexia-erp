<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountPayableSettingsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Cuentas por pagar';

    protected static ?string $navigationLabel = 'Configuración CxP';

    protected static ?string $title = 'Configuración CxP';

    protected static ?int $navigationSort = 50;

    protected static string $view = 'filament.pages.account-payable-settings-page';

    public ?int $defaultTreasuryAccountId = null;

    public ?int $defaultPaymentFormId = null;

    public int $defaultDueDays = 30;

    public float $roundingTolerance = 0.02;

    public bool $allowOverpayment = false;

    public bool $showLogoOnPdf = true;

    public function mount(): void
    {
        $companyId = $this->tenantCompanyId();

        if (! $companyId) {
            return;
        }

        $settings = DB::table('account_payable_settings')
            ->where('company_id', $companyId)
            ->first();

        if (! $settings) {
            $this->defaultTreasuryAccountId = $this->firstTreasuryAccountId($companyId);
            $this->defaultPaymentFormId = $this->firstPaymentFormId($companyId);

            return;
        }

        $this->defaultTreasuryAccountId = $settings->default_treasury_account_id ? (int) $settings->default_treasury_account_id : null;
        $this->defaultPaymentFormId = $settings->default_payment_form_id ? (int) $settings->default_payment_form_id : null;
        $this->defaultDueDays = (int) ($settings->default_due_days ?? 30);
        $this->roundingTolerance = (float) ($settings->rounding_tolerance ?? 0.02);
        $this->allowOverpayment = (bool) ($settings->allow_overpayment ?? false);
        $this->showLogoOnPdf = (bool) ($settings->show_logo_on_pdf ?? true);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && method_exists($user, 'can') && $user->can('account_payables.configure');
    }

    public function save(): void
    {
        $companyId = $this->tenantCompanyId();

        if (! $companyId) {
            Notification::make()
                ->title('No se encontró la empresa activa')
                ->danger()
                ->send();

            return;
        }

        $this->validate([
            'defaultTreasuryAccountId' => ['nullable', 'integer'],
            'defaultPaymentFormId' => ['nullable', 'integer'],
            'defaultDueDays' => ['required', 'integer', 'min:0', 'max:365'],
            'roundingTolerance' => ['required', 'numeric', 'min:0', 'max:999999'],
            'allowOverpayment' => ['boolean'],
            'showLogoOnPdf' => ['boolean'],
        ]);

        if ($this->defaultTreasuryAccountId && ! $this->treasuryAccountExists($companyId, (int) $this->defaultTreasuryAccountId)) {
            Notification::make()
                ->title('Cuenta de tesorería inválida')
                ->body('La cuenta/caja seleccionada no existe o no está activa en esta empresa.')
                ->danger()
                ->send();

            return;
        }

        if ($this->defaultPaymentFormId && ! $this->paymentFormExists($companyId, (int) $this->defaultPaymentFormId)) {
            Notification::make()
                ->title('Forma de pago inválida')
                ->body('La forma de pago seleccionada no existe o no está activa en esta empresa.')
                ->danger()
                ->send();

            return;
        }

        DB::table('account_payable_settings')->updateOrInsert(
            ['company_id' => $companyId],
            [
                'default_treasury_account_id' => $this->defaultTreasuryAccountId ?: null,
                'default_payment_form_id' => $this->defaultPaymentFormId ?: null,
                'default_due_days' => $this->defaultDueDays,
                'rounding_tolerance' => round((float) $this->roundingTolerance, 4),
                'allow_overpayment' => (bool) $this->allowOverpayment,
                'show_logo_on_pdf' => (bool) $this->showLogoOnPdf,
                'metadata' => json_encode([
                    'updated_from' => 'account_payable_settings_page',
                    'version' => 'v5.56.8',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Notification::make()
            ->title('Configuración CxP guardada')
            ->success()
            ->send();
    }

    public function getTreasuryAccountsProperty(): Collection
    {
        $companyId = $this->tenantCompanyId();

        if (! $companyId) {
            return collect();
        }

        return DB::table('treasury_accounts')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'currency_code', 'current_balance']);
    }

    public function getPaymentFormsProperty(): Collection
    {
        $companyId = $this->tenantCompanyId();

        if (! $companyId) {
            return collect();
        }

        return DB::table('payment_forms')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'sat_payment_form_code', 'name']);
    }

    protected function tenantCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        return $tenant && method_exists($tenant, 'getKey')
            ? (int) $tenant->getKey()
            : null;
    }

    protected function treasuryAccountExists(int $companyId, int $id): bool
    {
        return DB::table('treasury_accounts')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('id', $id)
            ->exists();
    }

    protected function paymentFormExists(int $companyId, int $id): bool
    {
        return DB::table('payment_forms')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('id', $id)
            ->exists();
    }

    protected function firstTreasuryAccountId(int $companyId): ?int
    {
        $id = DB::table('treasury_accounts')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->value('id');

        return $id ? (int) $id : null;
    }

    protected function firstPaymentFormId(int $companyId): ?int
    {
        $id = DB::table('payment_forms')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->value('id');

        return $id ? (int) $id : null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'pages.accountpayablesettingspage',
            fn (): bool => method_exists(static::class, 'canViewAny')
                ? static::canViewAny()
                : (method_exists(static::class, 'canAccess') ? static::canAccess() : true),
        );
    }

}
