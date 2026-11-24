<?php

namespace App\Dominios\Flota\Aplicacion;

use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Flota\Dominio\Excepciones\OperacionDeFlotaInvalida;
use App\Dominios\Flota\Dominio\Vigencia;
use App\Dominios\Flota\Infraestructura\Eloquent\Dron;
use App\Dominios\Flota\Infraestructura\Eloquent\Equipo;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * El titular arma un equipo operativo: un piloto, un dron y, si hace falta, un
 * auxiliar, todos de su proveedor (especificación §4.4). Reglas:
 * - el piloto y el auxiliar son del proveedor y el dron lo opera el proveedor;
 * - ninguno está rechazado, suspendido ni dado de baja (un integrante pendiente
 *   sí entra: el equipo no recibe ofertas hasta que todos estén aprobados);
 * - un piloto y un dron no pueden estar en dos equipos vigentes a la vez: dos
 *   vigencias que comparten un día se rechazan.
 *
 * La comprobación corre dentro de una transacción con los equipos del piloto y
 * del dron bloqueados; un índice único parcial cubre el caso residual de dos
 * pedidos simultáneos con vigencia abierta.
 */
final class CrearEquipo
{
    public function __construct(private readonly LecturaProveedores $proveedores) {}

    /**
     * @throws OperacionDeFlotaInvalida
     */
    public function ejecutar(
        int $actorUsuarioId,
        int $pilotoId,
        int $dronId,
        ?int $auxiliarId,
        string $vigenteDesde,
        ?string $vigenteHasta = null,
    ): Equipo {
        $proveedor = $this->proveedores->proveedorDelTitular($actorUsuarioId) ?? throw OperacionDeFlotaInvalida::sinProveedor();

        if (! $proveedor->admiteAltas()) {
            throw OperacionDeFlotaInvalida::proveedorNoAdmiteAltas();
        }

        $vigencia = new Vigencia($vigenteDesde, $vigenteHasta);

        if (! $vigencia->esValida()) {
            throw OperacionDeFlotaInvalida::vigenciaInvalida();
        }

        $piloto = $this->proveedores->piloto($pilotoId);

        if ($piloto === null || $piloto->proveedorId !== $proveedor->id) {
            throw OperacionDeFlotaInvalida::pilotoAjeno();
        }

        $this->exigirOperable($piloto->estado, 'piloto');

        if ($auxiliarId !== null) {
            $auxiliar = $this->proveedores->auxiliar($auxiliarId);

            if ($auxiliar === null || $auxiliar->proveedorId !== $proveedor->id) {
                throw OperacionDeFlotaInvalida::auxiliarAjeno();
            }

            $this->exigirOperable($auxiliar->estado, 'auxiliar');
        }

        return DB::transaction(function () use ($proveedor, $pilotoId, $dronId, $auxiliarId, $vigencia, $vigenteDesde, $vigenteHasta, $actorUsuarioId): Equipo {
            $dron = Dron::query()->lockForUpdate()->find($dronId);

            if ($dron === null || $dron->operador_proveedor_id !== $proveedor->id) {
                throw OperacionDeFlotaInvalida::dronAjeno();
            }

            if (! in_array($dron->estado, [EstadoAprobacion::Pendiente, EstadoAprobacion::Aprobado], true)) {
                throw OperacionDeFlotaInvalida::dronNoOperable();
            }

            $this->exigirSinSolape('piloto', Equipo::query()->where('piloto_id', $pilotoId), $vigencia);
            $this->exigirSinSolape('dron', Equipo::query()->where('dron_id', $dronId), $vigencia);

            try {
                return Equipo::query()->create([
                    'proveedor_id' => $proveedor->id,
                    'piloto_id' => $pilotoId,
                    'dron_id' => $dronId,
                    'auxiliar_id' => $auxiliarId,
                    'vigente_desde' => $vigenteDesde,
                    'vigente_hasta' => $vigenteHasta,
                    'created_by' => $actorUsuarioId,
                    'updated_by' => $actorUsuarioId,
                ]);
            } catch (QueryException $excepcion) {
                if (str_contains($excepcion->getMessage(), 'flo_equipos_piloto_abierto_unico')) {
                    throw OperacionDeFlotaInvalida::integranteEnOtroEquipo('piloto');
                }

                if (str_contains($excepcion->getMessage(), 'flo_equipos_dron_abierto_unico')) {
                    throw OperacionDeFlotaInvalida::integranteEnOtroEquipo('dron');
                }

                throw $excepcion;
            }
        });
    }

    private function exigirOperable(string $estado, string $rol): void
    {
        if (! in_array($estado, ['pendiente', 'aprobado'], true)) {
            throw OperacionDeFlotaInvalida::integranteNoDisponible($rol);
        }
    }

    /**
     * @param  Builder<Equipo>  $consulta
     */
    private function exigirSinSolape(string $rol, $consulta, Vigencia $nueva): void
    {
        foreach ($consulta->lockForUpdate()->get() as $existente) {
            if ($nueva->solapaCon($existente->vigencia())) {
                throw OperacionDeFlotaInvalida::integranteEnOtroEquipo($rol);
            }
        }
    }
}
