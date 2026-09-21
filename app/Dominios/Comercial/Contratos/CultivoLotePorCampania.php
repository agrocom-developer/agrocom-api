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
 *
 * `campaniaId` y `etapa` (21/9/2026): la orden de aplicación muestra, por
 * lote, qué cultivo tiene en la campaña de su contrato y en qué etapa está.
 * `etapa` es el valor de `Dominio\EtapaCultivo` como string plano —quien lo
 * muestra lo traduce con `comercial.siembra.etapa_opcion.*`— o `null` si no
 * se registró.
 */
final readonly class CultivoLotePorCampania
{
    public function __construct(
        public int $loteId,
        public string $loteCodigo,
        public int $cultivoId,
        public string $cultivoNombre,
        public string $hectareasSembradas,
        public int $campaniaId = 0,
        public ?string $etapa = null,
    ) {}
}
