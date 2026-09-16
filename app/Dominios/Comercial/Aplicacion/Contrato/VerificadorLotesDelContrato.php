<?php

namespace App\Dominios\Comercial\Aplicacion\Contrato;

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\ContratoLote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dos guardas sobre el conjunto de lotes que un contrato está por tener
 * (pedido del dueño, tarea "contratos-lotes", 16/9/2026): que cada lote sea
 * de una propiedad del mismo cliente del contrato, y que ninguna propiedad
 * involucrada quede "sobre-comprometida" (100% de sus lotes activos ya
 * cubiertos por OTROS contratos `vigente` de la misma campaña). Colaborador
 * compartido entre `Aplicacion/CrearContrato` y `Aplicacion/ActualizarContrato`
 * — mismo criterio que `Aplicacion/Lote/VerificadorHistorialLote`: un único
 * lugar para que ninguno de los dos casos de uso lo duplique ni lo olvide.
 *
 * Usa los modelos Eloquent `Lote`/`Propiedad`/`ContratoLote` directo, no
 * `DB::table`: los tres son del mismo módulo Comercial que `Contrato`, sin
 * frontera de `Contratos/` de por medio (ADR 0003 solo exige contrato/evento
 * ENTRE módulos, no dentro del mismo).
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
     * Primera propiedad (entre las de `$loteIds`) que ya tiene el 100% de
     * sus lotes activos cubiertos por OTROS contratos `vigente` de
     * `$campaniaId`, o `null` si ninguna quedó agotada.
     *
     * `$contratoIdExcluido` es el propio contrato en edición — se recalcula
     * sin contarlo contra sí mismo — o `null` en un alta, donde el contrato
     * todavía no existe.
     *
     * @param  list<int>  $loteIds
     * @return array{id: int, nombre: string}|null
     */
    public static function propiedadAgotada(array $loteIds, int $campaniaId, ?int $contratoIdExcluido): ?array
    {
        if ($loteIds === []) {
            return null;
        }

        $propiedadIds = Lote::query()->whereIn('id', $loteIds)->pluck('propiedad_id')->unique();

        foreach ($propiedadIds as $propiedadId) {
            $loteIdsDeLaPropiedad = Lote::query()->where('propiedad_id', $propiedadId)->pluck('id');

            $ocupados = ContratoLote::query()
                ->whereIn('lote_id', $loteIdsDeLaPropiedad)
                ->whereHas('contrato', function (Builder $query) use ($campaniaId, $contratoIdExcluido): void {
                    $query->where('estado', EstadoContrato::Vigente)
                        ->where('campania_id', $campaniaId);

                    if ($contratoIdExcluido !== null) {
                        $query->whereKeyNot($contratoIdExcluido);
                    }
                })
                ->pluck('lote_id')
                ->unique()
                ->count();

            if ($ocupados >= $loteIdsDeLaPropiedad->count()) {
                $propiedad = Propiedad::query()->find($propiedadId);
                $nombre = $propiedad === null ? (string) $propiedadId : $propiedad->nombre;

                return ['id' => (int) $propiedadId, 'nombre' => $nombre];
            }
        }

        return null;
    }
}
