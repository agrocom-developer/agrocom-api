<?php

namespace App\Dominios\Compartido\Dominio;

/**
 * Estado de aprobación de un proveedor, piloto o dron (especificación
 * del marketplace §5.2, ADR 0032). Es el mismo ciclo para los tres sujetos:
 * solo un administrador aprueba, suspende o da de baja. Vive en `Compartido`
 * porque lo usan `Proveedores` (proveedor, piloto) y `Flota` (dron)
 * sin importarse entre sí.
 */
enum EstadoAprobacion: string
{
    case Pendiente = 'pendiente';
    case Aprobado = 'aprobado';
    case Suspendido = 'suspendido';
    case Rechazado = 'rechazado';
    case Baja = 'baja';

    /** `baja` es terminal: ningún sujeto vuelve de una baja. */
    public function esTerminal(): bool
    {
        return $this === self::Baja;
    }

    /** Si el sujeto puede operar (recibir ofertas, entrar a un equipo). */
    public function puedeOperar(): bool
    {
        return $this === self::Aprobado;
    }

    /** Tono del badge del panel (`atoms/badge`). */
    public function tono(): string
    {
        return match ($this) {
            self::Pendiente => 'warning',
            self::Aprobado => 'success',
            self::Suspendido => 'danger',
            self::Rechazado => 'danger',
            self::Baja => 'neutral',
        };
    }
}
