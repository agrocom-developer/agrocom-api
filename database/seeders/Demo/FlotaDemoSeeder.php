<?php

namespace Database\Seeders\Demo;

use App\Dominios\Operaciones\Aplicacion\CrearDron;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use Illuminate\Database\Seeder;

/**
 * Los tres drones operativos de Agrocom (`ope_drones`) que el marketplace
 * enlaza a sus drones de la flota (`ProveedoresDemoSeeder`): la ejecución en el
 * lote los usa. Sin fichas técnicas, baterías ni bases: eran del sistema anterior.
 *
 * Centinela: el dron `AG-02`.
 */
class FlotaDemoSeeder extends Seeder
{
    use SoporteDemo;

    public const DRON_CENTINELA = 'AG-02';

    public function __construct(private readonly CrearDron $crearDron) {}

    public function run(): void
    {
        if (Dron::query()->where('identificador', self::DRON_CENTINELA)->exists()) {
            return;
        }

        $this->drones();
    }

    private function drones(): void
    {
        $catalogo = [
            ['AG-02', 'DJI Agras T30', '30.00', '40.00'],
            ['AG-07', 'DJI Agras T50', '50.00', '50.00'],
            ['AG-09', 'DJI Agras T60', '60.00', '70.00'],
        ];

        foreach ($catalogo as [$identificador, $modelo, $litros, $kilos]) {
            $this->crearDron->ejecutar($identificador, $modelo, $litros, $kilos);
        }
    }
}
