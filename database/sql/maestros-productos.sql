/* =====================================================================
   Catálogo de productos - SQL Server / Azure SQL
   ---------------------------------------------------------------------
   Adaptado al contrato OpenAPI (contrato/agropartners-api-v1.yaml), que
   es contra lo que se construye la App:

     GET /productos                     tarjeta: itemCode, nombre,
                                        categoria, presentacion, imagenUrl
     GET /productos/{itemCode}          ficha: + descripcion, ingredienteActivo,
                                        formulacion, dosisReferencial,
                                        cultivos, registro {entidad, numero}
     GET /productos/{itemCode}/documentos
                                        ficha_tecnica, hoja_seguridad,
                                        registro_sanitario
     GET /categorias                    las que tienen productos visibles

   - Vive en el esquema "maestros": el contrato le asigna estos endpoints
     al servicio de maestros.
   - Cada URL es una columna simple. Los documentos son tres tipos fijos en
     el contrato, así que no hace falta una tabla aparte.
   - Idempotente: crea solo lo que no existe y no borra datos.
   - Con sqlcmd, correrlo con -I (QUOTED_IDENTIFIER ON), igual que cualquier
     script que inserte acá: los índices filtrados lo exigen, y sin él todo
     INSERT falla. SSMS, Azure Data Studio y la app ya lo traen prendido.
   - La API muestra solo productos activos, con código de artículo y con
     categoría. El resto puede cargarse y conciliarse de a poco sin
     aparecer en la App.
   ===================================================================== */

IF SCHEMA_ID('maestros') IS NULL EXEC('CREATE SCHEMA maestros');
GO

/* ---------- Categoría -------------------------------------------------- */
-- Las categorías son datos, no una lista fija: las del contrato son ejemplos
-- y el catálogo real tiene más. Se cargan con maestros-productos-categorias.sql.
IF OBJECT_ID('maestros.categoria') IS NULL
CREATE TABLE maestros.categoria (
    codigo  NVARCHAR(50)  NOT NULL CONSTRAINT PK_categoria PRIMARY KEY,
    nombre  NVARCHAR(255) NOT NULL
);
GO

-- Una versión anterior de este script las limitaba a cuatro.
IF OBJECT_ID('maestros.CK_categoria_codigo', 'C') IS NOT NULL
    ALTER TABLE maestros.categoria DROP CONSTRAINT CK_categoria_codigo;
GO

/* ---------- Producto --------------------------------------------------- */
IF OBJECT_ID('maestros.producto') IS NULL
CREATE TABLE maestros.producto (
    id_producto             INT IDENTITY(1,1) CONSTRAINT PK_producto PRIMARY KEY,

    -- El itemCode del contrato (código SAP). Todas las rutas lo usan: sin
    -- él, el producto no sale en la API.
    codigo_articulo         NVARCHAR(50)   NULL,
    nombre                  NVARCHAR(255)  NOT NULL,
    -- Sin categoría tampoco sale: el contrato la exige en cada tarjeta.
    codigo_categoria        NVARCHAR(50)   NULL
        CONSTRAINT FK_producto_categoria REFERENCES maestros.categoria(codigo),
    presentacion            NVARCHAR(255)  NULL,

    descripcion             NVARCHAR(MAX)  NULL,
    ingrediente_activo      NVARCHAR(500)  NULL,
    formulacion             NVARCHAR(255)  NULL,
    dosis_referencial       NVARCHAR(500)  NULL,

    -- El registro es entidad y número juntos, o nada.
    registro_entidad        NVARCHAR(10)   NULL
        CONSTRAINT CK_producto_registro_entidad
            CHECK (registro_entidad IN (N'SENASAG', N'INIAF', N'otro')),
    registro_numero         NVARCHAR(100)  NULL,

    imagen_url              NVARCHAR(1000) NULL,
    ficha_tecnica_url       NVARCHAR(1000) NULL,
    hoja_seguridad_url      NVARCHAR(1000) NULL,
    registro_sanitario_url  NVARCHAR(1000) NULL,

    -- Dar de baja es poner 0: la App deja de verlo sin perder el dato.
    activo                  BIT            NOT NULL CONSTRAINT DF_producto_activo DEFAULT (1),

    wp_id                   INT            NULL,     -- ID en WordPress (ief_posts), para conciliar
    creado_en               DATETIME2(0)   NOT NULL CONSTRAINT DF_producto_creado DEFAULT SYSDATETIME(),
    actualizado_en          DATETIME2(0)   NOT NULL CONSTRAINT DF_producto_actualizado DEFAULT SYSDATETIME(),

    CONSTRAINT CK_producto_registro_completo CHECK (
        (registro_entidad IS NULL AND registro_numero IS NULL)
        OR (registro_entidad IS NOT NULL AND registro_numero IS NOT NULL)
    )
);
GO

-- Los índices se buscan en su tabla: el nombre solo es único por tabla, y
-- un script anterior pudo crearlos con el mismo nombre en otro esquema.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_producto_codigo_articulo'
               AND object_id = OBJECT_ID('maestros.producto'))
CREATE UNIQUE INDEX UX_producto_codigo_articulo
    ON maestros.producto(codigo_articulo) WHERE codigo_articulo IS NOT NULL;

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_producto_wp_id'
               AND object_id = OBJECT_ID('maestros.producto'))
CREATE UNIQUE INDEX UX_producto_wp_id
    ON maestros.producto(wp_id) WHERE wp_id IS NOT NULL;

-- El filtro por categoría de GET /productos, sobre lo que la App puede ver.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_producto_categoria'
               AND object_id = OBJECT_ID('maestros.producto'))
CREATE INDEX IX_producto_categoria
    ON maestros.producto(codigo_categoria, nombre) WHERE activo = 1;
GO

/* ---------- Cultivos (lista del producto) ------------------------------ */
IF OBJECT_ID('maestros.producto_cultivo') IS NULL
CREATE TABLE maestros.producto_cultivo (
    id_producto  INT           NOT NULL
        CONSTRAINT FK_producto_cultivo_producto REFERENCES maestros.producto(id_producto) ON DELETE CASCADE,
    cultivo      NVARCHAR(255) NOT NULL,
    CONSTRAINT PK_producto_cultivo PRIMARY KEY (id_producto, cultivo)
);
GO
