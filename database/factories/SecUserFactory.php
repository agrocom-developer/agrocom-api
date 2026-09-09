<?php

namespace Database\Factories;

use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecUser>
 */
class SecUserFactory extends Factory
{
    protected $model = SecUser::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'password' => 'password',
            'type' => TipoUsuario::Interno,
            'persona_id' => null,
            'contrato_id' => null,
            'state' => true,
        ];
    }
}
