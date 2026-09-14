<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Dominio\EstadoAlerta;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Alerta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Bandeja de alertas por excepción (HU-19, tarea 26): de las 13 condiciones
 * de la espec §10, solo 4 tienen datos reales detrás hoy — batería caliente
 * (`ope_recargas.alerta_temperatura`, tarea 23), dron sospechoso (3+ de esas
 * sobre el mismo dron), condiciones forzadas (`ope_condiciones.autorizado =
 * false`, tarea 17) y suma excedida (`EstadoCoberturaTrabajo::Observado`,
 * tarea 20). Ver el prompt de la tarea y `docs/gestion/cola_tareas.md` para
 * el porqué del recorte.
 *
 * Dos grupos, con auth distinta (nombres de función propios en cada uno —
 * Pest declara los helpers como funciones PHP globales, ver docblock de
 * `CierreSincronizacionTest.php`):
 *   - Generación: vía `POST /api/sync` (Sanctum, mismo patrón que
 *     `RecargaSincronizacionTest`/`CondicionesSincronizacionTest`).
 *   - Bandeja del panel: `GET /panel/alertas`, `POST .../atender` (sesión
 *     `interno`, mismo patrón que `TrabajosPanelTest`).
 *
 * routes/api.php es EXCLUSIVO de las apps de campo (ADR 0008): la bandeja del
 * encargado vive en routes/web.php (`/panel/alertas`), no en
 * `/api/alertas` como nombraba el prompt — ver el docblock de
 * `AlertasController` y runs/26.md.
 */

uses(RefreshDatabase::class);

// ── Generación de alertas, vía POST /api/sync ──────────────────────────────

describe('generación de alertas por excepción', function () {
    beforeEach(function () {
        $this->seed(DemoSeeder::class);
        $this->actingAs(SecUser::factory()->create(), 'sanctum');
    });

    function ordenParaAlerta(): OrdenAplicacion
    {
        $loteId = Lote::query()->where('codigo', 'L-01')->value('id');

        return OrdenAplicacion::query()
            ->whereHas('ordenLotes', fn ($q) => $q->where('lote_id', $loteId))
            ->where('estado', EstadoOrdenAplicacion::Vigente)
            ->firstOrFail();
    }

    function pilotoParaAlerta(string $nombre = 'Piloto Alerta'): PerPersona
    {
        return PerPersona::query()->create(['nombre' => $nombre, 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
    }

    /** @return array<string, mixed> */
    function registroTrabajoParaAlerta(string $uuidCliente): array
    {
        $orden = ordenParaAlerta();

        return [
            'tipo' => 'trabajo',
            'uuid_cliente' => $uuidCliente,
            'orden_id' => $orden->id,
            'lote_id' => (int) $orden->ordenLotes()->value('lote_id'),
            'nro_aplicacion' => $orden->nro_aplicacion,
            'inicio' => '2026-09-01T10:00:00-04:00',
        ];
    }

    /** @return array<string, mixed> */
    function registroSesionParaAlerta(string $uuidCliente, string $trabajoUuidCliente, int $pilotoId, array $sobrescribir = []): array
    {
        return array_merge([
            'tipo' => 'sesion',
            'uuid_cliente' => $uuidCliente,
            'trabajo_uuid_cliente' => $trabajoUuidCliente,
            'secuencia' => 1,
            'piloto_id' => $pilotoId,
            'inicio' => '2026-09-01T10:05:00-04:00',
        ], $sobrescribir);
    }

    /** @return array<string, mixed> */
    function registroRecargaParaAlerta(string $uuidCliente, string $sesionUuidCliente, array $sobrescribir = []): array
    {
        return array_merge([
            'tipo' => 'recarga',
            'uuid_cliente' => $uuidCliente,
            'sesion_uuid_cliente' => $sesionUuidCliente,
            'secuencia' => 1,
            'litros_caldo' => '30.00',
            'bateria_saliente_id' => 'BAT-01',
            'temperatura_bateria_c' => '35.00',
            'hora' => '2026-09-01T10:15:00-04:00',
        ], $sobrescribir);
    }

    /** @return array<string, mixed> */
    function registroCondicionesParaAlerta(string $uuidCliente, string $sesionUuidCliente, array $sobrescribir = []): array
    {
        return array_merge([
            'tipo' => 'condiciones',
            'uuid_cliente' => $uuidCliente,
            'sesion_uuid_cliente' => $sesionUuidCliente,
            'momento' => 'inicio_sesion',
            'viento_kmh' => '10.00',
            'temperatura_c' => '22.00',
            'humedad_pct' => '60.00',
        ], $sobrescribir);
    }

    /** @return array<string, mixed> */
    function registroCierreSesionParaAlerta(string $uuidCliente, string $sesionUuidCliente, string $hectareasDeclaradas, string $motivo = 'completado'): array
    {
        return [
            'tipo' => 'cierre_sesion',
            'uuid_cliente' => $uuidCliente,
            'sesion_uuid_cliente' => $sesionUuidCliente,
            'fin' => '2026-09-01T12:00:00-04:00',
            'motivo_cierre' => $motivo,
            'hectareas_declaradas' => $hectareasDeclaradas,
        ];
    }

    /*
     * ── Criterio 1 y 2: batería caliente ──
     */

    it('una recarga con temperatura > 50°C genera una alerta bateria_caliente pendiente', function () {
        $piloto = pilotoParaAlerta();
        $this->postJson('/api/sync', ['registros' => [
            registroTrabajoParaAlerta('uuid-t-bat1'),
            registroSesionParaAlerta('uuid-s-bat1', 'uuid-t-bat1', $piloto->id),
        ]])->assertOk();

        $this->postJson('/api/sync', ['registros' => [
            registroRecargaParaAlerta('uuid-r-bat1', 'uuid-s-bat1', ['temperatura_bateria_c' => '55.00']),
        ]])->assertOk();

        $alerta = Alerta::query()->where('tipo', 'bateria_caliente')->sole();
        expect($alerta->estado)->toBe(EstadoAlerta::Pendiente)
            ->and($alerta->recarga_id)->not->toBeNull()
            ->and($alerta->sesion_id)->not->toBeNull()
            ->and($alerta->mensaje)->not->toBeEmpty();
    });

    it('una recarga con temperatura normal no genera ninguna alerta', function () {
        $piloto = pilotoParaAlerta();
        $this->postJson('/api/sync', ['registros' => [
            registroTrabajoParaAlerta('uuid-t-bat2'),
            registroSesionParaAlerta('uuid-s-bat2', 'uuid-t-bat2', $piloto->id),
        ]])->assertOk();

        $this->postJson('/api/sync', ['registros' => [
            registroRecargaParaAlerta('uuid-r-bat2', 'uuid-s-bat2', ['temperatura_bateria_c' => '35.00']),
        ]])->assertOk();

        expect(Alerta::query()->count())->toBe(0);
    });

    /*
     * ── Criterio 3: dron sospechoso, sin duplicar con la 4ta recarga caliente ──
     */

    it('tres recargas calientes sobre el mismo dron generan dron_sospechoso una sola vez', function () {
        $piloto = pilotoParaAlerta();
        $dron = Dron::create(['identificador' => 'DJI-ALERTA-01']);

        $this->postJson('/api/sync', ['registros' => [registroTrabajoParaAlerta('uuid-t-dron')]])->assertOk();
        $this->postJson('/api/sync', ['registros' => [
            registroSesionParaAlerta('uuid-s-dron', 'uuid-t-dron', $piloto->id, ['dron_id' => $dron->id]),
        ]])->assertOk();

        foreach ([1, 2, 3, 4] as $secuencia) {
            $this->postJson('/api/sync', ['registros' => [
                registroRecargaParaAlerta("uuid-r-dron-{$secuencia}", 'uuid-s-dron', [
                    'secuencia' => $secuencia,
                    'temperatura_bateria_c' => '60.00',
                ]),
            ]])->assertOk();
        }

        expect(Alerta::query()->where('tipo', 'bateria_caliente')->count())->toBe(4)
            ->and(Alerta::query()->where('tipo', 'dron_sospechoso')->count())->toBe(1);

        $alertaDron = Alerta::query()->where('tipo', 'dron_sospechoso')->sole();
        expect($alertaDron->dron_id)->toBe($dron->id);
    });

    /*
     * ── Criterio 4: condiciones forzadas ──
     */

    it('condiciones fuera de rango autorizadas con observación generan condiciones_forzadas', function () {
        $piloto = pilotoParaAlerta();
        $this->postJson('/api/sync', ['registros' => [
            registroTrabajoParaAlerta('uuid-t-cond'),
            registroSesionParaAlerta('uuid-s-cond', 'uuid-t-cond', $piloto->id),
        ]])->assertOk();

        $this->postJson('/api/sync', ['registros' => [
            registroCondicionesParaAlerta('uuid-c-cond', 'uuid-s-cond', [
                'viento_kmh' => '20.00',
                'observacion_agronomo' => 'ventana angosta, se autoriza igual',
                'firma_observacion' => 'Agr. Gómez',
            ]),
        ]])->assertOk();

        $alerta = Alerta::query()->where('tipo', 'condiciones_forzadas')->sole();
        expect($alerta->estado)->toBe(EstadoAlerta::Pendiente)
            ->and($alerta->condiciones_id)->not->toBeNull();
    });

    it('condiciones dentro de rango no generan ninguna alerta', function () {
        $piloto = pilotoParaAlerta();
        $this->postJson('/api/sync', ['registros' => [
            registroTrabajoParaAlerta('uuid-t-cond2'),
            registroSesionParaAlerta('uuid-s-cond2', 'uuid-t-cond2', $piloto->id),
        ]])->assertOk();

        $this->postJson('/api/sync', ['registros' => [
            registroCondicionesParaAlerta('uuid-c-cond2', 'uuid-s-cond2'),
        ]])->assertOk();

        expect(Alerta::query()->count())->toBe(0);
    });

    /*
     * ── Criterio 5: suma excedida, sin duplicar cuando otra sesión mantiene Observado ──
     */

    it('cerrar una sesión que supera las hectáreas del lote más la tolerancia genera suma_excedida', function () {
        config(['operaciones.tolerancia_solape_hectareas' => '0.00']);
        $piloto = pilotoParaAlerta();

        $this->postJson('/api/sync', ['registros' => [
            registroTrabajoParaAlerta('uuid-t-suma'),
            registroSesionParaAlerta('uuid-s-suma-1', 'uuid-t-suma', $piloto->id),
            registroSesionParaAlerta('uuid-s-suma-2', 'uuid-t-suma', $piloto->id, ['secuencia' => 2]),
        ]])->assertOk();

        $trabajo = Trabajo::query()->where('uuid_cliente', 'uuid-t-suma')->firstOrFail();

        // L-01 tiene 250.00 ha (NucleoComercialSeeder) — la primera sesión ya
        // la supera.
        $this->postJson('/api/sync', ['registros' => [
            registroCierreSesionParaAlerta('uuid-cierre-suma-1', 'uuid-s-suma-1', '260.00'),
        ]])->assertOk();

        $alerta = Alerta::query()->where('tipo', 'suma_excedida')->sole();
        expect($alerta->trabajo_id)->toBe($trabajo->id)
            ->and($alerta->estado)->toBe(EstadoAlerta::Pendiente);

        // Una SEGUNDA sesión del mismo trabajo cierra y también deja la
        // cobertura Observado (la suma solo crece) — no duplica la alerta:
        // el índice único parcial por trabajo_id es la guarda real.
        $this->postJson('/api/sync', ['registros' => [
            registroCierreSesionParaAlerta('uuid-cierre-suma-2', 'uuid-s-suma-2', '5.00'),
        ]])->assertOk();

        expect(Alerta::query()->where('tipo', 'suma_excedida')->count())->toBe(1);
    });

    it('cerrar una sesión dentro de las hectáreas del lote no genera suma_excedida', function () {
        config(['operaciones.tolerancia_solape_hectareas' => '0.00']);
        $piloto = pilotoParaAlerta();

        $this->postJson('/api/sync', ['registros' => [
            registroTrabajoParaAlerta('uuid-t-suma-ok'),
            registroSesionParaAlerta('uuid-s-suma-ok', 'uuid-t-suma-ok', $piloto->id),
        ]])->assertOk();

        $this->postJson('/api/sync', ['registros' => [
            registroCierreSesionParaAlerta('uuid-cierre-suma-ok', 'uuid-s-suma-ok', '50.00'),
        ]])->assertOk();

        expect(Alerta::query()->where('tipo', 'suma_excedida')->count())->toBe(0);
    });

    /*
     * ── Criterio 6: reintento del mismo evento no duplica la alerta ──
     */

    it('reintentar la misma recarga, las mismas condiciones y el mismo cierre no duplica sus alertas', function () {
        config(['operaciones.tolerancia_solape_hectareas' => '0.00']);
        $piloto = pilotoParaAlerta();

        $this->postJson('/api/sync', ['registros' => [
            registroTrabajoParaAlerta('uuid-t-retry'),
            registroSesionParaAlerta('uuid-s-retry', 'uuid-t-retry', $piloto->id),
        ]])->assertOk();

        $recarga = ['registros' => [registroRecargaParaAlerta('uuid-r-retry', 'uuid-s-retry', ['temperatura_bateria_c' => '55.00'])]];
        $this->postJson('/api/sync', $recarga)->assertOk();
        $this->postJson('/api/sync', $recarga)->assertOk();

        $condiciones = ['registros' => [registroCondicionesParaAlerta('uuid-c-retry', 'uuid-s-retry', [
            'viento_kmh' => '20.00',
            'observacion_agronomo' => 'obs',
            'firma_observacion' => 'firma',
        ])]];
        $this->postJson('/api/sync', $condiciones)->assertOk();
        $this->postJson('/api/sync', $condiciones)->assertOk();

        $cierre = ['registros' => [registroCierreSesionParaAlerta('uuid-cierre-retry', 'uuid-s-retry', '260.00')]];
        $this->postJson('/api/sync', $cierre)->assertOk();
        $this->postJson('/api/sync', $cierre)->assertOk();

        expect(Alerta::query()->where('tipo', 'bateria_caliente')->count())->toBe(1)
            ->and(Alerta::query()->where('tipo', 'condiciones_forzadas')->count())->toBe(1)
            ->and(Alerta::query()->where('tipo', 'suma_excedida')->count())->toBe(1);
    });
});

// ── Bandeja del panel: GET /panel/alertas, POST .../atender ────────────────

describe('bandeja de alertas del panel', function () {
    beforeEach(function () {
        $this->seed(CatalogoSeeder::class);
    });

    function usuarioConRolParaAlertas(string $username, string $rol, ?int $personaId = null): array
    {
        $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123', 'persona_id' => $personaId]);
        $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

        $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
        $pivote->created_by = $usuario->id;
        $pivote->updated_by = $usuario->id;
        $pivote->save();

        return [$usuario, $idRol];
    }

    function entrarAlPanelParaAlertas(SecUser $usuario, int $idRolActivo): void
    {
        test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
            ->withSession(['sec_rol_activo_id' => $idRolActivo]);
    }

    function alertaDemo(array $atributos = []): Alerta
    {
        return Alerta::query()->create([...[
            'tipo' => 'bateria_caliente',
            'mensaje' => 'Batería a 55.00 °C en la recarga #1 de la sesión #1 (trabajo #1).',
            'estado' => 'pendiente',
        ], ...$atributos]);
    }

    /*
     * ── Criterio 7 y 8: atender, e idempotencia sobre una ya atendida ──
     */

    it('atender una alerta pendiente la deja atendida, con atendida_por y atendida_en', function () {
        $alerta = alertaDemo();
        $atendedor = PerPersona::create(['nombre' => 'Encargado', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);
        [$usuario, $idRol] = usuarioConRolParaAlertas('encargado.atiende', 'encargado_operaciones', $atendedor->id);
        entrarAlPanelParaAlertas($usuario, $idRol);

        $this->post("/panel/alertas/{$alerta->id}/atender")
            ->assertRedirect(route('panel.alertas.index'));

        $alerta->refresh();
        expect($alerta->estado)->toBe(EstadoAlerta::Atendida)
            ->and($alerta->atendida_por)->toBe($atendedor->id)
            ->and($alerta->atendida_en)->not->toBeNull();
    });

    it('reintentar atender una alerta ya atendida es idempotente, no rompe', function () {
        $alerta = alertaDemo();
        $atendedor = PerPersona::create(['nombre' => 'Encargado dos', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);
        [$usuario, $idRol] = usuarioConRolParaAlertas('encargado.reatiende', 'encargado_operaciones', $atendedor->id);
        entrarAlPanelParaAlertas($usuario, $idRol);

        $this->post("/panel/alertas/{$alerta->id}/atender")->assertRedirect();
        $primeraAtencion = $alerta->refresh()->atendida_en;

        $this->post("/panel/alertas/{$alerta->id}/atender")->assertRedirect();

        expect($alerta->refresh()->estado)->toBe(EstadoAlerta::Atendida)
            ->and($alerta->atendida_en?->toIso8601String())->toBe($primeraAtencion?->toIso8601String());
    });

    it('sin el permiso operaciones.alerta.atender, atender responde 403', function () {
        $alerta = alertaDemo();
        [$piloto, $idRol] = usuarioConRolParaAlertas('piloto.atiende.403', 'piloto');
        entrarAlPanelParaAlertas($piloto, $idRol);

        $this->post("/panel/alertas/{$alerta->id}/atender")->assertForbidden();
        expect($alerta->refresh()->estado)->toBe(EstadoAlerta::Pendiente);
    });

    /*
     * ── Criterio 9: filtro por estado ──
     */

    it('el filtro por estado pendiente devuelve solo las alertas pendientes', function () {
        $pendiente = alertaDemo(['tipo' => 'bateria_caliente']);
        $atendida = alertaDemo(['tipo' => 'condiciones_forzadas', 'estado' => 'atendida', 'atendida_por' => null, 'atendida_en' => now()]);

        [$encargado, $idRol] = usuarioConRolParaAlertas('encargado.filtro', 'encargado_operaciones');
        entrarAlPanelParaAlertas($encargado, $idRol);

        $this->get('/panel/alertas?estado=pendiente')
            ->assertOk()
            ->assertViewHas('alertas', fn ($alertas) => $alertas->pluck('id')->all() === [$pendiente->id]
                && ! $alertas->pluck('id')->contains($atendida->id));
    });

    it('el filtro por tipo devuelve solo las alertas de ese tipo', function () {
        $bateria = alertaDemo(['tipo' => 'bateria_caliente']);
        $condiciones = alertaDemo(['tipo' => 'condiciones_forzadas']);

        [$encargado, $idRol] = usuarioConRolParaAlertas('encargado.filtro.tipo', 'encargado_operaciones');
        entrarAlPanelParaAlertas($encargado, $idRol);

        $this->get('/panel/alertas?tipo=bateria_caliente')
            ->assertOk()
            ->assertViewHas('alertas', fn ($alertas) => $alertas->pluck('id')->all() === [$bateria->id]
                && ! $alertas->pluck('id')->contains($condiciones->id));
    });

    it('sin el permiso operaciones.alerta.ver, la bandeja responde 403', function () {
        [$piloto, $idRol] = usuarioConRolParaAlertas('piloto.bandeja.403', 'piloto');
        entrarAlPanelParaAlertas($piloto, $idRol);

        $this->get('/panel/alertas')->assertForbidden();
    });

    it('publica el ítem de menú de alertas gateado por operaciones.alerta.ver', function () {
        $itemMenu = SecMenu::query()->where('label', 'menu.reportes.items.alertas')->sole();
        $idPermiso = (int) SecPermission::query()->where('code', 'operaciones.alerta.ver')->value('id');

        expect($itemMenu->ruta)->toBe('panel.alertas.index')
            ->and($itemMenu->permission_id)->toBe($idPermiso);
    });
});
