<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/portal-contrato-avance etapas=1 -->

# Tarea 68 — cerrar las observaciones de la revisión del portal (PR #106)

## Por qué esta tarea

La revisión línea por línea posterior a la integración del PR #106 (HU-41,
portal del cliente, tarea 55), hecha el 4/9/2026 sobre `develop`, dio
**APROBADO CON OBSERVACIONES**: el scoping por contrato (invariante 5) está
bien resuelto en avance, actas y reportes, y los tests A→B → 404 existen.
Quedaron dos observaciones P2 y una P3 que esta tarea cierra. Es chica, de
una sola etapa. Es crítica porque toca el módulo Portal y su suite de
aislamiento; el PR se abre igual y queda anotado para revisión posterior.

## Observaciones, con evidencia

1. **P2 — `AvancePortalController` importa un caso de uso ajeno.**
   `app/Dominios/Portal/Infraestructura/Http/Controllers/Web/AvancePortalController.php`
   hace `use App\Dominios\Comercial\Aplicacion\ObtenerAvanceComercial` y lo
   inyecta en `index()`. Es la **única** importación cross-módulo de una
   clase `Aplicacion\*` en todo `app/Dominios/`; ADR 0003 dice que entre
   módulos se viaja por `Contratos/` o eventos. `ActasPortalController` y
   `ReportesPortalController` sí lo hacen bien: consumen
   `Operaciones\Contratos\LecturaActaConformada` y `LecturaReporteTecnico`.
   `Comercial\Contratos\` no tiene ninguna interfaz de lectura de avance.
   `tests/Unit/ArquitecturaModulosTest.php` no lo detecta: solo prohíbe
   importar modelos Eloquent ajenos y, puntualmente,
   `Comercial→Operaciones\{Aplicacion,Dominio,Infraestructura}`.

2. **P2 — Cuenta de portal sin `contrato_id`: camino fail-closed sin test.**
   `AutorizacionPortalClienteSesion::contratoId()` devuelve `null` si el
   `SecUsuarioCliente` autenticado tiene `contrato_id` null (columna
   nullable), y los tres controladores hacen `abort_if($contratoId === null,
   404)`. Correcto, pero ningún test de `tests/Feature/Portal/` ni de
   `tests/Feature/Seguridad/LoginPortalTest.php` lo ejercita.

3. **P3 — Cobertura de guard asimétrica.** `PortalClienteTest.php` solo
   prueba "guest → redirect al login del portal" y "sesión `interno` no
   entra" para `portal.avance.index`. No están replicados para
   `portal.actas.index`, `portal.actas.pdf`, `portal.reportes.index`,
   `portal.reportes.pdf` ni `portal.preferencias.tema`. El middleware
   `auth:cliente` es común a todo el grupo (`routes/web.php`, bloque
   `/portal/*`), así que el riesgo es bajo, pero CLAUDE.md pide un test por
   endpoint.

## Qué hacer

Cargá las skills `dominio-backend`, `seguridad-roles` y `verificacion`.

1. **Contrato de lectura de avance.** Interfaz
   `App\Dominios\Comercial\Contratos\LecturaAvanceComercial` con un único
   método `porContrato(int $contratoId): ?array` (o el DTO que ya devuelve
   `ObtenerAvanceComercial->ejecutar()` para un contrato; mirá qué forma
   tiene `$avance[0]` y devolvé eso mismo, tipado). Implementación
   `Comercial\Infraestructura\LecturaAvanceComercialEloquent` (o el nombre
   que siga el patrón de `LecturaActaConformadaEloquent`) que delega en
   `ObtenerAvanceComercial` sin duplicar lógica. Binding en
   `ComercialServiceProvider`. `AvancePortalController` pasa a depender de
   la interfaz; su docblock se ajusta.
2. **Regla de arquitectura general.** En `ArquitecturaModulosTest.php`,
   una regla nueva: ningún archivo de `app/Dominios/<A>/**` importa
   `App\Dominios\<B>\Aplicacion\*` con `B ≠ A`. Verificá primero con grep
   que el único infractor es el de arriba; si aparece otro, listalo en
   `runs/68.md` y arreglalo en la misma tarea solo si es un cambio de la
   misma naturaleza (interfaz + adaptador); si no, dejalo documentado y
   excluido de la regla con comentario que diga por qué.
3. **Test de cuenta sin contrato.** En `PortalClienteTest.php`:
   `it('una cuenta de portal sin contrato asociado recibe 404 en avance,
   actas y reportes', ...)`. Tres asserts, un solo usuario `cliente` con
   `contrato_id = null`.
4. **Tests de guard por endpoint.** Un `dataset` con las cinco rutas
   restantes del bloque `/portal/*` (`actas.index`, `actas.pdf`,
   `reportes.index`, `reportes.pdf`, `preferencias.tema`) y dos `it`
   parametrizados: guest → redirect a `portal.login`; sesión `interno` →
   no entra (mismo código que ya espera el test existente de
   `avance.index`, no cambies ese criterio).
5. **Espec.** En `docs/especificacion/especificacion_funcional_tecnica.md`,
   la línea que lista `GET /api/portal/reportes` como superficie JSON pasa
   a describir `/portal/*` con sesión Blade y guard `cliente` (ADR 0002,
   punto 6). Una línea, sin reescribir la sección.

## Qué NO hacer

- No toques la lógica de `ObtenerAvanceComercial` ni su firma: el
  adaptador lo envuelve, no lo reemplaza. Sigue acumulando con
  `BigDecimal`, nunca float (invariante 6).
- No cambies el criterio 404 (nunca 403) del portal ni el `abort_if` de
  los controladores.
- No agregues modelos ni tablas: el módulo Portal sigue sin escribir nada.
- No regeneres capturas de Playwright: nada visual cambia; si alguna
  captura falla, es una regresión y se investiga.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- `grep -rn "Dominios\\\\Comercial\\\\Aplicacion" app/Dominios/Portal` no
  devuelve nada, y la regla nueva de `ArquitecturaModulosTest` falla si se
  vuelve a introducir (probalo a mano revirtiendo el import antes de
  commitear la regla, y anotá en `runs/68.md` que lo hiciste).
- Test verde: cliente sin contrato → 404 en las tres pantallas.
- Test verde: guest e `interno` bloqueados en los seis endpoints del
  portal (los cinco nuevos + `avance.index` que ya estaba).
- Los tests cruzados A→B existentes siguen en verde sin modificarlos.

## Puede tocar

`app/Dominios/Comercial/Contratos/**`, `app/Dominios/Comercial/Infraestructura/**`
(solo el adaptador y el provider), `app/Dominios/Portal/**`,
`tests/Unit/ArquitecturaModulosTest.php`, `tests/Feature/Portal/**`,
`docs/especificacion/especificacion_funcional_tecnica.md` (una línea).

## Cierre obligatorio

`runs/68.estado`, `runs/68.md`, y al `OK` `runs/68.pr.md`. Commits
agrupados por función, en español, imperativo, sin `Co-Authored-By`.
