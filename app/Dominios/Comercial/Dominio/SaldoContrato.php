<?php

namespace App\Dominios\Comercial\Dominio;

/**
 * Filtro "saldo" del informe de avance de contratos (HU-52, tarea 75, espec
 * §9.1). Partición sin solape sobre hectáreas aplicadas vs. contratadas —
 * decisión de esta tarea, no explícita letra por letra en la especificación:
 *
 * - `Pendiente`: no se aplicó nada todavía (aplicadas = 0).
 * - `AAplicar`: en curso, ni arrancó en cero ni llegó a lo contratado.
 * - `Cumplido`: aplicadas >= contratadas (incluye el excedente de más del
 *   100%, que sigue siendo "cumplido" a efectos de este filtro).
 */
enum SaldoContrato: string
{
    case Pendiente = 'pendiente';
    case AAplicar = 'a_aplicar';
    case Cumplido = 'cumplido';
}
