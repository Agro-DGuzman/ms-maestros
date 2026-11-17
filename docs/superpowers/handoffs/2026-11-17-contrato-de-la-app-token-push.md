# Cambios al contrato de la App para el token push

Fecha: 17/11/2026. Son para `agropartners-api-v1.yaml`, y se hacen en el visor: el archivo no se edita en
los repos. Salen de la spec de ms-maestros `docs/superpowers/specs/2026-11-10-notificaciones-push-design.md`:
- NP2: el login guarda el token;
- NP3: registrar es guardarlo;
- NP4: un teléfono es de una sola sesión;
- NP18: cambiar el token sin cerrar sesión;
- NP19: el bloque `dispositivo` se manda siempre.

Ya funciona en ms-maestros:
- el login acepta `dispositivo.tokenPush`;
- `dispositivoRegistrado` dice si quedó un token;
- el logout lo borra.

Falta la operación nueva del cambio 6: se implementa cuando el contrato la traiga, porque `ContratoTest`
prohíbe exponer en `/v1` lo que el contrato no tiene.

**Decisión de estilo:** la spec decía que la operación nueva respondería 204. Responde **200 con el
envelope**, como el resto del contrato, que no tiene ningún 204, y con `dispositivoRegistrado`, como el login.

Cada bloque va con la sangría que tiene en el archivo: los esquemas cuelgan de `components.schemas`, con 4
espacios, y las rutas de `paths`, con 2.

---

## 1. `components.schemas.RegistroDispositivo`: reemplazar el esquema entero

```yaml
    RegistroDispositivo:
      type: object
      required:
      - instalacionId
      - plataforma
      properties:
        instalacionId:
          type: string
          format: uuid
          description: La genera la App una sola vez por instalación y la guarda. Es la misma en cada
            ingreso desde ese teléfono.
        plataforma:
          type: string
          enum:
          - android
          - ios
        tokenPush:
          type: string
          minLength: 1
          maxLength: 4000
          pattern: ^\S+$
          description: Token de registro de Firebase Cloud Messaging, en Android y en iOS (en iOS es el
            de FCM, no el de APNs). Va solo si la persona concedió permiso de notificaciones. Si Firebase
            lo rota, se cambia con `PUT /dispositivos/{instalacionId}/token`, sin cerrar sesión.
          examples:
          - fXk3Q9pL2sA:APA91bE-token-de-ejemplo
      description: La instalación desde la que se inició sesión, con su token push si hay permiso de
        notificaciones. Se envía en `POST /auth/login`.
```

## 2. `components.schemas.CodigoDeAcceso`: reemplazar la propiedad `dispositivo`

```yaml
        dispositivo:
          allOf:
          - $ref: '#/components/schemas/RegistroDispositivo'
          description: Se envía siempre, haya o no permiso de notificaciones; `tokenPush` va solo si lo hay.
            Sin este bloque la sesión no queda atada a la instalación, y la App no puede registrar el
            token más tarde.
```

## 3. `components.schemas.SesionIniciada` (el esquema del 200 del login): reemplazar `dispositivoRegistrado`

```yaml
        dispositivoRegistrado:
          type: boolean
          description: True si la sesión quedó con un token push y va a recibir notificaciones. False si
            no vino `dispositivo.tokenPush`. Un token mal formado no da false, responde 400.
```

## 4. `paths./auth/login.post`: la descripción y los ejemplos

En `description`, reemplazar el párrafo que hoy dice:

> Registra el dispositivo para notificaciones push si se envía el bloque `dispositivo`. **Es el único
> punto de registro del token push**: si la plataforma lo rota, hay que volver a iniciar sesión.

por este, con la misma sangría que el resto de la descripción:

```text
        Registra la instalación en la sesión, y el token push si viene `dispositivo.tokenPush`. Una
        instalación es de la última sesión que la registró: si otra persona entra en el mismo teléfono,
        la sesión anterior deja de recibir notificaciones. Si Firebase rota el token, la App lo cambia con
        `PUT /dispositivos/{instalacionId}/token`, sin volver a iniciar sesión.
```

En `requestBody.content.application/json.examples`, reemplazar los dos ejemplos. `sinDispositivo` sale,
porque ahora el bloque se manda siempre:

```yaml
            examples:
              conDispositivo:
                summary: Ingreso desde Android con permiso de notificaciones
                value:
                  otpId: e2b5a4d1-0c67-4f3a-9b18-5d2c7e91f306
                  codigo: '0421'
                  dispositivo:
                    instalacionId: 3f1c9a20-8d44-4b6e-9d21-6a7f0c4b18ee
                    plataforma: android
                    tokenPush: fXk3Q9pL2sA:APA91bE-token-de-ejemplo
              sinPermiso:
                summary: Ingreso sin permiso de notificaciones (el bloque va igual, sin token)
                value:
                  otpId: e2b5a4d1-0c67-4f3a-9b18-5d2c7e91f306
                  codigo: '0421'
                  dispositivo:
                    instalacionId: 3f1c9a20-8d44-4b6e-9d21-6a7f0c4b18ee
                    plataforma: ios
```

En `responses.'400'.content.application/json.examples`, sumar uno al lado de `parametroInvalido`:

```yaml
                tokenPushInvalido:
                  summary: Token push con espacios
                  value:
                    data: null
                    success: false
                    error:
                      code:
                      - PARAMETRO_INVALIDO
                      description: Parámetros mal formados.
                      structuredMessage:
                      - campo: dispositivo.tokenPush
                        codigo: PARAMETRO_INVALIDO
                        mensaje: Valor inválido.
                      type: VALIDATION
```

## 5. `paths./auth/logout`: no cambia

Ya dice que «desregistra el dispositivo de notificaciones», y eso es lo que hace: el logout borra el
token de esa sesión.

## 6. `paths`: la operación nueva, después de `/auth/logout`

```yaml
  /dispositivos/{instalacionId}/token:
    x-agropartners-servicio: maestros
    put:
      tags:
      - Autenticación
      summary: Cambiar el token push del dispositivo
      description: |-
        Guarda el token push nuevo en la sesión abierta de la persona con esa instalación, sin cerrar
        sesión. La App lo llama cada vez que Firebase le entrega un token nuevo (`onNewToken` en
        Android, `messaging(_:didReceiveRegistrationToken:)` en iOS) y cuando la persona concede el
        permiso de notificaciones después de haber entrado.

        La sesión se busca por la persona del token de acceso y por `instalacionId`. Si esa persona no
        tiene una sesión abierta con esa instalación —la cerró, venció, u otra persona entró después en
        el mismo teléfono—, responde 404 y la App tiene que volver a iniciar sesión para registrarlo.
      operationId: cambiarTokenPush
      parameters:
      - name: instalacionId
        in: path
        required: true
        description: La misma `instalacionId` que se mandó en `POST /auth/login`.
        schema:
          type: string
          format: uuid
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
              - tokenPush
              properties:
                tokenPush:
                  type: string
                  minLength: 1
                  maxLength: 4000
                  pattern: ^\S+$
                  description: El token de registro nuevo de Firebase Cloud Messaging.
            examples:
              tokenNuevo:
                summary: Firebase rotó el token
                value:
                  tokenPush: fXk3Q9pL2sA:APA91bE-token-nuevo-de-ejemplo
      responses:
        '200':
          description: Token guardado.
          content:
            application/json:
              schema:
                type: object
                required:
                - data
                - success
                - error
                properties:
                  data:
                    type: object
                    required:
                    - dispositivoRegistrado
                    properties:
                      dispositivoRegistrado:
                        type: boolean
                        const: true
                  success:
                    type: boolean
                    const: true
                  error:
                    type: 'null'
                    description: Siempre null cuando `success` es true.
              examples:
                guardado:
                  value:
                    data:
                      dispositivoRegistrado: true
                    success: true
                    error: null
        '400':
          description: Parámetros mal formados.
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/RespuestaError'
              examples:
                tokenPushInvalido:
                  summary: Token push con espacios
                  value:
                    data: null
                    success: false
                    error:
                      code:
                      - PARAMETRO_INVALIDO
                      description: Parámetros mal formados.
                      structuredMessage:
                      - campo: tokenPush
                        codigo: PARAMETRO_INVALIDO
                        mensaje: Valor inválido.
                      type: VALIDATION
        '401':
          $ref: '#/components/responses/NoAutenticado'
        '404':
          description: Esa persona no tiene una sesión abierta con esa instalación.
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/RespuestaError'
              examples:
                dispositivoNoEncontrado:
                  summary: La sesión de ese teléfono se cerró, venció o pasó a otra persona
                  value:
                    data: null
                    success: false
                    error:
                      code:
                      - DISPOSITIVO_NO_ENCONTRADO
                      description: No hay una sesión abierta en este dispositivo.
                      structuredMessage: []
                      type: NOT_FOUND
```

## 7. Opcional: qué trae cada notificación, en el tag `Avisos`

No es parte de la API, pero es donde el equipo de la App lo va a buscar. Reemplazar la descripción del tag:

```yaml
- name: Avisos
  description: |-
    Bandeja de avisos del usuario del portal.

    Cada aviso nuevo llega además como notificación push, por Firebase Cloud Messaging, a los
    teléfonos con sesión abierta y token registrado de quien ve ese socio, si tiene prendida su
    categoría. La notificación trae el título y el cuerpo del aviso, y en `data`:
    - `avisoId`: el id del aviso en la bandeja;
    - `categoria`: `factura`, `vencimiento`, `pago`, `reporte_campo` o `catalogo`;
    - `cardCode`: el socio del aviso, vacío en los de catálogo.

    Un aviso de más de 24 horas, o de un documento de antes de ayer, queda solo en la bandeja.
```

---

## Después de reemplazar el archivo

1. Copiar el contrato nuevo a `contrato/agropartners-api-v1.yaml` en ms-maestros (y en ms-comercial, que
   tiene su propia copia).
2. En ms-maestros, `ContratoTest` va a fallar por la operación nueva hasta que se implemente: es la tarea 7
   del plan `docs/superpowers/plans/2026-11-16-notificaciones-push.md`, que estaba bloqueada por esto.
