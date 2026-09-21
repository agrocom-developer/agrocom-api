<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http;

use App\Dominios\Compartido\Infraestructura\Http\PasosDeEstado;
use App\Dominios\Mantenimiento\Dominio\EstadoOrdenMantenimiento;
use App\Dominios\Mantenimiento\Dominio\MaquinaEstados\TransicionesOrdenMantenimiento;

/**
 * Los pasos de `molecules/step-arrow` de la ficha de una orden de
 * mantenimiento (tarea 116): cómo se dibuja su máquina de estados. Mismo papel
 * que `PasosDeContrato` en Comercial, sobre la máquina más corta del panel.
 *
 * La ruta es de dos pasos —«Abierta → Cerrada»— porque eso es lo que la tabla
 * de transiciones admite: `abierta` es el único estado de alta y `cerrada` no
 * tiene salida (no hay reapertura en este alcance). Un solo permiso gobierna
 * la única transición (`mantenimiento.orden.cerrar`), así que alcanza con
 * `puedeCambiar` y no hace falta el `puedeIrA` por destino de la orden de
 * aplicación.
 *
 * El paso «Cerrada» no cierra nada por sí mismo: abre el modal de la ficha, y
 * el modal envía el formulario de cierre —descripción final y repuestos
 * consumidos— a `panel.ordenes-mantenimiento.cerrar`. Quien aplica la
 * transición, con sus guardas de stock y de descripción, sigue siendo
 * `MaquinaEstadosOrdenMantenimiento` (invariante 7 de CLAUDE.md); la tabla que
 * consulta lo dibujado es la misma que consulta ella.
 *
 * @phpstan-type Paso array{key: string, label: string, tone: string, status: 'completed'|'current'|'next'|'pending'|'blocked', modal: string|null, hint: string|null, icon: string|null}
 */
final class PasosDeOrdenMantenimiento
{
    /**
     * Tono de cada estado, definido UNA vez: lo comparten el badge del
     * listado, los pasos de la ficha y el modal de cierre, para que los tres
     * hablen con el mismo color. Son los mismos tonos con los que el listado
     * ya venía pintando el badge — no se reeligen.
     *
     * @var array<string, string>
     */
    public const array TONO_POR_ESTADO = [
        'abierta' => 'neutral',
        'cerrada' => 'success',
    ];

    public const string PREFIJO_MODAL = 'orden-mantenimiento-estado-modal';

    /**
     * @param  bool  $puedeCerrar  si el rol activo tiene `mantenimiento.orden.cerrar`.
     * @return list<Paso>
     */
    public static function armar(EstadoOrdenMantenimiento $actual, bool $puedeCerrar): array
    {
        return PasosDeEstado::armar(
            ruta: [EstadoOrdenMantenimiento::Abierta, EstadoOrdenMantenimiento::Cerrada],
            actual: $actual,
            permitida: TransicionesOrdenMantenimiento::permitida(...),
            tonos: self::TONO_POR_ESTADO,
            claveEtiqueta: 'mantenimiento.orden.estado',
            prefijoModal: self::PREFIJO_MODAL,
            puedeCambiar: $puedeCerrar,
        );
    }

    /**
     * Párrafo de apoyo bajo los pasos: qué significa el estado actual y qué
     * pasa al cerrar la orden. Sale de `mantenimiento.orden.estado_ayuda.*`.
     *
     * A diferencia del trabajo, acá los cierres genéricos de `PasosDeEstado`
     * dicen la verdad: un paso sin acción lo está por el permiso del rol, que
     * es exactamente lo que `ui.pasos.ayuda_sin_permiso` explica.
     *
     * @param  list<Paso>  $pasos  los que devolvió {@see self::armar()}.
     */
    public static function ayuda(array $pasos): ?string
    {
        return PasosDeEstado::ayuda($pasos, 'mantenimiento.orden.estado_ayuda');
    }

    /**
     * El `id` del modal que abre el paso al estado `$destino`, para que la
     * ficha y el listado nombren el mismo sin repetir la interpolación.
     */
    public static function modal(EstadoOrdenMantenimiento $destino): string
    {
        return self::PREFIJO_MODAL.'-'.$destino->value;
    }
}
