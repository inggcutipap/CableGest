<?php

return [
    'cobranza' => [
        // 'fin_de_mes' calcula el ultimo dia calendario del periodo (regla heredada de VB6);
        // o un numero entero del 1 al 28 para fijar un dia especifico de vencimiento.
        'dia_vencimiento' => env('CABLEGEST_DIA_VENCIMIENTO', 'fin_de_mes'),

        // Dia del mes en que el programador genera automaticamente las mensualidades.
        'generar_dia_mes' => (int) env('CABLEGEST_GENERAR_DIA', 1),

        // Estados de contrato incluidos en la facturacion mensual.
        'estados_contrato_generables' => ['activo'],
    ],
];
