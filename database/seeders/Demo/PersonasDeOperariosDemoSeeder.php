<?php

namespace Database\Seeders\Demo;

use App\Dominios\Personal\Aplicacion\CrearPersona;
use App\Dominios\Personal\Aplicacion\DatosPersona;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Da una persona operativa a cada piloto de los proveedores de la
 * demo. El `piloto_id` de una sesión de vuelo es id de
 * `per_personas` y la app los toma de `usuario.persona_id` (`GET /api/auth/sesion`);
 * una cuenta sin persona no puede abrir sesiones. Un proveedor real recibe su
 * persona al aprobarse; la demo la siembra aquí.
 *
 * El vínculo vive en `sec_user.persona_legada_id` (el personal anterior, `per_personas`):
 * `persona_id` es ahora la persona única de la cuenta (`sec_personas`, ADR 0046) y no se
 * toca. La API sigue entregando `persona_legada_id` como `persona_id` hasta el contrato
 * nuevo del token.
 *
 * Idempotente: una cuenta que ya tiene vínculo no se toca.
 */
class PersonasDeOperariosDemoSeeder extends Seeder
{
    use SoporteDemo;

    /** username, nombres, apellido paterno, apellido materno, celular, rol */
    private const OPERARIOS = [
        ['rodrigo.suarez', 'Rodrigo', 'Suárez', 'Peña', '+591 70811001', 'piloto'],
        ['valeria.montano', 'Valeria', 'Montaño', 'Roca', '+591 70811002', 'piloto'],
        ['carlos.mamani', 'Carlos', 'Mamani', 'Quispe', '+591 70012345', 'piloto'],
        ['marcelo.terrazas', 'Marcelo', 'Terrazas', 'Aguilera', '+591 70811006', 'piloto'],
        ['ivan.cuellar', 'Iván', 'Cuéllar', 'Soliz', '+591 70811007', 'piloto'],
    ];

    public function __construct(private readonly CrearPersona $crearPersona) {}

    public function run(): void
    {
        foreach (self::OPERARIOS as $indice => [$username, $nombres, $paterno, $materno, $celular, $rol]) {
            $usuario = $this->usuarioPorUsername($username);

            if ($usuario === null || $usuario->persona_legada_id !== null) {
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

            // `persona_legada_id` no es asignable en masa: se escribe directo, sin pasar por el modelo.
            DB::table('sec_user')->where('id', $usuario->id)->update(['persona_legada_id' => $persona->id]);
        }
    }
}
