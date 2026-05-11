<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Facturación Bexia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            margin: 0;
            padding: 32px;
        }

        .card {
            max-width: 620px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            padding: 28px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, .10);
        }

        h1 {
            margin-top: 0;
            font-size: 28px;
        }

        .ticket {
            border: 1px dashed #94a3b8;
            border-radius: 14px;
            padding: 14px;
            font-size: 18px;
            font-weight: 900;
            background: #f8fafc;
        }

        .muted {
            color: #64748b;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Facturación Bexia</h1>

        <p class="muted">
            El módulo de facturación estará disponible próximamente.
            Conserva tu ticket para solicitar la factura cuando el módulo CFDI esté activo.
        </p>

        <p>Ticket:</p>
        <div class="ticket">{{ $ticket !== '' ? $ticket : 'Sin ticket' }}</div>
    </div>
</body>
</html>
