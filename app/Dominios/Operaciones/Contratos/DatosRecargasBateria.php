<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Lo que Operaciones sabe de una batería, en números, para el resumen
 * relacionado de su ficha en `Mantenimiento` (ADR 0003, regla 2): cuántas
 * recargas la sacaron de servicio y cuántas de ellas registraron una
 * temperatura por encima del umbral. Es la misma señal que alimenta la alerta
 * de retiro (`LecturaAlertasTemperaturaBateria`), pero contada.
 */
final readonly class DatosRecargasBateria
{
    public function __construct(
        public int $total,
        public int $conAlertaTemperatura,
    ) {}
}
