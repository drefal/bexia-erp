<?php

namespace App\Support\Billing;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PaymentForm;
use App\Models\TreasuryAccount;
use App\Models\TreasuryMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoicePaymentTreasuryService
{
    public function openBalance(Invoice $invoice): float
    {
        $invoice = $invoice->refresh();

        $total = (float) ($invoice->total ?? 0);

        $paid = (float) InvoicePayment::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', 'paid')
            ->sum('amount');

        return round(max($total - $paid, 0), 4);
    }

    public function canRegisterPayment(Invoice $invoice): bool
    {
        $status = (string) ($invoice->status ?? '');
        $cfdiStatus = (string) ($invoice->cfdi_status ?? '');

        if (in_array($status, ['cancelled', 'canceled'], true)) {
            return false;
        }

        if (in_array($cfdiStatus, ['cancelled', 'canceled', 'cancelled_internal'], true)) {
            return false;
        }

        return $this->openBalance($invoice) > 0.0001;
    }

    public function createDraftPayment(Invoice $invoice, array $data, ?int $userId = null): InvoicePayment
    {
        return DB::transaction(function () use ($invoice, $data, $userId): InvoicePayment {
            $lockedInvoice = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->canRegisterPayment($lockedInvoice)) {
                throw new RuntimeException('La factura no permite registrar pagos o ya no tiene saldo pendiente.');
            }

            $amount = round((float) ($data['amount'] ?? 0), 4);

            if ($amount <= 0) {
                throw new RuntimeException('El importe debe ser mayor a cero.');
            }

            $openBalance = $this->openBalance($lockedInvoice);

            if ($amount > $openBalance + 0.0001) {
                throw new RuntimeException('El importe no puede ser mayor al saldo pendiente.');
            }

            $companyId = (int) $lockedInvoice->company_id;
            $treasuryAccountId = (int) ($data['treasury_account_id'] ?? 0);
            $paymentFormId = ! empty($data['payment_form_id']) ? (int) $data['payment_form_id'] : null;

            $account = TreasuryAccount::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->find($treasuryAccountId);

            if (! $account) {
                throw new RuntimeException('Selecciona una cuenta/caja válida de tesorería.');
            }

            $paymentForm = $paymentFormId
                ? PaymentForm::query()->where('company_id', $companyId)->find($paymentFormId)
                : null;

            $paymentLabel = $paymentForm
                ? trim(($paymentForm->code ? "{$paymentForm->code} - " : '').$paymentForm->name)
                : 'Pago';

            $reference = trim((string) ($data['reference'] ?? ''));
            $notes = trim((string) ($data['notes'] ?? ''));
            $paymentDate = (string) ($data['payment_date'] ?? now()->toDateString());

            $payment = InvoicePayment::query()->create([
                'invoice_id' => $lockedInvoice->id,
                'company_id' => $companyId,
                'source_type' => 'treasury',
                'source_payment_id' => null,
                'payment_form_id' => $paymentForm?->id,
                'payment_label' => $paymentLabel,
                'payment_form_code' => $paymentForm?->code,
                'reference' => $reference !== '' ? $reference : null,
                'notes' => $notes !== '' ? $notes : null,
                'amount' => $amount,
                'status' => 'pending',
                'paid_at' => null,
                'cancelled_at' => null,
                'metadata' => [
                    'created_from' => 'invoice_payments_relation',
                    'requested_payment_date' => $paymentDate,
                    'created_by_user_id' => $userId,
                ],
            ]);

            $movement = TreasuryMovement::query()->create([
                'company_id' => $companyId,
                'treasury_account_id' => $account->id,
                'payment_form_id' => $paymentForm?->id,
                'type' => 'inbound',
                'source_type' => 'invoice_payment',
                'source_id' => $payment->id,
                'movement_date' => $paymentDate,
                'amount' => $amount,
                'currency_code' => $lockedInvoice->currency_code ?: 'MXN',
                'reference' => $reference !== '' ? $reference : null,
                'description' => 'Cobro de factura '.$this->invoiceDisplayNumber($lockedInvoice).($notes !== '' ? "\n".$notes : ''),
                'status' => 'draft',
                'created_by_user_id' => $userId,
                'metadata' => [
                    'invoice_id' => $lockedInvoice->id,
                    'invoice_number' => $this->invoiceDisplayNumber($lockedInvoice),
                    'payment_id' => $payment->id,
                ],
            ]);

            $payment->forceFill([
                'treasury_movement_id' => $movement->id,
            ])->save();

            $this->recalculateInvoiceTotals($lockedInvoice);

            return $payment->refresh();
        });
    }

    public function markPaymentPosted(int $paymentId, TreasuryMovement $movement): void
    {
        $payment = InvoicePayment::query()->find($paymentId);

        if (! $payment) {
            return;
        }

        $payment->forceFill([
            'status' => 'paid',
            'paid_at' => $movement->posted_at ?: now(),
            'cancelled_at' => null,
            'treasury_movement_id' => $movement->id,
        ])->save();

        if ($payment->invoice) {
            $this->recalculateInvoiceTotals($payment->invoice);
        }
    }

    public function markPaymentCancelled(int $paymentId, TreasuryMovement $movement): void
    {
        $payment = InvoicePayment::query()->find($paymentId);

        if (! $payment) {
            return;
        }

        $payment->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => $movement->cancelled_at ?: now(),
            'treasury_movement_id' => $movement->id,
        ])->save();

        if ($payment->invoice) {
            $this->recalculateInvoiceTotals($payment->invoice);
        }
    }

    public function recalculateInvoiceTotals(Invoice $invoice): Invoice
    {
        $paid = round((float) InvoicePayment::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', 'paid')
            ->sum('amount'), 4);

        $total = round((float) ($invoice->total ?? 0), 4);
        $balance = round(max($total - $paid, 0), 4);

        $invoice->forceFill([
            'paid_total' => $paid,
            'balance_total' => $balance,
        ])->save();

        return $invoice->refresh();
    }

    private function invoiceDisplayNumber(Invoice $invoice): string
    {
        return (string) (
            $invoice->cfdi_number_display
            ?: $invoice->number
            ?: $invoice->id
        );
    }
}
