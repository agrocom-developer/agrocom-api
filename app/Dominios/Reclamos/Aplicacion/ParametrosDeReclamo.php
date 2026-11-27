<?php

namespace App\Dominios\Reclamos\Aplicacion;

/**
 * Los parámetros de la política de reclamos (ADR 0016 y 0039), leídos de
 * `config/reclamos.php` cada vez que se piden.
 */
final class ParametrosDeReclamo
{
    public function aperturaClienteHoras(): int
    {
        return max(1, (int) config('reclamos.apertura_cliente_horas', 72));
    }

    public function aperturaProveedorDias(): int
    {
        return max(1, (int) config('reclamos.apertura_proveedor_dias', 30));
    }

    public function respuestaHoras(): int
    {
        return max(1, (int) config('reclamos.respuesta_horas', 48));
    }

    public function apelacionDias(): int
    {
        return max(1, (int) config('reclamos.apelacion_dias', 7));
    }

    public function textoMax(): int
    {
        return max(1, (int) config('reclamos.texto_max', 2000));
    }
}
