<?php

namespace Database\Seeders\Demo;

use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosSesion;
use App\Dominios\Operaciones\Aplicacion\ValidarSesion;
use App\Dominios\Operaciones\Dominio\CausaPausa;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Pausa;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Actividad RECIENTE, para que el dashboard se vea poblado (tarea 67).
 *
 * `OperacionDemoSeeder` siembra el relato de las capturas reales del control
 * remoto, con fechas fijas de agosto de 2026: es el material que sostiene
 * actas, reportes y facturas, y por eso sus fechas no se mueven. Pero el
 * dashboard mira los últimos días —hectáreas por día, cola de validación,
 * pausas del mes—, así que contra ese set aparece vacío desde septiembre.
 *
 * Este seeder agrega, encima y sin tocar nada de lo anterior, unos días de
 * actividad RELATIVOS A HOY:
 *
 * - seis sesiones en los últimos doce días, con hectáreas distintas por día
 *   (la curva del área deja de ser plana);
 * - dos de ellas quedan en `cerrado`, que es lo que llena la cola de
 *   validación del jefe de campo — el resto se valida por el flujo real
 *   ({@see ValidarSesion}), que es lo único que genera devengo (invariante 3);
 * - dos pausas con causa dentro del mes en curso.
 *
 * Todo por el flujo real y todo idempotente: los `uuid_cliente` se derivan de
 * una clave estable, así que una segunda corrida encuentra la sesión y la
 * saltea en vez de duplicar hectáreas (invariante 1). Nada se borra.
 *
 * La consecuencia de sembrar contra "hoy" es que las capturas de regresión
 * visual del dashboard no pueden compararse contra fechas literales: el spec
 * fija el reloj o enmascara la columna de fecha.
 */
class DashboardDemoSeeder extends Seeder
{
    /**
     * clave => [díasAtrás, hectáreas, litros, minutosDeVuelo, ¿queda sin
     * validar?, índiceDePiloto, índiceDeDron]
     *
     * Las hectáreas son distintas entre sí a propósito: con valores iguales
     * el área de "hectáreas por día" sale como una meseta y no se distingue
     * de un gráfico roto.
     *
     * Los pilotos rotan para que más de una persona vea su propio tablero
     * poblado al entrar, y los drones también: sin `dron_id` la sección "mis
     * equipos" —que se deriva de las sesiones, no de una tabla de asignación—
     * no tendría de dónde salir.
     */
    private const SESIONES = [
        'D-01' => [11, '42.50', '340.00', 95, false, 0, 0],
        'D-02' => [9, '28.75', '230.00', 70, false, 1, 1],
        'D-03' => [7, '51.20', '410.00', 115, false, 0, 0],
        'D-04' => [4, '33.40', '265.00', 80, false, 1, 0],
        'D-05' => [2, '46.90', '375.00', 105, true, 0, 1],
        'D-06' => [1, '19.60', '155.00', 50, true, 1, 1],
    ];

    /**
     * Motivo de cierre: columna simple sin enum propio en el dominio (el
     * catálogo vive en la espec §4.3 y lo valida la app de campo). `completado`
     * es el caso normal — la sesión terminó el lote sin excepción.
     */
    private const MOTIVO_CIERRE = 'completado';

    /** clave de sesión => [causa, minutos] */
    private const PAUSAS = [
        // En las dos sesiones más recientes a propósito: el agregado de
        // pausas es por MES CALENDARIO (mismo criterio que el badge del menú
        // y que `/panel/pausas`), así que colgarlas de una sesión de hace
        // diez días las deja fuera del mes durante la primera semana.
        'D-05' => [CausaPausa::Clima, 12],
        'D-06' => [CausaPausa::FallaEquipo, 8],
    ];

    public function __construct(
        private readonly MaquinaEstadosSesion $maquinaSesion,
        private readonly ValidarSesion $validarSesion,
    ) {}

    public function run(): void
    {
        $orden = OrdenAplicacion::query()->orderBy('id')->first();
        $lote = Lote::query()->orderBy('id')->first();
        $pilotos = PerPersona::query()->where('rol', 'piloto')->orderBy('id')->pluck('id')->all();
        $auxiliar = PerPersona::query()->where('rol', 'auxiliar')->orderBy('id')->first();
        $validador = PerPersona::query()->where('rol', 'encargado_operaciones')->orderBy('id')->first();
        $drones = Dron::query()->orderBy('id')->pluck('id')->all();
        $autorId = (int) (PerPersona::query()->min('id') ?? 1);

        // Sin el escenario base no hay dónde colgar la actividad. Se sale en
        // silencio en vez de reventar: este seeder es material de
        // demostración, no una precondición del sistema.
        if ($orden === null || $lote === null || $pilotos === [] || $validador === null) {
            return;
        }

        $trabajo = $this->trabajo($orden, $lote, $autorId);

        foreach (self::SESIONES as $clave => [$diasAtras, $hectareas, $litros, $minutos, $quedaCerrada, $iPiloto, $iDron]) {
            $piloto = $pilotos[$iPiloto] ?? $pilotos[0];

            // Invariante 4, a nivel de PERSONA: el validador nunca es el
            // piloto de esta sesión. El encargado no vuela, así que la
            // coincidencia no debería darse; si el catálogo cambiara y se
            // diera, se saltea la sesión en vez de sembrar algo que
            // `ValidarSesion` va a rechazar.
            if ($validador->id === $piloto) {
                continue;
            }

            $this->sesion($trabajo, $clave, $diasAtras, $hectareas, $litros, $minutos, $quedaCerrada, [
                'piloto' => $piloto,
                'auxiliar' => $auxiliar?->id,
                'validador' => $validador->id,
                'dron' => $drones[$iDron] ?? null,
            ], $autorId);
        }
    }

    /**
     * Un trabajo propio para esta actividad, en vez de colgarse de uno de
     * `OperacionDemoSeeder`: aquellos ya están CERRADOS con su acta firmada y
     * su factura emitida, y agregarles sesiones nuevas descuadraría hectáreas
     * ya facturadas.
     */
    private function trabajo(OrdenAplicacion $orden, Lote $lote, int $autorId): Trabajo
    {
        $uuid = $this->uuid('trabajo', 'dashboard');

        $existente = Trabajo::query()->where('uuid_cliente', $uuid)->first();

        if ($existente !== null) {
            return $existente;
        }

        $trabajo = new Trabajo([
            'uuid_cliente' => $uuid,
            'orden_id' => $orden->id,
            'lote_id' => $lote->id,
            'nro_aplicacion' => 1,
            'hectareas_declaradas' => '0.00',
            'inicio' => Carbon::today()->subDays(12)->setTime(9, 0),
        ]);

        return $this->crear($trabajo, $autorId);
    }

    /**
     * @param  array{piloto: int, auxiliar: int|null, validador: int, dron: int|null}  $personas
     */
    private function sesion(
        Trabajo $trabajo,
        string $clave,
        int $diasAtras,
        string $hectareas,
        string $litros,
        int $minutos,
        bool $quedaCerrada,
        array $personas,
        int $autorId,
    ): void {
        $uuid = $this->uuid('sesion', $clave);

        // Idempotencia por `uuid_cliente` (invariante 1): la segunda corrida
        // encuentra la sesión y no vuelve a sumar sus hectáreas.
        if (Sesion::query()->where('uuid_cliente', $uuid)->exists()) {
            return;
        }

        $inicio = Carbon::today()->subDays($diasAtras)->setTime(9, 30);
        $fin = $inicio->copy()->addMinutes($minutos);

        $sesion = $this->maquinaSesion->abrir([
            'uuid_cliente' => $uuid,
            'trabajo_id' => $trabajo->id,
            'secuencia' => (int) substr($clave, -2),
            'piloto_id' => $personas['piloto'],
            'auxiliar_id' => $personas['auxiliar'],
            'dron_id' => $personas['dron'],
            'hectarea_inicial_acumulada' => '0.00',
            'inicio' => $inicio->toDateTimeString(),
        ]);

        $this->autoria($sesion, $autorId);
        $this->pausa($sesion, $clave, $inicio, $autorId);

        $this->maquinaSesion->cerrar(
            $sesion,
            $this->uuid('cierre', $clave),
            $fin->toDateTimeString(),
            self::MOTIVO_CIERRE,
            $hectareas,
        );

        $sesion->litros_consumidos = $litros;
        $sesion->save();

        // Las dos últimas quedan sin validar a propósito: son la cola del
        // jefe de campo. Validar es lo único que genera devengo (invariante
        // 3) y el validador nunca es el piloto de la sesión (invariante 4).
        if (! $quedaCerrada) {
            $this->validarSesion->ejecutar($sesion, $personas['validador']);
        }
    }

    private function pausa(Sesion $sesion, string $clave, Carbon $inicio, int $autorId): void
    {
        if (! isset(self::PAUSAS[$clave])) {
            return;
        }

        [$causa, $minutos] = self::PAUSAS[$clave];

        $desde = $inicio->copy()->addMinutes(20);

        $this->crear(new Pausa([
            'sesion_id' => $sesion->id,
            'causa' => $causa,
            'inicio' => $desde->toDateTimeString(),
            'fin' => $desde->copy()->addMinutes($minutos)->toDateTimeString(),
            'duracion_minutos' => $minutos,
        ]), $autorId);
    }

    /** UUID determinístico: misma clave, mismo uuid, en toda corrida. */
    private function uuid(string $tipo, string $clave): string
    {
        $hash = md5("agrocom-dashboard:{$tipo}:{$clave}");

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }

    /**
     * @template T of ModeloDominio
     *
     * @param  T  $modelo
     * @return T
     */
    private function crear(ModeloDominio $modelo, int $autorId): ModeloDominio
    {
        $modelo->created_by = $autorId;
        $modelo->updated_by = $autorId;
        $modelo->save();

        return $modelo;
    }

    /** En un seeder no hay usuario autenticado: `RegistraAutoria` dejaría NULL. */
    private function autoria(ModeloDominio $modelo, int $autorId): void
    {
        $modelo->created_by ??= $autorId;
        $modelo->updated_by = $autorId;
        $modelo->save();
    }
}
