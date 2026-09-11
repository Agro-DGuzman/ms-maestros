<?php

declare(strict_types=1);

return [
    'driver' => (string) env('WHATSAPP_DRIVER', 'log'),   // log | cloud_api
    'base_url' => (string) env('WHATSAPP_BASE_URL', 'https://graph.facebook.com/v21.0'),
    'phone_number_id' => (string) env('WHATSAPP_PHONE_NUMBER_ID', ''),
    'token' => (string) env('WHATSAPP_TOKEN', ''),
    'plantilla' => (string) env('WHATSAPP_PLANTILLA', 'desafio_ingreso'),
    'idioma' => (string) env('WHATSAPP_IDIOMA', 'es'),
];
