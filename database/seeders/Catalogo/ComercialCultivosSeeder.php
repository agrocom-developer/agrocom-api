<?php

namespace Database\Seeders\Catalogo;

use App\Dominios\Comercial\Dominio\CicloVidaCultivo;
use App\Dominios\Comercial\Dominio\TipoCultivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use Illuminate\Database\Seeder;

/**
 * Catálogo de cultivos de la zona (HU-48, tarea 71, ADR 0015 punto 4):
 * soya, maíz, girasol, trigo, sorgo, chía, frejol, pasto — con nombre
 * científico, tipo y ciclo de vida (ampliación 16/9/2026).
 *
 * `notas_agronomicas` es informativo (memoria "la mezcla es del cliente"):
 * sugerencias de referencia, no reglas del sistema — ningún caso de uso las
 * interpreta. La de "Pasto" recoge un pedido puntual del dueño: un lote de
 * pasto no se fumiga, se siembra, y para considerar terminado el trabajo de
 * una campaña conviene un mínimo de aplicaciones — dato a validar en campo,
 * no una condición que el motor de sesiones vaya a exigir hoy.
 *
 * `nombre_cientifico` de "Pasto" queda NULL a propósito: "pasto" no es una
 * especie sino un nombre genérico que cubre varias (Brachiaria, Panicum,
 * etc.) — forzar una sola sería inexacto.
 *
 * Corre en todos los entornos, producción incluida — mismo criterio que
 * `FinanzasRubrosSeeder`. `created_by`/`updated_by` NULL: dato de catálogo,
 * sin autor humano. Idempotente por nombre común — a diferencia del
 * `firstOrCreate` original, también refresca los atributos agronómicos de
 * los cultivos ya sembrados, pero sin tocar `activo`: si el dueño desactivó
 * uno a mano desde el panel, volver a correr el seeder no debe reactivarlo.
 */
class ComercialCultivosSeeder extends Seeder
{
    /** @var list<array{nombre_comun: string, nombre_cientifico: ?string, tipo_cultivo: TipoCultivo, ciclo_vida: CicloVidaCultivo, notas_agronomicas: ?string}> */
    private const CULTIVOS = [
        [
            'nombre_comun' => 'Soya',
            'nombre_cientifico' => 'Glycine max',
            'tipo_cultivo' => TipoCultivo::Oleaginosa,
            'ciclo_vida' => CicloVidaCultivo::Anual,
            'notas_agronomicas' => null,
        ],
        [
            'nombre_comun' => 'Maíz',
            'nombre_cientifico' => 'Zea mays',
            'tipo_cultivo' => TipoCultivo::Cereal,
            'ciclo_vida' => CicloVidaCultivo::Anual,
            'notas_agronomicas' => null,
        ],
        [
            'nombre_comun' => 'Girasol',
            'nombre_cientifico' => 'Helianthus annuus',
            'tipo_cultivo' => TipoCultivo::Oleaginosa,
            'ciclo_vida' => CicloVidaCultivo::Anual,
            'notas_agronomicas' => null,
        ],
        [
            'nombre_comun' => 'Trigo',
            'nombre_cientifico' => 'Triticum aestivum',
            'tipo_cultivo' => TipoCultivo::Cereal,
            'ciclo_vida' => CicloVidaCultivo::Anual,
            'notas_agronomicas' => null,
        ],
        [
            'nombre_comun' => 'Sorgo',
            'nombre_cientifico' => 'Sorghum bicolor',
            'tipo_cultivo' => TipoCultivo::Cereal,
            'ciclo_vida' => CicloVidaCultivo::Anual,
            'notas_agronomicas' => null,
        ],
        [
            'nombre_comun' => 'Chía',
            'nombre_cientifico' => 'Salvia hispanica',
            'tipo_cultivo' => TipoCultivo::Oleaginosa,
            'ciclo_vida' => CicloVidaCultivo::Anual,
            'notas_agronomicas' => null,
        ],
        [
            'nombre_comun' => 'Frejol',
            'nombre_cientifico' => 'Phaseolus vulgaris',
            'tipo_cultivo' => TipoCultivo::Leguminosa,
            'ciclo_vida' => CicloVidaCultivo::Anual,
            'notas_agronomicas' => null,
        ],
        [
            'nombre_comun' => 'Pasto',
            'nombre_cientifico' => null,
            'tipo_cultivo' => TipoCultivo::Forrajera,
            'ciclo_vida' => CicloVidaCultivo::Perenne,
            'notas_agronomicas' => 'Es siembra de pasto, no fumigación de cosecha: sugerencia de un agrónomo, a validar en campo — considerar un mínimo de 3 aplicaciones en la campaña antes de dar el trabajo por terminado. Tolera caldas de baja a media concentración; la composición exacta la define el cliente.',
        ],
    ];

    public function run(): void
    {
        foreach (self::CULTIVOS as $datos) {
            $cultivo = Cultivo::query()->firstOrNew(['nombre_comun' => $datos['nombre_comun']]);

            $cultivo->nombre_cientifico = $datos['nombre_cientifico'];
            $cultivo->tipo_cultivo = $datos['tipo_cultivo'];
            $cultivo->ciclo_vida = $datos['ciclo_vida'];
            $cultivo->notas_agronomicas = $datos['notas_agronomicas'];

            if (! $cultivo->exists) {
                $cultivo->activo = true;
            }

            $cultivo->save();
        }
    }
}
