<?php

namespace App\Dominios\Notificaciones\Dominio;

/**
 * Catálogo cerrado de avisos que el motor sabe repartir (ADR 0025). Cada
 * valor es un hecho de negocio con su propia regla en `Aplicacion/Reglas/`;
 * el valor viaja tal cual a `ntf_notificaciones.tipo` (con su CHECK en
 * Postgres) y arma la clave del texto: un aviso nuevo es un caso acá, una
 * regla y una línea en `lang/es/notificaciones.php`.
 *
 * El texto NO se guarda armado: se guardan los `parametros` y se traduce al
 * mirar, así el aviso sigue el idioma de quien lo lee.
 */
enum TipoNotificacion: string
{
    case TrabajoCerrado = 'trabajo_cerrado';
    case SujetoAprobado = 'sujeto_aprobado';
    case SujetoRechazado = 'sujeto_rechazado';
    case SujetoSuspendido = 'sujeto_suspendido';
    case SujetoRehabilitado = 'sujeto_rehabilitado';
    case SujetoDadoDeBaja = 'sujeto_dado_de_baja';
    case SujetoReenviado = 'sujeto_reenviado';
    case DocumentoPorVencer = 'documento_por_vencer';
    case DocumentoVencido = 'documento_vencido';
    case ProveedorRegistrado = 'proveedor_registrado';
    case OfertaCreada = 'oferta_creada';
    case OfertaVencida = 'oferta_vencida';
    case OfertaRetirada = 'oferta_retirada';
    case SolicitudAsignada = 'solicitud_asignada';
    case SolicitudReasignada = 'solicitud_reasignada';
    case SolicitudSinProveedor = 'solicitud_sin_proveedor';
    case ProveedorSancionado = 'proveedor_sancionado';
    case SolicitudCompletada = 'solicitud_completada';
    case CalificacionRegistrada = 'calificacion_registrada';
    case CalificacionReportada = 'calificacion_reportada';
    case DisputaAbierta = 'disputa_abierta';
    case DisputaResuelta = 'disputa_resuelta';
    case DisputaApelada = 'disputa_apelada';
    case ClienteConAviso = 'cliente_con_aviso';
    case PublicacionLimitada = 'publicacion_limitada';
    case EquipoEnCamino = 'equipo_en_camino';
    case SolicitudEjecutada = 'solicitud_ejecutada';
    case NuevaSolicitudAbierta = 'nueva_solicitud_abierta';
    case OfertaRecibida = 'oferta_recibida';
    case PrecioCambiado = 'precio_cambiado';
    case SolicitudAdjudicada = 'solicitud_adjudicada';
    case SolicitudReabierta = 'solicitud_reabierta';
    case SolicitudExpirada = 'solicitud_expirada';
    case EjecucionIniciada = 'ejecucion_iniciada';
    case AvanceRegistrado = 'avance_registrado';
    case SolicitudFinalizada = 'solicitud_finalizada';
    case MensajeNuevo = 'mensaje_nuevo';

    /** Nombre del ícono de Material Symbols que rotula el aviso en la campana. */
    public function icono(): string
    {
        return match ($this) {
            self::TrabajoCerrado => 'task_alt',
            self::SujetoAprobado, self::SujetoRehabilitado => 'verified',
            self::SujetoRechazado => 'cancel',
            self::SujetoSuspendido, self::SujetoDadoDeBaja => 'block',
            self::SujetoReenviado, self::ProveedorRegistrado => 'pending_actions',
            self::DocumentoPorVencer => 'event_upcoming',
            self::DocumentoVencido => 'event_busy',
            self::OfertaCreada => 'campaign',
            self::OfertaVencida, self::OfertaRetirada => 'timer_off',
            self::SolicitudAsignada => 'handshake',
            self::SolicitudReasignada => 'sync_alt',
            self::SolicitudSinProveedor => 'person_off',
            self::ProveedorSancionado => 'gavel',
            self::SolicitudCompletada => 'task_alt',
            self::CalificacionRegistrada => 'star',
            self::CalificacionReportada => 'flag',
            self::DisputaAbierta => 'report',
            self::DisputaResuelta => 'balance',
            self::DisputaApelada => 'undo',
            self::ClienteConAviso, self::PublicacionLimitada => 'person_alert',
            self::EquipoEnCamino => 'local_shipping',
            self::SolicitudEjecutada => 'fact_check',
            self::NuevaSolicitudAbierta => 'map',
            self::OfertaRecibida, self::PrecioCambiado => 'request_quote',
            self::SolicitudAdjudicada => 'handshake',
            self::SolicitudReabierta => 'sync_alt',
            self::SolicitudExpirada => 'timer_off',
            self::EjecucionIniciada => 'flight_takeoff',
            self::AvanceRegistrado => 'photo_camera',
            self::SolicitudFinalizada => 'task_alt',
            self::MensajeNuevo => 'chat',
        };
    }

    /** Clave de `lang/es/notificaciones.php` del texto del aviso. */
    public function claveDeTitulo(): string
    {
        return "notificaciones.titulo.{$this->value}";
    }
}
