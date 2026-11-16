# Las notificaciones push — plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** que cada aviso nuevo de la bandeja de ms-comercial llegue como push, por Firebase, a los teléfonos de quienes ven ese socio y tienen prendida su categoría; y que un producto nuevo del catálogo se anuncie una sola vez.

**Architecture:**
- **El consumidor de eventos de Maestros** deja de conocer un solo tipo: delega en un puerto lo que no es suyo, y la implementación vive en `app/`, que traduce el sobre y despacha a Identidad (NP17).
- **Identidad** guarda el token push con la sesión, copia las preferencias, calcula los destinatarios y encola un trabajo por teléfono en la cola `push`. El `worker` lo manda por un puerto cuyo adaptador real es Firebase HTTP v1.
- **Maestros** marca `anunciado_el` y publica `maestros.producto.nuevo` por el outbox.

**Tech Stack:** Laravel 13 / PHP 8.4, Pest, Larastan 9, SQL Server en Azure y SQLite en las pruebas, cola `database`, `firebase/php-jwt` (ya instalada).

**Spec:** `docs/superpowers/specs/2026-11-10-notificaciones-push-design.md` (NP1–NP23, NP13 corregida el 16/11).

## Global Constraints

- `declare(strict_types=1);` en todo archivo de `src/`.
- **Reglas de módulos:**
  - Maestros no referencia Identidad.
  - El `Domain/` y el `Application/` no usan `Illuminate` ni Eloquent.
  - `tests/Architecture/EstructuraTest.php` tiene que seguir en verde sin tocarlo.
- **Tipos de evento, textuales:** `comercial.aviso.creado`, `comercial.preferencias.actualizadas`, `maestros.producto.nuevo`. Las formas son las de `contrato/eventos-de-dominio-v1.yaml`, que es una copia de ms-comercial y no se edita.
- **El token push nunca se escribe en un log ni viaja en el trabajo encolado.** El trabajo lleva el id de la sesión y el aviso (NP16, NP22).
- **Los pushes van en la cola `push`.** El `worker` corre `queue:work --queue=default,push`, así el código por WhatsApp va primero (NP23).
- **Categoría → interruptor:**
  - `factura` y `vencimiento` → `facturas`;
  - `pago` → `pagos`;
  - `reporte_campo` → `reportes`;
  - `catalogo` → `catalogo`.
  
  Sin fila de preferencias, todo prendido (NP8).
- **Aviso viejo (NP13):** si `creadoEn` tiene más de 24 horas, **o** si `fechaDelDocumento` (cuando no es `null`) es anterior a ayer en `America/La_Paz`.
- **Driver:** `PUSH_DRIVER=log` (por defecto) o `firebase`. La cuenta de servicio va solo en el `worker` (NP15, NP21).
- **Firebase HTTP v1:**
  - envío: `POST https://fcm.googleapis.com/v1/projects/{proyecto}/messages:send`;
  - token: `https://oauth2.googleapis.com/token` con el grant `urn:ietf:params:oauth:grant-type:jwt-bearer`;
  - alcance: `https://www.googleapis.com/auth/firebase.messaging`.
- `contrato/agropartners-api-v1.yaml` no se edita acá. La Task 7 espera a que el visor lo reemplace.
- `composer test`, `composer stan` y `composer lint -- --test` en verde antes de cada commit. Mensajes en español. Sin push: la puesta en marcha (al final) es del usuario.

## Review Focus

1. **El mismo token en dos sesiones abiertas** (reinstalación con otra instalación y el mismo token de Firebase): tiene que salir un solo push. → Task 5, «un token repetido recibe un solo trabajo».
2. **Una factura de hoy cuando en UTC ya es mañana** (después de las 20:00 en Bolivia): se manda. Una de anteayer no se manda. → Task 5, prueba de `esViejo` con el reloj en `2026-11-17T03:30:00Z`.
3. **Una persona con sesión y token que ya no entra** (SAP la puso en dos grupos, o dio de baja su contacto): no recibe nada, tampoco el catálogo. → Task 5, «una persona sin contexto no recibe ni el catalogo».
4. **Las preferencias de una persona sin sesiones** (se publican todas las existentes de una vez): se guardan igual, sin error. → Task 4.
5. **Un trabajo que corre después de un logout o de un cambio de token:** no manda, o usa el token del momento. → Task 6.

---

### Task 1: El consumidor delega los tipos que no son de Maestros

**Files:**
- Create: `src/Maestros/Application/Ingesta/ProcesarEvento/OtrosEventos.php`
- Modify: `src/Maestros/Application/Ingesta/ProcesarEvento/ProcesarEventoHandler.php`
- Create: `app/Eventos/EventosDeIdentidad.php`
- Modify: `app/Providers/ModulosServiceProvider.php` (el bind)
- Create: `tests/Dobles/OtrosEventosFalsos.php`
- Test: `tests/Feature/Maestros/ProcesarEventoTest.php`

**Interfaces:**
- Produces:
  - `interface OtrosEventos { public function atiende(string $tipo): bool; public function atender(Sobre $sobre): ResultadoDelEvento; }`.
  - `final readonly class App\Eventos\EventosDeIdentidad implements OtrosEventos`, con `__construct(Mediator $mediator)`. Hasta las Tasks 4 y 5 no atiende ningún tipo. Cada una le suma el suyo en un `match` por tipo, que es el único lugar donde se registran los tipos de Identidad.

- [ ] **Step 1: Las pruebas que fallan.** En `ProcesarEventoTest.php`, con `OtrosEventosFalsos` atado en el contenedor (`atiende` dice sí para los tipos que se le pasan, `atender` guarda el sobre y devuelve el resultado configurado):

```php
it('un tipo que atiende otro modulo se delega y queda registrado', function () {
    $otros = new OtrosEventosFalsos(['comercial.aviso.creado' => ResultadoDelEvento::Aplicado]);
    app()->instance(OtrosEventos::class, $otros);

    $resultado = procesar(sobreDeTipo('comercial.aviso.creado'));

    expect($resultado)->toBe(ResultadoDelEvento::Aplicado)
        ->and($otros->atendidos)->toHaveCount(1)
        ->and(eventoRegistrado($otros->atendidos[0]->eventoId))->toBeTrue();
});

it('un duplicado no le llega al otro modulo', function () { /* procesar dos veces: el segundo es Duplicado y atendidos sigue en 1 */ });

it('un tipo que nadie atiende sigue siendo invalido', function () { /* EventoInvalido, nada registrado */ });
```

`procesar()`, `sobreDeTipo()` y `eventoRegistrado()` siguen los helpers que ya tiene el archivo. Si no los tiene, se escriben en `tests/Soporte/`.

- [ ] **Step 2: Ver que fallan:** `composer test -- tests/Feature/Maestros/ProcesarEventoTest.php` → FAIL: no existe `OtrosEventos`.
- [ ] **Step 3: Implementación.** El handler sigue este orden:
  1. el duplicado;
  2. la propiedad;
  3. `otros->atiende()`, que delega con `atender()`;
  4. si nadie lo atiende, `EventoInvalido::sinTraductor`.
  
  Registra con el resultado que vuelve. Se bindea `OtrosEventos` a `EventosDeIdentidad`. El docblock del handler deja de decir «un solo tipo».
- [ ] **Step 4: Ver que pasan:** el archivo y `tests/Architecture` en verde.
- [ ] **Step 5: Commit** — `feat(eventos): el consumidor delega en Identidad los tipos que no son de Maestros`.

---

### Task 2: El token push vive con la sesión (NP2, NP3, NP4, NP5, NP19)

**Files:**
- Create: `database/migrations/identidad/2026_11_16_000100_token_push_en_sesiones.php` (`token_push` string 4096 nullable, `token_push_actualizado_en` dateTimeTz nullable, con el `tabla()` de las migraciones de Identidad)
- Create: `src/Identidad/Domain/Dispositivos/TokenPush.php`, `src/Identidad/Domain/Dispositivos/DispositivoErrors.php`
- Modify: `src/Identidad/Domain/Dispositivos/Dispositivo.php`, `src/Identidad/Domain/Sesiones/SesionDeAplicacion.php`, `src/Identidad/Domain/Sesiones/SesionRepository.php`
- Modify: `src/Identidad/Infrastructure/Persistence/SesionRecord.php`, `src/Identidad/Infrastructure/Persistence/EloquentSesionRepository.php`
- Modify: `src/Identidad/Application/Auth/IniciarSesion/IniciarSesion.php`, `IniciarSesionHandler.php`, `src/Identidad/Presentation/Http/AuthController.php`
- Test: `tests/Unit/Identidad/TokenPushTest.php`, `tests/Unit/Identidad/SesionDeAplicacionTest.php`, `tests/Feature/Identidad/IniciarSesionTest.php`, `tests/Feature/Identidad/RenovarYCerrarTest.php`, `tests/Feature/Identidad/SesionRepositorioTest.php`

**Interfaces:**
- Produces:
  - `TokenPush::desde(string $valor): self`. Recorta; lanza `DomainException(DispositivoErrors::tokenPushInvalido())` si queda vacío, pasa de 4096 o tiene espacios. Expone `valor(): string` y no tiene `__toString`.
  - `DispositivoErrors::tokenPushInvalido(): Error` (validación) y `DispositivoErrors::noEncontrado(): Error` (`notFound`, código `DISPOSITIVO_NO_ENCONTRADO`).
  - `Dispositivo::registrar(IdDeInstalacion, Plataforma, ?TokenPush $token = null)`, `tokenPush(): ?TokenPush`, `conToken(?TokenPush): self`.
  - En `SesionDeAplicacion`:
    - `cambiarTokenPush(TokenPush $token, DateTimeImmutable $ahora): void`;
    - `quitarTokenPush(): void`;
    - `desvincularDispositivo(): void`, que deja el dispositivo en `null`;
    - `tokenPush(): ?TokenPush`;
    - `cerrar()` además quita el token (NP5).
  - En `SesionRepository`:
    - `abiertasConInstalacion(IdDeInstalacion $instalacion, DateTimeImmutable $ahora): list<SesionDeAplicacion>`, de cualquier persona;
    - `abiertasConTokenPush(DateTimeImmutable $ahora): list<SesionDeAplicacion>`.
  - `IniciarSesion` suma `?string $tokenPush` al final.

- [ ] **Step 1: Las pruebas que fallan**
  - `TokenPushTest`: acepta un token de Firebase de ejemplo; rechaza `''`, `'   '`, `'con espacio'` y uno de 4097 caracteres.
  - `SesionDeAplicacionTest`:
    - `cerrar` deja `tokenPush()` en `null`;
    - `desvincularDispositivo` deja `dispositivo()` en `null`;
    - `cambiarTokenPush` en una sesión sin dispositivo no cambia nada.
  - `SesionRepositorioTest`:
    - el token y su fecha se guardan y se leen;
    - `abiertasConTokenPush` deja afuera una cerrada, una vencida y una sin token;
    - `abiertasConInstalacion` trae las de dos personas distintas.
  - `IniciarSesionTest`, con el armado que ya tiene el archivo:

```php
it('el login guarda el token push y responde dispositivoRegistrado true', function () {
    // dispositivo con tokenPush 'fcm-token-de-prueba'
    expect($respuesta->json('data.dispositivoRegistrado'))->toBeTrue()
        ->and(sesionesAbiertasDe('n-59170741828')[0]->tokenPush()?->valor())->toBe('fcm-token-de-prueba');
});
it('sin tokenPush entra y responde dispositivoRegistrado false', function () { /* NP2: 200, false */ });
it('un tokenPush mal formado es 400', function () { /* 'con espacio' → 400, error del campo dispositivo.tokenPush */ });
it('otra persona que entra en el mismo telefono le quita el dispositivo a la sesion anterior', function () { /* NP4 */ });
it('la misma persona que vuelve a entrar en el mismo telefono deja el dispositivo solo en la sesion nueva', function () { /* NP4 */ });
```

  La prueba que hoy espera `dispositivoRegistrado: true` **sin** token (línea 47) pasa a esperar `false`, por NP3.

  En `RenovarYCerrarTest`: `'el logout borra el token push de la sesion'`.

- [ ] **Step 2: Ver que fallan** (los cinco archivos).
- [ ] **Step 3: Implementación**
  - `AuthController` valida `dispositivo.tokenPush` como `['nullable', 'string', 'max:4096', 'regex:/^\S+$/']`, y `dispositivoRegistrado` pasa a `$sesion->tokenPush() !== null`.
  - `IniciarSesionHandler`, antes de guardar la sesión nueva, recorre `abiertasConInstalacion()` y le llama `desvincularDispositivo()` a cada una que no sea la nueva, en la misma transacción.
  - El repositorio mapea las dos columnas y escribe `token_push_actualizado_en` cuando cambia el token.
- [ ] **Step 4: Ver que pasan,** más `ContratoDeRespuestasTest`: el login sigue cumpliendo su esquema.
- [ ] **Step 5: Commit** — `feat(identidad): el login guarda el token push y un teléfono es de una sola sesión`.

---

### Task 3: Un producto nuevo se anuncia una sola vez (NP11, NP12)

**Files:**
- Modify: `database/sql/maestros-productos.sql` (bloque idempotente `IF COL_LENGTH('maestros.producto', 'anunciado_el') IS NULL ALTER TABLE … ADD anunciado_el DATETIME2(6) NULL`)
- Create: `database/migrations/maestros/2026_11_16_000300_anunciar_productos_nuevos.php`
- Create: `src/Maestros/Infrastructure/Persistence/VisibilidadDeProductos.php`; Modify: `EloquentCatalogoDeProductos.php`
- Create: `src/Maestros/Application/Ingesta/ProductoAnunciado.php`, `src/Maestros/Application/Eventos/ProductoNuevo.php`
- Modify: `src/Maestros/Application/Ingesta/ReplicaDeProductos.php`, `src/Maestros/Infrastructure/Persistence/EloquentReplicaDeProductos.php`, `src/Maestros/Application/Ingesta/ReplicarProducto/ReplicarProductoHandler.php`
- Test: `tests/Unit/Maestros/ProductoNuevoTest.php`, `tests/Feature/Ingesta/` (el archivo de la ingesta de productos que ya existe), `tests/Feature/Maestros/AnunciarProductosMigracionTest.php`

**Interfaces:**
- Produces:
  - `VisibilidadDeProductos::consulta(): Builder` es la definición que hoy está en el privado `visibles()` del catálogo, movida tal cual. El catálogo la usa, y el comentario «la única definición» se va con ella.
  - `ReplicaDeProductos::anunciarSiEsNuevo(string $itemCode, DateTimeImmutable $ahora): ?ProductoAnunciado`. Hace un `UPDATE … SET anunciado_el = ?` sobre `VisibilidadDeProductos` con `anunciado_el IS NULL`. Si tocó una fila devuelve el producto, y si no, `null`.
  - `final readonly class ProductoAnunciado(string $itemCode, string $nombre, string $categoriaCodigo, string $categoriaNombre)`.
  - `ProductoNuevo::TIPO = 'maestros.producto.nuevo'` y `ProductoNuevo::de(ProductoAnunciado $p, DateTimeImmutable $vigenteDesde): EventoSaliente`. El sobre sigue a `SocioActualizado`:
    - `ocurridoEn` es la vigencia del envío, en UTC con 6 decimales;
    - `clave` es `{objeto: producto, itemCode}`;
    - `datos` es `{itemCode, nombre, categoria: {codigo, nombre}}`;
    - un `nombre` de más de 200 caracteres se corta.

- [ ] **Step 1: Las pruebas que fallan**
  - `ProductoNuevoTest`: el sobre valida con `Contrato::diferenciasDeEsquema($sobre, 'eventos-de-dominio-v1.yaml', 'ProductoNuevo')`, y un `itemCode` distinto en la clave no valida.
  - En la ingesta:

```php
it('un producto que llega visible por primera vez deja su evento en el outbox', function () { /* POST activo con categoría → 1 evento maestros.producto.nuevo; anunciado_el no nulo */ });
it('el mismo producto reenviado no se anuncia de nuevo', function () { /* PUT más nuevo → sigue 1 evento */ });
it('uno que llega inactivo no se anuncia, y se anuncia cuando se activa', function () { /* 0, después 1 */ });
it('uno que vuelve de una baja, ya anunciado, no se anuncia', function () { /* POST, DELETE, POST más nuevo → 1 */ });
it('un producto sin categoria no se anuncia', function () { /* 0 */ });
```

  - `AnunciarProductosMigracionTest`: un producto que existía **inactivo** antes de la migración queda con `anunciado_el` y, al activarse, no se anuncia (NP12). Se arma con `migrate:rollback --step=1` y `migrate`.
- [ ] **Step 2: Ver que fallan.**
- [ ] **Step 3: Implementación**
  - **La migración:**
    - en `sqlsrv` corre el script con `LotesDeScript` y después `UPDATE maestros.producto SET anunciado_el = SYSUTCDATETIME() WHERE anunciado_el IS NULL`;
    - en SQLite hace `Schema::table` más el mismo `UPDATE`.
    
    El `UPDATE` va **solo en la migración**, no en el script: si estuviera en el script, cualquier corrida posterior marcaría como anunciados productos todavía no visibles.
  - **`ReplicarProductoHandler`:** después de `replicar()`, `anunciarSiEsNuevo($cuerpo->itemCode, $cuerpo->vigenteDesde)`. Si devuelve algo, `$this->salida->registrar(ProductoNuevo::de(...))`, en la misma transacción del pipeline. La baja no anuncia nada.
- [ ] **Step 4: Ver que pasan,** más todo `tests/Feature/Ingesta` y el catálogo.
- [ ] **Step 5: Commit** — `feat(productos): un producto nuevo se anuncia una vez por el outbox`.

---

### Task 4: Las preferencias se copian por evento (NP8)

**Files:**
- Create: `database/migrations/identidad/2026_11_16_000200_crear_preferencias_de_push.php`
  - columnas: `id_de_persona` string 100 PK; `facturas`, `pagos`, `reportes` y `catalogo` boolean; `vigente_desde` dateTimeTz con precisión 6.
- Create: `src/Identidad/Domain/Push/CategoriaDeAviso.php`, `src/Identidad/Domain/Push/PreferenciasDePush.php`, `src/Identidad/Domain/Push/PreferenciasDePushRepository.php`
- Create: `src/Identidad/Application/Push/RegistrarPreferencias/RegistrarPreferencias.php`, `RegistrarPreferenciasHandler.php`
- Create: `src/Identidad/Infrastructure/Persistence/EloquentPreferenciasDePushRepository.php`
- Modify: `app/Eventos/EventosDeIdentidad.php`, `app/Providers/ModulosServiceProvider.php`
- Test: `tests/Unit/Identidad/PreferenciasDePushTest.php`, `tests/Feature/Identidad/PreferenciasPorEventoTest.php`

**Interfaces:**
- Produces:
  - `enum CategoriaDeAviso: string { Factura = 'factura'; Vencimiento = 'vencimiento'; Pago = 'pago'; ReporteCampo = 'reporte_campo'; Catalogo = 'catalogo'; }`.
  - `PreferenciasDePush::de(IdDePersona, bool $facturas, bool $pagos, bool $reportes, bool $catalogo, DateTimeImmutable $vigenteDesde)`, `::todasPrendidas(IdDePersona)`, `permite(CategoriaDeAviso): bool`, `persona(): IdDePersona` y `vigenteDesde(): ?DateTimeImmutable`.
  - `PreferenciasDePushRepository`:
    - `de(IdDePersona): PreferenciasDePush`, que sin fila devuelve `todasPrendidas`;
    - `guardarSiEsMasNueva(PreferenciasDePush): bool`, que no pisa una foto más nueva y cuya igual cuenta como vieja, como en las propiedades.
  - `RegistrarPreferencias(string $persona, bool $facturas, bool $pagos, bool $reportes, bool $catalogo, DateTimeImmutable $ocurridoEn)` → `ResultWithValue<bool>` (true si se aplicó).

- [ ] **Step 1: Las pruebas que fallan**
  - `PreferenciasDePushTest` cubre la tabla de categorías de Global Constraints y, con `todasPrendidas`, que permite todo.
  - `PreferenciasPorEventoTest` usa el sobre de `ms-comercial/tests/Manual/eventos/comercial-preferencias-actualizadas.json`, copiado a `tests/Soporte/eventos/`, y pasa por `ProcesarEvento`, o sea por el despacho real:

```php
it('guarda las preferencias que llegan por el bus', function () { /* Aplicado; de(persona)->permite(Pago) según el sobre */ });
it('una foto vieja no pisa una nueva', function () { /* IgnoradoPorViejo */ });
it('las de una persona sin sesiones se guardan igual', function () { /* Review Focus 4 */ });
it('clave.persona distinta de datos.persona es un evento invalido', function () { /* EventoInvalido, nada registrado */ });
it('un interruptor que no es booleano es un evento invalido', function () { /* 'facturas' => 'si' */ });
```

- [ ] **Step 2: Ver que fallan.**
- [ ] **Step 3: Implementación.**
  - `EventosDeIdentidad` suma `comercial.preferencias.actualizadas` y traduce el sobre.
  - Un dato mal formado lanza `EventoInvalido::porDatos`. La persona se valida con `IdDePersona::desde()`, convirtiendo su `DomainException` en `EventoInvalido`.
  - Despacha `RegistrarPreferencias` por el `Mediator` y mapea el `bool` a `Aplicado` o `IgnoradoPorViejo`.
- [ ] **Step 4: Ver que pasan,** con la arquitectura.
- [ ] **Step 5: Commit** — `feat(push): las preferencias de cada persona se copian del bus`.

---

### Task 5: El aviso encola un push por teléfono que corresponde (NP7, NP9, NP13, NP14)

**Files:**
- Create: `src/Identidad/Domain/Push/AvisoParaPush.php`
- Create: `src/Identidad/Application/Contracts/ColaDePush.php`
- Create: `src/Identidad/Application/Push/AnunciarAviso/AnunciarAviso.php`, `AnunciarAvisoHandler.php`
- Create: `src/Identidad/Infrastructure/Push/ColaDePushEnLaravel.php`, `src/Identidad/Infrastructure/Push/EnviarPushJob.php` (en esta tarea, solo el constructor y `onQueue('push')`; el `handle` es de la Task 6)
- Modify: `app/Eventos/EventosDeIdentidad.php`, `app/Providers/ModulosServiceProvider.php`
- Create: `tests/Dobles/ColaDePushEnMemoria.php`
- Test: `tests/Unit/Identidad/AvisoParaPushTest.php`, `tests/Feature/Identidad/AnunciarAvisoTest.php`

**Interfaces:**
- Consumes: Task 2 (`abiertasConTokenPush`, `tokenPush()`), Task 4 (`PreferenciasDePushRepository::de`, `CategoriaDeAviso`) y `DirectorioDeContactos::contexto()`.
- Produces:
  - `AvisoParaPush(string $avisoId, CategoriaDeAviso $categoria, ?string $cardCode, string $titulo, string $cuerpo, DateTimeImmutable $creadoEn, ?string $fechaDelDocumento)`, con:
    - `esViejo(DateTimeImmutable $ahora): bool`, la regla de Global Constraints;
    - `aArreglo(): array` y `desdeArreglo(array): self`, para el trabajo.
  - `interface ColaDePush { public function encolar(IdDeSesion $sesion, AvisoParaPush $aviso): void; }`.
  - `AnunciarAviso(AvisoParaPush $aviso)` → `ResultWithValue<int>`, la cantidad de trabajos encolados.
  - `EnviarPushJob(string $idDeSesion, array $aviso)`, en la cola `push`.

- [ ] **Step 1: Las pruebas que fallan**
  - `AvisoParaPushTest`, con `$ahora = new DateTimeImmutable('2026-11-17T03:30:00Z')` (en Bolivia todavía es el 16):
    - `creadoEn` de hace 23 h y `fechaDelDocumento` `'2026-11-16'` → no es viejo;
    - `'2026-11-15'` → no es viejo;
    - `'2026-11-14'` → viejo;
    - `creadoEn` de hace 25 h con fecha `null` → viejo;
    - de hace 1 h con `null` → no es viejo;
    - `aArreglo`/`desdeArreglo` ida y vuelta.
  - `AnunciarAvisoTest`, por `ProcesarEvento` con el sobre de ejemplo de ms-comercial copiado a `tests/Soporte/eventos/`, `ColaDePushEnMemoria` en el contenedor y la réplica de ejemplo:

```php
it('encola un trabajo por cada sesion abierta con token de quien ve el socio', function () { /* 2 sesiones de la persona → 2 */ });
it('quien no ve el socio no recibe', function () {});
it('la categoria apagada no recibe', function () {});
it('una sesion vencida, una cerrada y una sin token no reciben', function () {});
it('una instalacion que paso a otra persona solo le llega a la nueva', function () { /* NP4 vía login de la Task 2 */ });
it('el catalogo le llega a toda persona que entra', function () { /* cardCode null */ });
it('una persona sin contexto no recibe ni el catalogo', function () { /* Review Focus 3 */ });
it('un token repetido recibe un solo trabajo', function () { /* Review Focus 1 */ });
it('un aviso viejo queda aplicado y no encola nada', function () { /* NP13 */ });
it('un duplicado no encola de nuevo', function () {});
it('un avisoId distinto en la clave, una categoria desconocida o un titulo de 66 caracteres son eventos invalidos', function () {});
it('el trabajo real va a la cola push y no lleva el token', function () {
    // Queue::fake() con ColaDePushEnLaravel → assertPushedOn('push', EnviarPushJob::class)
    // y que serialize($job) no contenga 'fcm-token-de-prueba'
});
```

- [ ] **Step 2: Ver que fallan.**
- [ ] **Step 3: Implementación**
  - **El handler:**
    1. si el aviso es viejo, devuelve 0;
    2. si no, toma `abiertasConTokenPush($ahora)`;
    3. agrupa por persona y pide `contexto()` una vez por persona;
    4. una persona recibe si su contexto no es `null` y, cuando hay `cardCode`, si está entre sus `socios`, y además si `permite(categoria)`;
    5. descarta los tokens repetidos;
    6. encola.
  - **`EventosDeIdentidad`** suma `comercial.aviso.creado`. Valida contra los límites del contrato (`avisoId` 1..60 e igual en la clave y en los datos; `titulo` 1..65; `cuerpo` 1..240; `creadoEn` instante; `fechaDelDocumento` `YYYY-MM-DD` o `null`) y responde `Aplicado` siempre que no lance.
- [ ] **Step 4: Ver que pasan.**
- [ ] **Step 5: Commit** — `feat(push): cada aviso encola un push por teléfono de quien lo puede ver`.

---

### Task 6: El worker manda por Firebase (NP15, NP16, NP20, NP21, NP22, NP23)

**Files:**
- Create: `src/Identidad/Application/Contracts/EnviadorDePush.php`, `src/Identidad/Application/Contracts/ResultadoDeEnvio.php`
- Modify: `src/Identidad/Infrastructure/Push/EnviarPushJob.php` (el `handle`)
- Create: `app/Push/EnviadorPushPorLog.php`, `app/Push/EnviadorFirebase.php`
- Create: `config/push.php`; Modify: `app/Providers/ModulosServiceProvider.php`, `.env.example`
- Modify: `compose.yaml` (worker: `--queue=default,push`), `infra/main.bicep` (args del worker, igual)
- Create: `tests/Manual/probar-push.php`
- Test: `tests/Unit/App/EnviadorFirebaseTest.php`, `tests/Feature/Identidad/EnviarPushJobTest.php`

**Interfaces:**
- Consumes: Task 2 (`SesionRepository`, `quitarTokenPush`), Task 4 (`PreferenciasDePushRepository`) y Task 5 (`AvisoParaPush`, `EnviarPushJob`).
- Produces:
  - `interface EnviadorDePush { public function enviar(TokenPush $token, AvisoParaPush $aviso): ResultadoDeEnvio; }`.
  - `final readonly class ResultadoDeEnvio`, con `::entregado()`, `::tokenMuerto()`, `::rechazado(string $motivo)` y `::reintentarEn(int $segundos)`, y `public EstadoDeEnvio $estado` y `public ?int $esperaSegundos`.
  - `config/push.php`: `driver` ← `PUSH_DRIVER` (`log`), `firebase.proyecto` ← `FIREBASE_PROYECTO` y `firebase.cuenta_de_servicio` ← `FIREBASE_CUENTA_DE_SERVICIO` (el JSON completo).

- [ ] **Step 1: Las pruebas que fallan**
  - `EnviadorFirebaseTest`, con `Http::fake` y una cuenta de servicio de prueba (una clave RSA generada en la prueba):
    - **las respuestas de NP20:**
      - 200 → `entregado`;
      - 404 `UNREGISTERED` → `tokenMuerto`;
      - 400 `INVALID_ARGUMENT` → `rechazado`;
      - 403 `SENDER_ID_MISMATCH` y 401 `THIRD_PARTY_AUTH_ERROR` → `rechazado`;
      - 429 → `reintentarEn(>= 60)`;
      - 503 con `Retry-After: 120` → `reintentarEn(120)`;
      - 500 → `reintentarEn` creciente.
    - **el token de acceso:** se pide una sola vez para dos envíos, porque queda en el caché.
    - **lo que va y lo que no va al log:** el log (`LoggerEspia`) trae el estado, el código y el mensaje de Firebase. El token push no aparece en el log ni en ningún pedido salvo el campo `message.token`.
    - **el cuerpo del mensaje:** `notification.title` y `notification.body` son los del aviso, y `data` trae `avisoId`, `categoria` y `cardCode` como texto (vacío si es `null`).
  - `EnviarPushJobTest`, con un `EnviadorDePush` falso:

```php
it('manda con el token que la sesion tiene al correr', function () { /* cambiarTokenPush después de encolar → usa el nuevo (Review Focus 5) */ });
it('no manda si la sesion se cerro o vencio mientras esperaba', function () {});
it('no manda si la persona apago la categoria mientras esperaba', function () {});
it('no manda un aviso que se volvio viejo mientras esperaba', function () {});
it('un token muerto se borra de la sesion y no se reintenta', function () {});
it('un rechazo no se reintenta ni borra el token', function () {});
it('reintentarEn libera el trabajo con esa espera', function () { /* $job->release($segundos) */ });
```

- [ ] **Step 2: Ver que fallan.**
- [ ] **Step 3: Implementación**
  - **El adaptador de Firebase:**
    - firma el JWT con `Firebase\JWT\JWT::encode` (RS256, `iss` y `sub` = `client_email`, `aud` = la URL del token, `scope` de Global Constraints, `exp` a una hora);
    - guarda el token de acceso en el caché hasta 5 minutos antes de vencer;
    - un 401 al enviar invalida el caché.
  - **`EnviadorPushPorLog`** escribe `'Push que se mandaría'` con la sesión y el aviso, sin el token.
  - **El binding** sigue al de WhatsApp: cualquier valor distinto de `firebase` es el log.
  - **`$tries`** del trabajo: 6, con espera que no pase las 24 horas de NP13.
  - **`probar-push.php`** sigue a `probar-whatsapp.php`: lee el `.env`, recibe el token por argumento y no lo imprime.
- [ ] **Step 4: Ver que pasan,** más `composer stan`.
- [ ] **Step 5: Commit** — `feat(push): el worker manda por Firebase, o al log con PUSH_DRIVER=log`.

---

### Task 7: Cambiar el token sin cerrar la sesión (NP18)

**Precondición:** `contrato/agropartners-api-v1.yaml`, reemplazado desde el visor, trae:
- `tokenPush` en `RegistroDispositivo`;
- la operación de NP18;
- el texto nuevo del bloque `dispositivo`.

Verificar con `grep -n "tokenPush" contrato/agropartners-api-v1.yaml`. **Si no está, esta tarea no se hace**: queda anotada como bloqueada en el ledger, porque `ContratoTest` prohíbe exponer en `/v1` lo que el contrato no tiene.

**Files:**
- Create: `src/Identidad/Application/Push/CambiarTokenPush/CambiarTokenPush.php`, `CambiarTokenPushHandler.php`
- Modify: `src/Identidad/Presentation/Http/AuthController.php` (o un controlador propio), `src/Identidad/Presentation/Http/routes.php`, `app/Http/MapaDeErroresHttp.php` si hace falta el 404
- Modify: `docs/ms-maestros.postman_collection.json` (el login con `tokenPush` y un pedido para NP18)
- Test: `tests/Feature/Identidad/CambiarTokenPushTest.php`, `tests/Feature/ContratoDeRespuestasTest.php`

**Interfaces:**
- Consumes: Task 2 (`cambiarTokenPush`, `abiertasDe`, `DispositivoErrors::noEncontrado`).
- Produces: la ruta y el código que fije el contrato. Lo previsto: `PUT /v1/dispositivos/{instalacionId}/token` con `{ tokenPush }`, que responde 204 o 404 `DISPOSITIVO_NO_ENCONTRADO`.

- [ ] **Step 1: Las pruebas que fallan**
  - `'cambia el token de la sesion propia y responde 204'`;
  - `'la instalacion de otra persona responde 404'`;
  - `'una sesion cerrada responde 404'`;
  - `'un token mal formado es 400'`;
  - `'sin token de acceso es 401'`;
  - los mismos casos en `ContratoDeRespuestasTest` contra el esquema.
- [ ] **Step 2: Ver que fallan.**
- [ ] **Step 3: Implementación.** El handler busca entre `abiertasDe($persona)` la que tenga ese `instalacionId`. Si no la encuentra, devuelve `failure(DispositivoErrors::noEncontrado())`.
- [ ] **Step 4: Ver que pasan, y que `ContratoTest` también pasa.**
- [ ] **Step 5: Commit** — `feat(identidad): la App cambia el token push sin cerrar sesión`.

---

### Task 8: Lo que queda escrito

**Files:** `CLAUDE.md`

- [ ] **Step 1: Agregar a «Contratos que no se negocian» un punto sobre las notificaciones push.** Tiene que decir:
  - quién manda y por qué (NP7, NP10);
  - que el token nunca va al log ni al trabajo;
  - la cola `push` y el `--queue=default,push` del `worker`;
  - `PUSH_DRIVER` y las dos variables de Firebase, solo en el `worker`;
  - NP13 con la fecha del documento en hora de Bolivia;
  - que `anunciado_el` se marcó una vez en la migración y nunca en el script;
  - los filtros de las dos suscripciones, nombrando cada tipo.
- [ ] **Step 2: En «Probar a mano», un apartado «El push»** con `probar-push.php` y la advertencia de que Firebase acepte no es que llegue.
- [ ] **Step 3: Compuertas y commit** — `docs: las notificaciones push en CLAUDE.md`.

---

## Puesta en marcha (la hace el usuario, en este orden)

1. **Cambiar el filtro de `sub-ms-comercial` en el portal:** `tipo LIKE 'maestros.socio.%' OR tipo = 'maestros.producto.nuevo'`. Puede ir antes que todo.
2. **Correr la migración contra Azure desde la imagen local**, antes del push: la del token en las sesiones, las preferencias y `anunciado_el` con la marca de NP12.
3. **Cambiar los argumentos del Container App `worker`** a `queue:work --queue=default,push --tries=3 --max-time=3600`, con `PUSH_DRIVER=log`.
4. **Push a `azure` y `origin`.**
5. **Cambiar el filtro de `sub-ms-maestros`:** `tipo LIKE 'comercial.propiedad.%' OR tipo = 'comercial.aviso.creado' OR tipo = 'comercial.preferencias.actualizadas'`.
6. **Después de la carga desde `AGRO_PRD`**, ms-comercial prende su publicación y publica las preferencias una vez.
7. **Firebase:** crear el proyecto y cargar la clave `.p8` de Apple. En el `worker` van `FIREBASE_PROYECTO`, la cuenta de servicio como secreto en `FIREBASE_CUENTA_DE_SERVICIO` y `PUSH_DRIVER=firebase`. Probar con `probar-push.php` contra un teléfono.
