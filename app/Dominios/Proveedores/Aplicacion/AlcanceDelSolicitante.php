<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Auxiliar;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Piloto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;

/**
 * Qué sujetos puede gestionar una cuenta desde la app (invariante 5 de
 * CLAUDE.md aplicado a proveedores: la garantía es de código, el alcance sale
 * siempre de la cuenta autenticada y nunca de un `where` agregado después).
 *
 * - El titular de un proveedor gestiona a su proveedor, sus pilotos y sus
 *   auxiliares.
 * - Un piloto gestiona sus propios documentos y los de los auxiliares que
 *   registró.
 * - Un auxiliar gestiona los suyos.
 *
 * Los drones los resuelve `Flota` con el mismo criterio (dueño u operador).
 */
final class AlcanceDelSolicitante
{
    public function proveedorDelTitular(int $usuarioId): ?Proveedor
    {
        return Proveedor::query()->where('usuario_titular_id', $usuarioId)->first();
    }

    public function puedeGestionar(int $usuarioId, TipoSujeto $tipo, int $sujetoId): bool
    {
        return match ($tipo) {
            TipoSujeto::Proveedor => Proveedor::query()->whereKey($sujetoId)->where('usuario_titular_id', $usuarioId)->exists(),
            TipoSujeto::Piloto => $this->puedeGestionarPiloto($usuarioId, $sujetoId),
            TipoSujeto::Auxiliar => $this->puedeGestionarAuxiliar($usuarioId, $sujetoId),
            TipoSujeto::Dron => false,
        };
    }

    private function puedeGestionarPiloto(int $usuarioId, int $pilotoId): bool
    {
        $piloto = Piloto::query()->with('proveedor')->find($pilotoId);

        return $piloto !== null
            && ($piloto->usuario_id === $usuarioId || $piloto->proveedor->usuario_titular_id === $usuarioId);
    }

    private function puedeGestionarAuxiliar(int $usuarioId, int $auxiliarId): bool
    {
        $auxiliar = Auxiliar::query()->with('proveedor')->find($auxiliarId);

        if ($auxiliar === null) {
            return false;
        }

        if ($auxiliar->usuario_id === $usuarioId || $auxiliar->proveedor->usuario_titular_id === $usuarioId) {
            return true;
        }

        return $auxiliar->registrado_por_piloto_id !== null
            && Piloto::query()->whereKey($auxiliar->registrado_por_piloto_id)->where('usuario_id', $usuarioId)->exists();
    }
}
