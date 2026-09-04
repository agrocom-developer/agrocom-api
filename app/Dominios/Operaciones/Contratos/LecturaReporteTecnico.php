<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de Operaciones hacia otros módulos (ADR 0003, regla 2):
 * el listado de reportes técnicos del panel (HU-43, tarea 57) necesita, de
 * cada reporte, su `contratoId` resuelto — sin importar `ReporteTecnico`,
 * `Trabajo` ni `OrdenAplicacion`. El caso de uso que lo consume
 * (`Aplicacion/ListarReportesTecnicos`, del propio módulo Operaciones) igual
 * pasa por este contrato en vez de leer los modelos directo: así el filtro
 * por cliente, que sí necesita cruzar a `Comercial`, compone sobre datos
 * primitivos, no sobre Eloquent.
 *
 * Un único método, no dos: a diferencia de `LecturaActaConformada` (que
 * separa "uno puntual" de "listar todos"), esta pantalla siempre necesita el
 * universo completo para filtrar después por cliente/período — no hay caso
 * de uso hoy que pida un reporte puntual por este contrato.
 */
interface LecturaReporteTecnico
{
    /** @return list<DatosReporteTecnico> todos los reportes técnicos generados, sin filtrar */
    public function listarTodos(): array;
}
