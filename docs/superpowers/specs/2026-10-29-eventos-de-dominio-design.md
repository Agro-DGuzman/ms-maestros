# ms-maestros — el bus de eventos de dominio (trabajo 3)

**Fecha:** 2026-10-29
**Deriva de:** la spec de ms-comercial `docs/superpowers/specs/2026-10-27-ms-comercial-eventos-de-dominio-design.md`
(el trabajo 2: ED1 a ED13), su guía `docs/despliegue-en-azure.md` (12.3 y 13.x) y su contrato
`contrato/eventos-de-dominio-v1.yaml`. Esa spec fija lo que comparten los dos servicios —los topics, el sobre y la
forma de los dos eventos— y manda en todo lo que esta no cambia. Esta es la parte de ms-maestros.

## 1. Para qué

ms-comercial necesita saber de cada socio lo que SAP le dice a ms-maestros: la razón social, el grupo, la baja y
sobre todo la **línea de crédito**, que *Inicio* suma para mostrar el crédito de la persona. Hoy no le llega nada.

ms-maestros, a su vez, necesita las **propiedades** de cada socio: las lista para *Solicitar visita técnica* y las
cuenta en `cantidadPropiedades` (`/mi-cuenta`, `/socios`, el login). Hoy salen de un script que carga una
conciliación a mano; desde ahora salen de Appgronomy, por ms-comercial.

Los dos servicios se avisan por Service Bus: ms-maestros publica `maestros.socio.actualizado` en `maestros-eventos`
y escucha `comercial.propiedad.actualizada` en `comercial-eventos`. ms-comercial ya tiene su lado hecho.

**Terminado cuando:**

1. Guardar un socio por la ingesta —crearlo, reemplazarlo o darlo de baja— deja su evento en el outbox en la misma
   transacción, y el consumidor lo publica en `maestros-eventos` en unos 10 segundos, nunca más de un minuto.
2. Un `comercial.propiedad.actualizada` en `sub-ms-maestros` crea o reemplaza la propiedad, con las reglas de
   siempre: un `eventoId` repetido no cambia nada y una foto vieja no pisa una nueva.
3. `eventos:publicar-socios` publica una vez todos los socios que ms-comercial tiene que tener.
4. La App lee el `estado` de la propiedad, no `activa`.
5. Una copia de `contrato/eventos-de-dominio-v1.yaml` está en `contrato/`, y una prueba valida contra ella los eventos
   que ms-maestros publica y el ejemplo de propiedad que consume.
6. `composer test:integration` pasa contra el topic de pruebas, `ms-maestros-consumidor` corre con una réplica, y la
   puesta en marcha (§8) está hecha.
7. `composer test`, `composer stan` y `composer lint` en verde, y `CLAUDE.md` dice cómo es todo.

## 2. Objetivos, no objetivos y lo que lo haría un fracaso

**Objetivos:** que ms-comercial tenga la foto de cada socio, con su línea, sin que nadie la cargue a mano; que las
propiedades de ms-maestros sigan a Appgronomy; que ninguno de los dos pierda un cambio sin que se vea.

**No objetivos:**
- Los contactos en el evento: son datos personales y ms-comercial no los usa (ED3).
- La `zona` de la propiedad: la App no la pide a ms-maestros (`Propiedad` del contrato es `{ id, nombre }`).
- Purgar las filas publicadas del outbox, otros eventos, notificaciones push.
- Cambiar nada en ms-comercial.

**Sería un fracaso aunque funcione:**
- Que la ingesta de socios dependa del bus: si Service Bus no responde, el Sincronizador tiene que seguir recibiendo
  201 y 200. El outbox es justamente lo que los desacopla.
- Que un evento se pierda en silencio: un corte entre guardar y publicar, o un mensaje que no se pudo aplicar y nadie
  ve.
- Que un socio de la semilla llegue a ms-comercial: no tiene línea, ms-comercial lo rechaza a mensajes muertos y
  suena la alerta por algo que no es un error.
- Que el consumidor se reinicie en bucle por un bus o una base caídos.

## 3. Cómo se construye: lo que se consideró

| Forma | Qué ofrecía | Por qué sí o por qué no |
|---|---|---|
| **A. Outbox más un consumidor de larga vida, como ms-comercial** | El evento nace en la transacción del dato; un Container App (`ms-maestros-consumidor`) publica el outbox y recibe en cada vuelta. | **Elegida.** Es lo que ms-comercial ya construyó y probó, y lo que fija su ED13. |
| Publicar apenas se guarda, sin outbox | 1 o 2 segundos en vez de 10. | Un corte entre guardar y publicar pierde el evento sin que nadie se entere. |
| El `worker` (`queue:work`) con una tarea que se vuelve a encolar para escuchar | Sin Container App nuevo. | Si la cadena se corta, ms-maestros deja de recibir sin aviso. ms-comercial lo descartó por eso. |
| Un SDK de Service Bus | Menos código propio. | El SDK de Azure para PHP está retirado y no hay un cliente AMQP 1.0 mantenido: va por la API REST. |

**Y cómo se trae el transporte de ms-comercial** (la firma SAS, el publicador, el buzón, el ciclo del consumidor):

| Forma | Qué ofrecía | Por qué sí o por qué no |
|---|---|---|
| **Copiarlo y adaptarlo** a los nombres y módulos de ms-maestros | Rápido; cada repo es dueño de lo suyo. Un arreglo en uno se lleva a mano al otro. | **Elegida por el usuario.** Son pocas clases, ya estables, y es como se trajo el core desde Java. |
| Un paquete de Composer compartido | Un solo lugar para arreglar. | Un repo nuevo, versiones, y Composer privado en los dos pipelines, por poco código. |

## 4. Decisiones

| # | Decisión | Por qué |
|---|---|---|
| EM1 | **El outbox es `maestros.evento_saliente`**, igual a `comercial.evento_saliente`: `evento_id` (uuid, clave primaria), `tipo`, `sobre` (el JSON entero, con `CHECK (ISJSON(sobre) = 1)` en SQL Server), `creado_en` y `publicado_en` con 6 decimales, `intentos`, y un índice común (no filtrado) sobre `(publicado_en, creado_en)`. Lo escribe un puerto de `Maestros\Application` (`SalidaDeEventos`) que no conoce `Illuminate`. | Es la forma que ms-comercial ya probó. El índice filtrado lo rechaza SQL Server con `QUOTED_IDENTIFIER` apagado (lo anotó ms-comercial). |
| EM2 | **Publican `ReplicarSocioHandler` y `DarDeBajaSocioHandler`**, dentro de su transacción (las dos peticiones ya son `RequiereTransaccion`): cuando el resultado es `Aplicado`, `DadoDeBaja` (el socio que deja de ser cliente) o la baja por `DELETE`. **No** publican con `IgnoradoPorViejo` ni con `NoConservado`. Publican aunque lo único que haya cambiado sean los contactos. | Es «cada vez que guarda un socio, también cuando lo da de baja» (§5 de la spec de comercial). Comparar contra lo guardado para no repetir una foto igual ahorra poco: la foto repetida trae un `ocurridoEn` más nuevo y ms-comercial la reescribe igual. El importador (`maestros:importar`, solo local y pruebas) no publica: escribe por el repositorio, no por los handlers. *Decisión del diseño.* |
| EM3 | **El sobre** es el de ED2 y ED3: `eventoId` UUID v4 aleatorio y nuevo por foto; `tipo` `maestros.socio.actualizado`; `ocurridoEn` = el `vigenteDesde` con el que se guardó el socio (en una baja, el momento de la baja), en UTC con `Z` y hasta 6 decimales; `origen` `ms-maestros`; `clave: { objeto: "socio", cardCode }`; `version: 1`; `datos: { cardCode, razonSocial, grupoEconomico, lineaDeCredito, deBaja }`. `grupoEconomico` es `{ codigo, nombre, segmento }` leído de `maestros.grupos`, o `null` si el socio no tiene grupo. `deBaja` es `dado_de_baja_el` no nula; un socio inactivo en SAP (`activo = false`) va con `deBaja: false`. | Lo fija el contrato. `activo` no viaja: ms-comercial no decide alcance, lo toma de ms-maestros (ED4). |
| EM4 | **Un socio sin línea no se publica.** Son los de la semilla y los del importador: si `DarDeBajaSocio` da de baja uno de ellos, la baja se guarda igual, sin evento, con una línea en el log. | ms-comercial exige la línea y rechazaría el evento a mensajes muertos. La ingesta siempre la trae (`f1d96aa`), así que esto solo le pasa a la semilla. |
| EM5 | **`eventos:publicar-socios`** pone en el outbox un evento por cada socio **con línea y sin baja**, con `eventoId` nuevo y `ocurridoEn` = su `vigenteDesde`, y responde cuántos encoló. Inactivos incluidos. La semilla queda fuera por EM4. | Es el paso 3 de la puesta en marcha de comercial (12.3). Con el `ocurridoEn` original, repetir lo que comercial ya tiene es inofensivo. Inactivos incluidos porque comercial guarda la réplica y no decide quién los ve; los dados de baja no, porque comercial borra sus socios en el paso 1 del 12.3 y no tiene a quién darle de baja. *Decisión del diseño.* |
| EM6 | **El consumidor es `eventos:consumir`**, en `ms-maestros-consumidor` (misma imagen), con el ciclo de `Consumidor` y `ConsumirCommand` de comercial: si la base no responde, no recibe; publica hasta 50 pendientes por vuelta, por orden de llegada; **si una publicación falla corta solo la publicación de esa vuelta** (la fila suma un intento) **y la vuelta igual recibe**; con una tanda llena, el recibir espera 1 segundo en vez de 10; espera exponencial hasta 60 s con el bus o la base caídos; SIGTERM se mira entre vueltas. **Una réplica**, mínimo y máximo. | Es ED8 y ED12, ya probados en comercial. Con una réplica no hay dos publicando el mismo outbox; si igual pasara, ms-comercial descarta por `eventoId`. |
| EM7 | **Un mensaje se procesa así:** se parsea el sobre (`Sobre` de comercial: UUID, `version` entera 1, `ocurridoEn` con zona y hasta 6 decimales); si el `eventoId` ya está en `maestros.evento_procesado`, sale como duplicado; si el `tipo` es `comercial.propiedad.actualizada`, se aplica (EM8); y se registra en `maestros.evento_procesado` (`evento_id` clave primaria, `tipo`, `resultado`, `procesado_en`) **en la misma transacción** que la propiedad. Un `tipo` desconocido, un sobre mal formado o datos inválidos **no se completan**: el mensaje vuelve, y a la quinta entrega va a mensajes muertos, donde lo ve la alerta del 13.9. Un error se registra en el log con su `eventoId`. | Es el `ProcesarEvento` de comercial (`RequiereTransaccion`). El registro es un `insert`, no un `upsert`: dos procesos con el mismo evento chocan en la clave y el segundo sale como duplicado en la entrega siguiente. |
| EM8 | **La propiedad se guarda con la foto completa:** `id_propiedad`, `nombre`, `codigo_de_socio`, `estado` y la nueva `vigente_desde` (el `ocurridoEn`, en UTC con 6 decimales). Se escribe si no existe, si su `vigente_desde` es nula (las de la semilla) o si el `ocurridoEn` es **estrictamente** más nuevo; si no, se ignora por vieja y se registra igual. Un cambio de `cardCode` la mueve de socio. Un `cardCode` que no está en la réplica se guarda igual (sin clave foránea, como hoy). `estado` es obligatorio en el evento (ED6: «el evento lo lleva siempre») y solo admite `activo` o `inactivo`; `nombre` y el id cumplen los mismos CHECK de la tabla, validados en el dominio antes de escribir, y lo que no los cumple es un dato inválido (EM7). | Es «una réplica nunca retrocede», con el reloj de comercial. Una propiedad que crea un ITR llega con 1970 y cualquier foto real la pisa (ED5). 6 decimales y no el segundo, como en los socios, porque `actualizadoEn` de Appgronomy puede traer dos cambios dentro del mismo segundo. |
| EM9 | **`activa` pasa a `estado`, en dos pasos.** Ahora: la migración agrega `estado NVARCHAR(10) NOT NULL DEFAULT 'activo'` con `CHECK (estado IN ('activo','inactivo'))`, lo completa desde `activa` y crea un índice igual a `IX_propiedad_socio` que incluye `estado` en lugar de `activa`; el código lee y escribe solo `estado`, y `EloquentPropiedadesDeSocios::activas()` pasa a `estado = 'activo'`. Después, en la entrega que borre `vista_en_importacion_el` y `habilitada_el`: se borran `activa`, su default y el índice viejo. | Lo pidió el usuario («para que no haya esa traducción»). En dos pasos porque la migración corre antes del push, mientras la revisión vieja todavía lee `activa`. |
| EM10 | **La estructura sigue viviendo en `database/sql/maestros-propiedades.sql`**: los cambios entran como bloques idempotentes al final del script (`IF COL_LENGTH(...) IS NULL ALTER TABLE ...`), una migración nueva vuelve a correrlo en SQL Server, y SQLite recibe la misma forma con Blueprint, sin los CHECK. Las tablas nuevas (`evento_saliente`, `evento_procesado`) van con Blueprint en su migración, como en comercial. | Es la regla de hoy: un solo lugar para la estructura de propiedades. El script deja de ser el lugar de la carga, pero sigue siendo el de la forma. |
| EM11 | **Las propiedades de la semilla se quedan hasta que comercial republique las suyas** (paso 2 del 12.3). Después, `maestros:propiedades-sin-evento` lista las que nunca llegaron por un evento (`vigente_desde` nula) y, con `--borrar`, las borra. Se corre desde la consola del Container App, a mano. | Sus dueños son socios de la semilla, que solo ven los tres números de prueba: no le muestran nada a nadie real. Una que comercial publique con el mismo id se reemplaza sola con su dueño verdadero. Borrarlas antes dejaría a la cuenta de prueba de la App sin propiedades sin necesidad; borrarlas después del republicado deja exactamente las que ningún evento va a corregir. No hay un evento que borre una propiedad (ED9), por eso el borrado es físico. *Decisión del diseño*; ver §9. |
| EM12 | **El script de carga se retira:** se borra `database/sql/maestros-propiedades-prueba.sql` (las 267 de Azure); queda `maestros-propiedades-ejemplo.sql`, solo para local; la regla de `CLAUDE.md` sobre propiedades se reescribe: salen de ms-comercial por el bus, y el id sigue sin reusarse ni cambiarse. | La conciliación manual termina con los eventos. |
| EM13 | **La copia del contrato** `contrato/eventos-de-dominio-v1.yaml` es idéntica a la de ms-comercial y no se edita acá: si cambia, se reemplaza. Una prueba valida contra `SocioActualizado` cada evento que ms-maestros publica (crear, reemplazar, baja, publicar todos, socio sin grupo), y que el consumidor acepta el ejemplo de `PropiedadActualizada` del contrato. | Como con los otros dos contratos: el contrato manda y queda atado a ejemplos por una prueba. |
| EM14 | **La configuración** copia los nombres de comercial, en `config/servicebus.php`: `SERVICEBUS_NAMESPACE`, `SERVICEBUS_TOPIC` (por defecto `comercial-eventos`), `SERVICEBUS_SUSCRIPCION` (`sub-ms-maestros`), `SERVICEBUS_SAS_NOMBRE` y `SERVICEBUS_SAS_CLAVE` (escucha); `SERVICEBUS_PUBLICACION_TOPIC` (`maestros-eventos`), `SERVICEBUS_PUBLICACION_SAS_NOMBRE` y `SERVICEBUS_PUBLICACION_SAS_CLAVE` (publicación); `SERVICEBUS_ESPERA_SEGUNDOS` (10). Las claves van como `secretRef` y nunca pasan por el chat. Sin namespace o sin una clave, `eventos:consumir` no arranca y lo dice. | Los mismos nombres en los dos Container Apps evitan confundirse al cargarlos. Que no arranque con la configuración incompleta es la regla de siempre: un error que se ve al encender, no a la tercera hora. |
| EM15 | **La prueba de integración** reusa el topic `pruebas-ms-comercial` y su suscripción `sub-pruebas`, con sus claves `pruebas-escucha` y `pruebas-envio` (13.5 de la guía de comercial): publicar, recibir, completar, y que un mensaje sin completar vuelva. La clave de envío del topic de pruebas la lee la prueba de `SERVICEBUS_SAS_ENVIO_NOMBRE` y `SERVICEBUS_SAS_ENVIO_CLAVE`, como en comercial, y no entra en `config/servicebus.php`: en producción no existe. Se salta sin las variables, y para darla por buena tiene que correr sin saltadas. **Nunca** contra los topics reales: vacía la suscripción antes de empezar. | No hace falta otro topic ni otras claves; ms-comercial y ms-maestros no la corren a la vez. *Decisión del diseño.* |

## 5. Dónde vive cada cosa

Siguiendo las reglas de `CLAUDE.md` (Domain y Application sin `Illuminate`; `Core` sin `Illuminate`):

- **`Maestros\Application\Eventos`:** el puerto `SalidaDeEventos` y `EventoSaliente` (EM1); el armado del sobre del
  socio (EM3), que usan los dos handlers y el comando de publicar todos; el `Consumidor` y sus puertos
  (`BuzonDeEntrada`, `PublicadorDeEventos`, `SaludDeLaBase`, `MensajeRecibido`).
- **`Maestros\Application\Ingesta`:** las peticiones que escriben sin persona (`EscrituraDeMaquina`, que
  `AlcanceTest` exige que vivan acá): procesar el evento (EM7), replicar la propiedad (EM8) y encolar todos los
  socios (EM5).
- **`Maestros\Domain\Propiedades`:** la propiedad que llega (id, nombre, socio, estado, vigencia) con las reglas
  de EM8.
- **`Maestros\Infrastructure`:** `FirmaSas`, `PublicadorServiceBusRest`, `BuzonServiceBusRest`, los comandos
  (`eventos:consumir`, `eventos:publicar-socios`, `maestros:propiedades-sin-evento`) y los repositorios Eloquent.

## 6. Lo que sale y lo que entra

```json
{
  "eventoId": "4f7c2a1e-9b3d-4e8a-a1c2-7d5e6f8a9b10",
  "tipo": "maestros.socio.actualizado",
  "ocurridoEn": "2026-10-29T14:00:00.000000Z",
  "origen": "ms-maestros",
  "clave": { "objeto": "socio", "cardCode": "10057" },
  "version": 1,
  "datos": {
    "cardCode": "10057",
    "razonSocial": "AGROPECUARIA SOGIMA SRL",
    "grupoEconomico": { "codigo": "1435", "nombre": "SOGIMA", "segmento": null },
    "lineaDeCredito": "50000.00",
    "deBaja": false
  }
}
```

Lo que entra es el `comercial.propiedad.actualizada` del contrato (ED5): `datos: { propiedadId, nombre, zona,
cardCode, estado }`, de la que ms-maestros ignora `zona`.

## 7. Pruebas

Cada una vista en rojo antes de implementar.

- **El outbox del socio:** crear, reemplazar y dar de baja dejan una fila con el sobre de EM3, que valida contra el
  contrato; uno ignorado por viejo y un no cliente nuevo no dejan nada; un socio que pasa a proveedor deja su baja;
  si algo lanza después de escribir, no queda ni el socio ni la fila; la baja de un socio sin línea se guarda y no
  deja fila.
- **El sobre:** `ocurridoEn` es el `vigenteDesde` guardado, en UTC con `Z`; un socio sin grupo va con
  `grupoEconomico: null`; un inactivo, con `deBaja: false`; cada foto, con un `eventoId` distinto.
- **`eventos:publicar-socios`:** una fila por socio con línea y sin baja; ninguna para la semilla ni para los dados
  de baja.
- **El envío:** las pruebas del consumidor de comercial, traídas tal cual (orden, tanda de 50, un fallo corta solo
  la publicación y la vuelta igual recibe, espera de 1 segundo con tanda llena, con la base caída no se publica).
- **La propiedad:** una nueva se crea con su `estado` y su vigencia; una más nueva la reemplaza, también si cambia de
  socio; una igual o más vieja se ignora; una de la semilla (vigencia nula) se reemplaza; la de un ITR (1970) se crea
  y cualquier foto real la pisa; un `eventoId` repetido no cambia nada; un `estado` que no es `activo` ni
  `inactivo`, un nombre con espacio al final o un tipo desconocido no se completan; una `inactivo` no aparece en
  `/socios/{cardCode}/propiedades` ni en `cantidadPropiedades`.
- **`maestros:propiedades-sin-evento`:** lista solo las de vigencia nula; `--borrar` borra solo esas.
- **El contrato de eventos:** EM13.
- **Integración:** EM15.

## 8. La puesta en marcha

Cada paso que escribe en Azure, con confirmación del usuario. **Se enciende apenas esté listo** (lo decidió el
usuario), sin esperar la cuenta de prueba real de la App.

1. **Antes de empezar,** comprobar en el portal que existen `maestros-eventos`, `comercial-eventos`,
   `sub-ms-comercial`, `sub-ms-maestros` (con su regla y sin `$Default`) y las claves `ms-maestros-escucha` y
   `ms-maestros-publica` (13.2 a 13.8 de la guía de comercial). Si falta `sub-ms-maestros`, lo que comercial
   publique hasta crearla se descarta; el paso 6 lo repone.
2. **La migración** (`evento_saliente`, `evento_procesado`, `propiedad.estado`, `propiedad.vigente_desde`), contra
   Azure desde la imagen local, antes del push.
3. **El push.** Desde acá, cada socio que entra por la ingesta deja su evento en el outbox, aunque nadie lo publique
   todavía.
4. **`composer test:integration`** contra `pruebas-ms-comercial` (EM15), con las claves leídas sin eco.
5. **`ms-maestros-consumidor`:** los secretos `servicebus-escucha` y `servicebus-publica`, las variables de EM14 más
   las de base de datos de la app y `LOG_CHANNEL=stderr`, el comando `php artisan eventos:consumir`, *Scale* 1 y 1.
   Comprobar: el *Log stream* sin «El bus no respondió» y `SELECT COUNT(*) FROM maestros.evento_saliente WHERE
   publicado_en IS NULL` en 0.
6. **`eventos:publicar-socios`**, una vez, cuando ms-comercial haya hecho el paso 1 del 12.3 (que borra sus socios):
   antes, lo que se publique se borra con ellos. Si la carga inicial de socios del Sincronizador entró **después**
   del paso 3, ya se publicó sola, y esto la repite sin daño.
7. **La alerta** de mensajes muertos de `comercial-eventos` (13.9 de la guía de comercial).
8. **Después del paso 2 del 12.3** (comercial republica sus propiedades): `maestros:propiedades-sin-evento`, y con
   `--borrar` si la lista es la esperada (EM11).

## 9. Lo que cambia respecto de lo hablado

Al hablarlo dije que borrar las 267 propiedades de la semilla dejaría a la cuenta de prueba de la App sin
propiedades, y el usuario lo aceptó para encender apenas esté listo. Revisándolo, no hace falta borrarlas al
encender (EM11): solo las ven los tres números de prueba, y las que comercial publique se corrigen solas. Se borran
después del republicado de comercial. Encender apenas esté listo sigue igual.

## 10. Lo que queda para quien lo construya

- Los nombres de clases, de puertos y de tablas que esta spec no fija.
- Cómo se generan el UUID v4 y el instante con 6 decimales sin `Illuminate` en `Application`.
- Si el consumidor de comercial se adapta en su lugar o se separa en partes: las pruebas de EM6 son las que mandan.
- Si `eventos:publicar-socios` encola en tandas o de una vez (son unos 2186 socios).

Fuera del alcance, por decisión del diseño: un servicio `consumidor` en `compose.yaml`. No hay bus local, así que el
consumidor se prueba con sus dobles y con la prueba de integración.
