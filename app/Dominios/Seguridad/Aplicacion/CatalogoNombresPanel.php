<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Personal\Contratos\LecturaPanelPersonal;

/**
 * Resuelve los NOMBRES que el dashboard necesita y que ningún módulo puede
 * dar solo: `Operaciones` sabe que la sesión 7 es del piloto 5, pero la
 * persona es de `Personal` (ADR 0003, regla
 * 3 — las FK cruzan como enteros pelados, sin relación Eloquent).
 *
 * Una sola responsabilidad: traducir ids a nombres, una vez por request y en
 * lote. Se instancia por request y cachea en memoria — el dashboard pide el
 * nombre de la misma persona en varias tablas, y sin esto serían consultas
 * idénticas.
 */
final class CatalogoNombresPanel
{
    /** @var array<int, string> */
    private array $personas = [];

    /** @var array<int, string> */
    private array $bases = [];

    public function __construct(private readonly LecturaPanelPersonal $personal) {}

    /**
     * Precarga los nombres de varias personas de una sola consulta. Se llama
     * antes de recorrer una lista de sesiones: pedirlos de a uno dentro del
     * bucle es el N+1 que este catálogo existe para evitar.
     *
     * @param  list<int>  $ids
     */
    public function precargarPersonas(array $ids): void
    {
        $faltantes = array_values(array_diff(array_unique($ids), array_keys($this->personas)));

        if ($faltantes !== []) {
            $this->personas += $this->personal->nombresDePersonas($faltantes);
        }
    }

    public function persona(?int $personaId): ?string
    {
        return $personaId === null ? null : ($this->personas[$personaId] ?? null);
    }

    /** @param  list<int>  $ids */
    public function precargarBases(array $ids): void
    {
        $faltantes = array_values(array_diff(array_unique($ids), array_keys($this->bases)));

        if ($faltantes !== []) {
            $this->bases += $this->personal->nombresDeBases($faltantes);
        }
    }

    public function base(?int $baseId): ?string
    {
        return $baseId === null ? null : ($this->bases[$baseId] ?? null);
    }
}
