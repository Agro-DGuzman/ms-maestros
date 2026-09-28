# Administración de enlaces del catálogo — plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que el área edite desde `/admin/productos` las cuatro URL del catálogo que no vienen de SAP, con validación, vista previa y bitácora.

**Architecture:** Maestros valida, guarda y lista (dominio `Enlace`, puerto `CatalogoAdministrable`, caso de uso `CambiarEnlacesDeProducto`). BackOffice despacha ese caso de uso y asienta la bitácora (`ActualizarEnlaces`), igual que `ConcederAcceso` con Identidad. Las pantallas son Blade, como las de contactos, detrás de un permiso nuevo «Catálogo».

**Tech Stack:** PHP 8.4, Laravel 13, Pest, SQLite en pruebas, SQL Server en despliegue.

**Spec:** `docs/superpowers/specs/2026-09-28-enlaces-del-catalogo-design.md`

## Global Constraints

- `declare(strict_types=1);` en todo archivo nuevo de `src/`.
- `BackOffice → Maestros`, nunca al revés. Ni `Domain` ni `Application` de ningún módulo usan Eloquent ni la `Infrastructure` de nadie (`tests/Architecture/EstructuraTest.php`).
- Primero el test, verificado en rojo. Cada archivo de prueba corre solo. Los dobles compartidos van en `tests/Dobles/`.
- `composer test`, `composer stan` y `composer lint -- --test` en verde antes de cada commit.
- Comentarios y commits en español; los comentarios dicen por qué.
- Columnas editables, y ninguna otra: `imagen_url`, `ficha_tecnica_url`, `hoja_seguridad_url`, `registro_sanitario_url`.
- Mensajes exactos: «Tiene que ser una dirección https completa.», «La imagen tiene que ser .jpg, .jpeg, .png o .webp.», «El documento tiene que ser un .pdf.», «La dirección es demasiado larga (máximo 1000 caracteres).», «No tenés permiso para esta sección.», «Se guardó 1 cambio.» / «Se guardaron N cambios.», «No había cambios.».

## Review Focus

1. Una URL con `®`, `™` o espacios en la ruta: se guarda codificada (`%C2%AE`, `%20`) y la API la devuelve así. → Task 2 (`normaliza lo que el contrato no admite`) y Task 3 (`la API devuelve la URL ya codificada`).
2. Un PDF pegado como imagen, o una imagen como documento: error junto al campo y no se guarda nada. → Task 3 (`un enlace invalido no guarda ninguno`) y Task 5 (`un pdf como imagen vuelve con el error`).
3. Guardar sin cambiar nada: «No había cambios.» y ningún asiento. → Task 4 y Task 5.
4. Una sesión abierta antes del despliegue, sin la clave `permisos`: es como no tener sesión (vuelve a `/admin/entrar`), no un 500. → Task 1 (`una sesion sin permisos es una sesion vencida`).
5. Un producto sin `codigo_articulo`: aparece en la lista, se edita por `id_producto` y su asiento guarda `item_code` en null. → Task 3 y Task 5 (`se edita un producto sin codigo`).

---

### Task 1: Permisos del operador

**Files:**
- Create: `src/BackOffice/Domain/Operadores/Permiso.php`
- Create: `src/BackOffice/Presentation/Http/Middleware/ExigirPermiso.php`
- Modify: `src/BackOffice/Domain/Operadores/Operador.php`
- Modify: `src/BackOffice/Presentation/Http/SesionDeOperador.php`
- Modify: `src/BackOffice/Infrastructure/Contrasena/AutenticadorDeContrasena.php`, `src/BackOffice/Infrastructure/Entra/AutenticadorDeDesarrollo.php` (los dos dan `Permiso::todos()`)
- Modify: `src/BackOffice/BackOfficeServiceProvider.php` (alias `backoffice.permiso`)
- Modify: `src/BackOffice/Presentation/Http/routes.php` (las rutas de contactos quedan bajo `backoffice.permiso:accesos`)
- Modify: los 8 archivos de `tests/` que hacen `new Operador(` (agregan `Permiso::todos()`)
- Test: `tests/Feature/BackOffice/PermisosTest.php`

**Interfaces:**
- Produces: `enum Permiso: string { case Accesos = 'accesos'; case Catalogo = 'catalogo'; public static function todos(): array /* list<self> */ }`
- Produces: `Operador::__construct(IdDeOperador $id, string $nombre, string $correo, array $permisos /* list<Permiso> */)` y `Operador::puede(Permiso $permiso): bool`. El parámetro es obligatorio: el día que entre Entra, cada autenticador tiene que decidir qué da.
- Produces: middleware `backoffice.permiso:<valor>`; sin el permiso responde **403** con el texto «No tenés permiso para esta sección.».

- [ ] **Step 1: Tests en rojo** en `PermisosTest.php` (`RefreshDatabase`; la sesión se arma con `SesionDeOperador::guardar`):
  - `sin el permiso de accesos, contactos responde 403` → operador con `[Permiso::Catalogo]`, `GET /admin/contactos` → 403 y ve «No tenés permiso para esta sección.».
  - `con el permiso de accesos, contactos abre` → `[Permiso::Accesos]` → 200.
  - `una sesion sin permisos es una sesion vencida` → `Session::put('backoffice.operador', ['oid' => 'x', 'nombre' => 'X', 'correo' => 'x@a.bo'])` sin la clave `permisos` → `SesionDeOperador::actual()` es `null` y `GET /admin/contactos` redirige a `admin.entrar`.
  - `un permiso desconocido en la sesion se ignora` → `permisos => ['accesos', 'borrar-todo']` → `actual()->permisos` es `[Permiso::Accesos]`.
  - `los autenticadores de hoy dan todos los permisos` → el operador que devuelve `AutenticadorDeDesarrollo` tiene `puede(Permiso::Catalogo)` y `puede(Permiso::Accesos)`.
- [ ] **Step 2:** `vendor/bin/pest tests/Feature/BackOffice/PermisosTest.php`. Esperado: falla, `Permiso` no existe.
- [ ] **Step 3:** Implementar. `SesionDeOperador::guardar` agrega `'permisos' => array_map(fn (Permiso $p) => $p->value, ...)`; `actual()` exige que la clave sea un array (si no, `null`) y mapea con `Permiso::tryFrom`, descartando los `null`. El resto de la suite se arregla sumando `Permiso::todos()` en cada `new Operador(`.
- [ ] **Step 4:** `composer test` en verde, incluida la suite de back-office existente.
- [ ] **Step 5:** Commit: `feat(backoffice): permisos por operador; contactos exige el de accesos`.

### Task 2: El enlace como valor de dominio

**Files:**
- Create: `src/Maestros/Domain/Productos/Enlace.php`
- Test: `tests/Unit/Maestros/EnlaceTest.php`

**Interfaces:**
- Produces: `Enlace::imagen(string $texto): ?self` y `Enlace::documento(string $texto): ?self`. Devuelven `null` si el texto recortado queda vacío. Si es inválido, lanzan `DomainException(Error::validation('ENLACE_INVALIDO', <mensaje exacto de Global Constraints>))`. `Enlace::valor(): string` es la URL normalizada.
- Reglas, en este orden: recortar; `https` absoluta con host; normalizar cada segmento de la ruta con `rawurlencode(rawurldecode($s))`; rechazar si queda un espacio o un byte fuera de ASCII en cualquier parte; máximo 1000 caracteres; extensión de la ruta (sin distinguir mayúsculas) en `jpg|jpeg|png|webp` para imagen y `pdf` para documento.

- [ ] **Step 1: Tests en rojo:**
  - `acepta una imagen https` → `Enlace::imagen('https://cdn.x.bo/a/Foto.JPG')->valor()` es la misma URL.
  - `vacio quita el enlace` → `Enlace::imagen('   ')` es `null`.
  - `normaliza lo que el contrato no admite` → `Enlace::documento('https://x.bo/2025/Aproach® Prima.pdf')->valor()` es `'https://x.bo/2025/Aproach%C2%AE%20Prima.pdf'`, y una URL ya codificada queda igual.
  - `rechaza lo que no es https` (dataset `http://x.bo/a.jpg`, `javascript:alert(1)`, `/uploads/a.jpg`, `ftp://x.bo/a.jpg`) → mensaje «Tiene que ser una dirección https completa.».
  - `una imagen no puede ser un pdf` → «La imagen tiene que ser .jpg, .jpeg, .png o .webp.».
  - `un documento tiene que ser pdf` → `Enlace::documento('https://x.bo/a.jpg')` → «El documento tiene que ser un .pdf.».
  - `rechaza lo que no es ascii fuera de la ruta` → `https://x.bo/a.pdf?nombre=Ñandú` → «Tiene que ser una dirección https completa.».
  - `rechaza una direccion demasiado larga` → la ruta con 1000 `a` → «La dirección es demasiado larga (máximo 1000 caracteres).».
- [ ] **Step 2:** `vendor/bin/pest tests/Unit/Maestros/EnlaceTest.php`. Esperado: falla, la clase no existe.
- [ ] **Step 3:** Implementar `Enlace` en `src/Maestros/Domain/Productos/Enlace.php`.
- [ ] **Step 4:** El test pasa.
- [ ] **Step 5:** Commit: `feat(maestros): el enlace del catalogo como valor de dominio`.

### Task 3: Maestros administra los enlaces

**Files:**
- Create en `src/Maestros/Application/Productos/`: `CampoDeEnlace.php`, `EnlacesDeProducto.php`, `ProductoAdministrable.php`, `FiltroDeEnlaces.php`, `CatalogoAdministrable.php`, `CambioDeEnlace.php`, `EnlacesCambiados.php`
- Create: `src/Maestros/Application/Productos/CambiarEnlaces/CambiarEnlacesDeProducto.php`, `CambiarEnlacesDeProductoHandler.php`
- Create: `src/Maestros/Infrastructure/Persistence/EloquentCatalogoAdministrable.php`
- Modify: `app/Providers/CoreServiceProvider.php` (handler), `app/Providers/ModulosServiceProvider.php` (binding del puerto)
- Modify: `tests/Soporte/CatalogoDeEjemplo.php` (agrega `idDe(string $nombre): int`)
- Test: `tests/Feature/Maestros/CatalogoAdministrableTest.php`, `tests/Feature/Maestros/CambiarEnlacesDeProductoTest.php`

**Interfaces:**
- Consumes: `Enlace::imagen/documento` (Task 2).
- Produces:
  - `enum CampoDeEnlace: string { Imagen = 'imagen_url'; FichaTecnica = 'ficha_tecnica_url'; HojaDeSeguridad = 'hoja_seguridad_url'; RegistroSanitario = 'registro_sanitario_url' }`, con `etiqueta(): string` («Imagen», «Ficha técnica», «Hoja de seguridad», «Registro sanitario») y `esImagen(): bool`. El valor es el nombre de la columna **y** el del campo del formulario.
  - `EnlacesDeProducto(?string $imagen, ?string $fichaTecnica, ?string $hojaDeSeguridad, ?string $registroSanitario)`, con `valor(CampoDeEnlace $campo): ?string` y `documentosCargados(): int`.
  - `ProductoAdministrable(int $id, ?string $itemCode, string $nombre, ?string $categoria, bool $activo, EnlacesDeProducto $enlaces)`. `$categoria` es el nombre, o null si falta o apunta a una categoría que no existe (left join). `porQueNoSeVe(): ?string` devuelve el primer motivo que aplique: «Está dado de baja.», «No tiene código de artículo.» o «No tiene categoría.»; `visibleEnApp(): bool` es `porQueNoSeVe() === null`. Es la misma regla que `EloquentCatalogoDeProductos::visibles()`.
  - `enum FiltroDeEnlaces: string { Todos = 'todos'; SinImagen = 'sin-imagen'; SinDocumentos = 'sin-documentos' }`. «Sin documentos» son los que no tienen **ninguno** de los tres.
  - `interface CatalogoAdministrable { listar(?string $texto, FiltroDeEnlaces $filtro): array /* list<ProductoAdministrable> por nombre */; buscar(int $id): ?ProductoAdministrable; guardarEnlaces(int $id, EnlacesDeProducto $enlaces): void }`
  - `CambioDeEnlace(CampoDeEnlace $campo, ?string $anterior, ?string $nuevo)`; `EnlacesCambiados(int $idProducto, ?string $itemCode, array $cambios /* list<CambioDeEnlace> */)`.
  - `CambiarEnlacesDeProducto(int $idProducto, ?string $imagen, ?string $fichaTecnica, ?string $hojaDeSeguridad, ?string $registroSanitario)` implements `Request, RequiereTransaccion`. Su handler devuelve `ResultWithValue<EnlacesCambiados>`, `Error::notFound('PRODUCTO_NO_ENCONTRADO', 'No existe el producto {id}', $id)`, o un `ValidationError` de `FieldError(<valor de CampoDeEnlace>, 'ENLACE_INVALIDO', <mensaje>)`, uno por campo inválido.

- [ ] **Step 1: Tests en rojo** de `CatalogoAdministrableTest.php`, con `CatalogoDeEjemplo::sembrar()`:
  - `lista todos, tambien los que la App no ve` → los 6 del ejemplo, por nombre; `visibleEnApp()` es falso para «Dado de baja», «Sin código SAP todavía» y «Sin categoría todavía», y `porQueNoSeVe()` da «Está dado de baja.», «No tiene código de artículo.» y «No tiene categoría.» respectivamente.
  - `busca por nombre o por codigo, sin distinguir mayusculas` → `'GLIFORTE'` y `'a-0142'` devuelven solo Gliforte.
  - `filtra los que no tienen imagen` y `filtra los que no tienen ningun documento` → con el ejemplo: sin imagen son todos menos Gliforte; sin documentos son Atrazina, «Sin código SAP todavía» y «Sin categoría todavía».
  - `buscar un id que no existe es null`.
- [ ] **Step 2: Tests en rojo** de `CambiarEnlacesDeProductoTest.php`, despachando con `app(Mediator::class)->send(...)`:
  - `guarda y la API lo devuelve` → la imagen nueva de Gliforte aparece en `GET /v1/productos/A-0142` como `data.imagenUrl` (con `VerificadorFalso` y el token `token-bueno`).
  - `devuelve solo lo que cambio, con el anterior de la base` → cambiar solo la ficha deja un `CambioDeEnlace` con la URL vieja en `anterior`.
  - `vaciar un campo lo quita` → `hojaDeSeguridad: ''` deja la columna en null y `GET /v1/productos/A-0142/documentos` ya no la lista.
  - `sin cambios no toca nada` → mandar los mismos valores → `cambios` vacío y `actualizado_en` sin cambiar.
  - `la API devuelve la URL ya codificada` → una imagen con `®` sale con `%C2%AE` en `data.imagenUrl`.
  - `un enlace invalido no guarda ninguno` → imagen válida más documento `.jpg` → falla con un `FieldError` sobre `ficha_tecnica_url`, y la imagen tampoco cambió.
  - `un producto sin codigo tambien se edita` → `idDe('Sin código SAP todavía')` → éxito, con `itemCode` null en `EnlacesCambiados`.
  - `un producto que no existe` → `PRODUCTO_NO_ENCONTRADO`.
- [ ] **Step 3:** Correr los dos archivos. Esperado: fallan, las clases no existen.
- [ ] **Step 4:** Implementar. Para buscar, `EloquentCatalogoAdministrable` usa `App\Persistence\ComparacionSinAcentos::comoTextoInsensible()` sobre el nombre y el código, igual que `EloquentBuscadorDeContactos`, con el patrón escapado y en minúsculas. `guardarEnlaces` actualiza las cuatro columnas y `actualizado_en`. El handler valida los cuatro campos antes de escribir, así un error no deja la mitad guardada.
- [ ] **Step 5:** Los dos archivos pasan, y también `tests/Architecture`.
- [ ] **Step 6:** Commit: `feat(maestros): el catalogo administra sus enlaces`.

### Task 4: Bitácora de cambios del catálogo

**Files:**
- Create: `database/migrations/backoffice/2026_09_28_000100_crear_cambios_de_catalogo.php`
- Create: `src/BackOffice/Domain/Catalogo/CambioDeCatalogo.php`, `RegistroDeCambiosDelCatalogo.php`
- Create: `src/BackOffice/Infrastructure/Persistence/CambioDeCatalogoRecord.php`, `EloquentRegistroDeCambiosDelCatalogo.php`
- Create: `src/BackOffice/Application/Catalogo/ActualizarEnlaces/ActualizarEnlaces.php`, `ActualizarEnlacesHandler.php`
- Create: `tests/Dobles/RegistroDeCambiosEnMemoria.php`
- Modify: `app/Providers/CoreServiceProvider.php` (handler), `src/BackOffice/BackOfficeServiceProvider.php` (binding del registro, donde está el de `BitacoraRepository`)
- Test: `tests/Unit/BackOffice/ActualizarEnlacesHandlerTest.php`, `tests/Feature/BackOffice/EloquentRegistroDeCambiosDelCatalogoTest.php`

**Interfaces:**
- Consumes: `CambiarEnlacesDeProducto`, `EnlacesCambiados`, `CambioDeEnlace` (Task 3); `Operador` (Task 1).
- Produces:
  - Tabla `backoffice.cambios_de_catalogo` (`backoffice_cambios_de_catalogo` en SQLite): `id_de_cambio` string(40) PK, `id_de_operador` string(100), `operador` string(200), `id_producto` int, `item_code` string(50) null, `campo` string(40), `valor_anterior` string(1000) null, `valor_nuevo` string(1000) null, `ocurrio_el` dateTimeTz, `direccion_ip` string(45); índice `(id_producto, ocurrio_el)`. Sin timestamps.
  - `CambioDeCatalogo::nuevo(Operador $operador, int $idProducto, ?string $itemCode, string $campo, ?string $anterior, ?string $nuevo, string $direccionIp, DateTimeImmutable $ocurrioEl): self` y `reconstituir(...)`, con el mismo patrón que `AsientoDeBitacora`. `$campo` es el valor de `CampoDeEnlace`: el dominio de BackOffice no depende de la capa de aplicación de Maestros.
  - `interface RegistroDeCambiosDelCatalogo { asentar(CambioDeCatalogo $cambio): void; historialDe(int $idProducto): array /* list<CambioDeCatalogo>, más reciente primero */ }`, sin modificar ni borrar.
  - `ActualizarEnlaces(int $idProducto, ?string $imagen, ?string $fichaTecnica, ?string $hojaDeSeguridad, ?string $registroSanitario, Operador $operador, string $direccionIp)` implements `Request, RequiereTransaccion`. Su handler, `(Mediator, RegistroDeCambiosDelCatalogo, RelojDelSistema)`, devuelve `ResultWithValue<int>` con la cantidad de cambios asentados.

- [ ] **Step 1: Tests en rojo** de `ActualizarEnlacesHandlerTest.php`, con `MediatorEspia`, `RegistroDeCambiosEnMemoria` y `RelojFijo`:
  - `despacha CambiarEnlacesDeProducto con lo recibido` → `$mediator->ultimo()` es un `CambiarEnlacesDeProducto` con el id y las cuatro URL.
  - `asienta un cambio por campo, con el operador y la IP` → el espía responde `EnlacesCambiados(7, 'A-0142', [2 cambios])` → 2 asientos con el nombre del operador, `'190.129.4.7'`, `item_code` `'A-0142'` y el momento de `RelojFijo`; el valor es `2`.
  - `sin cambios no asienta nada` → `EnlacesCambiados` con `[]` → 0 asientos, valor `0`.
  - `si Maestros falla no asienta nada` → el espía responde un fallo → se devuelve ese mismo fallo y hay 0 asientos.
- [ ] **Step 2: Tests en rojo** de `EloquentRegistroDeCambiosDelCatalogoTest.php`:
  - `devuelve el historial de un producto del mas reciente al mas viejo`.
  - `el historial de un producto no trae los de otro`.
  - `guarda un item_code nulo` (el producto sin código).
  - `el registro no expone forma de modificar ni borrar`, igual que el test del repositorio de bitácora.
- [ ] **Step 3:** Correr los dos archivos. Esperado: fallan.
- [ ] **Step 4:** Implementar la migración (esquema `backoffice` en `sqlsrv`, nombre aplanado en SQLite, como `2026_09_15_000100_crear_asientos_de_bitacora.php`), el dominio, el registro, el handler y el doble.
- [ ] **Step 5:** Los dos archivos pasan, y `composer test` entero.
- [ ] **Step 6:** Commit: `feat(backoffice): bitacora de cambios del catalogo`.

### Task 5: Pantallas de productos

**Files:**
- Create: `src/BackOffice/Presentation/Http/ProductosController.php`
- Create: `src/BackOffice/Presentation/Views/productos.blade.php`, `producto.blade.php`
- Modify: `src/BackOffice/Presentation/Http/routes.php`, `src/BackOffice/Presentation/Views/layout.blade.php`
- Test: `tests/Feature/BackOffice/PantallaDeProductosTest.php`

**Interfaces:**
- Consumes: `CatalogoAdministrable`, `FiltroDeEnlaces`, `CampoDeEnlace`, `Enlace` (Tasks 2 y 3); `ActualizarEnlaces`, `RegistroDeCambiosDelCatalogo` (Task 4); `Permiso`, `backoffice.permiso` (Task 1).
- Produces, bajo `backoffice.sesion` + `backoffice.permiso:catalogo`:
  - `GET /admin/productos` → `admin.productos`, `ProductosController::index(Request): View` (query `q`, `filtro`).
  - `GET /admin/productos/{id}` → `admin.producto`, `mostrar(int $id): View`; `whereNumber('id')`; 404 si `buscar()` es null.
  - `POST /admin/productos/{id}` → `admin.producto.guardar`, `guardar(Request, int $id): RedirectResponse`.
- La validación del formulario delega en `Enlace`: una regla closure por campo llama a `Enlace::imagen()` o a `Enlace::documento()` según `CampoDeEnlace::esImagen()`, y pasa a `$fallar` la descripción del `DomainException`. Los nombres de campo son los valores de `CampoDeEnlace`.
- El encabezado del layout pasa a «Back-office», con los enlaces «Contactos» (si `puede(Permiso::Accesos)`) y «Productos» (si `puede(Permiso::Catalogo)`).

- [ ] **Step 1: Tests en rojo** de `PantallaDeProductosTest.php`, con `CatalogoDeEjemplo::sembrar()` y un operador con `Permiso::todos()`:
  - `lista todos los productos y dice cuales ve la App` → `GET /admin/productos` muestra «Gliforte 68 SG» y «Sin categoría todavía», y la fila de «Dado de baja» dice «No».
  - `busca y filtra` → `?q=gliforte` muestra solo Gliforte; `?filtro=sin-imagen` no muestra Gliforte.
  - `el formulario muestra la vista previa y el historial` → `GET /admin/productos/{id de Gliforte}` contiene `<img` con su `imagen_url`, los cuatro campos con sus valores y el título del historial.
  - `el formulario explica por que la App no lo ve` → el de «Dado de baja» muestra «Está dado de baja.».
  - `guardar un cambio lo avisa y lo asienta` → `POST` con la ficha nueva y el resto igual → redirige a `admin.producto` con «Se guardó 1 cambio.», y hay 1 fila en `backoffice_cambios_de_catalogo` con el nombre del operador.
  - `un pdf como imagen vuelve con el error` → `imagen_url = https://x.bo/a.pdf` → sesión con error en `imagen_url` («La imagen tiene que ser .jpg, .jpeg, .png o .webp.»), lo tecleado conservado en `old()` y la base sin cambios.
  - `sin cambios lo dice y no asienta` → «No había cambios.» y 0 filas.
  - `se edita un producto sin codigo` → `idDe('Sin código SAP todavía')` → guarda y el asiento tiene `item_code` null.
  - `sin el permiso de catalogo responde 403` → operador con `[Permiso::Accesos]`.
  - `un id que no existe es 404` → `GET /admin/productos/999999` y `GET /admin/productos/abc`.
  - `el encabezado muestra solo las secciones permitidas` → con `[Permiso::Catalogo]`, «Productos» aparece y «Contactos» no.
- [ ] **Step 2:** `vendor/bin/pest tests/Feature/BackOffice/PantallaDeProductosTest.php`. Esperado: falla, las rutas no existen.
- [ ] **Step 3:** Implementar. El mensaje de éxito usa «Se guardó 1 cambio.» con 1 y «Se guardaron N cambios.» con N > 1; con 0, «No había cambios.». Todo valor que vuelve a la vista sale con `{{ }}` de Blade: una URL es entrada de usuario.
- [ ] **Step 4:** El archivo pasa, y `composer test` entero.
- [ ] **Step 5:** Commit: `feat(backoffice): pantallas para administrar los enlaces del catalogo`.

### Task 6: Documentación y puesta en marcha

**Files:**
- Modify: `CLAUDE.md`

- [ ] **Step 1:** En «Contratos que no se negocian», agregar dos cosas:
  - que las cuatro columnas de enlaces las administra el back-office, que una sincronización con SAP **no puede escribirlas**, y que cada cambio queda en `backoffice.cambios_de_catalogo`;
  - que cada pantalla del back-office exige su `Permiso`, y que hoy los autenticadores de desarrollo y contraseña dan todos, hasta que Entra decida por rol.
- [ ] **Step 2:** `composer test`, `composer stan`, `composer lint -- --test` en verde.
- [ ] **Step 3:** Contra el stack local: `docker compose up -d --build app` y `docker compose exec app php artisan migrate --force`. Entrar a `/admin/productos`, cambiar la imagen de un producto y comprobar que `GET /v1/productos/{itemCode}` la devuelve.
- [ ] **Step 4:** Commit: `docs: el back-office administra los enlaces del catalogo`.
- [ ] **Step 5:** Para la nube, después del push: correr la migración nueva en Azure, porque el pipeline no corre migraciones.
