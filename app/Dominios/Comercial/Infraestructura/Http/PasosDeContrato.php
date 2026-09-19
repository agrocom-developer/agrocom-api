<?php

namespace App\Dominios\Comercial\Infraestructura\Http;

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Dominio\MaquinaEstados\TransicionesContrato;
use App\Dominios\Compartido\Infraestructura\Http\PasosDeEstado;
use App\Dominios\Compartido\Infraestructura\Idioma\Texto;

/**
 * Los pasos de `molecules/step-arrow` de la ficha de edición de un contrato
 * (19/9/2026): cómo se dibuja su máquina de estados. Deja en un solo lugar lo
 * que es propio del contrato —la ruta, el tono de cada estado, qué se da por
 * recorrido, la pista de un conflicto y a qué paso apunta el párrafo de
 * ayuda— y delega el armado en {@see PasosDeEstado}.
 *
 * El contrato no es una línea recta: tiene un desvío que vuelve
 * (`pausado ⇄ vigente`), un estado que fija solo el sistema (`conflicto`, ADR
 * 0021) y una salida sin vuelta (`cancelado`). Por eso la ruta que se dibuja
 * son cinco pasos —«En aprobación → En ejecución → Pausado → Ejecutado →
 * Cancelado»— y qué se da por recorrido lo decide `recorridos()`, no la
 * posición en la fila: un contrato cancelado no completó «Ejecutado».
 *
 * Lo que el panel deja pedir es la tabla de transiciones menos los dos
 * estados del sistema: `borrador` y `conflicto` nunca son un destino que un
 * usuario elija (`MaquinaEstadosContrato::cambiarA()` los rechaza), así que
 * ningún paso los ofrece. Nada de esto decide una transición: quien la aplica,
 * con sus guardas, es `MaquinaEstadosContrato` (invariante 7).
 */
final class PasosDeContrato
{
    /**
     * Tono de cada estado, definido UNA vez: lo comparten el badge del
     * listado, el aviso de conflicto de la ficha y los pasos, para que los
     * tres hablen con el mismo color.
     *
     * @var array<string, string>
     */
    public const array TONO_POR_ESTADO = [
        'borrador' => 'neutral',
        'conflicto' => 'alert',
        'vigente' => 'success',
        'pausado' => 'info',
        'finalizado' => 'distintivo-2',
        'cancelado' => 'danger',
    ];

    public const string PREFIJO_MODAL = 'contrato-estado-modal';

    /**
     * @return list<array{key: string, label: string, tone: string, status: 'completed'|'current'|'next'|'pending'|'blocked', modal: string|null, hint: string|null, icon: string|null}>
     */
    public static function armar(EstadoContrato $actual, bool $puedeCambiar): array
    {
        return PasosDeEstado::armar(
            ruta: self::ruta($actual),
            actual: $actual,
            permitida: self::pedible(...),
            tonos: self::TONO_POR_ESTADO,
            claveEtiqueta: 'comercial.contrato.estado',
            prefijoModal: self::PREFIJO_MODAL,
            puedeCambiar: $puedeCambiar,
            recorridos: self::recorridos($actual),
            pistas: $actual === EstadoContrato::Conflicto
                ? [EstadoContrato::Vigente->value => Texto::de('comercial.contrato.pasos.pista_conflicto')]
                : [],
            iconos: [EstadoContrato::Cancelado->value => 'cancel'],
        );
    }

    /**
     * Párrafo de apoyo bajo los pasos: qué significa el estado actual y qué
     * conviene hacer. Sale de `comercial.contrato.estado_ayuda.*`.
     *
     * @param  list<array{key: string, label: string, tone: string, status: 'completed'|'current'|'next'|'pending'|'blocked', modal: string|null, hint: string|null, icon: string|null}>  $pasos
     */
    public static function ayuda(array $pasos, EstadoContrato $actual): ?string
    {
        return PasosDeEstado::ayuda($pasos, 'comercial.contrato.estado_ayuda', self::destinoDeLaAyuda($actual));
    }

    /**
     * ¿Puede un usuario pedir esta transición desde el panel? La tabla de la
     * máquina, salvo los estados que fija solo el sistema.
     */
    public static function pedible(EstadoContrato $desde, EstadoContrato $hacia): bool
    {
        return $hacia !== EstadoContrato::Borrador
            && $hacia !== EstadoContrato::Conflicto
            && TransicionesContrato::permitida($desde, $hacia);
    }

    /**
     * La fila de cinco pasos. El primero es el de la etapa de aprobación: en
     * un contrato en conflicto ocupa su lugar «En conflicto» —es un
     * `borrador` que choca con otro contrato—, así el estado actual siempre
     * aparece entre los pasos.
     *
     * @return list<EstadoContrato>
     */
    private static function ruta(EstadoContrato $actual): array
    {
        return [
            $actual === EstadoContrato::Conflicto ? EstadoContrato::Conflicto : EstadoContrato::Borrador,
            EstadoContrato::Vigente,
            EstadoContrato::Pausado,
            EstadoContrato::Finalizado,
            EstadoContrato::Cancelado,
        ];
    }

    /**
     * Estados por los que el contrato seguro pasó para llegar al actual. De
     * uno cancelado no se sabe (se cancela desde tres estados), así que no
     * marca ninguno; de uno pausado tampoco cuenta `vigente`, porque volver a
     * él es justo el paso que se ofrece (reanudar).
     *
     * @return list<EstadoContrato>
     */
    private static function recorridos(EstadoContrato $actual): array
    {
        return match ($actual) {
            EstadoContrato::Vigente, EstadoContrato::Pausado => [EstadoContrato::Borrador],
            EstadoContrato::Finalizado => [EstadoContrato::Borrador, EstadoContrato::Vigente],
            EstadoContrato::Borrador, EstadoContrato::Conflicto, EstadoContrato::Cancelado => [],
        };
    }

    /**
     * El paso que nombra el cierre del párrafo («Para avanzar, haz clic en…»).
     * No es el primero accionable: desde «En ejecución» ese sería «Pausado», y
     * el avance natural es «Ejecutado». Desde un contrato en conflicto apunta a
     * «En ejecución», que está bloqueado: el párrafo no invita a hacer clic ahí
     * y deja que el texto del estado explique qué decidir.
     */
    private static function destinoDeLaAyuda(EstadoContrato $actual): ?string
    {
        return match ($actual) {
            EstadoContrato::Borrador, EstadoContrato::Conflicto, EstadoContrato::Pausado => EstadoContrato::Vigente->value,
            EstadoContrato::Vigente => EstadoContrato::Finalizado->value,
            EstadoContrato::Finalizado, EstadoContrato::Cancelado => null,
        };
    }
}
