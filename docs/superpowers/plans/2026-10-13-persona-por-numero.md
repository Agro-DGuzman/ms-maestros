# La persona es el número · Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que se habilite un número de celular, y que ese número entre a la App
y vea los socios donde es contacto, siempre que todos sean de un mismo grupo
económico.

**Architecture:**
- Una regla de dominio pura, `AlcanceDeNumero`, decide si un número entra y qué
  socios ve, a partir de los vínculos (socio, grupo) de sus contactos visibles.
  Todo lo demás la consulta: el contexto, el alcance, la habilitación, el
  back-office y la conciliación.
- La identidad (Keycloak, bóveda, sesiones, token) pasa a usar
  `IdDePersona::deCelular()`.
- Los ids por contacto (`p-…`) siguen siendo la clave de la réplica, pero
  dejan de identificar personas.

**Tech Stack:** Laravel 13 / PHP 8.4, Pest, Larastan 9, SQLite en los tests y
SQL Server en Azure.

**Spec:** `docs/superpowers/specs/2026-10-13-persona-por-numero-design.md`

## Global Constraints

- `declare(strict_types=1);` en todo archivo de `src/`. Comentarios, mensajes y
  commits en español; los comentarios explican por qué.
- `composer test`, `composer stan` y `composer lint -- --test` en verde antes
  de cada commit.
- Primero el test que falla, y verlo en rojo. Los dobles compartidos van en
  `tests/Dobles/`. Cada archivo de prueba tiene que poder correr solo.
- El id de persona de un número es `n-` seguido del E.164 sin el `+`: el
  `70741828` es `n-59170741828`.
- Todo fallo de ingreso responde `CODIGO_INVALIDO`. No habilitado, en varios
  grupos, solo en socios sin grupo y desconocido son indistinguibles.
- Fuera de alcance es 403 `ACCESO_DENEGADO`, verificado antes que la existencia.
- La forma de `/mi-cuenta` y de `/socios` no cambia: `nombre`, `iniciales`,
  `celular` y `grupoEconomico { id, nombre, socios[] }`.
- La ingesta no cambia y nunca escribe nada de habilitación.
- **Lo que SQLite no ve:** toda consulta nueva o cambiada se corre una vez
  contra el SQL Server de docker antes del commit de su tarea. El patrón es el
  del paso «Verificar en SQL Server» de la tarea 2.

## Review Focus

1. **Un token con id por contacto, después del despliegue y antes de volver a
   entrar.** No ve nada: 403 en los socios y `CONTACTO_NO_ENCONTRADO` en
   `/mi-cuenta`. *(Tarea 4)*
2. **Un número que pasa a estar en dos grupos con la sesión abierta.** La
   renovación responde `REFRESH_TOKEN_INVALIDO`. *(Tarea 4)*
3. **Un socio cuyo código de grupo no tiene fila en `grupos`** (`GRP-099` en la
   semilla). Cuenta como ese grupo, y el nombre y las iniciales salen vacíos.
   *(Tarea 4)*
4. **La fila de un contacto dado de baja cuyo número sigue entrando por otro
   contacto.** Ofrece «Dar acceso», y dar acceso funciona. *(Tarea 3)*
5. **Un número habilitado en dos grupos y uno sin credencial.** El trabajo
   encolado no les manda código, y el login responde `CODIGO_INVALIDO`, no
   `IDENTIDAD_NO_DISPONIBLE`. *(Tarea 4)*

---

### Task 1: El vocabulario: el id de un número y el alcance de un número

**Files:**
- Modify: `src/Maestros/Domain/Contactos/IdDePersona.php`
- Create: `src/Maestros/Domain/Contactos/MotivoDeNoIngreso.php`
- Create: `src/Maestros/Domain/Contactos/AlcanceDeNumero.php`
- Modify: `src/Maestros/Domain/Contactos/ContactoErrors.php`
- Test: `tests/Unit/Maestros/IdDePersonaTest.php`, `tests/Unit/Maestros/AlcanceDeNumeroTest.php` (nuevo)

**Interfaces:**
- Produces:
  - `IdDePersona::deCelular(Celular $celular): self` y
    `IdDePersona::celular(): ?Celular`, que es `null` salvo para ids
    `/^n-591[67]\d{7}$/`.
  - `enum MotivoDeNoIngreso: string` con los casos `SinContactos =
    'sin-contactos'`, `SoloSinGrupo = 'solo-sin-grupo'` y `VariosGrupos =
    'varios-grupos'`.
  - `AlcanceDeNumero::desde(array $vinculos): self`, donde
    `$vinculos` es `list<array{socio: CodigoDeSocio, grupo: ?IdDeGrupo}>`.
  - Los métodos de `AlcanceDeNumero`: `entra(): bool`,
    `motivo(): ?MotivoDeNoIngreso`, `grupo(): ?IdDeGrupo`,
    `socios(): list<CodigoDeSocio>` (sin repetir, en el orden recibido) y
    `alcanza(CodigoDeSocio $socio): bool`.
  - `ContactoErrors::numeroNoIngresa(MotivoDeNoIngreso $motivo): Error`.

- [ ] **Step 1: Escribir los tests que fallan**

```php
it('el id de un numero es n- y el E.164 sin el mas', function () {
    $id = IdDePersona::deCelular(Celular::desdeLocalBoliviano('70741828'));
    expect($id->value())->toBe('n-59170741828')
        ->and($id->celular()?->e164())->toBe('+59170741828');
});

it('un id por contacto no es un numero', function () {
    expect(IdDePersona::desde('p-8f2b1c40')->celular())->toBeNull()
        ->and(IdDePersona::desde('n-123')->celular())->toBeNull();
});
```

`tests/Unit/Maestros/AlcanceDeNumeroTest.php`. Usá una función local
`v(string $socio, ?string $grupo)` que arme el vínculo:
- **`sin vinculos no entra`**: `motivo()` es `SinContactos` y `socios()` es `[]`.
- **`solo con socios sin grupo no entra`**: `[v('C-1', null)]` da `SoloSinGrupo`.
- **`con socios de dos grupos no entra`**: `[v('C-1','G1'), v('C-2','G2')]` da
  `VariosGrupos`.
- **`un grupo con varios socios entra e ignora los sin grupo`**:
  `[v('C-1','G1'), v('C-3', null), v('C-2','G1'), v('C-1','G1')]` da
  `entra()` true, `grupo()` `G1` y `socios()` `['C-1','C-2']`. Además,
  `alcanza(C-2)` es true y `alcanza(C-3)` es false.
- **`los codigos de error dicen por que no entra`**: `numeroNoIngresa(...)->code`
  da `NUMERO_SIN_CONTACTOS`, `NUMERO_SOLO_SIN_GRUPO` y
  `NUMERO_EN_VARIOS_GRUPOS`, los tres de tipo `ErrorType::Validation`.

- [ ] **Step 2: Verificar que fallan**

Run: `vendor/bin/pest tests/Unit/Maestros/IdDePersonaTest.php tests/Unit/Maestros/AlcanceDeNumeroTest.php`
Expected: FAIL. `deCelular`, `AlcanceDeNumero` y `numeroNoIngresa` no existen.

- [ ] **Step 3: Implementar**

Los mensajes de `numeroNoIngresa`:

| Motivo | Mensaje |
|---|---|
| `SinContactos` | «Ningún contacto activo en SAP tiene este número.» |
| `SoloSinGrupo` | «El número solo es contacto de socios sin grupo económico: no puede ingresar.» |
| `VariosGrupos` | «El número es contacto en socios de más de un grupo económico: hay que corregirlo en SAP.» |

- [ ] **Step 4: Verificar que pasan, y la batería entera**

Run: `vendor/bin/pest tests/Unit/Maestros/IdDePersonaTest.php tests/Unit/Maestros/AlcanceDeNumeroTest.php && composer test`
Expected: PASS.

- [ ] **Step 5: Commit**: `feat(maestros): el id de un numero y el alcance de un numero`

---

### Task 2: El alcance de un número, leído de la réplica

**Files:**
- Modify: `src/Maestros/Domain/Contactos/ContactoRepository.php`
- Modify: `src/Maestros/Infrastructure/Persistence/EloquentContactoRepository.php`
- Modify: `tests/Dobles/ContactosConLecturaVieja.php` (delega el método nuevo)
- Create: `src/Maestros/Application/Contactos/EvaluarNumero/EvaluarNumero.php` y `EvaluarNumeroHandler.php`, registrados en `app/Providers/CoreServiceProvider.php`
- Modify: `src/Identidad/Application/Contracts/DirectorioDeContactos.php`, `src/Identidad/Infrastructure/Maestros/DirectorioDeContactosEnProceso.php`
- Modify: `tests/Feature/Identidad/SolicitarDesafioTest.php`. Su doble del puerto está declarado ahí: implementá el método nuevo y, de paso, mudá el doble a `tests/Dobles/`.
- Create: `src/Maestros/Infrastructure/Alcance/ResolutorPorNumero.php` (todavía sin enlazar)
- Modify: `tests/Soporte/ReplicaDeEjemplo.php`
- Test: `tests/Feature/Maestros/AlcanceDeNumeroTest.php` (nuevo)

**Interfaces:**
- Consumes: `AlcanceDeNumero` y `MotivoDeNoIngreso`, de la tarea 1.
- Produces:
  - `ContactoRepository::alcanceDeNumero(Celular $celular): AlcanceDeNumero`:
    los vínculos (socio, grupo) de los contactos visibles con ese celular.
  - `EvaluarNumero(Celular $celular)`: su handler devuelve
    `ResultWithValue<AlcanceDeNumero>`.
  - `DirectorioDeContactos::motivoDeNoIngreso(Celular $celular): ?MotivoDeNoIngreso`:
    `null` si entra.
  - `ResolutorPorNumero implements ResolutorDeAlcance`.
  - `ReplicaDeEjemplo::otroContactoCon(string $celular, string $id = 'p-9999', bool $dadoDeBaja = false, string $cardCode = 'C-005210')`
    y `ReplicaDeEjemplo::MONICA = 'n-59170741828'`.

- [ ] **Step 1: Escribir los tests que fallan**

Van en `tests/Feature/Maestros/AlcanceDeNumeroTest.php`, con la semilla
importada. Mónica es el contacto `p-8f2b1c40`, celular `+59170741828`, socio
`C-004871`, grupo `GRP-014`. `C-004872` es del mismo grupo y `C-005210` es del
`GRP-021`.
- **`el numero de Monica entra por su socio y nada mas`**: `entra()` true,
  `grupo()` `GRP-014` y `socios()` `['C-004871']`.
- **`el mismo numero en otro socio del grupo suma ese socio`**: con
  `otroContactoCon('+59170741828', 'p-9999', cardCode: 'C-004872')`, los socios
  son `['C-004871','C-004872']`.
- **`el mismo numero en un socio de otro grupo no entra`**: con
  `otroContactoCon('+59170741828')`, el motivo es `VariosGrupos`.
- **`un socio sin grupo no suma ni bloquea`**: el otro contacto en `C-004872`, y
  `sinGrupo('C-004872')`. Los socios son `['C-004871']`.
- **`si solo queda en socios sin grupo no entra`**: con `sinGrupo('C-004871')`,
  el motivo es `SoloSinGrupo`.
- **`un contacto dado de baja o un socio inactivo no cuentan`**: con
  `personaDadaDeBaja('p-8f2b1c40')`, el motivo es `SinContactos`. Lo mismo con
  `socioInactivo('C-004871')`.
- **`el resolutor alcanza solo los socios del numero`**: con `ResolutorPorNumero`,
  `alcanza(n-59170741828, C-004871)` es true y `alcanza(n-59170741828, C-004872)`
  es false. `alcanza(p-8f2b1c40, C-004871)` también es false: un id por
  contacto no alcanza nada.
- **`el directorio dice por que un numero no entra`**: en
  `DirectorioDeContactosEnProceso::motivoDeNoIngreso`, Mónica da `null` y con el
  otro contacto en `C-005210` da `VariosGrupos`.

- [ ] **Step 2: Verificar que fallan**

Run: `vendor/bin/pest tests/Feature/Maestros/AlcanceDeNumeroTest.php`
Expected: FAIL. `alcanceDeNumero`, `ResolutorPorNumero` y `motivoDeNoIngreso`
no existen.

- [ ] **Step 3: Implementar**

`alcanceDeNumero` usa los `visibles()` que ya existen y filtra por
`c.celular = $celular->e164()`. Selecciona `s.codigo_de_socio` y
`s.id_de_grupo` sin repetir, ordenados por `s.codigo_de_socio`, y arma el
alcance con `AlcanceDeNumero::desde()`. `ResolutorPorNumero::alcanza` resuelve
`$persona->celular()`: si es `null`, false; si no,
`alcanceDeNumero(...)->alcanza($socio)`.

- [ ] **Step 4: Verificar que pasan, y la batería entera**

Run: `vendor/bin/pest tests/Feature/Maestros/AlcanceDeNumeroTest.php && composer test && composer stan`
Expected: PASS y 0 errores.

- [ ] **Step 5: Verificar en SQL Server**

Escribí un script de descarte, fuera del repo, que:
- arranque la app;
- corra `app(ContactoRepository::class)->alcanceDeNumero(Celular::desdeLocalBoliviano('70741828'))` dentro de `DB::beginTransaction()` / `rollBack()`;
- imprima el host, `entra()` y `socios()`.

Corrélo en la imagen, primero reconstruyéndola y levantando la base:

```bash
docker compose build app && docker compose up -d sqlserver
```

```bash
MSYS_NO_PATHCONV=1 docker compose run --rm --no-deps -T -v "<carpeta del script>:/scratch" app php /scratch/<script>.php
```

Expected: el host es `sqlserver`, sin error de SQL, y el resultado coincide con
el de SQLite para la semilla local.

- [ ] **Step 6: Commit**: `feat(maestros): el alcance de un numero y el resolutor por numero`

---

### Task 3: Dar acceso es habilitar el número

**Files:**
- Modify: `src/Identidad/Application/Habilitacion/HabilitarPersona/HabilitarPersonaHandler.php`. Deja de depender de `ContactoRepository` y pasa a usar `DirectorioDeContactos`.
- Modify: `src/Identidad/Infrastructure/Habilitacion/HabilitarPersonaCommand.php`, `DeshabilitarPersonaCommand.php`. El argumento pasa a ser `{celular}`.
- Modify: `src/Maestros/Domain/Contactos/ContactoErrors.php`. Borrá `celularEnConflicto()`, y `noVisible()` si queda sin uso.
- Modify: `src/Maestros/Application/Contactos/ContactoDeBackOffice.php`, `src/Maestros/Infrastructure/Persistence/EloquentBuscadorDeContactos.php`
- Delete: `src/Maestros/Application/Contactos/FiltroDeEstado.php`. Sacá `estado` de `CriterioDeBusqueda`.
- Modify: `src/BackOffice/Application/Accesos/ListarContactos/ContactoConAcceso.php`, `ListarContactosHandler.php`
- Modify: `src/BackOffice/Presentation/Http/AccesosController.php`, `src/BackOffice/Presentation/Views/contactos.blade.php`
- Test: `tests/Feature/Identidad/HabilitarPersonaTest.php`, `DeshabilitarPersonaTest.php`, `tests/Feature/BackOffice/PantallaDeContactosTest.php`, `MarcaDeAusenciaTest.php`, `tests/Feature/Maestros/BusquedaDeContactosTest.php`, `tests/Unit/Maestros/CriterioDeBusquedaTest.php`

**Interfaces:**
- Consumes:
  - `DirectorioDeContactos::motivoDeNoIngreso()` y
    `ContactoRepository::alcanceDeNumero()`, de la tarea 2;
  - `IdDePersona::deCelular()/celular()` y
    `ContactoErrors::numeroNoIngresa()`, de la tarea 1.
- Produces:
  - En `ContactoDeBackOffice` se sacan `estaHabilitada`, `habilitadaEl`,
    `dadoDeBajaEnSap` y `celularEnConflicto`. Se agregan
    `?string $personaDelNumero` (el `n-…` del número, o `null` si el celular no
    es válido), `int $compartidoCon` (los *otros* contactos visibles con ese
    número) y `?AlcanceDeNumero $alcanceDelNumero`.
  - En `ContactoConAcceso`, `tieneCredencial` se calcula por
    `personaDelNumero`. Se agrega `puedeDarAcceso(): bool`, que es
    `personaDelNumero !== null && ! tieneCredencial && alcanceDelNumero?->entra() === true`.
  - Las rutas `admin.habilitar`, `admin.deshabilitar` y `admin.historial`
    reciben el `n-…`.

- [ ] **Step 1: Escribir los tests que fallan**

`HabilitarPersonaTest`, por el mediator con `DirectorioFalso`:
- **`habilitar un numero crea la credencial con el id del numero`**: después de
  `HabilitarPersona(IdDePersona::desde('n-59170741828'))`,
  `DirectorioFalso->usuarios` tiene `n-59170741828` y la bóveda lo puede leer.
- **`no habilita un numero en socios de dos grupos`**: con
  `otroContactoCon('+59170741828')`, el código es `NUMERO_EN_VARIOS_GRUPOS` y
  no se crea ninguna credencial.
- **`no habilita un numero que solo es contacto de socios sin grupo`**:
  `NUMERO_SOLO_SIN_GRUPO`.
- **`no habilita un numero sin contactos visibles`**: `NUMERO_SIN_CONTACTOS`.
- **`no habilita un id por contacto`**: `HabilitarPersona(p-8f2b1c40)` da
  `CELULAR_INVALIDO`.
- **`el comando recibe el celular`**: `artisan identidad:habilitar 70741828`
  termina con 0 y crea `n-59170741828`. Lo mismo para
  `identidad:deshabilitar 70741828`, en `DeshabilitarPersonaTest`.

`PantallaDeContactosTest`, con el autenticador de desarrollo:
- **`dar acceso en una fila habilita el numero y todas sus filas lo muestran`**:
  con `otroContactoCon('+59170741828', 'p-9999', cardCode: 'C-004872')`, un
  POST a `admin.habilitar` con `n-59170741828` hace que
  `GET /admin/contactos?q=70741828` muestre «Quitar acceso» dos veces.
- **`cada fila dice con cuantos comparte el numero`**: la misma preparación
  muestra «Comparte el número con 1 contacto(s) más».
- **`un numero en dos grupos se marca y no ofrece dar acceso`**: con
  `otroContactoCon('+59170741828')` y `?q=70741828`, se ve «Contacto en socios
  de varios grupos: no puede entrar» y no se ve «Dar acceso».
- **`la fila de un contacto dado de baja ofrece dar acceso si el numero sigue entrando`** *(Review Focus 4)*:
  con `otroContactoCon('+59170741828', 'p-9999', cardCode: 'C-004872')` y
  `personaDadaDeBaja('p-8f2b1c40')`, la fila de Mónica ofrece «Dar acceso», y
  dar acceso funciona.
- **`no quedan el filtro ni la columna de habilitadas en SAP`**: no se ven
  «Habilitadas en SAP» ni «En SAP».

- [ ] **Step 2: Verificar que fallan**

Run: `vendor/bin/pest tests/Feature/Identidad/HabilitarPersonaTest.php tests/Feature/BackOffice/PantallaDeContactosTest.php`
Expected: FAIL.

- [ ] **Step 3: Implementar**

`HabilitarPersonaHandler` sigue este orden:
1. `$persona->celular()`; si es `null`, `ContactoErrors::celularNoUtilizable()`.
2. `motivoDeNoIngreso()`; si devuelve un motivo, `numeroNoIngresa($motivo)`.
3. Después, el directorio y la bóveda, como hoy.

En el buscador:
- inyectá `ContactoRepository`;
- sacá `c.habilitada_el` del select y el `match` del filtro;
- para cada celular válido *distinto* de la página, llamá una vez a
  `alcanceDeNumero()`;
- `compartidoCon` es `compartido` menos 1 si la fila es visible.

En la vista, los textos exactos:
- «Comparte el número con {N} contacto(s) más»;
- «Contacto en socios de varios grupos: no puede entrar»;
- «Solo en socios sin grupo: no puede entrar».

Los botones salen de `puedeDarAcceso()` y de `tieneCredencial`, y el enlace al
historial usa `personaDelNumero`. Arreglá los demás tests que leían los campos
o el filtro borrados.

- [ ] **Step 4: Verificar que pasan, y la batería entera**

Run: `composer test && composer stan && composer lint -- --test`
Expected: PASS.

- [ ] **Step 5: Verificar en SQL Server** el buscador:
  `app(BuscadorDeContactos::class)->buscar(CriterioDeBusqueda::de('70741828'))`,
  con el patrón de la tarea 2.

- [ ] **Step 6: Commit**: `feat(identidad): dar acceso habilita el numero`

---

### Task 4: Entrar y ver es por número

**Files:**
- Modify: `src/Maestros/Application/Contactos/ObtenerContexto/ObtenerContextoHandler.php`
- Modify: `app/Providers/ModulosServiceProvider.php`. Enlazá `ResolutorPorNumero`.
- Delete: `src/Maestros/Infrastructure/Alcance/ResolutorPorGrupo.php`
- Modify: `src/Identidad/Application/Contracts/DirectorioDeContactos.php` (sacá `buscarPorCelular`) y su adaptador
- Delete: `src/Maestros/Application/Contactos/BuscarPorCelular/`, su registro en `CoreServiceProvider`, y `ContactoRepository::porCelular()` en el repositorio y en los dobles
- Modify: `src/Identidad/Infrastructure/Whatsapp/EnviarDesafioJob.php`, `src/Identidad/Application/Auth/IniciarSesion/IniciarSesionHandler.php`
- Test: `tests/Feature/Maestros/ObtenerContextoTest.php`, `MiCuentaTest.php`, `SociosTest.php`, `PropiedadesTest.php`, `tests/Feature/Identidad/IniciarSesionTest.php`, `RenovarYCerrarTest.php`, `EnviarDesafioJobTest.php`, `SolicitarDesafioTest.php`, `tests/Feature/ContratoDeRespuestasTest.php`, más los que se autentican como `p-8f2b1c40`

**Interfaces:**
- Consumes:
  - `ContactoRepository::alcanceDeNumero()`, `ResolutorPorNumero` y
    `ReplicaDeEjemplo::MONICA`, de la tarea 2;
  - `IdDePersona::deCelular()/celular()`, de la tarea 1.
- Produces:
  - `ObtenerContexto(IdDePersona)` responde por número.
  - `EnviarDesafioJob::handle(DirectorioDeContactos $directorio, BovedaDeContrasenas $boveda, EnviadorDeDesafio $enviador): void`.

- [ ] **Step 1: Escribir los tests que fallan**

`ObtenerContextoTest`:
- **`Monica ve solo su socio y el nombre de su grupo`**: con `MONICA`, da
  `nombre` «Grupo Monasterio», `iniciales` «GM», `grupoId` `GRP-014`, los
  socios `['C-004871']` y `celular` «+591 707 41 828».
- **`el mismo numero en otro socio del grupo suma ese socio`**: con el otro
  contacto en `C-004872`, los socios son `['C-004871','C-004872']`.
- **`un numero en dos grupos no tiene contexto`**: da `CONTACTO_NO_ENCONTRADO`.
- **`un grupo que no esta en la replica da el nombre vacio`** *(Review Focus 3)*:
  el socio `C-004871` pasa a `id_de_grupo = 'GRP-099'`. Da `nombre` '',
  `iniciales` '', `grupoId` `GRP-099` y los socios `['C-004871']`.
- **`un id por contacto no tiene contexto`** *(Review Focus 1)*: `p-8f2b1c40` da
  `CONTACTO_NO_ENCONTRADO`.

`PropiedadesTest` / `SociosTest`:
- **`Monica no alcanza un socio de su grupo donde no es contacto`**:
  `GET /v1/socios/C-004872/propiedades` como `MONICA` da 403 `ACCESO_DENEGADO`.
- **`un token con id por contacto no alcanza nada`** *(Review Focus 1)*:
  `VerificadorFalso('p-8f2b1c40')` en `C-004871` da 403.

`IniciarSesionTest`:
- **`entra con el numero habilitado y la sesion es del numero`**: la sesión
  guardada es de `n-59170741828`.
- **`un numero sin credencial responde codigo invalido`** *(Review Focus 5)*: da
  `CODIGO_INVALIDO`, no `IDENTIDAD_NO_DISPONIBLE`.
- **`un numero en dos grupos responde codigo invalido aunque tenga credencial`**.

`RenovarYCerrarTest`:
- **`si el numero pasa a estar en dos grupos no renueva`** *(Review Focus 2)*:
  da `REFRESH_TOKEN_INVALIDO`.

`EnviarDesafioJobTest` *(Review Focus 5)*:
- **`no envia a un numero sin credencial`**;
- **`no envia a un numero en dos grupos`**;
- **`envia a un numero habilitado que entra`**.

- [ ] **Step 2: Verificar que fallan**

Run: `vendor/bin/pest tests/Feature/Maestros/ObtenerContextoTest.php tests/Feature/Identidad/IniciarSesionTest.php tests/Feature/Identidad/EnviarDesafioJobTest.php`
Expected: FAIL.

- [ ] **Step 3: Implementar**

`ObtenerContextoHandler`:
1. Lee `$persona->celular()` y `alcanceDeNumero()`. Si no entra, responde
   `noEncontrado`.
2. El nombre del grupo sale de `GrupoRepository::find()`; si no está, es ''.
3. Las iniciales salen de `RazonSocial::desde($nombre)->iniciales()`, o '' si
   el nombre está vacío.
4. Los socios son `alcance->socios()`, leídos con `SocioRepository::find()`,
   con `cantidadPropiedades` como hoy.
5. `celular` es `$celular->paraMostrar()`.

`IniciarSesionHandler`:
1. Construye la persona con `IdDePersona::deCelular($desafio->celular())`.
2. Pide el contexto **antes** de emitir el token y abrir la sesión: si es
   `null`, `codigoInvalido`.
3. Si la bóveda no tiene la contraseña, también `codigoInvalido`.
4. Devuelve el contexto que ya pidió.

`EnviarDesafioJob` no envía si la bóveda no tiene credencial para el número o
si el contexto es `null`.

Reemplazá `'p-8f2b1c40'` por `ReplicaDeEjemplo::MONICA` en todo test que lo use
como persona autenticada. Ajustá las expectativas que suponían que Mónica ve su
grupo entero: con esta regla ve un socio, y su nombre es «Grupo Monasterio».

- [ ] **Step 4: Verificar que pasan, y la batería entera**

Run: `composer test && composer stan && composer lint -- --test`
Expected: PASS.

- [ ] **Step 5: Verificar en SQL Server** `ObtenerContexto(MONICA)` y
  `ResolutorPorNumero`, con el patrón de la tarea 2.

- [ ] **Step 6: Commit**: `feat(identidad): la persona es el numero al entrar y al ver sus socios`

---

### Task 5: La conciliación compara números contra el directorio

**Files:**
- Modify: `src/Identidad/Application/Contracts/DirectorioDeIdentidades.php`, `src/Identidad/Infrastructure/Keycloak/KeycloakAdmin.php`, `tests/Dobles/DirectorioFalso.php`
- Modify: `src/Identidad/Infrastructure/Habilitacion/ConciliarIdentidadesCommand.php`
- Delete: `DirectorioDeContactos::habilitadas()` y su adaptador, `src/Maestros/Application/Contactos/ListarHabilitadas/` con su registro, y `ContactoRepository::habilitadas()` en el repositorio y en los dobles
- Test: `tests/Feature/Identidad/ConciliarIdentidadesTest.php` (reescrito), `tests/Feature/Identidad/KeycloakAdminTest.php` (nuevo, con `Http::fake`)

**Interfaces:**
- Produces: `DirectorioDeIdentidades::activos(): ?array`. Es `list<IdDePersona>`
  con los usuarios habilitados cuyo username empieza con `n-` o `p-`, y `null`
  si el directorio no respondió.

- [ ] **Step 1: Escribir los tests que fallan**

`ConciliarIdentidadesTest`, con `DirectorioFalso` y la bóveda real:
- **`sin discrepancias cuando cada numero con credencial tiene usuario activo`**:
  sale con 0 y muestra «Sin discrepancias.».
- **`avisa una credencial cuyo usuario esta inactivo`**: «Credencial guardada
  pero el usuario no está activo en el directorio: n-59170741828».
- **`avisa un usuario activo sin credencial`**: «Usuario activo en el directorio
  sin credencial guardada: …».
- **`avisa una credencial con id por contacto`**: «Credencial con id por
  contacto, anterior al cambio a números: p-8f2b1c40».
- **`informa sin contarlo un numero habilitado que hoy no ingresa`**: con
  `otroContactoCon('+59170741828')`, muestra «Habilitado pero hoy no ingresa
  (varios-grupos): n-59170741828» y sale con 0 si no hay otra cosa.
- **`falla si el directorio no responde`**: con `activos()` en `null`, sale con
  1.

`KeycloakAdminTest`:
- **`activos lista solo los usernames de personas`**: con `Http::fake` del token
  de servicio y de `GET …/users`, que devuelve `n-59170741828`, `p-8f2b1c40` y
  `service-account-ms-maestros`, el resultado es los dos primeros.

- [ ] **Step 2: Verificar que fallan**

Run: `vendor/bin/pest tests/Feature/Identidad/ConciliarIdentidadesTest.php tests/Feature/Identidad/KeycloakAdminTest.php`
Expected: FAIL.

- [ ] **Step 3: Implementar**

`activos()` hace `GET admin/realms/{realm}/users` con `enabled=true`,
`briefRepresentation=true` y `max=1000`, que alcanza para el piloto. El
comando cuenta como discrepancias los tres primeros avisos, y el cuarto solo
lo informa.

- [ ] **Step 4: Verificar que pasan, y la batería entera**

Run: `composer test && composer stan && composer lint -- --test`
Expected: PASS.

- [ ] **Step 5: Commit**: `feat(identidad): conciliar numeros con credencial contra el directorio`

---

### Task 6: Migrar las personas existentes, el realm de desarrollo y la documentación

**Files:**
- Create: `app/Console/MigrarANumerosCommand.php` (`identidad:migrar-a-numeros`), registrado con los demás comandos en `ModulosServiceProvider`
- Modify: `docker/keycloak/agropartners-realm.json` y `docker/keycloak/README.md` (`p-8f2b1c40` pasa a `n-59170741828`), `tests/Integration/KeycloakTest.php`
- Modify: `CLAUDE.md`, `docs/superpowers/handoffs/2026-10-05-ingesta-de-socios.md`
- Test: `tests/Feature/Identidad/MigrarANumerosTest.php` (nuevo)

**Interfaces:**
- Consumes: `HabilitarPersona` y `DeshabilitarPersona` por el mediator,
  `ContactoRepository::find()` y `BovedaDeContrasenas::personas()`.

- [ ] **Step 1: Escribir los tests que fallan**

- **`migra una credencial por contacto al numero y desactiva la vieja`**: la
  bóveda tiene `p-8f2b1c40` y `DirectorioFalso` lo tiene activo. Después de
  correr el comando:
  - la bóveda tiene `n-59170741828` y no tiene `p-8f2b1c40`;
  - `activos['p-8f2b1c40']` es false y `activos['n-59170741828']` es true;
  - las sesiones de `p-8f2b1c40` quedan cerradas.
- **`una segunda corrida no hace nada`**: muestra «Migradas 0».
- **`un contacto sin celular valido se informa y no se toca`**.

- [ ] **Step 2: Verificar que fallan**

Run: `vendor/bin/pest tests/Feature/Identidad/MigrarANumerosTest.php`
Expected: FAIL. El comando no existe.

- [ ] **Step 3: Implementar**

El comando recorre las credenciales de la bóveda cuyo id no es un número. Para
cada una:
1. Busca el contacto y su celular.
2. Habilita el número.
3. Deshabilita el id viejo **aunque** habilitar haya fallado: después del
   despliegue, ese id ya no sirve para entrar.

Imprime una línea por persona y un resumen («Migradas N»).

En `CLAUDE.md`:
- Reescribí las viñetas de la ingesta que hablan de `p-{CntctCode}` como
  persona, del celular en conflicto y del socio sin grupo, y agregá la regla
  del número.
- En «Probar a mano», `identidad:habilitar 70741828`.
- En los pendientes, sacá la parte B y «persona en dos grupos». Agregá «borrar
  `habilitada_el`» y «borrar `identidad:migrar-a-numeros` después de correrlo
  en Azure».

En el traspaso de la ingesta, marcá la parte B como reemplazada por el spec
nuevo.

- [ ] **Step 4: Verificar que pasan, y la batería entera**

Run: `composer test && composer stan && composer lint -- --test`
Expected: PASS.

Si Keycloak está levantado: primero `docker compose down keycloak && docker compose up -d keycloak`, y después `composer test:integration`.
Expected: PASS con el usuario nuevo del realm.

- [ ] **Step 5: Commit**: `feat(identidad): migrar las personas por contacto a numeros`

---

## Despliegue (lo hace el usuario)

1. **No hay migraciones de base:** no cambia ninguna tabla.
2. **Push** a `azure` y a `origin`.
3. **Apenas termine el pipeline,** en el portal: Container App `ms-maestros` →
   **Consola**. Ahí:
   - `php artisan identidad:migrar-a-numeros`;
   - después, `php artisan identidad:conciliar`, que tiene que dar cero
     discrepancias.

   Entre el despliegue y la migración, los tres números de prueba no entran.
4. **Avisar al equipo de la App y a ms-comercial,** según la sección «Avisos»
   del spec.
