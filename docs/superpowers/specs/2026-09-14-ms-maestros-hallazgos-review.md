# Hallazgos del review de `ms-maestros` · fase 1

**Fecha:** 14 de septiembre de 2026
**Método:** revisión de dos ejes (Standards / Spec) sobre el árbol completo. Sin diff —el repo vive en una máquina Windows inalcanzable desde el shell— y **sin ejecutar la batería de pruebas**. Todo sale de leer el código.
**Diseño de referencia:** `docs/superpowers/specs/2026-09-11-ms-maestros-estructura-design.md`
**Plan que lo implementó:** `docs/superpowers/plans/2026-09-11-ms-maestros-fase-1-identidad.md`

Dos hallazgos —H3 y H8— son errores del plan original, no de quien lo ejecutó. Están marcados.

---

## Cambian comportamiento

### H1 · La transacción no existe

`src/Maestros/Infrastructure/Persistence/EloquentUnitOfWork.php:21-23` abre una transacción con el cuerpo vacío. Los repositorios escriben **fuera** de ella, antes. Y `commit()` no se invoca desde ningún lado: el único rastro es el `bind` en `app/Providers/ModulosServiceProvider.php:55`.

El diseño §3.2 promete: «Abre transacción, persiste, confirma, y recién entonces junta los eventos de dominio de los agregados recibidos y los publica». Nada de eso ocurre.

Consecuencia concreta: `IniciarSesionHandler.php:52` (guarda el desafío consumido) y `:91` (guarda la sesión) son dos escrituras sueltas. Si el proceso muere entre las dos, el código de WhatsApp queda quemado y el socio se queda sin sesión.

Agravante: §3.2 pide «Behaviors de la fase 1: validación, transacción y alcance». Solo existe `AlcanceBehavior` (`CoreServiceProvider.php:40-42`).

### H2 · `POST /auth/otp` filtra por tiempo qué números existen

`SolicitarDesafioHandler.php:46-52` resuelve la persona **antes** de responder, y para un número desconocido sale temprano: sin `INSERT`, sin llamada a WhatsApp. La forma de la respuesta es constante; el tiempo no.

El diseño §6 lo dice textual: «responde **lo mismo y en el mismo tiempo** esté o no registrado el número. Si el desafío exigiera una persona detrás, el servicio tendría que resolverla antes de responder, y esa diferencia de tiempo sería la fuga que la regla quiere evitar».

Es la regla que el modelo de dominio v3.0 diseñó a propósito, y hoy no se cumple.

### H3 · El test de arquitectura del alcance pasa en verde sin verificar nada *(error del plan)*

`tests/Architecture/AlcanceTest.php:12` recorre `get_declared_classes()`, que solo ve las clases **ya cargadas** en ese momento. Con el autoload perezoso de PSR-4, ninguna petición está cargada cuando corre el test: pasa aunque el archivo esté vacío.

El plan §11.3 lo llamaba «el olvido que el behavior no puede atrapar solo». Hoy no atrapa nada.

### H4 · La importación no respeta que una réplica nunca retrocede

`ImportarMaestrosCommand.php:79-97` hace `save()` incondicional. Reejecutar la carga con un `vigenteDesde` más viejo pisa datos más nuevos.

`Socio::debeReemplazarA()` existe, está probado (`tests/Unit/Maestros/SocioTest.php`) y **no lo llama nadie**. El diseño §5 dice: «En fase 1 la usa la carga manual».

### H5 · `FAILURE` sin código conocido responde 403

`app/Http/MapaDeErroresHttp.php:31` usa `ErrorType::Failure => 403` como valor por defecto. Cualquier fallo genérico responde igual que `ACCESO_DENEGADO`, lo que hace indistinguible un permiso denegado de un error de programación. El defecto correcto es 500; 401, 403 y 429 ya están cubiertos por código en la tabla de excepciones.

---

## Estructura y capas

### H6 · `GrupoRepository` no existe y Application consulta Eloquent

`ObtenerContextoHandler.php:16,50` importa y consulta `Maestros\Infrastructure\Persistence\GrupoRecord` desde la capa Application. Contra el diseño §10: «Eloquent vive solo en `Infrastructure`».

El plan lo nombraba en la estructura (`Domain/Grupos/ GrupoEconomico, IdDeGrupo, GrupoRepository`) y no se llegó a crear. Ningún test de arquitectura lo atrapa: `EstructuraTest.php:63` solo vigila `*\Domain`.

### H7 · `ConciliarIdentidadesCommand` cruza la frontera entre módulos

`ConciliarIdentidadesCommand.php:11,24` itera `Maestros\Infrastructure\Persistence\ContactoRecord` directo, salteando el puerto `DirectorioDeContactos` — cuyo propio docblock declara que son «las dos únicas preguntas que Identidad le hace a Maestros». El día que se partan los servicios, esto no se reemplaza con un cliente HTTP.

Es además la única consulta que cruza de un esquema al otro, que es exactamente lo que la ADR 0001 pide evitar.

### H8 · Los dobles compartidos viven dentro de un archivo de prueba *(error del plan, corregido tarde)*

`tests/Feature/Identidad/RenovarYCerrarTest.php:17-18` usa `EmisorFalso` y `EnviadorQueRecuerda`, declarados en `IniciarSesionTest.php:19`. Correr ese archivo solo es un *fatal error*. La suite completa pasa por orden de carga, que es peor: falla únicamente cuando alguien depura un archivo suelto.

El apéndice A.1 del plan corrige esto (`tests/Dobles/` autocargado) y no se aplicó: no existe el directorio ni el namespace `Tests\Dobles\` en `composer.json:36-39`.

---

## Código muerto y ruido

### H9 · Andamiaje sin un solo uso

- `Core/Contracts/UnitOfWork.php` + `EloquentUnitOfWork` — ver H1.
- `Core/Contracts/NotificationPublisher.php` + `EventoDeLaravelPublisher` — ningún agregado emite eventos de dominio todavía, así que nadie publica nada.
- `$readOnly` de `Core/Contracts/Repository.php:11` — las cuatro implementaciones Eloquent lo ignoran, y seis llamadas lo pasan en `true` (`ResolutorPorGrupo.php:29,35,36`, `ObtenerContextoHandler.php:30,38`). Un parámetro que miente sobre lo que hace.
- `ResolutorQueNiegaTodo` existe y se niega a arrancar en producción, pero ningún test lo ejerce.

`DomainEvent` y `Entity::addDomainEvent()` **se conservan**: son parte del core portado desde Java, están probados, y la fase 2 los usa cuando llegue la ingesta de eventos.

### H10 · Duplicación mecánica

- El `getTable()` con el ternario `sqlsrv`/plano está copiado en los seis records: `SocioRecord:34`, `GrupoRecord:33`, `ContactoRecord:37`, `SesionRecord:39`, `DesafioRecord:38`, `CredencialRecord:30`.
- Errores armados a mano duplicando las fábricas que ya existen: `ObtenerContextoHandler:42-46` reimplementa `SocioErrors::noEncontrado()`, y `CONTACTO_NO_ENCONTRADO` aparece con tres mensajes distintos en cuatro archivos (`BuscarPorCelularHandler:25-28`, `ObtenerContextoHandler:32-36`, `HabilitarPersonaHandler:31-35`, `IniciarSesionHandler:96-99`).

### H12 · El verificador de token no mira `aud` ni `iss`

`src/Identidad/Infrastructure/Keycloak/VerificadorJwks.php:37` llama a `JWT::decode($jwt, $claves)`, que valida la firma y las marcas `exp`/`nbf`, pero **no** la audiencia ni el emisor. Un token emitido por el mismo realm para otro cliente sería aceptado.

Hoy hay un solo cliente en el realm, así que no es explotable, pero el día que aparezca un segundo la falla es silenciosa y difícil de diagnosticar.

*(Hallazgo propio, fuera de los dos ejes. Se difiere a propósito: cambia qué tokens acepta el servicio y merece su propia decisión, no entrar de contrabando en una tanda de correcciones.)*

### H11 · Menores

- `ImportarMaestrosCommand:58-59` usa `$socios_` y `$contactos_` con guion bajo para esquivar una colisión de nombres.
- `composer.json:9` fija `"php": "^8.3"` contra el requisito «PHP 8.4» del plan.
- `EstructuraTest.php:59-61` se llama «todo src declara tipos estrictos» y solo evalúa `Core`.
- `Application/Habilitacion/DeshabilitarPersona/` no existe, aunque `DirectorioDeIdentidades::deshabilitar()` sí.
- `CLAUDE.md` y `AGENTS.md` son el bootstrap de Laravel Boost sin personalizar: el repo no documenta ningún estándar propio.

---

## Lo que está correcto y no hay que tocar

- Supuesto S1: `cantidadPropiedades` devuelve `0` (`ObtenerContextoHandler.php:59`), con su prueba.
- Supuesto S2: la importación registra `importado_el` (`EloquentSocioRepository.php:71`).
- El envelope se construye desde un `Result` y nunca a mano; los códigos 401/403/429 salen por la tabla de excepciones.
- El alcance se verifica antes que la existencia (`AlcanceBehavior.php:32`).
- `docker/keycloak/README.md` documenta el problema del *declarative user profile* con el síntoma exacto (`Account is not fully set up`) y su corrección.
- Ninguna funcionalidad fuera de alcance en `src/`.
