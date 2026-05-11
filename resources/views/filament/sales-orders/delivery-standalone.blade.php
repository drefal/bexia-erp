<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Entrega de orden {{ $saleOrder->number ?? ('#' . $saleOrder->id) }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            margin: 0;
            background: #f8fafc;
            color: #111827;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .page {
            max-width: 1180px;
            margin: 0 auto;
            padding: 24px;
        }

        .header {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 18px;
            margin-bottom: 18px;
            display: flex;
            gap: 16px;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .title {
            font-size: 22px;
            font-weight: 800;
            margin: 0;
        }

        .subtitle {
            margin-top: 4px;
            color: #6b7280;
            font-size: 14px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            border: 1px solid #d1d5db;
            background: white;
            color: #111827;
            padding: 10px 14px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }

        .content {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 18px;
        }

        table {
            border-collapse: collapse;
        }

        input, textarea, select {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 7px;
        }

        button {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div>
                <h1 class="title">Entrega de orden de venta</h1>
                <div class="subtitle">
                    {{ $saleOrder->number ?? ('Orden #' . $saleOrder->id) }}
                    · Cliente: {{ $saleOrder->customer_name ?: 'Sin cliente' }}
                    · Estado: {{ $saleOrder->status }}
                </div>
            </div>

            <a href="javascript:history.back()" class="btn">Volver</a>
        </div>

        <div class="content">
            @include('filament.sales-orders.delivery-field', [
                'saleOrderId' => $saleOrder->id,
            ])
        </div>
    </div>
</body>
</html>
