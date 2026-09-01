<?php

namespace App\Dominios\Sincronizacion\Aplicacion;

use App\Dominios\Comercial\Contratos\LecturaLotes;
use App\Dominios\Operaciones\Contratos\LecturaOrdenesVigentes;
use App\Dominios\Personal\Contratos\LecturaPersonas;
use App\Dominios\Sincronizacion\Dominio\CursorCatalogo;
use App\Dominios\Sincronizacion\Dominio\PosicionCursor;

/**
 * Caso de uso de `GET /api/sync/catalogo` (espec §2.1, punto 6; TE-06
 * parcial — ver `runs/08-diseno.md`). Combina los tres contratos de lectura
 * de los módulos dueños (nunca sus modelos Eloquent, ADR 0003 regla 2) y
 * arma la respuesta con el cursor de continuación.
 *
 * Recetas y productos quedan fuera a propósito: `Mezclas` no existe todavía
 * (ver recorte de alcance de la tarea 08 en `docs/gestion/cola_tareas.md`).
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
    ) {}

    /** @return array<string, mixed> */
    public function ejecutar(?string $cursor): array
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

        return [
            'ordenes' => array_map(static fn ($orden) => $orden->toArray(), $ordenes),
            'lotes' => array_map(static fn ($lote) => $lote->toArray(), $lotes),
            'personas' => array_map(static fn ($persona) => $persona->toArray(), $personas),
            'cursor' => $saliente->serializar(),
        ];
    }
}
