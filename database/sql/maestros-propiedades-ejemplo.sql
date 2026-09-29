/* =====================================================================
   Propiedades de ejemplo - SOLO PARA LOCAL
   ---------------------------------------------------------------------
   Las mismas cinco filas que tests/Soporte/PropiedadesDeEjemplo.php, para
   los socios de database/semillas/maestros-ejemplo.json. Nunca contra
   Azure: las propiedades reales salen de la conciliación.

     C-004871  dos activas y una dada de baja (el socio de la persona
               p-8f2b1c40, celular 70741828)
     C-004872  una
     C-004873  ninguna, para ver la lista vacía
     C-005210  una, de otro grupo: pedirla con el token de p-8f2b1c40 es 403

   Idempotente: correrlo de nuevo no cambia nada. Con la tabla ya creada
   (php artisan migrate):

     docker cp database/sql/maestros-propiedades-ejemplo.sql ms-maestros-sqlserver-1:/tmp/
     MSYS_NO_PATHCONV=1 docker exec ms-maestros-sqlserver-1 /opt/mssql-tools18/bin/sqlcmd \
       -S localhost -U sa -P "Agro.Local.2026" -C -I -d maestros \
       -i /tmp/maestros-propiedades-ejemplo.sql
   ===================================================================== */

MERGE maestros.propiedad AS destino
USING (VALUES
    (N'PROP-014', N'Lote 14 · San Julián',     N'C-004871', 1),
    (N'PROP-007', N'Lote 07 · Cuatro Cañadas', N'C-004871', 1),
    (N'PROP-003', N'Lote 03 · Pailón',         N'C-004871', 0),
    (N'PROP-021', N'Lote 21 · Okinawa',        N'C-004872', 1),
    (N'PROP-031', N'Lote 31 · Montero',        N'C-005210', 1)
) AS origen (id_propiedad, nombre, codigo_de_socio, activa)
ON destino.id_propiedad = origen.id_propiedad
WHEN MATCHED AND (destino.nombre <> origen.nombre
               OR destino.codigo_de_socio <> origen.codigo_de_socio
               OR destino.activa <> origen.activa) THEN
    UPDATE SET nombre = origen.nombre,
               codigo_de_socio = origen.codigo_de_socio,
               activa = origen.activa,
               actualizado_en = SYSUTCDATETIME()
WHEN NOT MATCHED THEN
    INSERT (id_propiedad, nombre, codigo_de_socio, activa)
    VALUES (origen.id_propiedad, origen.nombre, origen.codigo_de_socio, origen.activa);
