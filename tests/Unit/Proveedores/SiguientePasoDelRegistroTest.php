<?php

use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Proveedores\Dominio\SiguientePasoDelRegistro;

/*
 * Qué sigue para quien ya tiene su cuenta de proveedor creada (ADR 0046, punto 26): la app lo lee de
 * `GET /api/proveedores/mi` tras iniciar sesión, en lugar de repetir el registro.
 */

it('indica el siguiente paso del registro según el estado y los documentos faltantes', function (EstadoAprobacion $estado, array $faltantes, SiguientePasoDelRegistro $esperado) {
    expect(SiguientePasoDelRegistro::de($estado, $faltantes))->toBe($esperado);
})->with([
    'pendiente con documentos por subir' => [EstadoAprobacion::Pendiente, ['licencia_piloto'], SiguientePasoDelRegistro::SubirDocumentos],
    'pendiente con todo subido' => [EstadoAprobacion::Pendiente, [], SiguientePasoDelRegistro::EnRevision],
    'rechazado corrige y reenvía' => [EstadoAprobacion::Rechazado, ['licencia_piloto'], SiguientePasoDelRegistro::Corregir],
    'aprobado' => [EstadoAprobacion::Aprobado, [], SiguientePasoDelRegistro::Listo],
    'suspendido' => [EstadoAprobacion::Suspendido, [], SiguientePasoDelRegistro::Suspendido],
    'baja' => [EstadoAprobacion::Baja, [], SiguientePasoDelRegistro::Baja],
]);
