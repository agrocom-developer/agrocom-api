<?php

namespace App\Dominios\Operaciones\Infraestructura\Http;

use App\Dominios\Compartido\Infraestructura\Http\PasosDeEstado;
use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesOrden;

/**
 * Los pasos de `molecules/step-arrow` de la orden de aplicación (19/9/2026), en
 * su ficha de edición y en su detalle: cómo se dibuja su máquina de estados.
 * Deja en un solo lugar lo que es propio de la orden —la ruta, el tono de cada
 * estado, qué se da por recorrido, qué acción y qué permiso hay detrás de cada
 * paso y a cuál apunta el párrafo de ayuda— y delega el armado en
 * {@see PasosDeEstado}.
 *
 * La orden tampoco es una línea recta: se puede pausar y volver (`vigente ⇄
 * pausada`) y tiene una salida sin vuelta (`cancelada`). La ruta que se dibuja
 * son cinco pasos —«Emitida → Vigente → Pausada → Consumida → Cancelada»—;
 * `vencida` no tiene disparador de negocio (ADR 0022) y solo se agrega, como
 * paso actual, si una orden ya está así.
 *
 * A diferencia del contrato, cada transición tiene SU permiso
 * (`operaciones.orden.activar`/`.pausar`/`.cerrar`/`.cancelar`; reanudar usa el
 * de pausar), así que el usuario puede tener unos pasos accionables y otros
 * no. Y los modales no son nuevos: son los de `_orden-modales.blade.php`, los
 * mismos que abre el listado, con su nombre por ACCIÓN (`orden-activar-modal-…`,
 * `orden-reanudar-modal-…`), no por estado de llegada: «Vigente» se alcanza con
 * activar o con reanudar según de dónde se venga.
 *
 * Nada de esto decide una transición: quien la aplica, con sus guardas, es
 * `MaquinaEstadosOrden` (invariante 7); la tabla que consulta lo dibujado es la
 * misma, {@see TransicionesOrden}.
 */
final class PasosDeOrden
{
    /**
     * Tono de cada estado, definido UNA vez: lo comparten el badge del listado y
     * del detalle, las tarjetas y los pasos, para que todos hablen con el mismo
     * color.
     *
     * @var array<string, string>
     */
    public const array TONO_POR_ESTADO = [
        'emitida' => 'neutral',
        'vigente' => 'success',
        'pausada' => 'warning',
        'consumida' => 'info',
        'cancelada' => 'danger',
        'vencida' => 'danger',
    ];

    /**
     * Permiso (grupo) que cada acción exige: reanudar comparte el de pausar,
     * como en el controlador.
     *
     * @var array<string, string>
     */
    private const array PERMISO_DE_ACCION = [
        'activar' => 'activar',
        'pausar' => 'pausar',
        'reanudar' => 'pausar',
        'cerrar' => 'cerrar',
        'cancelar' => 'cancelar',
    ];

    /**
     * @param  array{activar: bool, pausar: bool, cerrar: bool, cancelar: bool}  $puede  qué acciones permite el rol activo.
     * @param  string  $sufijoModal  el que llevan los ids de `_orden-modales` (`"{$contexto}{$orden->id}"`).
     * @return list<array{key: string, label: string, tone: string, status: 'completed'|'current'|'next'|'pending'|'blocked', modal: string|null, hint: string|null, icon: string|null}>
     */
    public static function armar(EstadoOrdenAplicacion $actual, array $puede, string $sufijoModal): array
    {
        return PasosDeEstado::armar(
            ruta: self::ruta($actual),
            actual: $actual,
            permitida: TransicionesOrden::permitida(...),
            tonos: self::TONO_POR_ESTADO,
            claveEtiqueta: 'operaciones.estado',
            prefijoModal: 'orden-estado-modal',
            // El permiso real es el de cada acción (`puedeIrA`).
            puedeCambiar: true,
            recorridos: self::recorridos($actual),
            pistas: $actual === EstadoOrdenAplicacion::Emitida
                ? [EstadoOrdenAplicacion::Cancelada->value => Texto::de('operaciones.ordenes.pasos.pista_cancelar_emitida')]
                : [],
            iconos: [
                EstadoOrdenAplicacion::Cancelada->value => 'cancel',
                EstadoOrdenAplicacion::Vencida->value => 'event_busy',
            ],
            puedeIrA: fn (EstadoOrdenAplicacion $hacia): bool => self::puede($actual, $hacia, $puede),
            modales: self::modales($actual, $sufijoModal),
        );
    }

    /**
     * Párrafo de apoyo bajo los pasos: qué significa el estado actual y qué
     * conviene hacer. Sale de `operaciones.ordenes.estado_ayuda.*`.
     *
     * @param  list<array{key: string, label: string, tone: string, status: 'completed'|'current'|'next'|'pending'|'blocked', modal: string|null, hint: string|null, icon: string|null}>  $pasos
     */
    public static function ayuda(array $pasos, EstadoOrdenAplicacion $actual): ?string
    {
        return PasosDeEstado::ayuda($pasos, 'operaciones.ordenes.estado_ayuda', self::destinoDeLaAyuda($actual));
    }

    /**
     * La fila de cinco pasos; «Vencida» solo aparece, al final, en una orden
     * que ya está vencida, para que el estado actual siempre figure entre los
     * pasos.
     *
     * @return list<EstadoOrdenAplicacion>
     */
    private static function ruta(EstadoOrdenAplicacion $actual): array
    {
        $ruta = [
            EstadoOrdenAplicacion::Emitida,
            EstadoOrdenAplicacion::Vigente,
            EstadoOrdenAplicacion::Pausada,
            EstadoOrdenAplicacion::Consumida,
            EstadoOrdenAplicacion::Cancelada,
        ];

        if ($actual === EstadoOrdenAplicacion::Vencida) {
            $ruta[] = EstadoOrdenAplicacion::Vencida;
        }

        return $ruta;
    }

    /**
     * Estados por los que la orden seguro pasó para llegar al actual. De una
     * pausada no cuenta `vigente`, porque volver a él es justo el paso que se
     * ofrece (reanudar). Una cancelada solo se cancela desde `vigente` o
     * `pausada`, así que activarla ya ocurrió; una vencida no dice de dónde
     * viene.
     *
     * @return list<EstadoOrdenAplicacion>
     */
    private static function recorridos(EstadoOrdenAplicacion $actual): array
    {
        return match ($actual) {
            EstadoOrdenAplicacion::Vigente, EstadoOrdenAplicacion::Pausada => [EstadoOrdenAplicacion::Emitida],
            EstadoOrdenAplicacion::Consumida, EstadoOrdenAplicacion::Cancelada => [EstadoOrdenAplicacion::Emitida, EstadoOrdenAplicacion::Vigente],
            EstadoOrdenAplicacion::Emitida, EstadoOrdenAplicacion::Vencida => [],
        };
    }

    /**
     * El paso que nombra el cierre del párrafo («Para avanzar, haz clic en…»):
     * activar una emitida, cerrar una vigente —no el primero accionable, que
     * sería «Pausada»— y reanudar una pausada. Un estado final no nombra ninguno.
     */
    private static function destinoDeLaAyuda(EstadoOrdenAplicacion $actual): ?string
    {
        return match ($actual) {
            EstadoOrdenAplicacion::Emitida, EstadoOrdenAplicacion::Pausada => EstadoOrdenAplicacion::Vigente->value,
            EstadoOrdenAplicacion::Vigente => EstadoOrdenAplicacion::Consumida->value,
            EstadoOrdenAplicacion::Consumida, EstadoOrdenAplicacion::Cancelada, EstadoOrdenAplicacion::Vencida => null,
        };
    }

    /**
     * La acción que lleva de un estado a otro, con el nombre que usan los
     * modales y los permisos. `emitida` y `vencida` nunca son un destino.
     */
    private static function accion(EstadoOrdenAplicacion $desde, EstadoOrdenAplicacion $hacia): ?string
    {
        return match ($hacia) {
            EstadoOrdenAplicacion::Vigente => $desde === EstadoOrdenAplicacion::Emitida ? 'activar' : 'reanudar',
            EstadoOrdenAplicacion::Pausada => 'pausar',
            EstadoOrdenAplicacion::Consumida => 'cerrar',
            EstadoOrdenAplicacion::Cancelada => 'cancelar',
            EstadoOrdenAplicacion::Emitida, EstadoOrdenAplicacion::Vencida => null,
        };
    }

    /**
     * @param  array{activar: bool, pausar: bool, cerrar: bool, cancelar: bool}  $puede
     */
    private static function puede(EstadoOrdenAplicacion $desde, EstadoOrdenAplicacion $hacia, array $puede): bool
    {
        $accion = self::accion($desde, $hacia);

        return $accion !== null && $puede[self::PERMISO_DE_ACCION[$accion]];
    }

    /**
     * Estado destino → `id` del modal de `_orden-modales` que abre ese paso
     * desde el estado actual.
     *
     * @return array<string, string>
     */
    private static function modales(EstadoOrdenAplicacion $actual, string $sufijoModal): array
    {
        $modales = [];

        foreach (EstadoOrdenAplicacion::cases() as $hacia) {
            $accion = self::accion($actual, $hacia);

            if ($accion !== null) {
                $modales[$hacia->value] = "orden-{$accion}-modal-{$sufijoModal}";
            }
        }

        return $modales;
    }
}
