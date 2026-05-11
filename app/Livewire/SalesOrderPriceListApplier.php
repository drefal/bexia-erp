<?php

namespace App\Livewire;

use App\Support\SalesPriceListUpdater;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class SalesOrderPriceListApplier extends Component
{
    public int $saleOrderId;
    public ?int $selectedPriceListId = null;
    public ?string $message = null;
    public ?string $errorMessage = null;

    public function mount(int $saleOrderId): void
    {
        $this->saleOrderId = $saleOrderId;
        $order = $this->order();

        $this->selectedPriceListId = $order ? (int) ($order->price_list_id ?? 0) : null;
    }

    public function applyPriceList(): void
    {
        $this->message = null;
        $this->errorMessage = null;

        try {
            $result = SalesPriceListUpdater::updateFromSelectedPriceList(
                $this->saleOrderId,
                (int) $this->selectedPriceListId
            );

            $this->message = ($result['message'] ?? 'Lista aplicada.') . ' Actualizando pantalla...';

            $order = $this->order();
            $this->selectedPriceListId = $order ? (int) ($order->price_list_id ?? 0) : $this->selectedPriceListId;

            $this->dispatch('sales-order-prices-applied', saleOrderId: $this->saleOrderId);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function getOptionsProperty(): array
    {
        return SalesPriceListUpdater::priceListOptions();
    }

    public function getCanApplyProperty(): bool
    {
        $order = $this->order();

        if (! $order || ! $this->selectedPriceListId) {
            return false;
        }

        $permission = SalesPriceListUpdater::canUpdatePrices($order);

        if (! ($permission['ok'] ?? false)) {
            return false;
        }

        $applied = (int) ($order->price_list_applied_id ?? $order->price_list_id ?? 0);

        return (int) $this->selectedPriceListId !== $applied;
    }

    public function getPermissionMessageProperty(): string
    {
        $permission = SalesPriceListUpdater::canUpdatePrices($this->order());

        return (string) ($permission['message'] ?? '');
    }

    public function getAppliedListNameProperty(): string
    {
        $order = $this->order();
        $id = (int) ($order->price_list_applied_id ?? $order->price_list_id ?? 0);

        return $this->options[$id]
            ?? \App\Support\SalesPriceListUpdater::priceListName($id)
            ?? 'Sin lista aplicada';
    }

    protected function order(): ?object
    {
        if (! Schema::hasTable('sales_orders')) {
            return null;
        }

        return DB::table('sales_orders')->where('id', $this->saleOrderId)->first();
    }

    public function render()
    {
        return view('livewire.sales-order-price-list-applier');
    }
}
