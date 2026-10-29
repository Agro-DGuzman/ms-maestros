/* =====================================================================
   Propiedades del socio - SQL Server / Azure SQL
   ---------------------------------------------------------------------
   La forma la fija el contrato (contrato/agropartners-api-v1.yaml):

     GET /socios/{cardCode}/propiedades   items: [{ id, nombre }]

   - No es réplica de SAP: las propiedades salen de una conciliación que
     hace Agropartners, y se cargan con INSERT sobre esta estructura. Por
     eso no pasan por vigenteDesde ni por el importador de maestros.
   - Este script es solo la estructura, e idempotente: la migración lo
     ejecuta, y correrlo de nuevo no cambia nada.
   - id_propiedad es el `id` que viaja a ms-comercial en
     POST /solicitudes-visita y que guardan los reportes de campo:
     NUNCA se reusa ni se cambia.
   - nombre es el mismo texto que ReporteCampo.propiedad: se toma de la
     fuente de esos reportes y no se renombra a la ligera.
   - Sin clave foránea a maestros.socios: esa tabla se reimporta desde SAP
     y la clave podría trabarla. La consulta del final encuentra lo que
     quedó colgado.
   - Si se cambia la tabla acá, se cambia también la copia de SQLite en
     database/migrations/maestros/2026_09_29_000100_crear_propiedades.php
     y en las migraciones que la amplían (2026_10_30_000200).
   ===================================================================== */

IF SCHEMA_ID('maestros') IS NULL EXEC('CREATE SCHEMA maestros');
GO

IF OBJECT_ID('maestros.propiedad') IS NULL
CREATE TABLE maestros.propiedad (
    id_propiedad     NVARCHAR(50)  NOT NULL CONSTRAINT PK_propiedad PRIMARY KEY,
    nombre           NVARCHAR(200) NOT NULL,
    codigo_de_socio  NVARCHAR(15)  NOT NULL,
    activa           BIT           NOT NULL CONSTRAINT DF_propiedad_activa DEFAULT (1),
    creado_en        DATETIME2(0)  NOT NULL CONSTRAINT DF_propiedad_creado DEFAULT SYSUTCDATETIME(),
    actualizado_en   DATETIME2(0)  NOT NULL CONSTRAINT DF_propiedad_actualizado DEFAULT SYSUTCDATETIME(),
    -- Un espacio de más en el id viajaría distinto a ms-comercial, y en el
    -- código de socio dejaría la propiedad invisible. LEN ignora los espacios
    -- finales: DATALENGTH contra RTRIM los detecta.
    --
    -- RTRIM y LIKE N' %' solo ven el espacio común. Un tab, el CR que deja un
    -- CSV de Windows o el espacio duro que se pega desde Excel pasarían igual,
    -- así que se rechazan en cualquier posición, comparando en binario para
    -- que la colación no los confunda con un espacio. Estos CHECK quedan
    -- congelados cuando la tabla existe: cambiarlos después exige un bloque
    -- que los borre y los vuelva a crear.
    CONSTRAINT CK_propiedad_id CHECK (LEN(id_propiedad) > 0 AND id_propiedad NOT LIKE N' %'
        AND DATALENGTH(id_propiedad) = DATALENGTH(RTRIM(id_propiedad))
        AND id_propiedad COLLATE Latin1_General_BIN2
            NOT LIKE N'%[' + NCHAR(9) + NCHAR(10) + NCHAR(13) + NCHAR(160) + N']%'),
    CONSTRAINT CK_propiedad_nombre CHECK (LEN(nombre) > 0 AND nombre NOT LIKE N' %'
        AND DATALENGTH(nombre) = DATALENGTH(RTRIM(nombre))
        AND nombre COLLATE Latin1_General_BIN2
            NOT LIKE N'%[' + NCHAR(9) + NCHAR(10) + NCHAR(13) + NCHAR(160) + N']%'),
    CONSTRAINT CK_propiedad_socio CHECK (LEN(codigo_de_socio) > 0 AND codigo_de_socio NOT LIKE N' %'
        AND DATALENGTH(codigo_de_socio) = DATALENGTH(RTRIM(codigo_de_socio))
        AND codigo_de_socio COLLATE Latin1_General_BIN2
            NOT LIKE N'%[' + NCHAR(9) + NCHAR(10) + NCHAR(13) + NCHAR(160) + N']%')
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_propiedad_socio'
               AND object_id = OBJECT_ID('maestros.propiedad'))
CREATE INDEX IX_propiedad_socio ON maestros.propiedad (codigo_de_socio) INCLUDE (activa, nombre);
GO

/* ---------------------------------------------------------------------
   Las propiedades por eventos de ms-comercial (spec 2026-10-29, EM8-EM10).
   `estado` reemplaza a `activa`, y `vigente_desde` ordena las fotos: nula
   en las que nunca llegaron por un evento. Cada bloque es idempotente: el
   script se vuelve a correr entero en cada migración que lo toca.
   --------------------------------------------------------------------- */

IF COL_LENGTH('maestros.propiedad', 'estado') IS NULL
ALTER TABLE maestros.propiedad
    ADD estado NVARCHAR(10) NOT NULL CONSTRAINT DF_propiedad_estado DEFAULT (N'activo');
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_propiedad_estado'
               AND parent_object_id = OBJECT_ID('maestros.propiedad'))
ALTER TABLE maestros.propiedad
    ADD CONSTRAINT CK_propiedad_estado CHECK (estado IN (N'activo', N'inactivo'));
GO

-- Con 6 decimales y en UTC: dos cambios de Appgronomy en el mismo segundo
-- tienen que ordenarse.
IF COL_LENGTH('maestros.propiedad', 'vigente_desde') IS NULL
ALTER TABLE maestros.propiedad ADD vigente_desde DATETIME2(6) NULL;
GO

-- Solo las que ningún evento tocó: después, el estado lo decide el evento y
-- `activa` queda vieja. Dentro de EXEC porque, cuando se borre `activa`, el
-- lote ya no compilaría con la columna nombrada directamente.
IF COL_LENGTH('maestros.propiedad', 'activa') IS NOT NULL
EXEC(N'UPDATE maestros.propiedad SET estado = N''inactivo'' WHERE activa = 0 AND vigente_desde IS NULL');
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_propiedad_socio_estado'
               AND object_id = OBJECT_ID('maestros.propiedad'))
CREATE INDEX IX_propiedad_socio_estado ON maestros.propiedad (codigo_de_socio) INCLUDE (estado, nombre);
GO

/* Verificación después de cargar: propiedades cuyo socio no está en la réplica.
   No aparecen en ningún lado, así que tiene que devolver cero filas.

   La comparación es binaria a propósito: con la colación de la base,
   'c-004871' encuentra a 'C-004871' y no se señala, pero el conteo de
   cantidadPropiedades la busca por el código exacto y da 0 mientras la lista
   la muestra.

SELECT p.id_propiedad, p.nombre, p.codigo_de_socio
FROM maestros.propiedad p
WHERE NOT EXISTS (SELECT 1 FROM maestros.socios s
                  WHERE s.codigo_de_socio = p.codigo_de_socio COLLATE Latin1_General_BIN2);
*/
