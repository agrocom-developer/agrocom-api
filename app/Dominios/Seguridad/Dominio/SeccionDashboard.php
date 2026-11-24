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
 * `requierePersona`: las secciones "mías" (mis sesiones, mis equipos)
 * necesitan que la cuenta tenga `persona_id`. Un usuario administrativo sin
 * persona asociada no las ve — no son un dato de la empresa, son los suyos.
 */
enum SeccionDashboard: string
{
    case SolicitudesPorEstado = 'solicitudes_por_estado';
    case DistribucionSesiones = 'distribucion_sesiones';
    case HectareasPorDia = 'hectareas_por_dia';
    case ColaValidacion = 'cola_validacion';
    case Multimedia = 'multimedia';
    case Pausas = 'pausas';
    case Alertas = 'alertas';
    case MisSesiones = 'mis_sesiones';
    case MisEquipos = 'mis_equipos';
    case ResumenVuelos = 'resumen_vuelos';
    case UsuariosPorRol = 'usuarios_por_rol';
    case DispositivosConSesion = 'dispositivos_con_sesion';
    case VersionesApk = 'versiones_apk';
    case BitacoraReciente = 'bitacora_reciente';
    case AccesoConfiguracion = 'acceso_configuracion';
    case AccesoOrganizacion = 'acceso_organizacion';

    public function permiso(): ?string
    {
        return match ($this) {
            // Las solicitudes por estado resumen la pantalla de solicitudes
            // (ADR 0027): con su permiso de listado se gatea.
            self::SolicitudesPorEstado => 'solicitudes.solicitud.ver',
            self::DistribucionSesiones,
            self::HectareasPorDia,
            self::Multimedia,
            // Mismo permiso que HectareasPorDia: es la MISMA serie de
            // hectáreas validadas, con selector de granularidad (tarea 136).
            self::ResumenVuelos => 'operaciones.trabajo.ver',
            self::ColaValidacion => 'operaciones.sesion.validar',
            self::Pausas => 'operaciones.pausa.ver',
            self::Alertas => 'operaciones.alerta.ver',
            // Las seis del administrador de plataforma (tarea 139): cada una
            // con el permiso de la pantalla que resume o a la que lleva.
            self::UsuariosPorRol => 'seguridad.usuario.ver',
            self::DispositivosConSesion => 'seguridad.dispositivo.ver',
            self::VersionesApk => 'distribucion.version.autorizar',
            self::BitacoraReciente => 'seguridad.bitacora.ver',
            self::AccesoConfiguracion => 'seguridad.configuracion.ver',
            self::AccesoOrganizacion => 'seguridad.organizacion.ver',
            // Sin permiso propio: son las sesiones y los drones de quien
            // mira, derivados de su `persona_id`. No hay nada que gatear que
            // `requierePersona()` no gatee ya.
            self::MisSesiones, self::MisEquipos => null,
        };
    }

    public function requierePersona(): bool
    {
        return in_array($this, [self::MisSesiones, self::MisEquipos], true);
    }
}
