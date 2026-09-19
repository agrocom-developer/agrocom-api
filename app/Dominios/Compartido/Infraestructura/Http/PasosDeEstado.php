<?php

namespace App\Dominios\Compartido\Infraestructura\Http;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use BackedEnum;
use Closure;

/**
 * Arma los pasos que dibuja `molecules/step-arrow` (19/9/2026) para un objeto
 * con máquina de estados: qué estado es el actual, cuáles ya se recorrieron,
 * a cuáles se puede pasar desde acá y cuáles no.
 *
 * No decide ninguna transición: la tabla de transiciones permitidas la
 * consulta a través de `$permitida` (la de la propia máquina, p. ej.
 * `TransicionesCampania::permitida(...)`), así lo que se dibuja no puede
 * contradecir lo que el servidor va a aceptar. Quien cambia el estado de
 * verdad sigue siendo el servicio de la máquina de estados (invariante 7 de
 * CLAUDE.md), al que llega el formulario que abre cada paso `next`.
 *
 * Es de plataforma: no conoce ningún módulo, solo el enum de estados y
 * las claves de idioma que le pasa quien lo usa. El objeto (campaña,
 * contrato…) solo define sus estados y, en su `lang`, la etiqueta y el texto
 * de ayuda de cada uno; cómo se compone el párrafo con el paso siguiente y el
 * permiso es de acá, así se reutiliza en cualquier pantalla.
 *
 * @phpstan-type Paso array{key: string, label: string, tone: string, status: 'completed'|'current'|'next'|'pending'|'blocked', modal: string|null, hint: string|null}
 */
final class PasosDeEstado
{
    /**
     * @template T of BackedEnum
     *
     * @param  list<T>  $ruta  estados de la ruta principal, en el orden en que se recorren.
     * @param  T  $actual  estado actual del objeto.
     * @param  Closure(T, T): bool  $permitida  ¿la máquina admite pasar del primero al segundo?
     * @param  array<string, string>  $tonos  valor del estado → tono de `atoms/badge`
     *                                        (sin entrada cae en `neutral`).
     * @param  string  $claveEtiqueta  prefijo de idioma: la etiqueta de cada paso es
     *                                 `"{$claveEtiqueta}.{valor del estado}"`.
     * @param  string  $prefijoModal  el `id` del `confirm-modal` que abre un paso `next`
     *                                es `"{$prefijoModal}-{valor del estado}"`.
     * @param  bool  $puedeCambiar  si el usuario tiene el permiso de cambiar el estado.
     * @return list<Paso>
     */
    public static function armar(
        array $ruta,
        BackedEnum $actual,
        Closure $permitida,
        array $tonos,
        string $claveEtiqueta,
        string $prefijoModal,
        bool $puedeCambiar,
    ): array {
        $posicionActual = array_search($actual, $ruta, true);
        $posicionActual = $posicionActual === false ? -1 : $posicionActual;
        $siguiente = $ruta[$posicionActual + 1] ?? null;

        $pasos = [];

        foreach ($ruta as $posicion => $estado) {
            $situacion = match (true) {
                $estado === $actual => 'current',
                $posicion < $posicionActual => 'completed',
                ! $permitida($actual, $estado) => 'blocked',
                $puedeCambiar => 'next',
                default => 'pending',
            };

            $pasos[] = [
                'key' => (string) $estado->value,
                'label' => self::etiqueta($claveEtiqueta, $estado),
                'tone' => $tonos[(string) $estado->value] ?? 'neutral',
                'status' => $situacion,
                'modal' => $situacion === 'next' ? "{$prefijoModal}-{$estado->value}" : null,
                'hint' => match (true) {
                    $situacion === 'pending' => Texto::de('ui.pasos.pista_sin_permiso'),
                    // Se pide pasar antes por el paso que sigue al actual; si ese es
                    // el mismo paso bloqueado, no hay nada intermedio que nombrar.
                    $situacion === 'blocked' && $siguiente !== null && $siguiente !== $estado => Texto::de(
                        'ui.pasos.pista_bloqueado',
                        ['paso' => self::etiqueta($claveEtiqueta, $siguiente)],
                    ),
                    default => null,
                },
            ];
        }

        return $pasos;
    }

    /**
     * Párrafo que acompaña a los pasos: qué significa el estado actual y por
     * qué conviene pasar al siguiente (el texto del objeto), y después qué
     * hacer — hacer clic en el paso siguiente, o que el rol no puede cambiar
     * el estado. Un estado final, sin paso al que ir, lleva solo el texto del
     * objeto.
     *
     * El texto del estado sale de `"{$claveAyuda}.{valor del estado}"` y puede
     * usar los marcadores `:actual` y `:paso` (etiquetas del estado actual y
     * del siguiente). Que cada estado de la ruta tenga su texto lo vigila
     * `tests/Unit/PasosDeEstadoTest.php`.
     *
     * @param  list<Paso>  $pasos  los que devolvió {@see self::armar()}.
     * @param  string  $claveAyuda  prefijo de idioma del texto de cada estado.
     */
    public static function ayuda(array $pasos, string $claveAyuda): ?string
    {
        $actual = null;
        $destino = null;

        foreach ($pasos as $paso) {
            if ($paso['status'] === 'current') {
                $actual = $paso;
            } elseif ($destino === null && in_array($paso['status'], ['next', 'pending'], true)) {
                $destino = $paso;
            }
        }

        if ($actual === null) {
            return null;
        }

        $motivo = Texto::de("{$claveAyuda}.{$actual['key']}", [
            'actual' => $actual['label'],
            'paso' => $destino['label'] ?? '',
        ]);

        return match ($destino['status'] ?? null) {
            'next' => $motivo.' '.Texto::de('ui.pasos.ayuda_accion', ['paso' => $destino['label']]),
            'pending' => $motivo.' '.Texto::de('ui.pasos.ayuda_sin_permiso'),
            default => $motivo,
        };
    }

    private static function etiqueta(string $claveEtiqueta, BackedEnum $estado): string
    {
        return Texto::de("{$claveEtiqueta}.{$estado->value}");
    }
}
