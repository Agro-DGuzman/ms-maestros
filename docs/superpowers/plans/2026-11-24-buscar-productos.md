# Buscar productos por nombre — plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** que `GET /v1/productos` acepte `buscar` y devuelva los productos cuyo nombre contiene todas las palabras del texto, sin distinguir mayúsculas ni acentos.

**Architecture:**
- El puerto `CatalogoDeProductos::pagina()` suma `?string $buscar` al final, con `null` por defecto.
- `EloquentCatalogoDeProductos` arma el filtro con un `LIKE` por palabra sobre `p.nombre`, con la colación de `ComparacionSinAcentos`.
- El escape de `LIKE` pasa al trait, para que el catálogo y el back-office compartan una sola regla.
- El controlador valida `buscar` como el contrato.

**Tech Stack:** Laravel 13 / PHP 8.4, Pest, Larastan 9, SQL Server en Azure y SQLite en las pruebas.

**Spec:** `docs/superpowers/handoffs/2026-11-24-buscar-productos.md`. Deja escritas las decisiones del 24/11 y los bloques exactos del contrato.

## Global Constraints

- `declare(strict_types=1);` en todo archivo de `src/`. `tests/Architecture` tiene que seguir en verde sin tocarlo.
- **Las reglas de la búsqueda:**
  - El nombre de columna que llega a `comoTextoInsensible()` es un literal del código, nunca algo de la petición.
  - Solo se compara contra `p.nombre`: ni `codigo_articulo`, ni `descripcion`, ni la categoría.
  - El texto se parte en palabras con `preg_split('/\s+/u', …, -1, PREG_SPLIT_NO_EMPTY)`. Cada palabra es un `LIKE` que tiene que cumplirse (AND).
  - El patrón va en minúsculas, con `\`, `%`, `_` y `[` escapados y `ESCAPE '\'` explícito.
- **Los bordes:**
  - `buscar`: `nullable`, `string`, `max:80`. Pasarse responde 400 `PARAMETRO_INVALIDO` sobre el campo `buscar`.
  - Vacío o solo espacios no filtra.
  - El orden no cambia: `p.nombre`, después `p.codigo_articulo`.
  - `categorias()` no cambia.
- **El contrato:** se edita a mano solo con los bloques del handoff. Es la excepción aprobada el 24/11 con el visor vencido.
- **Al cerrar:** `composer test`, `composer stan` y `composer lint -- --test` en verde antes de cada commit. Commits en español. Sin push.

## Review Focus

1. **`%`, `_` o `[` sueltos:** no tienen que devolver todo el catálogo. → Task 1, «los comodines de LIKE cuentan como texto».
2. **Varios espacios entre palabras, o las palabras en otro orden:** tiene que encontrar igual. → Task 1, «las palabras van en cualquier orden y con cualquier espacio».
3. **Un producto que la App no ve, pero cuyo nombre coincide:** no tiene que aparecer. → Task 1, «la busqueda no muestra lo que la App no puede ver».
4. **Buscar por el `itemCode`:** no encuentra nada, porque solo se busca por nombre. → Task 1, «no busca en el itemCode».
5. **Acentos en SQL Server:** no se pueden probar en SQLite. Se afirma el SQL que se genera. → Task 1, «el catalogo de la App lleva la colacion al nombre, una vez por palabra».

---

### Task 1: El catálogo busca por nombre

**Files:**
- Modify: `app/Persistence/ComparacionSinAcentos.php` (suma `patronQueContiene`)
- Modify: `src/Maestros/Infrastructure/Persistence/EloquentCatalogoAdministrable.php:33-44` (usa `patronQueContiene` en lugar de su escape propio)
- Modify: `src/Maestros/Application/Productos/CatalogoDeProductos.php:26`
- Modify: `src/Maestros/Infrastructure/Persistence/EloquentCatalogoDeProductos.php` (`pagina`, nuevo `filtrada`, `use ComparacionSinAcentos`)
- Modify: `src/Maestros/Presentation/Http/ProductosController.php:26-55`
- Test: `tests/Feature/Maestros/ProductosTest.php`, `tests/Feature/Maestros/BusquedaSinTildesTest.php`, `tests/Unit/App/ComparacionSinAcentosTest.php` (nuevo)

**Interfaces:**
- Produces:
  - `ComparacionSinAcentos::patronQueContiene(string $texto): string`: devuelve `'%'.<texto en minúsculas con \ % _ [ escapados>.'%'`.
  - `CatalogoDeProductos::pagina(?string $codigoDeCategoria, int $pagina, int $tamanoDePagina, ?string $buscar = null): PaginaDeProductos`.
  - `EloquentCatalogoDeProductos::filtrada(?string $codigoDeCategoria, ?string $buscar): Illuminate\Database\Query\Builder`. Es privado; la prueba del SQL lo invoca por reflexión, como `BusquedaSinTildesTest` hace con el buscador de contactos.

- [ ] **Step 1: Las pruebas que fallan.**

  **En `tests/Unit/App/ComparacionSinAcentosTest.php`,** con una clase anónima que usa el trait y expone el método:

  ```php
  it('el patron va en minusculas y con los comodines de LIKE escapados', function () {
      expect(patronDe('Agrogibe 4%_[X\\'))->toBe('%agrogibe 4\%\_\[x\\\\%');
  });
  ```

  **En `ProductosTest.php`,** con el catálogo de ejemplo. Son visibles A-0219 «Atrazina 90 WG», A-0142 «Gliforte 68 SG» y S-0101 «Sorgo Jisunú 101», y no se ven «Dado de baja», «Sin código SAP todavía» ni «Sin categoría todavía». Cada caso con `codigosListados()`:

  | Prueba | Pedido | Resultado |
  |---|---|---|
  | `'busca por el nombre sin distinguir mayusculas'` | `buscar=GLIFORTE` | `['A-0142']` |
  | `'todas las palabras tienen que estar en el nombre'` | `buscar=gliforte%2068` | `['A-0142']` |
  | | `buscar=gliforte%2090` | `[]` |
  | `'las palabras van en cualquier orden y con cualquier espacio'` | `buscar=sg%20%20%20gliforte` | `['A-0142']` |
  | `'se suma a la categoria'` | `categoria=semillas&buscar=gliforte` | `[]` |
  | | `categoria=herbicidas&buscar=atrazina` | `['A-0219']` |
  | `'la busqueda no muestra lo que la App no puede ver'` | `buscar=baja` | `[]` |
  | | `buscar=todav` | `[]` |
  | `'no busca en el itemCode'` | `buscar=A-0142` | `[]` |
  | `'los comodines de LIKE cuentan como texto'` | `buscar=%25` | `[]` |
  | | `buscar=_` | `[]` |
  | `'vacio o con solo espacios no filtra'` | `buscar=` | `['A-0219', 'A-0142', 'S-0101']` |
  | | `buscar=%20%20` | `['A-0219', 'A-0142', 'S-0101']` |
  | `'sin resultados es una lista vacia, no un error'` | `buscar=zzz` | 200, `data.items` en `[]` y `data.paginacion.total` en `0` |
  | `'acepta hasta 80 caracteres'` | `buscar=` + 80 `a` | 200 |

  En el dataset de `'rechaza parametros fuera del contrato'`, sumar `'buscar demasiado largo' => ['buscar='.str_repeat('a', 81), 'buscar']`.

  **En `BusquedaSinTildesTest.php`:**

  ```php
  it('el catalogo de la App lleva la colacion al nombre, una vez por palabra', function () {
      config(['database.default' => 'sqlsrv']);
      // filtrada(null, 'jisunu 101') por reflexión; solo ->toSql(), nunca se ejecuta.
      expect(substr_count($sql, 'p.nombre COLLATE Latin1_General_CI_AI LIKE ?'))->toBe(2)
          ->and($sql)->not->toContain('p.codigo_articulo COLLATE');
  });
  ```

- [ ] **Step 2: Ver que fallan.**
  - Comando: `vendor/bin/pest tests/Unit/App/ComparacionSinAcentosTest.php tests/Feature/Maestros/ProductosTest.php tests/Feature/Maestros/BusquedaSinTildesTest.php`
  - Esperado:
    - FAIL: `patronQueContiene` no existe, y tampoco `filtrada`;
    - FAIL: las pruebas de búsqueda, porque `buscar` se ignora y se lista todo;
    - FAIL: `'buscar demasiado largo'` responde 200;
    - pasan, porque hoy ya dan ese resultado: solo «vacio o con solo espacios no filtra» y «acepta hasta 80 caracteres». Los casos que esperan `[]` fallan, porque hoy `buscar` se ignora y se lista todo; eso prueba que el filtro es el que los vacía.

- [ ] **Step 3: `patronQueContiene(string $texto): string` en el trait,** y `EloquentCatalogoAdministrable::listar()` pasa a usarlo, sin cambiar su comportamiento. Las pruebas del back-office (`tests/Feature/Backoffice`) tienen que seguir en verde.

- [ ] **Step 4: `filtrada()` y `pagina(..., ?string $buscar = null)` en `EloquentCatalogoDeProductos`.**
  - Por cada palabra: `whereRaw($this->comoTextoInsensible('p.nombre')." LIKE ? ESCAPE '\\'", [$this->patronQueContiene($palabra)])`.
  - `pagina()` cuenta y pagina sobre `filtrada()`.
  - En el puerto, `buscar` va al final de `pagina()`, con su línea de por qué.
  - En el controlador:
    - `'buscar' => ['nullable', 'string', 'max:80']`;
    - pasa `isset($datos['buscar']) ? (string) $datos['buscar'] : null` como cuarto argumento.

- [ ] **Step 5: Ver que pasan.**
  - Comando: los tres archivos del Step 2, más `tests/Feature/Backoffice` y `tests/Architecture`.
  - Esperado: PASS.
  - Después, `composer stan`.

- [ ] **Step 6: Commit** — `feat(productos): la App busca productos por nombre`.

---

### Task 2: El contrato y lo que queda escrito

**Files:**
- Modify: `contrato/agropartners-api-v1.yaml` (bloques 1 y 2 del handoff)
- Modify: `tests/Feature/ContratoDeRespuestasTest.php` (el dataset de `'el catalogo de productos'`)
- Modify: `CLAUDE.md` (el punto del catálogo y la nota de «Pendientes conocidos»)
- Modify: `docs/ms-maestros.postman_collection.json`: un pedido «12b. Buscar productos», `GET {{baseUrl}}/v1/productos?buscar=gliforte`, después de «12. Productos». Es un archivo formateado a mano: se edita con Edit, no regenerando el JSON.
- Add: `docs/superpowers/handoffs/2026-11-24-buscar-productos.md`, con `git add -f` porque `docs/` está en `.gitignore`.

**Interfaces:**
- Consumes: Task 1 (`buscar` en `GET /v1/productos`, el 400 sobre `buscar`).

- [ ] **Step 1: El dataset de `'el catalogo de productos'`,** con dos casos:
  - `'listado con busqueda' => ['/productos?buscar=gliforte', '/productos', CON_TOKEN, 200]`;
  - `'listado con busqueda demasiado larga' => ['/productos?buscar='.str_repeat('a', 81), '/productos', CON_TOKEN, 400]`.

  Después, aplicar al contrato los bloques 1 y 2 del handoff, al pie de la letra.

- [ ] **Step 2: Ver que pasan, y que el contrato sigue parseando.**
  - Comando: `vendor/bin/pest tests/Feature/ContratoDeRespuestasTest.php tests/Feature/ContratoTest.php`
  - Esperado: PASS. Estos casos no pueden verse en rojo antes del contrato: el esquema de 200 y de 400 ya existe. Lo que prueban es que la respuesta nueva cumple el contrato nuevo.

- [ ] **Step 3: `CLAUDE.md`.**
  - En el punto del catálogo, una frase: `buscar` filtra por el nombre, palabra por palabra, sin mayúsculas ni acentos, con el escape compartido de `ComparacionSinAcentos`.
  - En «Pendientes conocidos», sumar este cambio a la nota de que el contrato va adelante del visor.

- [ ] **Step 4: La copia de ms-comercial.** Pedirle a la sesión `ec-filtro-socio` (`local_4e034fe8-833e-48d9-8bc5-46ee33767967`), que ya edita el contrato en su rama, que aplique también los bloques 1 y 2 de este handoff. Así no hay dos ramas tocando el mismo archivo.

- [ ] **Step 5: Las compuertas y el commit** — `docs(contrato): buscar productos por nombre`. Van el contrato, `ContratoDeRespuestasTest`, `CLAUDE.md`, Postman y el handoff.
