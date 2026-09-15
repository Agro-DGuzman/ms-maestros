# Hand-off · Back-office de accesos

**Rama:** `backoffice-de-accesos` (10 commits sobre `main`, sin mergear)
**Plan de origen:** `2026-09-15-backoffice-de-accesos.md`
**Diseño:** `2026-09-15-ms-maestros-backoffice-accesos-design.md`
**Estado:** tareas 1–12 hechas. La 13 está bloqueada por TI.

Leé `CLAUDE.md` antes de tocar nada: define las reglas de arquitectura, el
envelope y las trampas del entorno. Este documento cubre solo lo que cambió y
lo que el plan no anticipó.

---

## Qué hace ahora

Un operador de Agropartners concede y quita el acceso a la App desde tres
pantallas internas, con registro de quién hizo qué, en vez de correr
`php artisan identidad:habilitar` dentro del contenedor.

Siete rutas bajo `/admin/`, ninguna mezclada con las de `/v1/`:

```
GET  /admin/entrar                       redirige al autenticador
GET  /admin/callback                     valida el estado y abre sesión
GET  /admin/contactos                    lista con búsqueda, filtro y paginación
POST /admin/contactos/{id}/habilitar     ConcederAcceso  + asiento
POST /admin/contactos/{id}/deshabilitar  RevocarAcceso   + asiento
GET  /admin/contactos/{id}/historial     los asientos de esa persona
POST /admin/salir                        cierra la sesión local
```

Verificado a mano en el navegador: el ciclo completo dar → quitar → dar
funciona, y el historial registra acción, operador, IP y momento.

## Módulo nuevo

`src/BackOffice/`, al lado de `Maestros` e `Identidad`, con PSR-4 propio
(`BackOffice\` → `src/BackOffice/`, agregado a `composer.json`) y esquema de
base propio (`backoffice`).

La dependencia va en un solo sentido: **BackOffice → Identidad → Maestros**.
`tests/Architecture/EstructuraTest.php` lo verifica, incluida la regla de que
nadie mire hacia el back-office.

El back-office no escribe sobre la réplica de SAP. Despacha por el mediator
los casos de uso de `Identidad` que ya existían (`HabilitarPersona`,
`DeshabilitarPersona`) y asienta el resultado en su bitácora.

---

## Las seis desviaciones del plan

Cada una salió de un defecto real. Si volvés al plan, vas a encontrar lo otro.

### 1. Los namespaces del plan no existen

El plan escribe `App\BackOffice\…` y `App\Maestros\…`. El PSR-4 del proyecto
mapea `Maestros\` → `src/Maestros/` y `App\` → `app/`. Se usó el del proyecto.

### 2. La búsqueda va por el mediator, no por el puerto de Identidad

El plan agregaba `buscar()` a `DirectorioDeContactos`, que ubicaba en
`src/Maestros/Application/Contracts/`. Ese archivo no existe: el puerto vive en
`Identidad\Application\Contracts` y su docblock dice «las tres únicas preguntas
que Identidad le hace a Maestros».

En su lugar, `Maestros` expone `ListarContactos` (Request + Handler) y el
back-office lo despacha. Maestros sigue dueño de su modelo de lectura y el
puerto de Identidad no crece con un método que Identidad nunca llama.

### 3. La guarda de celular del plan era inalcanzable; el bug estaba al lado

B8 suponía que se podía habilitar a alguien sin celular utilizable. En este
esquema la columna es `NOT NULL` y `Celular` valida al leer, así que esa guarda
habría sido código muerto.

El fallo real, y ocurre hoy: si una fila tiene un celular inservible,
reconstruir la persona lanza `DomainException` y **revienta el mediator con un
stack**. `HabilitarPersonaHandler` ahora lo atrapa y devuelve `CELULAR_INVALIDO`
con un mensaje que dice dónde corregirlo.

### 4. `volver_a` era un open redirect

El plan tomaba ese campo del formulario y lo usaba como destino de redirección
tal cual, así que quien armara el POST elegía adónde saltaba el botón.
`AccesosController::destinoSeguro()` ahora solo acepta rutas relativas de
`/admin/`.

### 5. Un test del plan no podía pasar

Afirmaba que el historial no dijera «contraseña», pero su propia vista contiene
esa palabra explicando que no la muestra. Se comprueba lo que el diseño pide:
que no se filtre la credencial real.

### 6. El interruptor no respondía al operador

**El hallazgo más importante, y no estaba en el plan.**

La columna «Acceso» leía `habilitada_el`, que llena la importación desde SAP y
que el back-office no escribe nunca. El interruptor no cambiaba en ninguna de
las dos direcciones: conceder creaba el usuario en Keycloak, asentaba en la
bitácora, y la fila seguía ofreciendo «Dar acceso».

Debajo hay una contradicción del propio diseño. Su §1 dice que el back-office
«escribe únicamente sobre la habilitación, que es dato propio», pero en el
código la habilitación es una columna de la réplica que llena SAP.

**Resuelto separando las dos verdades**, con el usuario decidiendo entre tres
opciones:

- **En SAP** — lo que marca `habilitada_el` de la réplica.
- **Acceso a la App** — si la persona tiene credencial nuestra.

El botón actúa sobre la segunda. Cuando divergen la pantalla lo dice: *«Falta
darle acceso»* y *«Conserva acceso sin respaldo en SAP»*. Es la misma
divergencia que `identidad:conciliar` ya trata como estado real.

La composición va en PHP y no en un JOIN porque las dos fuentes viven en
esquemas distintos (ADR 0001).

---

## Cambios fuera de `BackOffice`

Tocan módulos existentes. Miralos antes de asumir que `Identidad` y `Maestros`
están como los dejó la fase 1.

| Dónde | Qué cambió y por qué |
|---|---|
| `HabilitarPersonaHandler` | Atrapa `DomainException` al cargar la persona → `CELULAR_INVALIDO` |
| `DeshabilitarPersonaHandler` | Ahora olvida la credencial guardada. Sin eso, «tiene credencial» seguía diciendo que sí después de revocar |
| `BovedaDeContrasenas` | Método nuevo `olvidar()` |
| `ContactoRepository` | Método nuevo `marcarVistasEnImportacion()` |
| `ContactoErrors` | Fábrica nueva `celularNoUtilizable()`, mismo código, mensaje para el operador |
| `ImportarMaestrosCommand` | Estampa `vista_en_importacion_el` en **toda** fila del archivo, incluidas las que omite por viejas |
| `maestros.contactos` | Columna nueva `vista_en_importacion_el` |
| `EstructuraTest` | Reglas nuevas que cubren `BackOffice` |

### Por qué `vista_en_importacion_el` y no `importado_el`

`importado_el` solo se escribe cuando la fila se guarda, y la importación omite
las que no son más nuevas. No distingue «no cambió» de «ya no está en SAP». La
columna nueva se estampa siempre.

La pantalla **avisa, no deshabilita**: la ausencia todavía no distingue una baja
en SAP de un archivo incompleto (§8.2 del diseño).

---

## Verificación

```bash
composer test              # 266 tests
composer stan              # Larastan nivel 9
composer lint -- --test    # Pint
composer test:integration  # 6 tests, necesita Keycloak vivo
```

Los cuatro en verde al cerrar la rama.

### La trampa del realm

Dar o quitar acceso desde la pantalla toca Keycloak de verdad: habilitar rota
la contraseña del realm importado y deshabilitar bloquea al usuario. Después de
cualquier prueba manual, `composer test:integration` falla hasta reimportar:

```bash
docker compose down keycloak && docker compose up -d keycloak
```

Un fallo de `KeycloakTest` casi siempre es esto y no una regresión.

---

## Pendientes

**Tarea 13 · `AutenticadorEntra`** — bloqueada por TI. Necesita el registro de
aplicación en Entra, el app role `Administrador` asignado, y confirmación de que
el token llega con el claim `roles`. Hasta entonces
`config('backoffice.autenticador')` vale `desarrollo`; elegir `entra` lanza
`BACKOFFICE_AUTENTICADOR_ENTRA_NO_IMPLEMENTADO` al arrancar.

**El glosario** — el plan pide agregar el término «operador» a `CONTEXT.md`. Ese
archivo no está versionado en el repo; existe una copia suelta en la carpeta de
descargas del usuario. Quedó sin hacer a propósito.

**La semilla** — `database/semillas/maestros-ejemplo.json` tiene **una sola
persona de contacto**, así que la búsqueda y la paginación no se pueden
ejercitar a mano. Enriquecerla es lo primero que conviene hacer para probar por
pantalla. Los casos ricos sí están cubiertos en
`tests/Feature/Maestros/BusquedaDeContactosTest.php`.

**El contenedor `app`** — la imagen que corre en el 8000 puede tener código
anterior al back-office. `docker compose up -d --build app worker`.

---

## Decisiones que ya tomó el usuario

No las reabras sin preguntar.

1. **La búsqueda va por un caso de uso de Maestros despachado por el mediator**,
   no agregando `buscar()` al puerto de Identidad.
2. **La pantalla muestra dos columnas** —SAP y credencial— en vez de leer el
   estado real de Keycloak por fila (N llamadas HTTP por página; el supuesto S6
   habla de centenas de contactos) o de mover `habilitada_el` fuera de la
   réplica.
