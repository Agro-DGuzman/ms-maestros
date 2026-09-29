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
     database/migrations/maestros/2026_09_29_000100_crear_propiedades.php.
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
    CONSTRAINT CK_propiedad_id CHECK (LEN(id_propiedad) > 0 AND id_propiedad NOT LIKE N' %'
        AND DATALENGTH(id_propiedad) = DATALENGTH(RTRIM(id_propiedad))),
    CONSTRAINT CK_propiedad_nombre CHECK (LEN(nombre) > 0 AND nombre NOT LIKE N' %'
        AND DATALENGTH(nombre) = DATALENGTH(RTRIM(nombre))),
    CONSTRAINT CK_propiedad_socio CHECK (LEN(codigo_de_socio) > 0 AND codigo_de_socio NOT LIKE N' %'
        AND DATALENGTH(codigo_de_socio) = DATALENGTH(RTRIM(codigo_de_socio)))
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_propiedad_socio'
               AND object_id = OBJECT_ID('maestros.propiedad'))
CREATE INDEX IX_propiedad_socio ON maestros.propiedad (codigo_de_socio) INCLUDE (activa, nombre);
GO

/* Verificación después de cargar: propiedades cuyo socio no está en la réplica.
   No aparecen en ningún lado, así que tiene que devolver cero filas.

SELECT p.id_propiedad, p.nombre, p.codigo_de_socio
FROM maestros.propiedad p
WHERE NOT EXISTS (SELECT 1 FROM maestros.socios s WHERE s.codigo_de_socio = p.codigo_de_socio);
*/
