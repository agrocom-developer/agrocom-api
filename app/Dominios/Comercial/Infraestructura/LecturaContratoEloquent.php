<?php

namespace App\Dominios\Comercial\Infraestructura;

use App\Dominios\Campania\Contratos\LecturaCampania;
use App\Dominios\Comercial\Contratos\DatosContratoParaOrden;
use App\Dominios\Comercial\Contratos\DatosResumenContrato;
use App\Dominios\Comercial\Contratos\LecturaContrato;
use App\Dominios\Comercial\Contratos\LoteDeContrato;
use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use Illuminate\Support\Facades\DB;

/**
 * Implementación Eloquent del contrato de lectura de resumen de contrato.
 * Vive fuera de `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaLotesEloquent`: esa subcarpeta está reservada a modelos que
 * extienden `ModeloDominio` (`tests/Unit/ArquitecturaModulosTest.php` lo
 * exige) — esta clase no es un modelo, es el adaptador que el
 * `ServiceProvider` del módulo liga a {@see LecturaContrato}.
 *
 * `Contrato` y `Cliente` viven las dos en este módulo, así que sí usa la
 * relación Eloquent `cliente()` directo (ADR 0003, regla 3 solo restringe
 * relaciones CRUZADAS entre módulos). La campaña, en cambio, vive en el
 * módulo `Campania` — se resuelve por su contrato de lectura
 * {@see LecturaCampania}, mismo patrón que `Comercial\Aplicacion\CrearContrato`
 * usa para validar la campaña elegida (ADR 0015).
 */
final class LecturaContratoEloquent implements LecturaContrato
{
    public function __construct(private readonly LecturaCampania $lecturaCampania) {}

    public function obtenerResumen(int $contratoId): ?DatosResumenContrato
    {
        $contrato = Contrato::query()->with('cliente')->find($contratoId);

        if ($contrato === null) {
            return null;
        }

        $campania = $contrato->campania_id !== null
            ? $this->lecturaCampania->obtener($contrato->campania_id)
            : null;

        return new DatosResumenContrato(
            contratoId: $contrato->id,
            clienteId: $contrato->cliente_id,
            clienteNombre: $contrato->cliente->razon_social,
            campaniaId: $contrato->campania_id,
            campaniaCodigo: $campania?->codigo,
        );
    }

    /**
     * Los lotes se leen con `DB::table` y no con relaciones Eloquent: es una
     * lectura plana de tres tablas del propio módulo, sin nada que hidratar.
     */
    public function obtenerParaOrden(int $contratoId): ?DatosContratoParaOrden
    {
        $contrato = Contrato::query()->find($contratoId);

        if ($contrato === null) {
            return null;
        }

        $lotes = DB::table('com_contrato_lotes as cl')
            ->join('com_lotes as l', 'l.id', '=', 'cl.lote_id')
            ->join('com_propiedades as p', 'p.id', '=', 'l.propiedad_id')
            ->where('cl.contrato_id', $contratoId)
            ->whereNull('cl.deleted_at')
            ->whereNull('l.deleted_at')
            ->orderBy('p.nombre')
            ->orderBy('l.codigo')
            ->get(['l.id as lote_id', 'l.codigo', 'l.hectareas', 'p.nombre as propiedad'])
            ->map(fn (object $fila): LoteDeContrato => new LoteDeContrato(
                loteId: (int) $fila->lote_id,
                codigo: (string) $fila->codigo,
                propiedad: (string) $fila->propiedad,
                hectareas: (string) $fila->hectareas,
            ))
            ->all();

        return new DatosContratoParaOrden(
            contratoId: $contrato->id,
            clienteId: $contrato->cliente_id,
            vigente: $contrato->estado === EstadoContrato::Vigente,
            aplicacionesPrevistas: (int) $contrato->aplicaciones_previstas,
            hectareasContratadas: (string) $contrato->hectareas_contratadas,
            lotes: $lotes,
        );
    }
}
