# ms-maestros

Microservicio de maestros e identidad de Agropartners. Réplica de SAP en modo
solo-lectura más el ingreso a la App por WhatsApp.

## Cómo correr las cosas

```sh
composer test              # unitarias + feature + arquitectura
composer stan              # Larastan nivel 9
composer lint              # Pint (agregar -- --test para no escribir)
composer test:integration  # necesita Keycloak vivo
```

Los tres primeros tienen que estar en verde antes de cualquier commit.

## Estructura

```
src/Core/          andamiaje portado del core Java: Result, Mediator, behaviors
src/Maestros/      socios, grupos, personas de contacto (réplica de SAP)
src/Identidad/     desafío de ingreso, sesiones, credenciales
app/               el host: Laravel, providers, envelope HTTP, adaptadores
```

Cada módulo se parte en `Domain/`, `Application/`, `Infrastructure/` y
`Presentation/`.

## Reglas que los tests de arquitectura hacen cumplir

`tests/Architecture/EstructuraTest.php` las verifica; no relajarlas para que
pase algo, porque una regla en rojo es un hallazgo real.

- **PHP 8.4** y `declare(strict_types=1);` en **todos** los archivos de `src/`.
- **PSR-4:** `Core\` → `src/Core/`, `Maestros\` → `src/Maestros/`,
  `Identidad\` → `src/Identidad/`, `App\` → `app/`, `Tests\` → `tests/`.
- **`Core/` no referencia `Illuminate\`.** Si el core necesita algo del
  framework, se define un puerto en `Core/Contracts/` y el adaptador vive en
  `app/`. Así entró `Transactor` cuando la transacción se mudó al pipeline.
- **`Maestros/` no referencia `Identidad\`**, y `Identidad\Domain` toma de
  Maestros solo el vocabulario de contacto (`Celular`, `IdDePersona`): ni
  agregados ajenos ni capas internas.
- **El `Domain/` y el `Application/` de cualquier módulo no conocen Eloquent,
  Laravel, ni la `Infrastructure` de nadie.** Eloquent vive solo en
  `Infrastructure`.
- Todo lo que Identidad le pregunta a Maestros pasa por el puerto
  `DirectorioDeContactos`. Ninguna consulta cruza de un esquema al otro.

### Ojo con la forma de las reglas de Pest

Pest combina las listas con AND: `expect([a, b])->not->toUse([x, y])` solo
falla si **las dos** capas usan **los dos** destinos, así que una regla escrita
con listas en los dos lados se relaja sola hasta no verificar nada. Una regla
por par (capa, prohibido) — los `foreach` de `EstructuraTest.php` las generan.

Por lo mismo, ningún test de arquitectura usa `get_declared_classes()`: con
autoload perezoso casi nada está cargado y el test pasa en verde sin mirar
nada. `Tests\Soporte\Clases::deSrc()` recorre el filesystem, y `AlcanceTest`
afirma primero que el recolector encontró algo.

## Nombres

Andamiaje en inglés (`Entity`, `Repository`, `save`, `find`, `Transactor`);
negocio en español (`Socio`, `DesafioDeIngreso`, `alcanza`). Sin prefijo `I`
en interfaces. Comentarios y mensajes de commit en español.

Los comentarios explican **por qué**, no qué. Si un comentario describe lo que
la línea siguiente ya dice, sobra.

## Contratos que no se negocian

- **Envelope:** `{ data, success, error }`, exactamente esos tres campos,
  construido desde un `Result` y nunca a mano. `success: true ⟹ error: null`;
  `success: false ⟹ data: null`.
- **Un solo control de autorización: el alcance.** Fuera de alcance → 403
  `ACCESO_DENEGADO`, nunca 404 ni lista vacía, y **el alcance se verifica antes
  que la existencia**. Toda petición que recibe un `CodigoDeSocio` declara
  `ConAlcanceDeSocio` (lo verifica `AlcanceTest`).
- **Códigos HTTP:** salen de `MapaDeErroresHttp`. 401/403/429 van por código en
  la tabla de excepciones; un `FAILURE` que no está en la tabla es un error
  nuestro y responde 500, no 403.
- **Desafío de ingreso:** 4 dígitos, 5 minutos, 5 intentos, un solo uso. Todo
  fallo de resolución responde el mismo `CODIGO_INVALIDO` — un desafío
  inexistente, uno vencido, uno ya usado y uno emitido para un número que no es
  de nadie son indistinguibles a propósito.
- **`POST /auth/otp` responde lo mismo y en el mismo tiempo** exista o no el
  número. Por eso el envío se encola: resolver la persona y hablar con WhatsApp
  son las dos cosas caras y las dos pasan dentro del trabajo encolado.
  **`QUEUE_CONNECTION` no puede ser `sync` en producción**, o la fuga vuelve.
- **Una réplica nunca retrocede:** antes de escribir, comparar `vigenteDesde`
  contra lo guardado con `debeReemplazarA()`.
- **Un `Result` fallido confirma la transacción; solo la excepción deshace.**
  Un fallo es una salida deliberada del caso de uso y lo que escribió antes es
  parte de la decisión: el contador de intentos del desafío se persiste justo
  antes de devolver `CODIGO_INVALIDO`, y si el fallo deshiciera, el límite de
  cinco intentos no existiría.
- **No borrar `DomainEvent` ni `Entity::addDomainEvent()`**: son el core
  portado desde Java, están probados, y la fase 2 los usa.

## Pruebas

- Pest. `tests/Unit` para dominio puro, `tests/Feature` contra SQLite en
  memoria, `tests/Architecture` para las reglas de arriba, `tests/Integration`
  aparte porque necesita Keycloak.
- **Los dobles compartidos viven en `tests/Dobles/`**, nunca declarados dentro
  de un archivo de prueba: si dos archivos comparten un doble declarado en uno
  de ellos, la suite completa pasa por orden de carga y correr el archivo
  suelto es un fatal error.
- Cada archivo de prueba tiene que poder correr solo:
  `vendor/bin/pest tests/Feature/Identidad/RenovarYCerrarTest.php`.
- Primero el test que falla, y verificarlo en rojo antes de escribir el código.
  Un test nuevo que pasa de entrada no está probando lo que se cree.

## Pendientes conocidos

- `VerificadorJwks` no valida `aud` ni `iss`: `JWT::decode` comprueba firma y
  expiración, no audiencia. Con un solo cliente en el realm no se explota, pero
  el día que aparezca un segundo la falla es silenciosa.
- Keycloak arranca con `start-dev` en `compose.yaml`, con base embebida que se
  pierde al recrear el contenedor.
- Sin responder: si una persona de contacto puede estar registrada en socios de
  dos grupos distintos. Si el caso existe, hoy no falla con un error claro: le
  muestra al usuario la mitad de sus socios.
