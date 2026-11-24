<?php

namespace Database\Seeders\Demo;

use App\Dominios\Personal\Aplicacion\CrearPersona;
use App\Dominios\Personal\Aplicacion\DatosPersona;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use Illuminate\Database\Seeder;

/**
 * Da una persona operativa a cada piloto y auxiliar de los proveedores de la
 * demo. El `piloto_id` y el `auxiliar_id` de una sesión de vuelo son ids de
 * `per_personas` y la app los toma de `usuario.persona_id` (`GET /api/auth/sesion`);
 * una cuenta sin persona no puede abrir sesiones. Un proveedor real recibe su
 * persona al aprobarse; la demo la siembra aquí.
 *
 * Idempotente: una cuenta que ya tiene `persona_id` no se toca.
 */
class PersonasDeOperariosDemoSeeder extends Seeder
{
    use SoporteDemo;

    /** username, nombres, apellido paterno, apellido materno, celular, rol */
    private const OPERARIOS = [
        ['rodrigo.suarez', 'Rodrigo', 'Suárez', 'Peña', '+591 70811001', 'piloto'],
        ['valeria.montano', 'Valeria', 'Montaño', 'Roca', '+591 70811002', 'piloto'],
        ['diego.parada', 'Diego', 'Parada', 'Cuéllar', '+591 70811003', 'auxiliar'],
        ['carlos.mamani', 'Carlos', 'Mamani', 'Quispe', '+591 70012345', 'piloto'],
        ['esteban.choque', 'Esteban', 'Choque', 'Ramos', '+591 70811005', 'auxiliar'],
        ['marcelo.terrazas', 'Marcelo', 'Terrazas', 'Aguilera', '+591 70811006', 'piloto'],
        ['ivan.cuellar', 'Iván', 'Cuéllar', 'Soliz', '+591 70811007', 'piloto'],
        ['lucia.flores', 'Lucía', 'Flores', 'Justiniano', '+591 70811008', 'auxiliar'],
    ];

    public function __construct(private readonly CrearPersona $crearPersona) {}

    public function run(): void
    {
        foreach (self::OPERARIOS as $indice => [$username, $nombres, $paterno, $materno, $celular, $rol]) {
            $usuario = $this->usuarioPorUsername($username);

            if ($usuario === null || $usuario->persona_id !== null) {
                continue;
            }

            $ci = (string) (9100100 + $indice);

            $persona = $this->personaPorCi($ci) ?? $this->crearPersona->ejecutar(new DatosPersona(
                nombres: $nombres,
                apellidoPaterno: $paterno,
                apellidoMaterno: $materno,
                ci: $ci,
                celular: $celular,
                correo: null,
                direccion: null,
                rol: RolOperativoPersona::from($rol),
                baseId: null,
            ));

            $usuario->persona_id = $persona->id;
            $usuario->save();
        }
    }
}
