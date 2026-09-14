# Correcciones del review · `ms-maestros` fase 1 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cerrar los once hallazgos del review de dos ejes: darle al servicio una transacción real, sacarle la fuga de tiempo al `POST /auth/otp`, hacer que los tests de arquitectura verifiquen algo, y limpiar el andamiaje que miente sobre lo que hace.

**Architecture:** Los cambios de comportamiento van primero (tareas 1 a 5) porque son los que pueden romper algo y quiero verlos en verde antes de tocar estructura. Después se corrigen las fronteras entre capas (6 a 9) y al final la limpieza mecánica (10 y 11). No se agrega ninguna funcionalidad nueva: todo lo que aparece acá o repara algo roto o borra algo muerto.

**Tech Stack:** PHP 8.4 · Laravel 13 · Pest 5 · Larastan nivel 9 · Pint · SQLite en memoria para pruebas · Keycloak

**Spec:**
- `docs/superpowers/specs/2026-09-14-ms-maestros-hallazgos-review.md` — los hallazgos, con el número `H*` que cada tarea cierra
- `docs/superpowers/specs/2026-09-11-ms-maestros-estructura-design.md` — el diseño original, que sigue mandando

## Global Constraints

- **PHP 8.4**, `declare(strict_types=1);` en **todos** los archivos de `src/`.
- **PSR-4:** `Core\` → `src/Core/`, `Maestros\` → `src/Maestros/`, `Identidad\` → `src/Identidad/`, `App\` → `app/`, `Tests\` → `tests/`.
- **Nombres:** andamiaje en inglés (`Entity`, `Repository`, `save`, `find`); negocio en español (`Socio`, `DesafioDeIngreso`). Sin prefijo `I` en interfaces.
- **`Core/` no referencia `Illuminate\`.** `Maestros/` no referencia `Identidad\`. El `Domain/` **y el `Application/`** de cualquier módulo no referencian `Illuminate\` ni Eloquent.
- **Envelope:** `{ data, success, error }`, exactamente esos tres campos, construido desde un `Result`. `success: true ⟹ error: null`; `success: false ⟹ data: null`.
- **Un solo control de autorización:** el alcance. Fuera de alcance → 403 `ACCESO_DENEGADO`, nunca 404 ni lista vacía, y **el alcance se verifica antes que la existencia**.
- **Desafío de ingreso:** 4 dígitos, 5 minutos, 5 intentos, un solo uso. Todo fallo de resolución responde el mismo `CODIGO_INVALIDO`.
- **`POST /auth/otp` responde lo mismo y en el mismo tiempo** exista o no el número.
- **No borrar `DomainEvent` ni `Entity::addDomainEvent()`**: son el core portado desde Java, están probados, y la fase 2 los usa.
- **Comandos:** `composer test` (unitarias + feature + arquitectura), `composer stan`, `composer lint`, `composer test:integration` (necesita Keycloak vivo).
- **Commits:** uno por tarea como mínimo, en español, formato `tipo: descripción`.

---

## Estructura de archivos

```
src/Core/
  Contracts/RequiereTransaccion.php          NUEVO  marcador: esta petición escribe
  Contracts/UnitOfWork.php                   BORRAR H9
  Contracts/NotificationPublisher.php        BORRAR H9
  Contracts/Repository.php                   MODIFICAR  se va $readOnly
  Mediator/Behaviors/TransaccionBehavior.php NUEVO  H1
src/Maestros/
  Domain/Grupos/GrupoRepository.php          NUEVO  H6
  Infrastructure/Persistence/
    EloquentUnitOfWork.php                   BORRAR H1/H9
    EventoDeLaravelPublisher.php             BORRAR H9
    EloquentGrupoRepository.php              NUEVO  H6
    TablaConEsquema.php                      NUEVO  trait, H10
  Application/Contactos/ObtenerContexto/
    ObtenerContextoHandler.php               MODIFICAR  H6, H10
  Infrastructure/Importacion/
    ImportarMaestrosCommand.php              MODIFICAR  H4, H11
src/Identidad/
  Application/Contracts/DirectorioDeContactos.php   MODIFICAR  H7
  Application/Contracts/DespachadorDeDesafio.php    NUEVO  H2
  Application/Auth/SolicitarDesafio/
    SolicitarDesafioHandler.php              MODIFICAR  H2
  Application/Auth/IniciarSesion/
    IniciarSesionHandler.php                 MODIFICAR  H2 (caso borde), H10
  Application/Habilitacion/DeshabilitarPersona/     NUEVO  H11
  Infrastructure/Whatsapp/
    DespachadorEnCola.php                    NUEVO  H2
    EnviarDesafioJob.php                     NUEVO  H2
  Infrastructure/Maestros/
    DirectorioDeContactosEnProceso.php       MODIFICAR  H7
  Infrastructure/Habilitacion/
    ConciliarIdentidadesCommand.php          MODIFICAR  H7
app/Http/MapaDeErroresHttp.php               MODIFICAR  H5
app/Providers/{Core,Modulos}ServiceProvider.php  MODIFICAR  varias
tests/Dobles/                                NUEVO  H8
tests/Architecture/{Alcance,Estructura}Test.php  REESCRIBIR  H3, H11
```

---

### Task 1: Transacción real en el pipeline

Cierra **H1**. Hoy `EloquentUnitOfWork::commit()` abre una transacción vacía que nadie invoca, así que ninguna escritura del servicio es atómica.

La corrección no es arreglar `commit()`: con Eloquent los repositorios escriben apenas se los llama, así que el lugar donde la transacción tiene sentido es el pipeline, envolviendo al handler entero. Eso además es lo que el diseño §3.2 pedía cuando listaba «validación, transacción y alcance» como los tres behaviors de la fase.

**Files:**
- Create: `src/Core/Contracts/RequiereTransaccion.php`
- Create: `src/Core/Mediator/Behaviors/TransaccionBehavior.php`
- Create: `src/Core/Mediator/Behaviors/DeshacerPorResultadoFallido.php`
- Modify: `src/Identidad/Application/Auth/IniciarSesion/IniciarSesion.php`
- Modify: `src/Identidad/Application/Auth/SolicitarDesafio/SolicitarDesafio.php`
- Modify: `src/Identidad/Application/Auth/RenovarSesion/RenovarSesion.php`
- Modify: `src/Identidad/Application/Auth/CerrarSesion/CerrarSesion.php`
- Modify: `src/Identidad/Application/Habilitacion/HabilitarPersona/HabilitarPersona.php`
- Modify: `app/Providers/CoreServiceProvider.php:40-42`
- Test: `tests/Feature/TransaccionBehaviorTest.php`

**Interfaces:**
- Consumes: `Core\Contracts\{PipelineBehavior, Request}`, `Core\Results\Result` (ya existen).
- Produces: `RequiereTransaccion` (interfaz marcadora, sin métodos); `TransaccionBehavior::__construct(ConnectionInterface $conexion)` con `handle(Request, Closure): Result`.

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/TransaccionBehaviorTest.php`:

```php
<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Contracts\RequiereTransaccion;
use Core\Results\Error;
use Core\Results\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maestros\Domain\Grupos\IdDeGrupo;
use Maestros\Domain\Socios\CodigoDeSocio;
use Maestros\Domain\Socios\RazonSocial;
use Maestros\Domain\Socios\Socio;
use Maestros\Domain\Socios\SocioRepository;

uses(RefreshDatabase::class);

final class EscrituraDoble implements Request, RequiereTransaccion
{
    public function __construct(public readonly bool $fallarAlFinal) {}
}

final class EscrituraDobleHandler implements RequestHandler
{
    public function __construct(private readonly SocioRepository $socios) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof EscrituraDoble);

        $this->socios->save(Socio::replica(
            CodigoDeSocio::desde('C-100'),
            RazonSocial::desde('Primero'),
            IdDeGrupo::desde('GRP-1'),
            new DateTimeImmutable('2026-09-14T12:00:00Z'),
        ));

        $this->socios->save(Socio::replica(
            CodigoDeSocio::desde('C-200'),
            RazonSocial::desde('Segundo'),
            IdDeGrupo::desde('GRP-1'),
            new DateTimeImmutable('2026-09-14T12:00:00Z'),
        ));

        return $peticion->fallarAlFinal
            ? Result::failure(Error::conflict('ALGO_SALIO_MAL', 'Se rompió al final'))
            : Result::success();
    }
}

final class EscrituraQueRevienta implements Request, RequiereTransaccion {}

final class EscrituraQueRevientaHandler implements RequestHandler
{
    public function __construct(private readonly SocioRepository $socios) {}

    public function handle(Request $peticion): Result
    {
        $this->socios->save(Socio::replica(
            CodigoDeSocio::desde('C-300'),
            RazonSocial::desde('Tercero'),
            IdDeGrupo::desde('GRP-1'),
            new DateTimeImmutable('2026-09-14T12:00:00Z'),
        ));

        throw new RuntimeException('boom');
    }
}

beforeEach(function () {
    $this->app->bind(Mediator::class, fn ($app) => new Core\Mediator\ContainerMediator(
        $app,
        [
            EscrituraDoble::class => EscrituraDobleHandler::class,
            EscrituraQueRevienta::class => EscrituraQueRevientaHandler::class,
        ],
        [Core\Mediator\Behaviors\TransaccionBehavior::class],
    ));
});

it('confirma las dos escrituras cuando el handler tiene exito', function () {
    app(Mediator::class)->send(new EscrituraDoble(fallarAlFinal: false));

    expect(app(SocioRepository::class)->find(CodigoDeSocio::desde('C-100')))->not->toBeNull()
        ->and(app(SocioRepository::class)->find(CodigoDeSocio::desde('C-200')))->not->toBeNull();
});

it('deshace TODAS las escrituras cuando el handler devuelve un Result fallido', function () {
    $resultado = app(Mediator::class)->send(new EscrituraDoble(fallarAlFinal: true));

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('ALGO_SALIO_MAL')
        ->and(app(SocioRepository::class)->find(CodigoDeSocio::desde('C-100')))->toBeNull()
        ->and(app(SocioRepository::class)->find(CodigoDeSocio::desde('C-200')))->toBeNull();
});

it('deshace las escrituras cuando el handler lanza', function () {
    expect(fn () => app(Mediator::class)->send(new EscrituraQueRevienta))
        ->toThrow(RuntimeException::class);

    expect(app(SocioRepository::class)->find(CodigoDeSocio::desde('C-300')))->toBeNull();
});

it('no abre transaccion para una peticion que no la pide', function () {
    $nivel = null;

    $this->app->bind(Mediator::class, fn ($app) => new Core\Mediator\ContainerMediator(
        $app,
        [],
        [Core\Mediator\Behaviors\TransaccionBehavior::class],
    ));

    $behavior = app(Core\Mediator\Behaviors\TransaccionBehavior::class);

    $behavior->handle(new class implements Request {}, function () use (&$nivel): Result {
        $nivel = DB::transactionLevel();

        return Result::success();
    });

    expect($nivel)->toBe(0);
});
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `composer test -- tests/Feature/TransaccionBehaviorTest.php`
Expected: FALLA con "Interface Core\Contracts\RequiereTransaccion not found".

- [ ] **Step 3: Escribir el marcador**

`src/Core/Contracts/RequiereTransaccion.php`:

```php
<?php

declare(strict_types=1);

namespace Core\Contracts;

/**
 * Marcador: esta petición escribe, así que el pipeline la envuelve en una
 * transacción. Las consultas no lo implementan — abrir una transacción para
 * leer sostiene bloqueos en SQL Server sin ganar nada.
 */
interface RequiereTransaccion {}
```

- [ ] **Step 4: Escribir el behavior**

`src/Core/Mediator/Behaviors/TransaccionBehavior.php`:

```php
<?php

declare(strict_types=1);

namespace Core\Mediator\Behaviors;

use Closure;
use Core\Contracts\PipelineBehavior;
use Core\Contracts\Request;
use Core\Contracts\RequiereTransaccion;
use Core\Results\Result;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;

/**
 * La transacción vive acá y no en un UnitOfWork: con Eloquent los repositorios
 * escriben apenas se los llama, así que el único punto que envuelve a todas
 * las escrituras de un caso de uso es el pipeline.
 *
 * Un `Result` fallido también deshace: un caso de uso que devuelve un error
 * después de haber escrito la mitad es exactamente el estado que la
 * transacción existe para evitar.
 */
final readonly class TransaccionBehavior implements PipelineBehavior
{
    public function __construct(private ConnectionInterface $conexion) {}

    public function handle(Request $peticion, Closure $siguiente): Result
    {
        if (! $peticion instanceof RequiereTransaccion) {
            return $siguiente($peticion);
        }

        $resultado = null;

        try {
            $this->conexion->transaction(function () use ($peticion, $siguiente, &$resultado): void {
                $resultado = $siguiente($peticion);

                if ($resultado->isFailure()) {
                    // Sale por excepción para que Laravel haga el rollback, y
                    // se atrapa abajo para devolver el Result tal cual vino.
                    throw new DeshacerPorResultadoFallido();
                }
            });
        } catch (DeshacerPorResultadoFallido) {
            assert($resultado instanceof Result);

            return $resultado;
        }

        if (! $resultado instanceof Result) {
            throw new RuntimeException('El handler no devolvió un Result');
        }

        return $resultado;
    }
}
```

`src/Core/Mediator/Behaviors/DeshacerPorResultadoFallido.php`:

```php
<?php

declare(strict_types=1);

namespace Core\Mediator\Behaviors;

use RuntimeException;

/**
 * Señal interna de `TransaccionBehavior`: es la única forma de que Laravel
 * deshaga la transacción cuando el handler devolvió un `Result` fallido en vez
 * de lanzar. Nunca escapa del behavior, que la atrapa y devuelve el `Result`.
 *
 * @internal
 */
final class DeshacerPorResultadoFallido extends RuntimeException {}
```

- [ ] **Step 5: Marcar las peticiones que escriben**

Agregar `use Core\Contracts\RequiereTransaccion;` y `, RequiereTransaccion` a la declaración de estas cinco clases —son todas las que escriben— dejando el resto del archivo igual:

- `src/Identidad/Application/Auth/SolicitarDesafio/SolicitarDesafio.php` → `final readonly class SolicitarDesafio implements Request, RequiereTransaccion`
- `src/Identidad/Application/Auth/IniciarSesion/IniciarSesion.php` → `final readonly class IniciarSesion implements Request, RequiereTransaccion`
- `src/Identidad/Application/Auth/RenovarSesion/RenovarSesion.php` → `final readonly class RenovarSesion implements Request, RequiereTransaccion`
- `src/Identidad/Application/Auth/CerrarSesion/CerrarSesion.php` → `final readonly class CerrarSesion implements Request, RequiereTransaccion`
- `src/Identidad/Application/Habilitacion/HabilitarPersona/HabilitarPersona.php` → `final readonly class HabilitarPersona implements Request, RequiereTransaccion`

`ObtenerContexto` y `BuscarPorCelular` **no** se marcan: son consultas.

- [ ] **Step 6: Registrar el behavior antes del de alcance**

En `app/Providers/CoreServiceProvider.php`, reemplazar la constante `BEHAVIORS`:

```php
    /** @var list<class-string> */
    public const BEHAVIORS = [
        TransaccionBehavior::class,
        AlcanceBehavior::class,
    ];
```

y agregar el import `use Core\Mediator\Behaviors\TransaccionBehavior;`.

El orden importa: la transacción envuelve al alcance, así que un rechazo por alcance deshace cualquier escritura que un behavior posterior hubiera hecho.

- [ ] **Step 7: Ejecutar y verificar que pasan**

Run: `composer test -- tests/Feature/TransaccionBehaviorTest.php`
Expected: PASA, 4 tests.

- [ ] **Step 8: Verificar que no se rompió nada**

Run: `composer test`
Expected: toda la batería en verde.

- [ ] **Step 9: Commit**

```bash
git add src/Core/Contracts/RequiereTransaccion.php src/Core/Mediator/Behaviors/TransaccionBehavior.php src/Identidad/Application app/Providers/CoreServiceProvider.php tests/Feature/TransaccionBehaviorTest.php
git commit -m "fix: transaccion real en el pipeline, con rollback tambien en Result fallido"
```

---

### Task 2: Borrar el andamiaje que miente

Cierra **H9**. Con la transacción ya resuelta en la tarea 1, `UnitOfWork` no tiene ningún trabajo que hacer, y `NotificationPublisher` no tiene a quién publicarle porque ningún agregado emite eventos todavía.

`DomainEvent` y `Entity::addDomainEvent()` **se quedan**: son el core portado desde Java, tienen pruebas propias, y la fase 2 los usa cuando llegue la ingesta de eventos de SAP. Lo que se borra es la plomería que los rodea y que hoy no conecta con nada.

**Files:**
- Delete: `src/Core/Contracts/UnitOfWork.php`
- Delete: `src/Core/Contracts/NotificationPublisher.php`
- Delete: `src/Maestros/Infrastructure/Persistence/EloquentUnitOfWork.php`
- Delete: `src/Maestros/Infrastructure/Persistence/EventoDeLaravelPublisher.php`
- Modify: `src/Core/Contracts/Repository.php`
- Modify: `src/Maestros/Infrastructure/Persistence/EloquentSocioRepository.php`
- Modify: `src/Maestros/Infrastructure/Persistence/EloquentContactoRepository.php`
- Modify: `src/Identidad/Infrastructure/Persistence/EloquentDesafioRepository.php`
- Modify: `src/Identidad/Infrastructure/Persistence/EloquentSesionRepository.php`
- Modify: `src/Maestros/Application/Contactos/ObtenerContexto/ObtenerContextoHandler.php:30,38`
- Modify: `src/Maestros/Infrastructure/Alcance/ResolutorPorGrupo.php:29,35,36`
- Modify: `app/Providers/ModulosServiceProvider.php:55`

**Interfaces:**
- Consumes: nada nuevo.
- Produces: `Repository` queda con `find(EntityId $id): ?AggregateRoot`, `add(AggregateRoot): void`, `save(AggregateRoot): void`. **Se va el parámetro `$readOnly`**, que las cuatro implementaciones ignoraban mientras seis llamadas lo pasaban en `true`.

- [ ] **Step 1: Comprobar que nadie usa lo que se va a borrar**

Run:

```bash
grep -rn "UnitOfWork\|NotificationPublisher\|EventoDeLaravelPublisher" --include=*.php src app tests
```

Expected: solo las cuatro declaraciones que se borran, más el `bind` de `ModulosServiceProvider.php:55`. **Si aparece cualquier otro uso, detener la tarea y avisar** — significa que algo cambió desde el review.

- [ ] **Step 2: Borrar los cuatro archivos**

```bash
git rm src/Core/Contracts/UnitOfWork.php \
       src/Core/Contracts/NotificationPublisher.php \
       src/Maestros/Infrastructure/Persistence/EloquentUnitOfWork.php \
       src/Maestros/Infrastructure/Persistence/EventoDeLaravelPublisher.php
```

- [ ] **Step 3: Sacar el `bind` huérfano**

En `app/Providers/ModulosServiceProvider.php`, borrar estas dos líneas y sus `use` correspondientes:

```php
$this->app->bind(NotificationPublisher::class, EventoDeLaravelPublisher::class);
$this->app->bind(UnitOfWork::class, EloquentUnitOfWork::class);
```

- [ ] **Step 4: Sacar `$readOnly` del contrato**

`src/Core/Contracts/Repository.php`:

```php
<?php

declare(strict_types=1);

namespace Core\Contracts;

use Core\Domain\AggregateRoot;

interface Repository
{
    public function find(EntityId $id): ?AggregateRoot;

    public function add(AggregateRoot $agregado): void;

    /**
     * Reemplaza el agregado si ya existe, lo crea si no.
     * Hace falta porque las réplicas son inmutables: no hay nada que mutar
     * entre cargar y confirmar, y una réplica que vuelve a llegar reemplaza.
     */
    public function save(AggregateRoot $agregado): void;
}
```

- [ ] **Step 5: Sacar `$readOnly` de las cuatro implementaciones y de las seis llamadas**

En `EloquentSocioRepository`, `EloquentContactoRepository`, `EloquentDesafioRepository` y `EloquentSesionRepository`, cambiar la firma de `find(EntityId $id, bool $readOnly = false)` a `find(EntityId $id)`.

En `ObtenerContextoHandler.php:30,38` y `ResolutorPorGrupo.php:29,35,36`, borrar el argumento `readOnly: true` de las cinco llamadas.

Run:

```bash
grep -rn "readOnly" --include=*.php src app tests
```

Expected: sin resultados.

- [ ] **Step 6: Ejecutar la batería**

Run: `composer test && composer stan`
Expected: todo en verde. PHPStan tiene que dejar de reportar el parámetro sin uso.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "refactor: borrar UnitOfWork y NotificationPublisher sin uso, y el parametro readOnly que se ignoraba"
```

---

### Task 3: `POST /auth/otp` sin fuga de tiempo

Cierra **H2**. Hoy `SolicitarDesafioHandler.php:46-52` resuelve la persona antes de responder y sale temprano para un número desconocido, sin `INSERT` ni llamada a WhatsApp. La forma de la respuesta es constante; el tiempo no, y esa diferencia dice qué números están registrados.

La corrección: el handler hace **exactamente el mismo trabajo** en los dos casos —genera dígitos, persiste el desafío, encola el envío— y la búsqueda de la persona se muda adentro del trabajo encolado, que corre fuera del ciclo de la petición.

**Files:**
- Create: `src/Identidad/Application/Contracts/DespachadorDeDesafio.php`
- Create: `src/Identidad/Infrastructure/Whatsapp/DespachadorEnCola.php`
- Create: `src/Identidad/Infrastructure/Whatsapp/EnviarDesafioJob.php`
- Modify: `src/Identidad/Application/Auth/SolicitarDesafio/SolicitarDesafioHandler.php`
- Modify: `src/Identidad/Application/Auth/IniciarSesion/IniciarSesionHandler.php:84-90`
- Modify: `app/Providers/ModulosServiceProvider.php`
- Modify: `.env.example`
- Test: `tests/Feature/Identidad/SolicitarDesafioTest.php` (reescribir dos casos)
- Test: `tests/Feature/Identidad/EnviarDesafioJobTest.php`

**Interfaces:**
- Consumes: `DesafioRepository`, `DesafioDeIngreso`, `IdDeDesafio`, `DesafioErrors`, `RelojDelSistema`, `Celular` (ya existen).
- Produces: `DespachadorDeDesafio::despachar(Celular $celular, string $digitos): void`; `EnviarDesafioJob::__construct(string $celularE164, string $digitos)` con `handle(DirectorioDeContactos, EnviadorDeDesafio): void`.
- `SolicitarDesafioHandler` deja de recibir `DirectorioDeContactos` y `EnviadorDeDesafio`; recibe `DespachadorDeDesafio`.

- [ ] **Step 1: Reescribir los dos casos del test que cambian**

En `tests/Feature/Identidad/SolicitarDesafioTest.php`, reemplazar los casos `'manda el WhatsApp solo si el numero esta registrado'` y `'guarda el desafio solo cuando hay a quien mandarlo'` por estos tres, y agregar `use Identidad\Infrastructure\Whatsapp\EnviarDesafioJob;` y `use Illuminate\Support\Facades\Queue;` arriba:

```php
it('encola el envio para cualquier numero, registrado o no', function () {
    Queue::fake();

    $this->postJson('/v1/auth/otp', ['telefono' => '70741828'])->assertStatus(200);
    $this->postJson('/v1/auth/otp', ['telefono' => '79999999'])->assertStatus(200);

    Queue::assertPushed(EnviarDesafioJob::class, 2);
});

it('persiste el desafio para cualquier numero, registrado o no', function () {
    Queue::fake();

    $conocido = $this->postJson('/v1/auth/otp', ['telefono' => '70741828']);
    $desconocido = $this->postJson('/v1/auth/otp', ['telefono' => '79999999']);

    $repo = app(DesafioRepository::class);

    expect($repo->find(IdDeDesafio::desde((string) $conocido->json('data.idDeDesafio'))))->not->toBeNull()
        ->and($repo->find(IdDeDesafio::desde((string) $desconocido->json('data.idDeDesafio'))))->not->toBeNull();
});

it('el handler no consulta el directorio', function () {
    Queue::fake();

    $directorio = new class implements Identidad\Application\Contracts\DirectorioDeContactos
    {
        public int $consultas = 0;

        public function buscarPorCelular(Maestros\Domain\Contactos\Celular $celular): ?Maestros\Domain\Contactos\IdDePersona
        {
            $this->consultas++;

            return null;
        }

        public function contexto(Maestros\Domain\Contactos\IdDePersona $id): ?Maestros\Application\Contactos\ObtenerContexto\ContextoDeContacto
        {
            return null;
        }

        /** @return list<Maestros\Domain\Contactos\IdDePersona> */
        public function habilitadas(): array
        {
            return [];
        }
    };

    $this->app->instance(Identidad\Application\Contracts\DirectorioDeContactos::class, $directorio);

    $this->postJson('/v1/auth/otp', ['telefono' => '70741828'])->assertStatus(200);

    expect($directorio->consultas)->toBe(0);
});
```

> El tercer método `habilitadas()` lo agrega la Task 7. Si se ejecuta esta tarea antes que aquella, omitirlo del doble anónimo y agregarlo después.

- [ ] **Step 2: Escribir el test del trabajo encolado**

`tests/Feature/Identidad/EnviarDesafioJobTest.php`:

```php
<?php

declare(strict_types=1);

use Identidad\Application\Contracts\EnviadorDeDesafio;
use Identidad\Infrastructure\Whatsapp\EnviarDesafioJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Dobles\EnviadorEspia;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->enviador = new EnviadorEspia;
    $this->app->instance(EnviadorDeDesafio::class, $this->enviador);
});

it('envia cuando el celular corresponde a una persona registrada', function () {
    dispatch_sync(new EnviarDesafioJob('+59170741828', '4821'));

    expect($this->enviador->enviados)->toHaveCount(1)
        ->and($this->enviador->enviados[0]['celular'])->toBe('+59170741828')
        ->and($this->enviador->enviados[0]['digitos'])->toBe('4821');
});

it('no envia nada cuando el celular no esta registrado', function () {
    dispatch_sync(new EnviarDesafioJob('+59179999999', '4821'));

    expect($this->enviador->enviados)->toBe([]);
});
```

> `EnviadorEspia` vive en `tests/Dobles/` (Task 8). Si esta tarea se ejecuta antes, declarar la clase al final de este mismo archivo y moverla en la Task 8.

- [ ] **Step 3: Ejecutar y verificar que fallan**

Run: `composer test -- tests/Feature/Identidad`
Expected: FALLA con "Class Identidad\Infrastructure\Whatsapp\EnviarDesafioJob not found".

- [ ] **Step 4: Escribir el puerto**

`src/Identidad/Application/Contracts/DespachadorDeDesafio.php`:

```php
<?php

declare(strict_types=1);

namespace Identidad\Application\Contracts;

use Maestros\Domain\Contactos\Celular;

/**
 * Difiere el envío fuera del ciclo de la petición. Existe para que
 * `POST /auth/otp` tarde lo mismo exista o no el número: resolver la persona
 * y hablar con WhatsApp son las dos cosas caras, y las dos pasan acá adentro.
 */
interface DespachadorDeDesafio
{
    public function despachar(Celular $celular, string $digitos): void;
}
```

- [ ] **Step 5: Escribir el trabajo y su despachador**

`src/Identidad/Infrastructure/Whatsapp/EnviarDesafioJob.php`:

```php
<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Whatsapp;

use Identidad\Application\Contracts\DirectorioDeContactos;
use Identidad\Application\Contracts\EnviadorDeDesafio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maestros\Domain\Contactos\Celular;

final class EnviarDesafioJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** El celular viaja como string E.164: la cola serializa, y un objeto de valor no aporta acá. */
    public function __construct(
        private readonly string $celularE164,
        private readonly string $digitos,
    ) {}

    public function handle(DirectorioDeContactos $directorio, EnviadorDeDesafio $enviador): void
    {
        $celular = Celular::desdeLocalBoliviano($this->celularE164);

        // La búsqueda vive acá y no en el handler: es lo que hacía que el
        // endpoint tardara distinto según si el número existía.
        if ($directorio->buscarPorCelular($celular) === null) {
            return;
        }

        $enviador->enviar($celular, $this->digitos);
    }
}
```

`src/Identidad/Infrastructure/Whatsapp/DespachadorEnCola.php`:

```php
<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Whatsapp;

use Identidad\Application\Contracts\DespachadorDeDesafio;
use Maestros\Domain\Contactos\Celular;

final class DespachadorEnCola implements DespachadorDeDesafio
{
    public function despachar(Celular $celular, string $digitos): void
    {
        EnviarDesafioJob::dispatch($celular->e164(), $digitos);
    }
}
```

- [ ] **Step 6: Reescribir el handler**

`src/Identidad/Application/Auth/SolicitarDesafio/SolicitarDesafioHandler.php`:

```php
<?php

declare(strict_types=1);

namespace Identidad\Application\Auth\SolicitarDesafio;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Identidad\Application\Contracts\DespachadorDeDesafio;
use Identidad\Application\Contracts\RelojDelSistema;
use Identidad\Domain\Desafios\DesafioDeIngreso;
use Identidad\Domain\Desafios\DesafioErrors;
use Identidad\Domain\Desafios\DesafioRepository;
use Identidad\Domain\Desafios\IdDeDesafio;

/**
 * Hace exactamente el mismo trabajo exista o no la persona: genera dígitos,
 * persiste el desafío y encola el envío. Quién es el dueño del número se
 * resuelve dentro del trabajo encolado, fuera del ciclo de la petición, para
 * que el tiempo de respuesta no diga nada.
 */
final readonly class SolicitarDesafioHandler implements RequestHandler
{
    public function __construct(
        private DesafioRepository $desafios,
        private DespachadorDeDesafio $despachador,
        private RelojDelSistema $reloj,
        private int $maximoPorHora,
    ) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof SolicitarDesafio);

        $ahora = $this->reloj->ahora();

        // El límite va sobre la EMISIÓN, no solo sobre los intentos: sin esto
        // el atacante compra intentos pidiendo desafíos nuevos.
        if ($this->desafios->emitidosDesde($peticion->celular, $ahora->modify('-1 hour')) >= $this->maximoPorHora) {
            return ResultWithValue::failure(DesafioErrors::limiteDeTasa());
        }

        $id = IdDeDesafio::nuevo();
        $digitos = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        $this->desafios->add(DesafioDeIngreso::emitir($id, $peticion->celular, $digitos, $ahora));
        $this->despachador->despachar($peticion->celular, $digitos);

        return ResultWithValue::of($id);
    }
}
```

- [ ] **Step 7: Cerrar el caso borde del login**

Un desafío emitido para un número desconocido ahora existe en la base. Si alguien acertara sus cuatro dígitos, `IniciarSesionHandler` respondería `IDENTIDAD_NO_DISPONIBLE` (500), y esa diferencia vuelve a ser un oráculo. En `src/Identidad/Application/Auth/IniciarSesion/IniciarSesionHandler.php`, reemplazar el bloque de las líneas 84-90 por:

```php
        $persona = $this->directorio->buscarPorCelular($desafio->celular());

        // Un desafío emitido para un número que no es de nadie: mismo error
        // que un código equivocado, para no distinguir un caso del otro.
        if ($persona === null) {
            return ResultWithValue::failure(DesafioErrors::codigoInvalido());
        }

        $contrasena = $this->boveda->leer($persona);

        // Acá sí es un problema nuestro: la persona existe pero no tiene
        // credencial en el proveedor de identidad.
        if ($contrasena === null) {
            return ResultWithValue::failure(Error::problem(
                'IDENTIDAD_NO_DISPONIBLE',
                'La persona no tiene credencial en el proveedor de identidad',
            ));
        }
```

- [ ] **Step 8: Enlazar y configurar la cola**

En `app/Providers/ModulosServiceProvider.php::register()`, agregar:

```php
$this->app->bind(
    \Identidad\Application\Contracts\DespachadorDeDesafio::class,
    \Identidad\Infrastructure\Whatsapp\DespachadorEnCola::class,
);
```

En `.env.example`, cambiar `QUEUE_CONNECTION=sync` por:

```dotenv
# La cola tiene que ser asincrónica: con `sync` el envío vuelve a correr
# dentro de la petición y reaparece la fuga de tiempo de /auth/otp.
QUEUE_CONNECTION=database
```

- [ ] **Step 9: Ejecutar y verificar que pasan**

Run: `composer test -- tests/Feature/Identidad`
Expected: PASA. Los casos de `/auth/otp` que ya existían —respuesta idéntica para conocido y desconocido, 422 con teléfono inválido, 429 al pasar el límite— siguen en verde sin tocarlos.

- [ ] **Step 10: Commit**

```bash
git add src/Identidad app/Providers/ModulosServiceProvider.php .env.example tests/Feature/Identidad
git commit -m "fix: sacar la fuga de tiempo de /auth/otp encolando el envio del desafio"
```

---

### Task 4: `GrupoRepository` y Eloquent fuera de Application

Cierra **H6**. `ObtenerContextoHandler.php:16,50` importa y consulta `GrupoRecord` desde la capa Application, contra el diseño §10 («Eloquent vive solo en `Infrastructure`»). El repositorio que falta ya estaba nombrado en la estructura del plan original.

**Files:**
- Create: `src/Maestros/Domain/Grupos/GrupoRepository.php`
- Create: `src/Maestros/Infrastructure/Persistence/EloquentGrupoRepository.php`
- Modify: `src/Maestros/Application/Contactos/ObtenerContexto/ObtenerContextoHandler.php`
- Modify: `app/Providers/ModulosServiceProvider.php`
- Test: `tests/Feature/Maestros/GrupoRepositorioTest.php`

**Interfaces:**
- Consumes: `Core\Contracts\Repository` (ya sin `$readOnly`, Task 2), `GrupoEconomico`, `IdDeGrupo`, `GrupoRecord`.
- Produces: `GrupoRepository extends Repository`, cuyo `find(EntityId $id): ?GrupoEconomico`. `EloquentGrupoRepository` lo implementa. `ObtenerContextoHandler::__construct(ContactoRepository, SocioRepository, GrupoRepository)`.

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/Maestros/GrupoRepositorioTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Grupos\GrupoEconomico;
use Maestros\Domain\Grupos\GrupoRepository;
use Maestros\Domain\Grupos\IdDeGrupo;

uses(RefreshDatabase::class);

it('guarda un grupo y lo vuelve a cargar', function () {
    $repo = app(GrupoRepository::class);

    $repo->save(GrupoEconomico::replica(
        IdDeGrupo::desde('GRP-014'),
        'Grupo Monasterio',
        new DateTimeImmutable('2026-09-11T12:00:00Z'),
    ));

    $grupo = $repo->find(IdDeGrupo::desde('GRP-014'));

    expect($grupo)->toBeInstanceOf(GrupoEconomico::class)
        ->and($grupo->nombre())->toBe('Grupo Monasterio');
});

it('save reemplaza en vez de duplicar', function () {
    $repo = app(GrupoRepository::class);
    $marca = new DateTimeImmutable('2026-09-11T12:00:00Z');

    $repo->save(GrupoEconomico::replica(IdDeGrupo::desde('GRP-014'), 'Viejo', $marca));
    $repo->save(GrupoEconomico::replica(IdDeGrupo::desde('GRP-014'), 'Nuevo', $marca));

    expect($repo->find(IdDeGrupo::desde('GRP-014'))->nombre())->toBe('Nuevo');
});

it('devuelve null cuando el grupo no existe', function () {
    expect(app(GrupoRepository::class)->find(IdDeGrupo::desde('GRP-999')))->toBeNull();
});
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `composer test -- tests/Feature/Maestros/GrupoRepositorioTest.php`
Expected: FALLA con "Interface Maestros\Domain\Grupos\GrupoRepository not found".

- [ ] **Step 3: Escribir el contrato**

`src/Maestros/Domain/Grupos/GrupoRepository.php`:

```php
<?php

declare(strict_types=1);

namespace Maestros\Domain\Grupos;

use Core\Contracts\Repository;

interface GrupoRepository extends Repository {}
```

- [ ] **Step 4: Escribir la implementación**

`src/Maestros/Infrastructure/Persistence/EloquentGrupoRepository.php`:

```php
<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use Core\Contracts\EntityId;
use Core\Domain\AggregateRoot;
use DateTimeImmutable;
use Maestros\Domain\Grupos\GrupoEconomico;
use Maestros\Domain\Grupos\GrupoRepository;
use Maestros\Domain\Grupos\IdDeGrupo;

final class EloquentGrupoRepository implements GrupoRepository
{
    public function find(EntityId $id): ?GrupoEconomico
    {
        $record = GrupoRecord::query()->find($id->value());

        if ($record === null) {
            return null;
        }

        return GrupoEconomico::replica(
            IdDeGrupo::desde((string) $record->id_de_grupo),
            (string) $record->nombre,
            new DateTimeImmutable((string) $record->vigente_desde),
        );
    }

    public function add(AggregateRoot $agregado): void
    {
        assert($agregado instanceof GrupoEconomico);
        GrupoRecord::query()->create($this->aFila($agregado));
    }

    public function save(AggregateRoot $agregado): void
    {
        assert($agregado instanceof GrupoEconomico);
        GrupoRecord::query()->updateOrCreate(
            ['id_de_grupo' => $agregado->idDeGrupo()->value()],
            $this->aFila($agregado),
        );
    }

    /** @return array<string, string> */
    private function aFila(GrupoEconomico $grupo): array
    {
        return [
            'id_de_grupo' => $grupo->idDeGrupo()->value(),
            'nombre' => $grupo->nombre(),
            'vigente_desde' => $grupo->vigenteDesde()->format('Y-m-d H:i:s'),
            'importado_el' => (new DateTimeImmutable)->format('Y-m-d H:i:s'),
        ];
    }
}
```

- [ ] **Step 5: Exponer `vigenteDesde()` en el agregado**

`GrupoEconomico` guarda `vigenteDesde` pero no lo expone. Agregar a `src/Maestros/Domain/Grupos/GrupoEconomico.php`, junto a `nombre()`:

```php
    public function vigenteDesde(): DateTimeImmutable
    {
        return $this->vigenteDesde;
    }
```

- [ ] **Step 6: Sacar Eloquent del handler**

En `src/Maestros/Application/Contactos/ObtenerContexto/ObtenerContextoHandler.php`: borrar el import `use Maestros\Infrastructure\Persistence\GrupoRecord;`, agregar `use Maestros\Domain\Grupos\GrupoRepository;`, sumar la dependencia al constructor y reemplazar las dos líneas que consultaban el record.

Constructor:

```php
    public function __construct(
        private ContactoRepository $contactos,
        private SocioRepository $socios,
        private GrupoRepository $grupos,
    ) {}
```

Y donde decía `$nombre = GrupoRecord::query()->whereKey($grupo->value())->value('nombre');` más la línea siguiente:

```php
        $nombreDelGrupo = $this->grupos->find($grupo)?->nombre() ?? '';
```

- [ ] **Step 7: Enlazar en el contenedor**

En `app/Providers/ModulosServiceProvider.php::register()`:

```php
$this->app->bind(
    \Maestros\Domain\Grupos\GrupoRepository::class,
    \Maestros\Infrastructure\Persistence\EloquentGrupoRepository::class,
);
```

- [ ] **Step 8: Ejecutar y verificar que pasan**

Run: `composer test -- tests/Feature/Maestros`
Expected: PASA. `ObtenerContextoTest` sigue en verde sin tocarlo.

- [ ] **Step 9: Commit**

```bash
git add src/Maestros app/Providers/ModulosServiceProvider.php tests/Feature/Maestros/GrupoRepositorioTest.php
git commit -m "fix: GrupoRepository y Eloquent fuera de la capa Application"
```

---

### Task 5: La importación respeta que una réplica nunca retrocede

Cierra **H4**. `ImportarMaestrosCommand.php:79-97` hace `save()` incondicional: reejecutar la carga con un `vigenteDesde` más viejo pisa datos más nuevos. `Socio::debeReemplazarA()` existe, está probado, y no lo llama nadie.

**Files:**
- Modify: `src/Maestros/Infrastructure/Importacion/ImportarMaestrosCommand.php`
- Test: `tests/Feature/Maestros/ImportarMaestrosTest.php` (agregar dos casos)

**Interfaces:**
- Consumes: `Socio::debeReemplazarA(DateTimeImmutable): bool`, `PersonaDeContacto::debeReemplazarA(...)`, `GrupoEconomico::debeReemplazarA(...)` (los tres ya existen); `GrupoRepository` (Task 4).
- Produces: el comando informa cuántas filas se omitieron por ser más viejas; el mensaje final pasa a `'Importados %d grupos, %d socios y %d contactos. Omitidos por ser más viejos: %d.'`.

- [ ] **Step 1: Escribir los dos casos que faltan**

Agregar a `tests/Feature/Maestros/ImportarMaestrosTest.php`:

```php
it('no pisa un dato nuevo con una carga mas vieja', function () {
    $nuevo = tempnam(sys_get_temp_dir(), 'imp').'.json';
    file_put_contents($nuevo, json_encode([
        'vigenteDesde' => '2026-09-14T12:00:00Z',
        'grupos' => [['id' => 'GRP-014', 'nombre' => 'Grupo Monasterio']],
        'socios' => [['cardCode' => 'C-004871', 'razonSocial' => 'Razon NUEVA', 'grupoId' => 'GRP-014']],
    ]));

    $viejo = tempnam(sys_get_temp_dir(), 'imp').'.json';
    file_put_contents($viejo, json_encode([
        'vigenteDesde' => '2026-01-01T00:00:00Z',
        'grupos' => [['id' => 'GRP-014', 'nombre' => 'Grupo Monasterio']],
        'socios' => [['cardCode' => 'C-004871', 'razonSocial' => 'Razon VIEJA', 'grupoId' => 'GRP-014']],
    ]));

    $this->artisan('maestros:importar', ['archivo' => $nuevo])->assertExitCode(0);
    $this->artisan('maestros:importar', ['archivo' => $viejo])->assertExitCode(0);

    expect(app(SocioRepository::class)->find(CodigoDeSocio::desde('C-004871'))->razonSocial()->texto())
        ->toBe('Razon NUEVA');
});

it('informa cuantas filas se omitieron por viejas', function () {
    $archivo = database_path('semillas/maestros-ejemplo.json');

    $this->artisan('maestros:importar', ['archivo' => $archivo])->assertExitCode(0);

    // La segunda corrida tiene la misma marca: nada es más nuevo, todo se omite.
    $this->artisan('maestros:importar', ['archivo' => $archivo])
        ->expectsOutputToContain('Omitidos por ser más viejos: 5')
        ->assertExitCode(0);
});
```

Agregar arriba del archivo los imports que falten: `use Maestros\Domain\Socios\CodigoDeSocio;` y `use Maestros\Domain\Socios\SocioRepository;`.

> Cinco omitidos = 1 grupo + 3 socios + 1 contacto del archivo de semilla.

- [ ] **Step 2: Ejecutar y verificar que fallan**

Run: `composer test -- tests/Feature/Maestros/ImportarMaestrosTest.php`
Expected: FALLA — la razón social queda en «Razon VIEJA» y el mensaje no menciona omitidos.

- [ ] **Step 3: Comparar antes de escribir**

En `src/Maestros/Infrastructure/Importacion/ImportarMaestrosCommand.php`, reemplazar la firma de `handle` y los tres bucles. Pasa a recibir `GrupoRepository` y deja de usar `GrupoRecord`, así que también se borra ese import:

```php
    public function handle(
        SocioRepository $socios,
        ContactoRepository $contactos,
        GrupoRepository $grupos,
    ): int {
```

Y los bucles:

```php
        $omitidos = 0;

        foreach ($filasDeGrupos as $fila) {
            $grupo = GrupoEconomico::replica(
                IdDeGrupo::desde($fila['id'] ?? ''),
                $fila['nombre'] ?? '',
                $vigenteDesde,
            );

            $existente = $grupos->find($grupo->idDeGrupo());

            // Una réplica nunca retrocede: si lo que está guardado es igual de
            // nuevo o más, la fila del archivo se ignora.
            if ($existente !== null && ! $grupo->debeReemplazarA($existente->vigenteDesde())) {
                $omitidos++;

                continue;
            }

            $grupos->save($grupo);
        }

        foreach ($filasDeSocios as $fila) {
            $socio = Socio::replica(
                CodigoDeSocio::desde($fila['cardCode'] ?? ''),
                RazonSocial::desde($fila['razonSocial'] ?? ''),
                IdDeGrupo::desde($fila['grupoId'] ?? ''),
                $vigenteDesde,
            );

            $existente = $socios->find($socio->codigoDeSocio());

            if ($existente !== null && ! $socio->debeReemplazarA($existente->vigenteDesde())) {
                $omitidos++;

                continue;
            }

            $socios->save($socio);
        }

        foreach ($filasDeContactos as $fila) {
            $persona = PersonaDeContacto::replica(
                IdDePersona::desde($fila['id'] ?? ''),
                CodigoDeSocio::desde($fila['cardCode'] ?? ''),
                $fila['nombre'] ?? '',
                Celular::desdeLocalBoliviano($fila['celular'] ?? ''),
                isset($fila['habilitadaEl']) ? new DateTimeImmutable($fila['habilitadaEl']) : null,
                $vigenteDesde,
            );

            $existente = $contactos->find($persona->idDePersona());

            if ($existente !== null && ! $persona->debeReemplazarA($existente->vigenteDesde())) {
                $omitidos++;

                continue;
            }

            $contactos->save($persona);
        }

        $this->info(sprintf(
            'Importados %d grupos, %d socios y %d contactos. Omitidos por ser más viejos: %d.',
            count($filasDeGrupos),
            count($filasDeSocios),
            count($filasDeContactos),
            $omitidos,
        ));
```

De paso, renombrar las tres variables de las líneas 57-59, que hoy usan guion bajo para esquivar la colisión con los parámetros (**H11**):

```php
        $filasDeGrupos = $this->filas($raiz['grupos'] ?? null);
        $filasDeSocios = $this->filas($raiz['socios'] ?? null);
        $filasDeContactos = $this->filas($raiz['contactos'] ?? null);
```

- [ ] **Step 4: Exponer `vigenteDesde()` en la persona de contacto**

`PersonaDeContacto` guarda `vigenteDesde` y no lo expone. Agregar a `src/Maestros/Domain/Contactos/PersonaDeContacto.php`:

```php
    public function vigenteDesde(): DateTimeImmutable
    {
        return $this->vigenteDesde;
    }
```

- [ ] **Step 5: Ejecutar y verificar que pasan**

Run: `composer test -- tests/Feature/Maestros/ImportarMaestrosTest.php`
Expected: PASA, 6 tests. Los cuatro que ya existían —importa, reejecutable, archivo inexistente, JSON roto— siguen en verde.

- [ ] **Step 6: Commit**

```bash
git add src/Maestros tests/Feature/Maestros/ImportarMaestrosTest.php
git commit -m "fix: la importacion respeta debeReemplazarA y no pisa datos mas nuevos"
```

---

### Task 6: Un `FAILURE` sin código conocido responde 500

Cierra **H5**. `app/Http/MapaDeErroresHttp.php:31` usa 403 como valor por defecto de `ErrorType::Failure`, lo que hace que un error de programación se vea igual que un permiso denegado. Los tres códigos que sí son 401/403/429 ya están en la tabla de excepciones.

**Files:**
- Modify: `app/Http/MapaDeErroresHttp.php:31`
- Test: `tests/Unit/Http/EnvelopeTest.php` (ajustar un caso, agregar otro)

**Interfaces:**
- Consumes: `Core\Results\{Error, ErrorType}`.
- Produces: `MapaDeErroresHttp::status()` sin cambio de firma.

- [ ] **Step 1: Ajustar el test**

En `tests/Unit/Http/EnvelopeTest.php`, en el caso `'traduce cada tipo a su codigo http'`, cambiar la última línea de 403 a 500:

```php
        ->and(MapaDeErroresHttp::status(Error::failure('X', 'x')))->toBe(500);
```

Y agregar un caso nuevo debajo:

```php
it('un FAILURE de codigo desconocido no se confunde con acceso denegado', function () {
    expect(MapaDeErroresHttp::status(Error::failure('ALGO_RARO', 'x')))->toBe(500)
        ->and(MapaDeErroresHttp::status(Error::failure('ACCESO_DENEGADO', 'x')))->toBe(403);
});
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `composer test -- tests/Unit/Http/EnvelopeTest.php`
Expected: FALLA — hoy devuelve 403.

- [ ] **Step 3: Cambiar el valor por defecto**

En `app/Http/MapaDeErroresHttp.php`, la línea 31 y el docblock:

```php
    /**
     * `FAILURE` se parte en tres según el código —401, 403, 429—, así que
     * esta tabla los nombra y el tipo solo da el defecto. Un `FAILURE` que no
     * está acá es un error nuestro, no del cliente: 500, no 403.
     *
     * @var array<string, int>
     */
    private const POR_CODIGO = [
        'NO_AUTENTICADO' => 401,
        'ACCESO_DENEGADO' => 403,
        'LIMITE_DE_TASA' => 429,
    ];

    public static function status(Error $error): int
    {
        return self::POR_CODIGO[$error->code] ?? match ($error->type) {
            ErrorType::Validation => 422,
            ErrorType::NotFound => 404,
            ErrorType::Conflict => 409,
            ErrorType::Problem, ErrorType::Failure => 500,
        };
    }
```

- [ ] **Step 4: Ejecutar la batería**

Run: `composer test`
Expected: todo en verde. Los casos de 401 de `RenovarYCerrarTest` y el 403 del alcance siguen pasando porque van por código, no por tipo.

- [ ] **Step 5: Commit**

```bash
git add app/Http/MapaDeErroresHttp.php tests/Unit/Http/EnvelopeTest.php
git commit -m "fix: un FAILURE sin codigo conocido responde 500 y no 403"
```

---

### Task 7: `ConciliarIdentidadesCommand` pasa por el puerto

Cierra **H7**. `ConciliarIdentidadesCommand.php:11,24` itera `Maestros\Infrastructure\Persistence\ContactoRecord` directo, salteando `DirectorioDeContactos` — cuyo docblock declara que son «las dos únicas preguntas que Identidad le hace a Maestros». Es además la única consulta que cruza de un esquema al otro, justo lo que la ADR 0001 pide evitar.

**Files:**
- Modify: `src/Identidad/Application/Contracts/DirectorioDeContactos.php`
- Modify: `src/Identidad/Infrastructure/Maestros/DirectorioDeContactosEnProceso.php`
- Modify: `src/Identidad/Infrastructure/Habilitacion/ConciliarIdentidadesCommand.php`
- Create: `src/Maestros/Application/Contactos/ListarHabilitadas/ListarHabilitadas.php`
- Create: `src/Maestros/Application/Contactos/ListarHabilitadas/ListarHabilitadasHandler.php`
- Modify: `src/Maestros/Domain/Contactos/ContactoRepository.php`
- Modify: `src/Maestros/Infrastructure/Persistence/EloquentContactoRepository.php`
- Modify: `app/Providers/CoreServiceProvider.php`
- Test: `tests/Feature/Identidad/ConciliarIdentidadesTest.php`

**Interfaces:**
- Consumes: `Mediator`, `IdDePersona`, `DirectorioDeIdentidades`, `BovedaDeContrasenas`.
- Produces:
  - `DirectorioDeContactos::habilitadas(): list<IdDePersona>` — tercera y última pregunta del puerto.
  - `ContactoRepository::habilitadas(): list<PersonaDeContacto>`.
  - `ListarHabilitadas` (Request, sin campos) y su handler, que devuelve `ResultWithValue<list<IdDePersona>>`.

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/Identidad/ConciliarIdentidadesTest.php`:

```php
<?php

declare(strict_types=1);

use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\DirectorioDeIdentidades;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\IdDePersona;
use Tests\Dobles\DirectorioFalso;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->directorio = new DirectorioFalso;
    $this->app->instance(DirectorioDeIdentidades::class, $this->directorio);
});

it('reporta a quien le falta el usuario en el directorio', function () {
    $this->artisan('identidad:conciliar')
        ->expectsOutputToContain('p-8f2b1c40')
        ->assertExitCode(1);
});

it('no reporta nada cuando directorio y boveda estan completos', function () {
    $persona = IdDePersona::desde('p-8f2b1c40');
    $this->directorio->crearOActualizar($persona, 'una-contrasena');
    app(BovedaDeContrasenas::class)->guardar($persona, 'una-contrasena');

    $this->artisan('identidad:conciliar')
        ->expectsOutputToContain('Sin discrepancias')
        ->assertExitCode(0);
});

it('reporta a quien tiene usuario pero no contrasena guardada', function () {
    $persona = IdDePersona::desde('p-8f2b1c40');
    $this->directorio->crearOActualizar($persona, 'una-contrasena');

    $this->artisan('identidad:conciliar')
        ->expectsOutputToContain('Sin contraseña guardada')
        ->assertExitCode(1);
});
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `composer test -- tests/Feature/Identidad/ConciliarIdentidadesTest.php`
Expected: FALLA con "Class Tests\Dobles\DirectorioFalso not found" (la Task 8 crea el directorio; si se ejecuta esta antes, declarar el doble al final del archivo y moverlo después).

- [ ] **Step 3: Agregar `habilitadas()` al repositorio de contactos**

`src/Maestros/Domain/Contactos/ContactoRepository.php`:

```php
<?php

declare(strict_types=1);

namespace Maestros\Domain\Contactos;

use Core\Contracts\Repository;

interface ContactoRepository extends Repository
{
    public function porCelular(Celular $celular): ?PersonaDeContacto;

    /** @return list<PersonaDeContacto> */
    public function habilitadas(): array;
}
```

Y en `EloquentContactoRepository`, junto a `porCelular()`:

```php
    /** @return list<PersonaDeContacto> */
    public function habilitadas(): array
    {
        return ContactoRecord::query()
            ->whereNotNull('habilitada_el')
            ->orderBy('id_de_persona')
            ->get()
            ->map(fn (ContactoRecord $r): PersonaDeContacto => $this->aDominio($r))
            ->all();
    }
```

- [ ] **Step 4: Escribir la consulta de Maestros**

`src/Maestros/Application/Contactos/ListarHabilitadas/ListarHabilitadas.php`:

```php
<?php

declare(strict_types=1);

namespace Maestros\Application\Contactos\ListarHabilitadas;

use Core\Contracts\Request;

final readonly class ListarHabilitadas implements Request {}
```

`src/Maestros/Application/Contactos/ListarHabilitadas/ListarHabilitadasHandler.php`:

```php
<?php

declare(strict_types=1);

namespace Maestros\Application\Contactos\ListarHabilitadas;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Contactos\PersonaDeContacto;

final readonly class ListarHabilitadasHandler implements RequestHandler
{
    public function __construct(private ContactoRepository $contactos) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof ListarHabilitadas);

        return ResultWithValue::of(array_map(
            static fn (PersonaDeContacto $p): IdDePersona => $p->idDePersona(),
            $this->contactos->habilitadas(),
        ));
    }
}
```

Registrarlo en `CoreServiceProvider::HANDLERS`:

```php
        ListarHabilitadas::class => ListarHabilitadasHandler::class,
```

con sus dos `use` correspondientes.

- [ ] **Step 5: Agregar la tercera pregunta al puerto**

En `src/Identidad/Application/Contracts/DirectorioDeContactos.php`, agregar dentro de la interfaz:

```php
    /**
     * Las personas a las que Agropartners ya les concedió acceso. La necesita
     * la conciliación, y va acá y no por SQL para que no haya una sola
     * consulta que cruce de un esquema al otro.
     *
     * @return list<IdDePersona>
     */
    public function habilitadas(): array;
```

Y en `src/Identidad/Infrastructure/Maestros/DirectorioDeContactosEnProceso.php`, agregar el método y el import de `ListarHabilitadas`:

```php
    /** @return list<IdDePersona> */
    public function habilitadas(): array
    {
        $resultado = $this->mediator->send(new ListarHabilitadas);

        if ($resultado->isFailure()) {
            return [];
        }

        assert($resultado instanceof ResultWithValue);

        /** @var list<IdDePersona> $personas */
        $personas = $resultado->value();

        return $personas;
    }
```

- [ ] **Step 6: Reescribir el comando**

`src/Identidad/Infrastructure/Habilitacion/ConciliarIdentidadesCommand.php`, reemplazando el import de `ContactoRecord` por el del puerto y el bucle por el del puerto:

```php
<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Habilitacion;

use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\DirectorioDeContactos;
use Identidad\Application\Contracts\DirectorioDeIdentidades;
use Illuminate\Console\Command;

/** Detecta el estado parcial: habilitado acá y no allá, o al revés. */
final class ConciliarIdentidadesCommand extends Command
{
    protected $signature = 'identidad:conciliar';

    protected $description = 'Compara las personas habilitadas contra los usuarios del directorio';

    public function handle(
        DirectorioDeContactos $contactos,
        DirectorioDeIdentidades $directorio,
        BovedaDeContrasenas $boveda,
    ): int {
        $discrepancias = 0;

        foreach ($contactos->habilitadas() as $persona) {
            if (! $directorio->existe($persona)) {
                $this->warn("Falta en el directorio: {$persona->value()}");
                $discrepancias++;

                continue;
            }

            if ($boveda->leer($persona) === null) {
                $this->warn("Sin contraseña guardada: {$persona->value()}");
                $discrepancias++;
            }
        }

        if ($discrepancias === 0) {
            $this->info('Sin discrepancias.');

            return self::SUCCESS;
        }

        $this->error("{$discrepancias} discrepancia(s). Corregir con identidad:habilitar.");

        return self::FAILURE;
    }
}
```

- [ ] **Step 7: Verificar que ya no cruza**

Run:

```bash
grep -rn "Maestros\\\\Infrastructure" --include=*.php src/Identidad
```

Expected: sin resultados.

- [ ] **Step 8: Ejecutar y verificar que pasan**

Run: `composer test -- tests/Feature/Identidad/ConciliarIdentidadesTest.php`
Expected: PASA, 3 tests. El caso de conciliación que vivía en `HabilitarPersonaTest.php` se puede borrar de allá: este archivo lo cubre mejor.

- [ ] **Step 9: Commit**

```bash
git add src app/Providers/CoreServiceProvider.php tests/Feature/Identidad
git commit -m "fix: la conciliacion pasa por el puerto DirectorioDeContactos y no cruza esquemas"
```

---

### Task 8: Los dobles compartidos salen de los archivos de prueba

Cierra **H8**. `tests/Feature/Identidad/RenovarYCerrarTest.php:17-18` usa `EmisorFalso` y `EnviadorQueRecuerda`, declarados en `IniciarSesionTest.php:19`. Correr ese archivo solo es un *fatal error*; la suite completa pasa por orden de carga, que es peor, porque falla únicamente cuando alguien depura un archivo suelto.

**Files:**
- Create: `tests/Dobles/EmisorFalso.php`
- Create: `tests/Dobles/EnviadorQueRecuerda.php`
- Create: `tests/Dobles/EnviadorEspia.php`
- Create: `tests/Dobles/VerificadorFalso.php`
- Create: `tests/Dobles/DirectorioFalso.php`
- Modify: `tests/Feature/Identidad/IniciarSesionTest.php` (borrar las dos clases, importar)
- Modify: `tests/Feature/Identidad/RenovarYCerrarTest.php` (importar)
- Modify: `tests/Feature/Identidad/SolicitarDesafioTest.php` (borrar `EnviadorEspia`, importar)
- Modify: `tests/Feature/Identidad/HabilitarPersonaTest.php` (borrar `DirectorioFalso`, importar)
- Modify: `tests/Feature/Maestros/MiCuentaTest.php` (borrar `VerificadorFalso`, importar)

**Interfaces:**
- Consumes: los contratos que cada doble implementa (`EmisorDeToken`, `EnviadorDeDesafio`, `VerificadorDeToken`, `DirectorioDeIdentidades`).
- Produces: el namespace `Tests\Dobles\` — ya cubierto por `"Tests\\": "tests/"` en `composer.json:36-39`, así que **no hay que tocar el autoload**.

- [ ] **Step 1: Verificar que el namespace ya resuelve**

Run: `composer dump-autoload && php -r "require 'vendor/autoload.php'; var_dump(class_exists('Tests\\\\TestCase'));"`
Expected: `bool(true)`. `Tests\` ya apunta a `tests/`, así que `tests/Dobles/X.php` es `Tests\Dobles\X` sin configuración extra.

- [ ] **Step 2: Mover los cinco dobles**

Cada uno es el mismo cuerpo que hoy está embebido en su archivo de prueba, con `namespace Tests\Dobles;` arriba y los `use` que necesita. `tests/Dobles/EmisorFalso.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Core\Results\Error;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Identidad\Application\Contracts\EmisorDeToken;
use Identidad\Application\Contracts\TokenEmitido;
use Maestros\Domain\Contactos\IdDePersona;

final class EmisorFalso implements EmisorDeToken
{
    public bool $caido = false;

    /** @var list<string> */
    public array $pedidos = [];

    public function emitirPara(IdDePersona $persona, string $contrasena): ResultWithValue
    {
        $this->pedidos[] = $persona->value();

        if ($this->caido) {
            return ResultWithValue::failure(
                Error::problem('IDENTIDAD_NO_DISPONIBLE', 'El proveedor de identidad no responde'),
            );
        }

        return ResultWithValue::of(new TokenEmitido('jwt-de-prueba', 'refresh-de-prueba', 900));
    }

    public function renovar(string $refreshToken): ResultWithValue
    {
        return ResultWithValue::of(new TokenEmitido('jwt-2', 'refresh-2', 900));
    }

    public function revocar(string $refreshToken): Result
    {
        return Result::success();
    }
}
```

`tests/Dobles/EnviadorQueRecuerda.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Identidad\Application\Contracts\EnviadorDeDesafio;
use Maestros\Domain\Contactos\Celular;

final class EnviadorQueRecuerda implements EnviadorDeDesafio
{
    public string $ultimoCodigo = '';

    public function enviar(Celular $a, string $digitos): void
    {
        $this->ultimoCodigo = $digitos;
    }
}
```

`tests/Dobles/EnviadorEspia.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Identidad\Application\Contracts\EnviadorDeDesafio;
use Maestros\Domain\Contactos\Celular;

final class EnviadorEspia implements EnviadorDeDesafio
{
    /** @var list<array{celular: string, digitos: string}> */
    public array $enviados = [];

    public function enviar(Celular $a, string $digitos): void
    {
        $this->enviados[] = ['celular' => $a->e164(), 'digitos' => $digitos];
    }
}
```

`tests/Dobles/VerificadorFalso.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Identidad\Application\Contracts\VerificadorDeToken;
use Maestros\Domain\Contactos\IdDePersona;

final class VerificadorFalso implements VerificadorDeToken
{
    public function __construct(private readonly ?string $persona) {}

    public function verificar(string $jwt): ?IdDePersona
    {
        return $jwt === 'token-bueno' && $this->persona !== null
            ? IdDePersona::desde($this->persona)
            : null;
    }
}
```

`tests/Dobles/DirectorioFalso.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Core\Results\Error;
use Core\Results\Result;
use Identidad\Application\Contracts\DirectorioDeIdentidades;
use Maestros\Domain\Contactos\IdDePersona;

final class DirectorioFalso implements DirectorioDeIdentidades
{
    public bool $caido = false;

    /** @var array<string, string> */
    public array $usuarios = [];

    public function crearOActualizar(IdDePersona $persona, string $contrasena): Result
    {
        if ($this->caido) {
            return Result::failure(
                Error::problem('IDENTIDAD_NO_DISPONIBLE', 'No responde'),
            );
        }

        $this->usuarios[$persona->value()] = $contrasena;

        return Result::success();
    }

    public function deshabilitar(IdDePersona $persona): Result
    {
        if ($this->caido) {
            return Result::failure(
                Error::problem('IDENTIDAD_NO_DISPONIBLE', 'No responde'),
            );
        }

        unset($this->usuarios[$persona->value()]);

        return Result::success();
    }

    public function existe(IdDePersona $persona): bool
    {
        return isset($this->usuarios[$persona->value()]);
    }
}
```

- [ ] **Step 3: Borrar las declaraciones viejas e importar**

En cada archivo de prueba, borrar la clase que se movió y agregar el `use Tests\Dobles\X;` correspondiente. En `RenovarYCerrarTest.php` borrar además los dos comentarios `// definido en IniciarSesionTest.php` e `// idem`.

- [ ] **Step 4: Verificar que cada archivo corre solo**

Run, uno por uno:

```bash
vendor/bin/pest tests/Feature/Identidad/RenovarYCerrarTest.php
vendor/bin/pest tests/Feature/Identidad/IniciarSesionTest.php
vendor/bin/pest tests/Feature/Identidad/SolicitarDesafioTest.php
vendor/bin/pest tests/Feature/Identidad/HabilitarPersonaTest.php
vendor/bin/pest tests/Feature/Maestros/MiCuentaTest.php
```

Expected: los cinco en verde por separado. **Esta es la verificación que importa**: antes, el primero de la lista era un fatal.

- [ ] **Step 5: Ejecutar la batería completa**

Run: `composer test`
Expected: todo en verde.

- [ ] **Step 6: Commit**

```bash
git add tests
git commit -m "test: mover los dobles compartidos a tests/Dobles para que cada archivo corra solo"
```

---

### Task 9: Tests de arquitectura que verifican de verdad

Cierra **H3** y parte de **H11**. `tests/Architecture/AlcanceTest.php:12` recorre `get_declared_classes()`, que solo ve las clases **ya cargadas**: con autoload perezoso, ninguna petición lo está cuando corre el test, así que pasa en verde aunque el archivo esté vacío. Y `EstructuraTest.php:59-61` se llama «todo src declara tipos estrictos» pero solo evalúa `Core`.

**Files:**
- Create: `tests/Soporte/Clases.php`
- Rewrite: `tests/Architecture/AlcanceTest.php`
- Modify: `tests/Architecture/EstructuraTest.php`

**Interfaces:**
- Consumes: `Core\Contracts\{Request, ConAlcanceDeSocio}`, `Maestros\Domain\Socios\CodigoDeSocio`.
- Produces: `Tests\Soporte\Clases::deSrc(): list<class-string>` — recorre el sistema de archivos y deriva el FQCN del path por PSR-4, sin depender de qué esté cargado.

- [ ] **Step 1: Probar que el test actual no verifica nada**

Antes de arreglarlo, comprobar el defecto. Crear un archivo temporal `src/Maestros/Application/Contactos/PeticionTrampa.php`:

```php
<?php

declare(strict_types=1);

namespace Maestros\Application\Contactos;

use Core\Contracts\Request;
use Maestros\Domain\Socios\CodigoDeSocio;

/** Trampa temporal: recibe un cardCode y NO declara ConAlcanceDeSocio. */
final readonly class PeticionTrampa implements Request
{
    public function __construct(public CodigoDeSocio $cardCode) {}
}
```

Run: `composer test -- tests/Architecture/AlcanceTest.php`
Expected: **PASA** — y eso es el error. El test debería estar en rojo.

- [ ] **Step 2: Escribir el recolector de clases**

`tests/Soporte/Clases.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Soporte;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Recorre `src/` por el sistema de archivos y arma el FQCN desde el path
 * según PSR-4. No usa `get_declared_classes()` a propósito: eso solo ve lo ya
 * cargado, y con autoload perezoso es casi nada.
 */
final class Clases
{
    /** @var array<string, string> namespace raíz => subdirectorio de src/ */
    private const MODULOS = [
        'Core' => 'Core',
        'Maestros' => 'Maestros',
        'Identidad' => 'Identidad',
    ];

    /** @return list<class-string> */
    public static function deSrc(): array
    {
        $raiz = dirname(__DIR__, 2).'/src';
        $encontradas = [];

        foreach (self::MODULOS as $namespace => $carpeta) {
            $base = $raiz.'/'.$carpeta;

            if (! is_dir($base)) {
                continue;
            }

            /** @var iterable<SplFileInfo> $archivos */
            $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));

            foreach ($archivos as $archivo) {
                if (! $archivo->isFile() || $archivo->getExtension() !== 'php') {
                    continue;
                }

                $relativo = substr($archivo->getPathname(), strlen($base) + 1);
                $sinExtension = substr($relativo, 0, -4);
                $clase = $namespace.'\\'.str_replace(['/', '\\'], '\\', $sinExtension);

                if (class_exists($clase) || interface_exists($clase)) {
                    $encontradas[] = $clase;
                }
            }
        }

        return $encontradas;
    }
}
```

- [ ] **Step 3: Reescribir el test de alcance**

`tests/Architecture/AlcanceTest.php`:

```php
<?php

declare(strict_types=1);

use Core\Contracts\ConAlcanceDeSocio;
use Core\Contracts\Request;
use Maestros\Domain\Socios\CodigoDeSocio;
use Tests\Soporte\Clases;

it('el recolector encuentra las peticiones reales del proyecto', function () {
    // Guarda contra el defecto que tenía este archivo: si el recolector
    // devuelve poco o nada, los dos tests de abajo pasan sin verificar.
    $peticiones = array_filter(
        Clases::deSrc(),
        static fn (string $c): bool => is_subclass_of($c, Request::class),
    );

    expect(count($peticiones))->toBeGreaterThanOrEqual(7);
});

it('toda peticion con cardCode declara su alcance', function () {
    $sinDeclarar = [];

    foreach (Clases::deSrc() as $clase) {
        if (! is_subclass_of($clase, Request::class)) {
            continue;
        }

        $reflexion = new ReflectionClass($clase);
        $constructor = $reflexion->getConstructor();

        if ($constructor === null) {
            continue;
        }

        foreach ($constructor->getParameters() as $parametro) {
            $tipo = $parametro->getType();

            $esCardCode = $tipo instanceof ReflectionNamedType
                && $tipo->getName() === CodigoDeSocio::class;

            if ($esCardCode && ! $reflexion->implementsInterface(ConAlcanceDeSocio::class)) {
                $sinDeclarar[] = $clase;
            }
        }
    }

    expect($sinDeclarar)->toBe([]);
});
```

- [ ] **Step 4: Verificar que ahora sí atrapa la trampa**

Run: `composer test -- tests/Architecture/AlcanceTest.php`
Expected: **FALLA**, nombrando `Maestros\Application\Contactos\PeticionTrampa`.

- [ ] **Step 5: Borrar la trampa y confirmar el verde**

```bash
rm src/Maestros/Application/Contactos/PeticionTrampa.php
```

Run: `composer test -- tests/Architecture/AlcanceTest.php`
Expected: PASA, 2 tests.

- [ ] **Step 6: Ampliar el test de estructura**

En `tests/Architecture/EstructuraTest.php`, reemplazar el bloque de tipos estrictos y agregar la regla que faltaba sobre Application:

```php
arch('todo src declara tipos estrictos')
    ->expect(['Core', 'Maestros', 'Identidad'])
    ->toUseStrictTypes();

arch('la capa Application tampoco conoce Eloquent')
    ->expect(['Maestros\Application', 'Identidad\Application'])
    ->not->toUse(['Illuminate', 'Eloquent']);
```

> Esta última es la regla que le faltaba al proyecto: la que habría atrapado el `GrupoRecord` dentro de `ObtenerContextoHandler` (H6) el día que se escribió.

- [ ] **Step 7: Ejecutar toda la arquitectura**

Run: `composer test -- tests/Architecture`
Expected: PASA. Si alguna regla nueva sale en rojo, es un hallazgo real: arreglarlo antes de seguir, no relajar la regla.

- [ ] **Step 8: Commit**

```bash
git add tests
git commit -m "test: tests de arquitectura que recorren el filesystem en vez de las clases cargadas"
```

---

### Task 10: Limpieza mecánica

Cierra **H10** y el resto de **H11**. Nada de esto cambia comportamiento: es duplicación y ruido.

**Files:**
- Create: `app/Persistence/TablaConEsquema.php`
- Modify: los seis records (`SocioRecord:34`, `GrupoRecord:33`, `ContactoRecord:37`, `SesionRecord:39`, `DesafioRecord:38`, `CredencialRecord:30`)
- Modify: `src/Maestros/Domain/Contactos/ContactoErrors.php`
- Modify: `src/Maestros/Application/Contactos/BuscarPorCelular/BuscarPorCelularHandler.php:25-28`
- Modify: `src/Maestros/Application/Contactos/ObtenerContexto/ObtenerContextoHandler.php:32-36,42-46`
- Modify: `src/Identidad/Application/Habilitacion/HabilitarPersona/HabilitarPersonaHandler.php:31-35`
- Modify: `composer.json:9`

**Interfaces:**
- Produces: trait `App\Persistence\TablaConEsquema` con `protected function tablaEn(string $esquema, string $nombre): string`; `ContactoErrors::noEncontrado(string $id): Error`.

- [ ] **Step 1: Escribir el trait**

Va en `app/` y no en un módulo porque lo comparten los records de `Maestros` y los de `Identidad`, y `Maestros` no puede referenciar `Identidad` ni al revés. `app/` es el host: depender del framework es exactamente su trabajo.

`app/Persistence/TablaConEsquema.php`:

```php
<?php

declare(strict_types=1);

namespace App\Persistence;

use Illuminate\Support\Facades\DB;

/**
 * En SQL Server las tablas viven bajo un esquema (`maestros.socios`); en el
 * SQLite de las pruebas no hay esquemas, así que el nombre se aplana
 * (`maestros_socios`). Estaba copiado en los seis records.
 */
trait TablaConEsquema
{
    protected function tablaEn(string $esquema, string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv'
            ? "{$esquema}.{$nombre}"
            : "{$esquema}_{$nombre}";
    }
}
```

- [ ] **Step 2: Usarlo en los seis records**

En cada uno, agregar `use App\Persistence\TablaConEsquema;` arriba, `use TablaConEsquema;` dentro de la clase, y reemplazar el cuerpo de `getTable()`. Por ejemplo en `SocioRecord`:

```php
    public function getTable(): string
    {
        return $this->tablaEn('maestros', 'socios');
    }
```

Los seis quedan: `SocioRecord` → `('maestros', 'socios')`; `GrupoRecord` → `('maestros', 'grupos')`; `ContactoRecord` → `('maestros', 'contactos')`; `DesafioRecord` → `('identidad', 'desafios')`; `SesionRecord` → `('identidad', 'sesiones')`; `CredencialRecord` → `('identidad', 'credenciales')`.

- [ ] **Step 3: Una sola fábrica para `CONTACTO_NO_ENCONTRADO`**

Hoy ese código aparece con tres mensajes distintos en cuatro archivos. Agregar a `src/Maestros/Domain/Contactos/ContactoErrors.php`:

```php
    public static function noEncontrado(string $id): Error
    {
        return Error::notFound(
            'CONTACTO_NO_ENCONTRADO',
            'No existe la persona de contacto {id}',
            $id,
        );
    }
```

Y reemplazar los cuatro `Error::notFound('CONTACTO_NO_ENCONTRADO', ...)` armados a mano por `ContactoErrors::noEncontrado($id)` en `BuscarPorCelularHandler`, `ObtenerContextoHandler`, `HabilitarPersonaHandler` e `IniciarSesionHandler`. En `BuscarPorCelularHandler`, que no tiene un id a mano, pasar `$peticion->celular->e164()`.

Lo mismo con el `SOCIO_NO_ENCONTRADO` de `ObtenerContextoHandler:42-46`, que reimplementa una fábrica que ya existe:

```php
            return ResultWithValue::failure(
                SocioErrors::noEncontrado($persona->codigoDeSocio()->value()),
            );
```

- [ ] **Step 4: Subir el piso de PHP**

En `composer.json`, línea 9: `"php": "^8.3"` pasa a `"php": "^8.4"`, que es lo que el plan original fijaba como requisito.

Run: `composer update --lock`
Expected: el lock se regenera sin errores.

- [ ] **Step 5: Ejecutar la batería**

Run: `composer test && composer stan && composer lint -- --test`
Expected: todo en verde. Ningún test debería haber cambiado de comportamiento.

Run: `grep -rn "'CONTACTO_NO_ENCONTRADO'" --include=*.php src`
Expected: una sola aparición, en `ContactoErrors.php`.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor: trait para el nombre de tabla, fabricas de error unificadas y PHP 8.4"
```

---

### Task 11: `DeshabilitarPersona` y la prueba que le falta al doble de alcance

Cierra lo último de **H11**. `DirectorioDeIdentidades::deshabilitar()` existe y no lo invoca nadie: no hay forma de quitarle el acceso a una persona. Y `ResolutorQueNiegaTodo` se niega a arrancar en producción, pero ningún test lo ejerce, así que esa guarda nunca se probó.

**Files:**
- Create: `src/Identidad/Application/Habilitacion/DeshabilitarPersona/DeshabilitarPersona.php`
- Create: `src/Identidad/Application/Habilitacion/DeshabilitarPersona/DeshabilitarPersonaHandler.php`
- Create: `src/Identidad/Infrastructure/Habilitacion/DeshabilitarPersonaCommand.php`
- Modify: `app/Providers/CoreServiceProvider.php`
- Modify: `app/Providers/ModulosServiceProvider.php`
- Test: `tests/Feature/Identidad/DeshabilitarPersonaTest.php`
- Test: `tests/Unit/Maestros/ResolutorQueNiegaTodoTest.php`

**Interfaces:**
- Consumes: `DirectorioDeIdentidades::deshabilitar(IdDePersona): Result`, `SesionRepository::abiertasDe(IdDePersona): list<SesionDeAplicacion>`, `RelojDelSistema`, `RequiereTransaccion` (Task 1).
- Produces: `DeshabilitarPersona implements Request, RequiereTransaccion` con `public readonly IdDePersona $persona`; su handler devuelve `Result`; comando `php artisan identidad:deshabilitar {persona}`.

- [ ] **Step 1: Escribir los tests que fallan**

`tests/Feature/Identidad/DeshabilitarPersonaTest.php`:

```php
<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Identidad\Application\Contracts\DirectorioDeIdentidades;
use Identidad\Application\Habilitacion\DeshabilitarPersona\DeshabilitarPersona;
use Identidad\Domain\Sesiones\IdDeSesion;
use Identidad\Domain\Sesiones\SesionDeAplicacion;
use Identidad\Domain\Sesiones\SesionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\IdDePersona;
use Tests\Dobles\DirectorioFalso;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->directorio = new DirectorioFalso;
    $this->app->instance(DirectorioDeIdentidades::class, $this->directorio);
});

it('saca el usuario del directorio y cierra sus sesiones abiertas', function () {
    $persona = IdDePersona::desde('p-8f2b1c40');
    $this->directorio->crearOActualizar($persona, 'una-contrasena');

    app(SesionRepository::class)->save(SesionDeAplicacion::abrir(
        IdDeSesion::desde('s-1'),
        $persona,
        null,
        new DateTimeImmutable('2026-09-14T12:00:00Z'),
        new DateTimeImmutable('2036-09-14T12:00:00Z'),
    ));

    $resultado = app(Mediator::class)->send(new DeshabilitarPersona($persona));

    expect($resultado->isSuccess)->toBeTrue()
        ->and($this->directorio->existe($persona))->toBeFalse()
        ->and(app(SesionRepository::class)->abiertasDe($persona))->toBe([]);
});

it('es idempotente: deshabilitar dos veces no falla', function () {
    $persona = IdDePersona::desde('p-8f2b1c40');

    expect(app(Mediator::class)->send(new DeshabilitarPersona($persona))->isSuccess)->toBeTrue()
        ->and(app(Mediator::class)->send(new DeshabilitarPersona($persona))->isSuccess)->toBeTrue();
});

it('el comando devuelve 0 al deshabilitar', function () {
    $this->artisan('identidad:deshabilitar', ['persona' => 'p-8f2b1c40'])->assertExitCode(0);
});
```

`tests/Unit/Maestros/ResolutorQueNiegaTodoTest.php`:

```php
<?php

declare(strict_types=1);

use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Socios\CodigoDeSocio;
use Maestros\Infrastructure\Alcance\ResolutorQueNiegaTodo;

it('se niega a existir en produccion', function () {
    expect(fn () => new ResolutorQueNiegaTodo('production'))
        ->toThrow(RuntimeException::class);
});

it('en cualquier otro entorno arranca y niega todo', function (string $entorno) {
    $resolutor = new ResolutorQueNiegaTodo($entorno);

    expect($resolutor->alcanza(IdDePersona::desde('p-1'), CodigoDeSocio::desde('C-004871')))
        ->toBeFalse();
})->with(['testing', 'local', 'staging']);
```

- [ ] **Step 2: Ejecutar y verificar que fallan**

Run: `composer test -- tests/Feature/Identidad/DeshabilitarPersonaTest.php tests/Unit/Maestros/ResolutorQueNiegaTodoTest.php`
Expected: el primero FALLA con "Class ...DeshabilitarPersona not found"; el segundo PASA (la guarda ya existía y ahora queda cubierta).

- [ ] **Step 3: Escribir la petición y su handler**

`src/Identidad/Application/Habilitacion/DeshabilitarPersona/DeshabilitarPersona.php`:

```php
<?php

declare(strict_types=1);

namespace Identidad\Application\Habilitacion\DeshabilitarPersona;

use Core\Contracts\Request;
use Core\Contracts\RequiereTransaccion;
use Maestros\Domain\Contactos\IdDePersona;

final readonly class DeshabilitarPersona implements Request, RequiereTransaccion
{
    public function __construct(public IdDePersona $persona) {}
}
```

`src/Identidad/Application/Habilitacion/DeshabilitarPersona/DeshabilitarPersonaHandler.php`:

```php
<?php

declare(strict_types=1);

namespace Identidad\Application\Habilitacion\DeshabilitarPersona;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Identidad\Application\Contracts\DirectorioDeIdentidades;
use Identidad\Application\Contracts\RelojDelSistema;
use Identidad\Domain\Sesiones\SesionRepository;

/**
 * Quitar el acceso son dos cosas y las dos tienen que pasar: bloquear al
 * usuario en el directorio, y cerrar las sesiones que ya estaban abiertas.
 * Solo lo primero deja a alguien operando con un token todavía válido.
 */
final readonly class DeshabilitarPersonaHandler implements RequestHandler
{
    public function __construct(
        private DirectorioDeIdentidades $directorio,
        private SesionRepository $sesiones,
        private RelojDelSistema $reloj,
    ) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof DeshabilitarPersona);

        $bloqueado = $this->directorio->deshabilitar($peticion->persona);

        if ($bloqueado->isFailure()) {
            return $bloqueado;
        }

        $ahora = $this->reloj->ahora();

        foreach ($this->sesiones->abiertasDe($peticion->persona) as $sesion) {
            $sesion->cerrar($ahora);
            $this->sesiones->save($sesion);
        }

        return Result::success();
    }
}
```

- [ ] **Step 4: Escribir el comando**

`src/Identidad/Infrastructure/Habilitacion/DeshabilitarPersonaCommand.php`:

```php
<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Habilitacion;

use Core\Contracts\Mediator;
use Identidad\Application\Habilitacion\DeshabilitarPersona\DeshabilitarPersona;
use Illuminate\Console\Command;
use Maestros\Domain\Contactos\IdDePersona;

final class DeshabilitarPersonaCommand extends Command
{
    protected $signature = 'identidad:deshabilitar {persona : Id de la persona de contacto}';

    protected $description = 'Quita el acceso a la App: bloquea el usuario y cierra sus sesiones';

    public function handle(Mediator $mediator): int
    {
        $resultado = $mediator->send(
            new DeshabilitarPersona(IdDePersona::desde((string) $this->argument('persona'))),
        );

        if ($resultado->isFailure()) {
            $this->error($resultado->error->description);

            return self::FAILURE;
        }

        $this->info('Deshabilitada.');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Registrar handler y comando**

En `CoreServiceProvider::HANDLERS`:

```php
        DeshabilitarPersona::class => DeshabilitarPersonaHandler::class,
```

En `ModulosServiceProvider::boot()`, sumar `DeshabilitarPersonaCommand::class` al arreglo que ya registra los otros comandos.

- [ ] **Step 6: Ejecutar y verificar que pasan**

Run: `composer test -- tests/Feature/Identidad/DeshabilitarPersonaTest.php tests/Unit/Maestros/ResolutorQueNiegaTodoTest.php`
Expected: PASA, 3 + 4 tests.

- [ ] **Step 7: Commit**

```bash
git add src app/Providers tests
git commit -m "feat: identidad:deshabilitar cierra sesiones abiertas, y prueba del resolutor de alcance falso"
```

---

## Verificación final

Correr la lista completa y pegar la salida antes de dar las correcciones por cerradas:

- [ ] `composer test` — unitarias, feature y arquitectura en verde.
- [ ] Cada archivo de prueba de `tests/Feature/Identidad/` **corriendo solo**, uno por uno. Es lo que la Task 8 arregla y lo único que lo demuestra.
- [ ] `composer stan` — nivel 9 sin errores.
- [ ] `composer lint -- --test` — sin diferencias de formato.
- [ ] `composer test:integration` con Keycloak levantado — en verde.
- [ ] `grep -rn "readOnly\|UnitOfWork\|NotificationPublisher" --include=*.php src app tests` — sin resultados.
- [ ] `grep -rn "Maestros\\\\Infrastructure" --include=*.php src/Identidad` — sin resultados.
- [ ] `php artisan migrate:fresh` contra SQL Server real — los dos esquemas se crean completos.
- [ ] Recorrido manual con `compose.yaml`, con `QUEUE_CONNECTION=database` y un `php artisan queue:work` corriendo:
  1. `php artisan maestros:importar database/semillas/maestros-ejemplo.json`
  2. Volver a correrlo → «Omitidos por ser más viejos: 5»
  3. `php artisan identidad:habilitar p-8f2b1c40` y `php artisan identidad:conciliar` → «Sin discrepancias»
  4. `POST /v1/auth/otp` con un número **registrado** y con uno **desconocido**: los dos 200, y **medir el tiempo de las dos respuestas** — tienen que ser comparables. Es la verificación de H2 y no la hace ningún test.
  5. Leer el código del log, `POST /v1/auth/login` → 200 con token y contexto
  6. `GET /v1/mi-cuenta` → 200; `POST /v1/auth/refresh` → 200; `POST /v1/auth/logout` → 200
  7. `php artisan identidad:deshabilitar p-8f2b1c40` → el refresh anterior ya no renueva

---

## Notas de cierre

**Lo que este plan no toca, a propósito:** la ingesta de eventos de SAP y todo su aparato; las rutas de lectura que faltan (`/socios`, `/productos`, `/categorias`, `/bancos`, `/contactos/atencion-al-cliente`); el registro de dispositivo como ruta propia; y el `aud`/`iss` del verificador de JWT, que quedó anotado abajo como pendiente.

**Pendientes que quedan abiertos después de estas once tareas:**

1. **`VerificadorJwks` no valida `aud` ni `iss`.** `JWT::decode` comprueba firma y expiración, no audiencia: un token emitido por el mismo realm para otro cliente entraría. Con un solo cliente hoy no se explota, pero son tres líneas y evita un problema difícil de diagnosticar. No entró en este plan porque cambia el contrato de lo que el servicio acepta y merece su propia decisión.
2. **`CLAUDE.md` y `AGENTS.md` siguen siendo el bootstrap de Laravel Boost sin personalizar.** El repo no documenta ningún estándar propio, así que el eje Standards de cualquier review futuro se apoya en la spec y no en el repo. Vale escribirlos con las reglas de la sección «Global Constraints» de este plan.
3. **Keycloak sigue arrancando con `start-dev`** en `compose.yaml`, con base embebida que se pierde al recrear el contenedor. El apéndice B del plan de la fase 1 tiene la configuración con Azure SQL; hay que aplicarla antes del piloto.
4. **La pregunta abierta #1 del diseño sigue sin responder:** si una persona de contacto puede estar registrada en socios de dos grupos distintos. Si existe el caso, no falla con un error claro: le muestra al usuario la mitad de sus socios.

**Y una advertencia sobre el orden:** las tareas 3, 7 y 11 usan `tests/Dobles/`, que crea la Task 8, y las tareas 5 y 11 dependen de cosas que crean la 4 y la 1. El orden escrito resuelve todas esas dependencias; si se ejecutan salteadas, cada tarea dice en un bloque `>` qué hacer mientras tanto.
