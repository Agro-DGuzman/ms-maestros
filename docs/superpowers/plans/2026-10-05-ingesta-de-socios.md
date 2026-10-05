# Ingesta de socios Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que el Sincronizador escriba socios, contactos y grupo económico en
la réplica por `POST`/`PUT`/`DELETE /ingesta/v1/socios`. La API valida el
secreto del gateway, el token de Entra y la idempotencia. La réplica gana
grupo opcional, estados y baja, y el alcance, el ingreso, la renovación y el
back-office los respetan.

**Architecture:**
- **Maestros es dueño de la réplica.** Dos casos de uso en
  `Maestros/Application/Ingesta` escriben grupo → socio → contactos en una
  transacción, y deciden todo lo que puede fallar antes de escribir.
- **La seguridad y la idempotencia son middlewares del host (`app/`)**, en
  orden fijo.
- **Qué es «visible»** se define una sola vez en los repositorios, y de eso
  cuelgan el alcance, el contexto, el ingreso y la renovación.

**Tech Stack:** PHP 8.4, Laravel, Pest, firebase/php-jwt (ya instalado), SQL
Server (Azure SQL) y SQLite en memoria para la batería.

**Spec:** `docs/superpowers/specs/2026-10-05-ingesta-de-socios-design.md`

## Global Constraints

- **Rama:** `feat/ingesta-de-socios`, desde `main`.
- **Calidad:**
  - `declare(strict_types=1);` en todo `src/`;
  - `Domain/` y `Application/` sin Eloquent ni Laravel;
  - Maestros no referencia `Identidad\`.
- **Nombres y commits:** andamiaje en inglés, negocio en español; comentarios
  en español y solo el porqué; commits en español con
  `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- **Antes de cada commit:** `composer test`, `composer stan` y
  `composer lint -- --test` en verde. Cada archivo de prueba corre solo, y
  primero va el test en rojo, verificado.
- **Id de persona:** `p-{CntctCode}`, solo dígitos, a lo sumo 38.
- **Habilitación:** la ingesta nunca escribe `habilitada_el`.
- **Visibilidad:**
  - socio visible = `activo` y sin `dado_de_baja_el`;
  - contacto visible = `activo`, sin `dado_de_baja_el` y su socio visible;
  - celular en conflicto = lo comparten dos o más contactos visibles.
- **Seguridad, en este orden:** secreto del gateway (403 `ACCESO_DENEGADO`)
  → token de Entra (401 `TOKEN_INVALIDO` / 503 `ENTRA_NO_DISPONIBLE` / 500
  `CONFIGURACION_INCOMPLETA`) → `Idempotency-Key` (400).
- **Idempotencia:** por la terna `(clave, método, path completo)`; no se
  guardan las 5xx.
- **Status de la ingesta:**
  - 201 `POST`, 200 `PUT`, 204 `DELETE` sin cuerpo;
  - 409 `SOCIO_YA_EXISTE`, 404 `SOCIO_NO_ENCONTRADO`, 422
    `CARDCODE_NO_COINCIDE`;
  - 400 con un `FieldError` por campo.
- **Rol por defecto:** `INGESTA_ROL` = `Ingesta.Maestros.Escribir`.
- **Lecturas de Entra:** `iss` =
  `https://login.microsoftonline.com/{tenant}/v2.0`, JWKS en
  `https://login.microsoftonline.com/{tenant}/discovery/v2.0/keys`, caché de
  60 minutos y, ante un `kid` desconocido, un refetch como mucho cada 5
  minutos. Tolerancia de reloj: 60 segundos.

## Review Focus

1. **`vigenteDesde` con fracciones de segundo o con offset distinto de `Z`.**
   Si lo guardado se trunca a segundos y el cuerpo no, un cuerpo viejo dentro
   del mismo segundo parece más nuevo y pisa al nuevo.
   - **Esperado:** las dos vigencias se comparan en UTC truncadas al segundo,
     y empatar es ignorar.
   - **Test:** en la Tarea 5.
2. **Celulares de SAP con formatos variados o fijos** (`+591 70741828`,
   `591-7074-1828`, `33456789`).
   - **Esperado:** los móviles se normalizan; un fijo queda sin celular y el
     socio se guarda igual.
   - **Test:** en la Tarea 5.
3. **Contacto con nombre vacío o solo espacios.** El back-office calcula
   iniciales con `RazonSocial::desde($nombre)`, que lanza con vacío, así que
   un contacto así rompería la pantalla de Contactos.
   - **Esperado:** 400 con `FieldError` en `contactos.N.nombre` (SAP siempre
     trae `OCPR.Name`, así que es un dato corrupto).
   - **Test:** en la Tarea 7.
4. **La misma terna llega dos veces casi a la vez.** El segundo `INSERT` en
   `ingesta_respuestas` violaría la clave primaria.
   - **Esperado:** se guarda la primera y la segunda no explota
     (`insertOrIgnore`).
   - **Test:** en la Tarea 6.
5. **Un token v1 de Entra** (el manifiesto no quedó en versión 2: `iss`
   `https://sts.windows.net/{tenant}/`, `aud` `api://…`).
   - **Esperado:** 401, y una línea de log que diga **qué** falló (`iss`,
     `aud`, rol, firma o vencimiento). Sin ella, un error de configuración en
     Entra no se distingue de un ataque.
   - **Test:** en la Tarea 6.

---

### Task 1: El modelo de la réplica

**Files:**
- Create: `database/migrations/maestros/2026_10_05_000100_preparar_la_replica_para_la_ingesta.php`
- Modify: `src/Maestros/Domain/Socios/Socio.php`,
  `src/Maestros/Domain/Contactos/PersonaDeContacto.php`,
  `src/Maestros/Domain/Contactos/IdDePersona.php`,
  `src/Maestros/Domain/Grupos/GrupoEconomico.php`,
  `src/Maestros/Domain/Contactos/ContactoRepository.php`
- Modify: `src/Maestros/Infrastructure/Persistence/{SocioRecord,ContactoRecord,GrupoRecord}.php`
  y sus `Eloquent*Repository.php`
- Modify: `src/Maestros/Infrastructure/Importacion/ImportarMaestrosCommand.php`
  y todo llamador de `Socio::idDeGrupo()` y `PersonaDeContacto::celular()`
  (PHPStan los marca)
- Test: `tests/Unit/Maestros/IdDePersonaTest.php`,
  `tests/Unit/Maestros/SocioTest.php`,
  `tests/Feature/Maestros/ReplicaParaIngestaTest.php`,
  `tests/Feature/Maestros/ImportarMaestrosTest.php` (ajuste)

**Interfaces:**
- Produces:
  - `IdDePersona::deContactoSap(string $codigo): IdDePersona`: `p-{codigo}`.
    `DomainException` con `ContactoErrors::idInvalido()` si no son solo
    dígitos o pasan de 38.
  - `Socio::replica(CodigoDeSocio $codigo, RazonSocial $razonSocial, ?IdDeGrupo $grupo, DateTimeImmutable $vigenteDesde, bool $activo = true, ?DateTimeImmutable $dadoDeBajaEl = null, ?string $origenEsquema = null, ?int $origenEventoId = null): Socio`.
  - `Socio::grupo(): ?IdDeGrupo` reemplaza a `idDeGrupo()`. Se suman
    `activo(): bool`, `dadoDeBajaEl(): ?DateTimeImmutable`,
    `esVisible(): bool`, `dadoDeBaja(DateTimeImmutable $momento): Socio` (la
    baja y `vigenteDesde = $momento`) y los getters del origen.
  - `PersonaDeContacto::replica(IdDePersona $id, CodigoDeSocio $socio, string $nombre, ?Celular $celular, ?DateTimeImmutable $habilitadaEl, DateTimeImmutable $vigenteDesde, bool $activa = true, ?DateTimeImmutable $dadoDeBajaEl = null): PersonaDeContacto`.
    `celular(): ?Celular`; se suman `activa(): bool` y
    `dadoDeBajaEl(): ?DateTimeImmutable`.
  - `GrupoEconomico::replica(IdDeGrupo $id, string $nombre, DateTimeImmutable $vigenteDesde, ?string $segmento = null)`
    y `segmento(): ?string`.
  - `ContactoRepository::darDeBajaLosQueNoVinieron(CodigoDeSocio $socio, list<IdDePersona> $vistos, DateTimeImmutable $momento): void`:
    los contactos del socio sin baja y fuera de `$vistos` quedan con
    `dado_de_baja_el = $momento`.
  - `ContactoRepository::darDeBajaAusentes(list<IdDePersona> $vistos, DateTimeImmutable $momento): void`:
    lo mismo pero sobre toda la tabla, para el importador. Reemplaza a
    `marcarVistasEnImportacion`.

- [ ] **Step 1: Tests en rojo**

`IdDePersonaTest`:
- `deContactoSap('1523')` → `p-1523`.
- `deContactoSap('15a3')`, `''` y `str_repeat('9', 39)` lanzan
  `DomainException`.

`SocioTest`:
- `esVisible()` es verdadero con `activo` y sin baja; falso inactivo; falso
  con baja.
- `dadoDeBaja($t)` deja `dadoDeBajaEl() == $t` y `vigenteDesde() == $t`.

`ReplicaParaIngestaTest` (RefreshDatabase), guardar y releer por los
repositorios:
- un socio **sin grupo** conserva `grupo() === null`;
- `activo = false`, la baja y el origen (`AGRO_P6`, `1842`) se conservan;
- un grupo con `segmento: 'Agroindustrial'` y un `codigo` de 50 caracteres se
  conserva;
- dos contactos con el **mismo celular** se guardan los dos (ya no es único);
- un contacto **sin celular** se guarda y se relee con `celular() === null`;
- `darDeBajaLosQueNoVinieron(C-004871, [p-a1b2c301], $t)` deja con baja a los
  otros contactos visibles de `C-004871` y no toca los de `C-004872`, ni la
  baja anterior de quien ya la tenía.

`ImportarMaestrosTest`: un contacto que estaba y no viene en el archivo queda
con `dado_de_baja_el` (reemplaza a la aserción sobre
`vista_en_importacion_el`).

- [ ] **Step 2: Correr y ver el rojo**

Run: `vendor/bin/pest tests/Unit/Maestros/IdDePersonaTest.php tests/Unit/Maestros/SocioTest.php tests/Feature/Maestros/ReplicaParaIngestaTest.php tests/Feature/Maestros/ImportarMaestrosTest.php`
Expected: FAIL (métodos inexistentes y columnas faltantes).

- [ ] **Step 3: La migración**

Con `$this->tabla()` como `crear_esquema_maestros`, para los dos motores:
- **`grupos`:**
  - `id_de_grupo` pasa a `string(50)`, y en SQL Server la clave primaria
    se borra y se recrea alrededor del `change()`;
  - se agrega `segmento` `string(100)` anulable.
- **`socios`:**
  - `id_de_grupo` pasa a `string(50)` anulable, y su índice se borra y se
    recrea alrededor del `change()`;
  - se agregan `activo` boolean default `true`, `dado_de_baja_el`
    `dateTimeTz` anulable, `origen_esquema` `string(20)` anulable y
    `origen_evento_id` `unsignedBigInteger` anulable.
- **`contactos`:**
  - `dropUnique(['celular'])`;
  - `celular` pasa a anulable con `index()`;
  - se agregan `activo` boolean default `true` y `dado_de_baja_el`
    `dateTimeTz` anulable.

`vista_en_importacion_el` **no se toca**. `down()` revierte en orden
inverso.

- [ ] **Step 4: Dominio, persistencia, importador y llamadores**

- El importador usa `darDeBajaAusentes` con los ids que vio.
- Los llamadores de `idDeGrupo()` y `celular()` pasan a manejar `null`;
  PHPStan los marca.
- El comportamiento de visibilidad todavía no cambia: es la Tarea 2.

- [ ] **Step 5: Verde**

Run: los tests del Step 2, después `composer test`, `composer stan` y
`composer lint -- --test`.
Expected: PASS.

- [ ] **Step 6: Contra el SQL Server local**

- `docker compose -f compose.yaml build app`
- `docker compose -f compose.yaml run --rm --no-deps -T app php artisan migrate --force`

**Expected:** `DONE`. Además, con `sqlcmd` (el script por archivo):
- `maestros.contactos` ya no tiene `maestros_contactos_celular_unique`;
- dos `INSERT` con el mismo celular entran;
- `maestros.socios.id_de_grupo` admite `NULL`;
- `maestros.grupos.id_de_grupo` admite 50 caracteres.

Después: `migrate:rollback --step=1` y de nuevo `migrate`, sin errores.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/maestros src/Maestros tests/Unit/Maestros tests/Feature/Maestros
git commit -m "feat(maestros): la replica admite socios sin grupo, bajas y celulares compartidos"
```

---

### Task 2: Visibilidad, alcance, contexto e ingreso

**Files:**
- Modify: `src/Maestros/Domain/Contactos/ContactoRepository.php`,
  `src/Maestros/Domain/Socios/SocioRepository.php`
- Modify: `src/Maestros/Infrastructure/Persistence/EloquentContactoRepository.php`,
  `src/Maestros/Infrastructure/Persistence/EloquentSocioRepository.php`
- Modify: `src/Maestros/Infrastructure/Alcance/ResolutorPorGrupo.php`,
  `src/Maestros/Application/Contactos/ObtenerContexto/ObtenerContextoHandler.php`,
  `src/Maestros/Application/Contactos/BuscarPorCelular/BuscarPorCelularHandler.php`
- Create: `tests/Soporte/ReplicaDeEjemplo.php`, con métodos que, sobre la
  semilla importada, dejan a un socio sin grupo, inactivo o dado de baja, o
  a dos contactos con el mismo celular
- Test: `tests/Feature/Maestros/VisibilidadTest.php`

**Interfaces:**
- Consumes: Tarea 1.
- Produces:
  - `ContactoRepository::visible(IdDePersona $id): ?PersonaDeContacto`:
    contacto visible con su socio visible.
  - `ContactoRepository::porCelular(Celular $celular): ?PersonaDeContacto`
    **cambia**: devuelve el único contacto visible con ese celular, y `null`
    si no hay ninguno o si hay dos o más.
  - `SocioRepository::porGrupo(IdDeGrupo $grupo)` devuelve solo socios
    visibles.

- [ ] **Step 1: Tests en rojo**

`VisibilidadTest`, con la semilla importada y la persona `p-8f2b1c40` (socio
`C-004871`, grupo `GRP-014`):
- **Alcance de un socio inactivo:** con `C-004872` inactivo,
  `GET /v1/socios/C-004872/propiedades` → 403 `ACCESO_DENEGADO`, y
  `/v1/socios` lista dos socios.
- **Alcance de un socio dado de baja:** con `C-004873` dado de baja, igual:
  403, y no aparece en `/v1/socios`.
- **Alcance sin grupo:** con `C-004871` sin grupo, `/v1/socios` lista solo
  `C-004871`, `GET /v1/mi-cuenta` trae `data.grupoEconomico.id` === `''` y
  `nombre` === `''`, y `C-004872` → 403.
- **Persona inactiva:** con `p-8f2b1c40` inactiva, `/v1/socios/C-004871/propiedades`
  → 403, y `ObtenerContexto` falla con `CONTACTO_NO_ENCONTRADO`.
- **Ingreso con conflicto:** con otro contacto visible con `70741828`,
  `BuscarPorCelular(70741828)` falla, y `POST /v1/auth/otp` responde 200 y
  no encola un envío para `p-8f2b1c40` (el doble del enviador queda vacío).
- **Ingreso con el otro dado de baja:** si el otro contacto con el mismo
  número está dado de baja, `BuscarPorCelular(70741828)` vuelve a dar
  `p-8f2b1c40`.
- **Ingreso con el socio dado de baja:** con `C-004871` dado de baja,
  `BuscarPorCelular(70741828)` falla.

- [ ] **Step 2: Correr y ver el rojo**

Run: `vendor/bin/pest tests/Feature/Maestros/VisibilidadTest.php`
Expected: FAIL (hoy todo es visible y el celular es único).

- [ ] **Step 3: Implementar**

- **`EloquentContactoRepository`:** un `Builder` privado `visibles()` (join a
  socios con las dos condiciones) es **la única definición**, y lo usan
  `visible()` y `porCelular()`. `porCelular` trae a lo sumo 2 filas y
  devuelve `null` si no es exactamente una.
- **`EloquentSocioRepository::porGrupo`:** filtra visibles.
- **`ResolutorPorGrupo`:** usa `contactos->visible()`, exige
  `$pedido->esVisible()` y, sin grupo, compara los códigos.
- **`ObtenerContextoHandler`:** usa `visible()`. Sin grupo arma
  `socios = [el suyo]` con `grupoId: ''` y `grupoNombre: ''`, y sigue
  contando propiedades con `contarPorSocio`.

- [ ] **Step 4: Verde**

Run: `vendor/bin/pest tests/Feature/Maestros/VisibilidadTest.php`, después
`composer test`, `composer stan` y `composer lint -- --test`.
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Maestros tests/Soporte/ReplicaDeEjemplo.php tests/Feature/Maestros/VisibilidadTest.php
git commit -m "feat(maestros): lo inactivo y lo dado de baja no se ven, y un celular compartido no entra"
```

---

### Task 3: Renovación cortada y habilitación con resguardos (Identidad)

**Files:**
- Modify: `src/Identidad/Application/Auth/RenovarSesion/RenovarSesionHandler.php`
- Modify: `src/Identidad/Application/Habilitacion/HabilitarPersona/HabilitarPersonaHandler.php`
- Modify: `src/Maestros/Domain/Contactos/ContactoErrors.php`
- Test: `tests/Feature/Identidad/RenovarYCerrarTest.php` (casos nuevos),
  `tests/Feature/Identidad/HabilitarPersonaTest.php` (casos nuevos)

**Interfaces:**
- Consumes:
  - `DirectorioDeContactos::contexto(IdDePersona): ?ContextoDeContacto`, que
    ahora da `null` para una persona no visible (Tarea 2);
  - `ContactoRepository::visible()` y `porCelular()` (Tarea 2).
- Produces:
  - `ContactoErrors::noVisible(string $id)`: validation,
    `CONTACTO_NO_VISIBLE`, «La persona está inactiva o dada de baja en
    SAP.»
  - `ContactoErrors::celularEnConflicto()`: validation,
    `CELULAR_EN_CONFLICTO`, «Otro contacto activo tiene el mismo celular:
    hay que corregirlo en SAP.»

- [ ] **Step 1: Tests en rojo**

- **`RenovarYCerrarTest`:**
  - `renovar con la persona dada de baja responde REFRESH_TOKEN_INVALIDO y cierra la sesión`:
    abrir sesión, dar de baja a `p-8f2b1c40` en la réplica, pedir
    `POST /v1/auth/refresh` → 401 `REFRESH_TOKEN_INVALIDO`;
  - el mismo refresh, después de reactivarla, sigue dando 401, porque la
    sesión quedó cerrada.
- **`HabilitarPersonaTest`:**
  - una persona dada de baja → `CONTACTO_NO_VISIBLE`, sin llamar al
    directorio de identidades (el doble no registra llamadas);
  - una persona con el celular en conflicto → `CELULAR_EN_CONFLICTO`;
  - una persona sin celular → el `celularNoUtilizable` de siempre.

- [ ] **Step 2: Correr y ver el rojo**

Run: `vendor/bin/pest tests/Feature/Identidad/RenovarYCerrarTest.php tests/Feature/Identidad/HabilitarPersonaTest.php`
Expected: FAIL.

- [ ] **Step 3: Implementar**

- **`RenovarSesionHandler`** recibe `DirectorioDeContactos`. Después de
  `estaAbierta()`: si `contexto($sesion->persona()) === null`, entonces
  `$sesion->cerrar($ahora)`, se guarda la sesión y responde
  `refreshInvalido()`.
- **`HabilitarPersonaHandler`:** `visible()` → si es null, `noVisible`; si
  no tiene celular, `celularNoUtilizable`; si
  `porCelular($celular)?->idDePersona()` no es ella, `celularEnConflicto`.

- [ ] **Step 4: Verde**

Run: los tests del Step 2, después `composer test`, `composer stan` y
`composer lint -- --test`.
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Identidad src/Maestros/Domain/Contactos/ContactoErrors.php tests/Feature/Identidad
git commit -m "feat(identidad): una baja en SAP corta la renovacion y frena la habilitacion"
```

---

### Task 4: La pantalla de Contactos del back-office

**Files:**
- Modify: `src/Maestros/Application/Contactos/ContactoDeBackOffice.php`,
  `src/Maestros/Infrastructure/Persistence/EloquentBuscadorDeContactos.php`,
  `src/BackOffice/Presentation/Views/contactos.blade.php`
- Test: `tests/Feature/BackOffice/MarcaDeAusenciaTest.php`, que hoy prueba
  «No vino en la última importación» con `vista_en_importacion_el` y se
  reescribe sobre la baja, y `tests/Feature/BackOffice/PantallaDeContactosTest.php`
  para el conflicto y el estado

**Interfaces:**
- Consumes: la Tarea 1 y la definición de visible de la Tarea 2.
- Produces: `ContactoDeBackOffice` cambia `ausenteEnUltimaImportacion` por
  `dadoDeBajaEnSap: bool` y suma `celularEnConflicto: bool` y
  `estado: string`, uno de `visible`, `inactivo` o `dado-de-baja`.

- [ ] **Step 1: Tests en rojo**

- Un contacto habilitado cuyo socio está dado de baja muestra «Dado de baja
  en SAP».
- Uno no habilitado en la misma situación no lo muestra.
- Dos contactos visibles con el mismo celular muestran «Celular en
  conflicto» los dos.
- Si uno de ellos se da de baja, ninguno muestra el aviso.
- La lista incluye a los inactivos y dados de baja, con su estado.
- El texto «No vino en la última importación» ya no aparece en ninguna fila.

- [ ] **Step 2: Correr y ver el rojo**

Run: el archivo de test del back-office.
Expected: FAIL.

- [ ] **Step 3: Implementar**

- **`aFila`:**
  - `estado` sale de `activo`, de la baja y del socio;
  - `dadoDeBajaEnSap = $habilitada && $estado !== 'visible'`;
  - `celularEnConflicto` viene de una subconsulta que cuenta contactos
    visibles con el mismo celular.
- **Lo que se borra:** `ultimaImportacion()`.
- **La vista:** dos marcas junto al nombre.

- [ ] **Step 4: Verde**

Run: el archivo de test, después `composer test`, `composer stan` y
`composer lint -- --test`.
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Maestros/Application/Contactos src/Maestros/Infrastructure/Persistence/EloquentBuscadorDeContactos.php src/BackOffice tests/Feature/BackOffice
git commit -m "feat(backoffice): contactos dados de baja en SAP y celulares en conflicto"
```

---

### Task 5: Los casos de uso de ingesta

**Files:**
- Create in `src/Maestros/Application/Ingesta/`:
  - `SocioIngresado.php`, `ContactoIngresado.php`, `GrupoIngresado.php`
  - `OperacionDeIngesta.php` (enum `Crear | Reemplazar`)
  - `ResultadoDeIngesta.php` (enum
    `Aplicado | IgnoradoPorViejo | DadoDeBaja | NoConservado`)
  - `IngestaErrors.php`
  - `ReplicarSocio/ReplicarSocio.php` y `ReplicarSocio/ReplicarSocioHandler.php`
  - `DarDeBajaSocio/DarDeBajaSocio.php` y `DarDeBajaSocio/DarDeBajaSocioHandler.php`
- Modify: `app/Providers/CoreServiceProvider.php` (registro de los dos
  handlers)
- Test: `tests/Feature/Maestros/ReplicarSocioTest.php`,
  `tests/Feature/Maestros/DarDeBajaSocioTest.php`

**Interfaces:**
- Consumes: Tareas 1 y 2.
- Produces:
  - `GrupoIngresado(string $codigo, string $nombre, ?string $segmento)`
  - `ContactoIngresado(string $codigo, string $nombre, ?string $celular, bool $activo)`
  - `SocioIngresado(string $cardCode, string $razonSocial, string $tipoSap, bool $activo, ?GrupoIngresado $grupo, list<ContactoIngresado> $contactos, DateTimeImmutable $vigenteDesde, string $origenEsquema, int $origenEventoId)`
  - `ReplicarSocio(OperacionDeIngesta $operacion, ?string $cardCodeDeLaRuta, SocioIngresado $socio, DateTimeImmutable $momento)`,
    con `Request` y `RequiereTransaccion` →
    `ResultWithValue<ResultadoDeIngesta>`
  - `DarDeBajaSocio(CodigoDeSocio $socio, DateTimeImmutable $momento)`, con
    `Request` y `RequiereTransaccion` → `Result`
  - `IngestaErrors`:
    - `socioYaExiste(string $cardCode)`: conflict, `SOCIO_YA_EXISTE`;
    - `cardCodeNoCoincide()`: validation, `CARDCODE_NO_COINCIDE`;
    - el 404 reusa `SocioErrors::noEncontrado()`.

- [ ] **Step 1: Tests en rojo**

`ReplicarSocioTest`, que despacha por el mediador sobre la base. Cuerpo base:
- `C-900001` «Agro Prueba SRL», `C`, activo;
- grupo `GRP-900` «Grupo Prueba» con segmento `Agroindustrial`;
- contactos `1523` «Mónica Salvatierra» `70741828` y `1524` «Rodrigo Téllez»
  `70112233`;
- `vigenteDesde 2026-10-05T12:00:00Z` y origen `AGRO_P6`/`1842`.

| Test | Aserción |
|---|---|
| `crear guarda grupo, socio y contactos` | `Aplicado`; grupo con segmento; socio visible en `GRP-900`; `p-1523` con `+59170741828` |
| `crear un socio que existe responde SOCIO_YA_EXISTE` | Ídem con un socio inactivo existente |
| `crear un socio dado de baja lo reactiva` | `dadoDeBajaEl()` null y datos nuevos |
| `reemplazar uno inexistente o dado de baja responde SOCIO_NO_ENCONTRADO` | 2 casos |
| `reemplazar con otro cardCode responde CARDCODE_NO_COINCIDE y no escribe nada` | El grupo `GRP-900` **no existe** después |
| `un proveedor al crear no se guarda` | `tipoSap S` → `NoConservado`; el socio no existe |
| `un socio que pasa a proveedor se da de baja` | `PUT` con `S` sobre uno existente → `DadoDeBaja`, socio y contactos con baja |
| `un cuerpo viejo se ignora entero` | Vigencia guardada 12:00, cuerpo 11:59 con otro nombre de grupo → `IgnoradoPorViejo`; nada cambió, **ni el grupo** |
| `un cuerpo del mismo segundo se ignora` | Guardado `12:00:00.900`, cuerpo `12:00:00.500` → `IgnoradoPorViejo` (Review Focus 1) |
| `el grupo conserva su vigencia propia` | El grupo actualizado a las 12:05 por otro socio; un cuerpo de las 12:01 de este socio con nombre de grupo viejo actualiza el socio pero **no** el nombre del grupo |
| `un socio sin grupo se guarda sin grupo` | `grupo: null` → `grupo() === null` |
| `los contactos que no vienen se dan de baja` | Reemplazar sin `1524` → `p-1524` con baja; `p-1523` no |
| `la habilitacion se conserva` | `p-1523` con `habilitada_el` previa → sigue igual tras reemplazar |
| `celulares de SAP` | `+591 70741828` y `591-7074-1828` → `+59170741828`; `33456789` → `celular() === null` y el socio igual se guarda (Review Focus 2) |
| `un contacto que estaba en otro socio pasa a este` | `p-1523` existente bajo `C-004871` → queda bajo `C-900001` |

`DarDeBajaSocioTest`:
- da de baja al socio y a sus contactos, con `vigente_desde` = el momento;
- un socio inexistente o ya dado de baja → `SOCIO_NO_ENCONTRADO`;
- **después de la baja**, un `ReplicarSocio` crear con vigencia anterior al
  momento → `IgnoradoPorViejo` y sigue dado de baja; con vigencia posterior
  → `Aplicado` y reactivado.

- [ ] **Step 2: Correr y ver el rojo**

Run: `vendor/bin/pest tests/Feature/Maestros/ReplicarSocioTest.php tests/Feature/Maestros/DarDeBajaSocioTest.php`
Expected: FAIL (clases inexistentes).

- [ ] **Step 3: Implementar los handlers**

- Los pasos y su orden están en la sección 3 del spec. **Ninguna escritura
  antes de los pasos 1 a 4.**
- **Vigencias:** se comparan en UTC truncadas al segundo, y empatar es
  ignorar. La truncación vive en un método privado del handler, el único
  que compara.
- **Celular:** `Celular::desdeLocalBoliviano` dentro de un `try`; el
  `DomainException` da `null`.
- **Ids de persona:** salen de `IdDePersona::deContactoSap()`.
- **`habilitada_el`:** se toma del contacto existente (`find`) o es `null`.

- [ ] **Step 4: Verde**

Run: los tests del Step 2, después `composer test`, `composer stan` y
`composer lint -- --test`.
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Maestros/Application/Ingesta app/Providers/CoreServiceProvider.php tests/Feature/Maestros
git commit -m "feat(maestros): replicar y dar de baja socios desde la ingesta"
```

---

### Task 6: Secreto del gateway, token de Entra e idempotencia

**Files:**
- Create: `config/ingesta.php`:
  - `gateway.secreto` ← `GATEWAY_SECRETO`;
  - `gateway.en_v1` ← `GATEWAY_SECRETO_EN_V1` (false);
  - `entra.tenant` ← `INGESTA_ENTRA_TENANT_ID`;
  - `entra.audiencia` ← `INGESTA_ENTRA_AUDIENCIA`;
  - `entra.rol` ← `INGESTA_ROL` (`Ingesta.Maestros.Escribir`);
  - `entra.timeout` ← `INGESTA_ENTRA_TIMEOUT` (10).
- Create: `app/Http/Middleware/ExigirSecretoDelGateway.php`,
  `app/Http/Middleware/AutenticarIngesta.php`,
  `app/Http/Middleware/Idempotencia.php`
- Create: `app/Ingesta/VerificadorEntra.php`,
  `app/Persistence/RespuestasDeIngesta.php`
- Create: `database/migrations/maestros/2026_10_05_000200_crear_respuestas_de_ingesta.php`
- Modify: `app/Http/MapaDeErroresHttp.php` (`CARDCODE_NO_COINCIDE` → 422,
  `ENTRA_NO_DISPONIBLE` → 503), `bootstrap/app.php` (alias),
  `.env.example` (las variables nuevas, vacías)
- Create: `tests/Soporte/TokenDeEntra.php`
- Test: `tests/Feature/Ingesta/SeguridadDeIngestaTest.php`,
  `tests/Feature/Ingesta/IdempotenciaTest.php`,
  `tests/Feature/Ingesta/SecretoEnV1Test.php`

**Interfaces:**
- Consumes: el envelope y el mapa de errores existentes.
- Produces:
  - **Aliases de middleware:**
    - `gateway` → `ExigirSecretoDelGateway`, con parámetro de ámbito
      (`gateway:ingesta` o `gateway:v1`);
    - `ingesta.token` → `AutenticarIngesta`;
    - `ingesta.idempotencia` → `Idempotencia`.
  - `VerificadorEntra::verificar(string $jwt): Result`. Éxito, o
    `TOKEN_INVALIDO`, `ENTRA_NO_DISPONIBLE` o `CONFIGURACION_INCOMPLETA`.
    Ante `TOKEN_INVALIDO`, deja en el log `motivo`: `firma`, `iss`, `aud`,
    `rol`, `vencido` o `formato`.
  - `RespuestasDeIngesta::buscar(string $clave, string $metodo, string $ruta): ?array{status:int, cuerpo:?string}`
    y `guardar(string $clave, string $metodo, string $ruta, int $status, ?string $cuerpo): void`,
    con `insertOrIgnore`.
  - `TokenDeEntra` (soporte de tests):
    - `jwks(): array`;
    - `firmar(array $claims, string $kid = 'clave-1'): string`;
    - `valido(array $sobrescribir = []): string`, con `iss`, `aud`,
      `roles: ['Ingesta.Maestros.Escribir']`, `exp` y `nbf` por defecto
      para el tenant `tenant-de-prueba` y la audiencia `api-de-prueba`;
    - `configurar(): void`: fija `config('ingesta.*')` y hace
      `Http::fake` del JWKS.

- [ ] **Step 1: Tests en rojo**

Sobre una ruta de prueba registrada en el test
(`POST /ingesta/v1/__prueba`, con los tres middlewares, que responde 201
`{cardCode: 'X'}`):

`SeguridadDeIngestaTest`:
- **Sin `X-Gateway-Secret`, o con otro valor** → 403 `ACCESO_DENEGADO`,
  aunque el token sea válido.
- **Con `GATEWAY_SECRETO` vacío** → 403, aunque la cabecera venga vacía.
- **Token:** sin `Authorization`, con firma de otra clave, con
  `iss = https://sts.windows.net/tenant-de-prueba/` (Review Focus 5), con
  otra `aud`, sin el rol, o vencido hace 2 minutos → 401 `TOKEN_INVALIDO`.
  Cada caso deja su `motivo` en el log (`Log::spy`).
- **Tolerancia:** vencido hace 30 segundos → pasa.
- **`kid` desconocido:** dispara **un** refetch del JWKS, que ya trae la
  clave nueva → pasa. Un segundo `kid` desconocido en menos de 5 minutos
  no vuelve a pedir el JWKS (`Http::assertSentCount`).
- **JWKS caído** (`Http::fake` con 500) → 503 `ENTRA_NO_DISPONIBLE`.
- **Sin `entra.tenant`** → 500 `CONFIGURACION_INCOMPLETA`.

`IdempotenciaTest`:
- **Clave:** sin `Idempotency-Key`, o con una que no son 32 hex → 400 con un
  `FieldError` en `Idempotency-Key`.
- **Repetición:** la misma terna dos veces → el segundo pedido devuelve el
  mismo status y cuerpo **sin** volver a ejecutar el controlador (un
  contador en la ruta de prueba sigue en 1).
- **Terna distinta:** la misma clave con otro método o ruta se ejecuta.
- **5xx:** si la ruta responde 500 la primera vez, la repetición vuelve a
  ejecutarla.
- **Carrera:** `guardar` dos veces la misma terna no lanza (Review Focus 4).

`SecretoEnV1Test`:
- con `gateway.en_v1 = false`, `GET /v1/version` sin cabecera → 200;
- con `true` y sin cabecera → 403;
- con `true` y la cabecera correcta → 200.

- [ ] **Step 2: Correr y ver el rojo**

Run: `vendor/bin/pest tests/Feature/Ingesta`
Expected: FAIL.

- [ ] **Step 3: Implementar**

- **`ExigirSecretoDelGateway`:**
  - `hash_equals`;
  - el ámbito `v1` consulta `gateway.en_v1` **en cada pedido**;
  - un secreto vacío rechaza, y deja un `Log::error` una vez por pedido.
- **El ámbito `v1`** se agrega al grupo `v1` de las rutas de
  `src/Identidad/Presentation/Http/routes.php` y
  `src/Maestros/Presentation/Http/routes.php`.
- **`VerificadorEntra`:**
  - `JWK::parseKeySet` sobre el JWKS cacheado con `Cache::remember` 60
    minutos;
  - la marca del último refetch va en caché, para el límite de 5 minutos;
  - `JWT::$leeway = 60`;
  - el `aud` se acepta como string o como lista;
  - `roles` se exige como lista.
- **`Idempotencia`:**
  - la ruta es `$pedido->getPathInfo()`;
  - guarda status y `getContent()` si `status < 500`;
  - en la repetición responde `new Response($cuerpo, $status, ['Content-Type' => 'application/json'])`.
- **La tabla `ingesta_respuestas`:** en SQL Server, `maestros.ingesta_respuestas`;
  en SQLite, `maestros_ingesta_respuestas`.

- [ ] **Step 4: Verde**

Run: `vendor/bin/pest tests/Feature/Ingesta`, después `composer test`,
`composer stan` y `composer lint -- --test`.
Expected: PASS.

- [ ] **Step 5: La tabla nueva contra el SQL Server local**

Reconstruir la imagen y correr `migrate --force` contra el SQL Server local.
Expected: `DONE`. Además, con `sqlcmd`, dos `INSERT` con la misma terna:
- el segundo falla por clave primaria;
- `cuerpo` acepta un JSON de más de 4000 caracteres.

- [ ] **Step 6: Commit**

```bash
git add config/ingesta.php app bootstrap/app.php database/migrations/maestros .env.example src/Identidad/Presentation/Http/routes.php src/Maestros/Presentation/Http/routes.php tests/Soporte/TokenDeEntra.php tests/Feature/Ingesta
git commit -m "feat(ingesta): secreto del gateway, token de Entra e idempotencia"
```

---

### Task 7: Las rutas de ingesta, el contrato y `CLAUDE.md`

**Files:**
- Create: `contrato/ingesta-api-v1.yaml`
- Create: `src/Maestros/Presentation/Http/ingesta.php`,
  `src/Maestros/Presentation/Http/IngestaController.php`,
  `app/Http/Middleware/ForzarJson.php`
- Modify: `app/Providers/ModulosServiceProvider.php` (`loadRoutesFrom` del
  archivo nuevo), `bootstrap/app.php` (alias `json` → `ForzarJson`),
  `tests/Soporte/Contrato.php` (el archivo como parámetro), `CLAUDE.md`
- Create: `tests/Soporte/Ingesta.php` (cabeceras completas y cuerpo base)
- Test: `tests/Feature/Ingesta/ContratoDeIngestaTest.php`,
  `tests/Feature/Ingesta/RutasDeIngestaTest.php`

**Interfaces:**
- Consumes: Tarea 5 (casos de uso y `ResultadoDeIngesta`), Tarea 6
  (middlewares y `TokenDeEntra`).
- Produces:
  - `Contrato::diferencias(TestResponse $r, string $metodo, string $ruta, string $archivo = 'agropartners-api-v1.yaml'): list<string>`,
    con un documento y un id de validador por archivo.
  - **Rutas:** `POST ingesta/v1/socios` → `IngestaController@crear`;
    `PUT ingesta/v1/socios/{cardCode}` → `@reemplazar`;
    `DELETE ingesta/v1/socios/{cardCode}` → `@eliminar`. Middlewares
    `json`, `gateway:ingesta`, `ingesta.token` e `ingesta.idempotencia`, en
    ese orden.

- [ ] **Step 1: La copia enmendada del contrato**

`contrato/ingesta-api-v1.yaml`: el archivo del 1 de octubre con estos
cambios.

- **Versión:** `openapi: 3.1.0`. Todo `nullable: true` pasa a
  `type: [<tipo>, 'null']`. Es el dialecto que valida
  `tests/Soporte/Contrato.php`, igual que el contrato de la App.
- **Rol:** `Ingesta.Maestros.Escribir` en `info.description` y en
  `securitySchemes.entraId`. La descripción dice que lo validan el gateway
  **y** `ms-maestros`.
- **Grupo:**
  `GrupoEconomicoIngesta: { type: object, required: [codigo, nombre], properties: { codigo: { type: string, maxLength: 50 }, nombre: { type: string }, segmento: { type: [string, 'null'] } } }`.
  `SocioIngesta.grupoEconomico` pasa a ser `oneOf: [{$ref: GrupoEconomicoIngesta}, {type: 'null'}]`
  y queda fuera de `required`.
- **Error:** `Error` se reemplaza por `RespuestaError`, `ErrorApi` y
  `DetalleCampo` copiados del contrato de la App.
- **Status:** se agregan `401` (`TOKEN_INVALIDO`), `403` (`ACCESO_DENEGADO`)
  y `503` (`ENTRA_NO_DISPONIBLE`) a las tres operaciones, como respuestas
  `RespuestaError`.

- [ ] **Step 2: Tests en rojo**

`ContratoDeIngestaTest`:
- cada operación de `contrato/ingesta-api-v1.yaml` existe como ruta en
  `ingesta/v1`;
- ninguna ruta `ingesta/*` falta en el contrato.

`RutasDeIngestaTest`, con `TokenDeEntra::configurar()`, la semilla y
`Ingesta::cabeceras()`. Cada respuesta pasa por
`Contrato::diferencias(..., 'ingesta-api-v1.yaml') === []`:

| Pedido | Status |
|---|---|
| `POST /ingesta/v1/socios` con el cuerpo base | 201 `data.cardCode = C-900001` |
| El mismo `POST` con otra clave | 409 `SOCIO_YA_EXISTE` |
| `PUT /ingesta/v1/socios/C-900001` con `vigenteDesde` posterior | 200 |
| `PUT /ingesta/v1/socios/C-999999` | 404 `SOCIO_NO_ENCONTRADO` |
| `PUT /ingesta/v1/socios/C-900001` con `cardCode: C-900002` en el cuerpo | 422 `CARDCODE_NO_COINCIDE` |
| `POST` sin `razonSocial` | 400, `FieldError` en `razonSocial` |
| `POST` con un contacto de `nombre: '   '` | 400, `FieldError` en `contactos.0.nombre` (Review Focus 3) |
| `POST` con dos contactos `1523` | 400, `FieldError` en `contactos.1.codigoDeContacto` |
| `POST` con `codigoDeContacto: '15a3'` | 400 |
| `POST` sin `Accept: application/json` y sin `razonSocial` | 400 en JSON, no 302 |
| `DELETE /ingesta/v1/socios/C-900001` | 204 sin cuerpo |
| El mismo `DELETE` con otra clave | 404 |
| `POST` sin `X-Gateway-Secret` | 403 |
| `POST` sin token | 401 |
| `POST` → 409 y después `PUT` con **la misma clave** | 200 (la terna distingue) |

Además, el log tiene una línea `ingesta` con `cardCode`, `operacion`,
`resultado` y `eventoId` para el `POST` base (`Log::spy`).

- [ ] **Step 3: Correr y ver el rojo**

Run: `vendor/bin/pest tests/Feature/Ingesta/ContratoDeIngestaTest.php tests/Feature/Ingesta/RutasDeIngestaTest.php`
Expected: FAIL (rutas inexistentes).

- [ ] **Step 4: Implementar**

- **`ForzarJson`:** fija `Accept: application/json` para que la validación
  responda el envelope 400.
- **`IngestaController`:**
  - valida con reglas de Laravel. `nombre` y `razonSocial` usan `required`
    después de `trim`, y los largos salen del contrato;
  - `codigoDeContacto` exige `regex:/^\d{1,38}$/` y `distinct`;
  - `vigenteDesde` es `date`, y `origen.esquema` y `origen.eventoId` son
    obligatorios;
  - arma el `SocioIngresado`, despacha con `momento = now`, y responde 201,
    200 o `response()->noContent()`;
  - escribe la línea de log `ingesta`.
- **`IngestaController@eliminar`:** usa `CodigoDeSocio::desde()`. Un código
  inválido se responde 404, igual que un socio inexistente.

- [ ] **Step 5: Verde**

Run: los tests del Step 3 y `vendor/bin/pest tests/Feature/ContratoDeRespuestasTest.php`,
que tiene que seguir verde con el soporte generalizado. Después
`composer test`, `composer stan` y `composer lint -- --test`.
Expected: PASS.

- [ ] **Step 6: `CLAUDE.md`**

Un punto en «Contratos que no se negocian» con:
- la ingesta como fuente en Azure, y el importador solo para local;
- `p-{CntctCode}`;
- que la ingesta nunca escribe `habilitada_el`;
- las tres definiciones de visibilidad;
- que un socio sin grupo se ve solo a sí mismo;
- el orden de la seguridad;
- la idempotencia por terna;
- que el contrato de ingesta es una copia enmendada en 3.1;
- que la migración va antes del push.

En «Pendientes conocidos»:
- la parte B, la habilitación en el back-office;
- borrar `vista_en_importacion_el`;
- la semilla en Azure;
- prender `GATEWAY_SECRETO_EN_V1` cuando el APIM mande la cabecera.

- [ ] **Step 7: Commit**

```bash
git add contrato/ingesta-api-v1.yaml src/Maestros/Presentation/Http app/Http/Middleware/ForzarJson.php app/Providers/ModulosServiceProvider.php bootstrap/app.php tests/Soporte tests/Feature/Ingesta CLAUDE.md
git commit -m "feat(ingesta): POST, PUT y DELETE /ingesta/v1/socios contra el contrato"
```
