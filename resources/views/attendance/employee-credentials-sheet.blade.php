<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">

    <style>
        @page {
            size: letter portrait;
            margin: 8mm 15mm;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #17212b;
            background: #ffffff;
        }

        .print-page {
            width: 100%;
        }

        .force-page-break {
            page-break-after: always;
        }

        .sheet-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: avoid;
        }

        .sheet-row {
            page-break-inside: avoid;
        }

        .sheet-cell {
            width: 33.333333%;
            height: 86mm;
            padding: 2mm 4.5mm;
            vertical-align: middle;
            text-align: center;
            page-break-inside: avoid;
        }

        .credential-card {
            position: relative;
            width: 52mm;
            height: 82mm;
            margin: 0 auto;
            overflow: hidden;
            background: #ffffff;
            border: 0.42mm solid #16476a;
            text-align: left;
            page-break-inside: avoid;
        }

        .credential-header {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 10.8mm;
            overflow: hidden;
            text-align: center;
            background: #edf5fa;
            border-top: 1.1mm solid #16476a;
            border-bottom: 0.48mm solid #5ba8c7;
        }

        .company-logo {
            display: block;
            max-width: 39mm;
            max-height: 7.1mm;
            margin: 1.25mm auto 0;
        }

        .company-name {
            margin: 2.3mm 2.8mm 0;
            max-height: 6.5mm;
            overflow: hidden;
            font-size: 8.2pt;
            line-height: 3.2mm;
            font-weight: 800;
            color: #16476a;
            text-align: center;
            text-transform: uppercase;
        }

        .credential-body {
            position: absolute;
            left: 0;
            top: 10.8mm;
            width: 100%;
            height: 40.2mm;
            overflow: hidden;
            text-align: center;
            background: #ffffff;
        }

        .photo-frame {
            position: absolute;
            left: 17.25mm;
            top: 1.5mm;
            width: 17.5mm;
            height: 17.5mm;
            overflow: hidden;
            border: 0.35mm solid #5ba8c7;
            background: #f3f7fa;
        }

        .photo-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .employee-info {
            position: absolute;
            left: 2.4mm;
            right: 2.4mm;
            top: 20mm;
            text-align: center;
        }

        .employee-name {
            max-height: 9.45mm;
            overflow: hidden;
            font-size: 8.1pt;
            line-height: 3.15mm;
            font-weight: 800;
            color: #123d5a;
            text-transform: uppercase;
            white-space: normal;
        }

        .employee-name--sm {
            font-size: 7.2pt;
            line-height: 2.95mm;
        }

        .employee-name--xs {
            font-size: 6.4pt;
            line-height: 2.7mm;
        }

        .employee-number {
            margin-top: 0.25mm;
            font-size: 5.6pt;
            line-height: 2.35mm;
            font-weight: 700;
            color: #1c608b;
        }

        .employee-position {
            margin-top: 0.1mm;
            max-height: 4.5mm;
            overflow: hidden;
            font-size: 5.2pt;
            line-height: 2.25mm;
            color: #17212b;
        }

        .employee-branch {
            margin-top: 0.1mm;
            max-height: 2.3mm;
            overflow: hidden;
            font-size: 5.1pt;
            line-height: 2.25mm;
            font-weight: 700;
            color: #527084;
        }

        .credential-qr-area {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 31mm;
            overflow: hidden;
            border-top: 0.4mm solid #5ba8c7;
            background: #eef5fa;
            text-align: center;
        }

        .qr-box {
            position: absolute;
            left: 13.3mm;
            top: 2.8mm;
            width: 25.4mm;
            height: 25.4mm;
            padding: 0.2mm;
            overflow: hidden;
            border: 0.2mm solid #bfd4e1;
            background: #ffffff;
        }

        .qr-box img {
            width: 25mm;
            height: 25mm;
        }
    </style>
</head>

<body>

@foreach(array_chunk($cards, 9) as $pageCards)

    <div
        class="print-page{{ ! $loop->last ? ' force-page-break' : '' }}"
    >

        <table class="sheet-table">

            @foreach(array_chunk($pageCards, 3) as $rowCards)

                <tr class="sheet-row">

                    @foreach($rowCards as $card)

                        <td class="sheet-cell">

                            @include(
                                'attendance.employee-credential-card',
                                ['card' => $card]
                            )

                        </td>

                    @endforeach

                    @for(
                        $missing = count($rowCards);
                        $missing < 3;
                        $missing++
                    )

                        <td class="sheet-cell"></td>

                    @endfor

                </tr>

            @endforeach

        </table>

    </div>

@endforeach

</body>
</html>
