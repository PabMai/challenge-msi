<?php

/*
|--------------------------------------------------------------------------
| Configuración de reservas
|--------------------------------------------------------------------------
|
| Parámetros de negocio del sistema de reservas del restaurante.
| Las ventanas horarias se expresan en minutos desde la medianoche
| del día de servicio para simplificar los cálculos del validador.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Duración de la reserva
    |--------------------------------------------------------------------------
    |
    | Duración fija de cada reserva en minutos. Toda reserva ocupa su mesa
    | durante este tiempo completo (starts_at + duración = ends_at).
    |
    */

    'duration_minutes' => 120,

    /*
    |--------------------------------------------------------------------------
    | Corte de recepción (cutoff)
    |--------------------------------------------------------------------------
    |
    | Una reserva debe solicitarse con esta antelación mínima respecto al
    | inicio del turno solicitado. Ej.: con 15, un turno de 20:00 deja de
    | aceptarse a las 19:45.
    |
    */

    'cutoff_minutes' => 15,

    /*
    |--------------------------------------------------------------------------
    | Máximo de mesas por reserva
    |--------------------------------------------------------------------------
    |
    | Límite de mesas que pueden combinarse para una misma reserva.
    | Las mesas deben pertenecer a la misma ubicación.
    |
    */

    'max_tables_per_reservation' => 3,

    /*
    |--------------------------------------------------------------------------
    | Horario de atención por día
    |--------------------------------------------------------------------------
    |
    | Claves: día de la semana ISO-8601 (1=Lunes ... 7=Domingo).
    | Valor: lista de ventanas [start, end] en minutos desde la medianoche,
    | lo que permite varios turnos por día si el negocio lo requiere.
    |
    | Un `end` mayor a 1440 indica cruce de medianoche normalizado al día de
    | servicio: el sábado cierra a las 02:00 del domingo (1440+120 = 1560).
    | La madrugada dominical (00:00-02:00) pertenece, por tanto, al servicio
    | del sábado.
    |
    | Horario definido: L-V 10:00-24:00 · Sábado 22:00-02:00 · Domingo 12:00-16:00
    |
    */

    'business_hours' => [
        1 => [
            ['start' => 600, 'end' => 1440], // 10:00 - 24:00
        ],
        2 => [
            ['start' => 600, 'end' => 1440], // 10:00 - 24:00
        ],
        3 => [
            ['start' => 600, 'end' => 1440], // 10:00 - 24:00
        ],
        4 => [
            ['start' => 600, 'end' => 1440], // 10:00 - 24:00
        ],
        5 => [
            ['start' => 600, 'end' => 1440], // 10:00 - 24:00
        ],
        6 => [
            ['start' => 1320, 'end' => 1560], // 22:00 - 02:00 (+1 día)
        ],
        7 => [
            ['start' => 720, 'end' => 960], // 12:00 - 16:00
        ],
    ],
];
