<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Seguridad\Aplicacion\ListarBitacora;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserPreferencia;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\View\View;

/**
 * `GET /panel/bitacora` (tarea 63, invariante 9 de CLAUDE.md): quién hizo
 * qué, cuándo y en qué zona horaria — hasta esta tarea el trait/observer de
 * `Compartido` escribía la fila, pero no había pantalla, permiso ni ítem de
 * menú para verla. Solo lectura: ninguna acción de este controlador acepta
 * `PUT`/`DELETE` (ADR 0007 — es un libro de solo-inserción).
 *
 * Un único permiso (`seguridad.bitacora.ver`) gatea toda la pantalla,
 * verificado DENTRO del controlador contra el ROL ACTIVO vía
 * {@see AutorizacionPanelWeb} — mismo criterio que el resto del panel.
 *
 * Las fechas del filtro se interpretan en la zona de QUIEN MIRA (no la del
 * actor auditado): se resuelve una sola vez acá y se le pasa al caso de uso,
 * que hace toda la conversión — este controlador no calcula zonas.
 */
final class BitacoraController
{
    private const PERMISO = 'seguridad.bitacora.ver';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarBitacora $listarBitacora): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        /** @var SecUser $usuario */
        $usuario = $request->user('interno');
        $zonaQueVe = SecUserPreferencia::query()->where('user_id', $usuario->id)->value('zona_horaria')
            ?? config('app.timezone');

        $usuarioId = $request->integer('usuario_id') ?: null;
        $tabla = $request->filled('tabla') ? TextoDeFiltro::de($request, 'tabla') : null;
        $accionCodigo = $request->filled('accion') ? TextoDeFiltro::de($request, 'accion') : null;
        $accion = $accionCodigo !== null ? AccionBitacora::tryFrom($accionCodigo) : null;
        $desde = $request->filled('desde') ? TextoDeFiltro::de($request, 'desde') : null;
        $hasta = $request->filled('hasta') ? TextoDeFiltro::de($request, 'hasta') : null;
        $registroId = $request->integer('registro_id') ?: null;

        $bitacora = $listarBitacora->ejecutar(
            zonaQueVe: (string) $zonaQueVe,
            usuarioId: $usuarioId,
            tabla: $tabla,
            accion: $accion,
            desde: $desde,
            hasta: $hasta,
            registroId: $registroId,
        );

        return view('seguridad::pages.bitacora.index', [
            ...$this->autorizacion->cascara($request),
            'bitacora' => $bitacora,
            'usuariosDisponibles' => $this->usuariosDisponibles(),
            'tablasDisponibles' => $this->tablasDisponibles(),
            'accionesDisponibles' => $this->accionesDisponibles(),
            'zonaQueVe' => $zonaQueVe,
            'filtros' => [
                'usuario_id' => $usuarioId,
                'tabla' => $tabla,
                'accion' => $accionCodigo,
                'desde' => $desde,
                'hasta' => $hasta,
                'registro_id' => $registroId,
            ],
        ]);
    }

    /**
     * Solo los usuarios que YA aparecen como actor en la bitácora — no el
     * catálogo completo de `sec_user`, que listaría cientos de cuentas que
     * nunca mutaron nada auditado.
     *
     * @return Collection<int, non-falsy-string>
     */
    private function usuariosDisponibles(): Collection
    {
        $ids = Bitacora::query()->whereNotNull('user_id')->distinct()->pluck('user_id');

        return SecUser::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'username'])
            ->mapWithKeys(fn (SecUser $usuario): array => [$usuario->id => "{$usuario->name} ({$usuario->username})"]);
    }

    /**
     * Solo las tablas que YA tienen alguna fila — evita ofrecer en el
     * filtro una entidad que nunca generó bitácora en esta instalación.
     *
     * @return array<string, string>
     */
    private function tablasDisponibles(): array
    {
        return Bitacora::query()
            ->distinct()
            ->orderBy('tabla')
            ->pluck('tabla')
            ->mapWithKeys(function (string $tabla): array {
                $clave = "seguridad.bitacora.entidades.{$tabla}";

                return [$tabla => Lang::has($clave) ? __($clave) : $tabla];
            })
            ->all();
    }

    /** @return array<string, string> */
    private function accionesDisponibles(): array
    {
        return collect(AccionBitacora::cases())
            ->mapWithKeys(fn (AccionBitacora $accion): array => [$accion->value => __("seguridad.bitacora.acciones.{$accion->value}")])
            ->all();
    }
}
