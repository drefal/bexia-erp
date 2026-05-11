@if(($human($proyectoValor) !== '') || ($human($observacionesValor) !== ''))
<table class="top-general-wrap">
    <tr>
        <td>
            <div class="box-table">
                <div class="section-title">Información general</div>

                <table class="kv single">
                    <tbody>
                        <tr>
                            <th>Proyecto</th>
                            <td>{{ $human($proyectoValor) }}</td>
                        </tr>
                        <tr>
                            <th>Observaciones</th>
                            <td>{{ $human($observacionesValor) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </td>
    </tr>
</table>
@endif
