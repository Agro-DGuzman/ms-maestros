# El estado de cuenta, filtrado por socio

Fecha: 24/11/2026. Son cambios a `agropartners-api-v1.yaml` para la operación de ms-comercial
`/estados-de-cuenta`. El contrato se edita en el visor, pero la cuenta del visor sigue vencida, y el
usuario aprobó la misma excepción que en NP18: aplicar los bloques a mano en las copias de ms-comercial
y ms-maestros, y cargarlos en el visor cuando vuelva a haber cuenta.

## Qué se decidió

- **El pedido:** después de `POST /estados-de-cuenta`, la App sigue la emisión consultando solo la fila de
  ese socio, no la lista entera.
- **Filtro:** `GET /estados-de-cuenta` acepta `cardCode`, opcional, con el parámetro compartido
  `CardCodeQuery`.
  - Con él, devuelve la misma forma, `{ items: [...] }`, con **una sola fila**: la emisión vigente de
    ese socio.
  - Sin él, la lista queda igual que hoy.
  - No se filtra por `solicitudId`: cuando una emisión más nueva reemplaza a la que se seguía, esa
    solicitud deja de ser la vigente y no habría qué responder. La fila trae su `solicitudId`, así que la
    App puede ver si la emisión es la suya.
- **Bordes:**

  | Caso | Respuesta |
  |---|---|
  | Socio fuera del alcance, exista o no | 403 `ACCESO_DENEGADO`: el alcance va antes que la existencia |
  | Socio del alcance que nunca pidió un estado de cuenta | Su fila con `sin_emitir` |
  | `cardCode` presente pero vacío | 400 `PARAMETRO_INVALIDO` sobre el campo `cardCode` |
  | Más de un `cardCode` | No se acepta: un solo socio por pedido |

- **`Location`:** el 202 del POST trae `/v1/estados-de-cuenta?cardCode=<el del cuerpo>`. En el contrato,
  la cabecera pasa a `format: uri-reference`, porque es una ruta relativa: detrás del APIM el servicio no
  conoce su URL pública.
- **Seguimiento:** la App consulta esa URL cada `reintentarEnSegundos`.
  - Para en `disponible`, cuando ya hay `url`, o en `fallida`.
  - Una emisión colgada más de 10 minutos también se ve `fallida`, y se puede volver a pedir.
- **Sin push:** el contrato deja de prometer una notificación cuando el PDF está listo. La spec de
  ms-comercial (EC10) y la del push de ms-maestros lo dejaron afuera a propósito.
- **No cambia:** mientras la fila está `pendiente`, `generando` o `fallida`, `url` es `null`, aunque
  exista un documento anterior.

## Vocabulario

- **Estado de cuenta:** el PDF con las facturas de un **socio de negocio**. Es del socio, no de la persona
  que lo pide.
- **Emisión:** cada pedido de un estado de cuenta, identificado por su `solicitudId`. La más nueva
  reemplaza a la anterior.
- **Emisión vigente:** la última emisión de un socio. Es lo que muestra su fila, y lo que el pedido
  llamaba «el último generado».

---

Cada bloque va con la sangría que tiene en el archivo. El texto de `info.description` cuelga con 4
espacios, las rutas de `paths` con 2 y los esquemas de `components.schemas` con 4.

## 1. `info.description`, el apartado «Escrituras asincrónicas»: reemplazar el párrafo

```text
    ## Escrituras asincrónicas
    Solicitar una visita técnica o un estado de cuenta responde `202 Accepted` con un identificador de
    seguimiento. El avance del estado de cuenta se consulta con `GET /estados-de-cuenta?cardCode=`, la
    URL que trae la cabecera `Location`; la solicitud de visita no tiene seguimiento.
```

## 2. `paths./estados-de-cuenta.get`

Reemplazar `description` y `parameters`:

```yaml
      description: |-
        Resuelve la pantalla *Descargar estado de cuenta*: **una fila por socio de negocio** que la persona
        alcanza, con la fecha de emisión del documento vigente, su peso y el enlace para bajarlo. Cada
        emisión reemplaza a la anterior. No pagina.

        Con `cardCode` devuelve solo la fila de ese socio, en la misma forma. Es la URL que trae la
        cabecera `Location` de `POST /estados-de-cuenta`, y la que la App consulta para seguir una
        emisión. Un socio fuera del alcance responde `403`, exista o no; uno que nunca pidió un estado de
        cuenta devuelve su fila con `sin_emitir`.

        Para seguir una emisión, la App consulta cada `reintentarEnSegundos` y para cuando la fila queda
        `disponible` (ya trae `url`) o `fallida`. Una emisión que no termina en 10 minutos también se ve
        `fallida`, y se puede volver a pedir. Mientras la fila está `pendiente`, `generando` o `fallida`,
        `url` es `null`, aunque exista un documento anterior.
      operationId: obtenerEstadoDeCuenta
      parameters:
      - $ref: '#/components/parameters/CardCodeQuery'
```

En `responses.'200'.content.application/json.examples`, sumar uno después de `unaEmisionEnCurso`:

```yaml
                soloUnSocio:
                  summary: Con `cardCode`, la fila de ese socio mientras se genera
                  value:
                    data:
                      items:
                      - socio:
                          cardCode: C-004872
                          razonSocial: Fernando Monasterio
                          iniciales: FM
                          cantidadPropiedades: 3
                        estado: generando
                        url: null
                        expiraEn: null
                        emitidoEl: '2026-08-04T11:05:00Z'
                        tamanoFormateado: 186 KB
                        solicitudId: 1b73c9a4-58e2-4d61-9f0c-3a5e7b2d4c88
                    success: true
                    error: null
```

En `responses`, sumar el 400 antes del `'401'`:

```yaml
        '400':
          description: Parámetros mal formados.
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/RespuestaError'
              examples:
                cardCodeVacio:
                  summary: '`cardCode` presente pero vacío'
                  value:
                    data: null
                    success: false
                    error:
                      code:
                      - PARAMETRO_INVALIDO
                      description: Parámetros mal formados.
                      structuredMessage:
                      - campo: cardCode
                        codigo: PARAMETRO_INVALIDO
                        mensaje: Valor inválido.
                      type: VALIDATION
```

El `'403'` ya está (`Prohibido`), y ahora además cubre el `cardCode` fuera de alcance.

## 3. `paths./estados-de-cuenta.post`

Reemplazar `description`:

```yaml
      description: |-
        Pide una emisión nueva para un socio. La generación es asincrónica y no hay notificación push: el
        avance se sigue consultando `GET /estados-de-cuenta?cardCode=`, la URL que trae la cabecera
        `Location`, cada `reintentarEnSegundos`. Cada emisión reemplaza a la anterior.
```

En `responses.'202'`, reemplazar la cabecera `Location`:

```yaml
            Location:
              description: Dónde seguir la emisión, `/v1/estados-de-cuenta?cardCode=<el del cuerpo>`. Es
                una ruta relativa, porque detrás del APIM el servicio no conoce su URL pública.
              schema:
                type: string
                format: uri-reference
```

En el mismo `'202'`, reemplazar el ejemplo `encolada`:

```yaml
                encolada:
                  summary: La emisión queda en cola; el avance se consulta
                  value:
                    data:
                      solicitudId: 9c1e7f30-4a52-4c88-b7d1-2e6f0a3b5c19
                      estado: pendiente
                      mensaje: Estamos generando tu estado de cuenta. Va a estar listo en unos segundos.
                      reintentarEnSegundos: 5
                      creadoEn: '2026-08-19T11:40:00Z'
                    success: true
                    error: null
```

## 4. `components.schemas.AcuseEmisionEstadoDeCuenta`

Reemplazar la `description` del esquema:

```yaml
      description: Acuse de una emisión de estado de cuenta. El avance se sigue con
        `GET /estados-de-cuenta?cardCode=`.
```

Y la `description` de `reintentarEnSegundos`:

```yaml
          description: Cuánto esperar antes de volver a consultar `GET /estados-de-cuenta?cardCode=`.
```

---

## Qué hace cada repo

- **ms-maestros:** aplica los bloques a su copia. No implementa nada: la operación es de ms-comercial, y
  su `ContratoTest` solo mira las de `maestros`.
- **ms-comercial:** aplica los mismos bloques a su copia e implementa con TDD:
  - el filtro en `EstadosDeCuentaController::index` y en `ObtenerEstadosDeCuenta`, con la verificación de
    alcance antes de buscar;
  - la `Location` con `?cardCode=`;
  - los casos nuevos del GET en su `ContratoDeRespuestasTest`: 200 filtrado, 400 y 403.

  Además saca de su `AGENTS.md` el pendiente del texto del push, que con esto queda resuelto en las
  copias.
- **El visor:** cuando vuelva a haber cuenta, se cargan estos cambios junto con los de NP18
  (`2026-11-17-contrato-de-la-app-token-push.md`).
