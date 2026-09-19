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
 * Una máquina en línea recta (campaña: planificada → abierta → cerrada) solo
 * necesita la ruta. Una con desvíos y salidas (contrato: pausado, conflicto,
 * cancelado) usa además `$recorridos`, `$pistas` e `$iconos` de `armar()` y el
 * `$claveDestino` de `ayuda()`; todos son opcionales y, sin ellos, el
 * resultado es el de siempre. Una con un permiso distinto por transición y
 * modales propios (la orden de aplicación) suma `$puedeIrA` y `$modales`.
 *
 * @phpstan-type Paso array{key: string, label: string, tone: string, status: 'completed'|'current'|'next'|'pending'|'blocked', modal: string|null, hint: string|null, icon: string|null}
 */
final class PasosDeEstado
{
    /**
     * @template T of BackedEnum
     *
     * @param  list<T>  $ruta  estados de la ruta principal, en el orden en que se recorren.
     * @param  T  $actual  estado actual del objeto.
     * @param  Closure(T, T): bool  $permitida  ¿la máquina admite pasar del primero al segundo?
     *                                          Un paso `next` puede quedar ANTES del actual (volver de
     *                                          una pausa): lo que manda es esta tabla, no la posición.
     * @param  array<string, string>  $tonos  valor del estado → tono de `atoms/badge`
     *                                        (sin entrada cae en `neutral`).
     * @param  string  $claveEtiqueta  prefijo de idioma: la etiqueta de cada paso es
     *                                 `"{$claveEtiqueta}.{valor del estado}"`.
     * @param  string  $prefijoModal  el `id` del `confirm-modal` que abre un paso `next`
     *                                es `"{$prefijoModal}-{valor del estado}"`.
     * @param  bool  $puedeCambiar  si el usuario tiene el permiso de cambiar el estado.
     * @param  list<T>|null  $recorridos  estados por los que el objeto YA pasó para llegar al
     *                                    actual. `null` (lo habitual) los deduce de la posición
     *                                    en la ruta: todo lo que queda antes del actual. Hay que
     *                                    pasarlos cuando la ruta mezcla desvíos y salidas: un
     *                                    contrato cancelado no completó «Ejecutado» aunque
     *                                    quede antes de «Cancelado» en la fila.
     * @param  array<string, string>  $pistas  valor del estado → texto (ya traducido) para un
     *                                         paso `blocked`, en lugar del genérico «pasa antes
     *                                         por…». Sirve cuando el motivo del bloqueo no es de
     *                                         orden sino de negocio (p. ej. un conflicto de lotes).
     * @param  array<string, string>  $iconos  valor del estado → ícono con el que se dibuja el
     *                                         paso cuando queda procesado (por defecto un check):
     *                                         un estado de salida como «Cancelado» no es un éxito.
     * @param  (Closure(T): bool)|null  $puedeIrA  ¿el usuario tiene el permiso de pasar A ese estado?
     *                                             Sirve cuando cada transición tiene su propio permiso
     *                                             (la orden: activar, pausar, cerrar, cancelar) y no
     *                                             uno solo. Se suma a `$puedeCambiar`: un paso es `next`
     *                                             si los dos lo permiten; si no, queda `pending`.
     * @param  array<string, string>  $modales  valor del estado → `id` del modal que abre su paso
     *                                          `next`, en vez del que arma el prefijo. Sirve cuando la
     *                                          pantalla ya trae sus modales con otro nombre y el mismo
     *                                          estado destino se alcanza con acciones distintas
     *                                          (activar y reanudar llegan a «vigente»).
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
        ?array $recorridos = null,
        array $pistas = [],
        array $iconos = [],
        ?Closure $puedeIrA = null,
        array $modales = [],
    ): array {
        $posicionActual = array_search($actual, $ruta, true);
        $posicionActual = $posicionActual === false ? -1 : $posicionActual;

        // El primer estado de la ruta al que se puede pasar desde el actual: es
        // por el que hay que pasar antes para llegar a uno bloqueado.
        $primerAlcanzable = null;

        foreach ($ruta as $estado) {
            if ($estado !== $actual && $permitida($actual, $estado)) {
                $primerAlcanzable = $estado;
                break;
            }
        }

        $pasos = [];

        foreach ($ruta as $posicion => $estado) {
            $completado = $recorridos === null
                ? $posicion < $posicionActual
                : in_array($estado, $recorridos, true);

            $situacion = match (true) {
                $estado === $actual => 'current',
                $permitida($actual, $estado) => $puedeCambiar && ($puedeIrA === null || $puedeIrA($estado)) ? 'next' : 'pending',
                $completado => 'completed',
                default => 'blocked',
            };

            $pasos[] = [
                'key' => (string) $estado->value,
                'label' => self::etiqueta($claveEtiqueta, $estado),
                'tone' => $tonos[(string) $estado->value] ?? 'neutral',
                'status' => $situacion,
                'modal' => $situacion === 'next' ? ($modales[(string) $estado->value] ?? "{$prefijoModal}-{$estado->value}") : null,
                'hint' => match (true) {
                    $situacion === 'pending' => Texto::de('ui.pasos.pista_sin_permiso'),
                    $situacion === 'blocked' && isset($pistas[(string) $estado->value]) => $pistas[(string) $estado->value],
                    $situacion === 'blocked' && $primerAlcanzable !== null => Texto::de(
                        'ui.pasos.pista_bloqueado',
                        ['paso' => self::etiqueta($claveEtiqueta, $primerAlcanzable)],
                    ),
                    default => null,
                },
                'icon' => $iconos[(string) $estado->value] ?? null,
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
     * El «siguiente» es, por defecto, el primer paso al que se puede pasar. En
     * una máquina con desvíos ese no siempre es el natural (desde «En
     * ejecución» el primero es «Pausado», no «Ejecutado»): `$claveDestino`
     * nombra el paso que va en el cierre. Si ese paso no es accionable (está
     * bloqueado, p. ej. un contrato en conflicto que no se puede aprobar), el
     * párrafo lleva solo el texto del objeto, sin invitar a hacer clic.
     *
     * @param  list<Paso>  $pasos  los que devolvió {@see self::armar()}.
     * @param  string  $claveAyuda  prefijo de idioma del texto de cada estado.
     * @param  string|null  $claveDestino  valor del estado que se nombra como paso siguiente.
     */
    public static function ayuda(array $pasos, string $claveAyuda, ?string $claveDestino = null): ?string
    {
        $actual = null;
        $destino = null;

        foreach ($pasos as $paso) {
            if ($paso['status'] === 'current') {
                $actual = $paso;
            } elseif ($claveDestino !== null) {
                if ($paso['key'] === $claveDestino) {
                    $destino = $paso;
                }
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
