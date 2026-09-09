<?php

namespace Database\Factories;

use App\Dominios\Distribucion\Dominio\EstadoVersionApk;
use App\Dominios\Distribucion\Infraestructura\Eloquent\VersionApk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VersionApk>
 */
class VersionApkFactory extends Factory
{
    protected $model = VersionApk::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $versionCode = fake()->unique()->numberBetween(1, 100000);

        return [
            // `version` se deriva del `version_code`, que ya es único: la columna
            // tiene índice único parcial (`dis_versiones_apk_version_unico`) y dos
            // `numberBetween(0, 20)` sueltos daban 441 combinaciones posibles, que
            // colisionaban de a poco en cuanto un test creaba varias versiones.
            'version' => sprintf('1.%d.%d', intdiv($versionCode, 100), $versionCode % 100),
            'version_code' => $versionCode,
            'url_apk' => "https://github.com/agrocom-developer/agrocom-field/releases/download/v1.0.{$versionCode}/agrocom-field.apk",
            'estado' => EstadoVersionApk::Pendiente,
        ];
    }
}
