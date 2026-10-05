# Ingesta de socios desde SAP — diseño

## Para qué

El **Sincronizador** (proceso .NET en un droplet, `178.128.144.47`) lleva a
`ms-maestros` cada cambio que SAP confirma sobre un socio y sus personas de
contacto. Reemplaza, en Azure, al importador de JSON: la réplica pasa a
alimentarse por la **API de ingesta**, socio por socio y en segundos.

**Éxito:**
- Los pasos 4.1 a 4.7 de `CONFIGURACION-INGESTA-SAP.md` se cumplen.
- La prueba de punta a punta de su §6 pasa:
  - un cambio en SAP se ve en `ms-maestros` en menos de 15 segundos;
  - un socio sin grupo no lo ve nadie de afuera;
  - un grupo nuevo se registra solo;
  - una credencial rota no consume reintentos.
- Una persona de contacto dada de baja en SAP deja de entrar y de ver datos en
  a lo sumo 5 minutos.

**Fuentes:**
- `DECISIONES-INGESTA-SOCIOS.md` (D1–D15), `DISENO-SINCRONIZADOR.md` y
  `CONFIGURACION-INGESTA-SAP.md`, todos con la enmienda del 5 de octubre.
- `ingesta-api-v1.yaml`, en su versión del 1 de octubre, que se enmienda acá.

## Decisiones tomadas en el diseño

| # | Decisión | Por qué |
|---|---|---|
| I1 | El id de persona de un contacto es `p-{CntctCode}` | Determinista y sin tabla de traducción. Las personas de la semilla (`p-8f2b1c40`) son de prueba |
| I2 | **La habilitación la decide el back-office.** La ingesta nunca escribe `habilitada_el` | SAP no manda ningún dato de habilitación, y así no hacen falta cambios en SAP ni en el Sincronizador |
| I3 | Una persona cuyo socio no tiene grupo ve **solo su propio socio** | D12 protege que nadie de afuera lo vea. Un grupo de uno no mezcla a nadie |
| I4 | Un celular que comparten dos contactos visibles **se acepta y se bloquea**: ninguno puede entrar con él hasta que SAP lo corrija | Falla cerrado solo para ese número, sin frenar otros cambios del socio |
| I5 | Todo vive en Maestros; la idempotencia es un middleware HTTP | Maestros es dueño de la réplica, y la regla de vigencia ya vuelve inofensivo reaplicar un cuerpo |

## Alcance

**Adentro (parte A, este spec):**
- `POST /ingesta/v1/socios` y `PUT`/`DELETE /ingesta/v1/socios/{cardCode}`, con
  la seguridad, la idempotencia y la copia enmendada del contrato.
- Los cambios de la réplica que la ingesta necesita: grupo opcional con
  segmento y vigencia propia, socio activo o dado de baja, contactos con celular
  opcional y estado.
- Cómo esos estados se reflejan en el alcance, el ingreso, la renovación de
  sesión y la pantalla de Contactos.
- El middleware del secreto del gateway, también para `/v1`, detrás de un
  interruptor.

**Afuera:**
- **Parte B, spec aparte:** dar acceso marca `habilitada_el` y quitarlo lo
  borra, y la conciliación y la pantalla se ajustan a eso. **Tiene que estar
  antes de que entren personas reales por la ingesta.**
- Revocar automáticamente la credencial de Keycloak de un contacto dado de
  baja. La renovación cortada (sección 4) lo deja sin acceso; limpiar la
  credencial es de la conciliación.
- La API de ingesta de documentos comerciales (el Drenador, `ms-comercial`).
- Borrar la columna `vista_en_importacion_el`: queda en desuso y se borra en
  una entrega posterior (sección 6).

## 1. El contrato y las respuestas

`contrato/ingesta-api-v1.yaml` es la copia del contrato, con cuatro
enmiendas:

1. **Rol:** `Ingesta.Maestros.Escribir` (D9), en lugar de `Ingesta.Escribir`.
2. **Grupo:** `grupoEconomico` opcional y anulable, con la forma
   `{ codigo, nombre, segmento }`. `codigo` y `nombre` son obligatorios dentro
   del objeto; `segmento` es anulable (D10, D13).
3. **Error:** el error tiene la forma de `/v1` (`code` como lista,
   `description`, `structuredMessage`, `type`). El contrato ya decía «como
   `/v1`», y así el armador de respuestas es uno solo.
4. **Status:** se agregan 401, 403 y 503, que se usan y no figuraban.

Esas mismas enmiendas tienen que llegar al archivo oficial que usa el equipo
del Sincronizador.

**Status.** La seguridad se evalúa en este orden y corta en la primera que
falla:

| Situación | Status | Código |
|---|---|---|
| Falta `X-Gateway-Secret` o no coincide | 403 | `ACCESO_DENEGADO` |
| Token de Entra ausente, inválido, de otro `iss` o `aud`, o sin el rol | 401 | `TOKEN_INVALIDO` |
| No se pueden obtener las claves de Entra | 503 | `ENTRA_NO_DISPONIBLE` |
| Falta la configuración de Entra | 500 | `CONFIGURACION_INCOMPLETA` |
| Falta `Idempotency-Key` o no es `^[0-9a-f]{32}$` | 400 | `FieldError` sobre `Idempotency-Key` |
| Cuerpo mal formado: campo faltante, tipo o largo inválido, `codigoDeContacto` repetido o con algo que no sean dígitos | 400 | Un `FieldError` por campo |
| `PUT` con el `cardCode` del cuerpo distinto del de la ruta | 422 | `CARDCODE_NO_COINCIDE` |
| `POST` de un socio que existe y no está dado de baja | 409 | `SOCIO_YA_EXISTE` |
| `PUT` o `DELETE` de un socio que no existe o está dado de baja | 404 | `SOCIO_NO_ENCONTRADO` |
| Éxito | 201 `POST` · 200 `PUT` · 204 `DELETE` | `data: { cardCode }`; el 204 va sin cuerpo |

`MapaDeErroresHttp` suma `CARDCODE_NO_COINCIDE` → 422 y
`ENTRA_NO_DISPONIBLE` → 503. Un socio dado de baja responde 404 al `PUT` a
propósito: si SAP lo vuelve a crear, el Sincronizador reintenta como `POST`, y
el `POST` lo reactiva.

**Idempotencia:**
- **Identificación:** por la terna `(clave, método, ruta)`, donde la ruta es
  el path completo, con el `cardCode` (`/ingesta/v1/socios/C-004871`). El Sincronizador
  reintenta un `POST` que recibió 409 como `PUT` **con la misma clave**, y
  con la clave sola el `PUT` recibiría el 409 guardado.
- **Repetición:** si la terna ya se vio, se devuelve el status y el cuerpo de
  la primera vez sin aplicar nada.
- **Qué se guarda:** toda respuesta final **menos las 5xx**, que son
  transitorias y tienen que poder reintentarse.
- **Sin comparar el cuerpo:** la clave es el `MENSAJE_ID` de SAP.
- **Sin atomicidad con la escritura:** reaplicar el mismo cuerpo es inofensivo
  por la regla de vigencia.

## 2. El modelo de la réplica

Una migración que solo agrega columnas y afloja restricciones. Las filas que
ya existen quedan activas y sin baja.

| Tabla | Cambio |
|---|---|
| `maestros.grupos` | `id_de_grupo` pasa de 40 a 50 caracteres (el `Code` de una tabla de usuario de SAP). Se agrega `segmento NVARCHAR(100) NULL`. `vigente_desde` es la vigencia **propia** del grupo |
| `maestros.socios` | `id_de_grupo` pasa a 50 caracteres y anulable. Se agregan `activo BIT NOT NULL DEFAULT 1`, `dado_de_baja_el` (anulable), `origen_esquema NVARCHAR(20) NULL` y `origen_evento_id BIGINT NULL` |
| `maestros.contactos` | `celular` pasa a anulable y **deja de ser único** (índice común). Se agregan `activo BIT NOT NULL DEFAULT 1` y `dado_de_baja_el` (anulable). `vista_en_importacion_el` queda en desuso |
| `maestros.ingesta_respuestas` (nueva) | `clave CHAR(32)`, `metodo VARCHAR(6)`, `ruta NVARCHAR(100)`, `status SMALLINT`, `cuerpo NVARCHAR(MAX) NULL`, `recibida_el`. Clave primaria: `(clave, metodo, ruta)` |

**Definiciones** (cada una en un solo lugar del adaptador):
- **Socio visible:** `activo = 1` y `dado_de_baja_el` vacío.
- **Contacto visible:** `activo = 1`, `dado_de_baja_el` vacío y su socio
  visible.
- **Celular en conflicto:** el que comparten dos o más contactos visibles. Se
  calcula al consultar, nunca se guarda, así se resuelve solo cuando SAP
  corrige uno.

**Dominio:**
- `IdDePersona::deContactoSap(string $codigo)` arma `p-{codigo}`. Exige solo
  dígitos y a lo sumo 38 (el id tiene 40).
- `PersonaDeContacto`:
  - el celular pasa a ser opcional (`?Celular`), y se suman `activo` y la baja;
  - un celular que no se normaliza a +591 se guarda vacío y no rechaza al
    socio: ese contacto no puede entrar.
- `Socio`: grupo opcional (`?IdDeGrupo`), más `activo`, la baja y el origen.
- `GrupoEconomico`: suma `segmento`.
- `tipoSap` no se guarda: solo se conservan los `C`.

**El importador de JSON** (`maestros:importar`) queda para local y para los
tests. Se adapta a lo mismo: un contacto que no viene en el archivo queda con
`dado_de_baja_el`, en lugar de marcar la corrida.

## 3. La escritura

Dos casos de uso en `Maestros/Application/Ingesta/`, ambos con
`RequiereTransaccion`. **Todo lo que puede fallar se decide antes de escribir
algo**: un `Result` fallido confirma la transacción, y un grupo escrito antes
de una validación fallida quedaría huérfano (D14).

### `ReplicarSocio(cuerpo, operación)`, para `POST` (crear) y `PUT` (reemplazar)

La forma del cuerpo (los 400 de la sección 1) se valida antes, en la capa
HTTP: el caso de uso recibe un cuerpo bien formado.

1. **Validar sin escribir.** En un `PUT`, el `cardCode` del cuerpo tiene que
   coincidir con el de la ruta (422).
2. **Existencia:**
   - crear un socio que existe y no está dado de baja (activo o inactivo)
     → 409;
   - reemplazar uno inexistente o dado de baja → 404.
3. **Vigencia.** Si lo guardado tiene `vigente_desde` igual o posterior al
   cuerpo, se ignora el cuerpo entero (grupo incluido) y se responde éxito.
   Va antes que el tipo: un envío viejo de cuando era un lead no puede dar de
   baja a quien SAP ya convirtió en cliente. *(Corregido en la revisión final:
   la primera versión ponía el tipo antes.)*
4. **Tipo.** Si `tipoSap` no es `C`:
   - al crear: 201 sin guardar nada;
   - al reemplazar: baja igual que en `DELETE` (sin el 404) y 200.
5. **Grupo primero:**
   - si viene y no existe, se crea con la vigencia del cuerpo;
   - si existe y el cuerpo es más nuevo que *su* vigencia, se actualizan
     nombre, segmento y vigencia;
   - si es más viejo, no se toca.

   En los tres casos se usa su `codigo` para la pertenencia.
6. **Socio:**
   - se escriben razón social, grupo (o ninguno), `activo`, `vigente_desde` y
     origen;
   - `dado_de_baja_el` vuelve a vacío (así un `POST` reactiva).
7. **Contactos:**
   - cada uno del cuerpo se escribe como `p-{codigo}`: nombre, celular
     normalizado o vacío, `activo`, `vigente_desde` y sin baja, **conservando
     su `habilitada_el`**;
   - si el código existía bajo otro socio, pasa a este;
   - los contactos visibles de este socio que no vinieron quedan con
     `dado_de_baja_el`.

### `DarDeBajaSocio(cardCode)`, para `DELETE`

- Si no existe o ya estaba dado de baja → 404.
- Si no:
  - el socio y todos sus contactos quedan con `dado_de_baja_el`;
  - la `vigente_desde` del socio pasa a ser **el momento de la baja**, así un
    envío leído en SAP antes de la baja tiene vigencia anterior y se ignora,
    y no lo resucita.

## 4. El alcance, el ingreso y las lecturas

**Alcance (`ResolutorPorGrupo`):** alcanza si la persona es visible, el socio
pedido es visible, y además:
- si el socio de la persona tiene grupo, son del mismo grupo;
- si no lo tiene, es el mismo socio.

Lo demás es 403, incluido un socio dado de baja o inactivo. El alcance sigue
verificándose antes que la existencia.

**Contexto (`/socios`, `/mi-cuenta`, el login):**
- Lista los socios visibles del grupo.
- Sin grupo, trae solo su socio, con `grupoEconomico.id` y `nombre` vacíos (el
  contrato de la App los exige, y es el criterio que ya se usa cuando el grupo
  falta en la réplica).
- Una persona no visible se trata como hoy a una inexistente.

**Ingreso (`BuscarPorCelular`, que usan el trabajo del desafío y el login):**
- Busca solo contactos visibles con ese celular.
- Ninguno, o dos o más (conflicto), es **número desconocido**:
  - el desafío se emite igual, con la misma respuesta y en el mismo tiempo,
    pero no se envía;
  - el login responde `CODIGO_INVALIDO`.

**Renovación (`RenovarSesion`):**
- Antes de renovar, pregunta al `DirectorioDeContactos` si la persona sigue
  visible.
- Si no lo está, responde `REFRESH_TOKEN_INVALIDO` y cierra la sesión.
- Con el token de 5 minutos, eso acota la baja.

**Back-office:**
- **Contactos:**
  - lista también a los inactivos y dados de baja, con su estado;
  - señala «celular en conflicto»;
  - para quien tiene habilitación, señala «dado de baja en SAP», que
    reemplaza a «ausente en la última importación».
- **Dar acceso:** se niega con un mensaje claro si la persona no es visible o
  si su celular está en conflicto.

## 5. Seguridad y configuración

**Rutas:** `src/Maestros/Presentation/Http/ingesta.php`, con prefijo
`ingesta/v1` y fuera de `auth.token`. Middlewares, en orden:
`ExigirSecretoDelGateway` → `AutenticarIngesta` → `Idempotencia`.

**`ExigirSecretoDelGateway` (`app/`):**
- **Comparación:** contra `GATEWAY_SECRETO`, con `hash_equals`.
- **En `/ingesta`:** siempre obligatorio. Con `GATEWAY_SECRETO` vacío,
  rechaza todo con 403 y deja un error en el log.
- **En `/v1`:** está conectado siempre, y decide **en cada pedido** según
  `GATEWAY_SECRETO_EN_V1` (apagado por defecto). No se resuelve al registrar
  las rutas, porque `route:cache` congelaría la configuración. Se prende
  después de confirmar que el APIM manda la cabecera en `/v1`.

**`AutenticarIngesta` + `VerificadorEntra` (`app/`):** son una identidad de
máquina, separada del verificador de Keycloak.
- **Firma:** solo RS256, contra
  `https://login.microsoftonline.com/{tenant}/discovery/v2.0/keys`.
- **Caché de claves:** 60 minutos. Un `kid` desconocido las vuelve a pedir
  una vez, como mucho cada 5 minutos.
- **`iss`:** exactamente `https://login.microsoftonline.com/{tenant}/v2.0`.
- **`aud`:** `INGESTA_ENTRA_AUDIENCIA`.
- **`roles`:** contiene `INGESTA_ROL`.
- **Vencimiento:** `exp` y `nbf`, con 60 segundos de tolerancia.

**Configuración** (las de Entra se leen al usarse, no al arrancar):

| Variable | Valor | Si falta |
|---|---|---|
| `GATEWAY_SECRETO` | El `gateway-shared-secret` del APIM, como `secretRef` | `/ingesta` responde 403 |
| `GATEWAY_SECRETO_EN_V1` | `false` por defecto | — |
| `INGESTA_ENTRA_TENANT_ID` | GUID del tenant de trabajo | `/ingesta` responde 500 `CONFIGURACION_INCOMPLETA` |
| `INGESTA_ENTRA_AUDIENCIA` | Id de cliente de `agropartners-ingesta-api` | Ídem |
| `INGESTA_ROL` | `Ingesta.Maestros.Escribir` por defecto | — |

**Log:** una línea por pedido con `cardCode`, operación, resultado (aplicado,
ignorado por viejo, dado de baja, no conservado por tipo) y `eventoId` de
origen.

## 6. Pruebas y despliegue

**Pruebas:**
- **Contrato:**
  - cada operación de `contrato/ingesta-api-v1.yaml` existe, y no hay en
    `/ingesta` nada que no figure;
  - cada status de la sección 1 valida contra su esquema;
  - `tests/Soporte/Contrato.php` se generaliza para recibir el archivo.
- **Escritura:** una prueba por paso de la sección 3, incluido que nada quede
  escrito cuando una validación falla.
- **Alcance e ingreso:** una por regla de la sección 4.
- **Seguridad:**
  - el secreto en `/ingesta` y el interruptor de `/v1`;
  - `VerificadorEntra` con claves RSA generadas en el test y el JWKS
    simulado (firma, `iss`, `aud`, rol, vencimiento, `kid` nuevo, caché);
  - idempotencia: repite status y cuerpo, el `POST` → 409 → `PUT` con la
    misma clave, y las 5xx no se guardan.
- **Contra el SQL Server local:** la migración, en especial quitar la
  unicidad del celular, que en SQL Server es un índice con nombre.

**Despliegue, en orden:**
1. **Migración en Azure antes del push**, desde la imagen local. Es compatible
   con el código que corre: no borra nada que ese código lea.
2. **Variables en el Container App:** `GATEWAY_SECRETO`,
   `INGESTA_ENTRA_TENANT_ID` e `INGESTA_ENTRA_AUDIENCIA`.
3. **Push.**
4. **Los pasos 2 y 3** de `CONFIGURACION-INGESTA-SAP.md` (Entra y APIM) y su
   prueba del §6.
5. **En una entrega posterior:** borrar `vista_en_importacion_el`.

## Abierto

- **La semilla en Azure:** los 8 socios de prueba, las 267 propiedades con
  socios inventados y la persona `p-8f2b1c40`. ¿Se borran cuando lleguen los
  socios reales? El equipo de la App los usa hoy, y un `cardCode` real podría
  coincidir con uno de la semilla.
- **Las enmiendas del contrato** de la sección 1 en el archivo oficial del
  equipo del Sincronizador.

## Lo que queda escrito

`CLAUDE.md`, en «Contratos que no se negocian»:
- La ingesta es la fuente de la réplica en Azure, y el importador queda para
  local.
- La identidad de un contacto es `p-{CntctCode}`.
- La ingesta nunca escribe `habilitada_el`.
- Qué es socio visible, contacto visible y celular en conflicto.
- Un socio sin grupo se ve solo a sí mismo.
- El orden de la seguridad en `/ingesta`.
- La idempotencia por terna.
- Que la migración va antes del push.
