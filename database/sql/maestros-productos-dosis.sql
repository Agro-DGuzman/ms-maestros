/* =====================================================================
   Dosis referencial de los productos (fuente: web)
   Ejecutar después de maestros-productos-carga-inicial.sql
   ---------------------------------------------------------------------
   Se carga solo la que trae unidad (10 productos): un número suelto
   («0,25») no dice si son L/ha, Kg/ha o ml/100 L, y la App lo mostraría
   así.

   Quedan EN BLANCO, a la espera de confirmación:
     * Sin unidad (el número que figura en la web, entre paréntesis):
       Abamex Plus (0,25), Agroalpha (0,30), Agrogibe 4% EC (0,02),
       Agrogite (0,50), Agrothrin (0,30), Alga 300++ (1,00), Alvo (0,30),
       Amino Total (0,30), Aproach Prima (0,40), Charter 240 EC (0,5),
       Clomax 480 EC (1,00), Clorimur 250 WP (0,03), Concert (0,60), Crop
       Oil (0,50), Dacloprid 350 SC (0,40), Delete (1,80), Dinno (0,15),
       Dueto Pro (0,40), Eiru (1,00), Exalt (0,12), Fatal 480 EC (1,00),
       Flumioxazol 48 (0,15), Flycontrol (0,15), Galactic 250 SL (0,80),
       Galant MAX (0,80), Gliforte Plus (2,00), Gluforte (2,00), Grap 104
       Fluid (1,00), Grap 30K (1,00), Grap Boric (1,00), Grap D-Lim
       (0,10), Grap Evic-S (0,50), Grap Mont 15 (1,00), Grap Nitro
       (2,00), Grap Sensor (0,05), Grap Super Gun (0,05), Innova (0,50),
       Intrepid SC (0,30), Joker (0,40), Lambdatrin 50 EC (0,30), Leser
       (0,30), Linear (2,00), Loop RS (0,05), Mancoparts (1,50), Nanofos
       (1,0), NC-Mectin (0,30), Nifuron 75 WG (0,06), Nion 48 SC (0,15),
       Pacto (0,04), Pirofen (0,70), Piryzol 750 WP (0,30), Potency
       (2,00), PQM Bacco Power (0,30), Prime-S (1,50), Quintal Xtra
       (0,10), Randon (2,00), Remove RS (0,01), Secaforte (2,00), Seguro
       (0,30), Sharfentrazone (0,80), Starane Xtra (0,35), Strada (1,50),
       Sumo (0,20), Supress (0,15), Surex RS (0,20), Texaro (0,35),
       Thanos (1,00), Thiametozix 70 (0,20), Transform (0,06), Trategy
       (0,25), Triler PRO (0,40), Vessarya (0,60), Viovan (0,60), Voyager
       (0,50), Zetapyr 100 SL (1,00).
     * Sin dosis en la web:
       Grap Amyno, Grap Grad, Kuprex, Leili 2000, Themis.

   Idempotente. Con sqlcmd, correrlo con -I.
   ===================================================================== */

SET NOCOUNT ON;

UPDATE p SET
    p.dosis_referencial = v.dosis_referencial,
    p.actualizado_en    = SYSDATETIME()
FROM maestros.producto p
JOIN (VALUES
    (N'10056', N'2 - 4 l/ha'),  -- Agrozina 50 SC
    (N'10174', N'350 - 400 ml/100 Kg'),  -- Athlon
    (N'10057', N'0,8 - 1 l'),  -- Benton
    (N'10175', N'350 - 400 ml/100 Kg'),  -- Efyra
    (N'10135', N'350 - 400 ml/100 Kg'),  -- Grap Nod AL
    (N'10136', N'350 - 400 ml/100 Kg'),  -- Grap Nod L
    (N'10001', N'350 - 400 ml/100 Kg'),  -- Invictus
    (N'10080', N'200 ml/ha'),  -- Mazapyr 250 SL
    (N'10176', N'350 - 400 ml/100 Kg'),  -- Theron
    (N'10004', N'350 - 400 ml/100 Kg')  -- Vitane HC
) AS v(codigo_articulo, dosis_referencial)
  ON v.codigo_articulo = p.codigo_articulo;

-- Verificación
SELECT COUNT(*) AS con_dosis FROM maestros.producto WHERE dosis_referencial IS NOT NULL;  -- esperado: 10
