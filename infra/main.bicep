// Infraestructura de ms-maestros en Azure Container Apps.
//
// Un solo entorno con tres aplicaciones:
//
//   ms-maestros  externo   /v1/ detrás del APIM, y /admin/ del back-office
//   keycloak     interno   nadie lo alcanza desde afuera
//   worker       sin ingress
//
// Keycloak va interno porque nadie de afuera necesita alcanzarlo: el teléfono
// le pega a /v1/ de ms-maestros y el resto ocurre de servidor a servidor. La
// app va externa porque el APIM del tier Consumption no puede alcanzar un
// ingress interno — no admite integración con red virtual.
//
// Las bases de datos NO se crean acá. Ya existen, y una plantilla capaz de
// recrearlas es una plantilla capaz de borrarlas.

@description('Región de todos los recursos.')
param ubicacion string = resourceGroup().location

@description('Prefijo de los nombres. Cambiarlo da un entorno nuevo y paralelo.')
param prefijo string = 'agro'

@description('Imagen de la aplicación con etiqueta. Ej: miacr.azurecr.io/ms-maestros:2026-09-18')
param imagenApp string

@description('Imagen de Keycloak con etiqueta.')
param imagenKeycloak string

@description('Registro del que se bajan las imágenes. Ej: miacr.azurecr.io')
param servidorDelRegistro string

@description('Servidor de Azure SQL. Ej: agro.database.windows.net')
param servidorSql string

@description('Base de la réplica de SAP.')
param baseDeMaestros string

@description('Base de Keycloak, separada y con su propio usuario.')
param baseDeKeycloak string = 'keycloak'

param usuarioDeMaestros string
param usuarioDeKeycloak string

@description('El `iss` de cada token. Cambiarlo invalida todo lo ya emitido.')
param emisorDeTokens string = 'https://identidad.agropartners.com.bo'

@description('Rangos CIDR desde los que se admite /admin/. Vacío hace fallar el back-office a propósito.')
param rangosIpDelBackOffice string

@secure()
param appKey string

@secure()
param contrasenaDeMaestros string

@secure()
param contrasenaDeKeycloak string

@secure()
@description('Compartido: Keycloak lo mete en el realm y la app lo usa para autenticarse.')
param secretoDelCliente string

@secure()
param contrasenaDeAdminDeKeycloak string

@secure()
@description('Operadores del back-office: correo|hash|Nombre separados por punto y coma.')
param operadoresDelBackOffice string

// Los logs van a Log Analytics porque Container Apps no guarda historial por sí
// mismo: sin esto, un contenedor que se reinicia se lleva la razón con él.
resource logs 'Microsoft.OperationalInsights/workspaces@2022-10-01' = {
  name: '${prefijo}-logs'
  location: ubicacion
  properties: {
    sku: {
      name: 'PerGB2018'
    }
    retentionInDays: 30
  }
}

resource entorno 'Microsoft.App/managedEnvironments@2024-03-01' = {
  name: '${prefijo}-entorno'
  location: ubicacion
  properties: {
    appLogsConfiguration: {
      destination: 'log-analytics'
      logAnalyticsConfiguration: {
        customerId: logs.properties.customerId
        sharedKey: logs.listKeys().primarySharedKey
      }
    }
  }
}

resource keycloak 'Microsoft.App/containerApps@2024-03-01' = {
  name: '${prefijo}-keycloak'
  location: ubicacion
  identity: {
    type: 'SystemAssigned'
  }
  properties: {
    environmentId: entorno.id
    configuration: {
      // Interno: solo lo alcanzan las otras aplicaciones del mismo entorno.
      ingress: {
        external: false
        targetPort: 8080
        transport: 'http'
      }
      registries: [
        {
          server: servidorDelRegistro
          identity: 'system'
        }
      ]
      secrets: [
        {
          name: 'db-password'
          value: contrasenaDeKeycloak
        }
        {
          name: 'admin-password'
          value: contrasenaDeAdminDeKeycloak
        }
        {
          name: 'client-secret'
          value: secretoDelCliente
        }
      ]
    }
    template: {
      containers: [
        {
          name: 'keycloak'
          image: imagenKeycloak
          resources: {
            cpu: json('1.0')
            memory: '2Gi'
          }
          env: [
            {
              name: 'KC_DB_URL'
              value: 'jdbc:sqlserver://${servidorSql}:1433;databaseName=${baseDeKeycloak};encrypt=true;trustServerCertificate=false;loginTimeout=30'
            }
            {
              name: 'KC_DB_USERNAME'
              value: usuarioDeKeycloak
            }
            {
              name: 'KC_DB_PASSWORD'
              secretRef: 'db-password'
            }
            // El emisor que viaja en cada token. Es un nombre propio y estable
            // que no necesita resolver a ningún lado: a Keycloak se lo alcanza
            // por su FQDN interno.
            {
              name: 'KC_HOSTNAME'
              value: emisorDeTokens
            }
            {
              name: 'KC_HOSTNAME_BACKCHANNEL_DYNAMIC'
              value: 'true'
            }
            {
              name: 'KC_HTTP_ENABLED'
              value: 'true'
            }
            {
              name: 'KC_PROXY_HEADERS'
              value: 'xforwarded'
            }
            {
              name: 'KC_BOOTSTRAP_ADMIN_USERNAME'
              value: 'admin'
            }
            {
              name: 'KC_BOOTSTRAP_ADMIN_PASSWORD'
              secretRef: 'admin-password'
            }
            {
              name: 'MS_MAESTROS_CLIENT_SECRET'
              secretRef: 'client-secret'
            }
          ]
          probes: [
            // Medido contra esta misma base: 44 segundos con el esquema ya
            // creado, 196 la primera vez. El umbral tolera el primer arranque;
            // si no, ACA mata el contenedor antes de que termine y entra en un
            // bucle de reinicios que parece otra cosa.
            {
              type: 'Startup'
              httpGet: {
                path: '/health/started'
                port: 9000
              }
              periodSeconds: 10
              failureThreshold: 30
            }
            {
              type: 'Readiness'
              httpGet: {
                path: '/health/ready'
                port: 9000
              }
              periodSeconds: 10
            }
            {
              type: 'Liveness'
              httpGet: {
                path: '/health/live'
                port: 9000
              }
              periodSeconds: 30
            }
          ]
        }
      ]
      // Sin escalar a cero: arrancar cuesta 44 segundos y el primero que entre
      // los pagaría. Tampoco más de una réplica todavía — con varias hay que
      // resolver antes cómo comparten las sesiones.
      scale: {
        minReplicas: 1
        maxReplicas: 1
      }
    }
  }
}

resource app 'Microsoft.App/containerApps@2024-03-01' = {
  name: '${prefijo}-ms-maestros'
  location: ubicacion
  identity: {
    type: 'SystemAssigned'
  }
  properties: {
    environmentId: entorno.id
    configuration: {
      ingress: {
        external: true
        targetPort: 8080
        transport: 'http'
      }
      registries: [
        {
          server: servidorDelRegistro
          identity: 'system'
        }
      ]
      secrets: [
        {
          name: 'app-key'
          value: appKey
        }
        {
          name: 'db-password'
          value: contrasenaDeMaestros
        }
        {
          name: 'client-secret'
          value: secretoDelCliente
        }
        {
          name: 'operadores'
          value: operadoresDelBackOffice
        }
      ]
    }
    template: {
      containers: [
        {
          name: 'ms-maestros'
          image: imagenApp
          resources: {
            cpu: json('0.5')
            memory: '1Gi'
          }
          env: [
            {
              name: 'APP_ENV'
              value: 'production'
            }
            {
              name: 'APP_DEBUG'
              value: 'false'
            }
            {
              name: 'APP_KEY'
              secretRef: 'app-key'
            }
            {
              name: 'DB_CONNECTION'
              value: 'sqlsrv'
            }
            {
              name: 'DB_HOST'
              value: servidorSql
            }
            {
              name: 'DB_PORT'
              value: '1433'
            }
            {
              name: 'DB_DATABASE'
              value: baseDeMaestros
            }
            {
              name: 'DB_USERNAME'
              value: usuarioDeMaestros
            }
            {
              name: 'DB_PASSWORD'
              secretRef: 'db-password'
            }
            {
              name: 'DB_ENCRYPT'
              value: 'yes'
            }
            {
              name: 'DB_TRUST_SERVER_CERTIFICATE'
              value: 'false'
            }
            // No puede ser `sync`: el envío del desafío se encola justamente
            // para que /auth/otp tarde lo mismo exista o no el número.
            {
              name: 'QUEUE_CONNECTION'
              value: 'database'
            }
            {
              name: 'CACHE_STORE'
              value: 'database'
            }
            {
              name: 'SESSION_DRIVER'
              value: 'database'
            }
            // Dónde vive Keycloak y quién dice ser. Son distintos a propósito.
            //
            // Por nombre corto y HTTP plano: es la forma que Container Apps
            // define para hablar entre aplicaciones del mismo entorno, y no
            // involucra certificados. Con el FQDN por HTTPS habría que confiar
            // en la CA interna de ACA desde el contenedor, y si no está en su
            // almacén el fallo es de verificación TLS — difícil de leer.
            {
              name: 'KEYCLOAK_BASE_URL'
              value: 'http://${keycloak.name}'
            }
            {
              name: 'KEYCLOAK_ISSUER'
              value: emisorDeTokens
            }
            {
              name: 'KEYCLOAK_REALM'
              value: 'agropartners'
            }
            {
              name: 'KEYCLOAK_CLIENT_ID'
              value: 'ms-maestros'
            }
            {
              name: 'KEYCLOAK_CLIENT_SECRET'
              secretRef: 'client-secret'
            }
            // Cinco segundos quedaban en el borde: emitir un token tarda entre 2
            // y 5,3 segundos medidos contra esta misma base. Con el valor viejo
            // el login fallaba de forma intermitente.
            {
              name: 'KEYCLOAK_TIMEOUT'
              value: '15'
            }
            // Hay un ingress adelante: la IP de la persona viaja en la cabecera.
            // De esto dependen el filtro de rangos, el freno de intentos del
            // ingreso, y la dirección que la bitácora guarda para siempre.
            {
              name: 'DETRAS_DE_PROXY'
              value: 'true'
            }
            {
              name: 'BACKOFFICE_AUTENTICADOR'
              value: 'contrasena'
            }
            {
              name: 'BACKOFFICE_OPERADORES'
              secretRef: 'operadores'
            }
            {
              name: 'BACKOFFICE_RANGOS_IP'
              value: rangosIpDelBackOffice
            }
          ]
          probes: [
            {
              type: 'Readiness'
              httpGet: {
                path: '/up'
                port: 8080
              }
              periodSeconds: 10
            }
            {
              type: 'Liveness'
              httpGet: {
                path: '/up'
                port: 8080
              }
              periodSeconds: 30
            }
          ]
        }
      ]
      scale: {
        minReplicas: 1
        maxReplicas: 3
      }
    }
  }
}

resource worker 'Microsoft.App/containerApps@2024-03-01' = {
  name: '${prefijo}-worker'
  location: ubicacion
  identity: {
    type: 'SystemAssigned'
  }
  properties: {
    environmentId: entorno.id
    configuration: {
      // Sin ingress: no escucha en ningún puerto, levanta trabajos de la cola.
      registries: [
        {
          server: servidorDelRegistro
          identity: 'system'
        }
      ]
      secrets: [
        {
          name: 'app-key'
          value: appKey
        }
        {
          name: 'db-password'
          value: contrasenaDeMaestros
        }
        {
          name: 'client-secret'
          value: secretoDelCliente
        }
      ]
    }
    template: {
      containers: [
        {
          name: 'worker'
          image: imagenApp
          command: [
            'php'
          ]
          args: [
            'artisan'
            'queue:work'
            '--tries=3'
            '--max-time=3600'
          ]
          resources: {
            cpu: json('0.5')
            memory: '1Gi'
          }
          env: [
            {
              name: 'APP_ENV'
              value: 'production'
            }
            {
              name: 'APP_DEBUG'
              value: 'false'
            }
            {
              name: 'APP_KEY'
              secretRef: 'app-key'
            }
            {
              name: 'DB_CONNECTION'
              value: 'sqlsrv'
            }
            {
              name: 'DB_HOST'
              value: servidorSql
            }
            {
              name: 'DB_PORT'
              value: '1433'
            }
            {
              name: 'DB_DATABASE'
              value: baseDeMaestros
            }
            {
              name: 'DB_USERNAME'
              value: usuarioDeMaestros
            }
            {
              name: 'DB_PASSWORD'
              secretRef: 'db-password'
            }
            {
              name: 'DB_ENCRYPT'
              value: 'yes'
            }
            {
              name: 'DB_TRUST_SERVER_CERTIFICATE'
              value: 'false'
            }
            {
              name: 'QUEUE_CONNECTION'
              value: 'database'
            }
            {
              name: 'CACHE_STORE'
              value: 'database'
            }
            {
              name: 'KEYCLOAK_BASE_URL'
              value: 'http://${keycloak.name}'
            }
            {
              name: 'KEYCLOAK_ISSUER'
              value: emisorDeTokens
            }
            {
              name: 'KEYCLOAK_REALM'
              value: 'agropartners'
            }
            {
              name: 'KEYCLOAK_CLIENT_ID'
              value: 'ms-maestros'
            }
            {
              name: 'KEYCLOAK_CLIENT_SECRET'
              secretRef: 'client-secret'
            }
            // Cinco segundos quedaban en el borde: emitir un token tarda entre 2
            // y 5,3 segundos medidos contra esta misma base. Con el valor viejo
            // el login fallaba de forma intermitente.
            {
              name: 'KEYCLOAK_TIMEOUT'
              value: '15'
            }
          ]
        }
      ]
      // Sin ingress no hay escalado por HTTP: una réplica siempre viva, porque
      // si no el desafío se encola y no lo levanta nadie.
      scale: {
        minReplicas: 1
        maxReplicas: 1
      }
    }
  }
}

@description('Dónde queda la aplicación. Es lo que hay que apuntar desde el APIM.')
output urlDeLaApp string = 'https://${app.properties.configuration.ingress.fqdn}'

@description('FQDN interno de Keycloak. No resuelve desde afuera del entorno.')
output urlInternaDeKeycloak string = 'https://${keycloak.properties.configuration.ingress.fqdn}'

@description('Identidades que hay que autorizar con AcrPull sobre el registro.')
output identidadesParaElRegistro array = [
  app.identity.principalId
  worker.identity.principalId
  keycloak.identity.principalId
]
