<?php

namespace App\Support\Cxp;

use Illuminate\Support\Facades\DB;

class AccountPayableSettings
{
    public static function forCompany(?int $companyId): ?object
    {
        if (! $companyId) {
            return null;
        }

        return DB::table('account_payable_settings')
            ->where('company_id', $companyId)
            ->first();
    }

    public static function defaultTreasuryAccountId(?int $companyId): ?int
    {
        $settings = self::forCompany($companyId);
        $id = $settings?->default_treasury_account_id;

        return $id ? (int) $id : null;
    }

    public static function defaultPaymentFormId(?int $companyId): ?int
    {
        $settings = self::forCompany($companyId);
        $id = $settings?->default_payment_form_id;

        return $id ? (int) $id : null;
    }

    public static function defaultDueDays(?int $companyId): int
    {
        $settings = self::forCompany($companyId);

        return max(0, (int) ($settings?->default_due_days ?? 30));
    }

    public static function roundingTolerance(?int $companyId): float
    {
        $settings = self::forCompany($companyId);

        return max(0.0, (float) ($settings?->rounding_tolerance ?? 0.02));
    }

    public static function allowOverpayment(?int $companyId): bool
    {
        $settings = self::forCompany($companyId);

        return (bool) ($settings?->allow_overpayment ?? false);
    }

    public static function showLogoOnPdf(?int $companyId): bool
    {
        $settings = self::forCompany($companyId);

        return (bool) ($settings?->show_logo_on_pdf ?? true);
    }
}
