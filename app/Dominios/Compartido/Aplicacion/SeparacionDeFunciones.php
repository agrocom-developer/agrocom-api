<?php

namespace App\Dominios\Compartido\Aplicacion;

use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\Excepciones\AprobacionPropia;

/**
 * Quien registra a un proveedor, piloto o dron no lo aprueba (invariante 4,
 * por analogía: el que captura no valida). Un registro que se autorregistra
 * desde la app lo crea su propia cuenta, que no es administrador; el que se
 * carga a mano en el panel lo crea el administrador, y ese mismo
 * administrador no puede aprobarlo. Otra persona con permiso sí. Se puede
 * apagar con `proveedores.separar_funciones` si el dueño decide lo contrario.
 */
final class SeparacionDeFunciones
{
    /** @throws AprobacionPropia si el actor aprueba lo que él mismo registró. */
    public static function exigir(AccionAprobacion $accion, ?int $registradoPor, ?int $actorUsuarioId): void
    {
        if ($accion !== AccionAprobacion::Aprobar || $registradoPor === null || $actorUsuarioId === null) {
            return;
        }

        if (! (bool) config('proveedores.separar_funciones', true)) {
            return;
        }

        if ($registradoPor === $actorUsuarioId) {
            throw AprobacionPropia::nueva();
        }
    }
}
