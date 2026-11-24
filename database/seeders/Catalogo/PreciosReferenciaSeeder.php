<?php

namespace Database\Seeders\Catalogo;

use App\Dominios\Solicitudes\Infraestructura\Eloquent\PrecioReferencia;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Seeder;

/**
 * Precios de referencia por hectárea (`negociacion-contrato.md` §3.1): el precio general de
 * cada tipo de aplicación (de `config/negociacion.php`) y uno por macrorregión, con un factor
 * regional sobre el general. Son datos de partida: el administrador los ajusta y este seeder
 * no los pisa (idempotente por tipo y región). Corre en todos los entornos.
 */
class PreciosReferenciaSeeder extends Seeder
{
    /** @var array<string, string> macrorregión => factor sobre el precio general */
    private const FACTORES = [
        'cercado' => '1.00',
        'norte_integrado' => '1.00',
        'chiquitania' => '1.10',
        'valles' => '1.10',
        'chaco' => '1.15',
    ];

    public function run(): void
    {
        foreach ((array) config('negociacion.precio_referencia_ha', []) as $tipo => $general) {
            PrecioReferencia::query()->firstOrCreate(
                ['tipo_aplicacion' => $tipo, 'zona_id' => null, 'macrorregion' => null],
                ['precio_ha' => (string) $general],
            );

            foreach (self::FACTORES as $macrorregion => $factor) {
                $precio = BigDecimal::of((string) $general)->multipliedBy($factor)->toScale(2, RoundingMode::HalfUp);

                PrecioReferencia::query()->firstOrCreate(
                    ['tipo_aplicacion' => $tipo, 'zona_id' => null, 'macrorregion' => $macrorregion],
                    ['precio_ha' => (string) $precio],
                );
            }
        }
    }
}
