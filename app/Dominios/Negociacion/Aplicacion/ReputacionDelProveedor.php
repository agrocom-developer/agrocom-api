<?php

namespace App\Dominios\Negociacion\Aplicacion;

use App\Dominios\Negociacion\Dominio\EstadoOferta;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Cancelacion;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Proveedores\Contratos\DatosProveedor;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;

/**
 * Reputación del proveedor (`GET /reputacion`, `negociacion-contrato.md` §3.5): su
 * calificación y su tasa de cancelación (cancelaciones tras adjudicar sobre servicios
 * adjudicados, en los últimos 90 días). Sin sanciones ni tasa de aceptación: si una
 * cifra pasa el umbral, solo se alerta al panel y decide el administrador.
 */
final class ReputacionDelProveedor
{
    public function __construct(
        private readonly LecturaProveedores $proveedores,
        private readonly ParametrosDeNegociacion $parametros,
    ) {}

    /** @return array{proveedor: DatosProveedor, tasa_cancelacion: string, adjudicadas: int, canceladas: int, alerta_cancelacion: bool} */
    public function deLaCuenta(int $usuarioId): array
    {
        $proveedor = $this->proveedores->proveedorDeLaCuenta($usuarioId)
            ?? throw NegociacionInvalida::ajeno('sin_proveedor');

        return ['proveedor' => $proveedor, ...$this->tasa($proveedor->id)];
    }

    /** @return array{tasa_cancelacion: string, adjudicadas: int, canceladas: int, alerta_cancelacion: bool} */
    public function tasa(int $proveedorId): array
    {
        $desde = Carbon::now()->subDays($this->parametros->ventanaCancelacionDias());

        $canceladas = Cancelacion::query()->where('proveedor_id', $proveedorId)->where('parte', 'proveedor')->where('created_at', '>=', $desde)->count();
        $vigentes = Oferta::query()->where('proveedor_id', $proveedorId)->where('estado', EstadoOferta::Aceptada->value)->where('respondida_en', '>=', $desde)->count();
        $adjudicadas = $vigentes + $canceladas;

        $tasa = $adjudicadas === 0
            ? BigDecimal::zero()
            : BigDecimal::of($canceladas)->dividedBy($adjudicadas, 4, RoundingMode::HalfUp);

        return [
            'tasa_cancelacion' => (string) $tasa->toScale(4),
            'adjudicadas' => $adjudicadas,
            'canceladas' => $canceladas,
            'alerta_cancelacion' => $adjudicadas > 0 && $tasa->isGreaterThanOrEqualTo(BigDecimal::of($this->parametros->umbralCancelacion())),
        ];
    }
}
