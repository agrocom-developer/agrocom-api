<?php

namespace App\Dominios\Flota\Aplicacion;

use App\Dominios\Compartido\Aplicacion\SeparacionDeFunciones;
use App\Dominios\Compartido\Contratos\Eventos\EstadoDeAprobacionCambiado;
use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Dominio\Excepciones\AprobacionPropia;
use App\Dominios\Compartido\Dominio\Excepciones\DocumentosIncompletos;
use App\Dominios\Compartido\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Compartido\Dominio\Excepciones\TransicionDeAprobacionInvalida;
use App\Dominios\Compartido\Dominio\OrigenCambio;
use App\Dominios\Compartido\Dominio\TipoDocumento;
use App\Dominios\Flota\Aplicacion\MaquinaEstados\MaquinaEstadosDron;
use App\Dominios\Flota\Dominio\Excepciones\OperacionDeFlotaInvalida;
use App\Dominios\Flota\Infraestructura\Eloquent\Dron;
use App\Dominios\Flota\Infraestructura\Eloquent\HistorialEstadoDron;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Proveedores\Contratos\VerificacionDocumental;
use Illuminate\Support\Facades\DB;

/**
 * Único camino para cambiar el estado de aprobación de un dron (invariante 7;
 * ADR 0029 y 0032). Misma regla que `ResolverAprobacion` de Proveedores: la
 * transición se valida contra la tabla compartida, rechazar, suspender y dar de
 * baja exigen motivo, aprobar exige los documentos del dron (registro,
 * habilitación y seguro) aprobados y vigentes y las fotos obligatorias aprobadas, y cada cambio deja su fila de
 * historial y el aviso al titular del dueño.
 */
final class ResolverAprobacionDron
{
    public function __construct(
        private readonly VerificacionDocumental $documentos,
        private readonly LecturaProveedores $proveedores,
        private readonly MaquinaEstadosDron $estados,
        private readonly FotosObligatorias $fotos,
    ) {}

    /**
     * @throws TransicionDeAprobacionInvalida
     * @throws MotivoRequerido
     * @throws AprobacionPropia si el actor aprueba lo que él mismo registró en el panel.
     * @throws DocumentosIncompletos
     * @throws OperacionDeFlotaInvalida si se aprueba sin las fotos obligatorias aprobadas (ADR 0042).
     */
    public function ejecutar(
        int $dronId,
        AccionAprobacion $accion,
        ?int $actorUsuarioId,
        ?string $motivo = null,
        OrigenCambio $origen = OrigenCambio::Administrador,
    ): void {
        DB::transaction(function () use ($dronId, $accion, $actorUsuarioId, $motivo, $origen): void {
            $dron = Dron::query()->lockForUpdate()->findOrFail($dronId);
            $anterior = $dron->estado;

            $destino = $this->estados->destino($dron, $accion, $motivo);

            SeparacionDeFunciones::exigir($accion, $dron->created_by, $actorUsuarioId);

            if ($destino === EstadoAprobacion::Aprobado) {
                $faltantes = $this->documentos->faltantes('dron', $dronId);

                if ($faltantes !== []) {
                    throw new DocumentosIncompletos(array_map(static fn (string $tipo): TipoDocumento => TipoDocumento::from($tipo), $faltantes));
                }

                // Rehabilitar a uno que ya estuvo aprobado no vuelve a pedir las fotos: las pidió la primera vez.
                $fotosFaltantes = $accion === AccionAprobacion::Aprobar ? $this->fotos->faltantes($dronId) : [];

                if ($fotosFaltantes !== []) {
                    throw OperacionDeFlotaInvalida::fotosIncompletas($fotosFaltantes);
                }
            }

            $this->estados->aplicar($dron, $accion, $destino, $actorUsuarioId, $motivo);

            $motivoLimpio = $motivo !== null && trim($motivo) !== '' ? trim($motivo) : null;

            $historial = new HistorialEstadoDron([
                'dron_id' => $dronId,
                'estado_anterior' => $anterior,
                'estado_nuevo' => $destino,
                'accion' => $accion,
                'origen' => $origen,
                'actor_usuario_id' => $actorUsuarioId,
                'motivo' => $motivoLimpio,
            ]);
            $historial->save();

            $titular = $this->proveedores->proveedor($dron->dueno_proveedor_id)?->usuarioTitularId;

            event(new EstadoDeAprobacionCambiado(
                sujetoTipo: 'dron',
                sujetoId: $dronId,
                nombre: trim("{$dron->marca} {$dron->modelo}")." ({$dron->numero_serie})",
                historialId: $historial->id,
                accion: $accion,
                motivo: $motivoLimpio,
                usuariosDestinatarios: $titular === null ? [] : [$titular],
                porElSistema: $origen === OrigenCambio::Sistema,
            ));
        });
    }
}
