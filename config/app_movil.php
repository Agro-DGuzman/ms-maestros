<?php

declare(strict_types=1);

/*
 * Lo que la App lee del servidor en vez de llevarlo compilado. Vive en
 * variables de entorno por ahora: cambiar algo es editar el contenedor.
 */
return [
    // Sin mínima ni recomendada, la plataforma deja pasar todas las versiones.
    'versiones' => [
        'android' => [
            'minima' => (string) env('MOVIL_ANDROID_MINIMA', ''),
            'recomendada' => (string) env('MOVIL_ANDROID_RECOMENDADA', ''),
            'tienda' => (string) env('MOVIL_ANDROID_TIENDA', ''),
        ],
        'ios' => [
            'minima' => (string) env('MOVIL_IOS_MINIMA', ''),
            'recomendada' => (string) env('MOVIL_IOS_RECOMENDADA', ''),
            'tienda' => (string) env('MOVIL_IOS_TIENDA', ''),
        ],
    ],

    'avisos' => [
        'obligatoria' => [
            'titulo' => (string) env('MOVIL_TITULO_OBLIGATORIA', 'Actualización necesaria'),
            'mensaje' => (string) env('MOVIL_MENSAJE_OBLIGATORIA', 'Esta versión dejó de estar disponible. Actualiza para seguir usando la App.'),
        ],
        'sugerida' => [
            'titulo' => (string) env('MOVIL_TITULO_SUGERIDA', 'Hay una versión nueva'),
            'mensaje' => (string) env('MOVIL_MENSAJE_SUGERIDA', 'Hay una versión nueva de la App. Puedes actualizar cuando quieras.'),
        ],
    ],

    // `Banco|cuenta` por entrada, separadas por `;`.
    'bancos' => [
        'cuentas' => (string) env('BANCOS', ''),
        'titular_razon_social' => (string) env('BANCOS_TITULAR_RAZON_SOCIAL', 'Agropartners S.R.L.'),
        'titular_nit' => (string) env('BANCOS_TITULAR_NIT', ''),
    ],

    // Un celular: WhatsApp no abre chats con un fijo.
    'atencion' => [
        'area' => (string) env('ATENCION_AREA', 'Atención al Cliente'),
        'telefono' => (string) env('ATENCION_TELEFONO', ''),
    ],
];
