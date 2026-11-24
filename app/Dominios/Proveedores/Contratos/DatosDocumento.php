<?php

namespace App\Dominios\Proveedores\Contratos;

/** Un documento de verificación listo para mostrarse en una ficha (ver {@see LecturaDocumentos}). */
final readonly class DatosDocumento
{
    public function __construct(
        public int $id,
        public string $tipo,
        public string $estado,
        public ?string $venceEn,
        public ?int $diasParaVencer,
        public ?string $motivoRechazo,
        public ?string $nombreOriginal,
        public int $bytes,
        public string $subidoEn,
    ) {}

    /** El documento está aprobado y vence en 30 días o menos (o ya venció). */
    public function estaPorVencer(): bool
    {
        return $this->estado === 'aprobado' && $this->diasParaVencer !== null && $this->diasParaVencer <= 30;
    }
}
