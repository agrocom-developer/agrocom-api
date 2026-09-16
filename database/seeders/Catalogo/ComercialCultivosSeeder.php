<?php

namespace Database\Seeders\Catalogo;

use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use Illuminate\Database\Seeder;

/**
 * Catálogo de cultivos de la zona (HU-48, tarea 71, ADR 0015 punto 4):
 * soya, maíz, girasol, trigo, sorgo, chía, frejol, pasto.
 *
 * "Pasto" (16/9/2026): un lote también puede sembrarse de pasto, no solo de
 * cultivos de cosecha — mismo catálogo, mismo flujo de siembra por
 * propiedad×campaña, sin distinción especial en el modelo.
 *
 * Corre en todos los entornos, producción incluida — mismo criterio que
 * `FinanzasRubrosSeeder`. `created_by`/`updated_by` NULL: dato de catálogo,
 * sin autor humano. Idempotente vía `firstOrCreate` por nombre.
 */
class ComercialCultivosSeeder extends Seeder
{
    /** @var list<string> */
    private const CULTIVOS = [
        'Soya',
        'Maíz',
        'Girasol',
        'Trigo',
        'Sorgo',
        'Chía',
        'Frejol',
        'Pasto',
    ];

    public function run(): void
    {
        foreach (self::CULTIVOS as $nombre) {
            Cultivo::query()->firstOrCreate(['nombre' => $nombre], ['activo' => true]);
        }
    }
}
