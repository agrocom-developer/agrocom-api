<?php

namespace App\Dominios\Notificaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Notificaciones\Infraestructura\Eloquent\Notificacion;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * `GET /panel/avisos` (`panel.avisos.index`): los avisos que el sistema envió a
 * clientes, proveedores y administradores, del más nuevo al más viejo, con su
 * destinatario y si ya los leyó. Solo lectura: es la forma de comprobar que un
 * evento (una adjudicación, un reclamo, una aprobación) avisó a quien debía.
 *
 * A diferencia de la campana —que muestra solo los avisos de la cuenta que mira—,
 * esta pantalla ve los de todas las cuentas; por eso exige su propio permiso,
 * `notificaciones.aviso.ver`, verificado contra el ROL ACTIVO (invariante 10).
 */
final class AvisosController
{
    private const PERMISO_VER = 'notificaciones.aviso.ver';

    private const LECTURAS = ['sin_leer', 'leida'];

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, LecturaCuentas $cuentas): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $lectura = TextoDeFiltro::de($request, 'lectura');
        $lectura = in_array($lectura, self::LECTURAS, true) ? $lectura : null;

        $avisos = Notificacion::query()
            ->when($lectura === 'sin_leer', fn ($consulta) => $consulta->whereNull('leida_en'))
            ->when($lectura === 'leida', fn ($consulta) => $consulta->whereNotNull('leida_en'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $cascara = $this->autorizacion->cascara($request);
        $zona = $cascara['zonaHoraria'] ?? null;
        $destinatarios = $cuentas->porIds($avisos->getCollection()->pluck('usuario_id')->unique()->values()->all());

        $filas = $avisos->getCollection()->map(fn (Notificacion $aviso): array => [
            'icono' => $aviso->tipo->icono(),
            'titulo' => __($aviso->tipo->claveDeTitulo(), $aviso->parametros ?? []),
            'destinatario' => $destinatarios[$aviso->usuario_id]->nombre ?? "#{$aviso->usuario_id}",
            'leida' => $aviso->leida_en !== null,
            'creado_en' => Carbon::instance($aviso->created_at)->timezone($zona ?: config('app.timezone'))->format('d/m/Y H:i'),
        ])->all();

        return view('notificaciones::pages.avisos.index', [
            ...$cascara,
            'avisos' => $avisos,
            'filas' => $filas,
            'filtros' => ['lectura' => $lectura],
            'lecturas' => self::LECTURAS,
        ]);
    }
}
