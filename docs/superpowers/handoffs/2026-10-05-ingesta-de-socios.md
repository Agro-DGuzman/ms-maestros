# Hand-off · Ingesta de socios desde SAP

**Rama:** `feat/ingesta-de-socios`, integrada a `main` el 2026-10-06 (merge `55be6d0`).
**Spec:** `docs/superpowers/specs/2026-10-05-ingesta-de-socios-design.md`
**Plan:** `docs/superpowers/plans/2026-10-05-ingesta-de-socios.md` (las 7 tareas hechas)
**Estado:** suite 584/584, Larastan 0, Pint en verde. Recorrido HTTP verificado
contra SQL Server local. Las dos migraciones corrieron en Azure el 2026-10-06 y
`main` se empujó a `azure`. **`/ingesta` todavía no recibe nada de verdad**:
faltan las variables del Container App y Entra/APIM (§6).

`CLAUDE.md` ya tiene las reglas de la ingesta (seguridad en orden, `p-{CntctCode}`,
*visible*, conflicto de celular, baja lógica, `EscrituraDeMaquina`). Esto cubre
solo lo que no está en el repo. Los documentos fuente del Sincronizador
(`DECISIONES-INGESTA-SOCIOS.md`, `CONFIGURACION-INGESTA-SAP.md`,
`DISENO-SINCRONIZADOR.md`) **no están en el repo**: el spec los resume y es la
autoridad acá.

---

## 1. Arranque

```sh
composer install
cp .env.example .env && php artisan key:generate
composer test && composer stan && composer lint -- --test
```

Listo cuando los tres dan verde y la suite cuenta **584**. Requiere PHP 8.4 con
`pdo_sqlite`; si el entorno no lo tiene, frená y avisá antes de tocar código.

## 2. Hasta dónde llega esta sesión

Tu trabajo termina en **una rama tuya, empujada a `origin`** (GitHub), con la
suite en verde. Integrar a `main`, migrar y desplegar lo hace el usuario desde
su máquina, porque ahí están las tres cosas que esta sesión no tiene:

- el driver `sqlsrv` y el SQL Server de docker;
- `.env.azure` y `.env.keycloak.azure`, con las credenciales reales;
- el remoto `azure` (Azure DevOps), cuyo push dispara el pipeline.

### Lo que SQLite no ve

El hallazgo grave de la revisión fue `insertOrIgnore`: SQLite lo implementa, la
gramática de SQL Server de Laravel no, y en Azure toda llamada habría dado 500
con la suite en verde. Por eso, en tu informe final, listá **cada consulta
nueva o cambiada** (método del query builder, `selectSub`, SQL crudo, migración)
bajo «verificar en SQL Server», con el comando para que el usuario la corra:

```sh
docker compose up -d --build app worker
docker compose exec app php artisan migrate --force
```

## 3. Trabajo pendiente, por prioridad

Cada uno: test en rojo primero, después el código.

1. **Parte B: la habilitación escribe `habilitada_el`.** Es diseño: spec antes
   de código. Hoy solo el importador escribe esa columna, y la ingesta no la
   toca nunca, así que con datos de la ingesta:
   - el filtro «Habilitadas» del back-office queda vacío;
   - `identidad:conciliar` (`ConciliarIdentidadesCommand`, vía `habilitadas()`)
     reporta a **toda persona con acceso** como «credencial sin habilitación»;
   - la marca roja de «dada de baja en SAP» no se enciende nunca.
   
   `HabilitarPersonaHandler` ya no exige `habilitada_el`. Lo que falta: conceder
   la marca, revocar la borra. La pregunta abierta es cómo Identidad le pide a
   Maestros esa escritura sin romper la regla del puerto `DirectorioDeContactos`.
   Tiene que estar antes de que entren personas reales por la ingesta.
2. **El refetch del JWKS vacía el caché antes de traer el nuevo**
   (`app/Ingesta/VerificadorEntra.php`, `claves()`). Si Entra falla en ese
   momento, todo token válido da 503. Traer primero y reemplazar solo si salió
   bien. Listo cuando un refetch fallido deja verificar un token firmado con
   un `kid` ya cacheado.
3. **`GATEWAY_SECRETO_EN_V1` lee `no` u `off` como verdadero**
   (`config/ingesta.php`, `(bool) env(...)`). Usar `filter_var(...,
   FILTER_VALIDATE_BOOL)`.
4. **«Visible» repetido** en `EloquentSocioRepository::porGrupo()` y en
   `EloquentBuscadorDeContactos::estado()`. Reusar
   `Maestros\Infrastructure\Persistence\Visibilidad`. Es una consulta: va a
   «verificar en SQL Server».
5. **CLAUDE.md describe una sola regla de vigencia.** El importador usa
   `debeReemplazarA()` con precisión completa; la ingesta usa `esMasNuevo()`
   de `ReplicarSocioHandler`, truncado al segundo (empatar es no reemplazar).
   Decir cuál aplica dónde.
6. **El contrato de ingesta no declara** el 400 del `DELETE` ni el 500
   `CONFIGURACION_INCOMPLETA`. `contrato/ingesta-api-v1.yaml` es copia
   enmendada; el archivo oficial es del equipo del Sincronizador, así que
   el cambio se le avisa al usuario para que lo pase.
7. **Postman no tiene `/ingesta`** (`docs/ms-maestros.postman_collection.json`).
8. **El celular crudo de SAP que no normaliza se pierde**: el back-office
   muestra «—» igual para «sin número» y «número inválido». Necesita columna
   nueva: diseño, prioridad baja. El mensaje de `ContactoErrors` todavía dice
   «volvé a importar».

## 4. Esperan información de afuera

No los resuelvas adivinando; si el usuario trae la respuesta, el cambio es chico.

- **¿Hay `cardCode` con `/`?** La ruta usa `[^/]+`: un `DELETE` daría 404 de
  ruta (el Sincronizador lo toma como éxito) y un `PUT` entraría en bucle
  409. Si existen: `->where('cardCode', '.+')`. Lo sabe SAP.
- **¿`MENSAJE_ID` llega en hexadecimal minúscula?** `Idempotency-Key` exige
  `^[0-9a-f]{32}$`; en mayúscula, todo pedido da 400. Lo sabe el equipo del
  Sincronizador.
- **¿El Sincronizador procesa en serie por socio?** La vigencia se compara sin
  bloqueo de fila; se asumió que sí.
- **¿Cuántos celulares comparten contactos visibles en SAP?** Cada uno queda en
  conflicto y nadie entra con él (decisión de producto I4). Medir antes de
  salir.

## 5. Decisiones tomadas: no reabrir

- La vigencia se evalúa **antes** que el tipo (spec §3 corregido): un envío
  viejo de cuando era lead no da de baja a un cliente.
- `vigenteDesde` se pasa a UTC en el controlador; los repositorios formatean con
  el trait `App\Persistence\FechaEnUtc`.
- La ingesta escribe contactos con `ContactoRepository::replicar()`, que no
  incluye `habilitada_el`. `save()` sí la incluye: no usarlo desde la ingesta.
- Idempotencia: `insert` que tolera `UniqueConstraintViolationException`.
- Las claves RSA de los tests son PEM fijos en `tests/Soporte/claves-de-prueba/`
  (`openssl_pkey_new` falla en el PHP de Windows).
- Aceptados a propósito: el refresh no se corta por un celular en conflicto
  (sí por visibilidad); el token de acceso vive hasta 5 minutos tras una baja;
  una `vigenteDesde` futura congela al socio hasta un `DELETE`.

## 6. Despliegue

Hecho el 2026-10-06: merge a `main`, las dos migraciones en Azure (antes del
push, porque el login y `/mi-cuenta` leen `activo` y `dado_de_baja_el`) y push a
`azure` y `origin`. Lo que falta lo hace el usuario en el portal:

1. En el Container App: `GATEWAY_SECRETO` (secretRef), `INGESTA_ENTRA_TENANT_ID`
   e `INGESTA_ENTRA_AUDIENCIA`. Sin el secreto, `/ingesta` responde 403 a todo.
2. Pasos 2 y 3 de `CONFIGURACION-INGESTA-SAP.md` (Entra y APIM) y la prueba de
   punta a punta de su §6.

Los números de prueba de la App en Azure son tres (`OTP_ECO_NUMEROS`):
`70741828` (p-8f2b1c40, GRP-014), `71112233` (p-b2c3d401, GRP-021) y
`72112233` (p-c3d4e501, GRP-033).
