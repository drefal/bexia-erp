<x-filament-panels::page>
    <style>
        .bexia-xml-card {
            border: 1px solid rgb(209 213 219);
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, .05);
            overflow: hidden;
        }

        .bexia-xml-card__header {
            padding: 18px 22px;
            border-bottom: 1px solid rgb(229 231 235);
        }

        .bexia-xml-card__body {
            padding: 22px;
        }

        .bexia-xml-label {
            display: block;
            margin-bottom: 7px;
            font-size: 13px;
            font-weight: 700;
            color: rgb(15 23 42);
        }

        .bexia-xml-input,
        .bexia-xml-select {
            width: 100%;
            min-height: 42px;
            border: 1px solid rgb(203 213 225);
            border-radius: 12px;
            background: white;
            padding: 9px 12px;
            font-size: 14px;
            color: rgb(15 23 42);
            outline: none;
        }

        .bexia-xml-input:focus,
        .bexia-xml-select:focus {
            border-color: rgb(37 99 235);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }

        .bexia-file-picker {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 42px;
            border: 1px solid rgb(203 213 225);
            border-radius: 12px;
            background: white;
            padding: 6px;
        }

        .bexia-file-picker__button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 30px;
            border-radius: 9px;
            background: rgb(37 99 235);
            color: white;
            padding: 0 12px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
        }

        .bexia-file-picker__name {
            color: rgb(71 85 105);
            font-size: 13px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .bexia-xml-footer {
            display: flex;
            justify-content: flex-end;
            padding-top: 18px;
            margin-top: 18px;
            border-top: 1px solid rgb(229 231 235);
        }

        .bexia-primary-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            border: 0;
            border-radius: 12px;
            background: rgb(37 99 235);
            color: white;
            padding: 0 18px;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 10px 18px rgba(37, 99, 235, .25);
            cursor: pointer;
        }

        .bexia-primary-button:hover {
            background: rgb(29 78 216);
        }

        .bexia-help {
            margin-top: 6px;
            color: rgb(100 116 139);
            font-size: 12px;
        }
    </style>

    <div class="space-y-6">
        @if(session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-medium text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800">
                {{ session('error') }}
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('purchases.orders.import-xml') }}"
            enctype="multipart/form-data"
            class="bexia-xml-card"
        >
            @csrf

            <input type="hidden" name="company_id" value="{{ request()->route('tenant') }}">

            <div class="bexia-xml-card__header">
                <div class="text-base font-semibold text-gray-950">
                    Subir XML CFDI de proveedor
                </div>

                <div class="mt-1 text-sm text-gray-500">
                    Bexia creará una OC en borrador. Si alguna línea no coincide con un producto interno, quedará pendiente de mapear y no podrá confirmarse hasta corregirla.
                </div>
            </div>

            <div class="bexia-xml-card__body">
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="bexia-xml-label">XML CFDI</label>

                        <div class="bexia-file-picker">
                            <label for="xml_file" class="bexia-file-picker__button">
                                Seleccionar XML
                            </label>

                            <span id="xml_file_name" class="bexia-file-picker__name">
                                Ningún archivo seleccionado
                            </span>

                            <input
                                id="xml_file"
                                type="file"
                                name="xml_file"
                                accept=".xml,text/xml,application/xml"
                                required
                                class="hidden"
                            >
                        </div>

                        <p class="bexia-help">Máximo 10 MB.</p>
                    </div>

                    <div>
                        <label class="bexia-xml-label">Almacén destino</label>

                        @if(count($warehouses))
                            <select id="warehouse_id" name="warehouse_id" required class="bexia-xml-select">
                                <option value="">Selecciona almacén</option>

                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse['id'] }}">
                                        {{ $warehouse['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" disabled value="No hay almacenes configurados" class="bexia-xml-input">
                        @endif
                    </div>

                    <div>
                        <label class="bexia-xml-label">Ubicación / recepción</label>

                        @if(count($receivingLocations))
                            <select id="location_id" name="location_id" class="bexia-xml-select" disabled>
                                <option value="">Selecciona primero un almacén</option>

                                @foreach($receivingLocations as $location)
                                    <option
                                        value="{{ $location['id'] }}"
                                        data-warehouse-id="{{ $location['warehouse_id'] }}"
                                    >
                                        {{ $location['label'] }}
                                    </option>
                                @endforeach
                            </select>

                            <p id="location_help" class="bexia-help">
                                Se mostrarán las ubicaciones reales del almacén seleccionado.
                            </p>
                        @else
                            <input type="text" disabled value="Sin ubicaciones de recepción configuradas" class="bexia-xml-input">
                        @endif
                    </div>
                </div>

                <div class="bexia-xml-footer">
                    <button type="submit" class="bexia-primary-button">
                        Crear OC en borrador
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const fileInput = document.getElementById('xml_file');
            const fileName = document.getElementById('xml_file_name');
            const warehouseSelect = document.getElementById('warehouse_id');
            const locationSelect = document.getElementById('location_id');
            const locationHelp = document.getElementById('location_help');

            if (fileInput && fileName) {
                fileInput.addEventListener('change', function () {
                    fileName.textContent = fileInput.files && fileInput.files.length
                        ? fileInput.files[0].name
                        : 'Ningún archivo seleccionado';
                });
            }

            if (warehouseSelect && locationSelect) {
                const originalOptions = Array.from(locationSelect.querySelectorAll('option[data-warehouse-id]'));

                function refreshLocations() {
                    const warehouseId = warehouseSelect.value;

                    locationSelect.innerHTML = '';

                    const placeholder = document.createElement('option');
                    placeholder.value = '';

                    if (!warehouseId) {
                        placeholder.textContent = 'Selecciona primero un almacén';
                        locationSelect.appendChild(placeholder);
                        locationSelect.disabled = true;

                        if (locationHelp) {
                            locationHelp.textContent = 'Se mostrarán las ubicaciones reales del almacén seleccionado.';
                        }

                        return;
                    }

                    const filtered = originalOptions.filter(function (option) {
                        return option.dataset.warehouseId === warehouseId;
                    });

                    if (!filtered.length) {
                        placeholder.textContent = 'No hay ubicación configurada para este almacén';
                        locationSelect.appendChild(placeholder);
                        locationSelect.disabled = true;

                        if (locationHelp) {
                            locationHelp.textContent = 'Revisa que el almacén tenga una ubicación real como CDF - CEDIS Florales.';
                        }

                        return;
                    }

                    placeholder.textContent = 'Selecciona ubicación / recepción';
                    locationSelect.appendChild(placeholder);

                    filtered.forEach(function (option) {
                        locationSelect.appendChild(option.cloneNode(true));
                    });

                    locationSelect.disabled = false;

                    if (filtered.length === 1) {
                        locationSelect.value = filtered[0].value;
                    }

                    if (locationHelp) {
                        locationHelp.textContent = filtered.length + ' ubicación(es) disponibles para este almacén.';
                    }
                }

                warehouseSelect.addEventListener('change', refreshLocations);
                refreshLocations();
            }
        });
    </script>
</x-filament-panels::page>
