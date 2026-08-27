<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Seguridad — menú de navegación del panel (ADR 0002 punto 5; ADR
 * 0004, "Consecuencias": "el panel web renderiza el menú desde
 * `sec_menu`/`sec_permission` filtrado por los permisos del rol activo de
 * la sesión — nunca por la unión de todos los roles del usuario").
 *
 * Estructura jerárquica autorreferenciada (`padre_id`) para submenús
 * colapsables (AdminLTE `collapsible-menu-group`, ver
 * `docs/diseno/sistema_diseno_panel.md` §4.4) — el esquema no limita la
 * profundidad, aunque hoy solo se puebla un nivel.
 *
 * `label` guarda una CLAVE de traducción (ADR 0013), nunca texto plano: la
 * resuelve quien pinta el menú (`frontend`, vía `__($label)`), nunca este
 * módulo. `icono` es el nombre de un ícono Material Symbols (string), nunca
 * un asset embebido (CLAUDE.md invariante 11 — mismo criterio que los
 * colores: se referencia por nombre/token, no por valor).
 *
 * `ruta` (nullable, string): nombre de ruta Laravel (convención de este
 * módulo, ver `panel.rol-activo.actualizar`) o URL literal. Nulo cuando el
 * ítem es solo un agrupador visual sin link propio. No se valida contra
 * `Route::has()` en la base — la ruta puede nombrarse acá antes de que
 * `frontend` la registre, mismo criterio de tolerancia que ya usa
 * `ResolverRolActivo::RUTA_SELECTOR`.
 *
 * `permission_id` (nullable, FK a `sec_permission`, RESTRICT): si es NULL,
 * el ítem es visible para cualquier usuario autenticado del panel (p. ej.
 * "Inicio"); si tiene valor, solo visible cuando el ROL ACTIVO de la sesión
 * (no la unión de roles, CLAUDE.md invariante 10) tiene ese permiso —
 * resuelto por `ObtenerMenuPorRolActivo` vía `SecUser::tienePermisoEnRol()`,
 * nunca acá ni en el modelo. `sec_menu` REFERENCIA a `sec_permission`,
 * jamás al revés (invariante 2 del modelo de seguridad).
 *
 * `padre_id` (nullable, FK autorreferenciada a `sec_menu`, RESTRICT): NULL
 * en los ítems de primer nivel.
 *
 * Soft delete + auditoría igual que el resto de `sec_*` (mismo patrón que
 * `sec_role`/`sec_permission`). Sin columna `state` adicional a propósito:
 * a diferencia de esos catálogos, acá no hay todavía un caso de uso real de
 * "desactivar temporalmente sin borrar" un ítem de menú — ocultarlo es
 * soft-delete y volver a mostrarlo es recrearlo (o restaurar la fila), hasta
 * que aparezca una necesidad concreta de distinguir ambos estados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sec_menu', function (Blueprint $table) {
            $table->id();
            $table->string('label', 150);
            $table->string('icono', 60);
            $table->string('ruta', 150)->nullable();
            $table->foreignId('padre_id')->nullable()->constrained('sec_menu')->restrictOnDelete();
            $table->unsignedInteger('orden')->default(0);
            $table->foreignId('permission_id')->nullable()->constrained('sec_permission')->restrictOnDelete();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('padre_id');
            $table->index('permission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_menu');
    }
};
