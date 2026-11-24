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
        };
    }

    /** Clave de `lang/es/notificaciones.php` del texto del aviso. */
    public function claveDeTitulo(): string
    {
        return "notificaciones.titulo.{$this->value}";
    }
}
