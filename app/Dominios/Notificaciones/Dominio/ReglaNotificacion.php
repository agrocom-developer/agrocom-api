<?php

namespace App\Dominios\Notificaciones\Dominio;

/**
 * Una regla por evento de dominio: dice qué aviso genera y a quién, y nada
 * más. No toca la base ni conoce cuentas — recibe el evento (un DTO de
 * primitivos del módulo emisor) y devuelve una {@see NotificacionArmada}.
 *
 * Vive en `Notificaciones` y no en el emisor a propósito (ADR 0025 punto 4):
 * «a quién le importa esto» es política de aviso, y los módulos de negocio no
 * tienen por qué saber que existe una campana.
 */
interface ReglaNotificacion
{
    /** @return class-string el evento de dominio que esta regla atiende */
    public function evento(): string;

    public function armar(object $evento): NotificacionArmada;
}
