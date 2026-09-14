<?php

namespace Database\Seeders\Catalogo;

use App\Dominios\Operaciones\Dominio\TipoInsumo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\CategoriaInsumo;
use Illuminate\Database\Seeder;

/**
 * Catálogo de categorías de insumo de la orden de aplicación (HU-79, tarea
 * 110): sólido (kilos por vuelo) o líquido (litros por hectárea) —
 * categorías tal cual las nombró el dueño, sin pantalla de alta todavía
 * (ver docblock de `CategoriaInsumo`).
 *
 * Corre en todos los entornos, producción incluida — mismo criterio que
 * `ComercialCultivosSeeder`. `created_by`/`updated_by` NULL: dato de
 * catálogo, sin autor humano. Idempotente vía `firstOrCreate` por nombre.
 */
class OperacionesCategoriasInsumoSeeder extends Seeder
{
    /** @var array<string, TipoInsumo> */
    private const CATEGORIAS = [
        'Fertilizantes' => TipoInsumo::Solido,
        'Semillas de Pasto' => TipoInsumo::Solido,
        'Insecticidas' => TipoInsumo::Liquido,
        'Herbicida' => TipoInsumo::Liquido,
        'Fungicidas' => TipoInsumo::Liquido,
        'Fertilizante' => TipoInsumo::Liquido,
        'Coadyuvantes' => TipoInsumo::Liquido,
        'Antiespumante' => TipoInsumo::Liquido,
    ];

    public function run(): void
    {
        foreach (self::CATEGORIAS as $nombre => $tipoInsumo) {
            CategoriaInsumo::query()->firstOrCreate(['nombre' => $nombre], ['tipo_insumo' => $tipoInsumo]);
        }
    }
}
