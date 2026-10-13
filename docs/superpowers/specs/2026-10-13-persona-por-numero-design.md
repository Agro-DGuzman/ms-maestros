# La persona es el número · diseño

**Fecha:** 13 de octubre de 2026
**Reemplaza:** la regla I4 (celular en conflicto), la I3 (un socio sin grupo se
ve a sí mismo) y la parte B tal como estaba planteada (habilitación por
contacto). Ver `2026-10-05-ingesta-de-socios-design.md`.

## Por qué

En `AGRO_P6` hay **148 celulares compartidos** entre contactos. El caso típico
es la misma persona cargada varias veces: como contacto de varios socios de su
grupo, o dos veces en el mismo socio con roles distintos. Por ejemplo, `10057`,
el primer socio real que entró por la ingesta, tiene «CONTACTO DE COBRANZA» y
«ADMINISTRADOR», los dos con el `+59177023291`.

Con la regla de hoy, ninguna de esas personas puede entrar a la App, porque el
número queda en conflicto. Y como la identidad es por contacto, tampoco hay
forma de que vea varios socios.

## La regla

Todo esto lo decidió el usuario:

1. **La persona es un número de celular.** Lo que se habilita es el número, no
   un contacto. Todos los contactos *visibles* de SAP con ese número son esa
   persona.
2. **Ve exactamente los socios donde su número es contacto**, si esos socios
   tienen grupo económico. El grupo ya no suma socios: alguien que es contacto
   de un solo socio del grupo Monasterio ve ese socio y ningún otro.
3. **El acceso sigue a SAP.** Si SAP carga el número en otro socio, la persona
   lo ve sin pasar por el back-office; si SAP lo da de baja, deja de verlo.
   Habilitar el número es la única decisión de Agropartners.
4. **Un socio sin grupo económico no aparece en la App para nadie.** No suma
   socios y no bloquea: se ignora. Esto alinea ms-maestros con el texto
   original de D12.
5. **Un número entra** si está habilitado y sus contactos visibles, sin contar
   los de socios sin grupo, dan **al menos un socio, y todos de un mismo grupo
   económico**. Si dan socios de dos grupos o más, **no entra**. Si solo es
   contacto de socios sin grupo, tampoco: no tiene nada que ver.
6. **La App muestra el nombre del grupo económico** como nombre de la persona.

Ejemplo: Ana, `+59177023291`, es contacto dos veces en `10057` (grupo
Monasterio) y una vez en `30001`, que no tiene grupo. Con el número habilitado,
entra y ve `10057`; `30001` no aparece. Si mañana SAP la carga en `20001`, del
grupo Vargas, Ana tiene contactos en dos grupos y deja de poder entrar hasta que
SAP lo corrija.

## Lo que no cambia

- **Visible** sigue siendo lo mismo: socio activo y sin baja; contacto activo,
  sin baja y de un socio visible.
- **Todo fallo de ingreso sigue respondiendo `CODIGO_INVALIDO`**, y `POST
  /auth/otp` sigue respondiendo lo mismo, y en el mismo tiempo, para cualquier
  número. Un número no habilitado, uno en varios grupos y uno que no es de nadie
  son indistinguibles. El envío sigue encolado.
- **La ingesta no cambia**, y nunca escribe nada sobre habilitación.
- **El alcance sigue siendo el único control de autorización**: fuera de alcance
  es 403 `ACCESO_DENEGADO`, verificado antes que la existencia.
- **El contrato de la App no cambia de forma.** `/mi-cuenta` sigue devolviendo
  `nombre`, `iniciales`, `celular` y `grupoEconomico { id, nombre, socios[] }`.

## Qué cambia en cada pieza

**Identidad de la persona.** `IdDePersona` se deriva del número: el usuario de
Keycloak, el `preferred_username` del token, la credencial de la bóveda, las
sesiones y los asientos de la bitácora pasan a usar ese id. Si alguien cambia de
celular en SAP, para el sistema es otra persona, y hay que habilitar el número
nuevo.

**Habilitación.** *Habilitado* es tener credencial, lo mismo que hoy crea «Dar
acceso»: un usuario activo en Keycloak y su contraseña en la bóveda, ahora con
el id del número. No hay otra marca. Habilitar y deshabilitar actúan sobre un
número, desde las dos puertas: el back-office y los comandos
`identidad:habilitar` / `identidad:deshabilitar`, que pasan a recibir el
celular. Habilitar exige que el número *entre* según la regla 5
en ese momento; si no, se rechaza con un error que dice por qué (varios grupos,
o solo socios sin grupo, o sin contactos visibles). Si el número deja de entrar
después, por un cambio en SAP, la habilitación se conserva: lo que se corta es
el ingreso.

**Ingreso y sesión.** El desafío y el login resuelven el número con la regla 5.
La renovación de sesión se corta cuando el número deja de entrar, igual que hoy
con una baja.

**Alcance.** `ResolutorPorGrupo` se reemplaza: una persona alcanza un socio si
el socio es visible, tiene grupo y su número es contacto visible de ese socio.
Como la regla 5 garantiza un solo grupo, eso es también «su grupo».

**`/mi-cuenta` y `/socios`.**
- `nombre` es el nombre del grupo, e `iniciales` sale de ese nombre.
- `celular` es el número.
- `grupoEconomico.socios` y `/socios` son los socios de la regla 2, con su
  `cantidadPropiedades` como hoy.

**Back-office.** La lista sigue siendo por contacto, pero el acceso es del
número:
- «Dar acceso» y «Quitar acceso» en cualquier fila actúan sobre su número, y
  todas las filas con ese número muestran el mismo estado.
- Cada fila indica con cuántos otros contactos comparte el número.
- En rojo, cuando corresponde: «Contacto en socios de varios grupos: no puede
  entrar» y «Solo en socios sin grupo: no puede entrar».
- Desaparecen «Celular en conflicto» y los textos «en SAP» de la habilitación
  (el filtro y la columna).
- «Dar acceso» no se ofrece en una fila cuyo número no entra.
- El historial se ve por número.

**Conciliación.** `identidad:conciliar` compara números con credencial en la
bóveda contra usuarios activos en Keycloak, en las dos direcciones. Avisa
además, sin contarlo como error de credenciales, de los números habilitados que
hoy no entran: varios grupos, solo sin grupo o sin contactos visibles.

## Datos existentes

En Azure hay tres personas con acceso: `p-8f2b1c40` (`70741828`), `p-b2c3d401`
(`71112233`) y `p-c3d4e501` (`72112233`). Son los números de prueba de la App.
Después del cambio:
- tienen que quedar habilitados **sus números**;
- no puede quedar ninguna credencial ni ningún usuario activo con id por
  contacto;
- `identidad:conciliar` tiene que dar cero discrepancias.

Cada uno va a pasar a ver un solo socio, porque cada uno es contacto de uno
solo. El equipo de la App lo tiene que saber.

La columna `habilitada_el` deja de usarse: la habilitación vive en Identidad.

## Fuera de alcance

- Que una persona con socios de dos grupos pueda entrar. Decidido: no entra.
- Revocar sola la credencial de un número que deja de entrar. La conciliación
  lo muestra.
- El crédito y las facturas en ms-comercial. Su visibilidad sigue a ms-maestros
  porque consulta `/socios` con el token de la persona; si el crédito sigue
  siendo por grupo, se decide allá.
- Un nombre de persona propio. La App muestra el del grupo.

## Avisos a otros equipos

- **La otra cuenta (parte B):** frenar; esto la reemplaza.
- **Equipo de la App:**
  - `nombre` pasa a ser el del grupo;
  - cada persona ve solo los socios donde es contacto;
  - los tres números de prueba van a ver un socio cada uno;
  - hay que volver a iniciar sesión después del despliegue, porque cambia el
    usuario.
- **ms-comercial:** cambia el formato del `preferred_username`. Si guarda ids
  de persona (por ejemplo, en reportes de campo), los nuevos no coinciden con
  los viejos.
- **Documento de decisiones del 5/10:** la visibilidad deja de ser por grupo;
  D12 queda literal.

## Decisiones del implementador (marcadas como tales)

- **El formato del id por número.** Mi propuesta: `n-591XXXXXXXX`, el E.164 sin
  el `+`, que Keycloak acepta como usuario. Tiene que ser una función pura del
  número.
- **Cómo se migran las tres personas de Azure.** Recordar que en Azure solo se
  puede ejecutar algo dentro del contenedor desde la consola del portal; el CLI
  de Azure no está instalado en la máquina del usuario.
- **Qué hacer con `ContactoRepository::porCelular()`** y con la lectura de
  `habilitada_el` que hoy hacen el buscador y la conciliación.
- **Un socio cuyo código de grupo no tiene fila en `grupos`** (pasa en la
  semilla con `GRP-099`) cuenta como de ese grupo: la identidad es el código.
  El nombre sale vacío, como hoy.
- **Los asientos viejos de la bitácora**, con ids por contacto, se conservan
  tal cual (es append-only). El historial por número muestra desde el cambio.
- **Actualizar el realm de desarrollo** (`docker/keycloak/agropartners-realm.json`
  trae el usuario `p-8f2b1c40`) **y la batería de integración**, que depende de
  él.
- **Actualizar `CLAUDE.md`**: la regla de *visible*, el conflicto de celular,
  el socio sin grupo, «el contacto de SAP es la persona `p-{CntctCode}`» y el
  pendiente de la parte B.

## Cómo se sabe que está bien

- **Ana** (dos contactos en `10057` y uno en un socio sin grupo, mismo número)
  entra, ve solo `10057`, y `/mi-cuenta` muestra el nombre del grupo Monasterio.
- **El mismo número cargado además en un socio de otro grupo** no entra: el
  login responde `CODIGO_INVALIDO`, la sesión abierta no se renueva y el
  back-office lo marca.
- **Mónica** (`70741828`, contacto solo de `C-004871`) ve únicamente `C-004871`.
  Pedir `C-004872` da 403.
- **Dar acceso en una fila habilita el número**, y todas las filas con ese
  número muestran «con acceso». Quitarlo en otra fila lo quita para todas y
  cierra las sesiones.
- **Un número que no está habilitado**, uno que no entra y uno que no es de
  nadie responden igual en `/auth/otp` y en el login.
- **Las consultas nuevas se verifican contra SQL Server** antes del despliegue:
  la batería corre sobre SQLite.
