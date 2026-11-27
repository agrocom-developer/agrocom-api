<?php

namespace App\Dominios\Reclamos\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Una operación de reclamo que las reglas no permiten. `esAjeno`: el registro
 * existe pero no es de quien pregunta y la API responde 404 (invariante 5 de
 * CLAUDE.md). `esConflicto`: el estado del reclamo no admite el paso y la API
 * responde 409.
 */
final class ReclamoInvalido extends RuntimeException
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
        return new self(Texto::de('reclamos.errores.servicio_ajeno'), true);
    }

    public static function reclamoAjeno(): self
    {
        return new self(Texto::de('reclamos.errores.reclamo_ajeno'), true);
    }

    public static function sinProveedor(): self
    {
        return new self(Texto::de('reclamos.errores.sin_proveedor'), true);
    }

    public static function yaExiste(): self
    {
        return new self(Texto::de('reclamos.errores.ya_existe'), false, true);
    }

    public static function motivoInvalido(string $motivo): self
    {
        return new self(Texto::de('reclamos.errores.motivo_invalido', ['motivo' => $motivo]), false, false, 'motivo');
    }

    public static function servicioNoDeclamable(string $estado): self
    {
        return new self(Texto::de('reclamos.errores.servicio_no_declamable', ['estado' => Texto::de('solicitudes.estado.'.$estado)]));
    }

    public static function plazoVencido(): self
    {
        return new self(Texto::de('reclamos.errores.plazo_vencido'));
    }

    public static function montoInvalido(string $referencia): self
    {
        return new self(Texto::de('reclamos.errores.monto_invalido', ['monto' => $referencia]), false, false, 'monto_en_reclamo');
    }

    public static function textoRequerido(): self
    {
        return new self(Texto::de('reclamos.errores.texto_requerido'), false, false, 'texto');
    }

    public static function motivoRequerido(): self
    {
        return new self(Texto::de('reclamos.errores.motivo_requerido'), false, false, 'motivo');
    }

    public static function noAdmiteMensajes(): self
    {
        return new self(Texto::de('reclamos.errores.no_admite_mensajes'), false, true);
    }

    public static function noSeRetira(): self
    {
        return new self(Texto::de('reclamos.errores.no_se_retira'), false, true);
    }

    public static function soloQuienLaAbrio(): self
    {
        return new self(Texto::de('reclamos.errores.solo_quien_la_abrio'), false, false, 'reclamo');
    }

    public static function noSeApela(): self
    {
        return new self(Texto::de('reclamos.errores.no_se_apela'), false, true);
    }

    public static function apelacionVencida(): self
    {
        return new self(Texto::de('reclamos.errores.apelacion_vencida'));
    }

    public static function noSeResuelve(): self
    {
        return new self(Texto::de('reclamos.errores.no_se_resuelve'), false, true);
    }

    public static function reembolsoInvalido(string $causa): self
    {
        return new self(Texto::de('reclamos.errores.reembolso.'.$causa), false, false, 'monto_reembolso');
    }

    public static function transicionNoPermitida(string $desde, string $hacia): self
    {
        return new self(Texto::de('reclamos.errores.transicion_no_permitida', [
            'desde' => Texto::de('reclamos.estado.'.$desde),
            'hacia' => Texto::de('reclamos.estado.'.$hacia),
        ]), false, true);
    }
}
