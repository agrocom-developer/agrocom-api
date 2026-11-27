<?php

namespace App\Dominios\Disputas\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Una operación de disputa que las reglas no permiten. `esAjeno`: el registro
 * existe pero no es de quien pregunta y la API responde 404 (invariante 5 de
 * CLAUDE.md). `esConflicto`: el estado de la disputa no admite el paso y la API
 * responde 409.
 */
final class DisputaInvalida extends RuntimeException
{
    private function __construct(
        string $mensaje,
        public readonly bool $esAjeno = false,
        public readonly bool $esConflicto = false,
        public readonly ?string $campo = null,
    ) {
        parent::__construct($mensaje);
    }

    public static function servicioAjeno(): self
    {
        return new self(Texto::de('disputas.errores.servicio_ajeno'), true);
    }

    public static function disputaAjena(): self
    {
        return new self(Texto::de('disputas.errores.disputa_ajena'), true);
    }

    public static function sinProveedor(): self
    {
        return new self(Texto::de('disputas.errores.sin_proveedor'), true);
    }

    public static function yaExiste(): self
    {
        return new self(Texto::de('disputas.errores.ya_existe'), false, true);
    }

    public static function motivoInvalido(string $motivo): self
    {
        return new self(Texto::de('disputas.errores.motivo_invalido', ['motivo' => $motivo]), false, false, 'motivo');
    }

    public static function servicioNoDisputable(string $estado): self
    {
        return new self(Texto::de('disputas.errores.servicio_no_disputable', ['estado' => Texto::de('solicitudes.estado.'.$estado)]));
    }

    public static function plazoVencido(): self
    {
        return new self(Texto::de('disputas.errores.plazo_vencido'));
    }

    public static function montoInvalido(string $referencia): self
    {
        return new self(Texto::de('disputas.errores.monto_invalido', ['monto' => $referencia]), false, false, 'monto_en_disputa');
    }

    public static function textoRequerido(): self
    {
        return new self(Texto::de('disputas.errores.texto_requerido'), false, false, 'texto');
    }

    public static function motivoRequerido(): self
    {
        return new self(Texto::de('disputas.errores.motivo_requerido'), false, false, 'motivo');
    }

    public static function noAdmiteMensajes(): self
    {
        return new self(Texto::de('disputas.errores.no_admite_mensajes'), false, true);
    }

    public static function noSeRetira(): self
    {
        return new self(Texto::de('disputas.errores.no_se_retira'), false, true);
    }

    public static function soloQuienLaAbrio(): self
    {
        return new self(Texto::de('disputas.errores.solo_quien_la_abrio'), false, false, 'disputa');
    }

    public static function noSeApela(): self
    {
        return new self(Texto::de('disputas.errores.no_se_apela'), false, true);
    }

    public static function apelacionVencida(): self
    {
        return new self(Texto::de('disputas.errores.apelacion_vencida'));
    }

    public static function noSeResuelve(): self
    {
        return new self(Texto::de('disputas.errores.no_se_resuelve'), false, true);
    }

    public static function reembolsoInvalido(string $causa): self
    {
        return new self(Texto::de('disputas.errores.reembolso.'.$causa), false, false, 'monto_reembolso');
    }

    public static function transicionNoPermitida(string $desde, string $hacia): self
    {
        return new self(Texto::de('disputas.errores.transicion_no_permitida', [
            'desde' => Texto::de('disputas.estado.'.$desde),
            'hacia' => Texto::de('disputas.estado.'.$hacia),
        ]), false, true);
    }
}
