# Imagen de Keycloak para Azure Container Apps.
#
# Se construye en dos etapas porque `kc.sh build` es lo que compila adentro de
# la imagen el proveedor de base de datos y las funciones habilitadas. Hecho
# eso, el arranque usa `--optimized` y no vuelve a construir nada: sin esto
# cada arranque recompila, que en un contenedor que escala es tiempo perdido en
# cada replica.
#
# Lleva adentro `agropartners-realm.produccion.json`, que no tiene secretos: el
# del cliente es un `${MS_MAESTROS_CLIENT_SECRET}` que el import sustituye desde
# el entorno. NO lleva `agropartners-realm.json`, el de desarrollo, que si trae
# el secreto y la contrasena del usuario de ejemplo escritos.
#
# `--import-realm` en cada arranque es seguro: Keycloak importa con
# OVERWRITE_EXISTING solo sobre una base vacia, y al reiniciar con el realm ya
# creado pasa a IGNORE_EXISTING y lo saltea. Verificado creando una persona,
# reiniciando el contenedor y comprobando que sigue ahi.

FROM quay.io/keycloak/keycloak:26.0 AS constructor

# Opciones de construccion: quedan grabadas en la imagen y no se pueden cambiar
# por variable de entorno despues.
ENV KC_DB=mssql
ENV KC_HEALTH_ENABLED=true

RUN /opt/keycloak/bin/kc.sh build

FROM quay.io/keycloak/keycloak:26.0

COPY --from=constructor /opt/keycloak/ /opt/keycloak/
COPY agropartners-realm.produccion.json /opt/keycloak/data/import/agropartners-realm.json

# Todo lo de abajo es configuracion de ejecucion y se define en el Container
# App, no aca, porque cambia por entorno y porque incluye credenciales:
#
#   KC_HOSTNAME                      https://identidad.agropartners.com.bo
#   KC_HOSTNAME_BACKCHANNEL_DYNAMIC  true
#   KC_HTTP_ENABLED                  true
#   KC_PROXY_HEADERS                 xforwarded
#   KC_DB_URL                        jdbc:sqlserver://...;databaseName=keycloak
#   KC_DB_USERNAME / KC_DB_PASSWORD  del secreto del Container App
#
# `KC_HOSTNAME` tiene que ser una URL con esquema: es requisito de
# `hostname-backchannel-dynamic`, que es lo que deja que el `iss` sea un nombre
# estable mientras los pedidos entran por el FQDN interno del entorno.

ENTRYPOINT ["/opt/keycloak/bin/kc.sh", "start", "--optimized", "--import-realm"]
