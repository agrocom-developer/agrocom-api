<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de Operaciones hacia otros módulos (ADR 0003, regla 2):
 * el consumidor (`Sincronizacion`) recibe DTOs primitivos, nunca el modelo
 * Eloquent `Trabajo`. Sección `trabajos` del catálogo (HU-70, tarea 85): los
 * trabajos que el panel ya abrió por asignación de equipo, para que la app de
 * campo los lea y abra sesiones sobre ellos sin haberlos creado ella misma.
 */
interface LecturaTrabajosAsignados
{
    /**
     * Trabajos con `equipo_trabajo_id` no nulo (nacidos por asignación desde
     * el panel, nunca por sync — ver `Aplicacion/AsignarEquiposOrden`)
     * modificados después de la posición del cursor, ordenados de forma
     * determinística por (`updated_at`, `id`) ascendente.
     *
     * `$cursorActualizadoEn`/`$cursorId` en `null` (ambos, siempre juntos)
     * significa "sin posición": trae desde el principio.
     *
     * @return list<TrabajoAsignadoCatalogo>
     */
    public function listarModificadosDesde(?string $cursorActualizadoEn, ?int $cursorId, int $limite): array;
}
