<?php

namespace App\Dominios\Finanzas\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Finanzas\Contratos\ModalidadPago;
use App\Dominios\Finanzas\Contratos\TarifaPago;

/**
 * Tarifa de pago al personal de campo (`fin_tarifas`, ADR 0023): la
 * configuración de pago base del módulo Finanzas. Un nombre («Fumigación
 * manual», «Fumigación con mapeo», «Voleo»…), la modalidad y cuánto cobra el
 * piloto y cuánto el ayudante. A lo sumo una es la predeterminada, que es la
 * que propone el alta de la Orden de Trabajo.
 *
 * Cambiar una tarifa NO altera ninguna orden ya armada ni ningún devengo:
 * ambos copian los montos al momento de crearse.
 *
 * @property int $id
 * @property string $nombre
 * @property ModalidadPago $modalidad
 * @property string $monto_piloto
 * @property string $monto_auxiliar
 * @property bool $predeterminada
 * @property string|null $descripcion
 */
class Tarifa extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'fin_tarifas';

    protected $fillable = [
        'nombre',
        'modalidad',
        'monto_piloto',
        'monto_auxiliar',
        'predeterminada',
        'descripcion',
    ];

    protected function casts(): array
    {
        return [
            'modalidad' => ModalidadPago::class,
            'monto_piloto' => 'decimal:2',
            'monto_auxiliar' => 'decimal:2',
            'predeterminada' => 'boolean',
        ];
    }

    public function comoContrato(): TarifaPago
    {
        return new TarifaPago(
            id: $this->id,
            nombre: $this->nombre,
            modalidad: $this->modalidad,
            montoPiloto: (string) $this->monto_piloto,
            montoAuxiliar: (string) $this->monto_auxiliar,
            predeterminada: $this->predeterminada,
        );
    }
}
