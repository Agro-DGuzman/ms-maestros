# Administración de enlaces del catálogo — diseño

## Para qué

El área comercial o técnica (no el equipo de desarrollo) mantiene las
**direcciones que no vienen de SAP**: la imagen del producto y las URL de sus
tres documentos. Hoy se cargan por script; quien las mantiene tiene que poder
corregirlas sola, ver al instante si la imagen carga, y que el cambio llegue a
la App sin pedirle nada a nadie.

**Éxito:** alguien del área busca un producto, pega o corrige una URL, ve la
vista previa, guarda, y `GET /v1/productos/{itemCode}` ya la devuelve. Cada
cambio queda en una bitácora que dice quién, cuándo, qué campo, antes y
después.

## Alcance

**Adentro:**

- Cuatro campos de `maestros.producto`, y ningún otro: `imagen_url`,
  `ficha_tecnica_url`, `hoja_seguridad_url`, `registro_sanitario_url`.
- Una sección `/admin/productos` en el back-office que ya existe: lista con
  búsqueda y filtro, y un formulario por producto.
- Un permiso «Catálogo» que el operador tiene que tener para entrar, separado
  de «Accesos» (habilitar socios). Hasta que exista el ingreso por Entra ID,
  los autenticadores actuales (desarrollo y contraseña) dan los dos.

**Afuera:**

- Editar cualquier otro campo del producto: viene de SAP o de script, y en la
  pantalla se ve pero no se edita.
- Alta, baja y categorías de productos.
- Subir archivos: se pega una URL de donde ya esté alojado.
- Ingreso por Entra ID y roles de Entra: es el proyecto siguiente. Este deja
  el permiso listo para que Entra solo tenga que decir quién lo tiene.

## Reglas de las URL

Una URL vacía **quita** el enlace: la App vuelve a dibujar el ícono de la
categoría, o el documento desaparece de la lista de descargas.

Una URL no vacía:

1. Se recortan los espacios de los extremos.
2. Tiene que ser absoluta y **`https`**, con host. Nada de `http`,
   `javascript:` ni rutas relativas.
3. Los segmentos de la ruta se normalizan con `rawurlencode(rawurldecode())`:
   `®` pasa a `%C2%AE`, un espacio a `%20`, y lo que ya venía codificado queda
   igual. El contrato pide formato `uri`.
4. Después de normalizar, no puede quedar ningún espacio ni carácter fuera de
   ASCII (por ejemplo en la consulta `?...`).
5. Máximo **1000** caracteres ya normalizada (el ancho de la columna).
6. La **imagen** tiene que terminar en `.jpg`, `.jpeg`, `.png` o `.webp`; un
   **documento**, en `.pdf`. Sin distinguir mayúsculas. Es lo que evita pegar
   un PDF como foto, el error que ya apareció con las etiquetas de la web.

Mensajes, por campo: «Tiene que ser una dirección https completa.», «La imagen
tiene que ser .jpg, .jpeg, .png o .webp.», «El documento tiene que ser un
.pdf.», «La dirección es demasiado larga (máximo 1000 caracteres).»

## Pantallas

**Lista (`GET /admin/productos`)** — todos los productos, también los que la
App no ve, ordenados por nombre. Columnas: código, nombre, categoría, «Se ve
en la App» (sí/no), imagen (sí/no), documentos (`n de 3`). Búsqueda por nombre
o código, sin distinguir mayúsculas ni acentos. Filtro: todos / sin imagen /
sin documentos. Sin paginación: son decenas, no miles.

**Producto (`GET /admin/productos/{id}`)** — se identifica por `id_producto`,
no por el código, porque un producto puede estar cargado sin código todavía.
Muestra lo que no se edita (código, nombre, categoría, si se ve en la App y por
qué no), la vista previa de la imagen, un campo por URL con un enlace para
abrir la actual, y abajo el historial de cambios de ese producto.

**Guardar (`POST /admin/productos/{id}`)** — valida cada campo con las reglas
de arriba; si alguno falla, vuelve al formulario con lo tecleado y el mensaje
junto al campo. Si pasa, guarda solo lo que cambió y avisa «Se guardaron N
cambios.» o «No había cambios.».

Un producto que no existe responde 404.

## Bitácora

Tabla nueva `backoffice.cambios_de_catalogo`, **append-only** como la de
accesos: una fila por campo que cambió, con operador (id y nombre copiado),
`id_producto`, `item_code` copiado, campo, valor anterior, valor nuevo,
momento e IP. El valor anterior se lee de la base al guardar, no del
formulario. Si nada cambió, no se asienta nada. El guardado y los asientos van
en la misma transacción.

## Arquitectura

- **Maestros** es dueño del catálogo: valida (`Enlace`, dominio), guarda y
  lista (`CatalogoAdministrable`, puerto; Eloquent en infraestructura) y
  expone el caso de uso `CambiarEnlacesDeProducto`, que devuelve qué cambió.
- **BackOffice** no sabe guardar productos: su caso de uso `ActualizarEnlaces`
  despacha el de Maestros y asienta la bitácora, igual que `ConcederAcceso`
  despacha el de Identidad.
- Dos concurrentes que editan el mismo producto: gana el último, y la
  bitácora muestra los dos cambios, con el anterior real de cada uno.

## Lo que queda escrito

`CLAUDE.md`: estas cuatro columnas las administra el back-office; una
sincronización futura desde SAP **no puede escribirlas**.
