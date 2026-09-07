<?php

namespace Database\Seeders\Demo;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraAutoria;
use App\Dominios\Finanzas\Aplicacion\GenerarDevengosSesion;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Illuminate\Database\Seeder;

/**
 * La cuadrilla de Agrocom: dos bases, seis personas y la cuenta de cada una.
 * Reemplaza a `PanelDemoSeeder`, que sembraba una sola usuaria multirol
 * inventada (`camila.rojas`) sin persona vinculada, y al usuario `demo` de
 * `NucleoComercialSeeder`, que existía únicamente para firmar la autoría de
 * las filas del catálogo comercial.
 *
 * ### Son personas reales
 *
 * Los seis nombres salen del banco de respuestas de campo
 * (`docs/gestion/respuestas_campo/`): cada cuestionario abre pidiendo «Tu
 * nombre completo», y quien lo contestó lo hizo desde el rol que ejerce. El
 * rol operativo de cada uno acá es el cuestionario que contestó, no una
 * asignación inventada:
 *
 * - Josue Haenke y Miguelito Justiniano Dorado contestaron el de Piloto.
 * - David Omar Ríos Lino, el de Auxiliar.
 * - Abraham Gutiérrez Contreras, el de Jefe de campo (y también el de
 *   Auxiliar: es el más veterano, cuatro campañas, y cubre las dos puntas).
 * - Jorge Richard Scheidel Dorado, el de Encargado de operaciones.
 * - Carlos Ferrufino contestó CUATRO —agrónomo, jefe de campo, encargado y
 *   piloto— y es el único que no declara su función en ninguno, porque es el
 *   dueño: hace de todo. Por eso es él, y no una cuenta inventada, quien
 *   ejercita el multirol del panel.
 *
 * ### Por qué toda cuenta interna nace con `persona_id`
 *
 * `sec_user.persona_id` es nullable —vincular la cuenta a una persona es
 * opcional en el ABM (HU-45)— y los seeders anteriores nunca lo llenaban. El
 * resultado era que la única pantalla del panel que resuelve por PERSONA y no
 * por rol, `GET /panel/devengos` (HU-28), devolvía 404 a todo usuario demo:
 * el permiso estaba, el sujeto no. Acá cada cuenta interna nace vinculada,
 * que es además como se usa en la realidad — la cuenta es de alguien.
 * (El agujero en sí lo tapa `sec_menu.requiere_persona`, que oculta el ítem
 * cuando el usuario no tiene persona; esto es lo que hace que en la demo no
 * haga falta ocultarlo.)
 *
 * ### Multirol, con el caso que la invariante 4 vuelve interesante
 *
 * Un usuario, un login, varios roles asignados, UN rol activo por sesión
 * (CLAUDE.md invariante 10). Dos cuentas lo ejercitan de verdad:
 * - `carlos.ferrufino` con los cuatro roles que contestó en las encuestas:
 *   `dueno` + `encargado_operaciones` + `jefe_campo` + `piloto`. Es la que
 *   usan los tests visuales para la pantalla de selección de rol (necesita
 *   2+ roles vivos) y, con `dueno` activo, la que abre TODAS las pantallas
 *   del panel — los 91 permisos del catálogo.
 * - `abraham.gutierrez` con `jefe_campo` + `auxiliar`: el jefe de campo que
 *   además vuela cuando hace falta. Es el caso de la invariante 4 —validador
 *   ≠ piloto DE ESA SESIÓN, a nivel de persona y no de rol—, así que en
 *   `OperacionDemoSeeder` valida las sesiones de los demás, pero la suya la
 *   valida el encargado de operaciones.
 *
 * `tarifa_ha` va en toda persona que pueda volar o asistir un vuelo, jefe de
 * campo y dueño incluidos: {@see GenerarDevengosSesion} lanza
 * `PersonaSinTarifaHa` si el piloto o el auxiliar de una sesión validada no
 * la tiene, y tanto Abraham como Carlos vuelan. El único que nunca sube a un
 * control remoto —Jorge, el encargado— la deja en `null`.
 *
 * Idempotente: `firstOrCreate` por nombre de persona, por username y por el
 * par (usuario, rol).
 */
class PersonalDemoSeeder extends Seeder
{
    /** Contraseña única para toda la demo — local y staging, nunca producción. */
    private const PASSWORD_DEMO = 'password';

    /** Cuenta que firma la autoría de todo lo que siembra la familia demo. */
    public const AUTOR = 'carlos.ferrufino';

    /**
     * Nombre → [rol operativo, tarifa_ha o null, base, username, roles `sec_role`].
     *
     * El orden importa poco salvo por uno: `carlos.ferrufino` va primero
     * porque es el autor (`created_by`) de todo lo que siembran los demás
     * seeders — y que la autoría del catálogo demo sea del dueño es lo más
     * parecido a la realidad que se puede sembrar sin usuario autenticado.
     *
     * @var list<array{string, RolOperativoPersona, string|null, string, string, list<string>}>
     */
    private const CUADRILLA = [
        ['Carlos Ferrufino', RolOperativoPersona::Dueno, '150.00', 'Cuatro Cañadas', 'carlos.ferrufino', ['dueno', 'encargado_operaciones', 'jefe_campo', 'piloto']],
        ['Jorge Richard Scheidel Dorado', RolOperativoPersona::EncargadoOperaciones, null, 'Cuatro Cañadas', 'jorge.scheidel', ['encargado_operaciones']],
        ['Abraham Gutiérrez Contreras', RolOperativoPersona::JefeCampo, '150.00', 'Cuatro Cañadas', 'abraham.gutierrez', ['jefe_campo', 'auxiliar']],
        ['Josue Haenke', RolOperativoPersona::Piloto, '150.00', 'Cuatro Cañadas', 'josue.haenke', ['piloto']],
        ['Miguelito Justiniano Dorado', RolOperativoPersona::Piloto, '145.00', 'Pailón', 'miguelito.justiniano', ['piloto', 'auxiliar']],
        ['David Omar Ríos Lino', RolOperativoPersona::Auxiliar, '60.00', 'Pailón', 'david.rios', ['auxiliar']],
    ];

    /** @var array<string, string> nombre de base → ubicación */
    private const BASES = [
        'Cuatro Cañadas' => 'Km 12 camino a Cuatro Cañadas, Santa Cruz, Bolivia',
        'Pailón' => 'Av. Circunvalación s/n, Pailón, Santa Cruz, Bolivia',
    ];

    public function run(): void
    {
        $bases = [];

        foreach (self::BASES as $nombre => $ubicacion) {
            $bases[$nombre] = PerBase::query()->firstOrCreate(
                ['nombre' => $nombre],
                ['ubicacion' => $ubicacion],
            );
        }

        $autorId = null;

        foreach (self::CUADRILLA as [$nombre, $rolOperativo, $tarifaHa, $base, $username, $rolesSeguridad]) {
            $persona = PerPersona::query()->firstOrCreate(
                ['nombre' => $nombre],
                [
                    'rol' => $rolOperativo,
                    'tarifa_ha' => $tarifaHa,
                    'base_id' => $bases[$base]->id,
                    'activo' => true,
                ],
            );

            $usuario = SecUser::query()->firstOrCreate(
                ['username' => $username],
                [
                    'name' => $nombre,
                    'password' => self::PASSWORD_DEMO,
                    'type' => TipoUsuario::Interno,
                    'persona_id' => $persona->id,
                ],
            );

            // Una cuenta sembrada por una vuelta anterior de este seeder (o
            // por `PanelDemoSeeder`, que no llenaba el campo) puede existir
            // sin persona: completarla es idempotente y nunca pisa un
            // vínculo ya hecho a mano.
            if ($usuario->persona_id === null) {
                $usuario->persona_id = $persona->id;
                $usuario->save();
            }

            $autorId ??= $usuario->id;

            $this->conAutoria($persona, $autorId);
            $this->asignarRoles($usuario, $rolesSeguridad, $autorId);
        }

        foreach ($bases as $baseCreada) {
            $this->conAutoria($baseCreada, (int) $autorId);
        }
    }

    /**
     * El id que el resto de los seeders demo usa como `created_by`/
     * `updated_by`: en un seeder no hay usuario autenticado, así que
     * {@see RegistraAutoria} dejaría la autoría en NULL.
     */
    public static function autorId(): int
    {
        return (int) SecUser::query()->where('username', self::AUTOR)->value('id');
    }

    /**
     * @param  list<string>  $nombresDeRol
     */
    private function asignarRoles(SecUser $usuario, array $nombresDeRol, int $autorId): void
    {
        foreach ($nombresDeRol as $nombreRol) {
            $rol = SecRole::query()->where('name', $nombreRol)->first();

            if ($rol === null) {
                continue; // catálogo de roles no sembrado — nada que asignar
            }

            $yaAsignado = SecUserRole::query()
                ->where('id_user', $usuario->id)
                ->where('id_role', $rol->id)
                ->exists();

            if ($yaAsignado) {
                continue;
            }

            $this->conAutoria(new SecUserRole([
                'id_user' => $usuario->id,
                'id_role' => $rol->id,
            ]), $autorId);
        }
    }

    /**
     * Persiste con autoría explícita. `created_by`/`updated_by` no son
     * fillable — mismo criterio que `NucleoComercialSeeder::crear()`.
     *
     * @template TModelo of ModeloDominio
     *
     * @param  TModelo  $modelo
     * @return TModelo
     */
    private function conAutoria(ModeloDominio $modelo, int $autorId): ModeloDominio
    {
        if ($modelo->created_by !== null && $modelo->updated_by !== null) {
            return $modelo;
        }

        $modelo->created_by = $autorId;
        $modelo->updated_by = $autorId;
        $modelo->save();

        return $modelo;
    }
}
