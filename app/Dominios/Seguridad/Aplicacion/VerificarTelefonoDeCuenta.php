<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Contratos\CanalDeCodigo;
use App\Dominios\Seguridad\Contratos\PropositoDeCodigo;
use App\Dominios\Seguridad\Dominio\Excepciones\CanalNoDisponible;
use App\Dominios\Seguridad\Dominio\Excepciones\CodigoInvalido;
use App\Dominios\Seguridad\Dominio\Excepciones\DemasiadosCodigos;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;

/**
 * Verificar el teléfono de la cuenta con un código por WhatsApp o SMS (ADR 0046, punto 29): confirmarlo prueba que la
 * persona tiene el número y marca `telefono_verificado_en`.
 */
final class VerificarTelefonoDeCuenta
{
    public function __construct(private readonly CodigosDeVerificacion $codigos) {}

    /**
     * @return string|null el destino enmascarado, o `null` si la cuenta no tiene a dónde enviarlo por ese canal: no se envió nada.
     *
     * @throws CanalNoDisponible
     * @throws DemasiadosCodigos
     */
    public function solicitar(SecUser $usuario, CanalDeCodigo $canal): ?string
    {
        return $this->codigos->emitir($usuario, PropositoDeCodigo::VerificarTelefono, $canal);
    }

    /** @throws CodigoInvalido */
    public function confirmar(SecUser $usuario, string $codigo): SecUser
    {
        $canal = $this->codigos->confirmar($usuario, PropositoDeCodigo::VerificarTelefono, $codigo);

        (new MarcarCanalVerificado)->ejecutar($usuario, $canal);

        return $usuario;
    }
}
