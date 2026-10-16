# Ingesta de productos desde SAP · diseño

**Fecha:** 15 de octubre de 2026 · **actualizado el 16** con las respuestas
del equipo del Sincronizador
**Cambia:** la regla de `CLAUDE.md` «el catálogo de productos no es réplica de
SAP». Pasa a ser réplica **en parte**: SAP manda la ficha y Agropartners
conserva la descripción y los cuatro enlaces.
**Se apoya en:** `2026-10-05-ingesta-de-socios-design.md`, del que copia la
seguridad, la idempotencia y la regla de vigencia.

## Para qué

Hoy el catálogo se cargó a mano desde la web: 90 productos, con fichas
incompletas (formulación y dosis en blanco en la mayoría) y un solo código por
producto aunque tenga varias presentaciones. SAP tiene la ficha de cada
artículo, y el Sincronizador ya sabe llevar cambios de SAP a ms-maestros. Con
esto, el catálogo de la App sigue a SAP en segundos, igual que los socios.

**Éxito:**
- Un artículo que cambia en SAP se ve cambiado en `GET /productos/{itemCode}`
  en el mismo tiempo que hoy tarda un socio.
- Lo que el área cargó desde el back-office (descripción, imagen, ficha
  técnica, hoja de seguridad, registro sanitario) **nunca** lo pisa un envío
  de SAP.
- Un artículo inactivo en SAP desaparece de la App, y vuelve con su ficha y
  sus enlaces intactos cuando SAP lo reactiva.
- Terminada la carga inicial, la App no muestra ningún producto que no haya
  llegado de SAP.

## Decisiones

| # | Decisión | Quién | Por qué |
|---|---|---|---|
| P1 | **Cada artículo de SAP es un producto**, identificado por su `ItemCode` (`codigo_articulo`). Una tarjeta por presentación: «ALVO - 5 L» y «ALVO - 1 L» son dos productos | Usuario | Es lo que manda el Sincronizador, y el contrato de la App no cambia (`presentacion` es un solo texto) |
| P2 | **La dosis es texto** y se guarda y muestra tal cual. No se asocia a ningún cultivo | Usuario | Nada asegura qué dosis corresponde a qué cultivo |
| P3 | **`activo` va en el cuerpo** y cubre lo que va y vuelve: un artículo inactivo llega como `PUT` con `activo: false`. **El `DELETE` es la baja lógica**, con la vigencia fijada en su momento, igual que en los socios | Usuario | Un artículo inactivo un tiempo tiene que volver sin perder lo que cargó el área |
| P4 | **La categoría se toma por código.** Si el código ya existe, su nombre no se toca; si es nuevo, se crea con el nombre que manda SAP | Usuario | Que «INSECTICIDAS» no pise el «Insecticidas» que hoy muestra el chip |
| P5 | **La descripción es solo del back-office.** El Sincronizador no la manda | Usuario | La de SAP repetía ingrediente y presentación |
| P6 | **Los demás textos se muestran tal como vienen de SAP**, mayúsculas incluidas. Si molestan, se corrigen en SAP | Usuario | Corregirlos en ms-maestros rompe siglas (SC, SENASAG) |
| P7 | **ms-maestros guarda todo artículo que le llega**, y la App muestra los activos, sin baja y con categoría. Qué artículos manda es decisión de SAP y del Sincronizador (sección «Lo que manda el Sincronizador») | Mía, no objetada | ms-maestros no tiene cómo saber qué es «de catálogo» en SAP |
| P8 | **Al terminar la carga inicial, los productos que nunca llegaron de SAP se dan de baja** con un comando, a mano y con confirmación | Usuario (la baja) · mía (el comando) | ms-maestros no puede saber cuándo terminó la carga |
| P9 | **Un dato vacío de SAP no borra nada.** La ingesta escribe solo los campos que traen valor; uno ausente, `null`, vacío o una lista vacía deja la columna como estaba | Usuario | Conservar la ficha cargada desde la web hasta conciliar los datos más adelante |
| P10 | **Los 14 productos de la web con varias presentaciones bajo un código** (Alvo, Abamex Plus, Charter 240 EC, Concert, Invictus y nueve más) conservan sus enlaces en la presentación que SAP tenga con ese código. **El área carga a mano los de las demás presentaciones**, unas 20 | Usuario | Son pocos, y el back-office ya filtra los productos sin imagen |
| P11 | **Las tres operaciones de los socios:** `POST` para agregar un producto nuevo, `PUT` para reemplazar y `DELETE` para la baja | Usuario | Tiene que haber una operación para agregar productos nuevos, y el Sincronizador ya hace `POST` → 409 → `PUT` con los socios. Es también la ruta que el Sincronizador supuso |

## Lo que manda el Sincronizador

Respuestas del equipo del Sincronizador, 16 de octubre. Son datos de SAP, no
decisiones de este diseño.

- **Qué artículos.** Todo artículo de venta (`SellItem = 'Y'`) cuyo grupo
  pertenece a la unidad de negocio AGR, FERT o SEM (`OITB.U_LBA_NR1`), esté
  activo o no. Hoy son **231**: 202 AGR, 6 FERT y 23 SEM, de los que 226 están
  activos. Un artículo que deja de cumplir el criterio (deja de ser de venta,
  cambia de grupo o se borra) recibe un `DELETE`.
- **`activo`** es `validFor = 'Y'` y `frozenFor = 'N'`, la misma regla que el
  socio. En `AGRO_P6` los dos campos se mueven juntos y ningún artículo usa
  vigencia por fechas.
- **El código de categoría** es el nombre del grupo (`OITB.ItmsGrpNam`) sin
  tildes, en minúsculas y con guiones. No usan el número `ItmsGrpCod` porque
  SAP lo numera distinto en cada empresa. Los seis códigos de hoy coinciden
  exactamente, así que ninguna categoría actual queda vacía.
- **La ficha** sale de campos de usuario de `OITM`: ingredientes y
  concentraciones (`U_ING_ACT1–10`, `U_CONCENT_ING1–10`), cultivos y dosis
  (`U_CULTIVO1–8`, `U_DOSIS1–8`), registro (`U_NRO_REG1`), formulación
  (`U_TIPO_FORM`) y presentación (`U_CANT_PRES` + `U_UNI_MED_PRES`). Editarla
  es actualizar el artículo, así que genera su evento.
- **`registro.entidad`** es siempre SENASAG: el campo de SAP es el número de
  registro SENASAG. Solo los AGR tienen registro (121 de 202); FERT y SEM
  viajan con `registro: null`. No mandan el segundo registro ni las fechas de
  vencimiento; si hacen falta, hay que pedirlos.
- **`eventoId`** es el `U_EVENTO_ID` que asigna SAP desde la secuencia
  `AGRO_EVENTO_SALIDA_S`, el mayor del grupo. El `0` del ejemplo salió del
  modo de prueba `VerProducto`, que arma un cuerpo sin evento.
- **Ningún `ItemCode` tiene `/`.** Los 553 de `OITM` usan letras, números,
  punto, guion o guion bajo. Igual el Sincronizador codifica la ruta, pero
  Laravel decodifica antes de comparar con la ruta, así que un `/` codificado
  tampoco entraría: no hace falta cambiar nada mientras no existan.
- **Las presentaciones.** Cada una es un artículo con su `ItemCode`, y el
  error estaba en el catálogo de la web, no en SAP. El Alvo es `10180` (5 L),
  `10198` (1 L) y `10199` (0.25 L). Para los demás productos de P10,
  `sql/catalogo-productos.sql` del repositorio del Sincronizador lista los 231
  con el código, el nombre y la presentación tal como se envían.

**Tres cambios en SAP no generan evento del artículo** y llegan recién con el
próximo cambio de cada artículo: el nombre de un grupo, la unidad de negocio de
un grupo y la descripción de una formulación (`UFD1`). Se acepta, igual que con
`@GRUPOECO_SEGMENT` en los socios. Dos consecuencias:
- **Renombrar un grupo cambia su código**, porque el código sale del nombre.
  Los productos pasan a una categoría nueva de a uno, a medida que cambian, y
  mientras tanto el catálogo queda repartido entre las dos. Hay que avisar al
  área de SAP que renombrar un grupo tiene ese efecto.
- **Un grupo que cambia de unidad de negocio** sigue visible hasta que cada
  artículo cambie y el Sincronizador le mande el `DELETE`.

## Alcance

**Adentro:**
- `POST /ingesta/v1/productos`, y `PUT` y `DELETE /ingesta/v1/productos/{itemCode}`,
  con la seguridad y la idempotencia de `/ingesta`, y su lugar en la copia
  enmendada del contrato.
- Las columnas nuevas del producto: vigencia, baja y origen.
- El campo descripción en el formulario de `/admin/productos`, con su asiento
  en la bitácora del catálogo.
- El comando de la transición.

**Afuera:**
- Agrupar presentaciones en un producto comercial (descartado en P1).
- Que la ingesta escriba la descripción, la imagen o los documentos, aunque
  vengan en el cuerpo.
- Editar categorías desde el back-office. Una categoría nueva que crea SAP
  queda con el nombre de SAP hasta que alguien lo corrija en la base.
- Precios, stock o cualquier dato comercial del artículo.
- Lo que tiene que hacer el Sincronizador del lado de SAP: registrar eventos de
  `OITM` y de donde viva la ficha, y la carga inicial.

## 1. El contrato y las respuestas

Se agregan las tres operaciones a `contrato/ingesta-api-v1.yaml` (la copia
enmendada) y las mismas al archivo oficial del equipo del Sincronizador. El
rol es el mismo, `Ingesta.Maestros.Escribir`: mismo llamador, mismo servicio.

**Cuerpo (`ProductoIngesta`).** Lo que no figura acá se **ignora**, igual que
`origen.tipo` en los socios. En particular, si llegan `descripcion`,
`imagenUrl` o cualquier enlace, no se escriben: la garantía de P5 la cumple
ms-maestros, no la confianza en el cuerpo.

| Campo | Tipo | Obligatorio | Va a |
|---|---|---|---|
| `itemCode` | texto, 1 a 50 | sí | `codigo_articulo` |
| `nombre` | texto, 1 a 255 | sí | `nombre` |
| `activo` | booleano: `validFor = 'Y'` y `frozenFor = 'N'` | sí | `activo` |
| `categoria` | `{ codigo (1–50), nombre (1–255) }` o `null` | sí, anulable | `codigo_categoria` (y `maestros.categoria` si es nueva) |
| `presentacion` | texto ≤ 255 o `null` | no | `presentacion` |
| `ingredienteActivo` | texto ≤ 500 o `null` | no | `ingrediente_activo` |
| `formulacionCodigo` | texto ≤ 20 o `null` | no | `formulacion`, armada (sección 3) |
| `formulacionDescripcion` | texto ≤ 200 o `null` | no | ídem |
| `dosisReferencial` | texto ≤ 500 o `null` | no | `dosis_referencial` |
| `cultivos` | lista de textos de 1 a 255, puede ser vacía | sí | `producto_cultivo` |
| `registro` | `{ entidad: SENASAG \| INIAF \| otro, numero (1–100) }` o `null` | no | `registro_entidad`, `registro_numero` |
| `vigenteDesde` | fecha y hora | sí | `vigente_desde` |
| `origen` | `OrigenIngesta` (el de los socios) | sí | `origen_esquema`, `origen_evento_id` |

Todos los textos se recortan. Un campo opcional ausente, `null`, vacío o con
solo espacios **no cambia** su columna (P9): no se escribe `null` encima de un
valor. Lo mismo vale para `categoria: null`, `registro: null` y `cultivos: []`.
Los campos de usuario de SAP suelen llegar como cadena vacía, y así no borran
la ficha que vino de la web.

**Status.** La seguridad y la clave cortan en el mismo orden y con los mismos
códigos que en los socios (403, 401, 503, 500, 400 por `Idempotency-Key`).
Después:

| Situación | Status | Código |
|---|---|---|
| Cuerpo mal formado: campo faltante, tipo o largo inválido, `registro.entidad` fuera de la lista | 400 | Un `FieldError` por campo |
| `POST` de un producto que existe y no está dado de baja | 409 | `PRODUCTO_YA_EXISTE` |
| `POST` aplicado o ignorado por viejo | 201 | `data: { itemCode }` |
| `PUT` con el `itemCode` del cuerpo distinto del de la ruta | 422 | `ITEMCODE_NO_COINCIDE` |
| `PUT` de un producto que no existe o está dado de baja | 404 | `PRODUCTO_NO_ENCONTRADO` |
| `PUT` aplicado o ignorado por viejo | 200 | `data: { itemCode }` |
| `DELETE` de un producto que no existe o ya está dado de baja | 404 | `PRODUCTO_NO_ENCONTRADO` |
| `DELETE` aplicado | 204 | sin cuerpo |

**Las tres operaciones de los socios (P11).** El `POST` agrega un producto
nuevo, el `PUT` reemplaza uno que existe, y el Sincronizador ya sabe
reintentar como `PUT` un `POST` que dio 409. Así entran también los 90 de la
web: existen sin haber llegado nunca de SAP, su primer `POST` da 409 y el
`PUT` los reemplaza. Un producto dado de baja vuelve con un `POST` más nuevo
que la baja, igual que un socio.

`MapaDeErroresHttp` suma `PRODUCTO_YA_EXISTE` → 409, `ITEMCODE_NO_COINCIDE` →
422 y `PRODUCTO_NO_ENCONTRADO` → 404.

**Idempotencia:** el mismo middleware, por la terna `(clave, método, ruta)`.
No cambia nada.

## 2. Quién escribe cada columna

| Columna de `maestros.producto` | La escribe |
|---|---|
| `codigo_articulo`, `nombre`, `presentacion`, `codigo_categoria`, `ingrediente_activo`, `formulacion`, `dosis_referencial`, `registro_entidad`, `registro_numero`, `activo`, cultivos | La ingesta |
| `vigente_desde`, `dado_de_baja_el`, `origen_esquema`, `origen_evento_id` (nuevas) | La ingesta y el comando de la transición |
| `descripcion`, `imagen_url`, `ficha_tecnica_url`, `hoja_seguridad_url`, `registro_sanitario_url` | **Solo el back-office** |
| `wp_id`, `creado_en` | Nadie nuevo |
| `actualizado_en` | Quien escriba |

**«La escribe la ingesta» quiere decir que la escribe cuando trae valor** (P9).
Por eso un dato que alguien borre en SAP sigue en la App hasta la conciliación;
es la consecuencia aceptada de no borrar nada.

**La categoría pasa a ser de SAP**, como asignación: un envío con otra
categoría mueve el producto, y uno con `categoria: null` no la cambia. Lo que
SAP no toca nunca es el **nombre** de una categoría que ya existe.

## 3. El modelo y la escritura

**Columnas nuevas** en `maestros.producto`, todas anulables, para que las filas
de hoy queden como están: `vigente_desde DATETIME2`, `dado_de_baja_el
DATETIME2`, `origen_esquema NVARCHAR(20)` y `origen_evento_id BIGINT`. Un
producto con `origen_esquema` vacío **nunca llegó de SAP**.

La estructura en SQL Server sigue viviendo en
`database/sql/maestros-productos.sql`, y la copia de SQLite con Blueprint se
cambia a la par (regla de `CLAUDE.md`). El script es idempotente, así que las
columnas se agregan solo si faltan.

**`ReplicarProducto(cuerpo, operación)`**, para el `POST` (crear) y el `PUT`
(reemplazar), en `Maestros/Application/Ingesta/` con `RequiereTransaccion` y
`EscrituraDeMaquina`, con la misma `OperacionDeIngesta` que los socios. Todo lo
que puede fallar se decide antes de escribir, porque un `Result` fallido
confirma la transacción:

1. **Validar sin escribir**, en el orden de `ReplicarSocioHandler`:
   - en el `PUT`, el `itemCode` del cuerpo coincide con el de la ruta (422);
   - en el `POST`, el producto no existe o está dado de baja (si no, 409);
   - en el `PUT`, el producto existe y no está dado de baja (si no, 404).
2. **Vigencia:** si el producto existe y su `vigente_desde` es igual o
   posterior al cuerpo, se ignora el cuerpo entero (categoría incluida) y se
   responde como si se hubiera aplicado (201 o 200). La regla es la de los socios: truncada al segundo, empatar es
   no reemplazar. Un producto sin `vigente_desde` (de la web) acepta cualquier
   cuerpo.
3. **Categoría:** el código se recorta y se pasa a minúsculas, como los seis
   de hoy. *(Mía: SQL Server compara sin distinguir mayúsculas y SQLite sí;
   así un `INSECTICIDAS` se comporta igual en los dos motores.)* Si su código
   no existe, se crea con el nombre del cuerpo; si existe, no se toca. Dos productos que crean a la vez la misma
   categoría nueva no pueden fallar: el segundo tolera la violación de unicidad
   y sigue. Hay que comprobar contra SQL Server que la transacción sigue
   usable después de capturar esa violación. Si no lo es, la categoría se
   crea con una inserción que no falla si ya existe. Una categoría que queda
   creada sin producto no molesta: `/categorias` muestra solo las que tienen
   productos visibles.
4. **Producto:** se crea o se actualiza por `codigo_articulo`, solo con las
   columnas de la ingesta (sección 2) que traen valor, con `dado_de_baja_el`
   vacío (así un `POST` reactiva) y el origen del cuerpo. `nombre`, `activo`, la
   vigencia y el origen siempre se escriben, porque son obligatorios.
5. **Formulación:** se arma como `«{formulacionDescripcion} ({formulacionCodigo})»`
   —«SUSPENSION CONCENTRADA (SC)»—, con el mismo formato que ya usa el
   catálogo. Con uno solo de los dos, ese; sin ninguno, no se toca.
6. **Cultivos:** una lista con al menos un cultivo reemplaza la entera; una
   vacía no la toca. Los repetidos se dejan una sola vez, comparando sin
   distinguir mayúsculas, porque la clave primaria de `producto_cultivo` en
   SQL Server tampoco distingue.

**`DarDeBajaProducto(itemCode)`**, para el `DELETE`:
- Si no existe o ya está dado de baja → 404.
- Si no, `dado_de_baja_el` y `vigente_desde` pasan a ser **el momento de la
  baja**, así un envío leído en SAP antes de la baja no lo resucita. La ficha,
  la descripción y los enlaces quedan como estaban.

**Log:** una línea por pedido, como en los socios: `itemCode`, operación,
resultado (`aplicado`, `ignorado-por-viejo`, `dado-de-baja`) y `eventoId`.

## 4. Lo que ve la App

La única definición, `EloquentCatalogoDeProductos::visibles()`, suma
`dado_de_baja_el` vacío a lo que ya exige (activo, con código y con una
categoría que existe). Nada más cambia en `/productos`, `/productos/{itemCode}`,
sus documentos ni `/categorias`.

Consecuencias que la App va a ver, sin cambio de contrato:
- Más tarjetas, una por presentación, con nombres como «ALVO - 5 L».
- Textos en mayúsculas.
- Una presentación nueva sale sin imagen, sin documentos y sin descripción
  hasta que el área los cargue. La API ya omite la descripción vacía.
- Una categoría nueva de SAP aparece en `/categorias` con el nombre de SAP.
  Con los datos de hoy son tres (ver «Las tres categorías nuevas»).
- Los productos de semillas (unidad SEM) entran al catálogo por primera vez.

## 5. El back-office

- **Formulario de `/admin/productos/{id}`:** suma la descripción, un texto de
  hasta 1000 caracteres (el largo de `valor_anterior` y `valor_nuevo` de la
  bitácora, que así no cambia). Vacía es `null`. *(El largo es mío.)*
- **Bitácora:** un cambio de descripción se asienta en
  `backoffice.cambios_de_catalogo` con `campo = 'descripcion'` y el valor
  anterior leído de la base, como los enlaces.
- **Por qué no se ve** (`ProductoAdministrable::porQueNoSeVe()`), en este
  orden: «Dado de baja en SAP.», «Inactivo en SAP.», «No tiene código de
  artículo.», «No tiene categoría.». Tiene que seguir siendo la misma regla
  que `visibles()`.
- **Aviso informativo**, que no oculta nada: «Todavía no llegó de SAP» en los
  productos sin origen. *(Mío: es lo que el operador necesita ver antes de
  correr el comando de la sección 6.)*
- El back-office sigue sin poder cambiar `activo`.

## 6. La transición

Un comando, `maestros:productos-sin-sap`:
- **Sin opciones,** lista los productos sin origen y sin baja: código, nombre
  y si hoy se ven en la App. No escribe nada.
- **Con `--dar-de-baja`,** les fija `dado_de_baja_el` y `vigente_desde` en el
  momento actual, igual que un `DELETE`, y dice cuántos fueron. Si después SAP
  manda uno de esos artículos con vigencia posterior, vuelve.

Se corre desde la consola del Container App cuando el equipo del Sincronizador
confirma que terminó la carga inicial. *(El nombre y la forma son míos.)*

## 7. Pruebas y despliegue

**Pruebas:**
- **Contrato:** las tres operaciones nuevas existen y cada status valida contra
  su esquema, con las pruebas de contrato de la ingesta que ya existen.
- **Escritura:** una por paso de la sección 3, y en particular:
  - un cuerpo con `descripcion` e `imagenUrl` no cambia esas columnas;
  - un producto de la web con enlaces y descripción conserva ambos al recibir
    su primer `PUT`, después del 409 del `POST`;
  - un cuerpo con campos ausentes, `null`, vacíos, `categoria: null`,
    `registro: null` y `cultivos: []` no borra la ficha que había (P9);
  - un nombre de categoría existente no cambia;
  - un `POST` de un producto que existe da 409, y un `PUT` de uno que no
    existe da 404;
  - un `POST` viejo no reactiva un producto dado de baja.
- **Lo que ve la App:** inactivo, dado de baja y sin categoría no salen;
  reactivado vuelve con sus enlaces.
- **Back-office:** el campo descripción, su asiento y los motivos nuevos.
- **Comando:** lista sin escribir; con la opción, da de baja solo a los que no
  tienen origen.
- **Contra SQL Server local:** la migración y las consultas nuevas, porque la
  batería corre sobre SQLite.

**Despliegue, en orden:**
1. **Migración en Azure antes del push**, desde la imagen local, como las de
   la réplica: la consulta de la App va a leer `dado_de_baja_el`.
2. **Push.**
3. **Del lado de SAP**, lo que hoy falta para que haya eventos de artículos:
   las filas 4-A, 4-U y 4-D en `@AGRO_MAPEO` y `sql/AGRO_REGISTRAR_EVENTO.sql`.
   En `AGRO_PRD` faltan además el campo `U_EVENTO_ID`, la secuencia y el
   procedimiento, que ya funcionan en `AGRO_P6`.
4. **El Sincronizador empieza a mandar productos** y hace la carga inicial.
5. **El comando**, primero sin opciones para revisar la lista, y después con
   `--dar-de-baja`.

## Las tres categorías nuevas

Con los datos de hoy, la ingesta crea tres categorías que no existen. **Entran
como categorías propias, con el nombre que manda SAP** (decisión del usuario,
16 de octubre): el nombre y si alguna se muestra dentro de otra se arreglan
más adelante.

| Grupo en SAP | Código | Artículos |
|---|---|---|
| TRATAMIENTO DE SEMILLAS | `tratamiento-de-semillas` | 28 |
| FERTILIZANTES DE BASE | `fertilizantes-de-base` | 6 |
| SEMILLAS | `semillas` | 23 |

No hace falta nada especial: es la regla de P4 para un código nuevo.

## Pendiente del área

- **Las presentaciones de los 14 productos de P10.** El área tiene que cargar
  los enlaces de las que faltan; la lista con los códigos está en
  `sql/catalogo-productos.sql` del Sincronizador. En el Alvo, la imagen y la
  hoja de seguridad del `10180` quedan en el de 5 L, y faltan el `10198` (1 L)
  y el `10199` (0.25 L). Como la web decía que el 10180 era «0.25 L / 1 L», hay
  que revisar que la imagen no muestre otro envase.

## Más adelante

- **Conciliar la ficha** (P9). Hasta entonces, un dato que SAP no tiene se
  sigue mostrando con el valor de la web, y uno que alguien borre en SAP no se
  borra en la App.
- **Las tres categorías nuevas:** corregir su nombre («TRATAMIENTO DE
  SEMILLAS» en el chip) y decidir si alguna se muestra dentro de otra, por
  ejemplo `fertilizantes-de-base` dentro de `fertilizantes`. Hoy el nombre se
  corrige solo en la base.

## Avisos

- **Equipo del Sincronizador:** la ruta que supusieron es la correcta
  (`POST /ingesta/v1/productos`, `PUT` y `DELETE /ingesta/v1/productos/{itemCode}`),
  y entra al contrato de ingesta con el cuerpo de la sección 1. `activo` es
  obligatorio, y la descripción y los enlaces se ignoran si llegan.
- **Quien administra SAP:** renombrar un grupo de artículos cambia el código
  de su categoría en la App (sección «Lo que manda el Sincronizador»).
- **Equipo de la App:** más tarjetas, una por presentación; nombres y textos
  en mayúsculas; presentaciones nuevas sin imagen al principio.
- **El área que carga el catálogo:** la descripción pasa a cargarse desde el
  back-office, y cada presentación nueva necesita sus enlaces y su
  descripción. Al principio son unas 20, de los 14 productos de P10.

## Lo que queda escrito

`CLAUDE.md`, en «Contratos que no se negocian», el párrafo del catálogo cambia
a:
- El catálogo es réplica de SAP en parte: la ingesta escribe la ficha, y la
  descripción y los cuatro enlaces son solo del back-office.
- Cada artículo de SAP es un producto.
- Pasa por `vigenteDesde`, con la misma regla que los socios, y la baja es
  lógica.
- La categoría se asigna desde SAP y su nombre no se pisa.
- Un dato vacío de SAP no borra nada, hasta conciliar la ficha.

Además, la línea de la ingesta suma las tres operaciones de productos.

## Lo que queda al implementador

- Cómo se reparte el controlador: uno nuevo o el de los socios.
- Si `esMasNuevo` se comparte entre los dos casos de uso o se repite.
- El texto exacto de los mensajes de error.
