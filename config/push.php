<?php

declare(strict_types=1);

/*
 * Las notificaciones push. Solo el `worker` las manda, así que la cuenta de
 * servicio va solo ahí (NP21).
 */
return [
    'driver' => (string) env('PUSH_DRIVER', 'log'),   // log | firebase
    'firebase' => [
        'proyecto' => (string) env('FIREBASE_PROYECTO', ''),
        // El JSON completo de la cuenta de servicio, como secreto.
        'cuenta_de_servicio' => (string) env('FIREBASE_CUENTA_DE_SERVICIO', ''),
    ],
];
