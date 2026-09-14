<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Forma de dato primitiva de una orden de aplicación para el pull de
 * catálogo (ADR 0003, regla 2): ningún módulo consumidor recibe el modelo
 * Eloquent `OrdenAplicacion`, solo estos campos planos. Los DECIMAL viajan
 * como string (invariante 6 de CLAUDE.md), igual que `OrdenAplicacionResource`.
 *
 * `lotes` (HU-92, tarea 107, reemplaza el `loteId: int` de antes): la orden
 * cubre varios lotes de la propiedad, cada uno con su propia hectáreas
 * solicitada. Forma elegida — lista plana de arrays, NO un DTO propio por
 * ítem: no hay precedente en el repo de un DTO anidado dentro de un
 * `*Catalogo` (todos son planos, ver `TrabajoAsignadoCatalogo`/`LoteCatalogo`),
 * y cada lote acá son solo dos escalares — un DTO propio sería una capa sin
 * beneficio.
 *
 * `litrosHa`/`kilosPorVuelo` (HU-79, tarea 110) son AMBOS `?string`, nunca
 * los dos con valor a la vez: cuál de los dos trae dato depende de si la
 * categoría de insumo de la orden es líquida o sólida (ver docblock de
 * `OrdenesController::normalizarDatos()`). `litrosHa` deja de ser
 * obligatorio con esta tarea — una orden sólida no tiene litros por
 * hectárea, y forzar un valor acá habría sido inventar un dato que no
 * existe.
 */
final readonly class OrdenAplicacionCatalogo
{
    /** @param  list<array{lote_id: int, hectareas_solicitadas: string}>  $lotes */
    public function __construct(
        public int $id,
        public int $contratoId,
        public array $lotes,
        public int $nroAplicacion,
        public ?string $litrosHa,
        public ?string $kilosPorVuelo,
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
            'lotes' => $this->lotes,
            'nro_aplicacion' => $this->nroAplicacion,
            'litros_ha' => $this->litrosHa,
            'kilos_por_vuelo' => $this->kilosPorVuelo,
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
