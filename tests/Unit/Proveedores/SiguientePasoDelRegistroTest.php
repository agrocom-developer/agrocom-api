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

it('agrega el dron y sus fotos entre los documentos y la revisión, solo a quien opera drones propios', function (EstadoAprobacion $estado, array $faltantes, ?bool $conDron, bool $fotos, SiguientePasoDelRegistro $esperado) {
    expect(SiguientePasoDelRegistro::de($estado, $faltantes, $conDron, $fotos))->toBe($esperado);
})->with([
    'los documentos van primero aunque falte el dron' => [EstadoAprobacion::Pendiente, ['seguro_responsabilidad'], false, false, SiguientePasoDelRegistro::SubirDocumentos],
    'documentos al día y sin dron' => [EstadoAprobacion::Pendiente, [], false, false, SiguientePasoDelRegistro::RegistrarDron],
    'con dron sin las fotos obligatorias' => [EstadoAprobacion::Pendiente, [], true, false, SiguientePasoDelRegistro::SubirFotosDron],
    'con dron y las fotos subidas' => [EstadoAprobacion::Pendiente, [], true, true, SiguientePasoDelRegistro::EnRevision],
    'quien no necesita dron pasa a revisión' => [EstadoAprobacion::Pendiente, [], null, false, SiguientePasoDelRegistro::EnRevision],
    'rechazado corrige aunque falte el dron' => [EstadoAprobacion::Rechazado, [], false, false, SiguientePasoDelRegistro::Corregir],
    'aprobado sigue listo' => [EstadoAprobacion::Aprobado, [], false, false, SiguientePasoDelRegistro::Listo],
]);
