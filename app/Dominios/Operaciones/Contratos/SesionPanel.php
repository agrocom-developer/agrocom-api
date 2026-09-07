<?php

namespace App\Dominios\Operaciones\Contratos;

use App\Dominios\Comercial\Contratos\LecturaPanelComercial;
use App\Dominios\Operaciones\Dominio\TonoEstadoSesion;
use App\Dominios\Personal\Contratos\LecturaPersonas;

/**
 * Forma primitiva de una sesión para el panel (ADR 0003, regla 2): el
 * consumidor —`Seguridad`, que arma el dashboard— nunca recibe el modelo
 * Eloquent `Sesion`. `hectareas` viaja como string decimal (invariante 6).
 *
 * `loteId` y `pilotoId` viajan como ENTEROS PELADOS, sin nombre: el lote es
 * de `Comercial` y la persona de `Personal`, y este módulo solo guarda la FK
 * (ADR 0003, regla 3 — cero relaciones Eloquent cruzando módulos). Quien
 * compone el dashboard los cruza con
 * {@see LecturaPanelComercial} y
 * {@see LecturaPersonas}. `dronCodigo` sí
 * viaja resuelto: `ope_drones` es tabla propia.
 *
 * `minutosVuelo` es `fin - inicio` ya calculado: la sesión abierta lo trae
 * en `null` porque todavía no terminó, no porque duró cero.
 *
 * `tono` es el vocabulario visual compartido del panel, derivado del estado
 * en un solo lugar ({@see TonoEstadoSesion})
 * para que ni la vista ni el mapa vuelvan a mapear estados a colores.
 */
final readonly class SesionPanel
{
    public function __construct(
        public int $id,
        public int $trabajoId,
        public int $loteId,
        public int $pilotoId,
        public string $estado,
        public string $tono,
        public string $hectareas,
        public string $inicio,
        public ?string $fin,
        public ?string $dronCodigo,
        public bool $tieneCapturaRc,
        public bool $anulada,
        public ?string $litrosConsumidos,
        public ?int $minutosVuelo,
    ) {}
}
