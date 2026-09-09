<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;

/**
 * Caso de uso de lectura para `/panel/bitacora` (tarea 63): listado paginado
 * de `plt_bitacoras`, del más reciente al más viejo, con filtros y las
 * fechas ya convertidas a la zona de quien mira — la vista no calcula
 * zonas, solo pinta el DTO {@see FilaBitacora}.
 *
 * Vive en `Seguridad` porque la PANTALLA es de Seguridad (permiso
 * `seguridad.bitacora.ver`); `plt_bitacoras` es de plataforma
 * (`Compartido`), no de este módulo — mismo criterio de lectura entre
 * módulos que `UsuariosController::personasDisponibles()` (consulta directa,
 * nunca una relación Eloquent cruzando módulos, ADR 0003 regla 3). Puede
 * importar `SecUser` porque es del propio módulo — resuelve el actor acá
 * mismo, en el mismo lote, para no golpear `sec_user` fila por fila.
 *
 * Los nombres legibles de tabla/acción salen de `lang/es/seguridad.php`
 * (`bitacora.entidades.*`/`bitacora.acciones.*`), nunca importando el modelo
 * Eloquent de la entidad auditada — así una entidad de otro módulo no
 * necesita que este caso de uso la conozca.
 */
final class ListarBitacora
{
    /** @return LengthAwarePaginator<int, FilaBitacora> */
    public function ejecutar(
        string $zonaQueVe,
        ?int $usuarioId = null,
        ?string $tabla = null,
        ?AccionBitacora $accion = null,
        ?string $desde = null,
        ?string $hasta = null,
        ?int $registroId = null,
        int $porPagina = 20,
    ): LengthAwarePaginator {
        $paginadorBitacoras = $this->consulta($zonaQueVe, $usuarioId, $tabla, $accion, $desde, $hasta, $registroId)
            ->orderByDesc('created_at')
            ->paginate($porPagina)
            ->withQueryString();

        $actores = $this->actoresPorId($paginadorBitacoras->getCollection()->pluck('user_id')->filter()->unique()->all());

        $filas = $paginadorBitacoras->getCollection()
            ->map(fn (Bitacora $fila): FilaBitacora => $this->aFila($fila, $zonaQueVe, $actores));

        // No se reusa el paginador de `Bitacora` con `setCollection()`: sus
        // genéricos quedan tipados al modelo original y la colección de DTOs
        // no calza (invariancia de `Collection<TValue>`). Se arma uno nuevo
        // con los mismos metadatos (total/página/ruta) y se le pasa la
        // colección ya mapeada; `withQueryString()` lee la request actual,
        // no el paginador viejo, así que conserva los filtros igual.
        return (new LengthAwarePaginator(
            $filas,
            $paginadorBitacoras->total(),
            $paginadorBitacoras->perPage(),
            $paginadorBitacoras->currentPage(),
            ['path' => $paginadorBitacoras->path()],
        ))->withQueryString();
    }

    /** @return Builder<Bitacora> */
    private function consulta(
        string $zonaQueVe,
        ?int $usuarioId,
        ?string $tabla,
        ?AccionBitacora $accion,
        ?string $desde,
        ?string $hasta,
        ?int $registroId,
    ): Builder {
        // "interpretado en la zona del que mira": el usuario filtra
        // "04/09/2026" pensando en SU día calendario, no en UTC — se
        // convierte el borde del día en su zona a UTC antes de comparar
        // contra `created_at` (siempre UTC).
        $desdeUtc = $desde !== null ? Carbon::parse($desde, $zonaQueVe)->startOfDay()->utc() : null;
        $hastaUtc = $hasta !== null ? Carbon::parse($hasta, $zonaQueVe)->endOfDay()->utc() : null;

        return Bitacora::query()
            ->when($usuarioId !== null, fn (Builder $consulta) => $consulta->where('user_id', $usuarioId))
            ->when($tabla !== null, fn (Builder $consulta) => $consulta->where('tabla', $tabla))
            ->when($accion !== null, fn (Builder $consulta) => $consulta->where('accion', $accion))
            ->when($desdeUtc !== null, fn (Builder $consulta) => $consulta->where('created_at', '>=', $desdeUtc))
            ->when($hastaUtc !== null, fn (Builder $consulta) => $consulta->where('created_at', '<=', $hastaUtc))
            ->when($registroId !== null, fn (Builder $consulta) => $consulta->where('registro_id', $registroId));
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, SecUser>
     */
    private function actoresPorId(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return SecUser::query()->whereIn('id', $ids)->get(['id', 'name', 'username'])->keyBy('id')->all();
    }

    /** @param  array<int, SecUser>  $actores */
    private function aFila(Bitacora $fila, string $zonaQueVe, array $actores): FilaBitacora
    {
        $instante = $fila->created_at->copy()->setTimezone($zonaQueVe);
        $actor = $fila->user_id !== null ? ($actores[$fila->user_id] ?? null) : null;

        return new FilaBitacora(
            id: $fila->id,
            instante: $instante,
            offset: $this->offsetLegible($instante),
            zonaRegistrada: ($fila->zona_horaria !== null && $fila->zona_horaria !== $zonaQueVe) ? $fila->zona_horaria : null,
            actorNombre: $actor?->name,
            actorUsername: $actor?->username,
            tabla: $fila->tabla,
            tablaLegible: $this->nombreLegibleTabla($fila->tabla),
            registroId: $fila->registro_id,
            accion: $fila->accion,
            diff: $this->diff($fila),
        );
    }

    /**
     * @return list<array{campo: string, antes: mixed, despues: mixed}>
     */
    private function diff(Bitacora $fila): array
    {
        $antes = $fila->antes ?? [];
        $despues = $fila->despues ?? [];
        $campos = array_unique([...array_keys($antes), ...array_keys($despues)]);

        return array_map(fn (string $campo): array => [
            'campo' => $campo,
            'antes' => $antes[$campo] ?? null,
            'despues' => $despues[$campo] ?? null,
        ], $campos);
    }

    private function nombreLegibleTabla(string $tabla): string
    {
        $clave = "seguridad.bitacora.entidades.{$tabla}";

        return Lang::has($clave) ? __($clave) : $tabla;
    }

    /**
     * Ej. "UTC−4" (América/La_Paz) o "UTC+2" (Europe/Madrid en verano) —
     * `getOffset()` ya resuelve horario de verano/invierno vía la base IANA
     * de PHP, nunca un offset fijo por zona.
     */
    private function offsetLegible(Carbon $instante): string
    {
        $horas = $instante->getOffset() / 3600;
        $signo = $horas < 0 ? '−' : '+';
        $horasAbs = abs($horas);
        $texto = $horasAbs === floor($horasAbs)
            ? (string) (int) $horasAbs
            : rtrim(rtrim(number_format($horasAbs, 2), '0'), '.');

        return "UTC{$signo}{$texto}";
    }
}
