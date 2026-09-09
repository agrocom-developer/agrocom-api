<?php

namespace App\Dominios\Compartido\Infraestructura\Eloquent;

use App\Dominios\Compartido\Dominio\AccionBitacora;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Observer de plataforma que materializa la bitácora del ADR 0007
 * (invariante 9 de CLAUDE.md): quién, cuándo, qué entidad, qué acción y los
 * valores antes/después de lo que cambió — nunca la fila entera, nunca una
 * columna de credenciales. Se engancha vía {@see RegistraBitacora}, nunca a
 * mano desde un caso de uso (esa era la alternativa que el ADR 0007
 * descartó).
 *
 * No escucha `restored`: ver el docblock de {@see AccionBitacora} — una
 * restauración ya quiere decir `save()` por debajo y la captura
 * {@see self::updated()} sin necesitar un caso aparte.
 */
final class BitacoraObserver
{
    /**
     * Nunca entran a `antes`/`despues`: una bitácora que copia un hash de
     * contraseña o un token convierte la auditoría en un segundo lugar del
     * que robarlo. Nombres GENÉRICOS de columna, válidos en cualquier tabla —
     * para una columna sensible con un nombre de negocio (p. ej. `valor` en
     * `plt_configuraciones`, que en otra tabla podría ser dinero u
     * hectáreas legítimos de auditar) el modelo la declara él mismo, ver
     * {@see self::columnasSensibles()}.
     *
     * @var list<string>
     */
    private const COLUMNAS_SENSIBLES = ['password', 'token', 'remember_token'];

    /**
     * Bookkeeping de plataforma que ya queda registrado por otro lado: el
     * actor y el momento de la mutación son `plt_bitacoras.user_id` y
     * `.created_at` (esta misma fila), y `created_by`/`updated_by` los pone
     * `RegistraAutoria` en la propia fila auditada. Repetirlos dentro de
     * `antes`/`despues` en cada mutación sería ruido, no una columna que
     * "cambió" en un sentido de negocio. `deleted_at` queda fuera de esta
     * lista a propósito: es la única columna de bookkeeping cuyo cambio SÍ
     * es la propia acción a auditar en un borrado o una restauración.
     *
     * @var list<string>
     */
    private const COLUMNAS_DE_PLATAFORMA = ['created_at', 'updated_at', 'created_by', 'updated_by'];

    public function created(Model $modelo): void
    {
        $this->registrar($modelo, AccionBitacora::Creado, null, $this->limpiar($modelo, $modelo->getAttributes()));
    }

    /**
     * OJO con el orden de los filtros: primero se decide si hubo un cambio
     * real (descontando solo el bookkeeping de plataforma) y RECIÉN
     * DESPUÉS se le quitan las columnas sensibles al contenido. Si se
     * filtrara todo junto antes de decidir, una actualización que solo
     * cambia `password` no dejaría fila alguna — perdiendo justo el
     * "quién y cuándo" de un cambio de contraseña, que es exactamente lo
     * que la invariante 9 quiere poder responder incluso cuando el
     * contenido del cambio no puede guardarse.
     */
    public function updated(Model $modelo): void
    {
        $cambiosReales = Arr::except($modelo->getChanges(), self::COLUMNAS_DE_PLATAFORMA);

        if ($cambiosReales === []) {
            return;
        }

        $sensibles = $this->columnasSensibles($modelo);
        $cambios = Arr::except($cambiosReales, $sensibles);
        $antes = Arr::except(Arr::only($modelo->getOriginal(), array_keys($cambiosReales)), $sensibles);

        $this->registrar($modelo, AccionBitacora::Actualizado, $antes, $cambios);
    }

    /**
     * Cubre tanto el borrado lógico normal (`ModeloDominio` bloquea el
     * físico antes de que este evento pueda dispararse por esa vía) como
     * cualquier modelo que use este trait sin `SoftDeletes` — ahí no hay
     * columna `deleted_at` que mostrar, pero la acción sigue siendo real.
     */
    public function deleted(Model $modelo): void
    {
        $columna = method_exists($modelo, 'getDeletedAtColumn') ? $modelo->getDeletedAtColumn() : null;

        $antes = $columna !== null ? [$columna => null] : null;
        $despues = $columna !== null ? [$columna => $modelo->getAttribute($columna)] : null;

        $this->registrar($modelo, AccionBitacora::Eliminado, $antes, $despues);
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @return array<string, mixed>
     */
    private function limpiar(Model $modelo, array $atributos): array
    {
        return Arr::except($atributos, [...$this->columnasSensibles($modelo), ...self::COLUMNAS_DE_PLATAFORMA]);
    }

    /**
     * Columnas genéricas de {@see self::COLUMNAS_SENSIBLES} más las que el
     * propio modelo declara sensibles para SU tabla (método opcional,
     * `method_exists` con el mismo criterio que `getDeletedAtColumn()` en
     * {@see self::deleted()} — la mayoría de los modelos no lo necesitan).
     *
     * @return list<string>
     */
    private function columnasSensibles(Model $modelo): array
    {
        $propias = method_exists($modelo, 'columnasSensiblesBitacora') ? $modelo->columnasSensiblesBitacora() : [];

        return [...self::COLUMNAS_SENSIBLES, ...$propias];
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $despues
     */
    private function registrar(Model $modelo, AccionBitacora $accion, ?array $antes, ?array $despues): void
    {
        $actorId = $this->actorId();

        Bitacora::query()->create([
            'user_id' => $actorId,
            'tabla' => $modelo->getTable(),
            'registro_id' => (int) $modelo->getKey(),
            'accion' => $accion,
            'antes' => $antes === [] ? null : $antes,
            'despues' => $despues === [] ? null : $despues,
            'zona_horaria' => $actorId !== null ? $this->zonaHorariaDeActor($actorId) : null,
        ]);
    }

    /**
     * `Auth::id()` a secas solo mira el guard POR DEFECTO
     * (`config('auth.defaults.guard')`, hoy `interno`) — una mutación hecha
     * por una cuenta de portal autenticada en el guard `cliente` (p. ej. su
     * propia preferencia) quedaba con `user_id` NULL aunque hubiera un actor
     * real. Recorre todos los guards declarados en `config('auth.guards')`
     * (agnóstico de sus nombres: son configuración de la app, no vocabulario
     * de un módulo) y devuelve el primero con sesión viva — en la práctica,
     * a lo sumo uno de `interno`/`cliente` está autenticado en el mismo
     * request, y `sanctum` (API de campo) cubre el resto.
     */
    private function actorId(): ?int
    {
        foreach (array_keys((array) config('auth.guards')) as $guard) {
            $id = Auth::guard($guard)->id();

            if ($id !== null) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * Zona horaria IANA del actor en el momento de la mutación (tarea 63):
     * lectura directa a `sec_user_preferencia` (mismo criterio que
     * `UsuariosController::personasDisponibles()` cruzando a `per_personas`
     * — consulta plana, no relación Eloquent, así `Compartido` no importa
     * ningún modelo de `Seguridad`, ADR 0003 regla 3). `null` si el actor
     * nunca fijó preferencia (seeders, comandos, o un token de dispositivo
     * sin ese dato) — nunca se inventa una zona.
     */
    private function zonaHorariaDeActor(int $actorId): ?string
    {
        return DB::table('sec_user_preferencia')->where('user_id', $actorId)->value('zona_horaria');
    }
}
