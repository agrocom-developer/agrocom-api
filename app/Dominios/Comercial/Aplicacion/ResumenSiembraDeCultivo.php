<?php

namespace App\Dominios\Comercial\Aplicacion;

/**
 * Dónde está sembrado un cultivo hoy, para el resumen relacionado de su
 * ficha de edición (tarea 120). Lo arma {@see ResumirSiembraDeCultivo}.
 *
 * `hectareasSembradas` y `hectareas` de cada propiedad viajan como string
 * `DECIMAL` de escala 2 (invariante 6): la vista solo las formatea.
 *
 * `lotesSembrados` cuenta siembras (un lote en una campaña abierta), no lotes
 * distintos: con una sola campaña abierta —lo habitual— es lo mismo, y con
 * dos, cada siembra es un hecho aparte que suma sus propias hectáreas.
 */
final readonly class ResumenSiembraDeCultivo
{
    /**
     * @param  list<string>  $campanias  códigos de las campañas abiertas (vacía si no hay ninguna)
     * @param  list<array{nombre: string, lotes: int, hectareas: string}>  $propiedades  de más a menos hectáreas
     */
    public function __construct(
        public array $campanias,
        public int $lotesSembrados,
        public string $hectareasSembradas,
        public array $propiedades,
    ) {}

    public function hayCampania(): bool
    {
        return $this->campanias !== [];
    }

    public function tieneSiembra(): bool
    {
        return $this->lotesSembrados > 0;
    }
}
