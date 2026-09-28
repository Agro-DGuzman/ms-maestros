/* =====================================================================
   Categorías de los 90 productos (fuente: WordPress, campo categoria_producto)
   Ejecutar después de maestros-productos-carga-inicial.sql
   ---------------------------------------------------------------------
   - Las categorías son datos: las del contrato son ejemplos.
   - GET /categorias muestra solo las que tienen productos visibles.
   - Idempotente. Con sqlcmd, correrlo con -I.
   ===================================================================== */

SET NOCOUNT ON;

-- 1) Categorías (6) -----------------------------------------------------
MERGE maestros.categoria AS t
USING (VALUES
    (N'biologicos', N'Biológicos'),
    (N'coadyuvantes', N'Coadyuvantes'),
    (N'fertilizantes', N'Fertilizantes'),
    (N'fungicidas', N'Fungicidas'),
    (N'herbicidas', N'Herbicidas'),
    (N'insecticidas', N'Insecticidas')
) AS s(codigo, nombre)
ON t.codigo = s.codigo
WHEN MATCHED THEN UPDATE SET nombre = s.nombre
WHEN NOT MATCHED THEN INSERT (codigo, nombre) VALUES (s.codigo, s.nombre);

-- 2) Asignar categoría a cada producto (por codigo_articulo) -------------
UPDATE p SET
    p.codigo_categoria = v.codigo_categoria,
    p.actualizado_en   = SYSDATETIME()
FROM maestros.producto p
JOIN (VALUES
    (N'10001', N'biologicos'),  -- Invictus ®
    (N'10003', N'biologicos'),  -- Thanos ®
    (N'10004', N'biologicos'),  -- Vitane HC ®
    (N'10135', N'biologicos'),  -- Grap Nod AL®
    (N'10136', N'biologicos'),  -- Grap Nod L®
    (N'10150', N'biologicos'),  -- Themis ®
    (N'10174', N'biologicos'),  -- Athlon®
    (N'10175', N'biologicos'),  -- Efyra®
    (N'10176', N'biologicos'),  -- Theron ®
    (N'10007', N'coadyuvantes'),  -- Crop Oil ®
    (N'10010', N'coadyuvantes'),  -- Grap D-Lim ®
    (N'10011', N'coadyuvantes'),  -- Grap Sensor ®
    (N'10012', N'coadyuvantes'),  -- Grap Super Gun ®
    (N'10013', N'coadyuvantes'),  -- Loop RS ®
    (N'10024', N'coadyuvantes'),  -- Grap Evic-S ®
    (N'10156', N'coadyuvantes'),  -- Remove RS ®
    (N'10157', N'coadyuvantes'),  -- Surex RS ®
    (N'20005', N'coadyuvantes'),  -- Voyager ®
    (N'10014', N'fertilizantes'),  -- Agrogibe 4% EC ®
    (N'10015', N'fertilizantes'),  -- Alga 300++ ®
    (N'10016', N'fertilizantes'),  -- Amino Total ®
    (N'10017', N'fertilizantes'),  -- Grap 104 Fluid ®
    (N'10019', N'fertilizantes'),  -- Grap 30K ®
    (N'10021', N'fertilizantes'),  -- Grap Amyno ®
    (N'10022', N'fertilizantes'),  -- Grap Boric ®
    (N'10025', N'fertilizantes'),  -- Grap Grad ®
    (N'10029', N'fertilizantes'),  -- Grap Mont 15 ®
    (N'10031', N'fertilizantes'),  -- Grap Nitro ®
    (N'10033', N'fertilizantes'),  -- Leili 2000 ®
    (N'10038', N'fungicidas'),  -- Aproach® Prima
    (N'10041', N'fungicidas'),  -- Concert ®
    (N'10042', N'fungicidas'),  -- Dueto Pro ®
    (N'10044', N'fungicidas'),  -- Innova ®
    (N'10045', N'fungicidas'),  -- Kuprex ®
    (N'10046', N'fungicidas'),  -- Mancoparts ®
    (N'10047', N'fungicidas'),  -- Piryzol 750 WP ®
    (N'10048', N'fungicidas'),  -- Supress ®
    (N'10050', N'fungicidas'),  -- Vessarya ®
    (N'10051', N'fungicidas'),  -- Viovan ®
    (N'10159', N'fungicidas'),  -- Triler PRO ®
    (N'10172', N'fungicidas'),  -- Strada ®
    (N'10056', N'herbicidas'),  -- Agrozina 50 SC ®
    (N'10057', N'herbicidas'),  -- Benton ®
    (N'10058', N'herbicidas'),  -- Charter 240 EC ®
    (N'10060', N'herbicidas'),  -- Clomax 480 EC ®
    (N'10061', N'herbicidas'),  -- Clorimur 250 WP ®
    (N'10066', N'herbicidas'),  -- Flumioxazol 48
    (N'10067', N'herbicidas'),  -- Galactic 250 SL ®
    (N'10072', N'herbicidas'),  -- Gliforte Plus ®
    (N'10074', N'herbicidas'),  -- Gluforte ®
    (N'10078', N'herbicidas'),  -- Leser ®
    (N'10079', N'herbicidas'),  -- Linear ®
    (N'10080', N'herbicidas'),  -- Mazapyr 250 SL ®
    (N'10081', N'herbicidas'),  -- Nifuron 75 WG ®
    (N'10082', N'herbicidas'),  -- Pacto ®
    (N'10083', N'herbicidas'),  -- Prime-S ®
    (N'10085', N'herbicidas'),  -- Randon ®
    (N'10087', N'herbicidas'),  -- Secaforte ®
    (N'10091', N'herbicidas'),  -- Starane® Xtra
    (N'10092', N'herbicidas'),  -- Zetapyr 100 SL ®
    (N'10153', N'herbicidas'),  -- Galant™ MAX
    (N'10160', N'herbicidas'),  -- Sharfentrazone ®
    (N'10161', N'herbicidas'),  -- Texaro®
    (N'10179', N'herbicidas'),  -- Potency ®
    (N'10182', N'herbicidas'),  -- Eiru ®
    (N'10184', N'herbicidas'),  -- Seguro ®
    (N'10093', N'insecticidas'),  -- Abamex Plus
    (N'10094', N'insecticidas'),  -- Agroalpha
    (N'10095', N'insecticidas'),  -- Agrogite
    (N'10097', N'insecticidas'),  -- Agrothrin
    (N'10099', N'insecticidas'),  -- Dacloprid 350 SC
    (N'10100', N'insecticidas'),  -- Delete
    (N'10101', N'insecticidas'),  -- Dinno
    (N'10102', N'insecticidas'),  -- Exalt ®
    (N'10103', N'insecticidas'),  -- Fatal 480 EC
    (N'10105', N'insecticidas'),  -- Flycontrol
    (N'10106', N'insecticidas'),  -- Intrepid®SC
    (N'10107', N'insecticidas'),  -- Joker
    (N'10108', N'insecticidas'),  -- Lambdatrin 50 EC
    (N'10110', N'insecticidas'),  -- Nanofos
    (N'10113', N'insecticidas'),  -- NC-Mectin
    (N'10114', N'insecticidas'),  -- Nion 48 SC
    (N'10115', N'insecticidas'),  -- Pirofen
    (N'10118', N'insecticidas'),  -- Quintal® Xtra
    (N'10120', N'insecticidas'),  -- Sumo
    (N'10121', N'insecticidas'),  -- Thiametozix 70
    (N'10123', N'insecticidas'),  -- Transform™
    (N'10158', N'insecticidas'),  -- PQM Bacco Power
    (N'10180', N'insecticidas'),  -- Alvo
    (N'10181', N'insecticidas')   -- Trategy
) AS v(codigo_articulo, codigo_categoria)
  ON v.codigo_articulo = p.codigo_articulo;

-- 3) Verificación --------------------------------------------------------
SELECT c.nombre AS categoria, COUNT(p.id_producto) AS productos
FROM maestros.categoria c
LEFT JOIN maestros.producto p ON p.codigo_categoria = c.codigo
GROUP BY c.nombre
ORDER BY c.nombre;
-- Esperado: Biológicos 9, Coadyuvantes 9, Fertilizantes 11,
--           Fungicidas 12, Herbicidas 25, Insecticidas 24  (total 90)

SELECT codigo_articulo, nombre FROM maestros.producto WHERE codigo_categoria IS NULL;  -- debe venir vacío
