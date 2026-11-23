<?php

namespace Database\Seeders\Demo;

use App\Dominios\Mantenimiento\Aplicacion\CrearBateria;
use App\Dominios\Mantenimiento\Aplicacion\CrearFichaDron;
use App\Dominios\Mantenimiento\Dominio\EstadoBateria;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\FichaDron;
use App\Dominios\Operaciones\Aplicacion\CrearDron;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use Illuminate\Database\Seeder;

/**
 * Recursos: drones con su ficha técnica y baterías con sus ciclos.
 *
 * Centinela: el dron `AG-02`.
 */
class FlotaDemoSeeder extends Seeder
{
    use SoporteDemo;

    public const DRON_CENTINELA = 'AG-02';

    public function __construct(
        private readonly CrearDron $crearDron,
        private readonly CrearFichaDron $crearFichaDron,
        private readonly CrearBateria $crearBateria,
    ) {}

    public function run(): void
    {
        if (Dron::query()->where('identificador', self::DRON_CENTINELA)->exists()) {
            return;
        }

        $central = (int) $this->basePorNombre(PersonalDemoSeeder::BASE_CENTRAL)?->id;
        $pailon = (int) $this->basePorNombre(PersonalDemoSeeder::BASE_PAILON)?->id;
        $sanJulian = (int) $this->basePorNombre(PersonalDemoSeeder::BASE_SAN_JULIAN)?->id;

        if ($central === 0 || $pailon === 0 || $sanJulian === 0) {
            return; // falta PersonalDemoSeeder
        }

        $this->drones();
        $this->fichasDron();
        $this->baterias($central, $pailon, $sanJulian);
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

    private function fichasDron(): void
    {
        $catalogo = [
            ['DRONT50', '1ZNBJ7C00C00A1', 'T50-CH-00471', 'v03.02.0412', 'LATAM', 'RC-PLUS-88213', true, true, true],
            ['AG-02', '1ZNBJ4A00B00K9', 'T30-CH-00218', 'v02.05.0117', 'LATAM', 'RC-PLUS-55120', true, false, true],
            ['AG-07', '1ZNBJ7C00C00F3', 'T50-CH-00502', 'v03.02.0412', 'LATAM', 'RC-PLUS-88240', true, true, false],
        ];

        foreach ($catalogo as [$identificador, $serie, $chasis, $software, $region, $serieControl, $cargador, $modem, $maletin]) {
            if (FichaDron::query()->where('identificador_dron', $identificador)->exists()) {
                continue;
            }

            $this->crearFichaDron->ejecutar($identificador, $serie, $chasis, $software, $region, $serieControl, $cargador, $modem, $maletin);
        }
    }

    private function baterias(int $central, int $pailon, int $sanJulian): void
    {
        $catalogo = [
            ['BAT-01', 0, 180, EstadoBateria::Activa, $pailon],
            ['BAT-02', 0, 240, EstadoBateria::Activa, $pailon],
            ['BAT-03', 50, 95, EstadoBateria::Activa, $pailon],
            ['BAT-04', 0, 310, EstadoBateria::Activa, $sanJulian],
            ['BAT-05', 0, 420, EstadoBateria::Activa, $sanJulian],
            ['BAT-06', 120, 160, EstadoBateria::Activa, $sanJulian],
            ['BAT-07', 0, 275, EstadoBateria::Mantenimiento, $central],
            ['BAT-08', 0, 812, EstadoBateria::Retirada, $central],
        ];

        foreach ($catalogo as [$identificador, $inicial, $acumulados, $estado, $baseId]) {
            if (Bateria::query()->where('identificador', $identificador)->exists()) {
                continue;
            }

            $this->crearBateria->ejecutar($identificador, $inicial, $acumulados, $baseId, $estado);
        }
    }
}
