/* =====================================================================
   Configuración de la App de ejemplo
   ---------------------------------------------------------------------
   Los mismos valores que tests/Soporte/ConfiguracionDeEjemplo.php, más la
   razón social. Sirve para dos cosas:

   1. El SQL Server local: con la tabla ya creada (php artisan migrate),

        docker cp database/sql/maestros-configuracion-ejemplo.sql ms-maestros-sqlserver-1:/tmp/
        MSYS_NO_PATHCONV=1 docker exec ms-maestros-sqlserver-1 /opt/mssql-tools18/bin/sqlcmd \
          -S localhost -U sa -P "Agro.Local.2026" -C -I -d maestros \
          -i /tmp/maestros-configuracion-ejemplo.sql

   2. Azure: copiarlo FUERA del repo, poner los valores reales y correrlo
      contra la base de Azure (DBeaver o sqlcmd). La copia con las cuentas
      y el NIT de verdad no se guarda acá: el repo tiene una copia en GitHub.

   Cada MERGE deja la tabla exactamente como dice el archivo: lo que no
   está, se borra. Para que un valor tome el que trae el código (la razón
   social, el área, los avisos), se borra su línea. Sin versiones, /version
   deja pasar a todos; sin cuentas, sin NIT o sin teléfono, su endpoint
   responde 500 CONFIGURACION_INCOMPLETA.

   Las reglas de cada valor están en maestros-configuracion.sql: un valor que
   no las cumple hace fallar el MERGE entero, y no se escribe nada.
   ===================================================================== */

MERGE maestros.cuentas_bancarias AS destino
USING (VALUES
    -- banco, número de cuenta (solo dígitos y guiones), orden en la App
    (N'Banco Nacional de Bolivia', '10000006816033',   1),
    (N'Banco BISA',                '701-5032719-3-81', 2)
) AS origen (banco, numero_cuenta, orden)
ON destino.orden = origen.orden
WHEN MATCHED AND (destino.banco <> origen.banco OR destino.numero_cuenta <> origen.numero_cuenta) THEN
    UPDATE SET banco = origen.banco, numero_cuenta = origen.numero_cuenta
WHEN NOT MATCHED BY TARGET THEN
    INSERT (banco, numero_cuenta, orden) VALUES (origen.banco, origen.numero_cuenta, origen.orden)
WHEN NOT MATCHED BY SOURCE THEN
    DELETE;

MERGE maestros.configuracion_app AS destino
USING (VALUES
    ('titular.nit',                 N'1013879029'),
    ('titular.razon_social',        N'Agropartners S.R.L.'),
    -- Un celular de 8 dígitos, sin 591: WhatsApp no abre chats con un fijo.
    ('atencion.telefono',           N'67701468'),
    -- MAYOR.MENOR.PARCHE. Por debajo de la mínima la App se bloquea hasta
    -- actualizar; por debajo de la recomendada, avisa y deja pasar.
    ('version.android.minima',      N'1.8.0'),
    ('version.android.recomendada', N'2.1.0'),
    ('version.android.tienda',      N'https://play.google.com/store/apps/details?id=bo.com.agropartners.socio')
    -- ,('version.ios.minima',      N'1.8.0')
    -- ,('version.ios.recomendada', N'2.1.0')
    -- ,('version.ios.tienda',      N'https://apps.apple.com/...')
) AS origen (clave, valor)
ON destino.clave = origen.clave
WHEN MATCHED AND destino.valor <> origen.valor THEN
    UPDATE SET valor = origen.valor
WHEN NOT MATCHED BY TARGET THEN
    INSERT (clave, valor) VALUES (origen.clave, origen.valor)
WHEN NOT MATCHED BY SOURCE THEN
    DELETE;
