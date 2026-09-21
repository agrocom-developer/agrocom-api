<?php

namespace App\Dominios\Personal\Contratos;

/**
 * Frontera de lectura de Personal hacia otros módulos (ADR 0003, regla 2): la
 * ficha de un dron —y mañana la de un vehículo, un generador o una batería—
 * muestra, en su resumen relacionado, qué cuadrillas lo tienen asignado sin
 * importar los modelos `EquipoRecurso` ni `EquipoTrabajo`. Es la pregunta
 * inversa de {@see LecturaEquipoTrabajo::recursosAFecha()} (qué recursos tenía
 * un equipo): acá se parte del recurso y se llega a sus equipos.
 *
 * `per_equipo_recursos` guarda `recurso_tipo` + `recurso_id` sin FK (ADR 0015,
 * punto 3), así que el contrato recibe los dos datos y no un id suelto.
 */
interface LecturaCuadrillasPorRecurso
{
    public const TIPO_DRON = 'dron';

    public const TIPO_VEHICULO = 'vehiculo';

    public const TIPO_GENERADOR = 'generador';

    public const TIPO_BATERIA = 'bateria';

    /**
     * Cuadrillas que tienen (o tuvieron) el recurso, a la fecha `$fecha`
     * (`Y-m-d`): «vigente» es lo mismo que en el listado de cuadrillas —`desde`
     * ya pasó y `hasta` no venció—. Una cuadrilla dada de baja no cuenta. Sin
     * asignaciones, todo en cero y la lista vacía.
     *
     * @param  self::TIPO_*  $recursoTipo
     */
    public function deRecurso(string $recursoTipo, int $recursoId, string $fecha): DatosCuadrillasDeRecurso;
}
