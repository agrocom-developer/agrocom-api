<?php

namespace App\Dominios\Disputas\Aplicacion;

/**
 * Los parámetros de la política de disputas (ADR 0016 y 0039), leídos de
 * `config/disputas.php` cada vez que se piden.
 */
final class ParametrosDeDisputa
{
    public function aperturaClienteHoras(): int
    {
        return max(1, (int) config('disputas.apertura_cliente_horas', 72));
    }

    public function aperturaProveedorDias(): int
    {
        return max(1, (int) config('disputas.apertura_proveedor_dias', 30));
    }

    public function respuestaHoras(): int
    {
        return max(1, (int) config('disputas.respuesta_horas', 48));
    }

    public function apelacionDias(): int
    {
        return max(1, (int) config('disputas.apelacion_dias', 7));
    }

    public function textoMax(): int
    {
        return max(1, (int) config('disputas.texto_max', 2000));
    }
}
