<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use App\Dominios\Comercial\Aplicacion\Contrato\VerificadorLotesDelContrato;
use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Pedido del dueño (18/9/2026, ADR 0021): un contrato no puede agregar un
 * lote que YA está retenido por otro contrato `vigente` o `pausado` de la
 * MISMA campaña — dos contratos no se disputan la misma superficie dentro del
 * mismo ciclo productivo. La verifica
 * {@see VerificadorLotesDelContrato::lotesOcupados()}, lote por lote. Reemplaza
 * a la guarda anterior, que solo saltaba cuando el 100% de los lotes de una
 * PROPIEDAD ya estaba cubierto (`LotesDePropiedadAgotados`, retirada).
 *
 * Mismo criterio que {@see CampaniaNoAbierta}: guarda de negocio cruzando
 * tablas, no expresable en un `CHECK`/`UNIQUE INDEX` (ver el docblock de la
 * migración `create_com_contrato_lotes_table`).
 */
final class LotesYaContratados extends RuntimeException
{
    /**
     * @param  non-empty-list<array{lote_id: int, codigo: string, contrato_id: int}>  $ocupados
     */
    public static function paraLotes(array $ocupados): self
    {
        if (count($ocupados) === 1) {
            return new self(Texto::de('comercial.errores.lote_ya_contratado', [
                'codigo' => $ocupados[0]['codigo'],
                'contrato' => $ocupados[0]['contrato_id'],
            ]));
        }

        return new self(Texto::de('comercial.errores.lotes_ya_contratados', [
            'codigos' => implode(', ', array_column($ocupados, 'codigo')),
        ]));
    }
}
