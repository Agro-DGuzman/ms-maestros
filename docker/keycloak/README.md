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
