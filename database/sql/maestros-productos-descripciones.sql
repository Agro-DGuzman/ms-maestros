/* =====================================================================
   Descripciones de los productos (fuente: textos de la web)
   Ejecutar después de maestros-productos-carga-inicial.sql
   ---------------------------------------------------------------------
   - Por codigo_articulo; el comentario de cada fila es el nombre.
   - Formato normalizado: sin los saltos de línea del diseño de la web, sin
     espacios de sobra y con mayúscula inicial. El texto no se tocó.
   - Quedan SIN descripción, a propósito, y la ficha no la muestra:
       * 23 con el texto de relleno «Lorem ipsum»: Agrogibe 4% EC, Alga 300++,
         Amino Total, Crop Oil, Efyra, Grap 104 Fluid, Grap 30K, Grap Amyno,
         Grap Boric, Grap Grad, Grap Nod AL, Grap Nod L, Grap Super Gun,
         Invictus, Kuprex, Leili 2000, Remove RS, Thanos, Themis, Theron,
         Vessarya, Viovan, Vitane HC.
       * Grap Nitro: la web tiene la descripción de Leili 2000.
       * Thiametozix 70: vacía en la web.
   - Idempotente. Con sqlcmd, correrlo con -I.
   ===================================================================== */

SET NOCOUNT ON;

UPDATE p SET
    p.descripcion    = v.descripcion,
    p.actualizado_en = SYSDATETIME()
FROM maestros.producto p
JOIN (VALUES
    (N'10093', N'Modulador de canales de cloro.'),  -- Abamex Plus
    (N'10094', N'Modulador de canales de sodio.'),  -- Agroalpha
    (N'10095', N'Inhibidor de transporte de electrones mitocondrial.'),  -- Agrogite
    (N'10097', N'Modulador de canales de sodio.'),  -- Agrothrin
    (N'10056', N'Herbicida selectivo, sistémico y residual.'),  -- Agrozina 50 SC
    (N'10180', N'Agonista nicotínico.'),  -- Alvo
    (N'10038', N'Fungicida sistémico con acción preventiva, curativa y erradicante.'),  -- Aproach Prima
    (N'10174', N'Biofungicida microbiológico formulado'),  -- Athlon
    (N'10057', N'Herbicida de contacto, post-emergente.'),  -- Benton
    (N'10058', N'Herbicida sistémico post-emergente.'),  -- Charter 240 EC
    (N'10060', N'Herbicida pre-emergente.'),  -- Clomax 480 EC
    (N'10061', N'Herbicida sistémico para aplicación en barbecho.'),  -- Clorimur 250 WP
    (N'10041', N'Fungicida sistémico preventivo y curativo.'),  -- Concert
    (N'10099', N'Agonista nicotínico.'),  -- Dacloprid 350 SC
    (N'10100', N'Desacoplador de fosforilación oxidativa.'),  -- Delete
    (N'10101', N'Agonista nicotínico.'),  -- Dinno
    (N'10042', N'Fungicida sistémico preventivo y curativo.'),  -- Dueto Pro
    (N'10182', N'Formulación concentrada de 2,4-D al 86%.'),  -- Eiru
    (N'10102', N'Insecticida a base de spinetoram, derivado de origen natural.'),  -- Exalt
    (N'10103', N'Inhibidor de acetilcolinesterasa.'),  -- Fatal 480 EC
    (N'10066', N'Herbicida de contacto y residual.'),  -- Flumioxazol 48
    (N'10105', N'Efecto de volteo rapido.'),  -- Flycontrol
    (N'10067', N'Herbicida post-emergente.'),  -- Galactic 250 SL
    (N'10153', N'Herbicida sistémico post-emergente.'),  -- Galant MAX
    (N'10072', N'Herbicida no selectivo sistémico.'),  -- Gliforte Plus
    (N'10074', N'Herbicida de contacto no selectivo.'),  -- Gluforte
    (N'10010', N'GRAP D-Lim es un coadyuvante multifuncional con características potenciadoras.'),  -- Grap D-Lim
    (N'10024', N'GRAP EVIC-S es un fertilizante foliar que contiene nitrógeno y azufre.'),  -- Grap Evic-S
    (N'10029', N'Grap Mont 15 es un fertilizante foliar diseñado específicamente para disminuir los efectos de fitotoxicidad en plantas tras la aplicación de herbicidas.'),  -- Grap Mont 15
    (N'10011', N'El GRAP Sensor es un coadyuvante con características útiles que mejora el proceso de pulverización.'),  -- Grap Sensor
    (N'10044', N'Fungicida sistémico, curativo y erradicante del grupo de los triazoles.'),  -- Innova
    (N'10106', N'Insecticida selectivo que controla eficazmente larvas.'),  -- Intrepid SC
    (N'10107', N'Amplio espectro y efecto de choque rapido.'),  -- Joker
    (N'10108', N'Modulador de canales de sodio.'),  -- Lambdatrin 50 EC
    (N'10078', N'Herbicida sistémico pre y post-emergente.'),  -- Leser
    (N'10079', N'Herbicida de amplio espectro pre y post-emergente.'),  -- Linear
    (N'10013', N'Es un aceite vegetal premium a base de maíz.'),  -- Loop RS
    (N'10046', N'Fungicida preventivo de contacto, ideal para manejo antiresistencia.'),  -- Mancoparts
    (N'10080', N'Herbicida no selectivo, eficaz contra malezas anuales, perennes y arbustivas. Con acción residual, elimina completamente la vegetación sin afectar fauna ni suelo, seguro, estable y libre de contaminación ambiental.'),  -- Mazapyr 250 SL
    (N'10110', N'Inhibidor de acetilcolinesterasa.'),  -- Nanofos
    (N'10113', N'Actua sobre el sistema nervioso de los insectos.'),  -- NC-Mectin
    (N'10081', N'Herbicida post-emergente sistémico.'),  -- Nifuron 75 WG
    (N'10114', N'Posee alta persistencia.'),  -- Nion 48 SC
    (N'10082', N'Herbicida sistémico post-emergente.'),  -- Pacto
    (N'10115', N'Ofrece control completo de ácaros y algunas plagas.'),  -- Pirofen
    (N'10047', N'Inhibe la biosíntesis de melanina en el patógeno y evitando su penetración en los tejidos de la planta.'),  -- Piryzol 750 WP
    (N'10179', N'Inhibidor de síntesis de ácidos grasos de cadena muy larga.'),  -- Potency
    (N'10158', N'Agonista nicotínico.'),  -- PQM Bacco Power
    (N'10083', N'Inhibidor de VLCFA.'),  -- Prime-S
    (N'10118', N'Ofrece rápido efecto de volteo, acción ovicida y residual prolongada'),  -- Quintal Xtra
    (N'10085', N'Herbicida hormonal sistémico, post-emergente.'),  -- Randon
    (N'10087', N'Herbicida de contacto no selectivo de acción extremadamente rápida.'),  -- Secaforte
    (N'10184', N'Es un herbicida selectivo de acción residual.'),  -- Seguro
    (N'10160', N'Herbicida pre-emergente residual con efecto de contacto en germinación.'),  -- Sharfentrazone
    (N'10091', N'Herbicida post-emergente sistémico de acción hormonal.'),  -- Starane Xtra
    (N'10172', N'Es efectivo contra múltiples enfermedades foliares, como manchas, roya y antracnosis en en el cultivo de soya.'),  -- Strada
    (N'10120', N'Agonista de la hormona de muda.'),  -- Sumo
    (N'10048', N'Fungicida sistémico triazol que inhibe la biosíntesis de ergosterol en hongos.'),  -- Supress
    (N'10157', N'Surex es un potente compatibilizante de mezclas.'),  -- Surex RS
    (N'10161', N'Inhibidor de ALS + auxina sintética.'),  -- Texaro
    (N'10123', N'Insecticida sistémico eficaz.'),  -- Transform
    (N'10181', N'Ofrece un control integral de plagas y ácaros en diversos cultivos.'),  -- Trategy
    (N'10159', N'Es un fungicida sistémico diseñado para el control de enfermedades foliares en cereales y soja, como roya y oídio.'),  -- Triler PRO
    (N'20005', N'Voyager es un fertilizante líquido a base de sulfuro de potasio, con 450 g/L de potasio soluble en agua y 450 g/L de azufre.'),  -- Voyager
    (N'10092', N'Herbicida sistémico residual pre y post-emergente.')  -- Zetapyr 100 SL
) AS v(codigo_articulo, descripcion)
  ON v.codigo_articulo = p.codigo_articulo;

-- Verificación
SELECT COUNT(*) AS con_descripcion FROM maestros.producto WHERE descripcion IS NOT NULL;  -- esperado: 65
SELECT codigo_articulo, nombre FROM maestros.producto WHERE descripcion IS NULL ORDER BY nombre;  -- los 25 de arriba
