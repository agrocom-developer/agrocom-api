<?php

namespace App\Dominios\Notificaciones\Contratos;

/**
 * El estado de lectura de UNA cuenta sobre las alertas técnicas, por id de
 * alerta: las que abrió (`leidas`) y las que limpió de la campana
 * (`limpiadas`). Una limpiada sigue en su pantalla: solo sale de la campana.
 */
final readonly class AlertasDeCuenta
{
    /**
     * @param  list<int>  $leidas
     * @param  list<int>  $limpiadas
     */
    public function __construct(
        public array $leidas = [],
        public array $limpiadas = [],
    ) {}

    public function leyo(int $alertaId): bool
    {
        return in_array($alertaId, $this->leidas, true);
    }
}
