<?php

namespace Database\Seeders\Catalogo;

use App\Dominios\Finanzas\Infraestructura\Eloquent\Rubro;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Subrubro;
use Illuminate\Database\Seeder;

/**
 * Catálogo de rubros y subrubros de gasto (espec §4.4, línea 143: "los 8 del
 * presupuesto + Indirectos"; HU-33, tarea 47). La especificación NO nombra los
 * 8 rubros ni trae valores de `presupuesto_bs_ha` — este seeder usa categorías
 * genéricas razonables para una operación de agroaplicación con drones y
 * presupuestos nominales de partida (Bs/ha), a ajustar por el usuario cuando
 * el dato real esté disponible; no hay caso de uso de edición en esta tarea,
 * así que por ahora solo se corrigen reseeding este archivo.
 *
 * Los subrubros son un seed mínimo de ejemplo (2 por rubro) para que el
 * `<select>` de subrubro del formulario de gasto no quede vacío — esta tarea
 * no trae un ABM de rubros/subrubros, solo el catálogo sembrado.
 *
 * Corre en todos los entornos, producción incluida — mismo criterio que
 * `SeguridadSeeder`/`SecMenuSeeder`. `created_by`/`updated_by` NULL: dato de
 * catálogo, sin autor humano. Idempotente vía `firstOrCreate` por nombre.
 */
class FinanzasRubrosSeeder extends Seeder
{
    /** @var array<string, array{presupuesto: string, subrubros: list<string>}> */
    private const RUBROS = [
        'Combustible' => ['presupuesto' => '15.00', 'subrubros' => ['Gasolina', 'Diésel']],
        'Mantenimiento de equipos' => ['presupuesto' => '10.00', 'subrubros' => ['Repuestos', 'Mano de obra']],
        'Personal de apoyo' => ['presupuesto' => '8.00', 'subrubros' => ['Jornales', 'Viáticos de campo']],
        'Insumos varios' => ['presupuesto' => '5.00', 'subrubros' => ['Elementos de protección', 'Herramientas menores']],
        'Alojamiento y viáticos' => ['presupuesto' => '6.00', 'subrubros' => ['Hospedaje', 'Alimentación']],
        'Transporte y logística' => ['presupuesto' => '7.00', 'subrubros' => ['Fletes', 'Peajes']],
        'Comunicaciones' => ['presupuesto' => '2.00', 'subrubros' => ['Telefonía', 'Datos móviles']],
        'Seguros' => ['presupuesto' => '4.00', 'subrubros' => ['Seguro de equipo', 'Seguro de personal']],
        'Indirectos' => ['presupuesto' => '12.00', 'subrubros' => ['Administración', 'Imprevistos']],
    ];

    public function run(): void
    {
        foreach (self::RUBROS as $nombre => $datos) {
            $rubro = Rubro::query()->firstOrCreate(
                ['nombre' => $nombre],
                ['presupuesto_bs_ha' => $datos['presupuesto']],
            );

            foreach ($datos['subrubros'] as $nombreSubrubro) {
                Subrubro::query()->firstOrCreate([
                    'rubro_id' => $rubro->id,
                    'nombre' => $nombreSubrubro,
                ]);
            }
        }
    }
}
