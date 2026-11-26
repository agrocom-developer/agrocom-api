<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Proveedores\Dominio\Disponibilidad;
use App\Dominios\Proveedores\Dominio\Excepciones\RegistroInvalido;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Piloto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use Illuminate\Support\Carbon;

/**
 * Mueve el interruptor de disponibilidad de una cuenta de proveedor (ADR 0045, punto 8).
 *
 * - La propia cuenta: el titular mueve el de su proveedor; el piloto de una empresa, el suyo.
 * - El encargado (titular) marca a un piloto de su proveedor. El proveedor ajeno es 404.
 *
 * Gana el último cambio, y la fila guarda cuándo y quién lo hizo (la app del piloto muestra
 * «Tu empresa te marcó como ocupado»). Es solo un estado: no emite avisos ni reabre
 * oleadas, así que volver a `disponible` nunca dispara avisos atrasados. El alcance sale de
 * la cuenta autenticada, nunca de un id del request (invariante 5).
 */
final class CambiarDisponibilidad
{
    public function __construct(private readonly AlcanceDelSolicitante $alcance) {}

    /** @return Proveedor|Piloto|null la fila cambiada, o null si la cuenta no es titular ni piloto de un proveedor. */
    public function propia(int $usuarioId, Disponibilidad $valor): Proveedor|Piloto|null
    {
        $registro = $this->alcance->proveedorDelTitular($usuarioId) ?? $this->alcance->pilotoDeLaCuenta($usuarioId);

        return $registro === null ? null : $this->aplicar($registro, $valor, $usuarioId);
    }

    /** @throws RegistroInvalido si el piloto no es de un proveedor del que la cuenta sea titular. */
    public function deUnPiloto(int $titularUsuarioId, int $pilotoId, Disponibilidad $valor): Piloto
    {
        $proveedor = $this->alcance->proveedorDelTitular($titularUsuarioId);
        $piloto = $proveedor === null ? null : Piloto::query()->whereKey($pilotoId)->where('proveedor_id', $proveedor->id)->first();

        if ($piloto === null) {
            throw RegistroInvalido::sujetoAjeno();
        }

        /** @var Piloto $cambiado */
        $cambiado = $this->aplicar($piloto, $valor, $titularUsuarioId);

        return $cambiado;
    }

    private function aplicar(Proveedor|Piloto $registro, Disponibilidad $valor, int $autorId): Proveedor|Piloto
    {
        $registro->update([
            'disponibilidad' => $valor,
            'disponibilidad_cambiada_en' => Carbon::now(),
            'disponibilidad_cambiada_por' => $autorId,
        ]);

        return $registro;
    }
}
