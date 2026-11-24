<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Compartido\Contratos\Eventos\EstadoDeAprobacionCambiado;
use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Dominio\Excepciones\DocumentosIncompletos;
use App\Dominios\Compartido\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Compartido\Dominio\Excepciones\TransicionDeAprobacionInvalida;
use App\Dominios\Compartido\Dominio\OrigenCambio;
use App\Dominios\Compartido\Dominio\TransicionesAprobacion;
use App\Dominios\Proveedores\Aplicacion\MaquinaEstados\MaquinaEstadosAprobacion;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Auxiliar;
use App\Dominios\Proveedores\Infraestructura\Eloquent\HistorialEstado;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Piloto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Único camino para cambiar el estado de aprobación de un proveedor, piloto o
 * auxiliar (invariante 7 de CLAUDE.md; ADR 0032): valida la transición contra
 * la tabla de {@see TransicionesAprobacion},
 * exige motivo donde corresponde, exige los documentos obligatorios vigentes
 * para aprobar o rehabilitar, deja la fila de historial con autor y motivo y
 * anuncia {@see EstadoDeAprobacionCambiado}. Todo en una transacción, con la
 * fila bloqueada: dos administradores decidiendo a la vez no se pisan.
 *
 * Quién puede decidir (el permiso por acción) lo verifica el controlador contra
 * el rol activo; quién puede reenviar (el solicitante) lo verifica el alcance
 * de la API. Acá solo se aplican las reglas del dominio.
 */
final class ResolverAprobacion
{
    public function __construct(
        private readonly EstadoDocumentalDeSujeto $documentos,
        private readonly ResolverSujeto $sujetos,
        private readonly MaquinaEstadosAprobacion $estados,
    ) {}

    /**
     * @throws TransicionDeAprobacionInvalida
     * @throws MotivoRequerido
     * @throws DocumentosIncompletos si se aprueba o rehabilita a quien no tiene todos sus documentos obligatorios aprobados y vigentes.
     */
    public function ejecutar(
        TipoSujeto $tipo,
        int $sujetoId,
        AccionAprobacion $accion,
        ?int $actorUsuarioId,
        ?string $motivo = null,
        OrigenCambio $origen = OrigenCambio::Administrador,
    ): void {
        if ($tipo === TipoSujeto::Dron) {
            throw new InvalidArgumentException('El dron se resuelve en Flota (ResolverAprobacionDron).');
        }

        DB::transaction(function () use ($tipo, $sujetoId, $accion, $actorUsuarioId, $motivo, $origen): void {
            $sujeto = $this->bloquear($tipo, $sujetoId);
            $anterior = $sujeto->estado;

            $destino = $this->estados->destino($sujeto, $accion, $motivo);

            if ($destino === EstadoAprobacion::Aprobado) {
                $faltantes = $this->documentos->faltantesDe($tipo, $sujetoId);

                if ($faltantes !== []) {
                    throw new DocumentosIncompletos($faltantes);
                }
            }

            $this->estados->aplicar($sujeto, $accion, $destino, $actorUsuarioId, $motivo);

            $historial = new HistorialEstado([
                'sujeto_tipo' => $tipo,
                'sujeto_id' => $sujetoId,
                'estado_anterior' => $anterior,
                'estado_nuevo' => $destino,
                'accion' => $accion,
                'origen' => $origen,
                'actor_usuario_id' => $actorUsuarioId,
                'motivo' => $motivo !== null && trim($motivo) !== '' ? trim($motivo) : null,
            ]);
            $historial->save();

            $this->anunciar($tipo, $sujetoId, $historial->id, $accion, $motivo, $origen);
        });
    }

    private function bloquear(TipoSujeto $tipo, int $id): Proveedor|Piloto|Auxiliar
    {
        $consulta = match ($tipo) {
            TipoSujeto::Proveedor => Proveedor::query(),
            TipoSujeto::Piloto => Piloto::query(),
            TipoSujeto::Auxiliar => Auxiliar::query(),
            TipoSujeto::Dron => throw new InvalidArgumentException('El dron se resuelve en Flota.'),
        };

        return $consulta->lockForUpdate()->findOrFail($id);
    }

    private function anunciar(TipoSujeto $tipo, int $id, int $historialId, AccionAprobacion $accion, ?string $motivo, OrigenCambio $origen): void
    {
        $sujeto = $this->sujetos->ejecutar($tipo, $id);

        event(new EstadoDeAprobacionCambiado(
            sujetoTipo: $tipo->value,
            sujetoId: $id,
            nombre: $sujeto->nombre ?? "#{$id}",
            historialId: $historialId,
            accion: $accion,
            motivo: $motivo !== null && trim($motivo) !== '' ? trim($motivo) : null,
            usuariosDestinatarios: $sujeto->usuariosAAvisar ?? [],
            porElSistema: $origen === OrigenCambio::Sistema,
        ));
    }
}
