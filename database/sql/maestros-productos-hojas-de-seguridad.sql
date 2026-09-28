/* =====================================================================
   Hojas de seguridad de los productos (fuente: web)
   Ejecutar después de maestros-productos-carga-inicial.sql
   ---------------------------------------------------------------------
   Van a hoja_seguridad_url: GET /productos/{itemCode}/documentos las
   entrega como «Hoja de seguridad · <producto>». 87 productos.

   - Las URL con ® o ™ van codificadas (%C2%AE, %E2%84%A2): el contrato pide
     formato uri. Es el mismo archivo.
   - Quedan SIN hoja de seguridad, a la espera de la correcta:
       Crop Oil (apunta a la de Alpha Tank), Surex RS (apunta a la de Remove RS);
       Transform (sin URL en la web).
   - Idempotente. Con sqlcmd, correrlo con -I.
   ===================================================================== */

SET NOCOUNT ON;

UPDATE p SET
    p.hoja_seguridad_url = v.hoja_seguridad_url,
    p.actualizado_en     = SYSDATETIME()
FROM maestros.producto p
JOIN (VALUES
    (N'10093', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Abamex-Plus-Hoja-de-Seguridad.pdf'),  -- Abamex Plus
    (N'10094', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Agroalpha-Hoja-de-Seguridad.pdf'),  -- Agroalpha
    (N'10014', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Agrogibe-4-EC-Hoja-de-Seguridad-.pdf'),  -- Agrogibe 4% EC
    (N'10095', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Agrogite-Ficha-de-datos-de-Seguridad-nueva_compressed.pdf'),  -- Agrogite
    (N'10097', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Agrothrin-Hoja-de-Seguridad.pdf'),  -- Agrothrin
    (N'10056', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Agrozina-50-SC-Hoja-de-Seguridad.pdf'),  -- Agrozina 50 SC
    (N'10015', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Alga-300-Hoja-de-Seguridad.pdf'),  -- Alga 300++
    (N'10180', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Alvo-Ficha-de-datos-de-Seguridad.pdf'),  -- Alvo
    (N'10016', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Amino-Total-Hoja-de-Seguridad.pdf'),  -- Amino Total
    (N'10038', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Aproach%C2%AE-Prima-Ficha-de-datos-de-Seguridad.pdf'),  -- Aproach Prima
    (N'10174', N'https://www.agropartners.com.bo/wp-content/uploads/2025/09/Athlon-Hoja-de-Seguridad.pdf'),  -- Athlon
    (N'10057', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Benton-Hoja-de-Seguridad.pdf'),  -- Benton
    (N'10058', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Charter-240-EC-Hoja-de-Seguridad.pdf'),  -- Charter 240 EC
    (N'10060', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Clomax-480-EC-Hoja-de-Seguridad.pdf'),  -- Clomax 480 EC
    (N'10061', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Clorimur-250-WP-Hoja-de-Seguridad_compressed.pdf'),  -- Clorimur 250 WP
    (N'10041', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Concert-Hoja-de-Seguridad.pdf'),  -- Concert
    (N'10099', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Dacloprid-350-SC-Hoja-de-Seguridad.pdf'),  -- Dacloprid 350 SC
    (N'10100', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Delete-Hoja-de-Seguridad.pdf'),  -- Delete
    (N'10101', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Dinno-Hoja-de-Seguridad.pdf'),  -- Dinno
    (N'10042', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Dueto-Pro-Hoja-de-Seguridad.pdf'),  -- Dueto Pro
    (N'10175', N'https://www.agropartners.com.bo/wp-content/uploads/2025/09/Efyra-Hoja-de-Seguridad.pdf'),  -- Efyra
    (N'10182', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Eiru-Hoja-de-Seguridad.pdf'),  -- Eiru
    (N'10102', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Exalt%C2%AE-Ficha-de-datos-de-Seguridad.pdf'),  -- Exalt
    (N'10103', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Fatal-480-EC-Ficha-de-datos-de-Seguridad.pdf'),  -- Fatal 480 EC
    (N'10066', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Flumioxazol-48-Hoja-de-Seguridad.pdf'),  -- Flumioxazol 48
    (N'10105', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Flycontrol-Hoja-de-Seguridad.pdf'),  -- Flycontrol
    (N'10067', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Galactic-250-SL-Hoja-de-Seguridad.pdf'),  -- Galactic 250 SL
    (N'10153', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Galant%E2%84%A2-MAX-Ficha-de-datos-de-Seguridad.pdf'),  -- Galant MAX
    (N'10072', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Gliforte-Plus-Hoja-de-Seguridad.pdf'),  -- Gliforte Plus
    (N'10074', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Gluforte-Hoja-de-Seguridad.pdf'),  -- Gluforte
    (N'10017', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Grap-104-Fluid-Hoja-de-Seguridad.pdf'),  -- Grap 104 Fluid
    (N'10019', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Grap-30K-Hoja-de-Seguridad.pdf'),  -- Grap 30K
    (N'10021', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Grap-Amyno-15-Hoja-de-Seguridad.pdf'),  -- Grap Amyno
    (N'10022', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Grap-Boric-Hoja-de-Seguridad.pdf'),  -- Grap Boric
    (N'10010', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Grap-D-Lim-Hoja-de-Seguridad.pdf'),  -- Grap D-Lim
    (N'10024', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Grap-Evic-S-Hoja-de-Seguridad.pdf'),  -- Grap Evic-S
    (N'10025', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Grap-Grad-Hoja-de-Seguridad.pdf'),  -- Grap Grad
    (N'10029', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Grap-Mont-15-Hoja-de-Seguridad.pdf'),  -- Grap Mont 15
    (N'10031', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Grap-Nitro-Hoja-de-Seguridad.pdf'),  -- Grap Nitro
    (N'10135', N'https://www.agropartners.com.bo/wp-content/uploads/2025/09/Grap-Nod-Al-Hoja-de-Seguridad.pdf'),  -- Grap Nod AL
    (N'10136', N'https://www.agropartners.com.bo/wp-content/uploads/2025/09/Grap-Nod-L-Hoja-de-Seguridad.pdf'),  -- Grap Nod L
    (N'10011', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Grap-Sensor-Hoja-de-Seguridad.pdf'),  -- Grap Sensor
    (N'10012', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Grap-Super-Gun-Hoja-de-Seguridad.pdf'),  -- Grap Super Gun
    (N'10044', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Innova-Ficha-de-datos-de-Seguridad.pdf'),  -- Innova
    (N'10106', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Intrepid%C2%AESC-Hoja-de-Seguridad.pdf'),  -- Intrepid SC
    (N'10001', N'https://www.agropartners.com.bo/wp-content/uploads/2025/09/Invictus-Hoja-de-Seguridad.pdf'),  -- Invictus
    (N'10107', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Joker-Hoja-de-Seguridad.pdf'),  -- Joker
    (N'10045', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Kuprex-Hoja-de-Seguridad.pdf'),  -- Kuprex
    (N'10108', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Lambdatrin-50-EC-Hoja-de-Seguridad.pdf'),  -- Lambdatrin 50 EC
    (N'10033', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Leili-2000-Hoja-de-Seguridad.pdf'),  -- Leili 2000
    (N'10078', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Leser-Ficha-de-datos-de-Seguridad.pdf'),  -- Leser
    (N'10079', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Linear-Ficha-de-datos-de-Seguridad_compressed.pdf'),  -- Linear
    (N'10013', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Loop-RS-Hoja-de-Seguridad.pdf'),  -- Loop RS
    (N'10046', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Mancoparts-Hoja-de-Seguridad.pdf'),  -- Mancoparts
    (N'10080', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Mazapyr-250-SL-Hoja-de-Seguridad.pdf'),  -- Mazapyr 250 SL
    (N'10110', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Nanofos-Hoja-de-Seguridad.pdf'),  -- Nanofos
    (N'10113', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/NC-Mectin-Hoja-de-Seguridad.pdf'),  -- NC-Mectin
    (N'10081', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Nifuron-75-WG-Hoja-de-Seguridad.pdf'),  -- Nifuron 75 WG
    (N'10114', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Nion-48-SC-Hoja-de-Seguridad.pdf'),  -- Nion 48 SC
    (N'10082', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Pacto%C2%AE-Hoja-de-Seguridad.pdf'),  -- Pacto
    (N'10115', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Pirofen-Ficha-de-datos-de-Seguridad.pdf'),  -- Pirofen
    (N'10047', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Piryzol-750-WP-Hoja-de-Seguridad.pdf'),  -- Piryzol 750 WP
    (N'10179', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Potency-Ficha-de-datos-de-Seguridad.pdf'),  -- Potency
    (N'10158', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/PQM-Bacco-Power-Hoja-de-Seguridad.pdf'),  -- PQM Bacco Power
    (N'10083', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Prime-S-Hoja-de-Seguridad.pdf'),  -- Prime-S
    (N'10118', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Quintal%C2%AEXtra-Hoja-de-Seguridad.pdf'),  -- Quintal Xtra
    (N'10085', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Randon-Hoja-de-Seguridad-Actualizado.pdf'),  -- Randon
    (N'10156', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Remove-RS-Hoja-de-seguridad.pdf'),  -- Remove RS
    (N'10087', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Secaforte-200-SL-Hoja-de-Seguridad.pdf'),  -- Secaforte
    (N'10184', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Seguro-Hoja-de-Seguridad.pdf'),  -- Seguro
    (N'10160', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Sharfentrazone-Hoja-de-Seguridad.pdf'),  -- Sharfentrazone
    (N'10091', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Starane%C2%AE-Xtra-Hoja-de-Seguridad.pdf'),  -- Starane Xtra
    (N'10172', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Strada-Ficha-de-datos-de-Seguridad.pdf'),  -- Strada
    (N'10120', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Sumo-Hoja-de-Seguridad.pdf'),  -- Sumo
    (N'10048', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Supress-Ficha-de-datos-de-Seguridad.pdf'),  -- Supress
    (N'10161', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Texaro%C2%AE-Hoja-de-Seguridad.pdf'),  -- Texaro
    (N'10003', N'https://www.agropartners.com.bo/wp-content/uploads/2025/09/Thanos-Hoja-de-Seguridad.pdf'),  -- Thanos
    (N'10150', N'https://www.agropartners.com.bo/wp-content/uploads/2025/09/Themis-Hoja-de-Seguridad.pdf'),  -- Themis
    (N'10176', N'https://www.agropartners.com.bo/wp-content/uploads/2025/09/Theron-Hoja-de-Seguridad.pdf'),  -- Theron
    (N'10121', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Thiametozix-70-Hoja-de-Seguridad.pdf'),  -- Thiametozix 70
    (N'10181', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Trategy-Ficha-de-datos-de-Seguridad.pdf'),  -- Trategy
    (N'10159', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Triler-PRO-Hoja-de-Seguridad.pdf'),  -- Triler PRO
    (N'10050', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Vessarya-%C2%AE-Hoja-de-Seguridad.pdf'),  -- Vessarya
    (N'10051', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Viovan%C2%AE-Hoja-de-Seguridad.pdf'),  -- Viovan
    (N'10004', N'https://www.agropartners.com.bo/wp-content/uploads/2025/09/Vitane-HC-Hoja-de-Seguridad.pdf'),  -- Vitane HC
    (N'20005', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Voyager-Hoja-de-Seguridad.pdf'),  -- Voyager
    (N'10092', N'https://www.agropartners.com.bo/wp-content/uploads/2025/10/Zetapyr-100-SL-Ficha-de-datos-de-Seguridad-nueva.pdf')  -- Zetapyr 100 SL
) AS v(codigo_articulo, hoja_seguridad_url)
  ON v.codigo_articulo = p.codigo_articulo;

-- Verificación
SELECT COUNT(*) AS con_hoja_de_seguridad FROM maestros.producto WHERE hoja_seguridad_url IS NOT NULL;  -- esperado: 87
