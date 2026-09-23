<?php

namespace App\Dominios\Notificaciones\Dominio;

/**
 * A qué recurso lleva el click de un aviso (`ntf_notificaciones.recurso_tipo`
 * + `recurso_id`). Solo nombra el recurso: la URL no se guarda ni se decide
 * acá — se resuelve al abrir el aviso, contra el rol activo de quien lo abre
 * (`Infraestructura/DestinoDeNotificacion`, ADR 0025 punto 6).
 */
enum RecursoNotificable: string
{
    case Contrato = 'contrato';
    case OrdenTrabajo = 'orden_trabajo';
    case Trabajo = 'trabajo';
}
