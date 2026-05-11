<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingSetting extends Model
{
    protected $fillable = [
        'company_id',
        'inventory_account_id',
        'cogs_account_id',
        'sales_income_account_id',
        'customer_receivable_account_id',
        'supplier_payable_account_id',
        'vat_creditable_account_id',
        'vat_payable_account_id',
        'cash_account_id',
        'bank_account_id',
        'inventory_adjustment_account_id',
        'default_journal_id',
        'purchases_journal_id',
        'sales_journal_id',
        'pos_journal_id',
        'costing_method',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'inventory_account_id');
    }

    public function cogsAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'cogs_account_id');
    }

    public function salesIncomeAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'sales_income_account_id');
    }

    public function customerReceivableAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'customer_receivable_account_id');
    }

    public function supplierPayableAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'supplier_payable_account_id');
    }

    public function vatCreditableAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'vat_creditable_account_id');
    }

    public function vatPayableAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'vat_payable_account_id');
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'cash_account_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'bank_account_id');
    }

    public function inventoryAdjustmentAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'inventory_adjustment_account_id');
    }

    public function defaultJournal(): BelongsTo
    {
        return $this->belongsTo(AccountingJournal::class, 'default_journal_id');
    }

    public function purchasesJournal(): BelongsTo
    {
        return $this->belongsTo(AccountingJournal::class, 'purchases_journal_id');
    }

    public function salesJournal(): BelongsTo
    {
        return $this->belongsTo(AccountingJournal::class, 'sales_journal_id');
    }

    public function posJournal(): BelongsTo
    {
        return $this->belongsTo(AccountingJournal::class, 'pos_journal_id');
    }
}
