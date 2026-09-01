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
            'version' => sprintf('1.%d.%d', fake()->numberBetween(0, 20), fake()->numberBetween(0, 20)),
            'version_code' => $versionCode,
            'ruta_apk' => "distribucion/apk/{$versionCode}.apk",
            'estado' => EstadoVersionApk::Pendiente,
        ];
    }
}
