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
- **Un token vale solo si viene de nuestro realm y es para nuestro cliente.**
  `JWT::decode` comprueba firma y expiración, no de dónde viene ni para quién
  es, así que `VerificadorJwks` valida además `iss` y la audiencia. La
  audiencia sale de `aud` cuando está, y de `azp` cuando no: Keycloak no emite
  `aud` salvo que el realm tenga un audience mapper, y exigirla a secas
  rechazaría todos los tokens que este realm emite hoy.
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

## Probar a mano

La colección de Postman está en `docs/ms-maestros.postman_collection.json` y se
autoencadena: el OTP guarda `idDeDesafio`, el login guarda `token` y
`refreshToken`. Lo único que se escribe a mano es el `codigo`.

**Hace falta un worker corriendo.** Con la cola en `database`, `POST /auth/otp`
responde 200 pero no manda nada hasta que alguien levante el trabajo. Si el
código no aparece en el log, falta `queue:work` — no es un bug.

Local, sin Docker (alcanza para todo `/auth/otp`, los comandos y el envelope):

```sh
php artisan migrate
php artisan maestros:importar database/semillas/maestros-ejemplo.json
php artisan serve --port=8099      # una terminal
php artisan queue:work             # otra terminal
tail -f storage/logs/laravel.log   # de aca sale el codigo
```

Para el login hace falta Keycloak, porque la credencial la emite el proveedor.
`KEYCLOAK_CLIENT_SECRET` tiene que estar en `.env` (el realm importado usa
`secreto-de-desarrollo`), y la persona necesita su credencial sincronizada:

```sh
docker compose up -d keycloak
php artisan identidad:habilitar p-8f2b1c40
```

El realm de `docker/keycloak/` ya trae el usuario `p-8f2b1c40`, que es el
contacto de la semilla, con celular `70741828`.

La verificación de que `POST /auth/otp` no filtra por tiempo **no la hace
ningún test**: hay que pedir el desafío para un número registrado y para uno
desconocido y comparar los tiempos, que tienen que ser indistinguibles.

### El stack completo, contra SQL Server

`docker compose up -d` levanta SQL Server, Keycloak, el `app` en `:8000` y el
`worker`. El orden lo resuelve solo: `sqlserver-init` crea la base que
`DB_DATABASE` nombra y recién entonces arrancan los otros dos.

```sh
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan maestros:importar database/semillas/maestros-ejemplo.json
docker compose exec app php artisan identidad:habilitar p-8f2b1c40
```

El código del desafío sale del log del worker, no del app:

```sh
docker compose logs -f worker
```

**Después de tocar código hay que reconstruir.** `docker compose up -d` reusa
la imagen que ya existe: sin `--build` el contenedor sigue corriendo el código
viejo y las pruebas manuales dan resultados que no corresponden a lo que hay
en el árbol.

```sh
docker compose up -d --build app worker
```

### Si `mi-cuenta` responde 401 con un token que parece válido

Dos causas, en orden de frecuencia:

1. **El token vive 5 minutos.** `data.expiraEnSegundos` lo dice (300). Un token
   que quedó en Postman un rato ya venció: pedí otro con `/v1/auth/refresh`.
2. **Recrear Keycloak invalida todo token ya emitido y además envenena el
   caché.** `VerificadorJwks` cachea el JWKS 60 minutos, así que tras un
   `down`/`up` del contenedor el caché guarda las claves del realm anterior y
   **ningún token verifica, ni siquiera uno nuevo**, hasta que expire. Después
   de tocar el contenedor de Keycloak, siempre:

```sh
php artisan cache:clear
```

Para confirmar que es esto, comparar el `kid` que publica el realm contra el
que quedó cacheado — si difieren, es el caché:

```sh
curl -s http://localhost:8081/realms/agropartners/protocol/openid-connect/certs
```

### El orden importa: integración antes de habilitar

`composer test:integration` espera que `p-8f2b1c40` tenga en Keycloak la
contraseña `contrasena-de-desarrollo` que trae el realm importado. Pero
`identidad:habilitar` **sobreescribe** esa contraseña con una aleatoria, y
`identidad:deshabilitar` borra el usuario. Después de correr cualquiera de los
dos, la batería de integración falla hasta reimportar el realm:

```sh
docker compose down keycloak && docker compose up -d keycloak
```

O sea: integración contra un realm recién levantado, y el recorrido manual
después. Un fallo de `KeycloakTest` casi siempre es esto y no una regresión.

## Pendientes conocidos

- Keycloak arranca con `start-dev` en `compose.yaml`, con base embebida que se
  pierde al recrear el contenedor.
- Sin responder: si una persona de contacto puede estar registrada en socios de
  dos grupos distintos. Si el caso existe, hoy no falla con un error claro: le
  muestra al usuario la mitad de sus socios.
