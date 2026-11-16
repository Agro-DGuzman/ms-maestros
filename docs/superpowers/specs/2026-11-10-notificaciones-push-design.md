# ms-maestros — las notificaciones push

Fecha: 10/11/2026, **actualizada el 13/11**: el proveedor pasa a ser Firebase. Diseño completo; el enfoque lo eligió el
usuario en la conversación. Lo que cambió el 13/11 está resumido en §9.

## 1. Para qué

La App tiene una bandeja de avisos: factura nueva, pago, reporte de campo. La bandeja la arma ms-comercial y ya
funciona, pero **no llega nada al teléfono**: ningún servicio manda push. El usuario quiere notificaciones push, y que
cada persona elija de qué categorías las recibe.

Lo que dijo el usuario:
- Son **notificaciones push** al teléfono, **por Firebase Cloud Messaging** (13/11). Lo eligió frente a Azure
  Notification Hubs por flexibilidad: cada envío dice qué pasó con ese teléfono, no hay encierro con un proveedor y es
  gratis.
- **Cada persona elige** qué recibe, con los cuatro interruptores de *Preferencias de avisos*: facturas, pagos,
  reportes y catálogo.
- **Una categoría apagada corta solo el push.** El aviso sigue apareciendo en la bandeja.
- **El aviso de catálogo sale cuando se agrega un producto nuevo.**
- **Las push salen de ms-maestros** (opción 1). ms-comercial anuncia por el bus cada aviso que nace y cada cambio de
  preferencias, y ms-maestros decide a quién le llega y lo manda.
- **Cada aviso es su propia notificación** (13/11): diez facturas que entran juntas son diez notificaciones, no una que
  pisa a las otras.

## 2. Objetivos, no objetivos y lo que lo haría un fracaso

**Objetivos**
- Que cada aviso nuevo de la bandeja llegue como push a los teléfonos de las personas que ven ese socio, si tienen
  prendida su categoría.
- Que un producto nuevo del catálogo deje un aviso para todos, en la bandeja y como push.
- Que el login registre el teléfono y el logout lo dé de baja, como dice el contrato.
- Que el teléfono pueda cambiar su token sin cerrar la sesión, porque Firebase lo rota.

**No objetivos**
- Avisos de vencimiento: nada los genera hoy, y esta entrega no los crea.
- El push del estado de cuenta en PDF: la App ya consulta cada 5 segundos. (Se reconsideró el 13/11 y sigue afuera.)
- Avisos por WhatsApp o correo.
- Agrupar varios avisos en una sola notificación, ni varios productos nuevos en un solo aviso. **Consecuencia aceptada:**
  si SAP agrega muchos artículos a la vez —una temporada de semillas, por ejemplo—, cada uno es un aviso y un push para
  todos. Lo que sí no puede pasar es que se anuncien productos que ya estaban (§2 y NP12).
- Horarios de silencio: un aviso que nace de madrugada se manda de madrugada.
- Azure Notification Hubs, ni ningún intermediario entre ms-maestros y Firebase.

**Sería un fracaso, aunque funcione:**
- **Que un push le llegue a quien no ve ese socio.** Un teléfono que cambió de persona, una sesión vencida o un contacto
  que SAP dio de baja no pueden recibir avisos ajenos.
- **Que lleguen pushes viejos en tanda.** Si el bus estuvo caído cuatro días, como pasó en octubre, al volver no puede
  sonar el teléfono por cosas de la semana pasada.
- **Que un reintento repita un push** a quien ya lo recibió.
- **Que push y bandeja no coincidan**: un push sin su aviso en la bandeja, o con otro texto.
- **Que una carga masiva dispare cientos de avisos de catálogo** por productos que ya estaban.
- **Que un token push quede escrito en un log.** Es un identificador del teléfono de una persona.

## 3. Cómo se construye: lo que se consideró

**Quién manda los push** (10/11):

| Opción | A favor | En contra |
|---|---|---|
| **1. Las push salen de ms-maestros** (elegida) | Quién ve cada socio y los teléfonos ya están acá, siempre al día. Sigue el camino de las propiedades: ms-comercial anuncia por el bus y ms-maestros reacciona. | Las preferencias quedan copiadas en ms-maestros. |
| 2. Las push salen de ms-comercial | Las preferencias no se copian. | ms-comercial necesitaría saber qué personas ven cada socio, y hoy solo lo pregunta con el token de una persona. |
| 3. Guardar junto a cada teléfono sus socios y preferencias | Nadie consulta a nadie al mandar. | Los datos se desactualizan con cada cambio de contactos en SAP, y hay que sincronizarlos. |

**El proveedor** (13/11; el 10/11 se había elegido Notification Hubs):

| Opción | A favor | En contra |
|---|---|---|
| **Firebase Cloud Messaging, HTTP v1** (elegida) | Gratis y sin tope de dispositivos. Cada envío responde qué pasó con ese token. Sin encierro: los tokens son nuestros. | Los tokens los guarda y los limpia ms-maestros, y un envío es un pedido por teléfono. La credencial es una cuenta de servicio de Google. |
| Azure Notification Hubs | El hub guarda los tokens, los limpia y reparte a varios teléfonos en una llamada. Firma con SAS, como el bus. | Saber qué teléfono falló y por qué es solo del plan Standard (US$200/mes). El plan gratuito tiene tope de 500 dispositivos. Los tokens quedan en el hub. Android igual necesita Firebase. |

**Cómo se hace el envío** (13/11):

| Opción | A favor | En contra |
|---|---|---|
| **El consumidor encola un trabajo por teléfono y el `worker` envía** (elegida) | Un Firebase lento no traba al consumidor, que también aplica propiedades. Cada teléfono se reintenta solo, sin repetirle el push a los demás. Reusa el `worker` y la cola que ya mandan el código por WhatsApp. | Un tipo de trabajo más, y unos segundos de demora por la cola. |
| El consumidor manda directo al procesar el evento | La menor cantidad de piezas. | El consumidor es de una réplica: un Firebase lento frena las propiedades. Un fallo a mitad de camino, al reintentar el evento, repite el push a los que ya lo recibieron. |
| Una tabla de pushes pendientes que vacía el consumidor | El mismo patrón que el outbox de socios. | Reescribe los reintentos que la cola ya tiene, y el consumidor sigue llamando a Firebase. |

## 4. Decisiones

| # | Decisión | Por qué |
|---|---|---|
| NP1 | **El proveedor es Firebase Cloud Messaging (API HTTP v1)**, con un proyecto de Firebase. Android va directo; iOS va por APNs, con la clave `.p8` de Apple cargada una vez en el proyecto de Firebase. El backend nunca habla con Apple. *(Cambió el 13/11; antes era Notification Hubs.)* | Lo eligió el usuario por flexibilidad (§1). |
| NP2 | **El login manda el token push del teléfono, y ms-maestros lo guarda con la sesión.** El bloque `dispositivo` suma `tokenPush`, el token de registro de Firebase en las dos plataformas (en iOS **no** el de Apple). **Requiere cambiar el contrato** (§10). **`tokenPush` es opcional:** si el bloque llega sin él, el login entra y responde `dispositivoRegistrado: false`. | El contrato ya lo dice: el login «es el único punto de registro del token push», pero el esquema no trae el token, y sin él el servidor no puede mandar nada. Si fuera obligatorio, una versión de la App ya instalada que manda el bloque sin token recibiría un 400 y no podría entrar. |
| NP3 | **Registrar es guardar el token: el login no llama a Firebase.** `dispositivoRegistrado` es `true` si la sesión quedó con token. *(Cambió el 13/11: con el hub, el login registraba la instalación en el proveedor y podía fallar.)* | Un token se prueba al mandarle algo; el login no tiene por qué esperar a nadie. |
| NP4 | **Una instalación es de la última sesión que la registró.** Si alguien inicia sesión en el mismo teléfono —otra persona o la misma—, toda otra sesión abierta con ese `instalacionId` pierde el dispositivo y el token. | Si no, en un teléfono compartido le llegarían a una persona los avisos de otra. Hoy el login no lo hace: abre la sesión nueva y deja la anterior con el mismo `instalacionId`. |
| NP5 | **El logout borra el token** de esa sesión, además de cerrarla. | El contrato dice que el logout «desregistra el dispositivo de notificaciones». |
| NP6 | **Un push va solo a los tokens de sesiones abiertas.** Una sesión cerrada o vencida no recibe nada, aunque conserve el token. | La tabla de sesiones decide quién recibe. |
| NP6b | *(Retirada el 13/11.)* Con el hub hacía falta un comando para borrar las instalaciones de sesiones vencidas, por el tope de 500 dispositivos. Con Firebase no hay tope ni nada que borrar afuera. | — |
| NP7 | **Los destinatarios se calculan desde los teléfonos, no desde el socio.** Se toman las personas con una sesión abierta con token, y a cada una se le pregunta su contexto por `DirectorioDeContactos::contexto()`. Recibe si el socio del aviso está entre los suyos; para catálogo, si entra a la App. **Coincide con la bandeja:** ms-comercial guarda el aviso por socio y lo filtra al leer, con los socios que alcanza cada persona (`EloquentBandejaDeAvisos::pagina()`). Verificado el 13/11. | Son pocas personas, y el alcance queda calculado en un solo lugar. Así no hay una segunda versión de la regla de visibilidad y del grupo económico. |
| NP8 | **Las preferencias se copian por evento.** ms-comercial publica `comercial.preferencias.actualizadas` con la persona, los cuatro interruptores y el `ocurridoEn`, y ms-maestros guarda una fila por persona. Una foto vieja no pisa una nueva, con la misma regla que las propiedades. Sin fila, todo está prendido, igual que en ms-comercial. | Es la copia mínima para filtrar sin preguntarle a ms-comercial en cada envío. |
| NP9 | **Cada aviso nuevo llega por el evento `comercial.aviso.creado`.** Lo publica ms-comercial por su outbox, en la misma transacción que crea el aviso. Trae el id del aviso, la categoría, el socio (vacío si es para todos), el título, el cuerpo y el `creadoEn`. | El texto del push es el de la bandeja, y lo escribe un solo servicio. |
| NP10 | **El catálogo también pasa por la bandeja.** ms-maestros publica `maestros.producto.nuevo`; ms-comercial crea el aviso para todos y publica su `comercial.aviso.creado`; ms-maestros manda el push con eso. | Así todo push nace de un aviso de la bandeja, con el mismo id y el mismo texto, y hay un solo camino de envío. |
| NP11 | **Un producto es nuevo la primera vez que la App lo ve**: activo, sin baja, con código de artículo y con categoría. Una columna `anunciado_el` en `maestros.producto` lo marca una sola vez, en la misma transacción de la ingesta que lo vuelve visible, y el evento sale por el outbox. Un producto que vuelve de una baja no se anuncia. | La visibilidad solo cambia por la ingesta: el back-office toca la descripción y los enlaces, que no la deciden. |
| NP12 | **La migración marca como anunciados todos los productos que ya existen, visibles o no.** Solo se anuncia un artículo que llega por primera vez después de la migración. *(Ampliada el 13/11: antes marcaba solo los visibles.)* | Si no, el primer envío de SAP anunciaría los 226 productos de la carga inicial. Y uno de los 5 inactivos que se reactive, o uno que reciba su categoría, se anunciaría como nuevo sin serlo. |
| NP13 | **Un aviso viejo no se manda por push**: queda solo en la bandeja. Se mira al recibir el evento **y otra vez antes de cada envío**, así un reintento tardío tampoco lo manda. Un aviso es viejo si se cumple cualquiera de estas dos condiciones: **su `creadoEn` tiene más de 24 horas**, o **su `fechaDelDocumento` es anterior a ayer** en hora de Bolivia. Con `fechaDelDocumento` en `null` (el catálogo, o un informe sin fecha de visita) vale solo la primera. *(Corregida el 16/11: el `creadoEn` no es la fecha del documento sino el instante en que el Sincronizador leyó SAP, según ms-comercial; y `fechaDelDocumento` entró a pedido de ms-maestros.)* | Las 24 horas frenan un bus o un Firebase caídos. La fecha del documento frena lo que las 24 horas no ven: un atraso del Sincronizador o una carga masiva, cuyos avisos nacen con la hora de la lectura. **Consecuencia aceptada:** una factura cargada en SAP con fecha de hace más de un día queda solo en la bandeja. |
| NP14 | **El envío va encolado, un trabajo por teléfono.** Al procesar `comercial.aviso.creado`, el consumidor calcula los destinatarios, registra el evento y encola un trabajo por cada token, todo en la misma transacción. El `worker` los corre. *(Cambió el 13/11: antes era un trabajo por aviso.)* | El consumidor es de una réplica y no debe trabarse esperando a Firebase. Firebase manda de a un token por pedido: con un trabajo por aviso, reintentarlo le repetiría el push a quien ya lo recibió. |
| NP15 | **Un driver `log` para local**, como WhatsApp: `PUSH_DRIVER=log` escribe en el log lo que se mandaría, sin el token. `firebase` es el real. | Se prueba todo sin Firebase y sin teléfono. |
| NP16 | **Si Firebase rechaza un envío, el log guarda su motivo**: el estado HTTP, el código de error de Firebase (`UNREGISTERED`, `INVALID_ARGUMENT`…) y el mensaje. **Nunca se escribe el token push**, ni en el log ni en el contexto de un trabajo fallido. | Lo que aprendimos con el bus y con WhatsApp: un rechazo sin motivo obliga a adivinar. |
| NP17 | **El consumidor despacha por tipo a quien corresponde.** Hoy `ProcesarEventoHandler` vive en Maestros y conoce un solo tipo. Los dos tipos nuevos los atiende Identidad, y Maestros no puede referenciar Identidad. El despacho por tipo pasa al host (`app/`) o a `Core`, y cada módulo registra sus tipos. | Respeta la regla de módulos de `EstructuraTest`. |
| NP18 | **Un endpoint para cambiar el token sin cerrar la sesión.** Con la persona autenticada, recibe el `instalacionId` y el `tokenPush` nuevo, y lo guarda en la sesión abierta de esa persona con esa instalación. Si esa persona no tiene una sesión abierta con esa instalación, responde 404. **Requiere cambiar el contrato** (§10). *(Nueva el 13/11.)* | Firebase rota el token, y el contrato pedía volver a iniciar sesión para registrarlo: hasta entonces, esa persona dejaba de recibir avisos sin que nadie lo notara. También sirve para quien acepta las notificaciones después de entrar. |
| NP19 | **El bloque `dispositivo` se manda siempre en el login**, haya o no permiso de notificaciones; `tokenPush` va solo si lo hay. **Requiere cambiar el texto del contrato**, que hoy dice «omitir si el usuario no concedió permiso». *(Nueva el 13/11.)* | NP18 busca la sesión por su `instalacionId`. Una sesión abierta sin dispositivo no se puede encontrar, y quien acepta después no tendría cómo registrar su token. |
| NP20 | **Qué hacer con cada respuesta de Firebase:** <br>• `UNREGISTERED` (404): el token ya no sirve. Se borra de esa sesión y no se reintenta. <br>• `INVALID_ARGUMENT` (400): no se reintenta. **No se borra el token**, porque puede ser un error nuestro en el mensaje. <br>• `SENDER_ID_MISMATCH` (403) y `THIRD_PARTY_AUTH_ERROR` (401): es configuración —un token de otro proyecto, o la clave de Apple—. No se reintenta ni se borra, y se registra como error. <br>• `QUOTA_EXCEEDED` (429): se reintenta, esperando al menos un minuto la primera vez. <br>• `UNAVAILABLE` (503) e `INTERNAL` (500): se reintenta con espera creciente, respetando `Retry-After`. | Es lo que indica la referencia de errores de FCM v1. Borrar el token ante un 400 dejaría sin avisos a quien no tiene la culpa. |
| NP21 | **La credencial es la cuenta de servicio del proyecto de Firebase** (un JSON), como secreto **solo en el `worker`**, que es el único que llama a Firebase. El token de acceso de Google se obtiene firmando un JWT con `firebase/php-jwt`, que ya es dependencia, y se guarda en el caché hasta poco antes de vencer. | El login ya no llama al proveedor (NP3), así que `ms-maestros` no necesita la credencial. No suma una librería para algo que es un pedido HTTP. |
| NP22 | **El trabajo lleva el id de la sesión y del aviso, no el token.** Antes de enviar vuelve a leer la sesión: si se cerró, si venció, si perdió el dispositivo o si la persona apagó esa categoría mientras tanto, no manda. El token es el que la sesión tenga en ese momento. *(Nueva el 13/11; la propuso el revisor.)* | El token no queda en la tabla de trabajos fallidos (NP16). Y un token que la App cambió por NP18 mientras el trabajo esperaba se usa al día. |
| NP23 | **Los pushes van en su propia cola**, separada de la del código por WhatsApp. *(Nueva el 13/11; la propuso el revisor.)* | Cien avisos de catálogo no pueden demorar el código de alguien que está esperando para entrar. |

## 5. Dónde vive cada cosa

- **Identidad:**
  - el registro del token en el login, su cambio por el endpoint de NP18 y su baja en el logout;
  - la desvinculación de NP4;
  - la copia de las preferencias;
  - el cálculo de destinatarios;
  - el trabajo de envío;
  - el puerto del proveedor de push.
- **Maestros:** `anunciado_el` y la publicación de `maestros.producto.nuevo` desde la ingesta de productos.
- **El host (`app/`):** el adaptador de Firebase y el despacho de eventos por tipo (NP17).
- **Cambios en la base:**
  - `identidad.sesiones` suma el token push y cuándo se actualizó (13/11; con el hub no hacía falta);
  - `identidad.preferencias_de_push`: una fila por persona, con los cuatro interruptores y la vigencia;
  - `maestros.producto.anunciado_el`.
- **Configuración:** el driver, el id del proyecto de Firebase y la cuenta de servicio. Va solo en el `worker` (NP21).

## 6. Lo que sale y lo que entra

| Evento | Quién lo publica | Quién lo escucha | Datos |
|---|---|---|---|
| `comercial.aviso.creado` | ms-comercial, en la transacción del aviso | ms-maestros | id del aviso, categoría, `cardCode` o vacío, título, cuerpo, `creadoEn` |
| `comercial.preferencias.actualizadas` | ms-comercial, al cambiar las preferencias | ms-maestros | persona (`n-591…`), los cuatro interruptores, `ocurridoEn` |
| `maestros.producto.nuevo` | ms-maestros, en la transacción de la ingesta | ms-comercial | `itemCode`, nombre, categoría, `ocurridoEn` |

**El contrato de eventos es de ms-comercial** (`contrato/eventos-de-dominio-v1.yaml`, copia idéntica que acá no se
edita). Los tres tipos se agregan allá y se copian acá.

**La forma que se propone** (13/11), para que ms-comercial la adopte en su contrato. Sigue el sobre de la versión 1
(ED2). Un campo anulable se escribe con `oneOf` y `type: 'null'`, como el resto del contrato, y viaja como `null`, no
ausente.

```yaml
# comercial.aviso.creado — no es una foto: es un hecho, y `eventoId` lo deduplica.
clave: { objeto: aviso, avisoId }
datos:
  avisoId:   string            # el id de ms-comercial, p. ej. AV-F-12345
  categoria: enum [factura, vencimiento, pago, reporte_campo, catalogo]   # las de CategoriaDeAviso
  cardCode:  CardCode | null   # null: aviso para todos (catálogo)
  titulo:    string, 1..65
  cuerpo:    string, 1..240
  creadoEn:  date-time         # el instante en que el Sincronizador leyó SAP; igual al ocurridoEn del sobre
  fechaDelDocumento: date | null   # en hora de Bolivia; null en catálogo y en un informe sin fecha (NP13, 16/11)

# comercial.preferencias.actualizadas — foto completa; `ocurridoEn` ordena (NP8).
clave: { objeto: preferencias, persona }
datos:
  persona:   string            # n-591…, el mismo id de la bandeja de ms-comercial
  facturas:  boolean           # gobierna factura y vencimiento
  pagos:     boolean           # pago
  reportes:  boolean           # reporte_campo
  catalogo:  boolean           # catalogo

# maestros.producto.nuevo — un hecho; sale una sola vez por producto (NP11).
clave: { objeto: producto, itemCode }
datos:
  itemCode:        string
  nombre:          string
  categoria:       { codigo: string, nombre: string }   # el código para filtrar, el nombre para el texto
```

Los largos de `titulo` y `cuerpo` son para que el mensaje entre holgado en el límite de 4 KB de Firebase y se lea en
una notificación; ms-comercial puede elegir otros si sus textos los necesitan.

**Lo que se propone para el contrato de la App** (13/11), para cambiarlo en el visor:
- `RegistroDispositivo` suma `tokenPush`: string, opcional.
- La descripción del bloque `dispositivo` pasa a decir que se manda siempre, y el token solo con permiso (NP19).
- La operación de NP18: `PUT /dispositivos/{instalacionId}/token`, con `{ tokenPush }`, autenticada; responde 204, o 404
  `DISPOSITIVO_NO_ENCONTRADO` si esa persona no tiene una sesión abierta con esa instalación. La ruta y el código
  los fija el visor.

**El orden del trabajo empieza por los contratos:** `ContratoTest` impide exponer en `/v1` lo que el contrato no
tenga, y los traductores validan contra la forma de cada evento.

**Un tipo desconocido termina en mensajes muertos.** Por eso cada servicio despliega primero el soporte para escuchar un
tipo, y recién después el otro lo publica.

**Las suscripciones filtran por tipo, y hay que ampliarlas** (13/11). Hoy `sub-ms-maestros` deja pasar
`tipo LIKE 'comercial.propiedad.%'` y `sub-ms-comercial` deja pasar `tipo LIKE 'maestros.socio.%'`. Si no se amplían,
los eventos nuevos se descartan sin llegar y sin error. Cada regla se amplía con los tipos nuevos nombrados uno por
uno, nunca con un comodín como `comercial.%`: un tipo que el otro servicio agregue después iría a mensajes muertos.

**El push que manda ms-maestros** lleva el título y el cuerpo del aviso como notificación, y en los datos el id del aviso,
la categoría y el `cardCode` si lo hay, para que la App abra la pantalla que corresponde.

## 7. Lo que tiene que cambiar en ms-comercial

Lo hace la sesión de ms-comercial; esta entrega depende de ello.
1. **Apagar una categoría deja de ocultar avisos en la bandeja.** Hoy, según su decisión A9, los oculta desde ese
   momento.
2. **Avisos para todos**, sin socio, para el catálogo. Los crea al recibir `maestros.producto.nuevo`.
3. **Publicar `comercial.aviso.creado` y `comercial.preferencias.actualizadas`**, y una vez las preferencias que ya
   existen. Sin esa publicación inicial, ms-maestros toma como prendido todo lo que alguien ya había apagado.
4. **Agregar los tres tipos** al contrato de eventos.
5. **Escribir el título y el cuerpo sin importes** (13/11): el push se ve con el teléfono bloqueado. «SOGIMA tiene una
   factura nueva», y el detalle adentro de la App.

## 8. Pruebas

- **Unitarias:**
  - destinatarios: una persona que no ve el socio, una que tiene la categoría apagada, una sesión vencida, una
    instalación que pasó a otra persona, una sesión sin token y un aviso de más de 24 horas;
  - la regla de «primera vez visible»;
  - el adaptador de Firebase con `Http::fake`: cada respuesta de NP20, el token de acceso en caché y que el token push
    no aparezca en lo registrado.
- **De funcionalidad:**
  - el login con y sin `tokenPush`, y la desvinculación de NP4 con otra persona y con la misma;
  - el logout borrando el token;
  - el endpoint de NP18: la sesión propia, la instalación de otra persona (404) y una sesión cerrada (404);
  - la ingesta de un producto nuevo, con su evento en el outbox, y la de uno que vuelve de una baja, sin evento;
  - el consumidor con los dos tipos nuevos, incluido un duplicado, y que encola un trabajo por token;
  - el trabajo: que no reintenta un 404 y borra el token, que reintenta un 503, y que no manda un aviso que pasó las
    24 horas mientras esperaba.
- **Contrato:** `ContratoDeRespuestasTest` con el campo nuevo del login y la operación de NP18, y los esquemas de los
  tres eventos.
- **Manual:** `tests/Manual/probar-push.php <tokenPush>` manda un push de prueba con la cuenta real e imprime lo que
  respondió Firebase, como `probar-whatsapp.php`. **Que Firebase acepte no es que llegue**: la prueba es que aparezca en
  el teléfono.

## 9. Lo que cambia respecto de lo hablado

- **NP10 (10/11):** en la conversación, el push de catálogo lo mandaba ms-maestros directamente. Pasa por la bandeja de
  ms-comercial, por la razón de NP10. Llega unos segundos más tarde.
- **NP2 (10/11):** el contrato tiene que sumar el token push. Sin eso no hay forma de mandar nada.
- **El 13/11 el usuario eligió Firebase en lugar de Notification Hubs**, y eso cambió:
  - NP1, el proveedor;
  - NP2 y NP3: ms-maestros guarda el token, y el login ya no llama al proveedor;
  - NP14: un trabajo por teléfono en lugar de uno por aviso;
  - NP6b se retira, y entran NP20 (las respuestas de Firebase) y NP21 (la credencial);
  - la configuración pasa a estar solo en el `worker`.
- **Entraron el 13/11, sin relación con el proveedor:** NP18 (cambiar el token sin cerrar sesión), NP19 (el bloque
  `dispositivo` siempre), NP13 mirado también al enviar, y la regla de las suscripciones en §6.
- **Después de la revisión del 13/11:** NP12 marca todos los productos que existen y no solo los visibles; NP22 (el
  trabajo lleva la sesión y no el token) y NP23 (una cola propia); la forma propuesta de los eventos y del contrato
  en §6; y en NP7 y NP13, lo verificado en el código de ms-comercial.
- **El push del estado de cuenta se reconsideró el 13/11** y sigue afuera, por la misma razón del 10/11.
- **El 16/11, con la respuesta de ms-comercial**
  (`ms-comercial/docs/superpowers/handoffs/2026-11-16-respuesta-de-ms-comercial-push.md`):
  - NP13 se corrige: el `creadoEn` del aviso es la hora de lectura del Sincronizador, no la del documento.
  - El usuario pidió sumar `fechaDelDocumento` al evento para frenar los atrasos.
  - Su contrato de eventos ya trae los tres tipos, y se copia tal cual. Detalles:
    - `avisoId` hasta 60 caracteres;
    - `cardCode` en `null` en el catálogo;
    - los textos se cortan con «…».

## 10. Lo que tiene que decidir alguien más

- **El contrato de la App** (`agropartners-api-v1.yaml`), que se cambia en el visor y se reemplaza el archivo:
  - sumar `tokenPush` a `RegistroDispositivo` (NP2);
  - la operación de NP18, con su ruta y su forma;
  - el texto del bloque `dispositivo`: se manda siempre, con el token solo si hay permiso (NP19).
- **El equipo de la App:**
  - integrar Firebase Messaging en Android **y en iOS**, y mandar el token de Firebase en las dos;
  - llamar a NP18 cada vez que Firebase le da un token nuevo;
  - qué pantalla abre cada categoría al tocar la notificación.
- **Quien administre las cuentas:** crear el proyecto de Firebase, cargarle la clave `.p8` de Apple, y generar la cuenta
  de servicio para el `worker`.

## 11. El orden del despliegue

Cada paso que escribe en Azure, con confirmación del usuario.

1. **Los contratos:** el de la App en el visor y el de eventos en ms-comercial.
2. **ms-comercial entiende `maestros.producto.nuevo`**, y recién entonces se amplía la regla de `sub-ms-comercial`.
3. **ms-maestros:** la migración contra Azure antes del push (el token en las sesiones, las preferencias,
   `anunciado_el` con la marca de NP12), y después el push. Desde acá entiende los dos tipos nuevos, guarda tokens y
   publica productos nuevos. El `worker` sigue con `PUSH_DRIVER=log`.
4. **Se amplía la regla de `sub-ms-maestros`** con los dos tipos de ms-comercial.
5. **ms-comercial empieza a publicar** avisos y preferencias, y publica una vez las preferencias existentes.
6. **Firebase:** el proyecto, la clave de Apple y la cuenta de servicio como secreto en el `worker`, con
   `PUSH_DRIVER=firebase`.
7. **La versión de la App** que manda el token.

Hasta el paso 6 los pushes se escriben en el log del `worker` en lugar de mandarse: todo el camino se puede verificar
antes de que suene un teléfono.

## 12. Lo que queda para quien lo construya

- La forma exacta del despacho por tipo (NP17), siempre que Maestros no conozca a Identidad.
- Los nombres de clases, columnas y variables de entorno, siguiendo los de WhatsApp y el bus.
- La cantidad de reintentos y la espera entre ellos, dentro de lo que pide NP20 y sin pasar las 24 horas de NP13.
- Los detalles del mensaje de Firebase que no cambian el comportamiento: el canal de Android, la prioridad, el sonido.
- Si se limpian los tokens de las sesiones vencidas. No se usan para mandar (NP6); se borrarían solo por no guardar un
  dato que no hace falta.
- El status de NP18 cuando la instalación no es de la persona, si el contrato elige otro que 404.
- Dónde registra Identidad los tipos de evento que atiende, dentro de lo que pide NP17.
