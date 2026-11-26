<?php

namespace App\Dominios\Solicitudes\Contratos;

/**
 * Lo que la negociación necesita saber de una solicitud (`negociacion-contrato.md`
 * §1 a §4): estado, partes, precio, vigencia, ventana y avance. Es una foto de
 * lectura; los cambios pasan por {@see PasosDeSolicitud}.
 */
final readonly class DatosSolicitudNegociable
{
    public function __construct(
        public int $id,
        public string $estado,
        public int $clienteId,
        public int $clienteUsuarioId,
        public ?int $proveedorId,
        public ?int $equipoId,
        public ?int $ofertaAdjudicadaId,
        public string $tipoAplicacion,
        public string $hectareasSolicitadas,
        public ?string $precioHaPropuesto,
        public ?string $precioHaAcordado,
        public ?string $montoEstimado,
        public ?string $montoFinal,
        public ?string $hectareasEjecutadas,
        public string $porcentajeAvance,
        public ?string $venceEn,
        public string $ventanaDesde,
        public string $ventanaHasta,
        public ?string $departamentoCodigo,
        public ?string $finalizadaEn,
        public ?string $cierreSolicitadoEn,
        public ?string $ultimoAvanceEn,
        public ?string $iniciadaEn,
        public ?string $terrenoNombre = null,
    ) {}

    public function enMercado(): bool
    {
        return in_array($this->estado, ['abierta', 'con_ofertas'], true);
    }
}
