<?php

// ACCESS9: opt-in explicito por empresa:PDV.
// Ningun PDV se habilita sin configuracion explicita.
// Otros puntos de venta NO se activan automaticamente.
return [
    'enabled_pos' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('BEXIA_ACCESS9_ENABLED_POS', ''))
    ))),
];
