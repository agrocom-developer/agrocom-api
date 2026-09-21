<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de Operaciones hacia `Mantenimiento` (ADR 0003, regla
 * 2): la ficha de una batería muestra, en su resumen relacionado, cuántas
 * recargas tuvo y cuántas con alerta de temperatura, sin importar el modelo
 * `Recarga`. Hermano de {@see LecturaAlertasTemperaturaBateria}, que solo
 * responde si hubo alguna; éste cuenta.
 *
 * El cruce es por IGUALDAD DE TEXTO entre `man_baterias.identificador` y
 * `ope_recargas.bateria_saliente_id`, NUNCA por id (ver el docblock de
 * {@see LecturaAlertasTemperaturaBateria} para el porqué): por eso el
 * contrato recibe el identificador y no un id.
 */
interface LecturaRecargasPorBateria
{
    /**
     * Recargas (no borradas) cuyo `bateria_saliente_id` coincide, como texto
     * exacto, con `$identificador`. Sin recargas, todo en cero.
     */
    public function deBateria(string $identificador): DatosRecargasBateria;
}
