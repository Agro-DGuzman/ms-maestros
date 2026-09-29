/* =====================================================================
   Propiedades de PRUEBA - relaciones con socios INVENTADAS
   ---------------------------------------------------------------------
   Los 267 ids y nombres son reales (vienen de la tabla que se va a
   conciliar), pero el socio de cada una es inventado: se reparten entre
   los ocho socios de database/semillas/maestros-ejemplo.json para que la
   App tenga algo que mostrar mientras llega la conciliación.

     C-004871  25   el socio del número de prueba 70741828 (p-8f2b1c40):
                    lista larga, más que el tope de 20 de una solicitud
     C-004872   4   lista corta
     C-004873   0   la pantalla vacía
     el resto       parejo entre C-005210, C-005211, C-006004, C-006005
                    y C-007700 (otros grupos: no se ven con p-8f2b1c40)

   Ningún socio recibe dos propiedades con el mismo nombre. Los nombres van
   tal cual, porque tienen que coincidir con los de los reportes de campo.

   Resguardos:
   - Si falta alguno de esos socios en maestros.socios, no carga nada: sin
     la semilla, estas filas quedarían huérfanas.
   - Solo inserta ids que no existen: nunca pisa una propiedad ya cargada,
     por ejemplo una que ya trajo la conciliación con su socio real.
   - Con @deshacer = 1 borra exactamente estas filas (id y socio inventado),
     y deja las que la conciliación ya haya corregido.

   Correrlo (el -f 65001 es para que lea los acentos como UTF-8):

     docker cp database/sql/maestros-propiedades-prueba.sql ms-maestros-sqlserver-1:/tmp/
     MSYS_NO_PATHCONV=1 docker exec ms-maestros-sqlserver-1 /opt/mssql-tools18/bin/sqlcmd \
       -S localhost -U sa -P "Agro.Local.2026" -C -I -f 65001 -d maestros \
       -i /tmp/maestros-propiedades-prueba.sql

   Generado desde la lista de nombres del 29/09/2026.
   ===================================================================== */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @deshacer BIT = 0;

DECLARE @prueba TABLE (
    id_propiedad    NVARCHAR(50)  NOT NULL PRIMARY KEY,
    nombre          NVARCHAR(200) NOT NULL,
    codigo_de_socio NVARCHAR(15)  NOT NULL
);

INSERT INTO @prueba (id_propiedad, nombre, codigo_de_socio) VALUES
    (N'017e69e9', N'Río Nuevo', N'C-004871'),
    (N'02f248f0', N'MAURI', N'C-004871'),
    (N'034d4ffc', N'GARZA BLANCA', N'C-004871'),
    (N'035f1e21', N'Santa Elena', N'C-004871'),
    (N'03c09201', N'Carlos Zarate-Faja Sucre', N'C-004871'),
    (N'0459bc65', N'La Morita', N'C-004871'),
    (N'052bf0cd', N'Calixto Barja Sindicato el 8', N'C-004871'),
    (N'064aabdd', N'Ivatuba', N'C-004871'),
    (N'0a68590a', N'San Diego', N'C-004871'),
    (N'0ac5d2d9', N'Guapilo', N'C-004871'),
    (N'0aeb6fd6', N'El Triunfo', N'C-004871'),
    (N'0bee5e7f', N'Sindicato Monte Cristo', N'C-004871'),
    (N'0cbda945', N'Meztizos', N'C-004871'),
    (N'0e3ebd50', N'Totai', N'C-004871'),
    (N'113162be', N'Guaraguazú', N'C-004871'),
    (N'125d352b', N'La caña', N'C-004871'),
    (N'13436680', N'Chontal', N'C-004871'),
    (N'137ced54', N'Chane', N'C-004871'),
    (N'13bb01d6', N'Las mangas', N'C-004871'),
    (N'1457ff21', N'Belén 2', N'C-004871'),
    (N'14738f6f', N'Lorena', N'C-004871'),
    (N'1518d02a', N'Freddy Quichu-San Julian', N'C-004871'),
    (N'1626c3a0', N'Las Lomas', N'C-004871'),
    (N'1695c1b7', N'Roly Cartagena', N'C-004871'),
    (N'16d05ab0', N'Cupesi', N'C-004871'),
    (N'16da8eab', N'TROMPILLO', N'C-004872'),
    (N'16f551ae', N'Los Chivos', N'C-004872'),
    (N'19915780', N'Osmar Muñoz-San Julian', N'C-004872'),
    (N'19eee51c', N'San jorge', N'C-004872'),
    (N'1abb724e', N'La Esmeralda', N'C-005210'),
    (N'1b40c74a', N'El Carmen', N'C-005211'),
    (N'1bd24564', N'Palmarito', N'C-006004'),
    (N'1bddd480', N'Sindicato Topater', N'C-006005'),
    (N'1c9fbf6f', N'Gravetal', N'C-007700'),
    (N'1cb6b59d', N'Los Andes-Planchada 2', N'C-005210'),
    (N'1cc22622', N'AGRONOGALES', N'C-005211'),
    (N'1d9bfead', N'San Pedrito', N'C-006004'),
    (N'1e87e296', N'SANTA FE', N'C-006005'),
    (N'1eda7700', N'Pueblos Unidos', N'C-007700'),
    (N'1f64d0b2', N'Sonrisos', N'C-005210'),
    (N'1fdf6dca', N'La Pista', N'C-005211'),
    (N'21bb4e55', N'Guadalupe', N'C-006004'),
    (N'21c3d13a', N'Garnica', N'C-006005'),
    (N'22f7b6ae', N'Villa don Bosco', N'C-007700'),
    (N'23395adf', N'RINCON DE JUKIÑA', N'C-005210'),
    (N'253d8b40', N'Santa Rosa', N'C-005211'),
    (N'281adc37', N'BBA', N'C-006004'),
    (N'287ccfb0', N'Monica', N'C-006005'),
    (N'2883512a', N'Agro Paraiso', N'C-007700'),
    (N'29ed3240', N'Guineal', N'C-005210'),
    (N'2aa1066b', N'Urasai', N'C-005211'),
    (N'2b5027e0', N'El pionero', N'C-006004'),
    (N'2be38b8b', N'Cotoquita', N'C-006005'),
    (N'2c1a60b6', N'Las Parabas', N'C-007700'),
    (N'2dc1937f', N'Prosperagro', N'C-005210'),
    (N'2dd7682e', N'Santa Martha', N'C-005211'),
    (N'2f78f9cb', N'Clever Viruez-19 Sínd. el 8', N'C-006004'),
    (N'2ff46360', N'Colonia Belice', N'C-006005'),
    (N'3140a89b', N'Guarayito', N'C-007700'),
    (N'3213c033', N'Uberaba', N'C-005210'),
    (N'32629ce6', N'San pedro-Casa blanca', N'C-005211'),
    (N'33c296c6', N'Oscar Mayon-Peta Grande', N'C-006004'),
    (N'33fa458d', N'Cinco estrellas', N'C-006005'),
    (N'34da901e', N'Sotagsa', N'C-007700'),
    (N'35640940', N'San pedro-Planchada', N'C-005210'),
    (N'35c3677b', N'AgroBurgos-Monterrey', N'C-005211'),
    (N'360fcdc4', N'Guipar', N'C-006004'),
    (N'378cedde', N'San Jose', N'C-006005'),
    (N'38b119c8', N'SAN MATEO', N'C-007700'),
    (N'38b5a6a1', N'Santo Domingo', N'C-005210'),
    (N'38e16d88', N'El Golfo', N'C-005211'),
    (N'39065926', N'Agro Undyck-Nueva Asuncion', N'C-006004'),
    (N'392526a6', N'Nueva India', N'C-006005'),
    (N'39541d7b', N'Primavera', N'C-007700'),
    (N'3a70eeea', N'Rio Grande', N'C-005210'),
    (N'3be435e8', N'Campo hermoso', N'C-005211'),
    (N'3c595100', N'El Pauro', N'C-006004'),
    (N'3dbc4857', N'San Antonio2', N'C-006005'),
    (N'3e535bec', N'NOVAGRO', N'C-007700'),
    (N'40c60dce', N'Ciasa Sur', N'C-005210'),
    (N'4164585d', N'Nuevo horizonte-(Alquiler)', N'C-005211'),
    (N'41a5a18a', N'SURUNGUITAL', N'C-006004'),
    (N'42ed7107', N'San Diego', N'C-006005'),
    (N'4456627d', N'LUIS ANGEL MARTINEZ', N'C-007700'),
    (N'44c78ea8', N'Indiana', N'C-005210'),
    (N'45f1b978', N'San Rafael', N'C-005211'),
    (N'46342040', N'Tacuari', N'C-006004'),
    (N'4793b332', N'El horizonte', N'C-006005'),
    (N'49224142', N'RIO VERDE', N'C-007700'),
    (N'49231840', N'San Miguel', N'C-005210'),
    (N'4ad0f91a', N'Colonia Las piedras', N'C-005211'),
    (N'4d32deff', N'Monte Grande', N'C-006004'),
    (N'4de74172', N'AgroNogales', N'C-006005'),
    (N'4e2d9678', N'Las Cachas', N'C-007700'),
    (N'4e441d4a', N'Porvenir', N'C-005210'),
    (N'4f22544b', N'Bazurero-San pedro', N'C-005211'),
    (N'50c1dc29', N'Monte Onda', N'C-006004'),
    (N'520ce7a6', N'Puquio', N'C-006005'),
    (N'5478184f', N'Sofia', N'C-007700'),
    (N'577a5a14', N'Monica Sa', N'C-005210'),
    (N'578ad6b6', N'Esteban Martinez-San Julian', N'C-005211'),
    (N'599aac4f', N'FumiAgro', N'C-006004'),
    (N'59ed87e0', N'Los Compadres', N'C-006005'),
    (N'5b6179b9', N'Modelo', N'C-007700'),
    (N'5c2fc93b', N'Alex Garcia Vargas', N'C-005210'),
    (N'5c3ea4be', N'Ramos y San Andres', N'C-005211'),
    (N'5c6ee3d3', N'Urasai 2', N'C-006004'),
    (N'5e68a839', N'La Esmeralda-Joci', N'C-006005'),
    (N'5feec640', N'Yañez', N'C-007700'),
    (N'60457180', N'Las Petas', N'C-005210'),
    (N'61b23508', N'Cupesi', N'C-005211'),
    (N'622fdf77', N'Guadalupe', N'C-006005'),
    (N'6305ef36', N'El Trompillo', N'C-007700'),
    (N'639afe8c', N'Don chito', N'C-005210'),
    (N'66da8925', N'Tamarindo', N'C-005211'),
    (N'67ea9a73', N'Curichi', N'C-006004'),
    (N'6acf0534', N'Toco 2', N'C-006005'),
    (N'6c0d09ab', N'Jacob wiebe - san pablo', N'C-007700'),
    (N'6cd8bdfa', N'Los sapitos', N'C-005210'),
    (N'6e5fcf17', N'Laguna España', N'C-005211'),
    (N'6e9013a7', N'Andes-mandarino', N'C-006004'),
    (N'700f04c9', N'Catalina Loza-Guadalupe', N'C-006005'),
    (N'703122ed', N'FLOR DEL VALLE', N'C-007700'),
    (N'7132f33c', N'San Gabriel', N'C-005210'),
    (N'721ab272', N'San Lorenzo', N'C-005211'),
    (N'72adca87', N'Monta Alto', N'C-006004'),
    (N'73609592', N'FAIZAN', N'C-006005'),
    (N'736fcdf4', N'Casarabe', N'C-007700'),
    (N'738a5709', N'6 hermanos', N'C-005210'),
    (N'76598782', N'Las Londras', N'C-005211'),
    (N'76a345b0', N'PERSEVERANCIA', N'C-006004'),
    (N'77e58f7c', N'Marilu', N'C-006005'),
    (N'796bddd2', N'Pailkn', N'C-007700'),
    (N'79dd3490', N'Shyo Ogata-Km 32', N'C-005210'),
    (N'7a154cce', N'Sausalito', N'C-005211'),
    (N'7a36a59a', N'Sara', N'C-006004'),
    (N'7a36f90c', N'Guadacruz', N'C-006005'),
    (N'7a42af0c', N'Cristo Rey', N'C-007700'),
    (N'7b7040b1', N'Feliciano Martinez - las brechas', N'C-005210'),
    (N'7b77f0fd', N'Sindicato Topater', N'C-005211'),
    (N'7e5ef93a', N'Juan Rocha-San Jose', N'C-006004'),
    (N'7f8bf5f4', N'Pascanita', N'C-006005'),
    (N'803ab71c', N'Capital', N'C-007700'),
    (N'814358c4', N'San Pedrito', N'C-005210'),
    (N'81ae9d1e', N'Sagrado Corazón-Sind. Monte Cristo', N'C-005211'),
    (N'81ec639b', N'Constantina Paniagua-Agroespani', N'C-006004'),
    (N'83d561c4', N'Palermo', N'C-006005'),
    (N'8550d00e', N'SAN MATEO', N'C-005210'),
    (N'8782457d', N'Adgair Bastidas_faja Juventud 2', N'C-005211'),
    (N'879d019f', N'Guayacan', N'C-006004'),
    (N'8999886e', N'Primitivo Paramo-Peta tercera', N'C-006005'),
    (N'89ad3fa5', N'AGROWIEBE S.R.L-MANITOBA', N'C-007700'),
    (N'8a3b3668', N'K de Oro', N'C-005210'),
    (N'8ad769df', N'El Carmen', N'C-006004'),
    (N'8c55099b', N'San Jones', N'C-006005'),
    (N'8cd91007', N'Ivatuba', N'C-007700'),
    (N'8d9b79d0', N'Bahia', N'C-005210'),
    (N'8e1d9791', N'Litaceg srl', N'C-005211'),
    (N'8e4d04bd', N'AgroVelarde', N'C-006004'),
    (N'8ef01791', N'San Pedrito', N'C-006005'),
    (N'8f439b34', N'San Francisco', N'C-007700'),
    (N'8f92f76a', N'Puesto Sonia', N'C-005210'),
    (N'903a4aec', N'Efrain', N'C-005211'),
    (N'91f747b4', N'Santa rosa - buen retiro', N'C-006004'),
    (N'93b42ca4', N'La Ponderosa', N'C-006005'),
    (N'94d41edd', N'Faja Juventud_Primitivo Flores', N'C-007700'),
    (N'954993a6', N'Emeterio Luizaga- El Carmen', N'C-005210'),
    (N'9699d37f', N'Mira Flores', N'C-005211'),
    (N'96dc0bd7', N'AGROCALY-MANITOVA', N'C-006004'),
    (N'990884d4', N'La Bonita', N'C-006005'),
    (N'99df11fa', N'Al Golfo', N'C-007700'),
    (N'9b2d1966', N'Los Angeles', N'C-005210'),
    (N'9c507abb', N'Francisco Peters-Río Negro', N'C-005211'),
    (N'9c6b15b6', N'Agroinga', N'C-006004'),
    (N'9d03bceb', N'Colamarino', N'C-006005'),
    (N'9dea0462', N'Interagro', N'C-007700'),
    (N'9e9f2409', N'Panama III', N'C-005210'),
    (N'9ef57877', N'Navidad-Peta Grande', N'C-005211'),
    (N'a005f7df', N'Churapa', N'C-006004'),
    (N'a03611d0', N'Retiro', N'C-006005'),
    (N'a185c1e4', N'AGROSAC', N'C-007700'),
    (N'a1f06876', N'Algarrobal', N'C-005210'),
    (N'a26c59fb', N'Copacabana', N'C-005211'),
    (N'a354dc86', N'Bajío', N'C-006004'),
    (N'a38fdf33', N'Kiyoshi miyamae', N'C-006005'),
    (N'a3fe7c86', N'La Conquista', N'C-007700'),
    (N'a56c2b42', N'Carlos Quiroz-Chané Magallanes', N'C-005210'),
    (N'a64070b6', N'19 de Agosto Wilson Porcel', N'C-005211'),
    (N'a93b00cc', N'Zona - Peta grande', N'C-006004'),
    (N'ab230459', N'San Miguelito', N'C-006005'),
    (N'ab9c2a93', N'Los Olivos', N'C-007700'),
    (N'aed6887b', N'Javier Apudaca-Guadalupe', N'C-005210'),
    (N'afe57ebe', N'Mira Flores', N'C-006004'),
    (N'b091f4b7', N'Florai', N'C-006005'),
    (N'b43e358b', N'Sausalito', N'C-007700'),
    (N'b565666b', N'Llanuras de israel', N'C-005210'),
    (N'b5d58ccc', N'Urkupiña', N'C-005211'),
    (N'b6d8a997', N'Freddy Taboada - las brechas', N'C-006004'),
    (N'b6e5b482', N'Rolando Romero Sind. El 8', N'C-006005'),
    (N'b77c4a24', N'Colonia tajibo', N'C-007700'),
    (N'b88fb033', N'Primitivo Paramo-Pirai', N'C-005210'),
    (N'badd24bc', N'Poker de Jota', N'C-005211'),
    (N'bda87fcf', N'AgroAltamirano', N'C-006004'),
    (N'c10c1961', N'Colonia Belice', N'C-007700'),
    (N'c44aa09a', N'Gladis Orellana-La Planchada', N'C-005210'),
    (N'c4671454', N'AgroPower', N'C-005211'),
    (N'c4c3139c', N'AgroBurgos-Murillo', N'C-006004'),
    (N'c94d4dfc', N'Caribe', N'C-006005'),
    (N'caddb756', N'Indiana', N'C-007700'),
    (N'cbcf3582', N'DARIO ROMERO PINTO', N'C-005210'),
    (N'cc68ad19', N'Los Andes - rio nuevo', N'C-005211'),
    (N'ccd5ecdc', N'BONARIA', N'C-006004'),
    (N'cd221e5b', N'Chaco Condory', N'C-006005'),
    (N'cfaeeec9', N'Rio Victoria', N'C-007700'),
    (N'cff6202a', N'Augusto Ramirez-Los Andes', N'C-005210'),
    (N'd106c422', N'Estanislao Romero-19 de Agosto', N'C-005211'),
    (N'd16d8b17', N'Faja Bolivar', N'C-006004'),
    (N'd1d5e6d7', N'Mario Rodríguez-Miraflores', N'C-006005'),
    (N'd35f2690', N'LOS ROJAS', N'C-007700'),
    (N'd3fa139c', N'Zona - Canandoa', N'C-005210'),
    (N'd4f95aa6', N'El Trompillo', N'C-005211'),
    (N'd5d162a7', N'San Antonio', N'C-006004'),
    (N'd6e8109c', N'AGROCALY-CALIFORNIA', N'C-006005'),
    (N'd8810d46', N'Limoncito-Primavera', N'C-007700'),
    (N'daae74cc', N'FumiAgro', N'C-005210'),
    (N'dad37572', N'AGRO DALE', N'C-005211'),
    (N'dbabbb62', N'Chaco Lejos', N'C-006004'),
    (N'dca44290', N'LA MONEDA', N'C-006005'),
    (N'dcef206f', N'Monica Norte', N'C-007700'),
    (N'dd16ddc2', N'El Encanto', N'C-005210'),
    (N'ded2145f', N'Santa Barbara', N'C-005211'),
    (N'e2579e0e', N'CORNELIUS GUINTER-BERTAL', N'C-006004'),
    (N'e2b65b78', N'Santa Anita', N'C-006005'),
    (N'e3adfa68', N'Cerro Alto', N'C-007700'),
    (N'e51f9008', N'Ramiro Condori_El Trompillo', N'C-005210'),
    (N'e57362e9', N'Claudio Diaz-San Julian', N'C-005211'),
    (N'e5972692', N'Imperio- La planchada', N'C-006004'),
    (N'e60db994', N'COL. PORVENIR ORIENTE', N'C-006005'),
    (N'e63c2f55', N'Pecus', N'C-007700'),
    (N'e6de0698', N'Agrofuerza', N'C-005210'),
    (N'e85a6f64', N'Don Manuel', N'C-005211'),
    (N'e998408e', N'Shunichi Iwase-Chapacos', N'C-006004'),
    (N'ea015668', N'Canta Rana', N'C-006005'),
    (N'ecb054af', N'San Antonio', N'C-007700'),
    (N'eeaaf156', N'San Rafael', N'C-005210'),
    (N'eef45dcd', N'Ambaibo', N'C-005211'),
    (N'ef4638c6', N'El Refugio', N'C-006004'),
    (N'efe5a917', N'Primitivo Paramo-2 de junio', N'C-006005'),
    (N'f0012757', N'El Carmen', N'C-007700'),
    (N'f06356d8', N'Agronaciente', N'C-005210'),
    (N'f16008f6', N'SOGIMA, San Julian', N'C-005211'),
    (N'f20545dc', N'Padua', N'C-006004'),
    (N'f4acb680', N'La Flauta', N'C-006005'),
    (N'f5c71430', N'Chihuahua', N'C-007700'),
    (N'f606868d', N'La Conquista2', N'C-005210'),
    (N'f6c17b27', N'La chuta', N'C-005211'),
    (N'f72b0bfe', N'Limber Condori-Chané Magallan', N'C-006004'),
    (N'f79394a6', N'Hacienda Curichi', N'C-006005'),
    (N'f8b7b79a', N'Nataly', N'C-007700'),
    (N'f8d87290', N'LA BENDECIDA', N'C-005210'),
    (N'f8fef6de', N'Turbion', N'C-005211'),
    (N'f967ad41', N'Calixto Barja-19 de agosto', N'C-006004'),
    (N'fa916fe8', N'Marotas', N'C-006005'),
    (N'fd4415c1', N'NUEVO HORIZONTE', N'C-007700'),
    (N'fea6ab65', N'Colonia Berlin', N'C-005210'),
    (N'ff330f20', N'San Martin', N'C-005211'),
    (N'ff3c3071', N'Limoncito', N'C-006004');

IF EXISTS (SELECT 1 FROM @prueba p
           WHERE NOT EXISTS (SELECT 1 FROM maestros.socios s
                             WHERE s.codigo_de_socio = p.codigo_de_socio COLLATE Latin1_General_BIN2))
BEGIN
    SELECT DISTINCT p.codigo_de_socio AS socio_que_falta
    FROM @prueba p
    WHERE NOT EXISTS (SELECT 1 FROM maestros.socios s
                      WHERE s.codigo_de_socio = p.codigo_de_socio COLLATE Latin1_General_BIN2);
    THROW 50001, N'Faltan socios de la semilla en maestros.socios: no se cargó nada.', 1;
END;

BEGIN TRANSACTION;

IF @deshacer = 1
BEGIN
    DELETE d
    FROM maestros.propiedad d
    JOIN @prueba p ON p.id_propiedad = d.id_propiedad AND p.codigo_de_socio = d.codigo_de_socio;
    PRINT CONCAT(N'Borradas: ', @@ROWCOUNT);
END
ELSE
BEGIN
    INSERT INTO maestros.propiedad (id_propiedad, nombre, codigo_de_socio)
    SELECT p.id_propiedad, p.nombre, p.codigo_de_socio
    FROM @prueba p
    WHERE NOT EXISTS (SELECT 1 FROM maestros.propiedad d WHERE d.id_propiedad = p.id_propiedad);
    PRINT CONCAT(N'Insertadas: ', @@ROWCOUNT);
END;

COMMIT;

SELECT p.codigo_de_socio, COUNT(*) AS propiedades
FROM maestros.propiedad p
JOIN @prueba x ON x.id_propiedad = p.id_propiedad
GROUP BY p.codigo_de_socio
ORDER BY p.codigo_de_socio;
