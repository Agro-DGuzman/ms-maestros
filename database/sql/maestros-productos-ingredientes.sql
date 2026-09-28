/* =====================================================================
   Ingredientes activos de los 90 productos
   Ejecutar después de maestros-productos-carga-inicial.sql
   ---------------------------------------------------------------------
   - Por codigo_articulo; el comentario de cada fila es el nombre.
   - Es lo que la ficha muestra como «Ingrediente activo»: en biológicos la
     cepa, en fertilizantes la composición.
   - Se quitó el envase que traían algunos («, envase de 5 Litros»): es
     presentación, no ingrediente.
   - Idempotente. Con sqlcmd, correrlo con -I.
   ===================================================================== */

SET NOCOUNT ON;

UPDATE p SET
    p.ingrediente_activo = v.ingrediente_activo,
    p.actualizado_en     = SYSDATETIME()
FROM maestros.producto p
JOIN (VALUES
    (N'10174', N'Bacillus subtilis CNPSo 2720 1.0 x 10^8 UFC/mL+Bacillus velezensis CNPSo 3602 1.0 x 10^8 UFC/mL+Bacillus pumilus CNPSo 3203 1.0 x 10^8 UFC/mL'),  -- Athlon
    (N'10175', N'Azospirillum brasilense (Ab/V6) 1.0 x 10^8 UFC/mL+Pseudomonas fluorescens (CCTB03) 1.0 x 10^8 UFC/mL'),  -- Efyra
    (N'10135', N'Azospirillum brasilense 2x10^8 celulas/mL'),  -- Grap Nod AL
    (N'10136', N'Bradyrhizobium japonicum 5x10^9 Celulas /mL'),  -- Grap Nod L
    (N'10001', N'Bacillus subtilis aislado 1 x 10^12 UFC/L'),  -- Invictus
    (N'10003', N'Bacillus thuringiensis'),  -- Thanos
    (N'10150', N'Bradyhizobium japonicum (Semia 5079 y 5080) 7 x 10^12 UFC/L'),  -- Themis
    (N'10176', N'Bacillus subtilis CCTB04 1.0 x 10^8 UFC/mL+Bacillus velezensis CCTB09 1.0 x 10^8 UFC/mL+Bacillus pumilus CCTB05 1.0 x 10^8 UFC/mL'),  -- Theron
    (N'10004', N'Bacillus aryabhattai 1 x 10 ^12 UFC/L'),  -- Vitane HC
    (N'10007', N'Paraffinic Petroleum Oil 720 g/L + Alcohol Ethoxylate 148 g/L SL'),  -- Crop Oil
    (N'10010', N'Nitrógeno 21,2 g/L + Boro 5,30 g/L'),  -- Grap D-Lim
    (N'10024', N'Nitrógeno 146,3 g/L + Azufre 332,5 g/L'),  -- Grap Evic-S
    (N'10011', N'Pentóxido de Fósforo 22 g/L'),  -- Grap Sensor
    (N'10012', N'Nitrógeno 60 g/L + Pentóxido de Fósforo 192 g/L'),  -- Grap Super Gun
    (N'10013', N'Nitrogeno 51,0 g/L'),  -- Loop RS
    (N'10156', N'Nitrogeno 55,0 g/L'),  -- Remove RS
    (N'10157', N'Manganeso 38,1 g/L'),  -- Surex RS
    (N'20005', N'Azufre (S soluble en H2O) 30% p/p (450 g/L) + Potasio (K2O solubre en H2O) 30% p/p (450 g/L)'),  -- Voyager
    (N'10014', N'Ácido Giberélico 40 g/L EC'),  -- Agrogibe 4% EC
    (N'10015', N'Nitrogeno total 90 g/L + Pentóxido de fósforo 40 g/L + Óxido de potasio 110 g/L + Extracto de algas 60 g/L'),  -- Alga 300++
    (N'10016', N'Aminoacidos total 43 g/Kg + Nitrogeno total 14 g/Kg SP'),  -- Amino Total
    (N'10017', N'Zinc 132 g/L + Azufre 52,8 g/L'),  -- Grap 104 Fluid
    (N'10019', N'Óxido de Potasio 435 g/L'),  -- Grap 30K
    (N'10021', N'Azufre 67,50 g/L + Boro 6,75 g/L + Cobre 6,75 g/L + Manganeso 81 g/L + Molibdeno 1,35 g/L + Zinc 40,5 g/L'),  -- Grap Amyno
    (N'10022', N'Boro 135 g/L'),  -- Grap Boric
    (N'10025', N'Magnesio 89,6 g/L'),  -- Grap Grad
    (N'10029', N'Azufre 70 g/L + Boro 7 g/L + Cobre 7 g/L + Manganeso 84 g/L + Molibdeno 1,4 g/L + Zinc 42 g/L'),  -- Grap Mont 15
    (N'10031', N'NItrógeno 390 g/L'),  -- Grap Nitro
    (N'10033', N'Acido alginico 14 g/L + Nitrogeno 95 g/L + Fe,Cu,Zn, Mn 40 g/L SL'),  -- Leili 2000
    (N'10038', N'Picoxystrobin 200 g/L + Cyproconazole 80 g/L SC'),  -- Aproach Prima
    (N'10041', N'Pyraclostrobin 150 g/L + Difenoconazole 150 g/L SC'),  -- Concert
    (N'10042', N'Picoxystrobin 200 g/L + Prothioconazole 175 g/L SC'),  -- Dueto Pro
    (N'10044', N'Tebuconazole 430 g/L SC'),  -- Innova
    (N'10045', N'Oxicloruro de Cobre 500 g/Kg WP'),  -- Kuprex
    (N'10046', N'Mancozeb 800 g/Kg WP'),  -- Mancoparts
    (N'10047', N'Tricyclazole 750 g/Kg WP'),  -- Piryzol 750 WP
    (N'10172', N'Clorothalonil 720 g/L SC'),  -- Strada
    (N'10048', N'Cyproconazole 400 g/L SC'),  -- Supress
    (N'10159', N'Prothioconazole 175 g/L + Trifloxystrobin 150 g/L SC'),  -- Triler PRO
    (N'10050', N'Picoxystrobin 100 g/L + Benzovindiflupyr 50 g/L EC'),  -- Vessarya
    (N'10051', N'Picoxystrobin 100,0 g/L + Prothioconazole 116,7 g/L EC'),  -- Viovan
    (N'10056', N'Atrazine 500 g/L SC'),  -- Agrozina 50 SC
    (N'10057', N'Bentazone 480 g/L SL'),  -- Benton
    (N'10058', N'Clethodim 240 g/L EC'),  -- Charter 240 EC
    (N'10060', N'Clomazone 480 g/L EC'),  -- Clomax 480 EC
    (N'10061', N'Chlorimuron-ethyl 250 g/Kg WP'),  -- Clorimur 250 WP
    (N'10182', N'2,4-D 700 g/L'),  -- Eiru
    (N'10066', N'Flumioxazin 480 g/L SC'),  -- Flumioxazol 48
    (N'10067', N'Fomesafen 250 g/L SL'),  -- Galactic 250 SL
    (N'10153', N'Haloxifop-R-methyl ester 935 g/L EC'),  -- Galant MAX
    (N'10072', N'Glyphosate Sal IPA 660 g/L SL'),  -- Gliforte Plus
    (N'10074', N'Glufosinate-ammonium 200 g/L SL'),  -- Gluforte
    (N'10078', N'Mesotrione 750 g/kgWG'),  -- Leser
    (N'10079', N'Hexazinone 132 g/Kg + Diuron 468 g/Kg WG'),  -- Linear
    (N'10080', N'Imazapyr 250 g/L SL'),  -- Mazapyr 250 SL
    (N'10081', N'Nicosulfuron 750 g/Kg WG'),  -- Nifuron 75 WG
    (N'10082', N'Chloransulam-methyl 840 g/Kg WG'),  -- Pacto
    (N'10179', N'Butachlor 900 g/kg EC'),  -- Potency
    (N'10083', N'S-metolachlor 960 g/L EC'),  -- Prime-S
    (N'10085', N'2,4-D 240 g/L + Picloram 64 g/L SL'),  -- Randon
    (N'10087', N'Paraquat Dichloride 276 g/L SL'),  -- Secaforte
    (N'10184', N'Metribuzin 700 g/Kg WG'),  -- Seguro
    (N'10160', N'Sulfentrazone 480 g/L SC'),  -- Sharfentrazone
    (N'10091', N'Fluroxypyr-meptyl 480 g/L EC'),  -- Starane Xtra
    (N'10161', N'DICLOSULAM + HALAUXIFEN-METHYL'),  -- Texaro
    (N'10092', N'Imazethapyr 100 g/L SL'),  -- Zetapyr 100 SL
    (N'10093', N'Abamectin 50 g/L EC'),  -- Abamex Plus
    (N'10094', N'Alpha-cypermethrin 100 g/L EC'),  -- Agroalpha
    (N'10095', N'Propargite 730 g/L EC'),  -- Agrogite
    (N'10097', N'Bifenthrin 100 g/L EC'),  -- Agrothrin
    (N'10180', N'Bifenthrin 280 g/L + Clothianidin 140 g/L'),  -- Alvo
    (N'10099', N'Imidacloprid 350 g/L SC'),  -- Dacloprid 350 SC
    (N'10100', N'Chlorfenapyr 240 g/L SC'),  -- Delete
    (N'10101', N'Dinotefuran 700 g/Kg WG'),  -- Dinno
    (N'10102', N'Spinetoram 120 g/L SC'),  -- Exalt
    (N'10103', N'Chlorpyrifos 480 g/L EC'),  -- Fatal 480 EC
    (N'10105', N'Acetamiprid 700 g/Kg WG'),  -- Flycontrol
    (N'10106', N'Methoxyfenoide 240 g/L SC'),  -- Intrepid SC
    (N'10107', N'Bifenthrin 180,2 g/L + Thiamethoxam 159 g/L SC'),  -- Joker
    (N'10108', N'Lambda-cyhalothrin 50 g/L EC'),  -- Lambdatrin 50 EC
    (N'10110', N'Chlorpyrifos 250 g/L ME'),  -- Nanofos
    (N'10113', N'Emamectin Benzoate 57 g/Kg WG'),  -- NC-Mectin
    (N'10114', N'Triflumuron 480 g/L SC'),  -- Nion 48 SC
    (N'10115', N'Spirodiclofen 60 g/L + Chlorfenapyr 240 g/L SC'),  -- Pirofen
    (N'10158', N'Dinotefuran 200 g/L + Bifenthrin 100 g/L EC'),  -- PQM Bacco Power
    (N'10118', N'Methoxyfenozide 240 g/L + Spinetoram 60 g/L SC'),  -- Quintal Xtra
    (N'10120', N'Chlorantraniliprole 200 g/L SC'),  -- Sumo
    (N'10121', N'Thiamethoxam 700 g/Kg WG'),  -- Thiametozix 70
    (N'10123', N'Sulfoxaflor 500 g/Kg WG'),  -- Transform
    (N'10181', N'Abamectin 50 g/L + Etoxazole 100 g/L SC')  -- Trategy
) AS v(codigo_articulo, ingrediente_activo)
  ON v.codigo_articulo = p.codigo_articulo;

-- Verificación
SELECT COUNT(*) AS con_ingrediente FROM maestros.producto WHERE ingrediente_activo IS NOT NULL;  -- esperado: 90
