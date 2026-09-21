<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Forma de dato primitiva de un trabajo asignado desde el panel, para el pull
 * de catálogo (ADR 0003, regla 2; HU-70, tarea 85): ningún módulo consumidor
 * recibe el modelo Eloquent `Trabajo`, solo estos campos planos.
 * `hectareas_declaradas` viaja como string (invariante 6 de CLAUDE.md).
 *
 * `uuid_cliente` es el que generó el panel al confirmar la asignación (ver
 * `Aplicacion/AsignarEquiposOrden`) — la app de campo lo lee de acá y lo usa
 * TAL CUAL para abrir sesiones sobre este trabajo (`abrirSesion` lo resuelve
 * por `trabajo_uuid_cliente`), sin generar uno propio.
 *
 * Los 7 campos de límites climáticos y parámetros de vuelo
 * (`humedadMinPct`...`anchoPasadaM`) llegaron acá desde
 * `OrdenAplicacionCatalogo` (migración
 * `2026_09_18_100001_mueve_clima_vuelo_de_ordenes_a_trabajos_table`):
 * describen el vuelo que ejecuta ESTE equipo, no la orden completa — todos
 * `?string` (`nullable`, invariante 6 de CLAUDE.md), `null` cuando el jefe
 * de campo no los completó al asignar. Coherente con que esta clase ya
 * filtra `whereNotNull('equipo_trabajo_id')` (solo los `Trabajo` nacidos de
 * `abrirPorAsignacion()`): son los únicos que tiene sentido mostrarle al
 * piloto antes del vuelo.
 */
final readonly class TrabajoAsignadoCatalogo
{
    public function __construct(
        public int $id,
        public string $uuidCliente,
        public int $ordenId,
        public int $loteId,
        public string $hectareasDeclaradas,
        public int $equipoTrabajoId,
        public ?string $humedadMinPct,
        public ?string $vientoMaxKmh,
        public ?string $temperaturaMaxC,
        public ?string $humedadMaxPct,
        public ?string $alturaVueloM,
        public ?string $velocidadVueloKmh,
        public ?string $anchoPasadaM,
        public string $updatedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid_cliente' => $this->uuidCliente,
            'orden_id' => $this->ordenId,
            'lote_id' => $this->loteId,
            'hectareas_declaradas' => $this->hectareasDeclaradas,
            'equipo_trabajo_id' => $this->equipoTrabajoId,
            'humedad_min_pct' => $this->humedadMinPct,
            'viento_max_kmh' => $this->vientoMaxKmh,
            'temperatura_max_c' => $this->temperaturaMaxC,
            'humedad_max_pct' => $this->humedadMaxPct,
            'altura_vuelo_m' => $this->alturaVueloM,
            'velocidad_vuelo_kmh' => $this->velocidadVueloKmh,
            'ancho_pasada_m' => $this->anchoPasadaM,
            'updated_at' => $this->updatedAt,
        ];
    }
}
