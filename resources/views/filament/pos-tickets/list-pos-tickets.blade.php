{{-- BEXIA_POS_MOV_UI_FIX5 / BEXIA_POS_MOV_FIX7D --}}
<x-filament-panels::page>

    <style>
        .bexia-pdv-filters {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            width: 100%;
            max-width: 850px;
            margin: 0 auto;
            align-items: end;
        }

        .bexia-pdv-filter-field {
            min-width: 0;
        }

        .bexia-pdv-filter-label {
            display: block;
            margin-bottom: 5px;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.4;
            color: #64748b;
        }

        .bexia-pdv-filter-input {
            display: block;
            box-sizing: border-box;
            width: 100%;
            min-width: 0;
            height: 36px;
            padding: 5px 10px;
            font-size: 12px;
            line-height: 20px;
            color: #334155;
            background: #fff;
            border: 1px solid #dbe3ee;
            border-radius: 9px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .035);
        }

        .bexia-pdv-filter-input:focus {
            border-color: #3b82f6;
            outline: 2px solid rgba(59, 130, 246, .12);
            outline-offset: 0;
        }

        .dark .bexia-pdv-filter-input {
            background: #111827;
            color: #f9fafb;
            border-color: #374151;
            color-scheme: dark;
        }

        .dark .bexia-pdv-filter-label {
            color: #cbd5e1;
        }

        @media (max-width: 900px) {
            .bexia-pdv-filters {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                max-width: 540px;
            }
        }

        @media (max-width: 480px) {
            .bexia-pdv-filters {
                grid-template-columns: minmax(0, 1fr);
                max-width: 100%;
            }
        }

    </style>

    <div class="bexia-pdv-filters">

        <div class="bexia-pdv-filter-field">
            <label for="bexia-method"
                   class="bexia-pdv-filter-label">
                Método de pago
            </label>

            <select
                id="bexia-method"
                wire:model.live="tableFilters.movement_payment.value"
                class="bexia-pdv-filter-input"
            >
                <option value="">Todos</option>
                <option value="cash">Efectivo</option>
                <option value="debit">Tarjeta de débito</option>
                <option value="credit">Tarjeta de crédito</option>
                <option value="transfer">Transferencia</option>
                <option value="clip">Tarjeta / CLIP</option>
                <option value="other">Otros históricos</option>
            </select>
        </div>

        <div class="bexia-pdv-filter-field">
            <label for="bexia-cashier"
                   class="bexia-pdv-filter-label">
                Cajero
            </label>

            <select
                id="bexia-cashier"
                wire:model.live="tableFilters.movement_cashier.value"
                class="bexia-pdv-filter-input"
            >
                <option value="">Todos</option>

                @foreach(
                    \App\Filament\Resources\PosTicketResource::
                        bexiaCashierFilterOptions() as $id => $name
                )
                    <option value="{{ $id }}">
                        {{ $name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="bexia-pdv-filter-field">
            <label for="bexia-from"
                   class="bexia-pdv-filter-label">
                Desde
            </label>
            <input
                id="bexia-from"
                type="datetime-local"
                lang="en-GB"
                step="60"
                wire:model.live="tableFilters.movement_datetime_v8.from"
                class="bexia-pdv-filter-input"
            />
        </div>

        <div class="bexia-pdv-filter-field">
            <label for="bexia-until"
                   class="bexia-pdv-filter-label">
                Hasta
            </label>
            <input
                id="bexia-until"
                type="datetime-local"
                lang="en-GB"
                step="60"
                wire:model.live="tableFilters.movement_datetime_v8.until"
                class="bexia-pdv-filter-input"
            />
        </div>

    </div>

        {{ $this->table }}

</x-filament-panels::page>
