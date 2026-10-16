# Ingesta de productos · plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** que el Sincronizador mantenga el catálogo desde SAP por `POST /ingesta/v1/productos` y `PUT`/`DELETE /ingesta/v1/productos/{itemCode}`, sin pisar lo que carga el back-office.

**Architecture:** copia la forma de la ingesta de socios. Dos casos de uso en `Maestros\Application\Ingesta` hablan con un puerto nuevo, `ReplicaDeProductos`, cuyo adaptador Eloquent escribe solo las columnas de SAP. Un controlador nuevo valida la forma del cuerpo. El back-office suma la descripción con un caso de uso propio. Un comando da de baja lo que nunca llegó de SAP.

**Tech Stack:** Laravel 13, PHP 8.4, Pest, Larastan 9, SQLite en la batería y SQL Server en Azure.

**Spec:** `docs/superpowers/specs/2026-10-15-ingesta-de-productos-design.md`

## Global Constraints

- PHP 8.4 y `declare(strict_types=1);` en todo archivo de `src/`.
- `composer test`, `composer stan` y `composer lint -- --test` en verde antes de cada commit. Correr `vendor/bin/pint` antes del lint.
- `Domain/` y `Application/` no conocen Eloquent ni Laravel. Eloquent y `DB::` viven solo en `Infrastructure`.
- Negocio en español, andamiaje en inglés. Comentarios y commits en español, y los comentarios dicen **por qué**.
- El envelope sale de un `Result`, y el status sale de `MapaDeErroresHttp`.
- **Prohibido `insertOrIgnore`:** la gramática de SQL Server de Laravel no lo tiene, y SQLite sí, así que la batería pasaría y Azure daría 500.
- La estructura del catálogo en SQL Server es `database/sql/maestros-productos.sql`. La copia de SQLite con Blueprint cambia a la par, sin los CHECK.
- Los dobles y armadores compartidos van en `tests/Dobles/` o `tests/Soporte/`. Las funciones globales de un archivo Pest llevan nombres únicos en toda la batería (`replicar` ya existe en `ReplicarSocioTest.php`); acá llevan «Producto» en el nombre.
- Cada archivo de prueba corre solo: `vendor/bin/pest <archivo>`.
- Primero el test en rojo, verificado, y después el código.
- `docs/` está en `.gitignore`: el plan y la spec se agregan con `git add -f`.
- Todo commit termina con `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Se trabaja en la rama `feat/ingesta-de-productos`, que ya existe.

## Review Focus

1. **La `vigenteDesde` real del Sincronizador** tiene microsegundos y desfase (`2026-10-15T19:32:58.866982+00:00`). Tiene que pasar a UTC y compararse al segundo. Test en la tarea 3.
2. **Un código de categoría con mayúsculas o espacios** (`" INSECTICIDAS"`) tiene que caer en la `insecticidas` existente, sin crear otra. Test en la tarea 2.
3. **Textos opcionales vacíos o con solo espacios** en el JSON, como suelen llegar los campos de usuario de SAP, no pueden pisar la ficha guardada (P9). Depende de que `TrimStrings` y `ConvertEmptyStringsToNull` actúen sobre `/ingesta`. Test HTTP en la tarea 3.
4. **Cultivos que difieren solo en mayúsculas** (`SOYA` y `Soya`) dan una sola fila. Si no, la clave primaria de SQL Server, que no distingue mayúsculas, rechaza el envío. Test en la tarea 2.
5. **Un `PUT` con `activo: false`** saca al producto de `/v1/productos`, y uno posterior con `activo: true` lo devuelve con sus enlaces. Es un criterio de éxito de la spec. Test de punta a punta en la tarea 3.

---

### Task 1: Las columnas nuevas y lo que dejan ver

**Files:**
- Modify: `database/sql/maestros-productos.sql` (al final)
- Create: `database/migrations/maestros/2026_10_16_000100_preparar_el_catalogo_para_la_ingesta.php`
- Modify: `src/Maestros/Infrastructure/Persistence/EloquentCatalogoDeProductos.php` (`visibles()`)
- Modify: `src/Maestros/Application/Productos/ProductoAdministrable.php`
- Modify: `src/Maestros/Infrastructure/Persistence/EloquentCatalogoAdministrable.php` (`consulta()`, `aProducto()`)
- Modify: `src/BackOffice/Presentation/Views/producto.blade.php` (encabezado)
- Modify: `tests/Soporte/CatalogoDeEjemplo.php`
- Test: `tests/Feature/Maestros/ProductosTest.php`, `tests/Unit/Maestros/ProductoAdministrableTest.php` (nuevo), `tests/Feature/BackOffice/PantallaDeProductosTest.php`

**Interfaces:**
- Produces: columnas `vigente_desde`, `dado_de_baja_el` (fecha con zona, anulables), `origen_esquema` (20) y `origen_evento_id` (entero grande), todas anulables, en `maestros.producto` / `maestros_producto`.
- Produces: `ProductoAdministrable::__construct(int $id, ?string $itemCode, string $nombre, ?string $categoria, bool $activo, bool $dadoDeBaja, bool $llegoDeSap, EnlacesDeProducto $enlaces)`. `llegoDeSap` es `origen_esquema` no vacío.

- [ ] **Step 1: Cambiar la semilla y escribir los tests**

En `CatalogoDeEjemplo`, el producto `A-0300` («Dado de baja») pasa a `'activo' => true, 'dado_de_baja_el' => '2026-10-10 12:00:00', 'vigente_desde' => '2026-10-10 12:00:00'`, como lo deja una baja real. Así queda afuera **solo** por la baja, y los tests que ya lo excluyen (`lista solo lo que la App puede ver`, los documentos 404) prueban la consulta nueva. La tarea 2 usa esa vigencia.

`tests/Unit/Maestros/ProductoAdministrableTest.php`:

```php
it('dice por que no se ve, en orden', function (bool $activo, bool $baja, bool $deSap, ?string $codigo, ?string $categoria, ?string $motivo) {
    $p = new ProductoAdministrable(1, $codigo, 'X', $categoria, $activo, $baja, $deSap, new EnlacesDeProducto(null, null, null, null));
    expect($p->porQueNoSeVe())->toBe($motivo);
})->with([
    'dado de baja gana a inactivo' => [false, true, true, 'A-1', 'Herbicidas', 'Está dado de baja.'],
    'inactivo en SAP' => [false, false, true, 'A-1', 'Herbicidas', 'Está inactivo en SAP.'],
    'inactivo de la web' => [false, false, false, 'A-1', 'Herbicidas', 'Está inactivo.'],
    'sin codigo' => [true, false, false, null, 'Herbicidas', 'No tiene código de artículo.'],
    'sin categoria' => [true, false, true, 'A-1', null, 'No tiene categoría.'],
    'visible' => [true, false, true, 'A-1', 'Herbicidas', null],
]);
```

En `PantallaDeProductosTest.php`:

```php
it('avisa cuando un producto todavia no llego de SAP', function () {
    $this->get("/admin/productos/{$this->gliforte}")->assertSee('Todavía no llegó de SAP');

    DB::table(CatalogoDeEjemplo::tabla('producto'))->where('id_producto', $this->gliforte)->update(['origen_esquema' => 'AGRO_P6']);

    $this->get("/admin/productos/{$this->gliforte}")->assertDontSee('Todavía no llegó de SAP');
});
```

- [ ] **Step 2: Correr y verlos en rojo**

Run: `vendor/bin/pest tests/Feature/Maestros/ProductosTest.php tests/Unit/Maestros/ProductoAdministrableTest.php tests/Feature/BackOffice/PantallaDeProductosTest.php`
Expected: FAIL. La semilla falla por la columna `dado_de_baja_el`, que no existe; el test unitario, por los argumentos del constructor.

- [ ] **Step 3: La migración y el script**

- **El script:** al final de `maestros-productos.sql`, un bloque «La ingesta desde SAP» con un `IF COL_LENGTH('maestros.producto', '<columna>') IS NULL ALTER TABLE maestros.producto ADD <columna> ... NULL;` por columna: `vigente_desde DATETIMEOFFSET`, `dado_de_baja_el DATETIMEOFFSET`, `origen_esquema NVARCHAR(20)` y `origen_evento_id BIGINT`, seguido de `GO`. Son `DATETIMEOFFSET` como las vigencias de los socios (`dateTimeTz`), así sirve el mismo `FechaEnUtc`.
- **La migración:**
  - En `sqlsrv`, vuelve a correr el script con `LotesDeScript::de('maestros-productos.sql')`. Es idempotente.
  - En los demás motores, `Schema::table('maestros_producto')` con `dateTimeTz(...)->nullable()` para las dos fechas, `string('origen_esquema', 20)->nullable()` y `unsignedBigInteger('origen_evento_id')->nullable()`.
  - `down()` borra las cuatro columnas en los dos motores.

- [ ] **Step 4: Lo que dejan ver**

- **`visibles()`** suma `->whereNull('p.dado_de_baja_el')`.
- **`ProductoAdministrable`** suma `dadoDeBaja` y `llegoDeSap`. `porQueNoSeVe()` mira en este orden:
  - `dadoDeBaja` → «Está dado de baja.»;
  - `! activo` → «Está inactivo en SAP.» si `llegoDeSap`, y si no, «Está inactivo.»;
  - sin código, y después sin categoría, como hoy.
- **El adaptador** `EloquentCatalogoAdministrable` selecciona `p.dado_de_baja_el` y `p.origen_esquema`.
- **La vista** `producto.blade.php`, en el párrafo del encabezado, agrega `· Todavía no llegó de SAP` en `tenue` cuando `! $producto->llegoDeSap`.

*Ruling sobre la spec:* la spec propone «Dado de baja en SAP.» e «Inactivo en SAP.». Pero el comando de la tarea 5 también da de baja, y un producto de la web inactivo nunca pasó por SAP. Por eso el texto solo nombra a SAP cuando el producto llegó de SAP.

- [ ] **Step 5: Correr y verlos en verde, con la batería entera**

Run: `composer test`
Expected: PASS. `CatalogoAdministrableTest` sigue esperando «Está dado de baja.» para «Dado de baja»: ahora lo da la baja.

- [ ] **Step 6: Commit**

```bash
git add database src tests
git commit -m "feat(productos): vigencia, baja y origen en el catálogo"
```

---

### Task 2: Los casos de uso de la ingesta

**Files:**
- Create: `src/Maestros/Application/Ingesta/ProductoIngresado.php`, `CategoriaIngresada.php`, `RegistroIngresado.php`, `ReplicaDeProductos.php`, `EstadoDeProductoReplicado.php`, `Vigencia.php`
- Create: `src/Maestros/Application/Ingesta/ReplicarProducto/ReplicarProducto.php` y `ReplicarProductoHandler.php`
- Create: `src/Maestros/Application/Ingesta/DarDeBajaProducto/DarDeBajaProducto.php` y `DarDeBajaProductoHandler.php`
- Create: `src/Maestros/Infrastructure/Persistence/EloquentReplicaDeProductos.php`
- Modify: `src/Maestros/Application/Ingesta/IngestaErrors.php`, `ReplicarSocio/ReplicarSocioHandler.php` (usa `Vigencia`)
- Modify: `app/Providers/CoreServiceProvider.php` (`HANDLERS`), `app/Providers/ModulosServiceProvider.php` (binding)
- Create: `tests/Soporte/ProductoDeIngesta.php`
- Test: `tests/Feature/Maestros/ReplicarProductoTest.php`, `tests/Unit/Maestros/ProductoIngresadoTest.php`

**Interfaces:**
- Consumes: las columnas de la tarea 1.
- Produces:
  - `CategoriaIngresada::de(string $codigo, string $nombre): self`, que recorta el código y lo pasa a minúsculas; propiedades `codigo` y `nombre`.
  - `RegistroIngresado(string $entidad, string $numero)`.
  - `ProductoIngresado(string $itemCode, string $nombre, bool $activo, ?CategoriaIngresada $categoria, ?string $presentacion, ?string $ingredienteActivo, ?string $formulacionCodigo, ?string $formulacionDescripcion, ?string $dosisReferencial, list<string> $cultivos, ?RegistroIngresado $registro, DateTimeImmutable $vigenteDesde, string $origenEsquema, int $origenEventoId)`, con:
    - `formulacion(): ?string`;
    - `cultivosSinRepetir(): list<string>`.
  - `EstadoDeProductoReplicado(?DateTimeImmutable $vigenteDesde, bool $dadoDeBaja)`.
  - `interface ReplicaDeProductos`, con tres métodos:
    - `estado(string $itemCode): ?EstadoDeProductoReplicado`;
    - `replicar(ProductoIngresado $producto): void`;
    - `darDeBaja(string $itemCode, DateTimeImmutable $momento): void`.
  - `Vigencia::esMasNuevo(DateTimeImmutable $cuerpo, DateTimeImmutable $guardado): bool`, la de `ReplicarSocioHandler` movida: compara al segundo, y empatar es `false`.
  - `ReplicarProducto(OperacionDeIngesta $operacion, ?string $itemCodeDeLaRuta, ProductoIngresado $producto)`, que implementa `EscrituraDeMaquina, Request, RequiereTransaccion`. Su valor es un `ResultadoDeIngesta`.
  - `DarDeBajaProducto(string $itemCode, DateTimeImmutable $momento)`, con las mismas tres interfaces.
  - `IngestaErrors`:
    - `productoYaExiste(string $itemCode)`: conflicto `PRODUCTO_YA_EXISTE`;
    - `productoNoEncontrado(string $itemCode)`: no encontrado `PRODUCTO_NO_ENCONTRADO`;
    - `itemCodeNoCoincide()`: validación `ITEMCODE_NO_COINCIDE`.

- [ ] **Step 1: El armador de prueba**

`tests/Soporte/ProductoDeIngesta.php`, clase `ProductoDeIngesta`, con dos métodos:
- `cuerpo(array $sobrescribir = []): array`, el JSON;
- `ingresado(array $sobrescribir = []): ProductoIngresado`, que arma el DTO a partir del mismo `cuerpo()`.

Valores por defecto (el ALVO de 1 L):

```php
[
    'itemCode' => '10198', 'nombre' => 'ALVO - 1 L', 'activo' => true,
    'categoria' => ['codigo' => 'insecticidas', 'nombre' => 'INSECTICIDAS'],
    'presentacion' => '1 L',
    'ingredienteActivo' => 'Bifenthrin 280 g/L + Clothianidin 140 g/L',
    'formulacionCodigo' => 'SC', 'formulacionDescripcion' => 'SUSPENSION CONCENTRADA',
    'dosisReferencial' => '300 ml/ha, 400 ml/ha',
    'cultivos' => ['ARROZ (Oryza Sativa L.)', 'SOYA (Glycine Max L.)'],
    'registro' => ['entidad' => 'SENASAG', 'numero' => '4848'],
    'vigenteDesde' => '2026-10-16T12:00:00Z',
    'origen' => ['esquema' => 'AGRO_P6', 'eventoId' => 5120],
]
```

- [ ] **Step 2: Los tests**

`tests/Unit/Maestros/ProductoIngresadoTest.php`:
- `arma la formulacion como la del catalogo`: los dos valores dan «SUSPENSION CONCENTRADA (SC)»; solo el código da «SC»; solo la descripción da la descripción; ninguno da `null`.
- `deja cada cultivo una vez sin distinguir mayusculas`: `['SOYA', 'Soya', 'MAÍZ', 'MAIZ']` da `['SOYA', 'MAÍZ', 'MAIZ']`. La primera escritura gana, y los acentos sí distinguen, igual que `CI_AS`.
- `la categoria normaliza el codigo`: `CategoriaIngresada::de(' INSECTICIDAS ', 'X')->codigo` es `'insecticidas'`.

`tests/Feature/Maestros/ReplicarProductoTest.php`. En el `beforeEach`, `CatalogoDeEjemplo::sembrar()`. La función global es `replicarProducto(ProductoIngresado $p, OperacionDeIngesta $op = OperacionDeIngesta::Crear, ?string $ruta = null): Result`, que despacha `ReplicarProducto` por el `Mediator`. Los tests, con sus afirmaciones:

- `crear guarda la ficha, la categoria y los cultivos`:
  - la fila `10198` tiene `formulacion` = «SUSPENSION CONCENTRADA (SC)», `registro_numero` = '4848', `origen_esquema` = 'AGRO_P6' y `origen_evento_id` = 5120;
  - tiene 2 cultivos;
  - el valor es `ResultadoDeIngesta::Aplicado`.
- `crear un producto que existe responde PRODUCTO_YA_EXISTE`: con `itemCode` 'A-0142'.
- `crear uno dado de baja lo reactiva si es mas nuevo`: con 'A-0300', que en la semilla tiene la baja del 2026-10-10. Queda `dado_de_baja_el` en `null`.
- `crear uno dado de baja con un cuerpo mas viejo que la baja lo ignora`: `vigenteDesde` del 2026-10-09 → `IgnoradoPorViejo`, y la baja sigue.
- `reemplazar uno inexistente o dado de baja responde PRODUCTO_NO_ENCONTRADO`: con '99999' y con 'A-0300'.
- `reemplazar con otro itemCode responde ITEMCODE_NO_COINCIDE y no escribe nada`: ruta 'A-0142', cuerpo '10198'. No existe ninguna fila '10198'.
- `reemplazar conserva la descripcion y los cuatro enlaces`: con 'A-0142'. La descripción e `imagen_url`, `ficha_tecnica_url` y `hoja_seguridad_url` siguen con los valores de la semilla, y el nombre pasa a ser el del cuerpo.
- `un dato vacio no borra nada`: con 'A-0142' y `presentacion`, `ingredienteActivo`, `formulacionCodigo`, `formulacionDescripcion`, `dosisReferencial`, `registro` y `categoria` en `null`, y `cultivos` en `[]`. Siguen 'Bolsa 10 Kg', 'Glifosato sal de amonio 68%', 'Gránulos solubles (SG)', '1,5 – 3,0 Kg/ha', '1842-H', 'herbicidas' y los 2 cultivos de la semilla.
- `un cuerpo igual o mas viejo que lo guardado se ignora entero`:
  - se crea con '2026-10-16T12:00:00Z';
  - se reemplaza con el mismo instante y nombre 'OTRO' → `IgnoradoPorViejo`, y el nombre sigue 'ALVO - 1 L';
  - un `PUT` a '2026-10-16T12:00:00.900Z' también se ignora (mismo segundo).
- `un producto de la web acepta cualquier vigencia`: 'A-0142', sin `vigente_desde`, se reemplaza con un cuerpo de 2001 → `Aplicado`.
- `una categoria nueva se crea con el nombre de SAP y una existente no cambia de nombre`:
  - `tratamiento-de-semillas` / 'TRATAMIENTO DE SEMILLAS' queda creada;
  - con `' INSECTICIDAS'` / 'INSECTICIDAS', la fila de `insecticidas` sigue llamándose 'Insecticidas', no hay otra fila, y el producto apunta a `insecticidas`.
- `cultivos repetidos por mayusculas dan una sola fila`: con `['SOYA', 'Soya']` queda 1 fila.
- `dar de baja fija la baja y la vigencia en el momento y conserva la ficha`:
  - `DarDeBajaProducto('A-0142', momento)` → éxito;
  - `dado_de_baja_el` y `vigente_desde` quedan en el momento;
  - la descripción y los enlaces siguen.
- `dar de baja uno inexistente o ya dado de baja responde PRODUCTO_NO_ENCONTRADO`: con '99999' y con 'A-0300'.

- [ ] **Step 3: Correrlos y verlos en rojo**

Run: `vendor/bin/pest tests/Unit/Maestros/ProductoIngresadoTest.php tests/Feature/Maestros/ReplicarProductoTest.php`
Expected: FAIL, porque las clases no existen.

- [ ] **Step 4: Los DTO, el puerto, los errores y `Vigencia`**

Las firmas son las del bloque Interfaces. `ReplicarSocioHandler` pasa a usar `Vigencia::esMasNuevo` y se borra su método privado.

- [ ] **Step 5: Los dos handlers**

`ReplicarProductoHandler` sigue el orden de `ReplicarSocioHandler`, sin el paso del tipo:
1. En `Reemplazar`, la ruta tiene que coincidir con el cuerpo; si no, `itemCodeNoCoincide`.
2. Se lee el `estado`. El producto está vigente si existe y no está dado de baja.
3. Crear uno vigente da `productoYaExiste`, y reemplazar uno que no es vigente da `productoNoEncontrado`.
4. Si el estado tiene `vigenteDesde` y el cuerpo no es más nuevo, el valor es `IgnoradoPorViejo`.
5. Si no, `replicar` y `Aplicado`.

`DarDeBajaProductoHandler`: inexistente o ya dado de baja da `productoNoEncontrado`; si no, `darDeBaja`.

Se registran los dos en `CoreServiceProvider::HANDLERS`.

- [ ] **Step 6: El adaptador**

`EloquentReplicaDeProductos` usa la tabla `maestros.producto` o `maestros_producto`, como `tabla()` en los otros adaptadores del catálogo, y el trait `FechaEnUtc` para las fechas. `replicar` hace cuatro cosas, en orden:

1. **Categoría,** si viene y su código no existe: `insert` dentro de un `try` que atrapa `Illuminate\Database\UniqueConstraintViolationException` y sigue. Nunca `insertOrIgnore`. El `catch` cubre dos envíos que crean a la vez la misma categoría. La batería no puede provocar esa carrera, así que la tarea 6 comprueba en SQL Server que la transacción sigue usable después del `catch`.
2. **Columnas** que se escriben siempre: `nombre`, `activo`, `vigente_desde`, `origen_esquema`, `origen_evento_id`, `dado_de_baja_el = null` y `actualizado_en`.
3. **Columnas** que se escriben solo si traen valor: `presentacion`, `codigo_categoria`, `ingrediente_activo`, `formulacion` (con `formulacion()`), `dosis_referencial`, y `registro_entidad` con `registro_numero` juntos. **Nunca** `descripcion`, los cuatro `*_url` ni `wp_id`. Se actualiza por `codigo_articulo`, o se inserta si no hay fila.
4. **Cultivos:** si `cultivosSinRepetir()` no está vacía, se borran los del producto y se insertan esos.

`ModulosServiceProvider` liga `ReplicaDeProductos` a `EloquentReplicaDeProductos`.

- [ ] **Step 7: Correr y verlos en verde, con la batería entera**

Run: `composer test && composer stan`
Expected: PASS. `AlcanceTest` acepta las dos peticiones nuevas porque viven en `Maestros\Application\Ingesta\`.

- [ ] **Step 8: Commit**

```bash
git add src app tests
git commit -m "feat(productos): casos de uso de la ingesta de productos"
```

---

### Task 3: La API

**Files:**
- Modify: `contrato/ingesta-api-v1.yaml`
- Modify: `src/Maestros/Presentation/Http/ingesta.php`
- Create: `src/Maestros/Presentation/Http/IngestaDeProductosController.php`
- Modify: `app/Http/MapaDeErroresHttp.php` (`'ITEMCODE_NO_COINCIDE' => 422`)
- Test: `tests/Feature/Ingesta/ContratoDeIngestaTest.php`, `tests/Feature/Ingesta/RutasDeIngestaDeProductosTest.php` (nuevo)

**Interfaces:**
- Consumes: `ReplicarProducto`, `DarDeBajaProducto`, `ProductoIngresado`, `CategoriaIngresada::de`, `RegistroIngresado`, `ResultadoDeIngesta` y `IngestaErrors::productoNoEncontrado` (tarea 2), y `ProductoDeIngesta::cuerpo` (tarea 2).
- Produces: `POST /ingesta/v1/productos` (201), `PUT /ingesta/v1/productos/{itemCode}` (200) y `DELETE /ingesta/v1/productos/{itemCode}` (204), con `data: { itemCode }` en los dos primeros.

- [ ] **Step 1: Los tests**

En `ContratoDeIngestaTest.php`, el primer test pasa a esperar seis operaciones: las tres de socios más `POST ingesta/v1/productos`, `PUT ingesta/v1/productos/{itemCode}` y `DELETE ingesta/v1/productos/{itemCode}`.

En `RutasDeIngestaDeProductosTest.php`:
- **El `beforeEach`:** `CatalogoDeEjemplo::sembrar()` y `TokenDeEntra::configurar()`.
- **Las funciones globales:** `crearProductoPorIngesta`, `reemplazarProductoPorIngesta` y `darDeBajaProductoPorIngesta`. Cada una le pega a la ruta con `Ingesta::cabeceras()` y pasa la respuesta por `Contrato::diferencias(..., 'ingesta-api-v1.yaml')`, que tiene que dar `[]`. Usan `ProductoDeIngesta::cuerpo()`.
- **Sin redeclarar nombres de otros archivos:** `cumpleElContratoDeIngesta` ya existe en `RutasDeIngestaTest.php`, y la constante `CON_SESION` en `ProductosTest.php`. Si se declaran de nuevo, la batería completa da un error fatal. Para la sesión de la App se usa la cabecera escrita a mano.
- **Los tests:**
  - `POST crea el producto y responde 201 con su itemCode`;
  - `POST de un producto que existe responde 409 PRODUCTO_YA_EXISTE`, con `A-0142`;
  - `POST y despues PUT con la misma clave aplican el PUT`: el 409 y el reintento, como hace el Sincronizador, con una `Idempotency-Key` fija;
  - `PUT con otro itemCode en la ruta responde 422 ITEMCODE_NO_COINCIDE`;
  - `PUT de un producto inexistente responde 404 PRODUCTO_NO_ENCONTRADO`;
  - `DELETE responde 204 sin cuerpo, y otra vez 404`;
  - `un cuerpo mal formado responde 400 con un FieldError por campo`, con un dataset por caso:
    - falta `nombre`;
    - `activo` no es booleano;
    - `categoria` sin `codigo`;
    - `registro.entidad` = 'OTRA';
    - `cultivos` ausente;
    - `itemCode` de 51 caracteres;
    - `presentacion` de 256;
  - `los campos que no estan en el contrato se ignoran`: el cuerpo con `descripcion` 'Pisada' e `imagenUrl` 'https://x/y.webp' sobre `A-0142` responde 200, y las dos columnas siguen como en la semilla;
  - `textos vacios o con espacios no pisan la ficha` (foco 3): `PUT` de `A-0142` con `presentacion` '   ', `dosisReferencial` '' y `cultivos` `['  ']`. Siguen 'Bolsa 10 Kg', '1,5 – 3,0 Kg/ha' y los 2 cultivos;
  - `la vigencia del Sincronizador se pasa a UTC y se compara al segundo` (foco 1):
    - con '2026-10-15T19:32:58.866982+00:00' se crea y queda guardado '2026-10-15 19:32:58';
    - un `PUT` con '2026-10-15T15:32:58-04:00', el mismo instante, cambia el nombre y se ignora: el nombre no cambia;
  - `inactivo sale de la App y reactivado vuelve con sus enlaces` (foco 5):
    - `PUT` de `A-0142` con `activo: false` y vigencia posterior;
    - `GET /v1/productos/A-0142` con sesión responde 404;
    - otro `PUT` con `activo: true` y vigencia posterior;
    - el `GET` vuelve a 200, con `imagenUrl` de la semilla;
    - la sesión es la de `ProductosTest`: `VerificadorFalso` y `Bearer token-bueno`;
  - `cada pedido deja su linea en el log`: con `Log::spy()`, un `info('ingesta', [...])` con `itemCode` '10198', `operacion` 'crear', `resultado` 'aplicado' y `eventoId` 5120.

- [ ] **Step 2: Correrlos y verlos en rojo**

Run: `vendor/bin/pest tests/Feature/Ingesta/ContratoDeIngestaTest.php tests/Feature/Ingesta/RutasDeIngestaDeProductosTest.php`
Expected: FAIL. Faltan las operaciones en el contrato y las rutas responden 404.

- [ ] **Step 3: El contrato**

En `contrato/ingesta-api-v1.yaml`:
- **Tag `Productos`** y un párrafo en `info.description`: los productos van por la misma seguridad y la misma idempotencia, y `descripcion` y los enlaces del cuerpo se ignoran.
- **Las rutas**, con `x-agropartners-servicio: maestros`:
  - `/productos` con `post` (`operationId: ingestaCrearProducto`);
  - `/productos/{itemCode}` con `put` (`ingestaReemplazarProducto`) y `delete` (`ingestaEliminarProducto`).
- **Los status**, con las mismas respuestas de seguridad que los socios (400, 401, 403, 500, 503):
  - `POST`: 201 y 409;
  - `PUT`: 200, 404 y 422;
  - `DELETE`: 204 y 404.
- **Componentes nuevos:**
  - el parámetro `ItemCode`;
  - los esquemas `ProductoIngesta` (los campos y largos de la tabla de la spec, §1, sin `additionalProperties: false`), `CategoriaIngesta`, `RegistroIngesta` (`entidad: enum [SENASAG, INIAF, otro]`) y `RespuestaProducto` (`data: { itemCode }`, como `RespuestaSocio`);
  - las respuestas `ProductoYaExiste` («el Sincronizador reintenta como PUT») y `ProductoNoEncontrado` («en PUT reintenta como POST; en DELETE lo toma como éxito»).
- **`DatosInvalidos`** nombra también `ITEMCODE_NO_COINCIDE`.

- [ ] **Step 4: Las rutas y el controlador**

En `ingesta.php`, dentro del mismo grupo:
- `Route::post('productos', [IngestaDeProductosController::class, 'crear'])`;
- `put` y `delete` en `'productos/{itemCode}'`.

`IngestaDeProductosController` tiene la forma de `IngestaController`:
- **`crear`, `reemplazar` y `eliminar`,** con `replicar(...)` privado, que valida, despacha y registra el log.
- **Las reglas de `validate`:**

```php
'itemCode' => ['required', 'string', 'max:50', 'regex:/^[^\/]+$/'],
'nombre' => ['required', 'string', 'max:255'],
'activo' => ['required', 'boolean'],
'categoria' => ['present', 'nullable', 'array'],
'categoria.codigo' => ['required_with:categoria', 'string', 'max:50'],
'categoria.nombre' => ['required_with:categoria', 'string', 'max:255'],
'presentacion' => ['nullable', 'string', 'max:255'],
'ingredienteActivo' => ['nullable', 'string', 'max:500'],
'formulacionCodigo' => ['nullable', 'string', 'max:20'],
'formulacionDescripcion' => ['nullable', 'string', 'max:200'],
'dosisReferencial' => ['nullable', 'string', 'max:500'],
'cultivos' => ['present', 'array'],
'cultivos.*' => ['nullable', 'string', 'max:255'],
'registro' => ['nullable', 'array'],
'registro.entidad' => ['required_with:registro', 'in:SENASAG,INIAF,otro'],
'registro.numero' => ['required_with:registro', 'string', 'max:100'],
'vigenteDesde' => ['required', 'date'],
'origen.esquema' => ['required', 'string', 'max:20'],
'origen.eventoId' => ['required', 'integer', 'min:0'],
```

- **El armado de `ProductoIngresado`:**
  - los textos recortados, y uno vacío pasa a `null`;
  - los cultivos vacíos se descartan;
  - `vigenteDesde` pasa a UTC, como en `socioIngresado()`.
- **La línea del log:** `Log::info('ingesta', ['itemCode', 'operacion', 'resultado', 'eventoId'])`, con `operacion` 'crear', 'reemplazar' o 'eliminar'.

`MapaDeErroresHttp::POR_CODIGO` suma `'ITEMCODE_NO_COINCIDE' => 422`.

- [ ] **Step 5: Correr y verlos en verde, con la batería entera**

Run: `composer test && composer stan && vendor/bin/pint && composer lint -- --test`
Expected: PASS. Si falla el test de textos vacíos, es que `TrimStrings` o `ConvertEmptyStringsToNull` no corren en `/ingesta`. La solución es recortar en el controlador, no quitar el test.

- [ ] **Step 6: Commit**

```bash
git add contrato src app tests
git commit -m "feat(productos): POST, PUT y DELETE de /ingesta/v1/productos"
```

---

### Task 4: La descripción en el back-office

**Files:**
- Create: `src/Maestros/Application/Productos/CambiarDescripcion/CambiarDescripcionDeProducto.php` y `CambiarDescripcionDeProductoHandler.php`, `src/Maestros/Application/Productos/DescripcionCambiada.php`
- Modify: `src/Maestros/Application/Productos/CatalogoAdministrable.php`, `ProductoAdministrable.php`, `src/Maestros/Infrastructure/Persistence/EloquentCatalogoAdministrable.php`
- Create: `src/BackOffice/Application/Catalogo/ActualizarDescripcion/ActualizarDescripcion.php` y `ActualizarDescripcionHandler.php`
- Modify: `src/BackOffice/Presentation/Http/ProductosController.php` (`guardar`), `src/BackOffice/Presentation/Views/producto.blade.php`, `app/Providers/CoreServiceProvider.php`
- Modify: `tests/Unit/Maestros/ProductoAdministrableTest.php` (constructor)
- Test: `tests/Feature/BackOffice/PantallaDeProductosTest.php`, `tests/Feature/Maestros/CambiarDescripcionDeProductoTest.php` (nuevo)

**Interfaces:**
- Consumes: `ProductoAdministrable` (tarea 1).
- Produces:
  - `ProductoAdministrable` suma `?string $descripcion`, después de `llegoDeSap`;
  - `CatalogoAdministrable::guardarDescripcion(int $id, ?string $descripcion): void`;
  - `CambiarDescripcionDeProducto(int $idProducto, ?string $descripcion)`, que implementa `Request, RequiereTransaccion` y tiene como valor un `DescripcionCambiada(int $idProducto, ?string $itemCode, ?string $anterior, ?string $nuevo)` con `cambio(): bool`;
  - `ActualizarDescripcion(int $idProducto, ?string $descripcion, Operador $operador, string $direccionIp)`, que implementa `Request, RequiereTransaccion` y tiene como valor un `int`, 0 o 1.

Se hace con un caso de uso aparte, y no ensanchando `CambiarEnlacesDeProducto`. `CampoDeEnlace` arma el formulario y valida URLs, y la descripción no es un enlace. El controlador valida los cinco campos antes de despachar, así que el segundo envío no puede fallar por datos.

- [ ] **Step 1: Los tests**

`CambiarDescripcionDeProductoTest.php`, por el `Mediator` y con `CatalogoDeEjemplo`:
- `cambia la descripcion y devuelve la anterior`:
  - en Gliforte, a 'Nueva';
  - `anterior` es el texto de la semilla, `nuevo` es 'Nueva' y la columna también.
- `vacia o con espacios la quita`: '   ' da `nuevo` `null` y la columna en `null`.
- `la misma descripcion no es un cambio`: `cambio()` da `false`.
- `mas de 1000 caracteres es un error del campo descripcion`: 1001 'a' falla con un `FieldError` sobre `descripcion`, y la columna no cambia.
- `un id que no existe responde PRODUCTO_NO_ENCONTRADO`.

En `PantallaDeProductosTest.php`:
- `formularioDeGliforte()` suma `'descripcion' => 'Herbicida sistémico no selectivo de amplio espectro.'`, el valor de la semilla, así los tests de hoy siguen viendo «No había cambios».
- `un POST sin los campos no borra nada` suma `descripcion` a los errores esperados, y afirma que la descripción no cambió.
- Nuevos:
  - `guardar la descripcion la asienta en la bitacora`: con `formularioDeGliforte(['descripcion' => 'Otra'])`, el aviso es «Se guardó 1 cambio.», hay un asiento con `campo` 'descripcion' y la página muestra «Descripción» en el historial;
  - `una descripcion de mas de 1000 caracteres vuelve con el error`: `assertSessionHasErrors('descripcion')` y 0 asientos;
  - `el formulario muestra la descripcion actual`.

- [ ] **Step 2: Correrlos y verlos en rojo**

Run: `vendor/bin/pest tests/Feature/Maestros/CambiarDescripcionDeProductoTest.php tests/Feature/BackOffice/PantallaDeProductosTest.php`
Expected: FAIL.

- [ ] **Step 3: El caso de uso de Maestros**

`CambiarDescripcionDeProductoHandler`:
- **Normaliza** el texto: lo recorta, y vacío es `null`.
- **Valida** el largo con `mb_strlen` > 1000. Si se pasa, devuelve `ValidationError(new FieldError('descripcion', 'DESCRIPCION_DEMASIADO_LARGA', 'La descripción admite hasta 1000 caracteres.'))`. 1000 es el largo de `valor_anterior` y `valor_nuevo` en la bitácora.
- **Busca** el producto. Si no existe, el mismo `Error::notFound('PRODUCTO_NO_ENCONTRADO', ...)` de `CambiarEnlacesDeProductoHandler`.
- **Toma el anterior de la base,** no del formulario.
- **Guarda** solo si cambió.

`EloquentCatalogoAdministrable` selecciona `p.descripcion` e implementa `guardarDescripcion`, que también toca `actualizado_en`.

- [ ] **Step 4: El back-office**

- **`ActualizarDescripcionHandler`:** despacha `CambiarDescripcionDeProducto`. Si hubo cambio, asienta un `CambioDeCatalogo` con `campo: 'descripcion'`, como `ActualizarEnlacesHandler`.
- **`ProductosController::guardar`:**
  - suma la regla `'descripcion' => ['present', 'nullable', 'string', 'max:1000']`;
  - despacha `ActualizarEnlaces`; si falla, redirige con el error como hoy;
  - si no, despacha `ActualizarDescripcion`;
  - el aviso cuenta la suma de los dos.
- **La vista:**
  - un `<textarea name="descripcion" maxlength="1000">` con `old('descripcion', $producto->descripcion)` y la etiqueta «Descripción», antes de los enlaces;
  - el texto «Vacía no se muestra en la App»;
  - en el historial, `campo` 'descripcion' se lee «Descripción».

Los dos handlers se registran en `CoreServiceProvider::HANDLERS`.

- [ ] **Step 5: Correr y verlos en verde, con la batería entera**

Run: `composer test && composer stan`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src app tests
git commit -m "feat(backoffice): la descripción del producto se carga desde el back-office"
```

---

### Task 5: El comando de la transición

**Files:**
- Modify: `src/Maestros/Application/Productos/CatalogoAdministrable.php`, `src/Maestros/Infrastructure/Persistence/EloquentCatalogoAdministrable.php`
- Create: `src/Maestros/Infrastructure/Catalogo/ProductosSinSapCommand.php`
- Modify: `app/Providers/ModulosServiceProvider.php` (lista de comandos)
- Test: `tests/Feature/Maestros/ProductosSinSapTest.php`

**Interfaces:**
- Consumes: `ProductoAdministrable::$llegoDeSap`, `$dadoDeBaja` y `visibleEnApp()` (tarea 1).
- Produces: `CatalogoAdministrable::darDeBaja(int $id, DateTimeImmutable $momento): void`, que fija `dado_de_baja_el` y `vigente_desde` en el momento, en UTC, igual que un `DELETE`. También el comando `maestros:productos-sin-sap {--dar-de-baja}`.

Va por id y no por `itemCode`, porque hay productos de la web sin código.

- [ ] **Step 1: Los tests**

Con `CatalogoDeEjemplo`. Los seis productos no tienen origen, y `A-0300` está dado de baja. En el `beforeEach`, a Gliforte se le pone `origen_esquema` 'AGRO_P6'.

- `sin opciones lista los que nunca llegaron de SAP y no escribe nada`:
  - sale exitoso;
  - la salida nombra «Sorgo Jisunú 101», «Atrazina 90 WG», «Sin código SAP todavía» y «Sin categoría todavía»;
  - no nombra «Gliforte 68 SG» ni «Dado de baja»;
  - no hay filas nuevas con `dado_de_baja_el`.
- `con --dar-de-baja les da de baja solo a esos`:
  - la salida dice «Se dieron de baja 4 productos.»;
  - los 4 quedan con `dado_de_baja_el` y `vigente_desde`;
  - Gliforte no.
- `sin nada que dar de baja lo dice`: si los 6 tienen origen, la salida es «Todos los productos llegaron de SAP.».

- [ ] **Step 2: Correrlos y verlos en rojo**

Run: `vendor/bin/pest tests/Feature/Maestros/ProductosSinSapTest.php`
Expected: FAIL, porque el comando no existe.

- [ ] **Step 3: El comando y `darDeBaja`**

`ProductosSinSapCommand`:
- **Toma los candidatos** de `CatalogoAdministrable::listar(null, FiltroDeEnlaces::Todos)`: los que no llegaron de SAP y no están dados de baja.
- **Los muestra** con `$this->table(['Código', 'Nombre', 'Se ve en la App'], ...)`, con «Sí» o «No».
- **Con la opción,** llama a `darDeBaja` para cada uno con el momento actual en UTC e imprime «Se dieron de baja N productos.».
- **Sin candidatos,** imprime solo «Todos los productos llegaron de SAP.».
- **Devuelve** `self::SUCCESS`.

Se registra junto a `ImportarMaestrosCommand`.

- [ ] **Step 4: Correr y verlos en verde, con la batería entera**

Run: `composer test && composer stan`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src app tests
git commit -m "feat(productos): comando para dar de baja lo que nunca llegó de SAP"
```

---

### Task 6: SQL Server y la documentación

**Files:**
- Modify: `CLAUDE.md`
- Scratch (no se commitea): un script PHP en el scratchpad de la sesión.

- [ ] **Step 1: Migrar contra el SQL Server local**

Run:
```bash
docker compose up -d sqlserver sqlserver-init
docker compose build app
MSYS_NO_PATHCONV=1 docker compose run --rm --no-deps -T app php artisan migrate --force
```
Expected: la migración `2026_10_16_000100_preparar_el_catalogo_para_la_ingesta` corre sin error. Volver a correr `migrate --force` dice que no hay nada pendiente. Si al principio dice «Cannot open database», volver a correr `sqlserver-init` y reintentar.

- [ ] **Step 2: Ejercitar las consultas nuevas en SQL Server**

Un script que arranca Laravel (`require 'vendor/autoload.php'` y el kernel de consola) y, dentro de `DB::transaction`, verifica cinco cosas:
1. **`replicar`** de un producto con una categoría nueva, y después **`estado`**.
2. **El `catch` de la carrera:** dentro de la transacción, insertar dos veces la misma categoría, atrapar la `UniqueConstraintViolationException` y después `replicar` otro producto. La transacción tiene que seguir usable y el producto tiene que quedar escrito, que es lo que asume el adaptador.
3. **Cultivos `['SOYA', 'Soya']`:** queda una fila.
4. **`darDeBaja`**, y que `EloquentCatalogoDeProductos::producto()` ya no lo devuelva.
5. **`CatalogoAdministrable::listar`** trae `dadoDeBaja` y `llegoDeSap`.

Al final, `DB::rollBack()` para no dejar datos.

Run: `MSYS_NO_PATHCONV=1 docker compose run --rm --no-deps -T -v "<scratchpad>:/scratch" app php /scratch/verificar-productos.php`
Expected: las cinco verificaciones dicen OK. Si falla la segunda, el adaptador tiene que crear la categoría con una consulta que no viole la unicidad (`INSERT ... SELECT ... WHERE NOT EXISTS`) en lugar de atrapar la excepción, con su test en la tarea 2.

- [ ] **Step 3: `CLAUDE.md`**

En «Contratos que no se negocian», el párrafo «El catálogo de productos no es réplica de SAP» se reescribe con lo que dice la spec en «Lo que queda escrito»:
- es réplica en parte, y la descripción y los cuatro enlaces son solo del back-office;
- cada artículo de SAP es un producto;
- pasa por `vigenteDesde`, con la regla de los socios, y la baja es lógica;
- la categoría se asigna desde SAP y su nombre no se pisa;
- un dato vacío de SAP no borra nada hasta conciliar la ficha.

El párrafo de los enlaces deja de decir «una sincronización futura»: la ingesta existe y no escribe esas columnas. La viñeta de la ingesta suma las tres rutas de productos y el comando `maestros:productos-sin-sap`, que se corre desde la consola del Container App cuando el Sincronizador termina la carga inicial. Las migraciones del catálogo van antes del push, igual que las de la réplica.

- [ ] **Step 4: Verificación final y commit**

Run: `composer test && composer stan && composer lint -- --test`
Expected: PASS.

```bash
git add CLAUDE.md
git commit -m "docs: el catálogo pasa a ser réplica de SAP en parte"
```
