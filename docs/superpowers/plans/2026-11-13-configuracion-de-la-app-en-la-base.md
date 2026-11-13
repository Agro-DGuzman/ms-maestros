# La configuración de la App, en la base — plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** que `/version`, `/bancos` y `/contactos/atencion-al-cliente` lean dos tablas de la base en lugar de variables de entorno, con las mismas respuestas.

**Architecture:** un adaptador nuevo del puerto `ConfiguracionDeLaApp`, `ConfiguracionDesdeLaBase`, lee `maestros.cuentas_bancarias` y el diccionario `maestros.configuracion_app` en cada pedido. El puerto, el dominio y el controlador no cambian. `ConfiguracionDesdeEntorno` y `config/app_movil.php` se borran.

**Tech Stack:** Laravel 13 / PHP 8.4, Pest, Larastan nivel 9, SQL Server (Azure SQL) en producción y SQLite en memoria en las pruebas.

**Spec:** `docs/superpowers/specs/2026-11-13-configuracion-de-la-app-en-la-base-design.md`

## Global Constraints

- `declare(strict_types=1);` en todo archivo nuevo de `src/`. Eloquent y `Illuminate\` solo en `Infrastructure`.
- Nombres de tabla: en SQL Server `maestros.cuentas_bancarias` y `maestros.configuracion_app`; en SQLite `maestros_cuentas_bancarias` y `maestros_configuracion_app` (mismo patrón `DB::getDriverName() === 'sqlsrv' ? … : …` que el resto de Maestros).
- Las 14 claves, en este orden: `titular.nit`, `titular.razon_social`, `atencion.telefono`, `atencion.area`, `version.android.minima`, `version.android.recomendada`, `version.android.tienda`, `version.ios.minima`, `version.ios.recomendada`, `version.ios.tienda`, `aviso.obligatoria.titulo`, `aviso.obligatoria.mensaje`, `aviso.sugerida.titulo`, `aviso.sugerida.mensaje`.
- Valores por defecto, textuales: razón social «Agropartners S.R.L.»; área «Atención al Cliente»; obligatoria «Actualización necesaria» / «Esta versión dejó de estar disponible. Actualiza para seguir usando la App.»; sugerida «Hay una versión nueva» / «Hay una versión nueva de la App. Puedes actualizar cuando quieras.».
- Un fallo de configuración es `Error::problem('CONFIGURACION_INCOMPLETA', 'El servicio no tiene esta información configurada.')`, con el detalle en el log como `'Configuración de la App incompleta'`, `['detalle' => …]`, igual que hoy.
- Los valores reales (cuentas, NIT, teléfono) nunca entran al repo.
- `composer test`, `composer stan` y `composer lint -- --test` en verde antes de cada commit. Comentarios y commits en español, explicando por qué. Sin push: la puesta en marcha es del usuario.

## Review Focus

1. Una fila de cuenta con banco o número en blanco (posible en SQLite, que no tiene los CHECK) tiene que dar `CONFIGURACION_INCOMPLETA`, no una cuenta vacía en la App. → Tarea 1, caso «una cuenta con banco en blanco».
2. Valores cargados con espacios alrededor (`' 1.8.0 '`, `' 67701468 '`) se recortan, como hoy con las variables. → Tarea 1, «los valores se recortan».
3. Si la tabla no existe o la base falla, la excepción sube: un 500 común, no `CONFIGURACION_INCOMPLETA`, para no confundir una base caída con un dato que falta. → Tarea 1, «si la base falla, la excepcion sube».
4. Antes, cambiar una cuenta era una revisión nueva; ahora es un `UPDATE` con el proceso vivo. El ETag de `/bancos` tiene que cambiar sin reiniciar nada. → Tarea 2, prueba del ETag.
5. La lista de claves del `CHECK` del script y la del código pueden divergir sin que nadie lo note, y una clave aceptada por la base quedaría sin leer. → Tarea 1, «el script acepta exactamente las claves que lee el codigo».

---

### Tarea 1: Las tablas y el adaptador que las lee

**Files:**
- Create: `database/sql/maestros-configuracion.sql`
- Create: `database/sql/maestros-configuracion-ejemplo.sql`
- Create: `database/migrations/maestros/2026_11_13_000100_crear_configuracion_de_la_app.php`
- Create: `src/Maestros/Infrastructure/Configuracion/ConfiguracionDesdeLaBase.php`
- Create: `tests/Soporte/ConfiguracionDeEjemplo.php`
- Test: `tests/Feature/Maestros/ConfiguracionDesdeLaBaseTest.php`

**Interfaces:**
- Consumes: el puerto `Maestros\Application\Configuracion\ConfiguracionDeLaApp` y el dominio existente (`PoliticaDeVersion`, `AvisoDeVersion`, `Version::desde`, `CuentaBancaria`, `CuentasParaPagar`, `CanalDeAtencion`, `Celular::desdeLocalBoliviano`).
- Produces:
  - `final readonly class ConfiguracionDesdeLaBase implements ConfiguracionDeLaApp`, con `__construct(LoggerInterface $log)` y `public const CLAVES` (`list<string>`, las 14 claves en el orden de Global Constraints).
  - `Tests\Soporte\ConfiguracionDeEjemplo`:
    - `sembrar(): void` carga las cuentas `Banco Nacional de Bolivia|10000006816033` (orden 1) y `Banco BISA|701-5032719-3-81` (orden 2), más `titular.nit=1013879029`, `atencion.telefono=67701468`, `version.android.minima=1.8.0`, `version.android.recomendada=2.1.0` y `version.android.tienda=https://play.google.com/store/apps/details?id=bo.com.agropartners.socio`. Nada de iOS, razón social, área ni avisos.
    - `poner(string $clave, string $valor): void` inserta o reemplaza.
    - `quitar(string $clave): void`.
    - `cuentas(array $cuentas): void`, con `list<array{banco: string, cuenta: string, orden: int}>`, borra todas y carga esas.

- [ ] **Paso 1: Escribir las pruebas que fallan**

`tests/Feature/Maestros/ConfiguracionDesdeLaBaseTest.php`, con `uses(RefreshDatabase::class)` y `ConfiguracionDeEjemplo::sembrar()` en `beforeEach`. El adaptador se pide con `new ConfiguracionDesdeLaBase(new LoggerEspia)` o `new NullLogger`:

```php
it('lee las cuentas en el orden de la columna orden, no en el de insercion', function () {
    ConfiguracionDeEjemplo::cuentas([
        ['banco' => 'Banco BISA', 'cuenta' => '701-5032719-3-81', 'orden' => 2],
        ['banco' => 'Banco Nacional de Bolivia', 'cuenta' => '10000006816033', 'orden' => 1],
    ]);
    $cuentas = adaptador()->cuentasParaPagar()->value();

    expect($cuentas->cuentas[0]->banco)->toBe('Banco Nacional de Bolivia')
        ->and($cuentas->cuentas[1]->numeroCuenta)->toBe('701-5032719-3-81')
        ->and($cuentas->nitDelTitular)->toBe('1013879029');
});

// Sin closures en el dataset: Pest las resuelve antes de pasarlas y la prueba
// recibiría su resultado, no la acción.
it('un valor que falta o esta mal cargado es un problema nuestro', function (string $clave, ?string $valor, string $metodo, array $argumentos) {
    $valor === null ? ConfiguracionDeEjemplo::quitar($clave) : ConfiguracionDeEjemplo::poner($clave, $valor);
    $resultado = adaptador()->{$metodo}(...$argumentos);

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('CONFIGURACION_INCOMPLETA');
})->with([
    'sin NIT' => ['titular.nit', null, 'cuentasParaPagar', []],
    'un NIT de espacios' => ['titular.nit', '   ', 'cuentasParaPagar', []],
    'sin telefono' => ['atencion.telefono', null, 'canalDeAtencion', []],
    'un telefono fijo' => ['atencion.telefono', '33445566', 'canalDeAtencion', []],
    'una version mal escrita' => ['version.android.minima', '1.8', 'politicaDeVersion', ['android']],
]);

it('sin cuentas, o con una en blanco, es un problema nuestro y no una lista vacia', function (array $cuentas) {
    ConfiguracionDeEjemplo::cuentas($cuentas);
    $resultado = adaptador()->cuentasParaPagar();

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('CONFIGURACION_INCOMPLETA');
})->with([
    'sin cuentas' => [[]],
    'una cuenta con banco en blanco' => [[['banco' => '   ', 'cuenta' => '1', 'orden' => 1]]],
]);

it('el detalle va al log y nombra la clave', function () {
    ConfiguracionDeEjemplo::quitar('titular.nit');
    $log = new LoggerEspia;
    (new ConfiguracionDesdeLaBase($log))->cuentasParaPagar();

    expect(json_encode($log->registros))->toContain('titular.nit');
});

it('una plataforma sin versiones deja pasar a todos', function () {
    $politica = adaptador()->politicaDeVersion('ios')->value();

    expect($politica->evaluar(Version::desde('0.0.1')))->toBe(EstadoDeVersion::Vigente)
        ->and($politica->urlTienda)->toBeNull();
});

it('sin razon social, area ni avisos salen los valores por defecto', function () {
    $politica = adaptador()->politicaDeVersion('android')->value();

    expect(adaptador()->cuentasParaPagar()->value()->razonSocialDelTitular)->toBe('Agropartners S.R.L.')
        ->and(adaptador()->canalDeAtencion()->value()->area)->toBe('Atención al Cliente')
        ->and($politica->aviso(EstadoDeVersion::ActualizacionObligatoria)->titulo)->toBe('Actualización necesaria')
        ->and($politica->aviso(EstadoDeVersion::ActualizacionObligatoria)->mensaje)->toBe('Esta versión dejó de estar disponible. Actualiza para seguir usando la App.')
        ->and($politica->aviso(EstadoDeVersion::ActualizacionSugerida)->titulo)->toBe('Hay una versión nueva')
        ->and($politica->aviso(EstadoDeVersion::ActualizacionSugerida)->mensaje)->toBe('Hay una versión nueva de la App. Puedes actualizar cuando quieras.');
});

it('un valor cargado pisa el valor por defecto', function () {
    ConfiguracionDeEjemplo::poner('aviso.obligatoria.titulo', 'Actualizá la App');

    expect(adaptador()->politicaDeVersion('android')->value()->aviso(EstadoDeVersion::ActualizacionObligatoria)->titulo)
        ->toBe('Actualizá la App');
});

it('los valores se recortan', function () {
    ConfiguracionDeEjemplo::poner('version.android.minima', ' 1.8.0 ');
    ConfiguracionDeEjemplo::poner('atencion.telefono', ' 67701468 ');

    expect(adaptador()->politicaDeVersion('android')->value()->minima->texto())->toBe('1.8.0')
        ->and(adaptador()->canalDeAtencion()->value()->whatsappUrl())->toBe('https://wa.me/59167701468');
});

it('una clave que el codigo no conoce se ignora', function () {
    // SQLite no tiene el CHECK de claves: la fila entra y no tiene que romper nada.
    DB::table('maestros_configuracion_app')->insert(['clave' => 'otra.cosa', 'valor' => 'x']);

    expect(adaptador()->cuentasParaPagar()->isFailure())->toBeFalse();
});

it('si la base falla, la excepcion sube', function () {
    Schema::drop('maestros_cuentas_bancarias');

    adaptador()->cuentasParaPagar();
})->throws(QueryException::class);

it('el script acepta exactamente las claves que lee el codigo', function () {
    $script = (string) file_get_contents(database_path('sql/maestros-configuracion.sql'));
    preg_match('/CK_configuracion_app_clave\s+CHECK\s*\(\s*clave\s+IN\s*\(([^)]*)\)/i', $script, $lista);
    preg_match_all("/N?'([a-z_.]+)'/", $lista[1] ?? '', $claves);

    expect(collect($claves[1])->sort()->values()->all())
        ->toBe(collect(ConfiguracionDesdeLaBase::CLAVES)->sort()->values()->all())
        ->and(ConfiguracionDesdeLaBase::CLAVES)->toHaveCount(14);
});
```

`adaptador()` es una función del archivo que devuelve `new ConfiguracionDesdeLaBase(new NullLogger)`. Los nombres de las funciones y helpers de prueba no pueden chocar con los de otros archivos: Pest carga todo junto (ver `CLAUDE.md`, «Cada archivo de prueba tiene que poder correr solo»).

- [ ] **Paso 2: Correrlas y ver que fallan**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Maestros/ConfiguracionDesdeLaBaseTest.php`
Expected: FAIL porque no existen la clase `ConfiguracionDesdeLaBase`, `Tests\Soporte\ConfiguracionDeEjemplo` y las tablas.

- [ ] **Paso 3: La estructura en SQL Server, `database/sql/maestros-configuracion.sql`**

Idempotente (`IF OBJECT_ID(...) IS NULL CREATE TABLE`), con un encabezado como el de `maestros-propiedades.sql` que diga:
- qué es y por qué se carga a mano;
- que las claves tienen que coincidir con `ConfiguracionDesdeLaBase::CLAVES`, y que una prueba lo exige;
- que la copia de SQLite vive en la migración, sin los `CHECK`;
- los `INSERT` inválidos que tienen que fallar (paso 7).

`maestros.cuentas_bancarias`:
- `id INT IDENTITY` como clave primaria;
- `banco NVARCHAR(100)` no vacío, con la técnica `LEN`/`DATALENGTH`/`RTRIM` de propiedades;
- `numero_cuenta VARCHAR(50)` con `NOT LIKE '%[^0-9-]%'` y `LIKE '%[0-9]%'`;
- `orden INT` con `UNIQUE`;
- `UNIQUE (banco, numero_cuenta)`.

`maestros.configuracion_app`:
- `clave VARCHAR(60)` como clave primaria, con `CONSTRAINT CK_configuracion_app_clave CHECK (clave IN (…las 14…))`. La prueba del script busca ese nombre exacto;
- `valor NVARCHAR(500)` no vacío;
- reglas por clave:
  - `atencion.telefono`: `[67]` seguido de 7 dígitos;
  - `version.%.minima` y `version.%.recomendada`: dígitos y dos puntos (`n.n.n`);
  - `version.%.tienda`: `LIKE 'https://_%'`.

- [ ] **Paso 4: La migración `2026_11_13_000100_crear_configuracion_de_la_app.php`**

Sigue la forma de `2026_09_29_000100_crear_propiedades.php`:
- en `sqlsrv`, ejecuta el script con `LotesDeScript::de('maestros-configuracion.sql')`;
- en SQLite, arma las dos tablas con Blueprint: mismas columnas, clave primaria y los dos `unique`, sin los `CHECK`;
- `down()` borra las dos tablas.

- [ ] **Paso 5: `ConfiguracionDesdeLaBase` y `ConfiguracionDeEjemplo`**

El adaptador:
- lee con el query builder: las cuentas `orderBy('orden')` y el diccionario completo, en una consulta cada uno;
- recorta cada valor y toma uno vacío como ausente;
- valida con el dominio (`Version::desde`, `Celular::desdeLocalBoliviano`);
- convierte un `DomainException` en `incompleta("<clave>: <descripción>")`, y una tabla sin cuentas en `incompleta('maestros.cuentas_bancarias está vacía')`;
- deja que las excepciones de la base suban;
- tiene los valores por defecto como constantes privadas, con el texto de Global Constraints.

El docblock explica por qué se lee en cada pedido (un valor mal cargado rompe solo su endpoint) y por qué existe `CLAVES` (la misma lista vive en el `CHECK` del script).

`ConfiguracionDeEjemplo` usa `DB::table` con el nombre según el motor.

- [ ] **Paso 6: Correr las pruebas y ver que pasan**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Maestros/ConfiguracionDesdeLaBaseTest.php`
Expected: PASS, 16 pruebas.

- [ ] **Paso 7: Los `CHECK` contra el SQL Server local, a mano**

Con Docker andando:

```sh
docker compose up -d --build sqlserver app
docker compose exec app php artisan migrate --force
```

Correr con `sqlcmd` (ver `CLAUDE.md`, «La imagen de Keycloak para la nube») cada `INSERT` inválido del encabezado. Tienen que fallar todos:
- una clave fuera de la lista;
- un valor de espacios;
- el teléfono `33445566`;
- la versión `1.8`;
- la tienda `http://…`;
- una cuenta con letras;
- un `orden` repetido;
- un banco en blanco.

Uno válido de cada tabla tiene que entrar; después se borra.
Expected: los inválidos fallan con un error de `CHECK` o de `UNIQUE`, y los válidos entran.

Si Docker no está disponible, se anota en el ledger como pendiente para el usuario antes del paso 2 de la puesta en marcha. No bloquea la tarea.

- [ ] **Paso 8: El ejemplo, `database/sql/maestros-configuracion-ejemplo.sql`**

- Un `MERGE` para cada tabla, con los valores de `ConfiguracionDeEjemplo::sembrar()` más `titular.razon_social`.
- El encabezado dice que es para el SQL Server local y para copiar: el usuario lo completa con los valores reales y lo corre en Azure **sin guardar la copia en el repo**.
- Cómo correrlo, con el mismo bloque `docker cp` + `sqlcmd` de `maestros-propiedades-ejemplo.sql`.

- [ ] **Paso 9: Compuertas y commit**

Run: `composer test`, `composer stan`, `composer lint -- --test`
Expected: los tres en verde.

```bash
git add database/sql/maestros-configuracion.sql database/sql/maestros-configuracion-ejemplo.sql database/migrations/maestros/2026_11_13_000100_crear_configuracion_de_la_app.php src/Maestros/Infrastructure/Configuracion/ConfiguracionDesdeLaBase.php tests/Soporte/ConfiguracionDeEjemplo.php tests/Feature/Maestros/ConfiguracionDesdeLaBaseTest.php
git commit -m "feat(configuracion): las cuentas y el diccionario de la App, en la base"
```

---

### Tarea 2: La App lee la base

**Files:**
- Modify: `app/Providers/ModulosServiceProvider.php` (el bind de `ConfiguracionDeLaApp`, cerca de la línea 118, y `versionesDe()`, cerca de la línea 213)
- Modify: `tests/Feature/Maestros/ConfiguracionDeLaAppTest.php`
- Modify: `tests/Feature/ContratoDeRespuestasTest.php` (el `config([...])` de «la configuracion de la App», cerca de la línea 139)
- Modify: `.env.example` (el bloque de la línea 130 a la 154: `MOVIL_*`, `BANCOS*`, `ATENCION_*` y sus comentarios)
- Modify: `CLAUDE.md` (líneas 97–104 y 255–257)
- Delete: `config/app_movil.php`, `src/Maestros/Infrastructure/Configuracion/ConfiguracionDesdeEntorno.php`, `tests/Unit/Maestros/ConfiguracionDesdeEntornoTest.php`

**Interfaces:**
- Consumes: `ConfiguracionDesdeLaBase::__construct(LoggerInterface $log)` y `ConfiguracionDeEjemplo::sembrar()`, `poner()`, `quitar()` y `cuentas()`, de la Tarea 1.
- Produces: nada que otra tarea use.

- [ ] **Paso 1: Pasar las pruebas de los endpoints a la base**

En `ConfiguracionDeLaAppTest.php`:
- `uses(RefreshDatabase::class)`;
- el `config([...])` del `beforeEach` pasa a ser `ConfiguracionDeEjemplo::sembrar()`.

Dos pruebas cambian:

```php
it('/bancos cambia de ETag cuando cambian las cuentas, sin reiniciar nada', function () {
    $antes = $this->getJson('/v1/bancos', AUTENTICADO)->headers->get('ETag');

    // Antes un cambio era una revisión nueva del contenedor; ahora es un
    // UPDATE con el proceso vivo, y la App no puede seguir con la lista vieja.
    ConfiguracionDeEjemplo::cuentas([['banco' => 'Banco Unión', 'cuenta' => '089489-001-1', 'orden' => 1]]);

    $despues = $this->getJson('/v1/bancos', AUTENTICADO);

    expect($despues->headers->get('ETag'))->not->toBe($antes)
        ->and($despues->json('data.items.0.banco'))->toBe('Banco Unión');
});

it('/bancos sin cuentas responde un problema nuestro, no una lista vacia', function () {
    ConfiguracionDeEjemplo::cuentas([]);

    $this->getJson('/v1/bancos', AUTENTICADO)
        ->assertStatus(500)
        ->assertJsonPath('error.code', ['CONFIGURACION_INCOMPLETA']);
});
```

En `ContratoDeRespuestasTest.php`, el `config([...])` de «la configuracion de la App» pasa a ser `ConfiguracionDeEjemplo::sembrar()`.

- [ ] **Paso 2: Correrlas y ver que fallan**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Maestros/ConfiguracionDeLaAppTest.php`
Expected: FAIL. `/bancos` y atención responden 500 `CONFIGURACION_INCOMPLETA`, porque el provider todavía lee las variables, que en la prueba están vacías.

- [ ] **Paso 3: El provider usa `ConfiguracionDesdeLaBase`**

`$this->app->bind(ConfiguracionDeLaApp::class, fn ($app): ConfiguracionDeLaApp => new ConfiguracionDesdeLaBase($app->make(LoggerInterface::class)));`

Se borran:
- `versionesDe()`;
- los `use` que quedan sin uso (`ConfiguracionDesdeEntorno`, `AvisoDeVersion` si nadie más lo usa);
- `config/app_movil.php`;
- `ConfiguracionDesdeEntorno.php`;
- su prueba unitaria;
- el bloque de `.env.example`.

El comentario del bind pasa a decir que se lee en cada pedido para que un valor mal cargado rompa solo su endpoint.

- [ ] **Paso 4: Correr y ver que pasan**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Maestros/ConfiguracionDeLaAppTest.php tests/Feature/ContratoDeRespuestasTest.php`
Expected: PASS.

Run: `grep -rn "app_movil\|ConfiguracionDesdeEntorno\|MOVIL_\|BANCOS\|ATENCION_TELEFONO" app src tests config .env.example`
Expected: sin resultados.

- [ ] **Paso 5: `CLAUDE.md`**

En «La configuración de la App falla distinto según lo que protege», la oración «`/version`, `/bancos` y `/contactos/atencion-al-cliente` leen variables de entorno (`config/app_movil.php`).» pasa a ser:

> `/version`, `/bancos` y `/contactos/atencion-al-cliente` leen dos tablas que se cargan a mano: `maestros.cuentas_bancarias` y el diccionario `maestros.configuracion_app` (`database/sql/maestros-configuracion.sql`, con su ejemplo al lado). El diccionario solo acepta las claves que el código lee (`ConfiguracionDesdeLaBase::CLAVES`), y una prueba exige que el script repita la misma lista. Los valores reales no van al repo.

Además:
- «Sin versiones configuradas» pasa a ser «Sin versiones cargadas».
- La última oración pasa a ser «Se leen en cada pedido, para que un valor mal cargado rompa solo su endpoint.».
- «Las migraciones que cambian la réplica o el catálogo van antes del push» pasa a ser «Las migraciones que cambian la réplica, el catálogo o la configuración de la App van antes del push».

- [ ] **Paso 6: Compuertas y commit**

Run: `composer test`, `composer stan`, `composer lint -- --test`
Expected: los tres en verde.

```bash
git add -A app config src tests .env.example CLAUDE.md
git commit -m "feat(configuracion): la App lee la base y las variables de entorno se van"
```

---

## Puesta en marcha (la hace el usuario, después de las dos tareas)

La imagen nueva lee las tablas, así que tienen que existir en Azure antes del push.

1. En el portal, anotar las variables `MOVIL_*` de `ms-maestros`, si hay.
2. Correr la migración contra Azure desde la imagen local, reconstruida con la migración nueva:
   ```sh
   set -a; . .env.azure; set +a
   export AZ_DB_HOST=$DB_HOST AZ_DB_DATABASE=$DB_DATABASE AZ_DB_USERNAME=$DB_USERNAME AZ_DB_PASSWORD=$DB_PASSWORD
   docker compose -f compose.yaml -f compose.azure.yaml build app
   docker compose -f compose.yaml -f compose.azure.yaml run --rm --no-deps app php artisan migrate --force
   ```
3. Copiar `database/sql/maestros-configuracion-ejemplo.sql` fuera del repo, completarlo con las cuentas, el NIT, el teléfono y las versiones del paso 1, y correrlo en Azure SQL.
4. `git push azure main` y `git push origin main`.
5. Correr la colección de Postman contra el APIM: `/bancos` y `/contactos/atencion-al-cliente` en verde.
6. Borrar del portal las variables viejas.
