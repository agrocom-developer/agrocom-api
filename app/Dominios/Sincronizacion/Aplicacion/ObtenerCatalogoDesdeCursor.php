<?php

namespace App\Dominios\Sincronizacion\Aplicacion;

use App\Dominios\Comercial\Contratos\LecturaLotes;
use App\Dominios\Operaciones\Contratos\LecturaOrdenesVigentes;
use App\Dominios\Operaciones\Contratos\LecturaRetirosCatalogo;
use App\Dominios\Operaciones\Contratos\LecturaTrabajosAsignados;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use App\Dominios\Personal\Contratos\LecturaPersonas;
use App\Dominios\Sincronizacion\Dominio\CursorCatalogo;
use App\Dominios\Sincronizacion\Dominio\PosicionCursor;
use Illuminate\Support\Carbon;

/**
 * Caso de uso de `GET /api/sync/catalogo` (espec §2.1, punto 6; TE-06
 * parcial — ver `runs/08-diseno.md`). Combina los cuatro contratos de lectura
 * de los módulos dueños (nunca sus modelos Eloquent, ADR 0003 regla 2) y
 * arma la respuesta con el cursor de continuación.
 *
 * Recetas y productos quedan fuera a propósito: el registro `mezcla` del
 * lote de sync (espec §7, HU-78, tarea 94) no necesita un catálogo previo —
 * el piloto transcribe el nombre del producto en el propio evento, y el
 * servidor lo resuelve contra `mez_productos` al aplicarlo (ver
 * `Mezclas\Infraestructura\EscrituraMezclasEloquent`). Si una futura HU
 * necesitara que el piloto ELIJA de un catálogo cerrado en vez de
 * transcribir, ahí sí haría falta sumar `productos` a este pull (recorte de
 * alcance original de la tarea 08 en `docs/gestion/cola_tareas.md`).
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
        private readonly LecturaOrdenesVigentes $ordenes,
        private readonly LecturaLotes $lotes,
        private readonly LecturaPersonas $personas,
        private readonly LecturaTrabajosAsignados $trabajos,
        private readonly LecturaEquipoTrabajo $equipos,
        private readonly LecturaRetirosCatalogo $retiros,
    ) {}

    /**
     * @param  int|null  $operarioPersonaId  persona dueña del token
     *                                       (`Seguridad\Contratos\IdentidadOperarioToken`).
     *                                       `trabajos` solo trae los de los
     *                                       equipos en los que está vigente
     *                                       HOY, y `trabajos_retirados` solo
     *                                       los que tuvo; sin persona,
     *                                       ninguno.
     * @return array<string, mixed>
     */
    public function ejecutar(?string $cursor, ?int $operarioPersonaId): array
    {
        $entrante = CursorCatalogo::desde($cursor);
        $saliente = $entrante;

        $posicionOrdenes = $entrante->posicion(CursorCatalogo::ORDENES);
        $ordenes = $this->ordenes->listarModificadosDesde(
            $posicionOrdenes?->actualizadoEn,
            $posicionOrdenes?->id,
            self::LIMITE_POR_SECCION,
        );
        if ($ordenes !== []) {
            $ultima = $ordenes[array_key_last($ordenes)];
            $saliente = $saliente->conPosicion(CursorCatalogo::ORDENES, new PosicionCursor($ultima->updatedAt, $ultima->id));
        }

        $posicionLotes = $entrante->posicion(CursorCatalogo::LOTES);
        $lotes = $this->lotes->listarModificadosDesde(
            $posicionLotes?->actualizadoEn,
            $posicionLotes?->id,
            self::LIMITE_POR_SECCION,
        );
        if ($lotes !== []) {
            $ultima = $lotes[array_key_last($lotes)];
            $saliente = $saliente->conPosicion(CursorCatalogo::LOTES, new PosicionCursor($ultima->updatedAt, $ultima->id));
        }

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

        $posicionTrabajos = $entrante->posicion(CursorCatalogo::TRABAJOS);
        $trabajos = $this->trabajos->listarModificadosDesde(
            $posicionTrabajos?->actualizadoEn,
            $posicionTrabajos?->id,
            self::LIMITE_POR_SECCION,
            $equiposVigentes = $this->equiposDelOperario($operarioPersonaId),
        );
        if ($trabajos !== []) {
            $ultima = $trabajos[array_key_last($trabajos)];
            $saliente = $saliente->conPosicion(CursorCatalogo::TRABAJOS, new PosicionCursor($ultima->updatedAt, $ultima->id));
        }

        // Retirados (opción B de la propuesta de #312). Un dispositivo sin
        // ninguna posición no tiene nada que retirar: no recibe retirados y
        // sus dos posiciones arrancan en "ahora", así el pull siguiente trae
        // solo lo que se retire desde este momento. Un cursor que ya tiene
        // otras secciones pero no estas (anterior a este cambio) las trae
        // desde el principio, paginadas: ese dispositivo puede tener órdenes
        // o trabajos viejos que limpiar.
        $ordenesRetiradas = [];
        $trabajosRetirados = [];

        if ($entrante->esVacio()) {
            $ahora = new PosicionCursor(Carbon::now()->toIso8601String(), 0);
            $saliente = $saliente
                ->conPosicion(CursorCatalogo::ORDENES_RETIRADAS, $ahora)
                ->conPosicion(CursorCatalogo::TRABAJOS_RETIRADOS, $ahora);
        } else {
            $posicionOrdenesRetiradas = $entrante->posicion(CursorCatalogo::ORDENES_RETIRADAS);
            $ordenesRetiradas = $this->retiros->ordenesRetiradasDesde(
                $posicionOrdenesRetiradas?->actualizadoEn,
                $posicionOrdenesRetiradas?->id,
                self::LIMITE_POR_SECCION,
            );
            if ($ordenesRetiradas !== []) {
                $ultima = $ordenesRetiradas[array_key_last($ordenesRetiradas)];
                $saliente = $saliente->conPosicion(CursorCatalogo::ORDENES_RETIRADAS, new PosicionCursor($ultima->updatedAt, $ultima->id));
            }

            $posicionTrabajosRetirados = $entrante->posicion(CursorCatalogo::TRABAJOS_RETIRADOS);
            $trabajosRetirados = $this->retiros->trabajosRetiradosDesde(
                $posicionTrabajosRetirados?->actualizadoEn,
                $posicionTrabajosRetirados?->id,
                self::LIMITE_POR_SECCION,
                $equiposVigentes,
                $this->equiposHistoricosDelOperario($operarioPersonaId),
            );
            if ($trabajosRetirados !== []) {
                $ultima = $trabajosRetirados[array_key_last($trabajosRetirados)];
                $saliente = $saliente->conPosicion(CursorCatalogo::TRABAJOS_RETIRADOS, new PosicionCursor($ultima->updatedAt, $ultima->id));
            }
        }

        return [
            'ordenes' => array_map(static fn ($orden) => $orden->toArray(), $ordenes),
            'lotes' => array_map(static fn ($lote) => $lote->toArray(), $lotes),
            'personas' => array_map(static fn ($persona) => $persona->toArray(), $personas),
            'trabajos' => array_map(static fn ($trabajo) => $trabajo->toArray(), $trabajos),
            'ordenes_retiradas' => array_map(static fn ($orden) => $orden->toArray(), $ordenesRetiradas),
            'trabajos_retirados' => array_map(static fn ($trabajo) => $trabajo->toArray(), $trabajosRetirados),
            'cursor' => $saliente->serializar(),
        ];
    }

    /**
     * Todos los equipos que la persona integró alguna vez: con ellos se
     * reconocen, en la bitácora, los trabajos que tuvo y le reasignaron a
     * otro equipo. Sin persona operativa, ninguno.
     *
     * @return list<int>
     */
    private function equiposHistoricosDelOperario(?int $operarioPersonaId): array
    {
        if ($operarioPersonaId === null) {
            return [];
        }

        return $this->equipos->equiposHistoricosDePersona($operarioPersonaId);
    }

    /**
     * Equipos en los que el operario del token está vigente hoy (petición de
     * agrocom-field del 1/10/2026, decisión del dueño): sin este filtro cada
     * dispositivo recibía los trabajos de TODOS los equipos y el piloto podía
     * cargar sesiones en el de otro. Un usuario sin persona operativa no
     * tiene equipo: `trabajos` va vacío.
     *
     * @return list<int>
     */
    private function equiposDelOperario(?int $operarioPersonaId): array
    {
        if ($operarioPersonaId === null) {
            return [];
        }

        return $this->equipos->equiposDePersonaAFecha($operarioPersonaId, Carbon::today()->toDateString());
    }
}
