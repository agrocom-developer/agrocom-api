# Reglas de rama y environment (pasos en la web)

Estos ajustes requieren permiso de administrador del repositorio, que la automatización no tiene. El dueño los activa una vez en `https://github.com/agrocom-developer/agrocom-api`.

## 1. Ruleset para `develop` y `master`

1. **Settings** > **Rules** > **Rulesets** > **New ruleset** > **New branch ruleset**.
2. **Ruleset name**: `ramas-integracion`. **Enforcement status**: `Active`.
3. **Bypass list**: vacía. (El auto-merge usa `GITHUB_TOKEN` por `gh pr merge` y respeta las reglas porque el check ya está en verde.)
4. **Target branches** > **Add target** > **Include by pattern**: `develop`, y de nuevo `master`.
5. En **Branch rules** marca:
   - **Restrict deletions**.
   - **Block force pushes**.
   - **Require a pull request before merging**, con **Required approvals** en `0`. No se exigen reviews porque el auto-merge se ejecuta con `GITHUB_TOKEN` y no puede aprobar ni ser aprobado por una persona.
   - **Require status checks to pass**: **Add checks** > escribe `laravel-tests` y selecciónalo. Deja **Require branches to be up to date before merging** desactivado (cada PR se integra por squash y el auto-merge ya espera el check del SHA de cabecera).
6. **Create**.

Efecto: nadie, ni el dueño por descuido, puede borrar `develop`/`master`, reescribir su historia ni integrar un PR con `laravel-tests` en rojo. El check `seguridad` no se marca como requerido: un advisory nuevo sobre una dependencia ajena al PR no debe bloquear su integración, pero queda visible.

## 2. Code owners (opcional, cuando haya otra persona escribiendo)

Con un solo colaborador no activar **Require review from Code Owners**: bloquearía el auto-merge. `.github/CODEOWNERS` ya declara las rutas sensibles; si en el futuro se suma un segundo colaborador con push, se activa esa casilla dentro del ruleset del punto 1 y se mantiene `Required approvals` en `0` solo si el auto-merge sigue siendo la vía de integración de los PR de automatización.

## 3. Environment `produccion-beta` con aprobación

1. **Settings** > **Environments** > `produccion-beta` (créalo si no existe).
2. **Required reviewers**: agrega a `agrocom-developer`. El despliegue queda en espera hasta que lo apruebes en la pestaña **Actions** del run.
3. **Deployment branches and tags** > **Selected branches and tags** > **Add deployment branch or tag rule** > tipo **Tag**, patrón `v*.*.*`.
4. **Secrets**: cualquier secreto de despliegue va aquí y no en secretos de repositorio.

## 4. Ajustes de Actions

**Settings** > **Actions** > **General**:

- **Fork pull request workflows from outside collaborators**: `Require approval for all outside collaborators`.
- **Workflow permissions**: `Read repository contents and packages permissions`. Marca **Allow GitHub Actions to create and approve pull requests** solo si Dependabot no puede abrir PR sin ella.
- **Actions permissions**: `Allow ... select actions` con `actions/*` y `shivammathur/setup-php` permitidos.

## 5. Escaneo y alertas

**Settings** > **Advanced Security** (o **Code security**): activar **Dependabot alerts**, **Dependabot security updates**, **Secret scanning** y **Push protection**. Activar **Private vulnerability reporting** para que funcione el reporte descrito en `SECURITY.md`.

## Verificación

- Intentar `git push --force origin develop` debe ser rechazado.
- Un PR con `laravel-tests` en rojo no se integra.
- Un push de tag `vX.Y.Z` deja el run de despliegue en estado **Waiting** hasta la aprobación.
