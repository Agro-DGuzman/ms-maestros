# El código del ingreso, por WhatsApp de verdad

Fecha: 04/11/2026. Diseño corto, aprobado en la conversación.

## Para qué

La lógica del OTP está hecha: el desafío, el login y la cola. Lo que falta es que el código **llegue por WhatsApp**.
Ya hay cuenta de WhatsApp Business y token, pero falta la plantilla. El envío real (`EnviadorCloudApi`) existe desde
septiembre, aunque nunca se prendió, y tiene tres cosas que lo harían fallar o meter la pata al prenderlo.

Las reglas de tasa del contrato quedan para otra entrega: 3 por hora, plazo de reenvío, invalidar el desafío previo y
límites por IP. Ahí también se decide dónde van los límites por IP, porque el APIM es del plan Consumption y no tiene
`rate-limit-by-key`.

## Decisiones

| # | Decisión | Por qué |
|---|---|---|
| W1 | **La plantilla es de categoría Autenticación**: `desafio_ingreso`, en español (`es`), con la recomendación de seguridad, el pie de vencimiento en 5 minutos, el botón «Copiar código» y 5 minutos de validez del mensaje. La crea el usuario en WhatsApp Manager. | Meta exige esa categoría para un código de ingreso, y el texto lo pone Meta. Con 5 minutos de validez, un mensaje que WhatsApp no logra entregar a tiempo no llega con un código ya vencido. |
| W2 | **`EnviadorCloudApi` manda el código dos veces**: como parámetro del cuerpo y como parámetro del botón (`type: button`, `sub_type: url`, `index: "0"`). | Es lo que pide Meta para una plantilla de autenticación con botón de copiar. Sin el botón, rechaza el envío. |
| W3 | **Si Meta rechaza, el log guarda su motivo**: el estado, más el `code`, el `message`, el `error_data.details` y el `fbtrace_id` del error. Los 4 dígitos no se escriben nunca: si el texto de Meta los trajera, se tapan. | Sin el motivo, un rechazo se ve como «400» y hay que adivinar, como pasó con el bus. El contrato prohíbe registrar el código. |
| W4 | **A los números con eco (`OTP_ECO_NUMEROS`) no se les manda WhatsApp.** Lo decide `SolicitarDesafioHandler`, que ya los conoce para el límite, y no encola el envío. | Son números inventados que el equipo de la App comparte para probar, y reciben el código en la respuesta. Con WhatsApp prendido, el código le llegaría a quien tenga ese número de verdad. Se decide en la app y no en el `worker`, porque la variable vive en `ms-maestros`. Se retira junto con el eco. |
| W5 | **La versión de la API pasa de `v21.0` a `v26.0`.** | La v21 deja de funcionar el 21/01/2027. |
| W6 | **Un script manual, `tests/Manual/probar-whatsapp.php <celular>`**, manda un código de prueba con la plantilla real e imprime lo que respondió Meta. Arma `EnviadorCloudApi` directamente desde `config/whatsapp.php`, sin mirar `WHATSAPP_DRIVER`. | Prueba la plantilla y el token sin que el número tenga que ser contacto de un socio, y sin cambiar el driver local, que mandaría WhatsApp de verdad en cada `/auth/otp`. |

## Prenderlo en Azure

- Las variables van en el Container App **`worker`**, porque es el que corre `EnviarDesafioJob`: `WHATSAPP_DRIVER=cloud_api`, `WHATSAPP_PHONE_NUMBER_ID` y `WHATSAPP_TOKEN` como secreto.
- Primero el push con estos cambios, después las variables.
- El token tiene que ser de un **usuario de sistema**. El temporal del panel de desarrolladores vence a las 24 horas.

## Lo que queda afuera

- Las reglas de tasa del contrato.
- Guardar el código hasheado, como pide el contrato.
- `CANAL_NO_DISPONIBLE`: con el envío encolado, el endpoint no se entera de si WhatsApp está caído.
