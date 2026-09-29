# Propiedades del socio — diseño

## Para qué

En el paso 2 de *Solicitar visita técnica*, la App lista las propiedades
(lotes) del socio elegido, y la persona marca cuáles quiere que se visiten. El
`id` de cada una viaja después a ms-comercial en `POST /solicitudes-visita`.

Hoy la réplica no tiene propiedades: `cantidadPropiedades` va fijo en `0`
(«Supuesto S1», `ObtenerContextoHandler`) y `GET /v1/socios/{cardCode}/propiedades`
es la única operación que el contrato asigna a `maestros` y figura en
`PENDIENTES`.

**Éxito:** una persona elige un socio de su grupo y
`GET /v1/socios/{cardCode}/propiedades` devuelve sus propiedades activas como
`{ id, nombre }`. `/socios` y `/mi-cuenta` muestran en `cantidadPropiedades`
exactamente cuántas devuelve esa lista. `ContratoTest` ya no tiene nada en
`PENDIENTES`.

## De dónde salen los datos

De una **conciliación**: las propiedades están en una tabla de otra base de
Azure, a la que este servicio no tiene acceso, y Agropartners decide cuáles se
migran y las carga con un script. No es réplica de SAP, así que no pasa por
`vigenteDesde` ni por el importador de maestros: el mismo modelo que el
catálogo de productos.

**La forma la fija el contrato**, no la tabla de origen: la migración se
adapta a esta tabla.

## Alcance

**Adentro:**

- La tabla `maestros.propiedad`, con su estructura en
  `database/sql/maestros-propiedades.sql` (idempotente), que la migración
  ejecuta en SQL Server y que en SQLite se arma con Blueprint sin los CHECK.
- `GET /v1/socios/{cardCode}/propiedades`.
- `cantidadPropiedades` real en `/socios` y `/mi-cuenta`.
- Un script de ejemplo con propiedades para los socios de la semilla, para
  probar en local.
- El pedido en la colección de Postman, y los tests de contrato.

**Afuera:**

- La conciliación y la carga real: las hace Agropartners, con `INSERT` sobre
  esta estructura.
- Administrar propiedades desde el back-office.
- Leer la tabla de origen: está en otra base y ninguna consulta cruza de una
  base a otra.
- La validación de ms-comercial (ver «Integración con ms-comercial»).

## La tabla `maestros.propiedad`

| Columna | Tipo | Regla |
|---|---|---|
| `id_propiedad` | `NVARCHAR(50)`, PK | El `id` del contrato (p. ej. `PROP-014`). No vacío. |
| `nombre` | `NVARCHAR(200)`, NOT NULL | El `nombre` del contrato, tal como se muestra en la lista («Lote 14 · San Julián»). No vacío. |
| `codigo_de_socio` | `NVARCHAR(15)`, NOT NULL, con índice | El dueño. Mismo tipo que `maestros.socios.codigo_de_socio`. |
| `activa` | `BIT`, NOT NULL, `1` por defecto | Retirar sin borrar. |
| `creado_en`, `actualizado_en` | `DATETIME2`, NOT NULL, `SYSUTCDATETIME()` por defecto | |

Reglas:

1. **El `id` es estable: nunca se reusa ni se cambia.** Viaja a ms-comercial y
   los reportes de campo lo guardan (`ReporteCampo.propiedadId`).
2. **El `nombre` es el mismo texto que muestran los reportes de campo.** El
   contrato dice que `ReporteCampo.propiedad` «es el mismo texto que
   `Propiedad.nombre`». La conciliación tiene que usar el nombre de la fuente
   de esos reportes, y renombrar una propiedad la separa de sus reportes
   anteriores.
3. **Cada propiedad es de un solo socio.** Un lote que usan dos socios va en
   dos filas con ids distintos. En la solicitud viaja el par
   `cardCode` + `id`, así que ms-comercial no pierde nada.
4. **Sin clave foránea hacia `socios`.** Esa tabla se reimporta desde SAP y la
   clave podría trabar la importación; el alcance ya garantiza que el socio
   consultado existe.
5. **Para la App, una propiedad existe solo si `activa = 1`.** Esa definición
   vive en un solo lugar del adaptador, y la usan la lista y el conteo.

No se valida el formato del `id` ni del `cardCode`: `PROP-014` y
`^C-[0-9]{6}$` son lo que muestra el contrato, no lo que exige esta tabla.

## El endpoint

`GET /v1/socios/{cardCode}/propiedades`, dentro del grupo `auth.token`.

| Caso | Respuesta |
|---|---|
| Socio del grupo de la persona, con propiedades | 200, `{ items: [{ id, nombre }] }`, solo las activas |
| Socio del grupo, sin propiedades activas | 200, `{ items: [] }` |
| Socio de otro grupo | 403 `ACCESO_DENEGADO` |
| `cardCode` inexistente | 403 `ACCESO_DENEGADO` |
| `cardCode` que ni siquiera es un código válido (más de 15 caracteres) | 403 `ACCESO_DENEGADO` |
| Sin token o con token vencido | 401 `TOKEN_INVALIDO` |

- **El alcance se verifica antes que la existencia**, como en todo el
  servicio, y el contrato solo documenta 401 y 403 para esta ruta: nunca 404
  ni 400.
- **Orden: por `nombre` y, a igualdad, por `id`.** El contrato no lo fija (su
  ejemplo pone «Lote 14» antes que «Lote 07», pero los ejemplos no son
  normativos).
- Sin paginación ni filtros, como pide el contrato. Cada ítem lleva
  exactamente `id` y `nombre`.

## `cantidadPropiedades`

`ObtenerContextoHandler` cuenta las propiedades activas de todos los socios
del grupo **en una sola consulta agrupada**, no una por socio. Un socio sin
propiedades cuenta `0`. Como `/socios` y `/mi-cuenta` salen del mismo handler,
los dos quedan corregidos juntos, y el número coincide siempre con la lista
porque usa la misma definición de «activa».

## Arquitectura

Todo en el módulo **Maestros**, con el patrón del catálogo:

- **`ListarPropiedades`** (`Application/Propiedades/ListarPropiedades/`):
  petición con la persona autenticada y el `CodigoDeSocio`. Implementa
  `ConAlcanceDeSocio`, así que `AlcanceBehavior` la corta con 403 antes del
  handler (`AlcanceTest` lo exige). El handler devuelve la lista.
- **`PropiedadesDeSocios`** (`Application/Propiedades/`, puerto):
  `deSocio(CodigoDeSocio): list<PropiedadDelSocio>` y
  `contarPorSocio(list<CodigoDeSocio>): array<string, int>`.
  `PropiedadDelSocio(id, nombre)` es el modelo de lectura.
- **`EloquentPropiedadesDeSocios`** (`Infrastructure/Persistence/`): la única
  definición de «activa», y el nombre de tabla según el motor
  (`maestros.propiedad` / `maestros_propiedad`), igual que el catálogo.
- **`PropiedadesController`** (`Presentation/Http/`): arma el `CodigoDeSocio`
  desde la ruta. Si el código no es válido responde el mismo 403
  `ACCESO_DENEGADO` sin despachar nada; si es válido, despacha
  `ListarPropiedades` y responde con el envelope.
- **`ObtenerContextoHandler`** recibe el puerto para el conteo.
- Registro del handler en `CoreServiceProvider` y del puerto en
  `ModulosServiceProvider`.

## Integración con ms-comercial

No es parte de esta entrega, pero el diseño la tiene en cuenta:

- `POST /solicitudes-visita` responde `propiedadAjena` («La propiedad PROP-031
  no pertenece al socio C-004871»), así que ms-comercial necesita saber de
  quién es cada propiedad. **La fuente es este endpoint:** ms-comercial lo
  consulta reenviando el token de la persona, y el mismo control de alcance le
  sirve. Una propiedad dada de baja después de que la App cargó la lista la
  rechaza ms-comercial, que es lo correcto.
- Los reportes de campo muestran el mismo `nombre` (regla 2 de la tabla).

## Pruebas

- **Feature**, una por fila de la tabla del endpoint: la lista del socio con
  solo las activas y en orden; la lista vacía; socio de otro grupo, código
  inexistente y código demasiado largo → 403; sin token → 401.
- Una propiedad inactiva no aparece ni cuenta.
- `cantidadPropiedades` de cada socio en `/socios` es igual al largo de su
  lista, y `0` para el que no tiene.
- `ContratoDeRespuestasTest` valida el 200 y el 403 contra el esquema.
- `ContratoTest`: la ruta sale de `PENDIENTES`.
- El adaptador contra SQLite: `deSocio` y `contarPorSocio`.

## Datos de ejemplo

`database/sql/maestros-propiedades-ejemplo.sql`, solo para local: dos
propiedades activas y una inactiva para un socio de la semilla, una para otro
del mismo grupo, y ninguna para un tercero (para ver la lista vacía).

## Lo que queda escrito

`CLAUDE.md`, en «Contratos que no se negocian»: las propiedades no son réplica
de SAP y se cargan por script; el `id` es estable y el `nombre` es el de los
reportes de campo; ms-comercial valida la pertenencia contra este endpoint.
