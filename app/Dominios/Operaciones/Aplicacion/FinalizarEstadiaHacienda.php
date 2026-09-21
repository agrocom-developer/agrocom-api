<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\EstadoEstadia;
use App\Dominios\Operaciones\Dominio\Excepciones\EstadiaYaFinalizada;
use App\Dominios\Operaciones\Dominio\Excepciones\SalidaAnteriorAEntrada;
use App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesEstadia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\EstadiaHacienda;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cierre manual de una estadía en hacienda desde el panel (reforma
 * 19/9/2026): la transición `en_curso → finalizada` pasa por
 * {@see TransicionesEstadia} (invariante 7 de CLAUDE.md) — la única salida es
 * esa, sin vuelta atrás. Mismo molde de concurrencia que
 * `EscrituraSincronizacionEloquent::cerrarEstadia()` (el cierre del MISMO
 * hecho, por sync): `lockForUpdate` dentro de una transacción, para que dos
 * clics de "Finalizar" concurrentes sobre la misma estadía no la finalicen
 * dos veces con `salida` distinta.
 *
 * Genera su propio `cierre_uuid_cliente` (`(string) Str::uuid()`, mismo
 * criterio que `RegistrarEstadiaHacienda`): esta finalización nace en el
 * panel, no hay dispositivo que traiga el UUID del evento de salida.
 */
final class FinalizarEstadiaHacienda
{
    /**
     * @throws EstadiaYaFinalizada si la estadía ya no está en curso.
     * @throws SalidaAnteriorAEntrada si `$salida` no es posterior a la entrada.
     */
    public function ejecutar(EstadiaHacienda $estadia, string $salida): EstadiaHacienda
    {
        return DB::transaction(function () use ($estadia, $salida): EstadiaHacienda {
            /** @var EstadiaHacienda $bloqueada */
            $bloqueada = EstadiaHacienda::query()->whereKey($estadia->id)->lockForUpdate()->firstOrFail();

            if (! TransicionesEstadia::permitida($bloqueada->estado(), EstadoEstadia::Finalizada)) {
                throw EstadiaYaFinalizada::porId($bloqueada->id);
            }

            $salidaUtc = CarbonImmutable::parse($salida)->utc();

            if (! $salidaUtc->greaterThan($bloqueada->entrada)) {
                throw new SalidaAnteriorAEntrada;
            }

            $bloqueada->salida = $salidaUtc;
            $bloqueada->cierre_uuid_cliente = (string) Str::uuid();
            $bloqueada->save();

            return $bloqueada;
        });
    }
}
