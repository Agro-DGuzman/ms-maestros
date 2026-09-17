# Realm `agropartners`

`agropartners-realm.json` se importa solo al levantar el contenedor (`--import-realm`).

## Por qué el realm redefine el perfil de usuario

El *declarative user profile* de Keycloak (activo por defecto desde la 24) marca
`email`, `firstName` y `lastName` como **requeridos** para todo usuario con rol
`user`. Las identidades de este servicio no tienen ninguno de los tres: el
`username` **es** el identificador de la persona (`p-8f2b1c40`), el socio nunca
conoce su credencial y no hay correo asociado.

Con el perfil por defecto, un usuario creado así queda "incompleto" y Keycloak
rechaza el ingreso con:

```
{"error":"invalid_grant","error_description":"Account is not fully set up"}
```

...aunque el usuario esté habilitado, sin `requiredActions` y con su contraseña
bien cargada. El síntoma es engañoso porque no menciona los atributos.

Por eso el realm redefine `org.keycloak.userprofile.UserProfileProvider` dejando
esos tres atributos sin `required`. **Esta misma configuración hay que aplicarla
en el Keycloak real**: sin ella, `identidad:habilitar` crea usuarios que no
pueden iniciar sesión.

## Rol de la cuenta de servicio

La cuenta de servicio de `ms-maestros` lleva `manage-users` y `view-users` del
cliente `realm-management`. Sin esos roles, `KeycloakAdmin` no puede dar de alta
ni buscar usuarios y la habilitación falla con `IDENTIDAD_NO_DISPONIBLE`.

## El realm de producción

`agropartners-realm.produccion.json` es el que va a la nube. Se diferencia del
de desarrollo en cuatro cosas, y ninguna es cosmética:

- **No trae al usuario `p-8f2b1c40`.** Ese usuario existe para probar en local y
  su contraseña está escrita en el archivo.
- **El secreto del cliente es `${MS_MAESTROS_CLIENT_SECRET}`.** El import de
  Keycloak sustituye `${VARIABLE}` desde el entorno, así que el secreto real
  llega como variable del Container App y nunca se versiona.
- **`sslRequired` es `external`** en vez de `none`.
- **Lleva un audience mapper** que pone `ms-maestros` en `aud`. El verificador
  acepta igual por `azp`, pero un gateway que valide audiencias necesita que
  `aud` nombre al cliente.

Lo que **sí** se conserva del de desarrollo es lo que el resto de este archivo
explica: el perfil de usuario redefinido y los roles de la cuenta de servicio.
Sin cualquiera de los dos, el realm arranca pero `identidad:habilitar` produce
usuarios que no pueden entrar.

### Probarlo entero antes de Azure

Se importa en un Keycloak local contra SQL Server y se ejercita con el código de
la aplicación: crear una persona por el Admin API, emitir su token y verificarlo.
Ese recorrido —y no el del usuario del realm— es el que hace de verdad la
aplicación, y es el que destapó que `aud: account` rechazaba a toda persona real.

### Al re-serializar el JSON con PHP

`json_decode($s, true)` convierte los `{}` vacíos en arrays y `json_encode` los
escribe como `[]`. El import falla con un error de Jackson sobre `subComponents`
que no menciona la causa. Hay que decodificar como objetos.

## La consola de administración no funciona con el emisor que no resuelve

`KC_HOSTNAME` no es solo el `iss`: con él Keycloak arma **todas** las URLs que
publica, incluidas las que usa su propia consola. Como el emisor es un nombre
que a propósito no resuelve, el navegador muere con `DNS_PROBE_FINISHED_NXDOMAIN`
y la consola muestra las claves de traducción crudas (`somethingWentWrong`),
porque falla antes de cargar el idioma.

`KC_HOSTNAME_ADMIN` **no alcanza**. Arregla dos de las cuatro URLs que la
consola necesita; las otras dos siguen apuntando al emisor:

```
serverBaseUrl : https://identidad.agropartners.com.bo   <- no resuelve
authServerUrl : https://identidad.agropartners.com.bo   <- no resuelve
adminBaseUrl  : http://localhost:8081                   <- lo arregla KC_HOSTNAME_ADMIN
authUrl       : http://localhost:8081                   <- idem
```

Se ven con `curl -s localhost:8081/admin/master/console/ | grep authServerUrl`.

Tampoco se arregla con una entrada en `hosts`: esas URLs son `https` y Keycloak
sirve HTTP plano detrás del proxy.

**Para administrar se usa `kcadm.sh`, que viene en la imagen.** Corre dentro del
contenedor contra `localhost:8080`, así que nunca pasa por el hostname:

```sh
docker exec -it ms-maestros-keycloak-1 /opt/keycloak/bin/kcadm.sh \
  config credentials --server http://localhost:8080 --realm master --user admin
docker exec ms-maestros-keycloak-1 /opt/keycloak/bin/kcadm.sh \
  get users -r agropartners --fields username,enabled
```

No imprime nada cuando el login sale bien. Habla solo si algo falla.

Si hace falta la consola gráfica, la salida limpia es un contenedor aparte y
temporal contra la misma base, con `KC_HOSTNAME=http://localhost:8082`. Los
tokens no se enteran: el hostname es configuración del servidor, no del realm.
