# Buscar productos por nombre

Fecha: 24/11/2026. Es un cambio a `agropartners-api-v1.yaml` para la operación de ms-maestros
`GET /productos`. La cuenta del visor sigue vencida, así que va con la misma excepción que NP18 y que el
filtro del estado de cuenta: los bloques se aplican a mano en las copias de ms-maestros y ms-comercial, y
se cargan en el visor cuando vuelva a haber cuenta.

## Qué se decidió

- **El parámetro:** `GET /productos` acepta el parámetro compartido `buscar`, el mismo que ya usa
  `GET /campanas/{campanaId}/compras`. Su descripción deja de decir que solo lo acepta esa operación.
- **Contra qué compara:** solo contra el **nombre** del producto, que es el de la tarjeta. Ni el
  `itemCode`, ni la descripción, ni la categoría, que ya tiene su propio filtro.
- **Cómo compara:**
  - parte el texto en palabras por los espacios;
  - un producto aparece si su nombre contiene **todas** las palabras: «alvo 5» encuentra «ALVO - 5 L» y
    no «ALVO - 1 L»;
  - no distingue mayúsculas ni acentos, igual que el buscador del back-office;
  - `%`, `_` y `[` cuentan como texto, no como comodines.
- **Cómo se combina:**
  - se suma a `categoria` y a la paginación;
  - sin resultados responde 200 con `items` vacío, como una categoría vacía;
  - vacío o con solo espacios es lo mismo que no mandarlo;
  - más de 80 caracteres responde 400 `PARAMETRO_INVALIDO` sobre el campo `buscar`.
- **No cambia:**
  - el orden: por nombre y después por `itemCode`;
  - `GET /categorias`, que sigue mostrando las categorías con productos visibles, sin mirar la búsqueda.

## Vocabulario

- **Producto:** un artículo de SAP. Cada presentación es un producto aparte, con su propia tarjeta.
- **Nombre del producto:** el que trae SAP y pinta la tarjeta. Es lo único contra lo que se busca.

---

Cada bloque va con la sangría que tiene en el archivo: los parámetros cuelgan de `components.parameters`
con 4 espacios, y las rutas de `paths` con 2.

## 1. `components.parameters.Buscar`: reemplazar `description`

```yaml
      description: |-
        Búsqueda por texto. La aceptan solo `GET /campanas/{campanaId}/compras`, que alimenta el buscador
        de la pantalla *Productos de campaña*, y `GET /productos`. Cada operación dice contra qué compara.
```

## 2. `paths./productos.get`

Reemplazar `description` y sumar `Buscar` a `parameters`:

```yaml
      description: |-
        Catálogo vigente: artículos activos, de venta y con lista de precios asignada al socio. El filtro
        `categoria` corresponde a los chips de la pantalla *Productos*.

        `buscar` filtra por el nombre del producto. Parte el texto en palabras por los espacios y devuelve
        los productos cuyo nombre contiene todas, sin distinguir mayúsculas ni acentos: «alvo 5» encuentra
        «ALVO - 5 L» y no «ALVO - 1 L». Se suma a `categoria` y a la paginación, y no cambia el orden.
        Vacío equivale a no mandarlo, y sin resultados la lista viene vacía, no es un error.
      operationId: listarProductos
      parameters:
      - name: categoria
        in: query
        description: Omitir equivale a «Todos».
        schema:
          $ref: '#/components/schemas/CodigoCategoria'
      - $ref: '#/components/parameters/Buscar'
      - $ref: '#/components/parameters/Pagina'
      - $ref: '#/components/parameters/TamanoPagina'
```

En `responses.'400'.content.application/json.examples`, sumar uno después de `parametroInvalido`:

```yaml
                buscarDemasiadoLargo:
                  summary: '`buscar` con más de 80 caracteres'
                  value:
                    data: null
                    success: false
                    error:
                      code:
                      - PARAMETRO_INVALIDO
                      description: Parámetros mal formados.
                      structuredMessage:
                      - campo: buscar
                        codigo: PARAMETRO_INVALIDO
                        mensaje: Valor inválido.
                      type: VALIDATION
```

---

## Qué hace cada repo

- **ms-maestros:** implementa la búsqueda y aplica los bloques a su copia, según el plan
  `docs/superpowers/plans/2026-11-24-buscar-productos.md`.
- **ms-comercial:** aplica los mismos bloques a su copia, que su prueba del contrato lee. No implementa
  nada: la operación es de ms-maestros.
- **El visor:** cuando vuelva a haber cuenta, se cargan junto con los de NP18 y los del estado de cuenta.
