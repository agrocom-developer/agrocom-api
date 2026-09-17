<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use App\Dominios\Comercial\Aplicacion\Contrato\VerificadorLotesDelContrato;
use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Pedido del dueño (tarea "contratos-lotes", 16/9/2026): un contrato no
 * puede agregar lotes de una propiedad que YA tiene el 100% de sus lotes
 * activos cubiertos por OTROS contratos `vigente` de la MISMA campaña — de
 * lo contrario dos contratos vigentes terminarían disputándose la misma
 * superficie dentro del mismo ciclo productivo. La verifica
 * {@see VerificadorLotesDelContrato::propiedadAgotada()},
 * contando lotes activos de la propiedad (`com_lotes`) contra los ya
 * ocupados en `com_contrato_lotes` de contratos ajenos al que se está
 * creando/editando — mismo criterio que {@see CampaniaCerrada}: guarda de
 * negocio cruzando tablas, no expresable en un `CHECK`/`UNIQUE INDEX` (ver
 * el docblock de la migración `create_com_contrato_lotes_table`).
 */
final class LotesDePropiedadAgotados extends RuntimeException
{
    public static function paraPropiedad(string $nombre): self
    {
        return new self(Texto::de('comercial.errores.propiedad_lotes_agotados', ['nombre' => $nombre]));
    }
}
