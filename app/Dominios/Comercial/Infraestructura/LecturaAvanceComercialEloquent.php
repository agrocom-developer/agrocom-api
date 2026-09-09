<?php

namespace App\Dominios\Comercial\Infraestructura;

use App\Dominios\Comercial\Aplicacion\ObtenerAvanceComercial;
use App\Dominios\Comercial\Contratos\DatosAvanceComercial;
use App\Dominios\Comercial\Contratos\LecturaAvanceComercial;

/**
 * Implementación del contrato de lectura de avance comercial. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaContratoEloquent`: esa subcarpeta está reservada a modelos que
 * extienden `ModeloDominio` (`tests/Unit/ArquitecturaModulosTest.php` lo
 * exige) — esta clase no es un modelo, es el adaptador que el
 * `ServiceProvider` del módulo liga a {@see LecturaAvanceComercial}.
 *
 * Delega en `ObtenerAvanceComercial` sin tocar su lógica ni su firma: filtra
 * por el `contratoId` pedido y toma la única fila del resultado.
 */
final class LecturaAvanceComercialEloquent implements LecturaAvanceComercial
{
    public function __construct(private readonly ObtenerAvanceComercial $obtenerAvance) {}

    public function porContrato(int $contratoId): ?DatosAvanceComercial
    {
        $avance = $this->obtenerAvance->ejecutar(contratoId: $contratoId)[0] ?? null;

        if ($avance === null) {
            return null;
        }

        return new DatosAvanceComercial(
            contratoId: $avance['contratoId'],
            clienteNombre: $avance['clienteNombre'],
            hectareasContratadas: $avance['hectareasContratadas'],
            hectareasAplicadas: $avance['hectareasAplicadas'],
            hectareasFacturadas: $avance['hectareasFacturadas'],
            montoFacturado: $avance['montoFacturado'],
        );
    }
}
