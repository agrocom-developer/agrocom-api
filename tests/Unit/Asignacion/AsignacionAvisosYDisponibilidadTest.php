<?php

use App\Dominios\Asignacion\Contratos\Eventos\OfertaCreada;
use App\Dominios\Asignacion\Contratos\Eventos\ProveedorSancionado;
use App\Dominios\Asignacion\Contratos\Eventos\SolicitudAsignada;
use App\Dominios\Asignacion\Contratos\Eventos\SolicitudSinProveedor;
use App\Dominios\Flota\Aplicacion\DeclararDisponibilidad;
use App\Dominios\Flota\Contratos\LecturaDisponibilidad;
use App\Dominios\Flota\Contratos\LecturaEquipos;
use App\Dominios\Flota\Dominio\Excepciones\OperacionDeFlotaInvalida;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaOfertaCreada;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaProveedorSancionado;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaSolicitudAsignada;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaSolicitudSinProveedor;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\RolDestinatario;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use App\Dominios\Proveedores\Contratos\DatosAuxiliar;
use App\Dominios\Proveedores\Contratos\DatosPiloto;
use App\Dominios\Proveedores\Contratos\DatosProveedor;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/*
 * Los avisos que reparte el motor de asignación (especificación §12, ADR 0025 y
 * 0038) y la disponibilidad «en línea» del proveedor (especificación §4.4).
 */

uses(TestCase::class);

// ── Reglas de notificación ───────────────────────────────────────────────────

test('una oferta nueva avisa a las cuentas del proveedor y lleva a la solicitud', function () {
    $armada = (new ReglaOfertaCreada)->armar(new OfertaCreada(31, 12, 2, [502, 507], '80.0000', '2026-12-02T12:00:00+00:00', false, 1, 'propio'));

    expect($armada->tipo)->toBe(TipoNotificacion::OfertaCreada)
        ->and($armada->claveEvento)->toBe('oferta_creada:31')
        ->and($armada->recurso)->toBe(RecursoNotificable::Solicitud)
        ->and($armada->recursoId)->toBe(12)
        ->and($armada->parametros)->toBe(['solicitud' => 12, 'hectareas' => '80,00', 'hora' => '12:00'])
        ->and($armada->destinatarios)->toEqual([Destinatario::usuario(502), Destinatario::usuario(507)]);
});

test('una solicitud asignada avisa al cliente y al proveedor, sin repetir cuentas; otro ciclo es otro aviso', function () {
    $regla = new ReglaSolicitudAsignada;
    $primera = $regla->armar(new SolicitudAsignada(12, 1, 900, 2, 'Beto', 12, [502, 900], 'proveedor'));
    $reasignada = $regla->armar(new SolicitudAsignada(12, 2, 900, 3, 'Cruz', 13, [503], 'administrador'));

    expect($primera->destinatarios)->toEqual([Destinatario::usuario(502), Destinatario::usuario(900)])
        ->and($primera->parametros)->toBe(['solicitud' => 12, 'proveedor' => 'Beto'])
        ->and($primera->claveEvento)->not->toBe($reasignada->claveEvento);
});

test('sin proveedor avisa al cliente y al equipo de operaciones', function () {
    $armada = (new ReglaSolicitudSinProveedor)->armar(new SolicitudSinProveedor(12, 1, 900));

    expect($armada->destinatarios)->toEqual([Destinatario::usuario(900), Destinatario::rol(RolDestinatario::EncargadoOperaciones)]);
});

test('una sanción avisa al proveedor y, si deja una revisión de suspensión, también a quien decide', function () {
    $regla = new ReglaProveedorSancionado;
    $advertencia = $regla->armar(new ProveedorSancionado(5, 2, 'Beto', 'advertencia', 'cancelaciones', [502]));
    $revision = $regla->armar(new ProveedorSancionado(6, 2, 'Beto', 'revision_suspension', 'cancelaciones', [502]));

    expect($advertencia->destinatarios)->toEqual([Destinatario::usuario(502)])
        ->and($revision->destinatarios)->toEqual([
            Destinatario::usuario(502), Destinatario::rol(RolDestinatario::Dueno), Destinatario::rol(RolDestinatario::EncargadoOperaciones),
        ])
        ->and($revision->recurso)->toBe(RecursoNotificable::Proveedor)
        ->and($revision->recursoId)->toBe(2);
});

// ── Disponibilidad ───────────────────────────────────────────────────────────

function disponibilidadEsquema(): void
{
    Schema::disableForeignKeyConstraints();

    $migraciones = ['create_plt_bitacoras_table', 'create_flo_disponibilidad_table'];
    static $instancias = [];

    foreach ($migraciones as $nombre) {
        $archivos = glob(database_path("migrations/*_{$nombre}.php")) ?: [];
        $instancias[$archivos[0]] ??= require $archivos[0];

        try {
            $instancias[$archivos[0]]->up();
        } catch (QueryException $excepcion) {
            if (! str_contains($excepcion->getMessage(), 'already exists') && ! str_contains($excepcion->getMessage(), 'USING btree')) {
                throw $excepcion;
            }
        }
    }
}

/** El titular 502 tiene los equipos 12 y 14; el piloto 600 solo está en el 14. */
function disponibilidadMundo(): DeclararDisponibilidad
{
    app()->instance(LecturaProveedores::class, new class implements LecturaProveedores
    {
        public function proveedor(int $id): ?DatosProveedor
        {
            return null;
        }

        public function proveedorDelTitular(int $usuarioId): ?DatosProveedor
        {
            return $usuarioId === 502 ? new DatosProveedor(2, 502, 'tercero', 'Beto', 'aprobado') : null;
        }

        public function proveedorPropio(): ?DatosProveedor
        {
            return null;
        }

        public function piloto(int $id): ?DatosPiloto
        {
            return null;
        }

        public function auxiliar(int $id): ?DatosAuxiliar
        {
            return null;
        }

        public function registrosDeCuenta(int $usuarioId): array
        {
            return ['pilotos' => [], 'auxiliares' => []];
        }

        public function pilotosDe(int $proveedorId): array
        {
            return [];
        }

        public function aprobadosDelGrupo(string $grupo): array
        {
            return [];
        }

        public function usuariosDeAviso(int $proveedorId): array
        {
            return [];
        }

        public function proveedorDeLaCuenta(int $usuarioId): ?DatosProveedor
        {
            return $this->proveedorDelTitular($usuarioId);
        }
    });

    app()->instance(LecturaEquipos::class, new class implements LecturaEquipos
    {
        public function aptosDe(int $proveedorId, ?DateTimeImmutable $hoy = null): array
        {
            return [];
        }

        public function equiposDeCuenta(int $usuarioId, bool $soloVigentes = true, ?DateTimeImmutable $hoy = null): array
        {
            return $usuarioId === 600 ? [14] : [];
        }

        public function tieneEquipoApto(int $proveedorId, ?DateTimeImmutable $hoy = null): bool
        {
            return false;
        }

        public function vigentesDe(int $proveedorId, ?DateTimeImmutable $hoy = null): array
        {
            return $proveedorId === 2 ? [12, 14] : [];
        }
    });

    return app(DeclararDisponibilidad::class);
}

beforeEach(function () {
    disponibilidadEsquema();
});

test('el titular se pone en línea con todos sus equipos y la ubicación queda para medir distancias', function () {
    $declarar = disponibilidadMundo();

    $filas = $declarar->ejecutar(502, true, '-17.7833000', '-63.1821000');
    $leidas = app(LecturaDisponibilidad::class)->porEquipos([12, 14, 99]);

    expect($filas)->toHaveCount(2)
        ->and(array_keys($leidas))->toBe([12, 14])
        ->and($leidas[12]->enLinea)->toBeTrue()
        ->and((float) $leidas[12]->lat)->toBe(-17.7833);
});

test('declararse dos veces lo mismo deja una fila por equipo y conserva la última ubicación', function () {
    $declarar = disponibilidadMundo();

    $declarar->ejecutar(502, true, '-17.7833000', '-63.1821000');
    $declarar->ejecutar(502, true, null, null);
    $declarar->ejecutar(502, false, null, null, 12);

    $leidas = app(LecturaDisponibilidad::class)->porEquipos([12, 14]);

    expect(DB::table('flo_disponibilidad')->count())->toBe(2)
        ->and($leidas[12]->enLinea)->toBeFalse()
        ->and($leidas[14]->enLinea)->toBeTrue()
        ->and((float) $leidas[12]->lat)->toBe(-17.7833);
});

test('un piloto que no es titular opera solo sobre sus equipos', function () {
    $declarar = disponibilidadMundo();

    $declarar->ejecutar(600, true, null, null);

    expect(array_keys(app(LecturaDisponibilidad::class)->porEquipos([12, 14])))->toBe([14])
        ->and(fn () => $declarar->ejecutar(600, true, null, null, 12))->toThrow(OperacionDeFlotaInvalida::class);
});

test('un equipo ajeno se rechaza como si no existiera y una cuenta sin equipos no declara nada', function () {
    $declarar = disponibilidadMundo();

    try {
        $declarar->ejecutar(502, true, null, null, 99);
        $this->fail('Debió rechazar el equipo ajeno.');
    } catch (OperacionDeFlotaInvalida $excepcion) {
        expect($excepcion->esAjeno)->toBeTrue();
    }

    expect(fn () => $declarar->ejecutar(777, true, null, null))->toThrow(OperacionDeFlotaInvalida::class)
        ->and(DB::table('flo_disponibilidad')->count())->toBe(0);
});
