<?php

namespace App\Dominios\Negociacion\Aplicacion;

use App\Dominios\Negociacion\Dominio\EstadoOferta;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Mensaje;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Seguridad\Contratos\ParticipanteDeEliminacion;
use App\Dominios\Solicitudes\Contratos\LecturaServicios;
use Illuminate\Support\Facades\DB;

/**
 * Lo que Negociación aporta a la eliminación de una cuenta de proveedor (ADR 0047): un servicio adjudicado o en ejecución la
 * impide; al pedirla se retiran las ofertas vigentes del proveedor (o se rechaza la contraoferta del cliente que esperaba su
 * respuesta), por el camino normal; al ejecutarla se borra el texto de los mensajes que escribió la cuenta. Las ofertas, el
 * precio acordado y los mensajes de la contraparte se conservan.
 */
final class EliminacionDeNegociaciones implements ParticipanteDeEliminacion
{
    public function __construct(
        private readonly Negociar $negociar,
        private readonly LecturaProveedores $proveedores,
        private readonly LecturaServicios $servicios,
    ) {}

    public function impedimentos(int $usuarioId): array
    {
        $proveedor = $this->proveedores->proveedorDeLaCuenta($usuarioId);

        if ($proveedor === null) {
            return [];
        }

        $solicitudes = Oferta::query()
            ->where('proveedor_id', $proveedor->id)
            ->where('estado', EstadoOferta::Aceptada->value)
            ->pluck('solicitud_id');

        foreach ($solicitudes as $solicitudId) {
            $estado = $this->servicios->datos((int) $solicitudId)?->estado;

            if (in_array($estado, ['adjudicada', 'en_ejecucion'], true)) {
                return ['servicio_en_curso'];
            }
        }

        return [];
    }

    public function alSolicitar(int $usuarioId): void
    {
        $proveedor = $this->proveedores->proveedorDeLaCuenta($usuarioId);

        if ($proveedor === null) {
            return;
        }

        Oferta::query()
            ->where('proveedor_id', $proveedor->id)
            ->where('estado', EstadoOferta::Vigente->value)
            ->get()
            ->each(function (Oferta $oferta) use ($usuarioId): void {
                try {
                    $oferta->autor === Negociar::LADO_PROVEEDOR
                        ? $this->negociar->retirar(Negociar::LADO_PROVEEDOR, $usuarioId, $oferta->id)
                        : $this->negociar->rechazar(Negociar::LADO_PROVEEDOR, $usuarioId, $oferta->id);
                } catch (NegociacionInvalida $falla) {
                    report($falla);
                }
            });
    }

    public function anonimizar(int $usuarioId): void
    {
        DB::table((new Mensaje)->getTable())
            ->where('autor_usuario_id', $usuarioId)
            ->update(['texto' => __('seguridad.eliminacion.mensaje_borrado')]);
    }
}
