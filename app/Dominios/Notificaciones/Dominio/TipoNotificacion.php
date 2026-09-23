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
    case ContratoCreado = 'contrato_creado';
    case OrdenTrabajoCreada = 'orden_trabajo_creada';
    case TrabajoCerrado = 'trabajo_cerrado';

    /** Nombre del ícono de Material Symbols que rotula el aviso en la campana. */
    public function icono(): string
    {
        return match ($this) {
            self::ContratoCreado => 'description',
            self::OrdenTrabajoCreada => 'assignment',
            self::TrabajoCerrado => 'task_alt',
        };
    }

    /** Clave de `lang/es/notificaciones.php` del texto del aviso. */
    public function claveDeTitulo(): string
    {
        return "notificaciones.titulo.{$this->value}";
    }
}
