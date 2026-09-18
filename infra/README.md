# Despliegue en Azure Container Apps

`main.bicep` crea el entorno y las tres aplicaciones. **No crea las bases de
datos**: ya existen, y una plantilla capaz de recrearlas es una plantilla capaz
de borrarlas.

## Lo que hace falta antes

- **Azure CLI.** Hoy no está instalada en la máquina de desarrollo.
- **Un registro de contenedores** (ACR) con las dos imágenes subidas.
- **Las dos bases creadas** en el servidor de Azure SQL, con un usuario propio
  cada una. El de Keycloak necesita poder **crear tablas**: en el primer
  arranque aplica 148 changesets que construyen el esquema.

## Construir y subir las imágenes

```sh
az acr login --name <registro>

docker build -f docker/Dockerfile -t <registro>.azurecr.io/ms-maestros:$(date +%Y-%m-%d) .
docker push <registro>.azurecr.io/ms-maestros:$(date +%Y-%m-%d)

docker build -f docker/keycloak/Dockerfile -t <registro>.azurecr.io/keycloak:$(date +%Y-%m-%d) docker/keycloak
docker push <registro>.azurecr.io/keycloak:$(date +%Y-%m-%d)
```

**Con etiqueta y no con `latest`.** Con `latest` no se sabe qué está corriendo
ni se puede volver atrás, que es lo único que importa a las tres de la mañana.

## Desplegar

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

Los parámetros marcados `@secure()` los va a pedir de forma interactiva y no
quedan en el historial del shell. **No pasarlos por línea de comandos.**

**Quien despliegue necesita poder crear asignaciones de rol** sobre el registro
—`Owner` o `User Access Administrator`—, porque la plantilla autoriza ella misma
a la identidad. Si no tiene ese permiso, hay que sacar el módulo `registro.bicep`
y asignar `AcrPull` a mano antes de desplegar, usando la salida
`identidadDeLasAplicaciones`.

## Por qué la identidad es asignada por el usuario

Con identidad **del sistema** el primer despliegue falla. Esa identidad nace
recién cuando el Container App se crea, y el contenedor intenta bajar su imagen
en ese mismo instante, sin permiso todavía sobre el registro. El error habla de
la imagen y no del permiso, así que se busca donde no es.

Creándola aparte se la autoriza antes de que exista algo que la necesite, y las
tres aplicaciones declaran que esperan a esa autorización.

## Después del primer despliegue

**Correr las migraciones**, que la aplicación no ejecuta sola a propósito —
migrar al arrancar, con varias réplicas, es una carrera:

```sh
az containerapp exec --name agro-ms-maestros --resource-group <grupo> \
  --command "php artisan migrate --force"
```

**El primer arranque de Keycloak tarda unos 196 segundos** creando su esquema.
Los reinicios posteriores, 44. Las sondas están dimensionadas para eso.

## Lo que no está resuelto acá

- **La consola de administración de Keycloak es inalcanzable**, por diseño: el
  emisor es un nombre que no resuelve. Se administra con `kcadm.sh`. Ver
  `docker/keycloak/README.md`.
- **El APIM tiene que apuntar a la salida `urlDeLaApp`**, y publicar solo
  `/v1/`. El back-office no gana nada pasando por un gateway de APIs.
- **`/admin/` queda alcanzable desde internet**, porque el APIM del tier
  Consumption no puede llegar a un ingress interno. Lo cubren la lista de
  rangos y la contraseña por operador — y, cuando exista, Entra.
