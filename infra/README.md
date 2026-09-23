# Crear el entorno en la nube

Al terminar estos pasos hay tres aplicaciones corriendo en un entorno de
Container Apps —`ms-maestros` pública, `keycloak` interna y `worker` sin
ingress— y una persona puede pedir su código, entrar y ver sus socios.

Los valores concretos viven en `main.bicep`. Este documento es el orden y lo
que no se deduce leyéndolo.

## Lo que no se resuelve acá

Pedir estas tres cosas primero: tienen cola propia y bloquean pasos enteros.

**Permisos en Azure.** Quien despliegue necesita crear recursos en el grupo y
**crear asignaciones de rol** sobre el registro (`Owner` o
`User Access Administrator`), porque la plantilla autoriza ella misma a la
identidad. Sin ese segundo permiso, el paso 3 falla.

**Permisos en Azure DevOps.** Encolar corridas, las dos conexiones de servicio
creadas, y poder crear entornos. La primera corrida que use una conexión
**se pausa pidiendo autorización**, y el mensaje habla de que no la encuentra.

**WhatsApp.** Número, token y una plantilla aprobada por Meta. Hasta que
lleguen, `WHATSAPP_DRIVER` se queda en `log` y el código sale por los logs en
vez de por el teléfono: alcanza para verificar el recorrido, no para personas
reales.

## Paso 1 — Las dos bases

Una para la réplica y otra para Keycloak, **en el mismo servidor y con un
usuario cada una**. El de Keycloak no necesita ver la réplica, y si se filtra
alcanza solo su esquema.

El usuario de Keycloak necesita **crear tablas**: su primer arranque aplica 148
changesets que construyen el esquema. Con `db_datareader` y `db_datawriter`
falla, y el error habla de permisos sobre un objeto, no de la base.

El firewall del servidor tiene que admitir a Container Apps.

**Terminado cuando** las dos bases existen y cada usuario puede conectarse a la
suya.

## Paso 2 — El registro y las imágenes

```sh
az acr login --name <registro>

docker build -f docker/Dockerfile -t <registro>.azurecr.io/ms-maestros:$(git rev-parse HEAD) .
docker push <registro>.azurecr.io/ms-maestros:$(git rev-parse HEAD)

docker build -f docker/keycloak/Dockerfile -t <registro>.azurecr.io/keycloak:$(git rev-parse HEAD) docker/keycloak
docker push <registro>.azurecr.io/keycloak:$(git rev-parse HEAD)
```

**Con el commit como etiqueta, nunca `latest`.** Es lo único que después deja
saber qué código está corriendo y volver a una versión anterior.

**Terminado cuando** `az acr repository show-tags` lista las dos etiquetas.

## Paso 3 — El entorno y las tres aplicaciones

```sh
az deployment group create \
  --resource-group <grupo> \
  --template-file infra/main.bicep \
  --parameters \
      imagenApp=<registro>.azurecr.io/ms-maestros:<etiqueta> \
      imagenKeycloak=<registro>.azurecr.io/keycloak:<etiqueta> \
      nombreDelRegistro=<registro> \
      servidorSql=<servidor>.database.windows.net \
      baseDeMaestros=<base> usuarioDeMaestros=<usuario> \
      usuarioDeKeycloak=<usuario> \
      rangosIpDelBackOffice=<la IP pública fija de la oficina>/32
```

Los parámetros `@secure()` se piden de forma interactiva. **Pasarlos por línea
de comandos los deja en el historial del shell.**

Dos de ellos comparten valor y tienen que ser idénticos: `secretoDelCliente` es
el que Keycloak mete en el realm al importarlo y el que la aplicación usa para
autenticarse. Si difieren, `identidad:habilitar` falla con
`IDENTIDAD_NO_DISPONIBLE`.

**`emisorDeTokens` se elige una sola vez.** Es el `iss` de cada token emitido;
cambiarlo invalida todos los que existan.

El primer arranque de Keycloak tarda unos **196 segundos** creando su esquema
—los reinicios, 44—, así que este paso no es rápido.

**Terminado cuando** la salida `urlDeLaApp` responde 200 en `/up` y los logs de
Keycloak dicen `Import finished successfully`.

## Paso 4 — El esquema y los datos

Las migraciones no corren al arrancar: con más de una réplica sería una
carrera.

```sh
az containerapp exec --name <app> --resource-group <grupo> \
  --command "php artisan migrate --force"

az containerapp exec --name <app> --resource-group <grupo> \
  --command "php artisan maestros:importar database/semillas/maestros-ejemplo.json"
```

**Terminado cuando** la importación informa los 43 registros de la semilla.

## Paso 5 — Comprobar el recorrido

Habilitar a la persona de la semilla y recorrer los tres endpoints:

```sh
az containerapp exec --name <app> --resource-group <grupo> \
  --command "php artisan identidad:habilitar p-8f2b1c40"
```

Después, contra `urlDeLaApp`: `POST /v1/auth/otp` con
`{"telefono":"70741828"}`, leer el código del log del worker, `POST
/v1/auth/login`, y `GET /v1/mi-cuenta` con el token.

**Terminado cuando** `mi-cuenta` responde 200 y el token trae
`iss: <emisorDeTokens>/realms/agropartners` y el cliente en `aud`.

## Paso 6 — El pipeline

`azure-pipelines.yml` construye la imagen y se la pone a la aplicación y al
worker, que tienen que existir antes de la primera corrida: el pipeline los
actualiza, no los crea. Los nombres están en las variables `appDeContenedor` y
`workerDeContenedor`. Keycloak no pasa por el pipeline. Para que
funcione hacen falta, en DevOps: crear el pipeline apuntando al archivo,
autorizar las dos conexiones de servicio, y una **política de rama** sobre
`main` que exija la corrida —el campo `pr:` del YAML no funciona en Azure
Repos, así que sin esa política las compuertas corren recién después del
merge—.

**Terminado cuando** una corrida completa las tres etapas y las revisiones
activas de la aplicación y del worker apuntan las dos a la etiqueta que publicó.

## Cuando algo falla

Los cuatro que ya nos costaron tiempo, con lo que el síntoma no dice:

| Síntoma | Causa |
|---|---|
| El despliegue falla bajando la imagen | La identidad no tiene `AcrPull` todavía. La plantilla lo resuelve; si se desplegó sin el módulo, asignarlo a mano |
| `IDENTIDAD_NO_DISPONIBLE` al habilitar | El secreto del cliente difiere entre Keycloak y la aplicación, o Keycloak está frío y el pedido superó el timeout |
| `TOKEN_INVALIDO` con un token recién emitido | `KEYCLOAK_ISSUER` no coincide con el `KC_HOSTNAME` de Keycloak |
| `/admin/` responde error al primer pedido | `BACKOFFICE_RANGOS_IP` vacío con `APP_ENV=production`. Es el resguardo funcionando |
| La secuencia de consola no muestra nada de la aplicación | Falta `LOG_CHANNEL=stderr`. Sin eso Laravel escribe en un archivo adentro del contenedor: no se ve, no llega a Log Analytics, y se pierde en cada reinicio |
| El worker existe y no procesa nada | Sin ingress no hay disparador de escala: con el mínimo en 0 nunca arranca. Mínimo 1 |

Keycloak en Container Apps queda con ingress interno: **su consola web no es
alcanzable**, por diseño. Se administra con `kcadm.sh`, y está explicado en
`docker/keycloak/README.md`.

## Lo que este documento no cubre

El APIM delante de `/v1/`, y `AutenticadorEntra` para el back-office. Ninguno
hace falta para que el entorno funcione.

Vale saber por qué la aplicación queda pública: el APIM del tier Consumption no
puede alcanzar un ingress interno. Eso deja `/admin/` accesible desde internet,
y lo que lo cubre son los rangos de IP del paso 3 y la contraseña por operador.
