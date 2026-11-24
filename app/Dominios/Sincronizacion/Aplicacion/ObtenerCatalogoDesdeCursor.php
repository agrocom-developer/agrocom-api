<?php

namespace App\Dominios\Sincronizacion\Aplicacion;

use App\Dominios\Flota\Contratos\LecturaEquipos;
use App\Dominios\Operaciones\Contratos\LecturaLimitesDeVuelo;
use App\Dominios\Personal\Contratos\LecturaPersonas;
use App\Dominios\Sincronizacion\Dominio\CursorCatalogo;
use App\Dominios\Sincronizacion\Dominio\PosicionCursor;
use App\Dominios\Solicitudes\Contratos\LecturaServiciosAsignados;
use Illuminate\Support\Carbon;

/**
 * Caso de uso de `GET /api/sync/catalogo` (espec §2.1, punto 6; ADR 0037).
 * Combina los contratos de lectura de los módulos dueños (nunca sus modelos
 * Eloquent, ADR 0003 regla 2) y arma la respuesta con el cursor de
 * continuación:
 *
 *  - `personas`: las personas operativas (el `piloto_id` de una sesión es una).
 *  - `servicios_asignados`: las solicitudes aceptadas que el equipo del operario
 *    lleva al lote (reemplazan a las órdenes de aplicación).
 *  - `servicios_retirados`: lo que el dispositivo ya no debe mostrar.
 *  - `limites`: los límites de condiciones de vuelo con los que el servidor
 *    valida el registro `condiciones`.
 *
 * Recetas y productos quedan fuera a propósito: el registro `mezcla` del lote
 * de sync (espec §7, HU-78, tarea 94) no necesita un catálogo previo — el
 * piloto transcribe el nombre del producto en el propio evento, y el servidor
 * lo resuelve contra `mez_productos` al aplicarlo.
 */
final class ObtenerCatalogoDesdeCursor
{
    /**
     * Registros por sección y por pull. Generoso para el volumen esperado de
     * una sola operación; si una sección agota el límite, su posición de
     * cursor avanza hasta la última fila entregada y el siguiente pull con
     * ese mismo cursor trae el resto — no hace falta un flag "hay más".
     */
    private const int LIMITE_POR_SECCION = 200;

    public function __construct(
        private readonly LecturaPersonas $personas,
        private readonly LecturaServiciosAsignados $servicios,
        private readonly LecturaEquipos $equipos,
        private readonly LecturaLimitesDeVuelo $limites,
    ) {}

    /**
     * @param  int|null  $operarioPersonaId  persona dueña del token
     *                                       (`Seguridad\Contratos\IdentidadOperarioToken`).
     * @param  int|null  $operarioUsuarioId  cuenta dueña del token: de ella sale su
     *                                       equipo de flota. `servicios_asignados`
     *                                       solo trae los servicios de los equipos
     *                                       en los que está vigente HOY y
     *                                       `servicios_retirados` los de los que
     *                                       tuvo; sin cuenta, ninguno.
     * @return array<string, mixed>
     */
    public function ejecutar(?string $cursor, ?int $operarioPersonaId, ?int $operarioUsuarioId = null): array
    {
        unset($operarioPersonaId);

        $entrante = CursorCatalogo::desde($cursor);
        $saliente = $entrante;

        $posicionPersonas = $entrante->posicion(CursorCatalogo::PERSONAS);
        $personas = $this->personas->listarModificadosDesde(
            $posicionPersonas?->actualizadoEn,
            $posicionPersonas?->id,
            self::LIMITE_POR_SECCION,
        );
        if ($personas !== []) {
            $ultima = $personas[array_key_last($personas)];
            $saliente = $saliente->conPosicion(CursorCatalogo::PERSONAS, new PosicionCursor($ultima->updatedAt, $ultima->id));
        }

        $equiposVigentes = $this->equiposDeLaCuenta($operarioUsuarioId, true);

        $posicionServicios = $entrante->posicion(CursorCatalogo::SERVICIOS);
        $servicios = $this->servicios->asignadosDesde(
            $posicionServicios?->actualizadoEn,
            $posicionServicios?->id,
            self::LIMITE_POR_SECCION,
            $equiposVigentes,
        );
        if ($servicios !== []) {
            $ultimo = $servicios[array_key_last($servicios)];
            $saliente = $saliente->conPosicion(CursorCatalogo::SERVICIOS, new PosicionCursor($ultimo->updatedAt, $ultimo->id));
        }

        // Retirados. Un dispositivo sin ninguna posición no tiene nada que
        // retirar: no recibe retirados y su posición arranca en «ahora», así el
        // pull siguiente trae solo lo que se retire desde este momento. Un
        // cursor que ya tiene otras secciones pero no esta la trae desde el
        // principio, paginada: ese dispositivo puede tener servicios viejos que
        // limpiar.
        $serviciosRetirados = [];

        if ($entrante->esVacio()) {
            $saliente = $saliente->conPosicion(CursorCatalogo::SERVICIOS_RETIRADOS, new PosicionCursor(Carbon::now()->toIso8601String(), 0));
        } else {
            $posicionRetirados = $entrante->posicion(CursorCatalogo::SERVICIOS_RETIRADOS);
            $serviciosRetirados = $this->servicios->retiradosDesde(
                $posicionRetirados?->actualizadoEn,
                $posicionRetirados?->id,
                self::LIMITE_POR_SECCION,
                $this->equiposDeLaCuenta($operarioUsuarioId, false),
            );
            if ($serviciosRetirados !== []) {
                $ultimo = $serviciosRetirados[array_key_last($serviciosRetirados)];
                $saliente = $saliente->conPosicion(CursorCatalogo::SERVICIOS_RETIRADOS, new PosicionCursor($ultimo->updatedAt, $ultimo->id));
            }
        }

        return [
            'personas' => array_map(static fn ($persona) => $persona->toArray(), $personas),
            'servicios_asignados' => array_map(static fn ($servicio) => $servicio->toArray(), $servicios),
            'servicios_retirados' => array_map(static fn ($servicio) => $servicio->toArray(), $serviciosRetirados),
            'limites' => $this->limites->efectivos(),
            'cursor' => $saliente->serializar(),
        ];
    }

    /**
     * Equipos de flota en los que la cuenta es piloto o auxiliar: los vigentes
     * hoy para lo que debe llevar, todos los que tuvo para lo que debe retirar.
     * Una cuenta sin registro de piloto ni de auxiliar no tiene equipo.
     *
     * @return list<int>
     */
    private function equiposDeLaCuenta(?int $usuarioId, bool $soloVigentes): array
    {
        if ($usuarioId === null) {
            return [];
        }

        return $this->equipos->equiposDeCuenta($usuarioId, $soloVigentes);
    }
}
