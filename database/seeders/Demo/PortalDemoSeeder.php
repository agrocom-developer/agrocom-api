<?php

namespace Database\Seeders\Demo;

use App\Dominios\Campania\Dominio\EstadoCampania;
use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraAutoria;
use App\Dominios\Operaciones\Aplicacion\FirmarActa;
use App\Dominios\Operaciones\Aplicacion\GenerarActaTrabajo;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosSesion;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosTrabajo;
use App\Dominios\Operaciones\Aplicacion\ValidarSesion;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\TipoEvidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Acta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\ReporteTecnico;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Dos cuentas de portal para probar el aislamiento a mano (tarea 65,
 * HU-41): `cliente.sanjorge` (contrato de {@see NucleoComercialSeeder}) y
 * `cliente.esperanza` (cliente nuevo, propio de esta tarea, con hectáreas y
 * precio bien distintos). Cada una con al menos una sesión validada, un
 * acta firmada y su reporte técnico — por el flujo real (máquinas de estado
 * + `ValidarSesion` + `GenerarActaTrabajo` + `FirmarActa`, invariantes 2, 3
 * y 7 de CLAUDE.md), nunca `Model::create(['estado' => ...])` a mano. Mismo
 * patrón que `OperacionDemoSeeder::sembrarTrabajos()/sembrarSesiones()/
 * sembrarActasYReportes()`, reducido a lo mínimo que el portal necesita
 * mostrar (avance, actas, reportes) — no reproduce recargas, condiciones ni
 * incidencias, que esta tarea no toca.
 *
 * Encadenado desde {@see DemostracionSeeder}, NO desde {@see DemoSeeder}
 * (la familia mínima que corren ~19 tests, que afirman sobre su TAMAÑO —
 * «una orden vigente», «un lote»: agregar un cliente, un contrato y una
 * orden más ahí adentro los rompe a todos, mismo motivo por el que
 * `OperacionDemoSeeder` tampoco vive en `DemoSeeder`). El cliente A depende
 * de que `NucleoComercialSeeder` ya haya corrido (vía `DemoSeeder`, que
 * `DemostracionSeeder` llama primero), y ambos flujos usan
 * {@see PersonalDemoSeeder::autorId()} como autor. Los tests de este
 * seeder siembran `DemoSeeder` + `PortalDemoSeeder` directo — ver
 * `tests/Feature/Demo/PortalDemoSeederTest.php`.
 *
 * Idempotente por guarda temprana (por cliente): si el trabajo demo de un
 * cliente ya existe, ese flujo se saltea entero — dos corridas de
 * `migrate --seed` no duplican nada. Las cuentas de portal, aparte, por
 * `firstOrCreate` de `username` (índice único parcial de `sec_user`).
 *
 * `created_by`/`updated_by` de las cuentas de portal quedan en NULL: mismo
 * criterio ya establecido en `PersonalDemoSeeder::run()` para las cuentas
 * `SecUser` que no son el autor — no hay usuario autenticado en un seeder
 * ({@see RegistraAutoria}),
 * y esta tarea no rompe esa convención.
 */
class PortalDemoSeeder extends Seeder
{
    private const NIT_ESPERANZA = '1044789013';

    private const USERNAME_SANJORGE = 'cliente.sanjorge';

    private const USERNAME_ESPERANZA = 'cliente.esperanza';

    private const PASSWORD_DEMO = 'password';

    public function __construct(
        private readonly MaquinaEstadosTrabajo $maquinaTrabajo,
        private readonly MaquinaEstadosSesion $maquinaSesion,
        private readonly ValidarSesion $validarSesion,
        private readonly GenerarActaTrabajo $generarActa,
        private readonly FirmarActa $firmarActa,
    ) {}

    public function run(): void
    {
        $autorId = PersonalDemoSeeder::autorId();

        $sanJorge = Cliente::query()->where('nit', '1023456022')->first();

        if ($sanJorge !== null) {
            $contratoSanJorge = Contrato::query()->where('cliente_id', $sanJorge->id)->first();

            if ($contratoSanJorge !== null) {
                $this->cuentaPortal(self::USERNAME_SANJORGE, 'Portal Agropecuaria San Jorge', $contratoSanJorge->id);
                $this->flujoOperativo('sanjorge', $contratoSanJorge, $autorId);
            }
        }

        $esperanza = $this->clienteEsperanza($autorId);
        $contratoEsperanza = Contrato::query()->where('cliente_id', $esperanza->id)->first();

        if ($contratoEsperanza !== null) {
            $this->cuentaPortal(self::USERNAME_ESPERANZA, 'Portal Estancia La Esperanza', $contratoEsperanza->id);
            $this->flujoOperativo('esperanza', $contratoEsperanza, $autorId);
        }
    }

    /**
     * Cliente B, con contrato de hectáreas y precio bien distintos de San
     * Jorge (4.000 ha × 65 Bs/ha): así se ve a simple vista, con los ojos,
     * que son dos clientes distintos — no solo dos nombres distintos.
     */
    private function clienteEsperanza(int $autorId): Cliente
    {
        $existente = Cliente::query()->where('nit', self::NIT_ESPERANZA)->first();

        if ($existente !== null) {
            return $existente;
        }

        $cliente = $this->crear(new Cliente([
            'razon_social' => 'Estancia La Esperanza S.A.',
            'nit' => self::NIT_ESPERANZA,
        ]), $autorId);

        $campania = $this->crear(new Campania([
            'cliente_id' => $cliente->id,
            'codigo' => '2025-2026',
            'fecha_inicio' => '2025-07-01',
            'fecha_fin' => '2026-06-30',
            'estado' => EstadoCampania::Abierta,
        ]), $autorId);

        $contrato = $this->crear(new Contrato([
            'cliente_id' => $cliente->id,
            'campania_id' => $campania->id,
            'hectareas_contratadas' => '850.00',
            'aplicaciones_previstas' => 3,
            'precio_ha' => '48.00',
            'monto_total' => '40800.00',
            'fecha_inicio' => '2026-08-15',
            'fecha_fin' => '2026-11-30',
            'estado' => EstadoContrato::Vigente,
        ]), $autorId);

        $campo = $this->crear(new Campo([
            'cliente_id' => $cliente->id,
            'nombre' => 'La Esperanza — Casco Central',
            'ubicacion' => 'Zona norte cruceña, km 30 camino a Okinawa, Santa Cruz, Bolivia',
        ]), $autorId);

        $lote = $this->crear(new Lote([
            'campo_id' => $campo->id,
            'codigo' => 'LE-01',
            'hectareas' => '300.00',
            'geometria' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [-63.1200, -16.9800],
                    [-63.1050, -16.9800],
                    [-63.1050, -16.9950],
                    [-63.1200, -16.9950],
                    [-63.1200, -16.9800],
                ]],
            ],
            'restricciones' => null,
        ]), $autorId);

        $this->crear(new OrdenAplicacion([
            'contrato_id' => $contrato->id,
            'lote_id' => $lote->id,
            'nro_aplicacion' => 1,
            'litros_ha' => '12.00',
            'fecha_emision' => '2026-08-20',
            'estado' => EstadoOrdenAplicacion::Vigente,
        ]), $autorId);

        return $cliente;
    }

    private function cuentaPortal(string $username, string $nombre, int $contratoId): void
    {
        SecUser::query()->firstOrCreate(
            ['username' => $username],
            [
                'name' => $nombre,
                'password' => self::PASSWORD_DEMO,
                'type' => TipoUsuario::Cliente,
                'contrato_id' => $contratoId,
                'state' => true,
            ],
        );
    }

    /**
     * Trabajo → sesión → validación → acta firmada → reporte técnico, por el
     * flujo real (nunca `estado` a mano). Idempotente: si el trabajo de
     * `$sufijo` ya existe, no repite nada.
     */
    private function flujoOperativo(string $sufijo, Contrato $contrato, int $autorId): void
    {
        $uuidTrabajo = "demo-portal-trabajo-{$sufijo}";

        if (Trabajo::query()->where('uuid_cliente', $uuidTrabajo)->exists()) {
            return;
        }

        $orden = OrdenAplicacion::query()->where('contrato_id', $contrato->id)->orderBy('nro_aplicacion')->first();

        if ($orden === null) {
            return; // falta el seeder previo: nada de qué colgar el trabajo
        }

        // `tarifa_ha` es obligatoria: `ValidarSesion` dispara `SesionValidada`
        // síncrono, y `Finanzas\GenerarDevengosSesion` (invariante 3) lanza
        // `PersonaSinTarifaHa` si el piloto no la tiene configurada.
        $piloto = PerPersona::query()->firstOrCreate(
            ['nombre' => "Piloto Portal Demo {$sufijo}"],
            ['rol' => RolOperativoPersona::Piloto, 'tarifa_ha' => '150.00', 'activo' => true],
        );

        $validador = PerPersona::query()->firstOrCreate(
            ['nombre' => "Jefe de Campo Portal Demo {$sufijo}"],
            ['rol' => RolOperativoPersona::JefeCampo, 'activo' => true],
        );

        $trabajo = $this->maquinaTrabajo->abrir([
            'uuid_cliente' => $uuidTrabajo,
            'orden_id' => $orden->id,
            'lote_id' => $orden->lote_id,
            'nro_aplicacion' => $orden->nro_aplicacion,
            'hectareas_declaradas' => '0',
            'inicio' => '2026-08-25T08:00:00-04:00',
        ]);
        $this->autoria($trabajo, $autorId);

        $sesion = $this->maquinaSesion->abrir([
            'uuid_cliente' => "demo-portal-sesion-{$sufijo}",
            'trabajo_id' => $trabajo->id,
            'secuencia' => 1,
            'piloto_id' => $piloto->id,
            'hectarea_inicial_acumulada' => '0.00',
            'inicio' => '2026-08-25T08:05:00-04:00',
        ]);
        $this->autoria($sesion, $autorId);

        $hectareasSesion = '12.00';

        $this->maquinaSesion->cerrar(
            $sesion,
            "demo-portal-cierre-sesion-{$sufijo}",
            '2026-08-25T10:30:00-04:00',
            'completado',
            $hectareasSesion,
        );

        $this->maquinaTrabajo->cerrar($trabajo, "demo-portal-cierre-trabajo-{$sufijo}", '2026-08-25T10:30:00-04:00');
        $trabajo->hectareas_declaradas = (string) Sesion::query()->where('trabajo_id', $trabajo->id)->sum('hectareas_declaradas');
        $trabajo->updated_by = $autorId;
        $trabajo->save();

        // Validador ≠ piloto de ESTA sesión, a nivel de persona (invariante
        // 4): son dos `PerPersona` distintas a propósito.
        $this->validarSesion->ejecutar($sesion, $validador->id);

        $acta = $this->generarActa->ejecutar($trabajo->refresh(), "demo-portal-acta-{$sufijo}");
        $this->autoria($acta, $autorId);

        // El archivo tiene que EXISTIR de verdad en el disco `r2` (ADR
        // 0009): a diferencia de `tests/Visual/fixtures/portal-demo.php`
        // (que solo lo referencia), `SeedDemoCompletaTest` comprueba que
        // toda evidencia sembrada por la demo tenga su archivo real.
        $evidenciaUuid = "demo-portal-firma-{$sufijo}";
        $rutaArchivo = "evidencias/firma_acta/2026/08/{$evidenciaUuid}.jpg";
        $contenidoArchivo = "Carta de conformidad — demo portal ({$sufijo})";
        Storage::disk('r2')->put($rutaArchivo, $contenidoArchivo);

        $evidencia = $this->crear(new Evidencia([
            'uuid_cliente' => $evidenciaUuid,
            'tipo' => TipoEvidencia::FirmaActa,
            'archivo_url' => $rutaArchivo,
            'hash' => hash('sha256', $contenidoArchivo),
            'fecha' => '2026-08-25T11:00:00-04:00',
        ]), $autorId);

        $this->firmarActa->ejecutar($acta, $evidencia->uuid_cliente, 'Ing. Agrónomo Demo', '2026-08-25T16:00:00-04:00');

        // El reporte lo genera FirmarActa por dentro (GenerarReporteTecnico):
        // no hay una referencia directa que devuelva, se recupera para
        // estampar la autoría demo (mismo criterio que Acta, arriba).
        $reporte = ReporteTecnico::query()->where('trabajo_id', $trabajo->id)->first();

        if ($reporte !== null) {
            $this->autoria($reporte, $autorId);
        }
    }

    /**
     * Persiste con autoría demo explícita. `created_by`/`updated_by` no son
     * fillable — mismo criterio que `NucleoComercialSeeder::crear()`.
     *
     * @template TModelo of ModeloDominio
     *
     * @param  TModelo  $modelo
     * @return TModelo
     */
    private function crear(ModeloDominio $modelo, int $autorId): ModeloDominio
    {
        $modelo->created_by = $autorId;
        $modelo->updated_by = $autorId;
        $modelo->save();

        return $modelo;
    }

    /**
     * Re-estampa la autoría demo sobre un modelo que ya nació por un caso de
     * uso/máquina de estados (que no la asigna: no hay usuario autenticado
     * en un seeder). Mismo criterio que `OperacionDemoSeeder::autoria()`.
     *
     * @template TModelo of ModeloDominio
     *
     * @param  TModelo  $modelo
     * @return TModelo
     */
    private function autoria(ModeloDominio $modelo, int $autorId): ModeloDominio
    {
        $modelo->created_by = $autorId;
        $modelo->updated_by = $autorId;
        $modelo->save();

        return $modelo;
    }
}
