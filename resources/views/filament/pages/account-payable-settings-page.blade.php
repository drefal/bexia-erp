<x-filament-panels::page>
    <form wire:submit.prevent="save" class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Valores default para Cuentas por pagar
            </x-slot>

            <x-slot name="description">
                Esta configuración aplica por empresa y solo controla defaults operativos del módulo CxP.
                La configuración contable general sigue en Contabilidad.
            </x-slot>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        Cuenta / Caja default para pagos
                    </label>

                    <select
                        wire:model="defaultTreasuryAccountId"
                        class="mt-1 w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                    >
                        <option value="">Sin default</option>
                        @foreach ($this->treasuryAccounts as $account)
                            <option value="{{ $account->id }}">
                                {{ $account->name }} | {{ $account->type }} | Saldo: ${{ number_format((float) $account->current_balance, 2) }} {{ $account->currency_code }}
                            </option>
                        @endforeach
                    </select>

                    @error('defaultTreasuryAccountId')
                        <div class="mt-1 text-sm text-danger-600">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        Forma de pago default
                    </label>

                    <select
                        wire:model="defaultPaymentFormId"
                        class="mt-1 w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                    >
                        <option value="">Sin default</option>
                        @foreach ($this->paymentForms as $form)
                            @php($code = $form->code ?: $form->sat_payment_form_code)
                            <option value="{{ $form->id }}">
                                {{ trim(($code ? $code . ' - ' : '') . $form->name) }}
                            </option>
                        @endforeach
                    </select>

                    @error('defaultPaymentFormId')
                        <div class="mt-1 text-sm text-danger-600">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        Días de vencimiento default
                    </label>

                    <input
                        type="number"
                        min="0"
                        max="365"
                        wire:model="defaultDueDays"
                        class="mt-1 w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                    >

                    @error('defaultDueDays')
                        <div class="mt-1 text-sm text-danger-600">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        Tolerancia de redondeo en pagos
                    </label>

                    <input
                        type="number"
                        min="0"
                        step="0.0001"
                        wire:model="roundingTolerance"
                        class="mt-1 w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                    >

                    @error('roundingTolerance')
                        <div class="mt-1 text-sm text-danger-600">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Comportamiento
            </x-slot>

            <div class="space-y-4">
                <label class="flex items-start gap-3">
                    <input
                        type="checkbox"
                        wire:model="allowOverpayment"
                        class="mt-1 rounded border-gray-300"
                    >

                    <span>
                        <span class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                            Permitir sobrepago
                        </span>
                        <span class="block text-sm text-gray-500">
                            Si está apagado, no se permite registrar un pago mayor al saldo pendiente.
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-3">
                    <input
                        type="checkbox"
                        wire:model="showLogoOnPdf"
                        class="mt-1 rounded border-gray-300"
                    >

                    <span>
                        <span class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                            Mostrar logo de empresa en PDF
                        </span>
                        <span class="block text-sm text-gray-500">
                            Aplica a documentos y reportes PDF de CxP.
                        </span>
                    </span>
                </label>
            </div>
        </x-filament::section>

        <div>
            <button
                type="submit"
                style="display:inline-flex;align-items:center;justify-content:center;border-radius:0.5rem;background:#2563eb;color:#ffffff;padding:0.5rem 1rem;font-size:0.875rem;font-weight:600;text-decoration:none;box-shadow:0 1px 2px rgba(0,0,0,0.08);"
            >
                Guardar configuración
            </button>
        </div>
    </form>
</x-filament-panels::page>
