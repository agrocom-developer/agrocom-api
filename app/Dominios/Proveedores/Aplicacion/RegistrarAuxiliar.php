<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Proveedores\Aplicacion\MaquinaEstados\MaquinaEstadosAprobacion;
use App\Dominios\Proveedores\Dominio\Excepciones\RegistroInvalido;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Auxiliar;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Piloto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use App\Dominios\Seguridad\Contratos\CuentaDuplicada;
use App\Dominios\Seguridad\Contratos\RegistroCuentas;
use Illuminate\Support\Facades\DB;

/**
 * Un piloto o el titular del proveedor registra a un auxiliar: crea la cuenta
 * (rol `auxiliar`) y el auxiliar `pendiente`, que un administrador aprueba (ADR
 * 0032, punto 2). Un piloto solo registra mientras él mismo está aprobado; el
 * titular, mientras el proveedor admita altas. `registrado_por_piloto_id` queda
 * lleno cuando quien registra es piloto (incluido el independiente, que es
 * titular y piloto a la vez).
 */
final class RegistrarAuxiliar
{
    public function __construct(
        private readonly AlcanceDelSolicitante $alcance,
        private readonly RegistroCuentas $cuentas,
        private readonly MaquinaEstadosAprobacion $estados,
    ) {}

    /**
     * @param  array{nombre: string, username: string, email: ?string, password: string}  $datos
     *
     * @throws RegistroInvalido si la cuenta no es titular ni piloto aprobado, o el proveedor no admite altas.
     * @throws CuentaDuplicada si el `username` o el `email` ya existen.
     */
    public function ejecutar(int $actorUsuarioId, array $datos): Auxiliar
    {
        $proveedor = $this->alcance->proveedorDelTitular($actorUsuarioId);
        $piloto = Piloto::query()->where('usuario_id', $actorUsuarioId)->first();

        if ($proveedor === null) {
            if ($piloto === null || $piloto->estado !== EstadoAprobacion::Aprobado) {
                throw RegistroInvalido::sinProveedor();
            }

            $proveedor = Proveedor::query()->findOrFail($piloto->proveedor_id);
        }

        if (! in_array($proveedor->estado, [EstadoAprobacion::Pendiente, EstadoAprobacion::Aprobado], true)) {
            throw RegistroInvalido::proveedorNoAprobado();
        }

        $registraPor = $piloto !== null && $piloto->proveedor_id === $proveedor->id ? $piloto->id : null;

        return DB::transaction(function () use ($proveedor, $datos, $actorUsuarioId, $registraPor): Auxiliar {
            $usuarioId = $this->cuentas->crear($datos['nombre'], $datos['username'], $datos['email'] ?? null, $datos['password'], ['auxiliar']);

            return $this->estados->registrarAuxiliar([
                'proveedor_id' => $proveedor->id,
                'usuario_id' => $usuarioId,
                'registrado_por_piloto_id' => $registraPor,
                'created_by' => $actorUsuarioId,
                'updated_by' => $actorUsuarioId,
            ]);
        });
    }
}
