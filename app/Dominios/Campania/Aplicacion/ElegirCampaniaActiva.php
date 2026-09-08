<?php

namespace App\Dominios\Campania\Aplicacion;

use App\Dominios\Campania\Dominio\EstadoCampania;
use App\Dominios\Campania\Dominio\Excepciones\CampaniaNoEncontrada;
use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Session;

/**
 * Único punto que escribe `session('cpn_campania_activa_id')` (ADR 0015
 * punto 1, tarea 69): espejo exacto de
 * `Seguridad\Aplicacion\ElegirRolActivo` — mismo criterio de "una sola
 * implementación, ningún llamador revalida por su cuenta". A diferencia del
 * rol activo, la campaña activa no está "asignada" a nadie: cualquier
 * usuario de panel puede elegir cualquier campaña no borrada (invariante:
 * "la campaña activa filtra, no autoriza", nunca toca permisos).
 *
 * Dos llamadores comparten esta única implementación:
 * - El middleware `ResolverCampaniaActiva`, para el default automático
 *   ({@see resolverPorDefecto()}) cuando la sesión llega sin campaña activa
 *   resuelta.
 * - El endpoint de cambio de campaña activa sin volver a loguearse (el
 *   usuario elige explícitamente, desde el listado de campañas).
 */
final class ElegirCampaniaActiva
{
    private const CLAVE_SESION = 'cpn_campania_activa_id';

    /**
     * @throws CampaniaNoEncontrada si `$idCampania` no existe o está borrada.
     */
    public function ejecutar(int $idCampania): Campania
    {
        $campania = Campania::query()->find($idCampania);

        if ($campania === null) {
            throw CampaniaNoEncontrada::paraId($idCampania);
        }

        Session::put(self::CLAVE_SESION, $campania->id);

        return $campania;
    }

    /**
     * Default al iniciar sesión / cuando la sesión llega sin campaña activa
     * resoluble (prompt de la tarea 69): la campaña `abierta` cuyo rango
     * contiene hoy; si no hay ninguna, la última `abierta` por
     * `fecha_inicio`; si tampoco, `null` — y el chip del header no se pinta.
     * Nunca cae a `planificada`/`cerrada`: activar por defecto una campaña
     * que todavía no admite imputaciones (o que ya no admite ninguna) no
     * tiene sentido de negocio.
     *
     * Fija la sesión cuando encuentra una, igual que `ejecutar()` — pero sin
     * revalidar contra nada (no hay "asignación" que verificar, a diferencia
     * del rol activo).
     */
    public function resolverPorDefecto(): ?Campania
    {
        $hoy = CarbonImmutable::today()->toDateString();

        $campania = Campania::query()
            ->where('estado', EstadoCampania::Abierta->value)
            ->where('fecha_inicio', '<=', $hoy)
            ->where('fecha_fin', '>=', $hoy)
            ->first()
            ?? Campania::query()
                ->where('estado', EstadoCampania::Abierta->value)
                ->orderByDesc('fecha_inicio')
                ->first();

        if ($campania !== null) {
            Session::put(self::CLAVE_SESION, $campania->id);
        }

        return $campania;
    }
}
