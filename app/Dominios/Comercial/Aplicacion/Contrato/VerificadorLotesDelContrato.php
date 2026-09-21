<?php

namespace App\Dominios\Comercial\Aplicacion\Contrato;

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\ContratoLote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dos guardas sobre el conjunto de lotes que un contrato está por tener: que
 * cada lote sea de una propiedad del mismo cliente del contrato (pedido del
 * dueño, tarea "contratos-lotes", 16/9/2026), y que ningún lote esté ya
 * retenido por OTRO contrato `vigente` o `pausado` de la misma campaña
 * (pedido del dueño, 18/9/2026, ADR 0021 — reemplaza a la guarda por
 * PROPIEDAD agotada, que solo saltaba con el 100% de sus lotes cubiertos).
 * Colaborador compartido entre `Aplicacion/CrearContrato`,
 * `Aplicacion/ActualizarContrato` y `MaquinaEstadosContrato::activar()` —
 * mismo criterio que `Aplicacion/Lote/VerificadorHistorialLote`: un único
 * lugar para que ninguno lo duplique ni lo olvide.
 *
 * Usa los modelos Eloquent `Lote`/`ContratoLote`/`Contrato` directo, no
 * `DB::table`: son del mismo módulo Comercial, sin frontera de `Contratos/`
 * de por medio (ADR 0003 solo exige contrato/evento ENTRE módulos, no
 * dentro del mismo).
 */
final class VerificadorLotesDelContrato
{
    /**
     * Primer lote (por su `codigo`, para el mensaje) que NO pertenece a
     * ninguna propiedad del cliente dado, o `null` si todos pertenecen.
     *
     * @param  list<int>  $loteIds
     */
    public static function loteAjenoAlCliente(array $loteIds, int $clienteId): ?string
    {
        if ($loteIds === []) {
            return null;
        }

        $ajeno = Lote::query()
            ->whereIn('id', $loteIds)
            ->whereDoesntHave('propiedad', fn (Builder $query) => $query->where('cliente_id', $clienteId))
            ->first();

        return $ajeno?->codigo;
    }

    /**
     * Lotes de `$loteIds` que YA están retenidos (contrato `vigente` o
     * `pausado`, ver {@see EstadoContrato::retieneLotes()}) por OTRO contrato
     * de `$campaniaId`, cada uno con el primer contrato que lo retiene.
     * Lista vacía si ninguno está ocupado.
     *
     * `$contratoIdExcluido` es el propio contrato en edición — se recalcula
     * sin contarlo contra sí mismo — o `null` en un alta, donde el contrato
     * todavía no existe.
     *
     * @param  list<int>  $loteIds
     * @return list<array{lote_id: int, codigo: string, contrato_id: int}>
     */
    public static function lotesOcupados(array $loteIds, int $campaniaId, ?int $contratoIdExcluido): array
    {
        if ($loteIds === []) {
            return [];
        }

        $filas = ContratoLote::query()
            ->whereIn('lote_id', $loteIds)
            ->whereHas('contrato', function (Builder $query) use ($campaniaId, $contratoIdExcluido): void {
                $query->whereIn('estado', EstadoContrato::valoresQueRetienenLotes())
                    ->where('campania_id', $campaniaId);

                if ($contratoIdExcluido !== null) {
                    $query->whereKeyNot($contratoIdExcluido);
                }
            })
            ->orderBy('contrato_id')
            ->get(['id', 'contrato_id', 'lote_id']);

        if ($filas->isEmpty()) {
            return [];
        }

        $codigos = Lote::query()
            ->withTrashed()
            ->whereIn('id', $filas->pluck('lote_id')->unique()->all())
            ->pluck('codigo', 'id');

        $ocupados = [];
        foreach ($filas as $fila) {
            $ocupados[$fila->lote_id] ??= [
                'lote_id' => $fila->lote_id,
                'codigo' => (string) ($codigos[$fila->lote_id] ?? $fila->lote_id),
                'contrato_id' => $fila->contrato_id,
            ];
        }

        return array_values($ocupados);
    }

    /**
     * Serializa, dentro de la transacción en curso, toda operación que decide
     * qué contrato retiene qué lote de `$campaniaId`: dos aprobaciones
     * simultáneas de contratos que comparten un lote se turnan en vez de
     * ver ambas "el lote está libre". Bloquea las filas de los contratos de
     * la campaña (`SELECT ... FOR UPDATE`); en SQLite (tests) no hace nada.
     * Llamar SIEMPRE adentro de un `DB::transaction()`, antes de consultar
     * {@see self::lotesOcupados()}.
     */
    public static function bloquearCampania(int $campaniaId): void
    {
        Contrato::query()->where('campania_id', $campaniaId)->lockForUpdate()->pluck('id');
    }
}
