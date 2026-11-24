<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de Operaciones hacia otros módulos (ADR 0003, regla 2):
 * quien necesita, de un acta, si está firmada, sus hectáreas conformadas y la
 * solicitud a la que pertenece —por ejemplo la comisión al completar la
 * solicitud (ADR 0034) o lo que ve el cliente— lo lee por acá, sin importar
 * `Acta` ni `Trabajo`. Resuelve `Acta.trabajo_id → Trabajo.solicitud_id`
 * puertas adentro de este módulo.
 *
 * `listarFirmadasPorSolicitud` nace de la solicitud (invariante 5 de
 * CLAUDE.md): el módulo `Solicitudes` ya acotó esa solicitud al cliente
 * autenticado, así que nunca es «todas las actas y después filtrar».
 */
interface LecturaActaConformada
{
    public function obtenerPorActaId(int $actaId): ?DatosActaConformada;

    /** @return list<DatosActaConformada> actas firmadas de la solicitud dada, de la más reciente a la más vieja */
    public function listarFirmadasPorSolicitud(int $solicitudId): array;
}
