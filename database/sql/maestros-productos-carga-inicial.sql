/* =====================================================================
   Carga inicial de los 90 productos publicados en la web
   Tabla: maestros.producto  (ejecutar después de maestros-productos.sql)
   ---------------------------------------------------------------------
   - codigo_articulo: código en app.productos (uno por producto; cuando
     hay varias presentaciones, el código sin presentación o el menor).
     Es el itemCode con el que la App pide cada producto.
   - nombre: nombre de la web sin ®/™.
   - presentacion: presentaciones que existen en producción.
   - Resto de campos queda en NULL para conciliar después.
   - Idempotente: no inserta códigos que ya existan.
   - Con sqlcmd, correrlo con -I: los índices filtrados de la tabla exigen
     QUOTED_IDENTIFIER ON y sin él todo INSERT falla.

   OJO: sin codigo_categoria un producto NO aparece en la App (el contrato
   exige la categoría en cada tarjeta). Esta carga no la trae: hasta
   asignarla, GET /productos sigue vacío.
   ===================================================================== */

SET NOCOUNT ON;

INSERT INTO maestros.producto (codigo_articulo, nombre, presentacion)
SELECT v.codigo_articulo, v.nombre, v.presentacion
FROM (VALUES
    (N'10093', N'Abamex Plus', N'0.25 L / 1 L / 5 L'),
    (N'10094', N'Agroalpha', N'5 L'),
    (N'10014', N'Agrogibe 4% EC', NULL),
    (N'10095', N'Agrogite', NULL),
    (N'10097', N'Agrothrin', NULL),
    (N'10056', N'Agrozina 50 SC', NULL),
    (N'10015', N'Alga 300++', NULL),
    (N'10180', N'Alvo', N'0.25 L / 1 L'),
    (N'10016', N'Amino Total', NULL),
    (N'10038', N'Aproach Prima', NULL),
    (N'10174', N'Athlon', N'5 L'),
    (N'10057', N'Benton', N'5 L'),
    (N'10058', N'Charter 240 EC', N'1 L / 5 L'),
    (N'10060', N'Clomax 480 EC', N'5 L'),
    (N'10061', N'Clorimur 250 WP', NULL),
    (N'10041', N'Concert', N'0.25 L / 1 L / 5 L'),
    (N'10007', N'Crop Oil', NULL),
    (N'10099', N'Dacloprid 350 SC', NULL),
    (N'10100', N'Delete', NULL),
    (N'10101', N'Dinno', NULL),
    (N'10042', N'Dueto Pro', NULL),
    (N'10175', N'Efyra', N'1.5 L'),
    (N'10182', N'Eiru', NULL),
    (N'10102', N'Exalt', NULL),
    (N'10103', N'Fatal 480 EC', NULL),
    (N'10066', N'Flumioxazol 48', NULL),
    (N'10105', N'Flycontrol', NULL),
    (N'10067', N'Galactic 250 SL', N'5 L'),
    (N'10153', N'Galant MAX', NULL),
    (N'10072', N'Gliforte Plus', N'20 L / 200 L'),
    (N'10074', N'Gluforte', N'1 L'),
    (N'10017', N'Grap 104 Fluid', N'5 L / 20 L'),
    (N'10019', N'Grap 30K', N'5 L / 20 L'),
    (N'10021', N'Grap Amyno', NULL),
    (N'10022', N'Grap Boric', N'5 L / 20 L'),
    (N'10010', N'Grap D-Lim', NULL),
    (N'10024', N'Grap Evic-S', NULL),
    (N'10025', N'Grap Grad', N'250 ml / 1 L'),
    (N'10029', N'Grap Mont 15', N'5 L / 20 L'),
    (N'10031', N'Grap Nitro', NULL),
    (N'10135', N'Grap Nod AL', NULL),
    (N'10136', N'Grap Nod L', NULL),
    (N'10011', N'Grap Sensor', NULL),
    (N'10012', N'Grap Super Gun', NULL),
    (N'10044', N'Innova', NULL),
    (N'10106', N'Intrepid SC', N'5 L'),
    (N'10001', N'Invictus', N'1 L / 5 L / 10 L'),
    (N'10107', N'Joker', N'250 ml / 1 L'),
    (N'10045', N'Kuprex', NULL),
    (N'10108', N'Lambdatrin 50 EC', NULL),
    (N'10033', N'Leili 2000', NULL),
    (N'10078', N'Leser', NULL),
    (N'10079', N'Linear', NULL),
    (N'10013', N'Loop RS', N'1 L'),
    (N'10046', N'Mancoparts', N'25 Kg'),
    (N'10080', N'Mazapyr 250 SL', NULL),
    (N'10110', N'Nanofos', NULL),
    (N'10113', N'NC-Mectin', NULL),
    (N'10081', N'Nifuron 75 WG', NULL),
    (N'10114', N'Nion 48 SC', NULL),
    (N'10082', N'Pacto', NULL),
    (N'10115', N'Pirofen', NULL),
    (N'10047', N'Piryzol 750 WP', NULL),
    (N'10179', N'Potency', NULL),
    (N'10158', N'PQM Bacco Power', NULL),
    (N'10083', N'Prime-S', NULL),
    (N'10118', N'Quintal Xtra', NULL),
    (N'10085', N'Randon', N'5 L / 20 L'),
    (N'10156', N'Remove RS', NULL),
    (N'10087', N'Secaforte', N'20 L'),
    (N'10184', N'Seguro', NULL),
    (N'10160', N'Sharfentrazone', NULL),
    (N'10091', N'Starane Xtra', N'5 L'),
    (N'10172', N'Strada', NULL),
    (N'10120', N'Sumo', NULL),
    (N'10048', N'Supress', N'1 L / 5 L'),
    (N'10157', N'Surex RS', NULL),
    (N'10161', N'Texaro', NULL),
    (N'10003', N'Thanos', NULL),
    (N'10150', N'Themis', NULL),
    (N'10176', N'Theron', N'5 L'),
    (N'10121', N'Thiametozix 70', N'1 Kg'),
    (N'10123', N'Transform', N'300 g'),
    (N'10181', N'Trategy', NULL),
    (N'10159', N'Triler PRO', NULL),
    (N'10050', N'Vessarya', NULL),
    (N'10051', N'Viovan', NULL),
    (N'10004', N'Vitane HC', NULL),
    (N'20005', N'Voyager', N'10 L'),
    (N'10092', N'Zetapyr 100 SL', NULL)
) AS v(codigo_articulo, nombre, presentacion)
WHERE NOT EXISTS (
    SELECT 1 FROM maestros.producto p WHERE p.codigo_articulo = v.codigo_articulo
);

SELECT COUNT(*) AS total_productos FROM maestros.producto;   -- debe ser 90

-- Los que la App todavía no ve, porque les falta la categoría.
SELECT COUNT(*) AS sin_categoria FROM maestros.producto WHERE codigo_categoria IS NULL;

-- Verificación: códigos que no existen en app.productos (debe venir vacío).
-- Solo donde esa tabla existe: por EXEC, para que el lote no falle en una
-- base que no la tiene.
IF OBJECT_ID('app.productos') IS NOT NULL
    EXEC(N'SELECT p.codigo_articulo, p.nombre
           FROM maestros.producto p
           LEFT JOIN app.productos ap ON ap.codigo = p.codigo_articulo
           WHERE ap.codigo IS NULL;');
