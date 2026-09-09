<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Forma de dato primitiva de una orden de aplicación para el pull de
 * catálogo (ADR 0003, regla 2): ningún módulo consumidor recibe el modelo
 * Eloquent `OrdenAplicacion`, solo estos campos planos. Los DECIMAL viajan
 * como string (invariante 6 de CLAUDE.md), igual que `OrdenAplicacionResource`.
 */
final readonly class OrdenAplicacionCatalogo
{
    public function __construct(
        public int $id,
        public int $contratoId,
        public int $loteId,
        public int $nroAplicacion,
        public string $litrosHa,
        public ?string $humedadMinPct,
        public ?string $vientoMaxKmh,
        public ?string $temperaturaMaxC,
        public ?string $humedadMaxPct,
        public ?string $velocidadMaxKmh,
        public ?string $alturaVueloM,
        public ?string $velocidadVueloKmh,
        public ?string $anchoPasadaM,
        public ?string $observaciones,
        public ?int $emitidaPorContactoId,
        public string $fechaEmision,
        public string $estado,
        public string $updatedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'contrato_id' => $this->contratoId,
            'lote_id' => $this->loteId,
            'nro_aplicacion' => $this->nroAplicacion,
            'litros_ha' => $this->litrosHa,
            'humedad_min_pct' => $this->humedadMinPct,
            'viento_max_kmh' => $this->vientoMaxKmh,
            'temperatura_max_c' => $this->temperaturaMaxC,
            'humedad_max_pct' => $this->humedadMaxPct,
            'velocidad_max_kmh' => $this->velocidadMaxKmh,
            'altura_vuelo_m' => $this->alturaVueloM,
            'velocidad_vuelo_kmh' => $this->velocidadVueloKmh,
            'ancho_pasada_m' => $this->anchoPasadaM,
            'observaciones' => $this->observaciones,
            'emitida_por_contacto_id' => $this->emitidaPorContactoId,
            'fecha_emision' => $this->fechaEmision,
            'estado' => $this->estado,
            'updated_at' => $this->updatedAt,
        ];
    }
}
