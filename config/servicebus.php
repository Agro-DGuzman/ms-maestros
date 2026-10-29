<?php

declare(strict_types=1);

/*
 * El bus de eventos de dominio (EM14). Los mismos nombres de variables que en
 * ms-comercial, para no confundirse al cargarlos en los dos Container Apps.
 * Las claves van como secretRef y nunca pasan por el chat.
 */
return [
    'namespace' => (string) env('SERVICEBUS_NAMESPACE', ''),

    // Lo que ms-maestros escucha: las propiedades de ms-comercial.
    'topic' => (string) env('SERVICEBUS_TOPIC', 'comercial-eventos'),
    'suscripcion' => (string) env('SERVICEBUS_SUSCRIPCION', 'sub-ms-maestros'),
    // Solo Listen en comercial-eventos: con ella no se publica nada.
    'escucha' => [
        'nombre' => (string) env('SERVICEBUS_SAS_NOMBRE', ''),
        'clave' => (string) env('SERVICEBUS_SAS_CLAVE', ''),
    ],

    // Solo Send en maestros-eventos: el consumidor publica el outbox.
    'publicacion' => [
        'topic' => (string) env('SERVICEBUS_PUBLICACION_TOPIC', 'maestros-eventos'),
        'nombre' => (string) env('SERVICEBUS_PUBLICACION_SAS_NOMBRE', ''),
        'clave' => (string) env('SERVICEBUS_PUBLICACION_SAS_CLAVE', ''),
    ],

    // Cuánto espera el bus un mensaje antes de responder que no hay. Es lo que
    // tarda, como mucho, un evento del outbox en salir (EM6).
    'espera_segundos' => (int) env('SERVICEBUS_ESPERA_SEGUNDOS', 10),
];
