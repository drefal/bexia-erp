<table class="info-wrap">
    <tr>
        <td class="pad-r">
            <div class="box-table">
                <div class="section-title">
                    Datos de envío
                </div>

                <table class="kv">
                    <tbody>
                        <tr>
                            <th>Fecha de salida</th>
                            <td class="center">{{ $human($fechaSalidaValor) }}</td>
                        </tr>

                        <tr>
                            <th>Nombre de quien envía</th>
                            <td class="center">{{ $human($enviaNombreValor) }}</td>
                        </tr>

                        <tr>
                            <th>Almacén de envío</th>
                            <td class="center">{{ $human($almacenEnvioValor) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </td>

        <td class="pad-l">
            <div class="box-table">
                <div class="section-title">
                    Datos de recepción
                </div>

                <table class="kv">
                    <tbody>
                        <tr>
                            <th>Almacén de recepción</th>
                            <td class="center">{{ $human($almacenRecepcionValor) }}</td>
                        </tr>

                        <tr>
                            <th>Nombre de quien recibe</th>
                            <td class="center">{{ $human($recibeNombreValor) }}</td>
                        </tr>

                        <tr>
                            <th>Folio</th>
                            <td class="center">{{ $folioDisplay ?? $submission->folio ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </td>
    </tr>
</table>
