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
  es, así que `VerificadorJwks` valida además `iss` y la audiencia.
- **La audiencia se cumple con `aud` o con `azp`, y alcanza con una.** No se
  puede preferir `aud` cuando está: Keycloak le pone `aud: account` a todo
  usuario con los roles por defecto del realm, o sea a **toda persona que crea
  `identidad:habilitar`**, y eso no tiene nada que ver con nosotros. Exigir
  entonces que `aud` nos nombre rechazaba a todas ellas — entraban, recibían su
  token, y después cada pedido daba `NO_AUTENTICADO`. El único usuario que
  funcionaba era el del realm importado, que no tiene roles por defecto y por
  eso no trae `aud`; por ahí se escondió el agujero durante toda la fase 1.
  **Cualquier prueba sobre tokens tiene que usar una persona creada por el
  Admin API**, no la del realm, porque son formas de token distintas.
- **«Quién emite» y «dónde está» son dos valores distintos.** `KEYCLOAK_ISSUER`
  es la identidad que viaja como `iss` dentro de cada token; `KEYCLOAK_BASE_URL`
  es la dirección de red por la que se llama a Keycloak. En local coinciden y el
  issuer cae por defecto en la base. En Azure no: el `iss` es
  `https://identidad.agropartners.com.bo` —un nombre propio que no resuelve a
  ningún lado y por eso no necesita DNS ni certificado— y las llamadas van al
  FQDN interno del entorno. **Cambiar el issuer invalida todo token emitido**,
  así que se elige una vez; la dirección, en cambio, puede cambiar sin
  consecuencias. Del lado de Keycloak esto exige
  `KC_HOSTNAME_BACKCHANNEL_DYNAMIC=true`, que a su vez **exige que `KC_HOSTNAME`
  sea una URL con esquema**, no un hostname pelado.
- **Deshabilitar no borra: pone `enabled = false`.** Por eso el puerto pregunta
  `estaActivo()` y no «existe»: buscar al usuario por username lo encuentra
  igual después de darlo de baja. Los dobles de prueba tienen que modelar eso
  — uno que hiciera `unset()` afirma que el usuario desapareció y tapa el
  agujero.
- **`identidad:conciliar` mira las dos direcciones**: quién está habilitado en
  la réplica y no tiene acceso, y quién conserva credencial sin seguir
  habilitado. La segunda es la que detecta una revocación a medias, porque
  nada propaga solo que SAP deje de marcar a alguien.
- **La búsqueda del back-office ignora mayúsculas y acentos.** La colación por
  defecto de Azure SQL (`SQL_Latin1_General_CP1_CI_AS`) distingue acentos, así
  que sin pedir `Latin1_General_CI_AI` en la comparación un operador que teclea
  «Chavez» no encuentra a «Chávez». Lo resuelve el trait
  `App\Persistence\ComparacionSinAcentos`. **En SQLite no hay equivalente**, así
  que la batería solo puede afirmar qué SQL se genera; el comportamiento se
  comprueba contra SQL Server.
- **Quién entra al back-office lo decide `AutenticadorDeOperador`, y hay tres
  implementaciones.** `AutenticadorDeDesarrollo` **no pide contraseña**: le
  devuelve a cualquiera el operador del `.env`, y siempre el mismo, así que
  todos los asientos de bitácora quedan firmados por la misma persona. Sirve
  para trabajar en local y nada más — por eso muere en el constructor con
  `APP_ENV=production`. Lo que corre en el piloto es
  `AutenticadorDeContrasena`, con una contraseña por persona, para que la
  bitácora pueda decir quién hizo cada cosa. `AutenticadorEntra` es el destino
  y todavía no existe.
- **El ingreso con contraseña pasa por el mismo recorrido que tendrá Entra.**
  El formulario no abre la sesión: verifica la contraseña, emite un código de un
  solo uso y redirige a `/admin/callback`, que es donde ya estaban el control de
  `state` y la regeneración de sesión. Así la contraseña no viaja en ninguna URL
  y el día que entre Entra se borran el controlador, sus dos rutas y su vista
  sin tocar nada más. El código vive 60 segundos, se guarda hasheado en el caché
  (es una credencial: en claro, cualquiera que liste llaves entra) y se gasta al
  canjearse.
- **Correo desconocido y contraseña equivocada responden lo mismo**, igual que
  el desafío de ingreso, y también tardan lo mismo: cuando el correo no es de
  nadie se compara contra un hash de descarte, porque rechazar sin hashear es
  notablemente más rápido y eso delata qué correos son de operadores.
- **El freno de intentos cuenta por correo y por IP, no solo por IP.** Los dos
  operadores salen por la misma oficina; contar solo la IP dejaría que uno
  bloquee al otro equivocándose cinco veces.
- **La IP de la persona sale de `IpRealDetrasDelIngress`, no de `X-Forwarded-For`
  a secas.** De `$pedido->ip()` cuelgan tres cosas: el filtro de rangos del
  back-office, el freno de intentos del ingreso, y la dirección que la bitácora
  guarda **para siempre** —y que es append-only, así que un dato malo no se
  corrige nunca—. Detrás del ingress de Container Apps las tres verían la
  dirección de Azure. La cabecera se recorta a su **última** entrada, que es la
  que agrega el ingress; Symfony resuelve la cadena tomando la primera, que acá
  es justo la parte que el cliente escribe. Va con interruptor
  (`DETRAS_DE_PROXY`) y **apagado por defecto**: encenderlo sin un proxy delante
  convierte la lista de rangos en decoración, porque entonces la cabecera la
  controla quien conecta. El mismo interruptor enciende
  `EsquemaRealDetrasDelIngress`: el ingress atiende HTTPS y le pasa HTTP plano,
  y sin eso cada formulario y cada redirección apuntan a `http://`. El ingress
  redirige a HTTPS y en esa redirección **el POST llega como GET**, así que el
  ingreso al back-office no funciona.
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
autoencadena: el OTP guarda `otpId`, el login guarda `token` y
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

### Entrar al back-office con contraseña

En local no hace falta: con `BACKOFFICE_AUTENTICADOR=desarrollo`, abrir
`/admin/entrar` ya deja adentro. Para probar el ingreso de verdad —el que va al
piloto— hay que generar una línea por persona y pegarlas todas en una sola
variable:

```sh
php artisan backoffice:hash ana@agropartners.com.bo "Ana Suárez"
```

Pide la contraseña sin mostrarla (mínimo 12 caracteres) e imprime
`correo|hash|Nombre`. Varias personas van separadas por `;` en
`BACKOFFICE_OPERADORES`, y hay que poner `BACKOFFICE_AUTENTICADOR=contrasena`.
El valor va **entre comillas** en el `.env`, porque el nombre lleva espacios y
sin comillas dotenv corta la línea con un error de parseo que impide arrancar
del todo. El `$` del hash no molesta: no se interpola.

`/admin/formulario` y `/admin/verificar` **existen siempre pero responden 404**
con cualquier otro autenticador puesto. Se registran incondicionalmente a
propósito: si dependieran de la configuración, `route:cache` congelaría la que
estaba cuando se cacheó.

Sin `BACKOFFICE_OPERADORES` el autenticador **no arranca** (`BACKOFFICE_SIN_OPERADORES`),
y una entrada mal formada tampoco (`BACKOFFICE_OPERADOR_MAL_FORMADO`). Los dos
son a propósito: saltear en silencio a quien quedó mal escrito se descubre
recién cuando esa persona no puede trabajar.

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

### Apuntar la app a una base en Azure SQL

`compose.azure.yaml` sobreescribe la base de `app` y `worker` dejando Keycloak
local. Los valores salen de un `.env.azure` propio, que git ignora:

```sh
set -a; . .env.azure; set +a
export AZ_DB_HOST=$DB_HOST AZ_DB_DATABASE=$DB_DATABASE \
       AZ_DB_USERNAME=$DB_USERNAME AZ_DB_PASSWORD=$DB_PASSWORD
docker compose -f compose.yaml -f compose.azure.yaml up -d app worker
```

Baja `APP_ENV` a `local` a propósito: con `production`, `AutenticadorDeDesarrollo`
se niega a existir y `RestringirPorIp` exige rangos configurados. Los dos
resguardos están bien; esto no es producción. Para volver al SQL Server local
basta `docker compose up -d app worker` sin el segundo archivo.

**El driver `sqlsrv` solo está en la imagen**, no en el PHP del host, así que
todo lo que toque SQL Server se corre con `docker compose exec app …`. Por lo
mismo `composer test` nunca puede ejercitar ese motor.

**Ojo al probar acentos con curl desde Git Bash en Windows:** los argumentos no
ASCII se convierten al codepage del sistema antes de llegar al ejecutable, y
`--data-urlencode 'q=Chávez'` termina mandando `q=Ch%e1vez`, que no es UTF-8 y
no encuentra nada. Escribir la URL ya codificada (`q=Ch%C3%A1vez`) sí funciona.
Es la herramienta, no la aplicación.

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

### Keycloak contra la base de Azure

`compose.azure.yaml` también levanta el Keycloak de verdad —la imagen propia,
contra su propia base— en lugar del `start-dev` con base embebida. Es el ensayo
de lo que va a Container Apps.

**Cada uno con su archivo y sus credenciales**, porque son usuarios distintos:
el de Keycloak es dueño solo de su base y no necesita ver la réplica de SAP.
Compose lee los dos archivos y no hace falta exportar nada al shell, así que el
comando es idéntico en PowerShell y en bash:

```sh
docker compose --env-file .env.azure --env-file .env.keycloak.azure \
  -f compose.yaml -f compose.azure.yaml up -d app worker keycloak
```

`.env.keycloak.azure` lleva `AZ_KC_HOST`, `AZ_KC_DATABASE`, `AZ_KC_USERNAME`,
`AZ_KC_PASSWORD`, `AZ_KC_ADMIN_PASSWORD` y `AZ_KC_CLIENT_SECRET`. Este último
tiene que ser **idéntico** al `KEYCLOAK_CLIENT_SECRET` de la app: Keycloak lo
mete en el realm al importar y la app lo usa para autenticarse. Si difieren,
`identidad:habilitar` falla con `IDENTIDAD_NO_DISPONIBLE`.

**El `iss` es el mismo en el ensayo y en la nube a propósito.** Si difiriera, un
token emitido acá no serviría allá y estaríamos probando otra cosa.

**Ojo con `volumes: []` en un override de compose: no borra nada.** Una lista
vacía deja intacta la del archivo base, así que `docker/keycloak/` seguiría
montado y taparía con el realm de desarrollo —secreto y contraseña incluidos—
al de producción que viaja dentro de la imagen. Se cancela con `volumes: !reset []`.
### El orden importa: integración antes de habilitar

`composer test:integration` espera que `p-8f2b1c40` tenga en Keycloak la
contraseña `contrasena-de-desarrollo` que trae el realm importado. Pero
`identidad:habilitar` **sobreescribe** esa contraseña con una aleatoria, y
`identidad:deshabilitar` le pone `enabled = false`. Después de correr
cualquiera de los dos, la batería de integración falla hasta reimportar el
realm:

```sh
docker compose down keycloak && docker compose up -d keycloak
```

O sea: integración contra un realm recién levantado, y el recorrido manual
después. Un fallo de `KeycloakTest` casi siempre es esto y no una regresión.

### La imagen de Keycloak para la nube

`docker/keycloak/Dockerfile` construye la imagen de Container Apps. `kc.sh build`
graba adentro el proveedor de SQL Server y la salud; el arranque usa
`--optimized` y no recompila. **No importa el realm de desarrollo a propósito**:
`agropartners-realm.json` trae el secreto del cliente y la contraseña del
usuario de ejemplo.

Se puede probar entera contra SQL Server local antes de tocar Azure:

```sh
docker build -f docker/keycloak/Dockerfile -t keycloak-agro docker/keycloak
docker compose up -d sqlserver
docker exec ms-maestros-sqlserver-1 /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "Agro.Local.2026" -C \
  -Q "IF DB_ID('keycloak') IS NULL CREATE DATABASE keycloak;"
```

Y después el contenedor en la red de compose, con la configuración de la nube.
La comprobación que importa es que el descubrimiento devuelva el `iss` fijo y
las demás URLs apuntando a por donde entró el pedido:

```sh
curl -s localhost:8082/realms/master/.well-known/openid-configuration
```

## Pendientes conocidos

- Keycloak arranca con `start-dev` en `compose.yaml`, con base embebida que se
  pierde al recrear el contenedor. La imagen de `docker/keycloak/Dockerfile` es
  la que lo reemplaza en la nube; el `compose.yaml` local todavía no la usa.
- Sin responder: si una persona de contacto puede estar registrada en socios de
  dos grupos distintos. Si el caso existe, hoy no falla con un error claro: le
  muestra al usuario la mitad de sus socios.
