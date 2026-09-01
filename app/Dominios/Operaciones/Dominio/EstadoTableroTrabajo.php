<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Estado de TABLERO de un trabajo (HU-15, tarea 15) — no es un estado real
 * de `Trabajo` (ese es `EstadoTrabajo`, con dos valores; invariante 7: solo
 * lo escribe `Aplicacion/MaquinaEstados/MaquinaEstadosTrabajo.php`). Es una
 * PROYECCIÓN DE LECTURA para el tablero del jefe de campo: nunca se
 * persiste ni transiciona, se recalcula en cada consulta a partir de
 * `EstadoTrabajo` más el estado de las sesiones del trabajo (HU-14) — así
 * una validación nueva se refleja sin ningún paso extra acá.
 *
 * Ver `Trabajo::estadoTablero()` (cálculo en PHP, sesiones ya cargadas) y
 * `Trabajo::scopeConEstadoTablero()` (misma regla en SQL, para el filtro
 * paginado de `Aplicacion/ListarTrabajos.php`).
 */
enum EstadoTableroTrabajo: string
{
    case Abierto = 'abierto';
    case Cerrado = 'cerrado';
    case Validado = 'validado';
}
