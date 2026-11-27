<?php

namespace App\Dominios\Negociacion\Infraestructura\Oyentes;

use App\Dominios\Negociacion\Aplicacion\Avisador;
use App\Dominios\Negociacion\Aplicacion\FiltrosDeProveedor\SolicitudParaAviso;
use App\Dominios\Negociacion\Aplicacion\PreferenciasDeAviso;
use App\Dominios\Proveedores\Contratos\DatosProveedor;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudAbierta;

/**
 * Al abrirse una solicitud avisa a cada cuenta de proveedor aprobada y disponible cuyos filtros
 * la dejan pasar (`negociacion-contrato.md` §7; el departamento filtra desde que la solicitud lo trae). Una solicitud avisa
 * a una cuenta una sola vez: la clave del aviso es la solicitud, así que un nuevo aviso de la misma solicitud no repite.
 * Es el incentivo para que los pilotos reciban ofertas de trabajo sin abrir la app.
 */
final class AvisarNuevaSolicitud
{
    public function __construct(
        private readonly LecturaProveedores $proveedores,
        private readonly PreferenciasDeAviso $preferencias,
        private readonly Avisador $avisos,
    ) {}

    public function handle(SolicitudAbierta $evento): void
    {
        $usuarios = [];
        $solicitud = new SolicitudParaAviso($evento->hectareas, $evento->departamentoCodigo, $evento->tipoAplicacion);

        /** @var list<DatosProveedor> $aprobados */
        $aprobados = [...$this->proveedores->aprobadosDelGrupo('propio'), ...$this->proveedores->aprobadosDelGrupo('tercero')];

        foreach ($aprobados as $proveedor) {
            // Solo cuentas disponibles (ADR 0045, punto 8): ocupado o en trabajo no recibe aviso, y al volver no hay avisos atrasados.
            foreach ($this->proveedores->usuariosParaAvisoDeNuevasSolicitudes($proveedor->id) as $usuarioId) {
                if ($usuarioId !== $evento->clienteUsuarioId && $this->preferencias->coincide($usuarioId, $solicitud)) {
                    $usuarios[] = $usuarioId;
                }
            }
        }

        $this->avisos->avisar('nueva_solicitud_abierta', "nueva_solicitud_abierta:{$evento->solicitudId}", [
            'solicitud' => $evento->solicitudId,
            'hectareas' => $evento->hectareas,
            'precio' => $evento->precioHa,
        ], $evento->solicitudId, $usuarios);
    }
}
