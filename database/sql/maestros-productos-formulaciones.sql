/* =====================================================================
   Formulación de los productos (fuente: web)
   Ejecutar después de maestros-productos-carga-inicial.sql
   ---------------------------------------------------------------------
   En la web casi todas dicen «Suspensión concentrada (SC)», que parece un
   valor por defecto. Se carga solo donde no la contradice el sufijo del
   ingrediente activo (SC, EC, SL, WG, WP, ME, SP) — 38 productos.

   Quedan EN BLANCO, a la espera de confirmación:
     * La web dice SC y el ingrediente dice otra cosa:
       Agrogibe 4% EC (web SC, ingrediente EC), Amino Total (web SC,
       ingrediente SP), Benton (web SC, ingrediente SL), Charter 240 EC
       (web SC, ingrediente EC), Clomax 480 EC (web SC, ingrediente EC),
       Clorimur 250 WP (web SC, ingrediente WP), Crop Oil (web SC,
       ingrediente SL), Galactic 250 SL (web SC, ingrediente SL), Galant
       MAX (web SC, ingrediente EC), Gliforte Plus (web SC, ingrediente
       SL), Gluforte (web SC, ingrediente SL), Kuprex (web SC,
       ingrediente WP), Leili 2000 (web SC, ingrediente SL), Leser (web
       SC, ingrediente WG), Linear (web SC, ingrediente WG), Mancoparts
       (web SC, ingrediente WP), Nifuron 75 WG (web SC, ingrediente WG),
       Pacto (web SC, ingrediente WG), Piryzol 750 WP (web SC,
       ingrediente WP), Potency (web SC, ingrediente EC), Prime-S (web
       SC, ingrediente EC), Randon (web SC, ingrediente SL), Secaforte
       (web SC, ingrediente SL), Seguro (web SC, ingrediente WG), Starane
       Xtra (web SC, ingrediente EC), Vessarya (web SC, ingrediente EC),
       Viovan (web SC, ingrediente EC), Zetapyr 100 SL (web SC,
       ingrediente SL).
     * Sin formulación en la web:
       Abamex Plus, Agroalpha, Agrogite, Agrothrin, Alvo, Dacloprid 350
       SC, Delete, Dinno, Exalt, Fatal 480 EC, Flycontrol, Intrepid SC,
       Joker, Lambdatrin 50 EC, Nanofos, NC-Mectin, Nion 48 SC, Pirofen,
       PQM Bacco Power, Quintal Xtra, Sumo, Thiametozix 70, Transform,
       Trategy.

   Idempotente. Con sqlcmd, correrlo con -I.
   ===================================================================== */

SET NOCOUNT ON;

UPDATE p SET
    p.formulacion    = v.formulacion,
    p.actualizado_en = SYSDATETIME()
FROM maestros.producto p
JOIN (VALUES
    (N'10056', N'Suspensión concentrada (SC)'),  -- Agrozina 50 SC
    (N'10015', N'Suspensión concentrada (SC)'),  -- Alga 300++
    (N'10038', N'Suspensión concentrada (SC)'),  -- Aproach Prima
    (N'10174', N'Suspensión concentrada (SC)'),  -- Athlon
    (N'10041', N'Suspensión concentrada (SC)'),  -- Concert
    (N'10042', N'Suspensión concentrada (SC)'),  -- Dueto Pro
    (N'10175', N'Suspensión concentrada (SC)'),  -- Efyra
    (N'10182', N'Suspensión concentrada (SC)'),  -- Eiru
    (N'10066', N'Suspensión concentrada (SC)'),  -- Flumioxazol 48
    (N'10017', N'Suspensión concentrada (SC)'),  -- Grap 104 Fluid
    (N'10019', N'Suspensión concentrada (SC)'),  -- Grap 30K
    (N'10021', N'Suspensión concentrada (SC)'),  -- Grap Amyno
    (N'10022', N'Suspensión concentrada (SC)'),  -- Grap Boric
    (N'10010', N'Suspensión concentrada (SC)'),  -- Grap D-Lim
    (N'10024', N'Suspensión concentrada (SC)'),  -- Grap Evic-S
    (N'10025', N'Suspensión concentrada (SC)'),  -- Grap Grad
    (N'10029', N'Suspensión concentrada (SC)'),  -- Grap Mont 15
    (N'10031', N'Suspensión concentrada (SC)'),  -- Grap Nitro
    (N'10135', N'Suspensión concentrada (SC)'),  -- Grap Nod AL
    (N'10136', N'Suspensión concentrada (SC)'),  -- Grap Nod L
    (N'10011', N'Suspensión concentrada (SC)'),  -- Grap Sensor
    (N'10012', N'Suspensión concentrada (SC)'),  -- Grap Super Gun
    (N'10044', N'Suspensión concentrada (SC)'),  -- Innova
    (N'10001', N'Suspensión concentrada (SC)'),  -- Invictus
    (N'10013', N'Suspensión concentrada (SC)'),  -- Loop RS
    (N'10080', N'Concentrado Soluble (SL)'),  -- Mazapyr 250 SL
    (N'10156', N'Suspensión concentrada (SC)'),  -- Remove RS
    (N'10160', N'Suspensión concentrada (SC)'),  -- Sharfentrazone
    (N'10172', N'Suspensión concentrada (SC)'),  -- Strada
    (N'10048', N'Suspensión concentrada (SC)'),  -- Supress
    (N'10157', N'Suspensión concentrada (SC)'),  -- Surex RS
    (N'10161', N'Suspensión concentrada (SC)'),  -- Texaro
    (N'10003', N'Suspensión concentrada (SC)'),  -- Thanos
    (N'10150', N'Suspensión concentrada (SC)'),  -- Themis
    (N'10176', N'Suspensión concentrada (SC)'),  -- Theron
    (N'10159', N'Suspensión concentrada (SC)'),  -- Triler PRO
    (N'10004', N'Suspensión concentrada (SC)'),  -- Vitane HC
    (N'20005', N'Suspensión concentrada (SC)')  -- Voyager
) AS v(codigo_articulo, formulacion)
  ON v.codigo_articulo = p.codigo_articulo;

-- Verificación
SELECT COUNT(*) AS con_formulacion FROM maestros.producto WHERE formulacion IS NOT NULL;  -- esperado: 38
