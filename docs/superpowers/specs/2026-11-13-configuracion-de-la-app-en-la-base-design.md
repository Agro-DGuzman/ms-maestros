# La configuración de la App, en la base

Diseño corto, aprobado el 13/11/2026.

## Qué y por qué

`/version`, `/bancos` y `/contactos/atencion-al-cliente` leen hoy variables de
entorno (`config/app_movil.php`). Cambiar una cuenta o el teléfono es editar el
Container App en el portal de Azure, y en Azure todavía faltan `BANCOS`,
`BANCOS_TITULAR_NIT` y `ATENCION_TELEFONO`, así que dos de los tres responden
500 `CONFIGURACION_INCOMPLETA`.

El usuario quiere cargar estos valores **una vez, en la base**, y que la App los
lea de ahí. Las cuentas bancarias, en una tabla normal; lo demás, en una tabla
diccionario.

## Objetivos

- Una sola fuente: la base. Las variables `MOVIL_*`, `BANCOS*` y `ATENCION_*`
  desaparecen.
- Las respuestas de los tres endpoints no cambian, tampoco cómo fallan.
- En SQL Server, un error de tipeo al cargar a mano falla en el `INSERT`, no en
  la App.

## Fuera de alcance

- La pantalla del back-office, los permisos y la bitácora. Se suman cuando
  otra persona que no sea el usuario tenga que cambiar estos datos. Hoy el
  ingreso con contraseña da todos los permisos a todos, así que un permiso
  propio no separaría a nadie hasta que entre Entra.
- Moneda, tipo de cuenta o QR por cuenta: el contrato pide solo `banco` y
  `numeroCuenta`.
- Un caché: es una consulta corta por pedido, y `/version` ya va con
  `Cache-Control: max-age=300`.

## Lo que no puede pasar

- Que una tabla vacía responda una lista de bancos vacía: le diría al socio que
  no hay dónde pagar. Sigue siendo 500 `CONFIGURACION_INCOMPLETA`.
- Que faltar las versiones bloquee a alguien: sin versiones, `/version` deja
  pasar a todos, igual que hoy.
- Que los valores reales entren al repo. Hay una copia en GitHub.

## Enfoques

1. **Variables de entorno (hoy).** No hay que escribir código, pero cambiar
   algo exige el portal y no queda registro de quién lo hizo.
2. **Un diccionario genérico para todo**, con las cuentas como el texto
   `banco|cuenta;…`. Es una sola tabla, pero el valor sigue siendo texto sin
   validar, y un error aparece recién cuando la App pide `/bancos`.
3. **Tabla normal para las cuentas y diccionario con claves conocidas, sin
   pantalla.** *Elegido por el usuario.* Las cuentas son una lista y tienen su
   propia forma; el resto son valores sueltos.
4. **Lo mismo con pantalla, permisos y bitácora.** Queda para después: sin
   permisos por persona, la pantalla no protege lo que más importa, que son
   las cuentas.

## Diseño

### `maestros.cuentas_bancarias`

| Columna | Tipo | Regla |
|---|---|---|
| `id` | `INT IDENTITY` | clave primaria |
| `banco` | `NVARCHAR(100)` | no vacío |
| `numero_cuenta` | `VARCHAR(50)` | solo dígitos y guiones, con al menos un dígito |
| `orden` | `INT` | único; la App recibe las cuentas en este orden |

Además, el par (`banco`, `numero_cuenta`) no se repite. «No vacío» incluye un
valor hecho solo de espacios, con la misma técnica que
`maestros-propiedades.sql` (`DATALENGTH` contra `RTRIM`).

### `maestros.configuracion_app`

| Columna | Tipo | Regla |
|---|---|---|
| `clave` | `VARCHAR(60)` | clave primaria; una de la lista de abajo |
| `valor` | `NVARCHAR(500)` | no vacío |

| Clave | Si falta | Regla en la tabla |
|---|---|---|
| `titular.nit` | `/bancos` responde 500 | — |
| `titular.razon_social` | «Agropartners S.R.L.» | — |
| `atencion.telefono` | atención responde 500 | 8 dígitos, empieza con 6 o 7 |
| `atencion.area` | «Atención al Cliente» | — |
| `version.android.minima`, `version.ios.minima` | no se exige ninguna | `n.n.n` |
| `version.android.recomendada`, `version.ios.recomendada` | no se sugiere ninguna | `n.n.n` |
| `version.android.tienda`, `version.ios.tienda` | sin enlace | empieza con `https://` |
| `aviso.obligatoria.titulo` | «Actualización necesaria» | — |
| `aviso.obligatoria.mensaje` | «Esta versión dejó de estar disponible. Actualiza para seguir usando la App.» | — |
| `aviso.sugerida.titulo` | «Hay una versión nueva» | — |
| `aviso.sugerida.mensaje` | «Hay una versión nueva de la App. Puedes actualizar cuando quieras.» | — |

La tabla solo acepta esas claves (`CHECK … IN`). Agregar una clave es tocar el
código y ese `CHECK`, con una migración. Es a propósito: los valores se cargan
a mano, y sin la regla una clave mal escrita se guardaría sin error y el valor
quedaría sin leer.

Los valores por defecto son los de `config/app_movil.php` hoy, textuales.

### Cómo se lee

- Un adaptador nuevo, `Maestros\Infrastructure\Configuracion\ConfiguracionDesdeLaBase`,
  implementa el puerto `ConfiguracionDeLaApp` y reemplaza a
  `ConfiguracionDesdeEntorno`. El puerto, el dominio
  (`PoliticaDeVersion`, `CuentasParaPagar`, `CanalDeAtencion`) y el controlador
  no cambian.
- Se lee en cada pedido. El detalle de lo que está mal va al log, y el socio
  recibe `CONFIGURACION_INCOMPLETA`, igual que hoy.
- La validación del código sigue siendo la que manda: `Version::desde`,
  `Celular::desdeLocalBoliviano` (que acepta el número con o sin `591`). Los
  `CHECK` son una red para el `INSERT` a mano, y SQLite no los tiene.
- Un valor de solo espacios cuenta como ausente: en SQLite no hay `CHECK` que
  lo frene.
- Una clave que el código no conoce se ignora.
- Si la base no responde, es una excepción y un 500 común, no
  `CONFIGURACION_INCOMPLETA`.

### Archivos

- `database/sql/maestros-configuracion.sql`: la estructura de las dos tablas,
  idempotente, con su encabezado. La migración lo ejecuta con `LotesDeScript`,
  y en SQLite arma la misma forma con Blueprint, sin los `CHECK`, como
  `crear_propiedades`.
- `database/sql/maestros-configuracion-ejemplo.sql`: un `MERGE` con valores de
  ejemplo, para local y para que el usuario lo copie, complete y corra en
  Azure **sin guardarlo en el repo**.
- Se borran `config/app_movil.php`, `ConfiguracionDesdeEntorno`, el armado en
  `ModulosServiceProvider` y las variables de `.env.example`.
- `CLAUDE.md`: el párrafo «La configuración de la App falla distinto…» pasa a
  hablar de las tablas, y se suma a la regla de las migraciones antes del push.

### Pruebas

`ConfiguracionDeLaAppTest` y `ContratoDeRespuestasTest` dejan de pisar la
configuración: cargan filas en las tablas. Casos nuevos:

- las cuentas salen en el orden de `orden`, no en el de inserción;
- sin cuentas, o sin `titular.nit`, responde 500 `CONFIGURACION_INCOMPLETA`;
- un valor de solo espacios cuenta como ausente;
- sin `titular.razon_social`, `atencion.area` ni los avisos, salen los valores
  por defecto;
- sin versiones, `/version` deja pasar;
- una versión mal escrita responde `CONFIGURACION_INCOMPLETA`;
- una clave desconocida no rompe nada.

Los `CHECK` no se pueden probar en SQLite. Se comprueban a mano contra el SQL
Server local: cada `INSERT` inválido del encabezado del script tiene que fallar.

## Puesta en marcha

La imagen nueva lee las tablas, así que tienen que existir en Azure antes del
push, igual que las de propiedades.

1. Revisar en el portal si `ms-maestros` tiene variables `MOVIL_*` cargadas, y
   anotar sus valores.
2. Correr la migración contra Azure desde la imagen local, con `.env.azure`.
3. Completar una copia del ejemplo con los valores reales (las cuentas, el NIT,
   el teléfono y las versiones del paso 1) y correrla en Azure. No guardarla en
   el repo.
4. Push a `azure` y a `origin`.
5. Correr la colección de Postman contra el APIM: `/bancos` y atención tienen
   que pasar a verde.
6. Borrar del portal las variables viejas: ya no las lee nadie.

## Lo que decide quien lo construye

- Si el adaptador usa el query builder o modelos de Eloquent (en los dos casos,
  solo dentro de `Infrastructure`).
- Dónde viven los valores por defecto, siempre que el texto sea el de arriba.
- La forma exacta de cada `CHECK` en T-SQL.
