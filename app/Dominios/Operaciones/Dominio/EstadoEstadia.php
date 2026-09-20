<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Estado de una estadía en hacienda, para la pantalla de panel (reforma
 * 19/9/2026: la oficina ahora registra, edita, finaliza y da de baja
 * estadías, no solo las lee). NO existe una columna `estado` en
 * `ope_estadias_hacienda` — se DERIVA de `salida` (`null` = en curso, ver
 * `EstadiaHacienda::estado()`), mismo criterio documentado en el docblock de
 * la migración original de la tabla: una columna aparte solo podría
 * desincronizarse de la fecha real.
 *
 * Por eso este enum SÍ tiene su tabla de transiciones (`Dominio\MaquinaEstados\TransicionesEstadia`,
 * invariante 7 de CLAUDE.md) aunque no haya una columna física que gobernar: la única
 * transición de negocio (`en_curso → finalizada`, vía
 * `Aplicacion/FinalizarEstadiaHacienda`) sigue necesitando su guarda, aunque
 * lo que en verdad se escribe es `salida`, no `estado`.
 */
enum EstadoEstadia: string
{
    case EnCurso = 'en_curso';
    case Finalizada = 'finalizada';
}
