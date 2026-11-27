<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Contratos\CanalDeCodigo;
use App\Dominios\Seguridad\Contratos\PropositoDeCodigo;
use App\Dominios\Seguridad\Dominio\Excepciones\CanalNoDisponible;
use App\Dominios\Seguridad\Dominio\Excepciones\CodigoInvalido;
use App\Dominios\Seguridad\Dominio\Excepciones\DemasiadosCodigos;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecTokenDispositivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Support\Facades\DB;

/**
 * Cambiar la contraseña con la sesión iniciada (ADR 0046, punto 27): la persona pide un código por el canal que elige y lo
 * confirma junto con la contraseña nueva. Se revocan los demás dispositivos y se conserva el que hizo el cambio.
 */
final class CambiarContrasenaDeCuenta
{
    public function __construct(
        private readonly CodigosDeVerificacion $codigos,
        private readonly RevocarTokensDeCuenta $revocar,
    ) {}

    /**
     * @return string|null el destino enmascarado, o `null` si la cuenta no tiene a dónde enviarlo por ese canal: no se envió nada.
     *
     * @throws CanalNoDisponible
     * @throws DemasiadosCodigos
     */
    public function solicitarCodigo(SecUser $usuario, CanalDeCodigo $canal): ?string
    {
        return $this->codigos->emitir($usuario, PropositoDeCodigo::CambiarContrasena, $canal);
    }

    /** @throws CodigoInvalido */
    public function cambiar(SecUser $usuario, SecTokenDispositivo $dispositivoActual, string $codigo, string $passwordNueva): void
    {
        $canal = $this->codigos->confirmar($usuario, PropositoDeCodigo::CambiarContrasena, $codigo);

        DB::transaction(function () use ($usuario, $dispositivoActual, $canal, $passwordNueva): void {
            $usuario->password = $passwordNueva;
            (new MarcarCanalVerificado)->ejecutar($usuario, $canal);
            $this->revocar->ejecutar($usuario, $dispositivoActual->id);
        });
    }
}
