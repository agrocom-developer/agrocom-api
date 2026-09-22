<?php

namespace Database\Seeders\Demo;

use App\Dominios\Personal\Aplicacion\CrearBase;
use App\Dominios\Personal\Aplicacion\CrearPersona;
use App\Dominios\Personal\Aplicacion\DatosPersona;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\Accesorio;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Aplicacion\ActualizarPreferenciaUsuario;
use App\Dominios\Seguridad\Aplicacion\EmitirTokenDispositivo;
use App\Dominios\Seguridad\Dominio\TemaPreferencia;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecTokenDispositivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Illuminate\Database\Seeder;

/**
 * Bases, personas, cuentas internas con sus roles, preferencias, tokens de
 * dispositivo y el catálogo de accesorios de cuadrilla.
 *
 * Se suma a las cuatro personas y la base que ya cargó el dueño (Miguelito
 * Justiniano, Jorge Sheidel, David Rios, Abraham Coimbra; CENTRAL SANTA
 * CRUZ). A las dos personas suyas con rol piloto se les crea también su
 * cuenta, para poder entrar a la app de campo con ellas.
 *
 * Todas las cuentas demo usan la contraseña `0000`, igual que `miguelo`.
 */
class PersonalDemoSeeder extends Seeder
{
    use SoporteDemo;

    public const PASSWORD = '0000';

    /**
     * CI de las personas que usan los demás seeders para encontrarlas.
     */
    public const CI_DUENO = '3456789';

    public const CI_ENCARGADA = '4567890';

    public const CI_JEFE_CAMPO = '5678901';

    public const CI_PILOTO_JOSUE = '6789012';

    public const CI_PILOTO_RODRIGO = '7890123';

    public const CI_AUXILIAR_LUIS = '8901234';

    public const CI_AUXILIAR_WILDER = '9012345';

    public const CI_AUXILIAR_DANIELA = '9123456';

    public const CI_AUXILIAR_PEDRO = '9234567';

    public const BASE_CENTRAL = 'CENTRAL SANTA CRUZ';

    public const BASE_PAILON = 'BASE PAILÓN';

    public const BASE_SAN_JULIAN = 'BASE SAN JULIÁN';

    /**
     * @var array<string, array{string, string, string}>
     */
    private const BASES = [
        self::BASE_PAILON => ['Carretera Bioceánica km 52, Pailón, Santa Cruz, Bolivia', '-16.847600', '-62.752300'],
        self::BASE_SAN_JULIAN => ['Km 130 carretera a San Julián, Santa Cruz, Bolivia', '-16.931400', '-62.611200'],
    ];

    /**
     * [nombres, apellido paterno, apellido materno, CI, celular, correo, rol operativo, base, username, roles de seguridad]
     *
     * @var list<array{string, string, string|null, string, string, string|null, RolOperativoPersona, string, string|null, list<string>}>
     */
    private const PERSONAS = [
        ['Carlos', 'Ferrufino', 'Vaca', self::CI_DUENO, '70011223', 'carlos.ferrufino@agrocom.demo', RolOperativoPersona::Dueno, self::BASE_CENTRAL, 'carlos.ferrufino', ['dueno']],
        ['Marcela', 'Antelo', 'Roca', self::CI_ENCARGADA, '70022334', 'marcela.antelo@agrocom.demo', RolOperativoPersona::EncargadoOperaciones, self::BASE_CENTRAL, 'marcela.antelo', ['encargado_operaciones']],
        ['Abraham', 'Gutiérrez', 'Contreras', self::CI_JEFE_CAMPO, '70033445', 'abraham.gutierrez@agrocom.demo', RolOperativoPersona::JefeCampo, self::BASE_PAILON, 'abraham.gutierrez', ['jefe_campo', 'piloto']],
        ['Josué', 'Haenke', 'Paz', self::CI_PILOTO_JOSUE, '70044556', 'josue.haenke@agrocom.demo', RolOperativoPersona::Piloto, self::BASE_PAILON, 'josue.haenke', ['piloto']],
        ['Rodrigo', 'Peña', 'Suárez', self::CI_PILOTO_RODRIGO, '70055667', 'rodrigo.pena@agrocom.demo', RolOperativoPersona::Piloto, self::BASE_SAN_JULIAN, 'rodrigo.pena', ['piloto']],
        ['Luis Fernando', 'Mamani', 'Quispe', self::CI_AUXILIAR_LUIS, '70066778', null, RolOperativoPersona::Auxiliar, self::BASE_PAILON, 'luis.mamani', ['auxiliar']],
        ['Wilder', 'Choque', 'Flores', self::CI_AUXILIAR_WILDER, '70077889', null, RolOperativoPersona::Auxiliar, self::BASE_PAILON, null, []],
        ['Daniela', 'Rojas', 'Vaca', self::CI_AUXILIAR_DANIELA, '70088990', 'daniela.rojas@agrocom.demo', RolOperativoPersona::Auxiliar, self::BASE_SAN_JULIAN, 'daniela.rojas', ['auxiliar']],
        ['Pedro', 'Vaca', 'Roca', self::CI_AUXILIAR_PEDRO, '70099001', null, RolOperativoPersona::Auxiliar, self::BASE_CENTRAL, null, []],
    ];

    /**
     * Cuentas para personas que ya cargó el dueño, ubicadas por CI.
     *
     * @var array<string, array{string, list<string>}>
     */
    private const CUENTAS_PERSONAS_EXISTENTES = [
        '81263712' => ['miguelito.justiniano', ['piloto']],
        '34121312' => ['david.rios', ['piloto']],
    ];

    /**
     * Dispositivos con token vivo: username → [uuid del dispositivo, nombre, rol].
     *
     * @var array<string, array{string, string, string}>
     */
    private const DISPOSITIVOS = [
        'josue.haenke' => ['dispositivo-josue', 'Samsung Galaxy A54 de Josué', 'piloto'],
        'rodrigo.pena' => ['dispositivo-rodrigo', 'Xiaomi Redmi Note 13 de Rodrigo', 'piloto'],
        'abraham.gutierrez' => ['dispositivo-abraham', 'Motorola Edge 40 de Abraham', 'jefe_campo'],
        'miguelito.justiniano' => ['dispositivo-miguelito', 'Samsung Galaxy S23 de Miguelito', 'piloto'],
    ];

    private const ACCESORIOS = [
        'Bidón de 20 L',
        'Embudo con filtro',
        'Balde graduado',
        'Probeta de 1 L',
        'Radio handy',
        'Botiquín',
        'Cargador de baterías',
        'Lona de sombra',
    ];

    public function __construct(
        private readonly CrearBase $crearBase,
        private readonly CrearPersona $crearPersona,
        private readonly ActualizarPreferenciaUsuario $actualizarPreferencia,
        private readonly EmitirTokenDispositivo $emitirToken,
    ) {}

    public function run(): void
    {
        $this->bases();
        $this->personas();
        $this->cuentasDePersonasExistentes();
        $this->preferencias();
        $this->dispositivos();
        $this->accesorios();
    }

    private function bases(): void
    {
        foreach (self::BASES as $nombre => [$ubicacion, $latitud, $longitud]) {
            if ($this->basePorNombre($nombre) === null) {
                $this->crearBase->ejecutar($nombre, $ubicacion, $latitud, $longitud);
            }
        }
    }

    private function personas(): void
    {
        foreach (self::PERSONAS as [$nombres, $paterno, $materno, $ci, $celular, $correo, $rol, $base, $username, $roles]) {
            $persona = $this->personaPorCi($ci);

            if ($persona === null) {
                $persona = $this->crearPersona->ejecutar(new DatosPersona(
                    nombres: $nombres,
                    apellidoPaterno: $paterno,
                    apellidoMaterno: $materno,
                    ci: $ci,
                    celular: $celular,
                    correo: $correo,
                    direccion: null,
                    rol: $rol,
                    baseId: $this->basePorNombre($base)?->id,
                ));
            }

            if ($username !== null) {
                $this->cuenta($persona, $username, $correo, $roles);
            }
        }

        // Pedro integró la cuadrilla de invierno que ya cerró: queda inactivo
        // para que el listado de personal muestre los dos casos.
        $pedro = $this->personaPorCi(self::CI_AUXILIAR_PEDRO);

        if ($pedro !== null && $pedro->activo) {
            $pedro->activo = false;
            $pedro->save();
        }
    }

    private function cuentasDePersonasExistentes(): void
    {
        foreach (self::CUENTAS_PERSONAS_EXISTENTES as $ci => [$username, $roles]) {
            $persona = $this->personaPorCi($ci);

            if ($persona !== null) {
                $this->cuenta($persona, $username, null, $roles);
            }
        }
    }

    /**
     * @param  list<string>  $roles
     */
    private function cuenta(PerPersona $persona, string $username, ?string $correo, array $roles): void
    {
        $usuario = $this->usuarioPorUsername($username);

        if ($usuario === null) {
            if (SecUser::query()->where('persona_id', $persona->id)->exists()) {
                return; // la persona ya tiene otra cuenta: un login por persona
            }

            $usuario = new SecUser([
                'name' => $persona->nombre,
                'username' => $username,
                'email' => $correo,
                'password' => self::PASSWORD,
                'type' => TipoUsuario::Interno,
                'persona_id' => $persona->id,
                'state' => true,
            ]);
            $usuario->save();
        }

        foreach ($roles as $nombreRol) {
            $rol = SecRole::query()->where('name', $nombreRol)->first();

            if ($rol === null) {
                continue;
            }

            $yaAsignado = SecUserRole::withTrashed()
                ->where('id_user', $usuario->id)
                ->where('id_role', $rol->id)
                ->exists();

            if (! $yaAsignado) {
                (new SecUserRole(['id_user' => $usuario->id, 'id_role' => $rol->id]))->save();
            }
        }
    }

    private function preferencias(): void
    {
        $catalogo = [
            'carlos.ferrufino' => [TemaPreferencia::Oscuro, 'America/La_Paz'],
            'marcela.antelo' => [TemaPreferencia::Claro, 'America/La_Paz'],
            'abraham.gutierrez' => [TemaPreferencia::Oscuro, null],
        ];

        foreach ($catalogo as $username => [$tema, $zona]) {
            $usuario = $this->usuarioPorUsername($username);

            if ($usuario !== null && $usuario->preferencia === null) {
                $this->actualizarPreferencia->ejecutar($usuario, $tema, 'es', $zona);
            }
        }
    }

    private function dispositivos(): void
    {
        foreach (self::DISPOSITIVOS as $username => [$uuid, $nombre, $rol]) {
            $usuario = $this->usuarioPorUsername($username);
            $rolId = SecRole::query()->where('name', $rol)->value('id');

            if ($usuario === null || $rolId === null) {
                continue;
            }

            $uuidDispositivo = $this->uuid('dispositivo', $uuid);

            $existe = SecTokenDispositivo::query()
                ->where('user_id', $usuario->id)
                ->where('uuid_dispositivo', $uuidDispositivo)
                ->exists();

            if (! $existe) {
                $this->emitirToken->ejecutar($usuario, $uuidDispositivo, $nombre, (int) $rolId);
            }
        }
    }

    private function accesorios(): void
    {
        foreach (self::ACCESORIOS as $nombre) {
            $existe = Accesorio::query()
                ->whereRaw('lower(nombre) = ?', [mb_strtolower($nombre)])
                ->exists();

            if (! $existe) {
                Accesorio::query()->create(['nombre' => $nombre, 'activo' => true]);
            }
        }
    }
}
