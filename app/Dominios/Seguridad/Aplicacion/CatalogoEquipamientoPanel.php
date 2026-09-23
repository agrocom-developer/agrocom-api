<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Mantenimiento\Contratos\LecturaEquipamiento;
use App\Dominios\Mantenimiento\Contratos\LecturaEstadoEquipamiento;
use App\Dominios\Operaciones\Contratos\LecturaDrones;
use App\Dominios\Personal\Contratos\DatosRecursoEquipo;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;

/**
 * Resuelve el EQUIPAMIENTO de una cuadrilla para el dashboard (tarea 138): qué
 * recursos tiene asignados hoy, cómo se llaman y en qué estado están. Ningún
 * módulo lo tiene entero — la asignación es de `Personal`, el dron es de
 * `Operaciones` y el vehículo, el generador, las baterías y el estado de todos
 * son de `Mantenimiento` (ADR 0003, regla 3) —, así que se arma acá, cada
 * parte por su contrato.
 *
 * Mismo criterio que {@see CatalogoNombresPanel}: se instancia por request,
 * cachea en memoria y trabaja en lote. El dashboard muestra el mismo
 * equipamiento en la pestaña de recursos y en la de órdenes, y sin esto serían
 * dos rondas de consultas idénticas.
 */
final class CatalogoEquipamientoPanel
{
    /** Orden en que se listan los recursos de una cuadrilla: lo que vuela primero. */
    private const TIPOS = [
        LecturaEstadoEquipamiento::TIPO_DRON,
        LecturaEquipamiento::TIPO_VEHICULO,
        LecturaEquipamiento::TIPO_GENERADOR,
        LecturaEquipamiento::TIPO_BATERIA,
    ];

    /** @var array<int, list<array{tipo: string, tipoEtiqueta: string, identificador: string, estado: string, etiquetaEstado: string, tono: string, operativo: bool}>> */
    private array $porEquipo = [];

    public function __construct(
        private readonly LecturaEquipoTrabajo $cuadrillas,
        private readonly LecturaEquipamiento $equipamiento,
        private readonly LecturaDrones $drones,
        private readonly LecturaEstadoEquipamiento $estados,
    ) {}

    /**
     * Carga el equipamiento vigente HOY de varias cuadrillas de una vez: un
     * recurso que ya no está asignado (`hasta` pasada) no figura.
     *
     * @param  list<int>  $equipoTrabajoIds
     */
    public function precargar(array $equipoTrabajoIds): void
    {
        $faltantes = array_values(array_diff(array_unique($equipoTrabajoIds), array_keys($this->porEquipo)));

        if ($faltantes === []) {
            return;
        }

        $hoy = today()->toDateString();

        /** @var array<int, list<DatosRecursoEquipo>> $asignados */
        $asignados = [];
        /** @var array<string, list<int>> $idsPorTipo */
        $idsPorTipo = [];

        foreach ($faltantes as $equipoId) {
            $asignados[$equipoId] = array_values(array_filter(
                $this->cuadrillas->recursosAFecha($equipoId, $hoy),
                fn (DatosRecursoEquipo $recurso): bool => in_array($recurso->recursoTipo, self::TIPOS, true),
            ));

            foreach ($asignados[$equipoId] as $recurso) {
                $idsPorTipo[$recurso->recursoTipo][] = $recurso->recursoId;
            }
        }

        $identificadores = [];
        $estados = [];

        foreach ($idsPorTipo as $tipo => $ids) {
            $ids = array_values(array_unique($ids));

            $identificadores[$tipo] = $this->identificadores($tipo, $ids);
            $estados[$tipo] = $this->estados->porIds($tipo, $ids);
        }

        foreach ($asignados as $equipoId => $recursos) {
            $filas = [];

            foreach ($recursos as $recurso) {
                $estado = $estados[$recurso->recursoTipo][$recurso->recursoId] ?? null;
                $identificador = $identificadores[$recurso->recursoTipo][$recurso->recursoId] ?? null;

                // Un recurso que Mantenimiento u Operaciones ya no conocen (dado
                // de baja después de asignarse) no tiene nada que mostrar.
                if ($estado === null || $identificador === null) {
                    continue;
                }

                $filas[] = [
                    'tipo' => $recurso->recursoTipo,
                    'tipoEtiqueta' => __('personal.recurso_tipo.'.$recurso->recursoTipo),
                    'identificador' => $identificador,
                    'estado' => $estado->estado,
                    'etiquetaEstado' => $estado->etiqueta,
                    'tono' => $estado->tono,
                    'operativo' => $estado->operativo,
                ];
            }

            usort($filas, fn (array $a, array $b): int => [array_search($a['tipo'], self::TIPOS, true), $a['identificador']]
                <=> [array_search($b['tipo'], self::TIPOS, true), $b['identificador']]);

            $this->porEquipo[$equipoId] = $filas;
        }
    }

    /**
     * @return list<array{tipo: string, tipoEtiqueta: string, identificador: string, estado: string, etiquetaEstado: string, tono: string, operativo: bool}>
     */
    public function deEquipo(int $equipoTrabajoId): array
    {
        return $this->porEquipo[$equipoTrabajoId] ?? [];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string> identificador por id del recurso
     */
    private function identificadores(string $tipo, array $ids): array
    {
        $catalogo = $tipo === LecturaEstadoEquipamiento::TIPO_DRON
            ? $this->drones->porIds($ids)
            : $this->equipamiento->porIds($tipo, $ids);

        return array_map(fn ($recurso): string => $recurso->identificador, $catalogo);
    }
}
