/* =====================================================================
   Configuración de la App - SQL Server / Azure SQL
   ---------------------------------------------------------------------
   Lo que la App lee del servidor en vez de llevarlo compilado
   (spec 2026-11-13-configuracion-de-la-app-en-la-base-design.md):

     GET /bancos                          maestros.cuentas_bancarias
                                          + titular.* del diccionario
     GET /contactos/atencion-al-cliente   atencion.*
     GET /version                         version.* y aviso.*

   - No es réplica de SAP ni lo carga una pantalla: los valores se cargan
     a mano con un INSERT (database/sql/maestros-configuracion-ejemplo.sql,
     completado fuera del repo). Por eso las reglas están acá: un error de
     tipeo tiene que fallar en el INSERT y no cuando la App pide el dato.
   - El diccionario acepta solo las claves que el código lee. La lista de
     CK_configuracion_app_clave es la de ConfiguracionDesdeLaBase::CLAVES, y
     ConfiguracionDesdeLaBaseTest exige que sean iguales: si se agrega una,
     se agrega en los dos lados, con una migración que vuelva a crear el
     CHECK (los CHECK quedan congelados cuando la tabla existe).
   - El código valida igual al leer (Version::desde, el celular): los CHECK
     son la red del INSERT a mano, y SQLite no los tiene. La copia de SQLite
     vive en database/migrations/maestros/2026_11_13_000100_crear_configuracion_de_la_app.php.
   - Idempotente: la migración lo ejecuta, y correrlo de nuevo no cambia nada.

   Cada uno de estos INSERT tiene que fallar (comprobación a mano):

     INSERT INTO maestros.configuracion_app VALUES ('atencion.whatsapp', '67701468');
     INSERT INTO maestros.configuracion_app VALUES ('titular.nit', N'   ');
     INSERT INTO maestros.configuracion_app VALUES ('atencion.telefono', N'33445566');
     INSERT INTO maestros.configuracion_app VALUES ('version.android.minima', N'1.8');
     INSERT INTO maestros.configuracion_app VALUES ('version.ios.tienda', N'http://apps.apple.com/x');
     INSERT INTO maestros.cuentas_bancarias (banco, numero_cuenta, orden) VALUES (N'Banco BISA', 'CTA-701', 1);
     INSERT INTO maestros.cuentas_bancarias (banco, numero_cuenta, orden) VALUES (N'   ', '701', 1);
     -- con una cuenta ya cargada en el orden 1:
     INSERT INTO maestros.cuentas_bancarias (banco, numero_cuenta, orden) VALUES (N'Banco Unión', '089489', 1);
   ===================================================================== */

IF SCHEMA_ID('maestros') IS NULL EXEC('CREATE SCHEMA maestros');
GO

IF OBJECT_ID('maestros.cuentas_bancarias') IS NULL
CREATE TABLE maestros.cuentas_bancarias (
    id             INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_cuentas_bancarias PRIMARY KEY,
    banco          NVARCHAR(100) NOT NULL,
    numero_cuenta  VARCHAR(50)   NOT NULL,
    -- La App muestra las cuentas en este orden; sin él, el orden lo decidiría
    -- el plan de la consulta.
    orden          INT           NOT NULL CONSTRAINT UQ_cuentas_bancarias_orden UNIQUE,
    CONSTRAINT UQ_cuentas_bancarias_cuenta UNIQUE (banco, numero_cuenta),
    -- El banco se le muestra al socio tal cual. Igual que en
    -- maestros-propiedades.sql: LEN ignora los espacios finales, DATALENGTH
    -- contra RTRIM los detecta, y el tab, el salto de línea o el espacio duro
    -- pegado desde Excel se rechazan en cualquier posición.
    CONSTRAINT CK_cuentas_bancarias_banco CHECK (LEN(banco) > 0 AND banco NOT LIKE N' %'
        AND DATALENGTH(banco) = DATALENGTH(RTRIM(banco))
        AND banco COLLATE Latin1_General_BIN2
            NOT LIKE N'%[' + NCHAR(9) + NCHAR(10) + NCHAR(13) + NCHAR(160) + N']%'),
    -- Solo dígitos y guiones: el socio copia este número para transferir.
    -- En binario, para que la colación no tome un ² por un dígito.
    CONSTRAINT CK_cuentas_bancarias_numero CHECK (
        numero_cuenta COLLATE Latin1_General_BIN2 NOT LIKE '%[^0-9-]%'
        AND numero_cuenta COLLATE Latin1_General_BIN2 LIKE '%[0-9]%')
);
GO

IF OBJECT_ID('maestros.configuracion_app') IS NULL
CREATE TABLE maestros.configuracion_app (
    clave  VARCHAR(60)   NOT NULL CONSTRAINT PK_configuracion_app PRIMARY KEY,
    valor  NVARCHAR(500) NOT NULL,
    CONSTRAINT CK_configuracion_app_clave CHECK (clave IN (
        'titular.nit',
        'titular.razon_social',
        'atencion.telefono',
        'atencion.area',
        'version.android.minima',
        'version.android.recomendada',
        'version.android.tienda',
        'version.ios.minima',
        'version.ios.recomendada',
        'version.ios.tienda',
        'aviso.obligatoria.titulo',
        'aviso.obligatoria.mensaje',
        'aviso.sugerida.titulo',
        'aviso.sugerida.mensaje'
    )),
    -- LEN ignora los espacios finales: un valor de solo espacios da 0.
    CONSTRAINT CK_configuracion_app_valor CHECK (LEN(valor) > 0),
    -- WhatsApp no abre chats con un fijo. El número va sin 591: el código lo
    -- acepta con o sin, pero acá se carga de una sola forma. LIKE sobre
    -- NVARCHAR toma en cuenta los espacios finales, así que también los frena.
    CONSTRAINT CK_configuracion_app_telefono CHECK (clave <> 'atencion.telefono'
        OR valor COLLATE Latin1_General_BIN2 LIKE N'[67][0-9][0-9][0-9][0-9][0-9][0-9][0-9]'),
    -- MAYOR.MENOR.PARCHE, como Version::desde: una versión mal escrita haría
    -- que /version responda 500 hasta que alguien la corrija.
    CONSTRAINT CK_configuracion_app_version CHECK (clave NOT IN (
            'version.android.minima', 'version.android.recomendada',
            'version.ios.minima', 'version.ios.recomendada')
        OR (valor COLLATE Latin1_General_BIN2 NOT LIKE N'%[^0-9.]%'
            AND valor COLLATE Latin1_General_BIN2 LIKE N'[0-9]%.[0-9]%.[0-9]%'
            AND valor NOT LIKE N'%.%.%.%')),
    CONSTRAINT CK_configuracion_app_tienda CHECK (clave NOT IN ('version.android.tienda', 'version.ios.tienda')
        OR valor LIKE N'https://_%')
);
GO
