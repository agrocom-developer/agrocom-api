<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de Operaciones hacia otros módulos (ADR 0003, regla 2):
 * quien necesita reportes técnicos con su `contratoId` resuelto pasa por acá,
 * sin importar `ReporteTecnico`, `Trabajo` ni `OrdenAplicacion`.
 *
 * Dos consumidores, dos métodos:
 *
 * - `listarTodos()` — el listado de reportes técnicos del panel (HU-43,
 *   tarea 57). Su caso de uso (`Aplicacion/ListarReportesTecnicos`, del
 *   propio módulo) igual pasa por este contrato en vez de leer los modelos
 *   directo: así el filtro por cliente, que sí necesita cruzar a
 *   `Comercial`, compone sobre datos primitivos, no sobre Eloquent. Siempre
 *   necesita el universo completo para filtrar después por cliente/período.
 * - `listarPorContrato()` — el portal del cliente (HU-41, tarea 55). La
 *   consulta nace del contrato del usuario autenticado (invariante 5 de
 *   CLAUDE.md): nunca es "todos y después filtrar".
 *
 * No hay caso de uso hoy que pida un reporte puntual por este contrato.
 */
interface LecturaReporteTecnico
{
    /** @return list<DatosReporteTecnico> todos los reportes técnicos generados, sin filtrar */
    public function listarTodos(): array;

    /** @return list<DatosReporteTecnico> solo los reportes de trabajos que cuelgan de órdenes de ese contrato, del más reciente al más viejo */
    public function listarPorContrato(int $contratoId): array;
}
