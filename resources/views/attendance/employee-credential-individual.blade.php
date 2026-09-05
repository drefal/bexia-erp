<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">

    <style>
        @page {
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: 52mm;
            height: 82mm;
            overflow: hidden;
        }

        body {
            position: relative;
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            background: #ffffff;
        }

        .credential-card {
            position: absolute;
            left: 0;
            top: 0;
            width: 52mm;
            height: 82mm;
            overflow: hidden;
            background: #ffffff;
            border: 0.32mm solid #111827;
        }

        .credential-header {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 9.2mm;
            border-bottom: 0.24mm solid #d1d5db;
            text-align: center;
        }

        .brand {
            margin-top: 1.1mm;
            font-size: 13.5pt;
            line-height: 4.6mm;
            font-weight: 800;
            letter-spacing: 0.8mm;
        }

        .company {
            margin: 0.25mm 2.5mm 0;
            font-size: 5.2pt;
            line-height: 2.2mm;
            font-weight: 700;
            color: #4b5563;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
        }

        .credential-body {
            position: absolute;
            left: 0;
            top: 9.2mm;
            width: 100%;
            height: 42.3mm;
            text-align: center;
        }

        .photo-frame {
            position: absolute;
            left: 16.5mm;
            top: 2.2mm;
            width: 19mm;
            height: 19mm;
            overflow: hidden;
            border: 0.24mm solid #d1d5db;
            background: #f3f4f6;
        }

        .photo-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .employee-info {
            position: absolute;
            left: 2.8mm;
            right: 2.8mm;
            top: 22.5mm;
            text-align: center;
        }

        .employee-name {
            font-size: 8.8pt;
            line-height: 3.8mm;
            font-weight: 800;
            text-transform: uppercase;
            max-height: 7.6mm;
            overflow: hidden;
        }

        .employee-number {
            margin-top: 0.45mm;
            font-size: 5.7pt;
            line-height: 2.6mm;
            font-weight: 700;
            color: #4b5563;
        }

        .employee-position {
            margin-top: 0.25mm;
            font-size: 5.5pt;
            line-height: 2.5mm;
            color: #111827;
            max-height: 5mm;
            overflow: hidden;
        }

        .employee-branch {
            margin-top: 0.25mm;
            font-size: 5.4pt;
            line-height: 2.45mm;
            font-weight: 700;
            color: #4b5563;
            max-height: 4.9mm;
            overflow: hidden;
        }

        .credential-qr-area {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 30.5mm;
            border-top: 0.24mm solid #d1d5db;
            background: #fafafa;
            text-align: center;
        }

        .qr-box {
            position: absolute;
            left: 13.5mm;
            top: 3.7mm;
            width: 25mm;
            height: 25mm;
            background: #ffffff;
        }

        .qr-box img {
            width: 25mm;
            height: 25mm;
        }

        .qr-copy {
            position: absolute;
            left: 2mm;
            right: 2mm;
            top: 0.8mm;
            text-align: center;
        }

        .qr-copy .qr-title {
            font-size: 5.6pt;
            line-height: 2.4mm;
            font-weight: 800;
            color: #374151;
        }

        .qr-copy .qr-instruction,
        .qr-copy .qr-security {
            display: none;
        }
    </style>
</head>

<body>

@include(
    'attendance.employee-credential-card',
    ['card' => $card]
)

</body>
</html>
