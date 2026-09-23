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
    case DiasEnHacienda = 'dias_en_hacienda';
    case Stock = 'stock';
    case AvanceClientes = 'avance_clientes';
    case Alertas = 'alertas';
    case MisSesiones = 'mis_sesiones';
    case MisEquipos = 'mis_equipos';
    case MiLiquidacion = 'mi_liquidacion';
    case EstadoCuentas = 'estado_cuentas';
    case TrabajosPorEquipo = 'trabajos_por_equipo';
    case ProgresoCampania = 'progreso_campania';
    case EstadoOrdenesAplicacion = 'estado_ordenes_aplicacion';
    case ResumenVuelos = 'resumen_vuelos';
    case RecursosEnUso = 'recursos_en_uso';
    case OrdenesTrabajoPorCuadrilla = 'ordenes_trabajo_por_cuadrilla';
    case OrdenesConEquipamiento = 'ordenes_con_equipamiento';
    case UsuariosPorRol = 'usuarios_por_rol';
    case DispositivosConSesion = 'dispositivos_con_sesion';
    case VersionesApk = 'versiones_apk';
    case BitacoraReciente = 'bitacora_reciente';
    case AccesoConfiguracion = 'acceso_configuracion';
    case AccesoOrganizacion = 'acceso_organizacion';

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
            self::Multimedia,
            // Mismo permiso que ResumenPorLote: son la MISMA data de
            // trabajos (Operaciones), agrupada distinto — por equipo en vez
            // de por lote, y totalizada en vez de fila por fila.
            self::TrabajosPorEquipo,
            self::ProgresoCampania,
            // Mismo permiso que HectareasPorDia: es la MISMA serie de
            // hectáreas validadas, con selector de granularidad (tarea 136).
            self::ResumenVuelos,
            // Las Órdenes de Trabajo agrupadas por cuadrilla (tarea 138): el
            // permiso de su pantalla, `/panel/trabajos`.
            self::OrdenesTrabajoPorCuadrilla => 'operaciones.trabajo.ver',
            self::ColaValidacion => 'operaciones.sesion.validar',
            self::Pausas => 'operaciones.pausa.ver',
            self::DiasEnHacienda => 'operaciones.estadia.ver',
            self::Stock => 'inventario.movimiento.ver',
            // Mismo permiso que AvanceClientes: ambas resumen contratos de
            // Comercial, una en hectáreas y otra en plata.
            self::AvanceClientes,
            self::EstadoCuentas => 'comercial.contrato.ver',
            self::Alertas => 'operaciones.alerta.ver',
            // El permiso de la pantalla de órdenes de aplicación, que es la
            // que este donut resume (tarea 136).
            self::EstadoOrdenesAplicacion,
            // La misma lista de órdenes con las haciendas y el equipamiento
            // que le corresponde a cada una (tarea 138).
            self::OrdenesConEquipamiento => 'operaciones.orden.ver',
            // Qué lleva cada cuadrilla al campo — sus integrantes y sus
            // recursos — es el contenido de la pantalla de cuadrillas, y con
            // su permiso se gatea (tarea 138).
            self::RecursosEnUso => 'personal.equipo_trabajo.ver',
            // Las seis del administrador de plataforma (tarea 139): cada una
            // con el permiso de la pantalla que resume o a la que lleva.
            self::UsuariosPorRol => 'seguridad.usuario.ver',
            self::DispositivosConSesion => 'seguridad.dispositivo.ver',
            self::VersionesApk => 'distribucion.version.autorizar',
            self::BitacoraReciente => 'seguridad.bitacora.ver',
            self::AccesoConfiguracion => 'seguridad.configuracion.ver',
            self::AccesoOrganizacion => 'seguridad.organizacion.ver',
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
