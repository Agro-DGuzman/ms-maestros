# Propiedades del socio Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Servir `GET /v1/socios/{cardCode}/propiedades` desde una tabla propia `maestros.propiedad` y hacer que `cantidadPropiedades` cuente de verdad.

**Architecture:** Mismo patrón que el catálogo de productos: estructura en un script T-SQL que la migración ejecuta (SQLite recibe una copia con Blueprint), un puerto de lectura en `Maestros/Application/Propiedades` con su adaptador Eloquent, una petición `ListarPropiedades` que implementa `ConAlcanceDeSocio` para que `AlcanceBehavior` corte con 403, y `ObtenerContextoHandler` que usa el mismo puerto para contar.

**Tech Stack:** PHP 8.4, Laravel, Pest, SQL Server (Azure SQL) y SQLite en memoria para la batería.

**Spec:** `docs/superpowers/specs/2026-09-29-propiedades-del-socio-design.md`

## Global Constraints

- Rama `feat/propiedades-del-socio` desde `main`.
- `declare(strict_types=1);` en todo archivo de `src/`; `Domain/` y `Application/` no usan Eloquent ni Laravel.
- Andamiaje en inglés, negocio en español; comentarios en español y solo el porqué; commits en español con `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Antes de cada commit: `composer test`, `composer stan` y `composer lint -- --test` en verde. Cada archivo de prueba corre solo. Primero el test en rojo, verificado.
- Tabla `maestros.propiedad` en SQL Server, `maestros_propiedad` en SQLite. Columnas exactas: `id_propiedad NVARCHAR(50)` PK, `nombre NVARCHAR(200)` NOT NULL, `codigo_de_socio NVARCHAR(15)` NOT NULL con índice, `activa BIT` NOT NULL default `1`, `creado_en` y `actualizado_en DATETIME2` NOT NULL default `SYSUTCDATETIME()`. Sin clave foránea a `socios`.
- Visible para la App = `activa = 1`, definido en un solo lugar del adaptador.
- Cada ítem de la respuesta tiene exactamente `id` y `nombre`. Orden: `nombre`, y a igualdad `id_propiedad`.
- Fuera de alcance, `cardCode` inexistente o de más de 15 caracteres → 403 `ACCESO_DENEGADO` (`Maestros\Application\Alcance\AlcanceErrors::accesoDenegado()`), nunca 404 ni 400.

## Review Focus

1. **Espacios alrededor del `id`, del `nombre` o del `codigo_de_socio` en la carga** → SQL Server rechaza la fila (CHECK); un `id` con espacio viajaría a ms-comercial distinto. Lo prueba el paso manual de la Task 1.
2. **El mismo `id` cargado para dos socios** → SQL Server rechaza la fila (PK). Paso manual de la Task 1.
3. **Una propiedad cuyo `codigo_de_socio` no está en la réplica** (error de tipeo) → no aparece en ningún lado y nadie se entera. El script trae la consulta que las lista; la Task 4 la corre.
4. **`contarPorSocio` con lista vacía** (grupo sin socios) → `[]`, sin consulta rota. Test en la Task 1.
5. **Orden con la colación de SQL Server** (insensible a mayúsculas) contra el orden binario de SQLite → el que ve la App es el de SQL Server. Paso manual de la Task 4.

## Datos de ejemplo (los usan el fixture y el script de ejemplo, idénticos)

| `id_propiedad` | `nombre` | `codigo_de_socio` | `activa` |
|---|---|---|---|
| `PROP-014` | `Lote 14 · San Julián` | `C-004871` | 1 |
| `PROP-007` | `Lote 07 · Cuatro Cañadas` | `C-004871` | 1 |
| `PROP-003` | `Lote 03 · Pailón` | `C-004871` | 0 |
| `PROP-021` | `Lote 21 · Okinawa` | `C-004872` | 1 |
| `PROP-031` | `Lote 31 · Montero` | `C-005210` | 1 |

`C-004871`, `C-004872` y `C-004873` son del grupo `GRP-014`, el de la persona `p-8f2b1c40` de la semilla; `C-005210` es de otro grupo. `C-004873` no tiene propiedades.

---

### Task 1: La tabla y su adaptador

**Files:**
- Create: `database/sql/maestros-propiedades.sql`
- Create: `app/Persistence/LotesDeScript.php`
- Create: `database/migrations/maestros/2026_09_29_000100_crear_propiedades.php`
- Modify: `database/migrations/maestros/2026_09_28_000100_crear_catalogo_de_productos.php` (usa `LotesDeScript`, borra sus dos métodos privados)
- Create: `src/Maestros/Application/Propiedades/PropiedadDelSocio.php`
- Create: `src/Maestros/Application/Propiedades/PropiedadesDeSocios.php`
- Create: `src/Maestros/Infrastructure/Persistence/EloquentPropiedadesDeSocios.php`
- Modify: `app/Providers/ModulosServiceProvider.php` (binding junto al de `CatalogoDeProductos`)
- Create: `tests/Soporte/PropiedadesDeEjemplo.php`
- Test: `tests/Unit/Persistence/LotesDeScriptTest.php`, `tests/Feature/Maestros/PropiedadesDeSociosTest.php`

**Interfaces:**
- Produces:
  - `App\Persistence\LotesDeScript::de(string $archivo): list<string>` — lee `database_path("sql/{$archivo}")`, `RuntimeException` si no puede.
  - `App\Persistence\LotesDeScript::desdeTexto(string $script): list<string>` — parte en líneas `GO` (sin distinguir mayúsculas), recorta, descarta lotes vacíos o solo de comentarios. Es la lógica que hoy está en la migración del catálogo.
  - `Maestros\Application\Propiedades\PropiedadDelSocio` — `final readonly`, `public string $id`, `public string $nombre`.
  - `Maestros\Application\Propiedades\PropiedadesDeSocios`:
    - `deSocio(CodigoDeSocio $socio): array` — `list<PropiedadDelSocio>`, solo activas, ordenadas.
    - `contarPorSocio(array $socios): array` — recibe `list<CodigoDeSocio>`, devuelve `array<string, int>` por `cardCode`, solo los que tienen al menos una activa.
  - `Tests\Soporte\PropiedadesDeEjemplo::sembrar(): void` y `::tabla(): string`.

- [ ] **Step 1: Tests en rojo**

`LotesDeScriptTest`:
```php
it('parte en GO y descarta lotes vacios o de solo comentarios', function () {
    $script = "CREATE A;\nGO\n-- solo un comentario\nGO\n/* bloque */\ngo\n  CREATE B;  \nGO\n";

    expect(LotesDeScript::desdeTexto($script))->toBe(['CREATE A;', 'CREATE B;']);
});
```

`PropiedadesDeSociosTest` (`RefreshDatabase`, `beforeEach` → `PropiedadesDeEjemplo::sembrar()`; `$p = app(PropiedadesDeSocios::class)`):
- `lista solo las activas del socio, por nombre` → `array_map(fn ($x) => [$x->id, $x->nombre], $p->deSocio(CodigoDeSocio::desde('C-004871')))` es `[['PROP-007', 'Lote 07 · Cuatro Cañadas'], ['PROP-014', 'Lote 14 · San Julián']]`.
- `un socio sin propiedades da lista vacia` → `deSocio(C-004873)` es `[]`.
- `cuenta las activas de cada socio pedido` → `contarPorSocio([C-004871, C-004872, C-004873])` es `['C-004871' => 2, 'C-004872' => 1]` (orden de claves indiferente: comparar con `toEqualCanonicalizing` o `ksort`).
- `contar una lista vacia no consulta nada` → `contarPorSocio([])` es `[]`.
- `no cuenta socios que no se pidieron` → `contarPorSocio([C-004871])` es `['C-004871' => 2]` (PROP-031 de `C-005210` no aparece).

- [ ] **Step 2: Correr y ver el rojo**

Run: `vendor/bin/pest tests/Unit/Persistence/LotesDeScriptTest.php tests/Feature/Maestros/PropiedadesDeSociosTest.php`
Expected: FAIL, clases inexistentes.

- [ ] **Step 3: El script T-SQL**

`database/sql/maestros-propiedades.sql`, con un encabezado que diga que es la estructura, que es idempotente, que la carga de la conciliación son `INSERT` sobre ella y las dos reglas del spec (el `id` nunca se reusa ni cambia; el `nombre` es el de los reportes de campo). Cuerpo:

```sql
IF SCHEMA_ID('maestros') IS NULL EXEC('CREATE SCHEMA maestros');
GO

IF OBJECT_ID('maestros.propiedad') IS NULL
CREATE TABLE maestros.propiedad (
    id_propiedad     NVARCHAR(50)  NOT NULL CONSTRAINT PK_propiedad PRIMARY KEY,
    nombre           NVARCHAR(200) NOT NULL,
    codigo_de_socio  NVARCHAR(15)  NOT NULL,
    activa           BIT           NOT NULL CONSTRAINT DF_propiedad_activa DEFAULT (1),
    creado_en        DATETIME2(0)  NOT NULL CONSTRAINT DF_propiedad_creado DEFAULT SYSUTCDATETIME(),
    actualizado_en   DATETIME2(0)  NOT NULL CONSTRAINT DF_propiedad_actualizado DEFAULT SYSUTCDATETIME(),
    -- LEN ignora los espacios finales: DATALENGTH contra RTRIM los detecta.
    CONSTRAINT CK_propiedad_id CHECK (LEN(id_propiedad) > 0 AND id_propiedad NOT LIKE N' %'
        AND DATALENGTH(id_propiedad) = DATALENGTH(RTRIM(id_propiedad))),
    CONSTRAINT CK_propiedad_nombre CHECK (LEN(nombre) > 0 AND nombre NOT LIKE N' %'
        AND DATALENGTH(nombre) = DATALENGTH(RTRIM(nombre))),
    CONSTRAINT CK_propiedad_socio CHECK (LEN(codigo_de_socio) > 0 AND codigo_de_socio NOT LIKE N' %'
        AND DATALENGTH(codigo_de_socio) = DATALENGTH(RTRIM(codigo_de_socio)))
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_propiedad_socio'
               AND object_id = OBJECT_ID('maestros.propiedad'))
CREATE INDEX IX_propiedad_socio ON maestros.propiedad (codigo_de_socio) INCLUDE (activa, nombre);
GO

/* Verificación después de cargar: propiedades cuyo socio no está en la réplica.
   No aparecen en ningún lado, así que tiene que devolver cero filas.

SELECT p.id_propiedad, p.nombre, p.codigo_de_socio
FROM maestros.propiedad p
WHERE NOT EXISTS (SELECT 1 FROM maestros.socios s WHERE s.codigo_de_socio = p.codigo_de_socio);
*/
```

- [ ] **Step 4: `LotesDeScript`, la migración y el catálogo**

`LotesDeScript` con la lógica de `lotesDelScript()`/`soloComentarios()` de la migración del catálogo, que pasa a llamar `LotesDeScript::de('maestros-productos.sql')`. La migración nueva: en `sqlsrv`, `DB::unprepared()` por cada lote de `LotesDeScript::de('maestros-propiedades.sql')`; en SQLite, `Schema::create('maestros_propiedad', ...)` con `string('id_propiedad', 50)->primary()`, `string('nombre', 200)`, `string('codigo_de_socio', 15)->index()`, `boolean('activa')->default(true)`, `dateTime('creado_en')->useCurrent()`, `dateTime('actualizado_en')->useCurrent()`, sin los CHECK. `down()` borra `maestros.propiedad` / `maestros_propiedad`. El docblock de la migración repite el del catálogo: un solo lugar donde vive la estructura; si cambia uno, cambia el otro.

- [ ] **Step 5: El puerto, el adaptador, el binding y el fixture**

`EloquentPropiedadesDeSocios` con un método privado `activas(): Builder` (tabla según motor, `where('activa', true)`) que usan las dos operaciones; `contarPorSocio([])` devuelve `[]` sin consultar; el conteo es una sola consulta con `whereIn` + `groupBy('codigo_de_socio')`. Binding en `ModulosServiceProvider`. `PropiedadesDeEjemplo::sembrar()` inserta las cinco filas de «Datos de ejemplo».

- [ ] **Step 6: Verde**

Run: `vendor/bin/pest tests/Unit/Persistence/LotesDeScriptTest.php tests/Feature/Maestros/PropiedadesDeSociosTest.php`
Expected: PASS. Después `composer test`, `composer stan`, `composer lint -- --test` en verde.

- [ ] **Step 7: Contra SQL Server local**

`docker compose -f compose.yaml up -d sqlserver`, `docker compose -f compose.yaml build app`, `docker compose -f compose.yaml run --rm --no-deps -T app php artisan migrate --force`. Con `sqlcmd` contra `maestros` (el script por archivo con `docker cp`, no por `-Q`, por el escape de las barras en Git Bash):
- `INSERT ... VALUES (N'PROP-X ', N'Lote X', N'C-004871')` → falla por `CK_propiedad_id`.
- `INSERT ... VALUES (N'PROP-X', N' Lote X', N'C-004871')` → falla por `CK_propiedad_nombre`.
- Dos `INSERT` con `N'PROP-X'` para socios distintos → el segundo falla por `PK_propiedad`.
- `DELETE FROM maestros.propiedad WHERE id_propiedad = N'PROP-X'` para dejarla vacía.

- [ ] **Step 8: Commit**

```bash
git add database/sql/maestros-propiedades.sql app/Persistence/LotesDeScript.php database/migrations/maestros src/Maestros/Application/Propiedades src/Maestros/Infrastructure/Persistence/EloquentPropiedadesDeSocios.php app/Providers/ModulosServiceProvider.php tests/Soporte/PropiedadesDeEjemplo.php tests/Unit/Persistence/LotesDeScriptTest.php tests/Feature/Maestros/PropiedadesDeSociosTest.php
git commit -m "feat(maestros): la tabla de propiedades del socio y su lectura"
```

---

### Task 2: El endpoint

**Files:**
- Create: `src/Maestros/Application/Propiedades/ListarPropiedades/ListarPropiedades.php`
- Create: `src/Maestros/Application/Propiedades/ListarPropiedades/ListarPropiedadesHandler.php`
- Create: `src/Maestros/Presentation/Http/PropiedadesController.php`
- Modify: `src/Maestros/Presentation/Http/routes.php` (dentro de `auth.token`, después de `socios`)
- Modify: `app/Providers/CoreServiceProvider.php` (junto a `ObtenerContexto`)
- Modify: `tests/Feature/ContratoTest.php` (`PENDIENTES` queda vacío)
- Modify: `tests/Feature/ContratoDeRespuestasTest.php`
- Test: `tests/Feature/Maestros/PropiedadesTest.php`

**Interfaces:**
- Consumes: `PropiedadesDeSocios::deSocio()`, `PropiedadDelSocio`, `PropiedadesDeEjemplo::sembrar()` (Task 1).
- Produces:
  - `ListarPropiedades(IdDePersona $persona, CodigoDeSocio $socio)` — `final readonly`, implementa `Request` y `ConAlcanceDeSocio` (`persona()` y `cardCode()` devuelven esos dos).
  - `ListarPropiedadesHandler` → `ResultWithValue<list<PropiedadDelSocio>>`.
  - Ruta `GET v1/socios/{cardCode}/propiedades` → `PropiedadesController` (invocable, `__invoke(Request $peticion, string $cardCode): JsonResponse`).

- [ ] **Step 1: Tests en rojo**

`PropiedadesTest`, con el `beforeEach` de `SociosTest` (semilla + `VerificadorFalso('p-8f2b1c40')`) más `PropiedadesDeEjemplo::sembrar()`, y `Authorization: Bearer token-bueno`:
- `lista las propiedades activas del socio, ordenadas por nombre` → `GET /v1/socios/C-004871/propiedades` 200, `data.items` es `[['id' => 'PROP-007', 'nombre' => 'Lote 07 · Cuatro Cañadas'], ['id' => 'PROP-014', 'nombre' => 'Lote 14 · San Julián']]`, `error` null.
- `un socio del grupo sin propiedades responde una lista vacia` → `C-004873`: 200, `data.items` es `[]`.
- `un socio de otro grupo responde 403` → `C-005210`: 403, `error.code` es `['ACCESO_DENEGADO']`, `data` null.
- `un cardCode inexistente responde 403, no 404` → `C-999999`: 403 `ACCESO_DENEGADO`.
- `un cardCode de mas de 15 caracteres responde 403, no 400` → `str_repeat('C', 16)`: 403 `ACCESO_DENEGADO`.
- `sin token responde 401` → 401, `error.code` es `['TOKEN_INVALIDO']`.

`ContratoDeRespuestasTest`: un `it('las propiedades del socio', ...)` con dataset, como el del catálogo, contra la ruta `'/socios/{cardCode}/propiedades'`: `C-004871` con token → 200; `C-004873` con token → 200; `C-005210` con token → 403; `C-004871` sin token → 401. Cada uno sembrando con `PropiedadesDeEjemplo::sembrar()`.

`ContratoTest`: `const PENDIENTES = [];` (su docblock dice que se achica: queda vacío).

- [ ] **Step 2: Correr y ver el rojo**

Run: `vendor/bin/pest tests/Feature/Maestros/PropiedadesTest.php tests/Feature/ContratoDeRespuestasTest.php tests/Feature/ContratoTest.php`
Expected: FAIL: 404 en las rutas nuevas y la operación del contrato sin ruta.

- [ ] **Step 3: Petición, handler, controlador, ruta y registro**

El controlador arma `CodigoDeSocio::desde($cardCode)`; si lanza `DomainException` responde `Envelope::responder(Result::failure(AlcanceErrors::accesoDenegado()))` sin despachar (comentario: un código que ni siquiera es válido es un socio fuera de alcance, y el contrato no documenta 400 acá). Si no, despacha `ListarPropiedades(PersonaAutenticada::deLaPeticion($peticion), $codigo)` y responde `['items' => [['id' => ..., 'nombre' => ...], ...]]`. Handler registrado en `CoreServiceProvider`.

- [ ] **Step 4: Verde**

Run: `vendor/bin/pest tests/Feature/Maestros/PropiedadesTest.php tests/Feature/ContratoDeRespuestasTest.php tests/Feature/ContratoTest.php tests/Architecture`
Expected: PASS (incluido `AlcanceTest`). Después `composer test`, `composer stan`, `composer lint -- --test`.

- [ ] **Step 5: Commit**

```bash
git add src/Maestros/Application/Propiedades/ListarPropiedades src/Maestros/Presentation/Http app/Providers/CoreServiceProvider.php tests/Feature/Maestros/PropiedadesTest.php tests/Feature/ContratoTest.php tests/Feature/ContratoDeRespuestasTest.php
git commit -m "feat(maestros): GET /socios/{cardCode}/propiedades"
```

---

### Task 3: `cantidadPropiedades` cuenta de verdad

**Files:**
- Modify: `src/Maestros/Application/Contactos/ObtenerContexto/ObtenerContextoHandler.php`
- Test: `tests/Feature/Maestros/ObtenerContextoTest.php`, `tests/Feature/Maestros/SociosTest.php`

**Interfaces:**
- Consumes: `PropiedadesDeSocios::contarPorSocio()` (Task 1), la ruta de la Task 2.

- [ ] **Step 1: Tests en rojo**

`ObtenerContextoTest`: `cuenta las propiedades activas de cada socio del grupo` → con `PropiedadesDeEjemplo::sembrar()`, `array_column($contexto->socios, 'cantidadPropiedades', 'cardCode')` es `['C-004871' => 2, 'C-004872' => 1, 'C-004873' => 0]`. El test existente, sin propiedades sembradas, sigue esperando `0`.

`SociosTest`: `cantidadPropiedades es el largo de la lista de cada socio` → con `PropiedadesDeEjemplo::sembrar()`, para cada ítem de `GET /v1/socios`, `cantidadPropiedades` es igual a `count()` de `data.items` de `GET /v1/socios/{cardCode}/propiedades`; y al menos uno es mayor que 0 (si no, `0 == 0` pasaría sin probar nada).

- [ ] **Step 2: Correr y ver el rojo**

Run: `vendor/bin/pest tests/Feature/Maestros/ObtenerContextoTest.php tests/Feature/Maestros/SociosTest.php`
Expected: FAIL, `cantidadPropiedades` es 0 para `C-004871`.

- [ ] **Step 3: El handler cuenta**

`ObtenerContextoHandler` recibe `PropiedadesDeSocios`; una sola llamada a `contarPorSocio()` con los `CodigoDeSocio` de `porGrupo($grupo)`, y `cantidadPropiedades` = el conteo del socio o `0`. Se borra el comentario del «Supuesto S1».

- [ ] **Step 4: Verde**

Run: `vendor/bin/pest tests/Feature/Maestros/ObtenerContextoTest.php tests/Feature/Maestros/SociosTest.php`
Expected: PASS. Después `composer test`, `composer stan`, `composer lint -- --test`.

- [ ] **Step 5: Commit**

```bash
git add src/Maestros/Application/Contactos/ObtenerContexto/ObtenerContextoHandler.php tests/Feature/Maestros/ObtenerContextoTest.php tests/Feature/Maestros/SociosTest.php
git commit -m "feat(maestros): cantidadPropiedades cuenta las propiedades activas"
```

---

### Task 4: Datos de ejemplo, Postman y `CLAUDE.md`

**Files:**
- Create: `database/sql/maestros-propiedades-ejemplo.sql`
- Modify: `docs/ms-maestros.postman_collection.json`
- Modify: `CLAUDE.md`

**Interfaces:**
- Consumes: la tabla (Task 1), la ruta (Task 2).

- [ ] **Step 1: El script de ejemplo**

Las cinco filas de «Datos de ejemplo» con un `MERGE` sobre `id_propiedad` (idempotente). El encabezado dice que es **solo para local** y cómo correrlo:

```bash
docker cp database/sql/maestros-propiedades-ejemplo.sql ms-maestros-sqlserver-1:/tmp/
MSYS_NO_PATHCONV=1 docker exec ms-maestros-sqlserver-1 /opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P "Agro.Local.2026" -C -I -d maestros -i /tmp/maestros-propiedades-ejemplo.sql
```

- [ ] **Step 2: Contra SQL Server local**

Con la imagen de la Task 1: correr el script de ejemplo dos veces (la segunda no cambia nada); correr la consulta de verificación de `maestros-propiedades.sql` → 0 filas; y con un script PHP dentro del contenedor que resuelve `PropiedadesDeSocios` y `ObtenerContexto`:
- `deSocio(C-004871)` → `PROP-007`, `PROP-014`, en ese orden.
- `cantidadPropiedades` de `p-8f2b1c40` → `C-004871: 2`, `C-004872: 1`, `C-004873: 0`.

- [ ] **Step 3: Postman**

En «3b. Socios de negocio», el test guarda `pm.collectionVariables.set('cardCode', b.data.items[0].cardCode)`. Ítem nuevo «3c. Propiedades del socio» después de 3b: `GET {{baseUrl}}/v1/socios/{{cardCode}}/propiedades` con las mismas cabeceras que 3b, test de status 200 y de que cada ítem tiene `id` y `nombre`, y una descripción que diga que es el paso 2 de *Solicitar visita técnica* y que el `id` es el que viaja a `POST /solicitudes-visita`. La colección tiene que seguir siendo JSON válido (`php -r 'json_decode(file_get_contents("docs/ms-maestros.postman_collection.json"), flags: JSON_THROW_ON_ERROR);'`).

- [ ] **Step 4: `CLAUDE.md`**

Un punto en «Contratos que no se negocian»: las propiedades no son réplica de SAP, salen de una conciliación y se cargan por script sobre `database/sql/maestros-propiedades.sql`; el `id` es estable (viaja a ms-comercial y a los reportes de campo); el `nombre` es el mismo texto que `ReporteCampo.propiedad`; ms-comercial valida `propiedadAjena` contra este endpoint; después de cargar, la consulta de verificación del script tiene que dar cero filas.

- [ ] **Step 5: Commit**

```bash
git add database/sql/maestros-propiedades-ejemplo.sql docs/ms-maestros.postman_collection.json CLAUDE.md
git commit -m "docs: propiedades del socio, datos de ejemplo y Postman"
```
