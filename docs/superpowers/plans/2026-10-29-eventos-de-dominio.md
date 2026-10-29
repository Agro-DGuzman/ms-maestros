# El bus de eventos de dominio (trabajo 3) · plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** que ms-maestros publique `maestros.socio.actualizado` en `maestros-eventos` cada vez que guarda un socio, y que reciba `comercial.propiedad.actualizada` de `sub-ms-maestros` para mantener sus propiedades, con `estado` en lugar de `activa`.

**Architecture:** copia el lado de ms-comercial (`C:\Users\dguzman\Documents\microservicio-comercial\ms-comercial`). Un outbox (`maestros.evento_saliente`) se escribe en la transacción de `ReplicarSocio` y `DarDeBajaSocio`. Un comando de larga vida, `eventos:consumir`, publica el outbox y recibe de la suscripción en cada vuelta, por la API REST de Service Bus. Lo que llega pasa por `ProcesarEvento`, que descarta repetidos con `maestros.evento_procesado` y escribe la propiedad solo si su foto es más nueva.

**Tech Stack:** Laravel 13, PHP 8.4, Pest, Larastan 9, Pint, `Http` de Laravel en el transporte, `ramsey/uuid` para los ids, opis/json-schema contra el contrato. SQLite en la batería y SQL Server en Azure.

**Spec:** `docs/superpowers/specs/2026-10-29-eventos-de-dominio-design.md`. Las decisiones se citan como EM1…EM15.

## Global Constraints

- PHP 8.4 y `declare(strict_types=1);` en todo archivo de `src/`.
- `composer test`, `composer stan` y `composer lint -- --test` en verde antes de cada commit. Correr `vendor/bin/pint` antes del lint.
- `Maestros\Domain` y `Maestros\Application` no usan `Illuminate`, `Eloquent` ni `Maestros\Infrastructure` (`tests/Architecture/EstructuraTest.php`). `Psr\Log\LoggerInterface` y `Ramsey\Uuid` sí se pueden usar.
- Una petición con `EscrituraDeMaquina` vive en `Maestros\Application\Ingesta` (`tests/Architecture/AlcanceTest.php`).
- Los handlers se registran en `CoreServiceProvider::HANDLERS`. Los puertos y los comandos, en `app/Providers/ModulosServiceProvider.php`.
- **Copiar de ms-comercial no es pegar.** Se cambian el namespace, los nombres de tablas (`maestros.*`, y `maestros_*` en SQLite) y lo que nombra a comercial en los comentarios.
- **Prohibido `insertOrIgnore`:** la gramática de SQL Server de Laravel no lo tiene.
- La estructura de propiedades en SQL Server vive en `database/sql/maestros-propiedades.sql`. Su copia de SQLite con Blueprint cambia a la par, sin los CHECK (EM10).
- Las claves de Service Bus no se escriben en ningún archivo ni en el chat.
- Los dobles compartidos van en `tests/Dobles/`, y los armadores en `tests/Soporte/`. Las funciones globales de un archivo Pest llevan nombres únicos en toda la batería.
- Cada archivo de prueba corre solo: `vendor/bin/pest <archivo>`.
- Primero el test en rojo, verificado, y después el código.
- `docs/` está en `.gitignore`: el plan y la spec se agregan con `git add -f`.
- Comentarios y commits en español, y los comentarios dicen **por qué**. Todo commit termina con `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Se trabaja en la rama `feat/eventos-de-dominio`, creada desde `main`.

## Review Focus

1. **Texto no ASCII de punta a punta.** «Vargas Peña SRL» y «Lote 14 · San Julián» tienen que salir del outbox y del publicador sin escapes `\u00f1`, iguales a lo guardado. Tests en las tareas 2 y 5.
2. **Dos fotos de la misma propiedad dentro del mismo segundo.** La de 12:00:00.900 pisa a la de 12:00:00.100, y la vigencia se guarda y se lee con sus 6 decimales. Test en la tarea 7.
3. **Un `ocurridoEn` con desfase** (`2026-10-21T08:00:00-04:00`) se compara en UTC contra lo guardado. Una foto de las 11:00Z no pisa una de las 12:00Z. Test en la tarea 7.
4. **Un socio con `id_de_grupo` cuyo grupo no está en `maestros.grupos`** (le pasa a `C-007700` de la semilla, con `GRP-099`). El evento sale con `grupoEconomico: null` y valida contra el contrato. Test en la tarea 1.
5. **`eventos:consumir` sin configuración.** Tiene que negarse a arrancar y decir qué variable falta, no entrar en un bucle de 401. Test en la tarea 8.

---

### Task 1: El contrato y el sobre del socio

**Files:**
- Create: `contrato/eventos-de-dominio-v1.yaml` (copia byte a byte de `ms-comercial/contrato/eventos-de-dominio-v1.yaml`)
- Create: `src/Maestros/Application/Eventos/EventoSaliente.php`
- Create: `src/Maestros/Application/Eventos/SocioActualizado.php`
- Modify: `tests/Soporte/Contrato.php` (un método público nuevo)
- Test: `tests/Unit/Maestros/SocioActualizadoTest.php`

**Interfaces:**
- Produces:
  - `final readonly class EventoSaliente { __construct(public string $eventoId, public string $tipo, public array $sobre, public DateTimeImmutable $creadoEn) }`, igual a la de comercial.
  - `final class SocioActualizado { public const string TIPO = 'maestros.socio.actualizado'; public static function de(Socio $socio, ?GrupoEconomico $grupo): ?EventoSaliente }`. Devuelve `null` si el socio no tiene línea (EM4).
  - `Tests\Soporte\Contrato::diferenciasDeEsquema(mixed $dato, string $archivo, string $esquema): list<string>`. Valida `$dato`, pasado por `json_decode(json_encode(...))`, contra `#/components/schemas/{$esquema}`.

- [ ] **Step 1: Copiar el contrato** y comprobar que es idéntico: `cmp contrato/eventos-de-dominio-v1.yaml ../../microservicio-comercial/ms-comercial/contrato/eventos-de-dominio-v1.yaml` no imprime nada.

- [ ] **Step 2: Escribir los tests que fallan**

Arman socios con `Socio::replica(...)`. `GrupoEconomico::replica(IdDeGrupo::desde('1435'), 'SOGIMA', $t, null)` es el grupo de ejemplo.
- `it('arma el sobre de la spec y valida contra el contrato')`:
  - socio `10057`, «AGROPECUARIA SOGIMA SRL», línea `'50000.00'`, vigente `2026-10-29T14:00:00Z`;
  - `tipo` es `'maestros.socio.actualizado'`, `origen` es `'ms-maestros'`, `version` es `1`;
  - `clave` es `['objeto' => 'socio', 'cardCode' => '10057']`;
  - `ocurridoEn` es `'2026-10-29T14:00:00.000000Z'`;
  - `datos` es `['cardCode' => '10057', 'razonSocial' => 'AGROPECUARIA SOGIMA SRL', 'grupoEconomico' => ['codigo' => '1435', 'nombre' => 'SOGIMA', 'segmento' => null], 'lineaDeCredito' => '50000.00', 'deBaja' => false]`;
  - `Contrato::diferenciasDeEsquema($evento->sobre, 'eventos-de-dominio-v1.yaml', 'SocioActualizado')` es `[]`.
- `it('un ocurridoEn con desfase sale en UTC con Z')`: vigente `2026-10-29T10:00:00-04:00` da `'2026-10-29T14:00:00.000000Z'`.
- `it('sin grupo, o con un grupo que no esta guardado, sale con grupoEconomico null y valida')`:
  - el socio tiene `IdDeGrupo::desde('GRP-099')` y se pasa `$grupo = null`;
  - `datos.grupoEconomico` es `null`, y el sobre valida.
- `it('un socio dado de baja sale con deBaja true, y uno inactivo con deBaja false')`.
- `it('cada foto lleva un eventoId distinto, UUID v4 en minusculas')`:
  - dos llamadas con el mismo socio dan `eventoId` distintos;
  - cada uno cumple `/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/`;
  - `EventoSaliente::$eventoId` es igual a `sobre['eventoId']`.
- `it('un socio sin linea no se publica')`: `SocioActualizado::de($socioSinLinea, null)` es `null`.

- [ ] **Step 3: Correrlos.** `vendor/bin/pest tests/Unit/Maestros/SocioActualizadoTest.php`. Expected: FAIL, `Class "Maestros\Application\Eventos\SocioActualizado" not found`.

- [ ] **Step 4: Implementar `EventoSaliente`, `SocioActualizado::de` y `Contrato::diferenciasDeEsquema`.**
  - `ocurridoEn`: `vigenteDesde()->setTimezone(UTC)->format('Y-m-d\TH:i:s.u\Z')`.
  - `eventoId`: `Uuid::uuid4()->toString()`.
  - `creadoEn`: el momento de armarlo, en UTC.
  - `diferenciasDeEsquema` reusa `validador()` e `id()` del archivo, con el puntero `/components/schemas/{$esquema}`.

- [ ] **Step 5: Correrlos.** Expected: PASS.

- [ ] **Step 6: Commit** `feat(eventos): el sobre de maestros.socio.actualizado y la copia del contrato de eventos`.

### Task 2: El outbox

**Files:**
- Create: `database/migrations/maestros/2026_10_30_000100_crear_eventos_de_dominio.php`
- Create: `src/Maestros/Application/Eventos/SalidaDeEventos.php`
- Create: `src/Maestros/Infrastructure/Eventos/EventoSalienteRecord.php`
- Create: `src/Maestros/Infrastructure/Eventos/EloquentSalidaDeEventos.php`
- Create: `tests/Dobles/SalidaDeEventosEnMemoria.php`
- Modify: `app/Providers/ModulosServiceProvider.php` (vincular `SalidaDeEventos`)
- Test: `tests/Feature/Maestros/SalidaDeEventosTest.php`

**Interfaces:**
- Consumes: `EventoSaliente` (Task 1).
- Produces: `interface SalidaDeEventos { registrar(EventoSaliente $evento): void; pendientes(int $limite): list<EventoSaliente>; marcarPublicado(string $eventoId): void; sumarIntento(string $eventoId): void; }`.
  - Es la de comercial, pero `marcarPublicado` no recibe el instante: lo pone el adaptador. Así `Application` no necesita un reloj.
  - `SalidaDeEventosEnMemoria` cumple el mismo puerto y expone `public array $registrados`.

- [ ] **Step 1: Escribir los tests que fallan**
- `it('registra un evento y lo devuelve pendiente con el sobre intacto')`: el sobre trae `razonSocial` «Vargas Peña SRL», y `pendientes(50)[0]->sobre` es igual al registrado (Review Focus 1).
- `it('devuelve las pendientes por orden de llegada y respeta el limite')`: tres registradas con `creadoEn` crecientes; `pendientes(2)` da las dos primeras.
- `it('una publicada ya no esta pendiente')`: después de `marcarPublicado`, `pendientes(50)` no la trae, y su fila tiene `publicado_en` no nula.
- `it('sumarIntento cuenta y la deja pendiente')`: `intentos` es 1 y sigue en `pendientes`.

- [ ] **Step 2: Correrlos.** `vendor/bin/pest tests/Feature/Maestros/SalidaDeEventosTest.php`. Expected: FAIL, la tabla o el puerto no existen.

- [ ] **Step 3: Implementar.**
  - La migración crea `maestros.evento_saliente` (SQLite: `maestros_evento_saliente`) con la forma de EM1. Copia `2026_10_28_002500_crear_eventos_salientes.php` de comercial: el `CHECK (ISJSON(sobre) = 1)` solo en sqlsrv y el índice `ix_evento_saliente_pendientes`.
  - La misma migración crea `maestros.evento_procesado` (EM7): `evento_id` uuid clave primaria, `tipo` string(60), `resultado` string(30), `procesado_en` `dateTimeTz(6)`. La usa la tarea 7, pero las dos tablas van en una sola migración para Azure.
  - El adaptador guarda `sobre` con `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR`.

- [ ] **Step 4: Correrlos.** Expected: PASS. Correr también `vendor/bin/pest tests/Architecture`.

- [ ] **Step 5: Commit** `feat(eventos): el outbox de ms-maestros`.

### Task 3: Guardar un socio deja su evento

**Files:**
- Modify: `src/Maestros/Application/Ingesta/ReplicarSocio/ReplicarSocioHandler.php`
- Modify: `src/Maestros/Application/Ingesta/DarDeBajaSocio/DarDeBajaSocioHandler.php`
- Test: `tests/Feature/Maestros/EventosDeSocioTest.php`

**Interfaces:**
- Consumes: `SocioActualizado::de` (Task 1), `SalidaDeEventos` (Task 2), `GrupoRepository::find(EntityId): ?GrupoEconomico`.
- Produces:
  - `ReplicarSocioHandler::__construct(SocioRepository, GrupoRepository, ContactoRepository, SalidaDeEventos)`.
  - `DarDeBajaSocioHandler::__construct(SocioRepository, ContactoRepository, GrupoRepository, SalidaDeEventos, LoggerInterface)`. Hoy recibe `SocioRepository` y `ContactoRepository`: conservar ese orden y sumar los tres al final.

- [ ] **Step 1: Escribir los tests que fallan.** Van por el `Mediator`, con `CuerpoDeIngesta::socio()`, e importan la semilla. Cada fila se lee de `maestros_evento_saliente` y su `sobre` valida con `Contrato::diferenciasDeEsquema(..., 'SocioActualizado')`.
- `it('crear deja un evento con la linea y el grupo guardado')`: una fila; `datos.lineaDeCredito` es `'50000.00'`; `datos.grupoEconomico.codigo` es `'GRP-900'`.
- `it('reemplazar deja otro evento con el ocurridoEn del cuerpo')`: dos filas; la segunda con `ocurridoEn` `'2026-10-05T13:00:00.000000Z'`.
- `it('un cuerpo viejo no deja evento')` y `it('un proveedor nuevo no deja evento')`.
- `it('un socio que pasa a proveedor deja su baja')`: `datos.deBaja` es `true`.
- `it('el DELETE deja la baja con el momento de la baja')`: `ocurridoEn` es el momento pasado a `DarDeBajaSocio`.
- `it('la baja de un socio sin linea se guarda sin evento')`:
  - con `C-004871` de la semilla, la respuesta es éxito;
  - `dado_de_baja_el` no es nula;
  - no hay filas en el outbox.
- `it('si el outbox falla no queda el socio')`:
  - vincula en el contenedor una `SalidaDeEventos` anónima cuyo `registrar` lanza `RuntimeException`;
  - el envío de `ReplicarSocio` lanza;
  - `SocioRecord::query()->find('C-900001')` es `null`.

- [ ] **Step 2: Correrlos.** `vendor/bin/pest tests/Feature/Maestros/EventosDeSocioTest.php`. Expected: FAIL, no hay filas.

- [ ] **Step 3: Implementar.**
  - Después de cada `socios->save(...)` que publica según EM2, armar el evento con el socio guardado y `$this->grupos->find($socio->grupo())`, o `null` si no tiene grupo, y registrarlo.
  - En `DarDeBajaSocioHandler`, si `SocioActualizado::de` da `null`, registrar `warning('Baja sin evento: el socio no tiene línea', ['cardCode' => ...])`.

- [ ] **Step 4: Correrlos,** junto con `tests/Feature/Maestros/ReplicarSocioTest.php`, `tests/Feature/Maestros/DarDeBajaSocioTest.php` y `tests/Feature/Ingesta/RutasDeIngestaTest.php`. Expected: PASS.

- [ ] **Step 5: Commit** `feat(eventos): guardar o dar de baja un socio deja su evento en el outbox`.

### Task 4: `eventos:publicar-socios`

**Files:**
- Modify: `src/Maestros/Domain/Socios/SocioRepository.php`, `src/Maestros/Infrastructure/Persistence/EloquentSocioRepository.php`
- Create: `src/Maestros/Application/Ingesta/PublicarSocios/PublicarSocios.php` y `PublicarSociosHandler.php`
- Create: `src/Maestros/Infrastructure/Eventos/PublicarSociosCommand.php`
- Modify: `app/Providers/CoreServiceProvider.php`, `app/Providers/ModulosServiceProvider.php`
- Test: `tests/Feature/Maestros/PublicarSociosTest.php`

**Interfaces:**
- Consumes: `SocioActualizado::de`, `SalidaDeEventos`.
- Produces:
  - `SocioRepository::conLineaYSinBaja(): list<Socio>`.
  - `final readonly class PublicarSocios implements Request, EscrituraDeMaquina, RequiereTransaccion`, sin propiedades.
  - Su handler devuelve `ResultWithValue<int>` con cuántos encoló.
  - `php artisan eventos:publicar-socios` imprime `Socios en la cola: N.`

- [ ] **Step 1: Escribir los tests que fallan**
- `it('encola un evento por socio con linea y sin baja')`:
  - con la semilla importada, más dos socios por la ingesta (uno activo y uno inactivo) y un tercero dado de baja;
  - el resultado es `2`, con dos filas en el outbox;
  - ningún `cardCode` de la semilla aparece;
  - cada `sobre` valida contra `SocioActualizado`, y su `ocurridoEn` es el `vigente_desde` del socio (EM5, EM13).
- `it('el comando dice cuantos encolo')`: `$this->artisan('eventos:publicar-socios')->expectsOutput('Socios en la cola: 2.')->assertExitCode(0)`.

- [ ] **Step 2: Correrlos.** Expected: FAIL, el comando no existe.

- [ ] **Step 3: Implementar.**
  - `conLineaYSinBaja` es `whereNotNull('linea_de_credito')->whereNull('dado_de_baja_el')`, ordenado por `codigo_de_socio`.
  - El handler lee cada grupo una sola vez: guarda en un arreglo los que ya buscó.

- [ ] **Step 4: Correrlos,** junto con `tests/Architecture/AlcanceTest.php`. Expected: PASS.

- [ ] **Step 5: Commit** `feat(eventos): eventos:publicar-socios para la puesta en marcha`.

### Task 5: El transporte REST

**Files:**
- Create: `config/servicebus.php`
- Create: `src/Maestros/Application/Eventos/BuzonDeEntrada.php`, `MensajeRecibido.php` y `PublicadorDeEventos.php`
- Create: `src/Maestros/Infrastructure/ServiceBus/FirmaSas.php`, `PublicadorServiceBusRest.php` y `BuzonServiceBusRest.php`
- Test: `tests/Unit/Maestros/FirmaSasTest.php`, `tests/Unit/Maestros/BuzonServiceBusRestTest.php` y `tests/Unit/Maestros/PublicadorServiceBusRestTest.php`

**Interfaces:**
- Produces, con las mismas firmas que en comercial (`src/Ingesta/Application/Contracts/*` y `src/Ingesta/Infrastructure/ServiceBus/*`):
  - `BuzonDeEntrada { recibir(?int $esperaSegundos = null): ?MensajeRecibido; completar(MensajeRecibido $mensaje): void; }`
  - `MensajeRecibido(string $cuerpo, string $idDeMensaje, int $numeroDeEntrega, string $referenciaDeBloqueo)`
  - `PublicadorDeEventos { publicar(array $sobre): void; }`
  - `FirmaSas::token(string $uriDelRecurso, string $nombreDeClave, string $clave, int $expiraEn): string`
  - Los constructores de `PublicadorServiceBusRest` y `BuzonServiceBusRest`, iguales a los de comercial.
- `config/servicebus.php` tiene las claves de EM14, sin `envio`:
  - `namespace`;
  - `topic`, con `comercial-eventos` por defecto;
  - `suscripcion`, con `sub-ms-maestros` por defecto;
  - `escucha.nombre` y `escucha.clave`;
  - `publicacion.topic`, con `maestros-eventos` por defecto;
  - `publicacion.nombre` y `publicacion.clave`;
  - `espera_segundos`, con 10 por defecto.

- [ ] **Step 1: Copiar los tests de comercial** (`tests/Unit/Ingesta/FirmaSasTest.php` y `BuzonServiceBusRestTest.php`) con los namespaces nuevos.

- [ ] **Step 2: Escribir el test del publicador**, con `Http::fake()`:
- `it('publica el sobre con MessageId, la cabecera tipo y el texto sin escapes')`:
  - la petición va a `https://ns.servicebus.windows.net/maestros-eventos/messages`;
  - `BrokerProperties` decodificado trae `MessageId` igual al `eventoId`;
  - la cabecera `tipo` es `"maestros.socio.actualizado"`, con las comillas;
  - el cuerpo contiene `Peña` literal (Review Focus 1).
- `it('una respuesta que no es 201 es una excepcion')`: 401 lanza `RuntimeException` con `401` en el mensaje.

- [ ] **Step 3: Correrlos.** Expected: FAIL, las clases no existen.

- [ ] **Step 4: Copiar y adaptar las clases.**

- [ ] **Step 5: Correrlos,** junto con `tests/Architecture`. Expected: PASS.

- [ ] **Step 6: Commit** `feat(eventos): el transporte REST de Service Bus, copiado de ms-comercial`.

### Task 6: La propiedad pasa a `estado`, y gana vigencia

**Files:**
- Modify: `database/sql/maestros-propiedades.sql` (bloques al final)
- Create: `database/migrations/maestros/2026_10_30_000200_propiedades_por_eventos.php`
- Modify: `src/Maestros/Infrastructure/Persistence/EloquentPropiedadesDeSocios.php:56-60`
- Modify: `tests/Soporte/PropiedadesDeEjemplo.php` y `database/sql/maestros-propiedades-ejemplo.sql` (`estado` en lugar de `activa`)
- Test: `tests/Feature/Maestros/PropiedadesDeSociosTest.php` (agregar un caso)

**Interfaces:**
- Produces: las columnas `estado` (`'activo'` o `'inactivo'`) y `vigente_desde` (nula, con 6 decimales y en UTC) en `maestros.propiedad`. `activa` queda sin leer ni escribir (EM9).

- [ ] **Step 1: Escribir el test que falla**
- `it('una propiedad inactiva no se lista ni se cuenta, aunque activa diga 1')`:
  - inserta `PROP-090` con `estado => 'inactivo'` y `activa => true` en `C-004872`;
  - `deSocio` no la trae;
  - `contarPorSocio` sigue en 1 para `C-004872`.

- [ ] **Step 2: Correrlo.** Expected: FAIL. Primero falla por la columna inexistente; con la columna y sin el cambio de lectura, porque la cuenta da 2.

- [ ] **Step 3: Implementar.**

Al final del script, cada bloque idempotente y separado por `GO`:
1. Agregar `estado` si `COL_LENGTH('maestros.propiedad','estado') IS NULL`: `ALTER TABLE maestros.propiedad ADD estado NVARCHAR(10) NOT NULL CONSTRAINT DF_propiedad_estado DEFAULT (N'activo');`
2. El `CHECK CK_propiedad_estado (estado IN (N'activo', N'inactivo'))`, si no existe en `sys.check_constraints`.
3. Agregar `vigente_desde DATETIME2(6) NULL`, si no existe.
4. Pasar `activa` a `estado`, solo en las filas que nunca tocó un evento, y dentro de `EXEC(N'...')`: cuando el paso 2 de EM9 borre `activa`, el lote ya no compila sin el `EXEC`.
   ```sql
   IF COL_LENGTH('maestros.propiedad', 'activa') IS NOT NULL
   EXEC(N'UPDATE maestros.propiedad SET estado = N''inactivo'' WHERE activa = 0 AND vigente_desde IS NULL');
   ```
5. Crear `IX_propiedad_socio_estado ON maestros.propiedad (codigo_de_socio) INCLUDE (estado, nombre)`, si no existe.

La migración:
- en sqlsrv vuelve a correr el script con `LotesDeScript`, como `2026_10_16_000100_preparar_el_catalogo_para_la_ingesta.php`;
- en SQLite agrega las dos columnas con Blueprint y hace el mismo `UPDATE`.

`activas()` pasa a `where('estado', 'activo')`. `PropiedadesDeEjemplo` y el script de ejemplo escriben `estado`: `PROP-003` es `'inactivo'`.

- [ ] **Step 4: Correr** `vendor/bin/pest tests/Feature/Maestros` y `tests/Feature/ContratoDeRespuestasTest.php`. Expected: PASS.

- [ ] **Step 5: Probar el script contra SQL Server local**, con `docker compose up -d sqlserver` y la imagen reconstruida:
  - `docker compose run --rm --no-deps -T app php artisan migrate --force` dos veces seguidas;
  - las dos terminan sin error, porque el script es idempotente.

- [ ] **Step 6: Commit** `feat(propiedades): estado en lugar de activa, y la vigencia de la foto`.

### Task 7: Procesar `comercial.propiedad.actualizada`

**Files:**
- Create: `src/Maestros/Domain/Propiedades/EstadoDePropiedad.php`, `PropiedadReplicada.php` y `PropiedadErrors.php`
- Create: `src/Maestros/Application/Eventos/Sobre.php` y `EventoInvalido.php` (copiados de comercial)
- Create: `src/Maestros/Application/Ingesta/ProcesarEvento/ProcesarEvento.php`, `ProcesarEventoHandler.php`, `ResultadoDelEvento.php` y `TraductorDePropiedad.php`
- Create: `src/Maestros/Application/Ingesta/RegistroDeEventos.php` y `ReplicaDePropiedades.php`
- Create: `src/Maestros/Infrastructure/Eventos/EloquentRegistroDeEventos.php` y `src/Maestros/Infrastructure/Persistence/EloquentReplicaDePropiedades.php`
- Modify: `app/Providers/CoreServiceProvider.php`, `app/Providers/ModulosServiceProvider.php`
- Test: `tests/Unit/Maestros/SobreTest.php` (copiado), `tests/Unit/Maestros/PropiedadReplicadaTest.php` y `tests/Feature/Maestros/ProcesarEventoTest.php`
- Create: `tests/Soporte/SobresDePropiedad.php`

**Interfaces:**
- Consumes: la tabla `evento_procesado` (Task 2) y las columnas de Task 6.
- Produces:
  - `enum EstadoDePropiedad: string { case Activo = 'activo'; case Inactivo = 'inactivo'; }`
  - `PropiedadReplicada::desde(string $id, string $nombre, string $cardCode, string $estado, DateTimeImmutable $vigenteDesde): self`. Lanza `DomainException` en estos casos:
    - un id que no cumple `^[A-Za-z0-9-]{1,30}$`;
    - un nombre vacío, de más de 200 caracteres, con espacio al principio o al final, o con TAB, LF, CR o U+00A0 en cualquier lugar;
    - un `cardCode` que `CodigoDeSocio::desde` rechaza;
    - un estado que no es del enum.

    Expone `id()`, `nombre()`, `socio(): CodigoDeSocio`, `estado(): EstadoDePropiedad` y `vigenteDesde()`.
  - `interface ReplicaDePropiedades { guardarSiEsMasNueva(PropiedadReplicada $propiedad): bool; }`. Devuelve `true` si escribió.
  - `interface RegistroDeEventos { yaProcesado(string $eventoId): bool; registrar(string $eventoId, string $tipo, ResultadoDelEvento $resultado): void; }`
  - `enum ResultadoDelEvento: string { case Aplicado = 'aplicado'; case IgnoradoPorViejo = 'ignorado-por-viejo'; case Duplicado = 'duplicado'; }`
  - `final readonly class ProcesarEvento implements Request, EscrituraDeMaquina, RequiereTransaccion { __construct(public Sobre $sobre) }`. El handler devuelve `ResultWithValue<ResultadoDelEvento>` y lanza `EventoInvalido` ante un tipo desconocido o datos inválidos.
  - `Sobre::desdeJson(string): Sobre` y `Sobre::desdeArreglo(array): Sobre`, como en comercial.
  - `Tests\Soporte\SobresDePropiedad::de(array $datos = [], array $sobre = []): array`. Por defecto es el ejemplo del contrato: `PROP-014`, «Lote 14 · San Julián», `zona` `norte`, `C-004871`, `activo`, `2026-10-21T12:00:00.000Z`.

- [ ] **Step 1: Escribir los tests que fallan.**

`SobreTest` se copia de comercial, armando con `SobresDePropiedad`. `PropiedadReplicadaTest` tiene un dataset por cada regla de `desde`.

`ProcesarEventoTest` va por el `Mediator`:
- `it('el ejemplo del contrato crea la propiedad activa con su vigencia')`:
  - primero, `Contrato::diferenciasDeEsquema(SobresDePropiedad::de(), 'eventos-de-dominio-v1.yaml', 'PropiedadActualizada')` es `[]`;
  - la fila tiene `estado = 'activo'` y `vigente_desde` `2026-10-21 12:00:00.000000`;
  - el resultado es `Aplicado`.
- `it('una foto mas nueva la reemplaza y la puede mover de socio')`.
- `it('una foto igual o mas vieja se ignora y queda registrada')`: el resultado es `IgnoradoPorViejo` y hay fila en `evento_procesado`.
- `it('dentro del mismo segundo gana la de mas microsegundos')`: `.100Z` y después `.900Z` con otro nombre; queda el nombre de la segunda (Review Focus 2).
- `it('un ocurridoEn con desfase se compara en UTC')`: guardada a las `12:00:00Z`; llega `2026-10-21T08:00:00-04:00` con otro nombre; se ignora (Review Focus 3).
- `it('una de la semilla, sin vigencia, se reemplaza')`: con `PropiedadesDeEjemplo::sembrar()`, un evento de `PROP-014` con 1970 la pisa.
- `it('la de un ITR llega con 1970 y cualquier foto real la pisa')`.
- `it('un eventoId repetido es Duplicado y no cambia nada')`.
- `it('un dato invalido lanza EventoInvalido y no deja nada escrito')`: dataset con `estado` `'baja'`, sin `estado`, nombre `'Lote 14 '`, `clave.propiedadId` distinto de `datos.propiedadId`, y `tipo` `'comercial.otra.cosa'`. Después, ni la propiedad ni `evento_procesado` cambiaron.
- `it('una inactiva por evento sale de la lista de la App')`: por HTTP, `GET /v1/socios/C-004871/propiedades` con la persona de la semilla no la trae.

- [ ] **Step 2: Correrlos.** Expected: FAIL, las clases no existen.

- [ ] **Step 3: Implementar.**
  - `TraductorDePropiedad::traducir(Sobre $sobre): PropiedadReplicada` lee `datos` y convierte un `DomainException` en `EventoInvalido`.
  - El handler: si el evento ya está registrado, es `Duplicado`; si el `tipo` no es `comercial.propiedad.actualizada`, lanza `EventoInvalido::sinTraductor`. Si no, traduce, guarda si es más nueva y registra.
  - `EloquentReplicaDePropiedades` lee la fila. Escribe si no existe, si `vigente_desde` es nula, o si la vigencia entrante es estrictamente mayor. Guarda `vigente_desde` con `format('Y-m-d H:i:s.u')` en UTC, y en el `update` nunca nombra `activa` ni `creado_en`.

- [ ] **Step 4: Correrlos,** junto con `tests/Architecture`. Expected: PASS.

- [ ] **Step 5: Commit** `feat(eventos): aplicar comercial.propiedad.actualizada`.

### Task 8: El consumidor

**Files:**
- Create: `src/Maestros/Application/Eventos/Consumidor.php`, `ResultadoDeVuelta.php` y `SaludDeLaBase.php`
- Create: `src/Maestros/Infrastructure/Eventos/SaludDeLaBaseEloquent.php` y `ConsumirCommand.php`
- Create: `tests/Dobles/BuzonEnMemoria.php`, `PublicadorEnMemoria.php`, `SaludFija.php` y `LoggerEspia.php` (copiados de comercial)
- Modify: `app/Providers/ModulosServiceProvider.php` (los vínculos de `BuzonDeEntrada`, `PublicadorDeEventos`, `SaludDeLaBase` y `RegistroDeEventos`, y el comando)
- Test: `tests/Unit/Maestros/ConsumidorTest.php` (copiado), `tests/Feature/Maestros/SaludDeLaBaseTest.php` (copiado) y `tests/Feature/Maestros/ConsumirCommandTest.php`

**Interfaces:**
- Consumes:
  - `SalidaDeEventos` (Task 2). `marcarPublicado` va sin el instante, así que el consumidor no recibe `Reloj`.
  - `BuzonDeEntrada` y `PublicadorDeEventos` (Task 5).
  - `ProcesarEvento` y `Sobre` (Task 7).
- Produces:
  - `Consumidor::__construct(BuzonDeEntrada, SaludDeLaBase, Mediator, LoggerInterface, SalidaDeEventos, PublicadorDeEventos)`
  - `Consumidor::vuelta(): ResultadoDeVuelta`, con los casos `BaseCaida`, `BusNoResponde`, `SinMensajes`, `Completado` y `Fallido`.
  - `php artisan eventos:consumir {--una-vez}`.

- [ ] **Step 1: Copiar `ConsumidorTest`** de comercial, con `SobresDePropiedad` en lugar de los sobres de comercial, y adaptar `eventoDePropiedadPendiente` a un evento de socio. Copiar también `SaludDeLaBaseTest`.
  - Sin `RelojFijo`: `marcarPublicado` no recibe el instante.
  - Las aserciones sobre `publicado_en` pasan a mirar que la fila ya no esté en `pendientes()`.

- [ ] **Step 2: Escribir el test del comando**
- `it('sin configuracion de Service Bus no arranca y dice que falta')`:
  - con `config(['servicebus.namespace' => '', 'servicebus.escucha.clave' => ''])`;
  - `$this->artisan('eventos:consumir --una-vez')->expectsOutputToContain('SERVICEBUS_NAMESPACE')->assertExitCode(1)` (Review Focus 5);
  - una configuración a la que le falta solo una de las cuatro (`namespace`, `escucha.nombre`, `escucha.clave`, `publicacion.clave`) también falla, y nombra esa.
- `it('con la base y el bus de dobles hace una vuelta y sale')`:
  - liga `BuzonDeEntrada` a un `BuzonEnMemoria` con un sobre de propiedad y `PublicadorDeEventos` a un `PublicadorEnMemoria`;
  - `eventos:consumir --una-vez` sale con 0;
  - la propiedad quedó escrita.

- [ ] **Step 3: Correrlos.** Expected: FAIL, las clases no existen.

- [ ] **Step 4: Copiar y adaptar** `Consumidor`, `ResultadoDeVuelta`, `ConsumirCommand` y `SaludDeLaBaseEloquent`.
  - La comprobación de la configuración va al principio de `handle()`, antes de resolver el buzón.
  - Los vínculos de `BuzonDeEntrada` y `PublicadorDeEventos` se arman desde `config('servicebus.*')`.

- [ ] **Step 5: Correr** `composer test`. Expected: PASS.

- [ ] **Step 6: Commit** `feat(eventos): eventos:consumir publica el outbox y recibe propiedades`.

### Task 9: `maestros:propiedades-sin-evento`

**Files:**
- Create: `src/Maestros/Infrastructure/Persistence/PropiedadesSinEventoCommand.php`
- Modify: `app/Providers/ModulosServiceProvider.php`
- Test: `tests/Feature/Maestros/PropiedadesSinEventoTest.php`

**Interfaces:**
- Produces: `php artisan maestros:propiedades-sin-evento {--borrar}`.
  - Muestra una tabla con id, nombre y socio de las filas con `vigente_desde` nula.
  - Con `--borrar`, las borra e imprime `Se borraron N propiedades.`
  - Sin ninguna, imprime `Todas las propiedades llegaron por un evento.`

- [ ] **Step 1: Escribir los tests que fallan**
- `it('lista solo las que nunca llegaron por un evento')`: siembra la semilla y aplica un evento a `PROP-014`; la salida trae `PROP-007` y no `PROP-014`.
- `it('con --borrar borra solo esas')`: quedan solo las que tienen vigencia.
- `it('sin pendientes lo dice')`.

- [ ] **Step 2: Correrlos.** Expected: FAIL, el comando no existe.

- [ ] **Step 3: Implementar,** con la forma de `ProductosSinSapCommand`. El docblock explica que el borrado es físico porque no hay un evento que borre una propiedad, y que se corre después del republicado de comercial (EM11).

- [ ] **Step 4: Correrlos.** Expected: PASS.

- [ ] **Step 5: Commit** `feat(propiedades): maestros:propiedades-sin-evento para borrar la semilla`.

### Task 10: La integración, el retiro del script y las reglas

**Files:**
- Create: `tests/Integration/ServiceBusTest.php` (copiado de comercial)
- Delete: `database/sql/maestros-propiedades-prueba.sql`
- Modify: `.env.example` (las variables de EM14, vacías), `CLAUDE.md`

- [ ] **Step 1: Copiar `ServiceBusTest`** con `env('SERVICEBUS_TOPIC', 'pruebas-ms-comercial')` y `env('SERVICEBUS_SUSCRIPCION', 'sub-pruebas')` por defecto. La clave de envío sale de `SERVICEBUS_SAS_ENVIO_*` (EM15).

- [ ] **Step 2: Comprobar que se salta sin variables.** `vendor/bin/pest -c phpunit.integration.xml tests/Integration/ServiceBusTest.php`. Expected: 2 skipped, con el motivo «sin namespace de Service Bus».

- [ ] **Step 3: `CLAUDE.md`.**
  - Reescribir el contrato «Las propiedades del socio tampoco son réplica de SAP»:
    - salen de ms-comercial por `comercial.propiedad.actualizada`;
    - `estado` decide qué se ve;
    - una foto vieja no pisa;
    - el id nunca se reusa ni se cambia;
    - el nombre es el de los reportes;
    - sin clave foránea;
    - `maestros:propiedades-sin-evento` se corre después del republicado de comercial.
  - Agregar un contrato «Los eventos de dominio», con lo que no se negocia:
    - el outbox en la transacción;
    - la semilla y los socios sin línea no se publican;
    - un evento inválido va a mensajes muertos;
    - una sola réplica de `ms-maestros-consumidor`;
    - nunca correr la prueba de integración contra los topics reales.
  - En «Pendientes», borrar `activa` junto con `vista_en_importacion_el` y `habilitada_el`.
  - Agregar una sección «Probar a mano» con el comando de integración de EM15.

- [ ] **Step 4: Correr** `composer test`, `composer stan` y `composer lint -- --test`. Expected: todo verde.

- [ ] **Step 5: Commit** `docs: los eventos de dominio en CLAUDE.md, y se retira el script de propiedades de prueba`.

---

## Después del plan

La puesta en marcha es la §8 de la spec, paso por paso y con confirmación del usuario en cada escritura en Azure: la migración antes del push, `ms-maestros-consumidor`, `eventos:publicar-socios` y la alerta. No es parte de las tareas.
