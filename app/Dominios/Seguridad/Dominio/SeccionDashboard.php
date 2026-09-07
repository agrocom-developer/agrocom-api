<?php

namespace App\Dominios\Seguridad\Dominio;

/**
 * Las secciones que puede tener el dashboard y el permiso que habilita cada
 * una (tarea 67).
 *
 * Existe para que "qué ve cada rol" sea UNA tabla y no una constelación de
 * `@if` en Blade: el rol activo no elige una plantilla distinta, elige un
 * subconjunto de estas secciones. Un dueño las ve casi todas, un piloto ve
 * dos, y ninguno de los dos tiene una vista propia que mantener en paralelo.
 *
 * El permiso de cada sección es EL MISMO que gatea su pantalla completa en
 * el menú: si un rol no puede entrar a `/panel/pausas`, tampoco ve el
 * resumen de pausas acá. Lo contrario sería una fuga por la ventana del
 * dashboard, que es exactamente el hallazgo que cerró la tarea 62.
 *
 * `requierePersona`: las secciones "mías" (mis sesiones, mi liquidación)
 * necesitan que la cuenta tenga `persona_id`. Un usuario administrativo sin
 * persona asociada no las ve — no son un dato de la empresa, son su recibo.
 */
enum SeccionDashboard: string
{
    case Mapa = 'mapa';
    case DistribucionSesiones = 'distribucion_sesiones';
    case HectareasPorDia = 'hectareas_por_dia';
    case ColaValidacion = 'cola_validacion';
    case ResumenPorLote = 'resumen_por_lote';
    case Multimedia = 'multimedia';
    case Pausas = 'pausas';
    case Stock = 'stock';
    case AvanceClientes = 'avance_clientes';
    case Alertas = 'alertas';
    case MisSesiones = 'mis_sesiones';
    case MisEquipos = 'mis_equipos';
    case MiLiquidacion = 'mi_liquidacion';

    /**
     * El mapa se gatea con `operaciones.trabajo.ver` y no con
     * `comercial.campo.ver`, aunque dibuje geometría de `com_lotes`: lo que
     * muestra es DÓNDE se está fumigando, no la ficha administrativa del
     * campo. Con el permiso comercial, el jefe de campo —que es quien más lo
     * necesita— se quedaba sin la única vista geográfica del panel.
     */
    public function permiso(): ?string
    {
        return match ($this) {
            self::Mapa,
            self::DistribucionSesiones,
            self::HectareasPorDia,
            self::ResumenPorLote,
            self::Multimedia => 'operaciones.trabajo.ver',
            self::ColaValidacion => 'operaciones.sesion.validar',
            self::Pausas => 'operaciones.pausa.ver',
            self::Stock => 'inventario.movimiento.ver',
            self::AvanceClientes => 'comercial.contrato.ver',
            self::Alertas => 'operaciones.alerta.ver',
            self::MiLiquidacion => 'finanzas.devengo.ver',
            // Sin permiso propio: son las sesiones y los drones de quien
            // mira, derivados de su `persona_id`. No hay nada que gatear que
            // `requierePersona()` no gatee ya.
            self::MisSesiones, self::MisEquipos => null,
        };
    }

    public function requierePersona(): bool
    {
        return in_array($this, [self::MisSesiones, self::MisEquipos, self::MiLiquidacion], true);
    }
}
