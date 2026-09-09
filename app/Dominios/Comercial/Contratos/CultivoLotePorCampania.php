<?php

namespace App\Dominios\Comercial\Contratos;

/**
 * Forma de dato primitiva de una siembra para quien necesita agrupar por
 * cultivo sin importar los modelos Eloquent `LoteCampania`/`Lote`/`Cultivo`
 * (ADR 0003, regla 2): la entrada obligatoria del informe de avance de
 * HU-48 (tarea 75) es el cultivo, y hoy esa lectura no tiene forma de
 * hacerse desde fuera de `Comercial` sin tocar sus modelos.
 *
 * `hectareasSembradas` viaja como string (invariante 6).
 */
final readonly class CultivoLotePorCampania
{
    public function __construct(
        public int $loteId,
        public string $loteCodigo,
        public int $cultivoId,
        public string $cultivoNombre,
        public string $hectareasSembradas,
    ) {}
}
