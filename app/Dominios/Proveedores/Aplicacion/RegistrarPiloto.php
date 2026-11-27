<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Proveedores\Aplicacion\MaquinaEstados\MaquinaEstadosAprobacion;
use App\Dominios\Proveedores\Dominio\Excepciones\RegistroInvalido;
use App\Dominios\Proveedores\Dominio\TipoProveedor;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Piloto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use App\Dominios\Seguridad\Contratos\CuentaDuplicada;
use App\Dominios\Seguridad\Contratos\CuentaYaRegistrada;
use App\Dominios\Seguridad\Contratos\DatosDePersona;
use App\Dominios\Seguridad\Contratos\RegistroCuentas;
use App\Dominios\Seguridad\Contratos\ReglasDeContacto;
use App\Dominios\Seguridad\Contratos\TipoCuenta;
use Illuminate\Support\Facades\DB;

/**
 * El titular de un proveedor `tercero` registra a un piloto suyo: crea la cuenta
 * (rol `piloto`) y el piloto `pendiente`, que un administrador aprueba (ADR
 * 0032). El proveedor `independiente` es su propio piloto y no suma otros: si
 * crece, se registra como `tercero`.
 */
final class RegistrarPiloto
{
    public function __construct(
        private readonly AlcanceDelSolicitante $alcance,
        private readonly RegistroCuentas $cuentas,
        private readonly MaquinaEstadosAprobacion $estados,
    ) {}

    /**
     * @param  array{nombre: string, username: string, email: ?string, password: string, licencia_numero: string, licencia_vence: string, categoria_habilitacion: string, telefono?: ?string, documento_tipo?: ?string, documento_numero?: ?string, nombres?: ?string, apellidos?: ?string, fecha_nacimiento?: ?string}  $datos
     *
     * @throws RegistroInvalido si la cuenta no es titular de un proveedor, el proveedor no admite altas o es independiente.
     * @throws CuentaDuplicada si el `username` o el `email` ya existen.
     * @throws CuentaYaRegistrada si el teléfono o el documento ya tienen una cuenta de proveedor.
     */
    public function ejecutar(int $actorUsuarioId, array $datos): Piloto
    {
        $proveedor = $this->alcance->proveedorDelTitular($actorUsuarioId) ?? throw RegistroInvalido::sinProveedor();

        return $this->registrarEn($proveedor, $actorUsuarioId, $datos);
    }

    /**
     * El alta que carga la oficina desde el panel: el mismo registro, sobre un proveedor
     * elegido por su id y con quien lo carga como autor.
     *
     * @param  array{nombre: string, username: string, email: ?string, password: string, licencia_numero: string, licencia_vence: string, categoria_habilitacion: string, telefono?: ?string, documento_tipo?: ?string, documento_numero?: ?string, nombres?: ?string, apellidos?: ?string, fecha_nacimiento?: ?string}  $datos
     *
     * @throws RegistroInvalido si el proveedor no existe, no admite altas o es independiente.
     * @throws CuentaDuplicada si el `username` o el `email` ya existen.
     * @throws CuentaYaRegistrada si el teléfono o el documento ya tienen una cuenta de proveedor.
     */
    public function ejecutarParaProveedor(int $proveedorId, int $registradoPor, array $datos): Piloto
    {
        $proveedor = Proveedor::query()->find($proveedorId) ?? throw RegistroInvalido::sinProveedor();

        return $this->registrarEn($proveedor, $registradoPor, $datos);
    }

    /** @param  array{nombre: string, username: string, email: ?string, password: string, licencia_numero: string, licencia_vence: string, categoria_habilitacion: string, telefono?: ?string, documento_tipo?: ?string, documento_numero?: ?string, nombres?: ?string, apellidos?: ?string, fecha_nacimiento?: ?string}  $datos */
    private function registrarEn(Proveedor $proveedor, int $actorUsuarioId, array $datos): Piloto
    {
        if ($proveedor->tipo === TipoProveedor::Independiente) {
            throw RegistroInvalido::tipoDeProveedorSinPersonal();
        }

        if (! in_array($proveedor->estado, [EstadoAprobacion::Pendiente, EstadoAprobacion::Aprobado], true)) {
            throw RegistroInvalido::proveedorNoAprobado();
        }

        return DB::transaction(function () use ($proveedor, $datos, $actorUsuarioId): Piloto {
            // Teléfono y persona del piloto (ADR 0046): opcionales hasta que la app los envíe (TRANSITORIO).
            $telefono = ReglasDeContacto::telefono($datos['telefono'] ?? null);
            $persona = ! empty($datos['documento_numero']) && ! empty($datos['nombres']) && ! empty($datos['apellidos'])
                ? new DatosDePersona($datos['documento_tipo'] ?? 'ci', $datos['documento_numero'], $datos['nombres'], $datos['apellidos'], $datos['fecha_nacimiento'] ?? null)
                : null;

            $usuarioId = $this->cuentas->crear($datos['nombre'], $datos['username'], $datos['email'] ?? null, $datos['password'], TipoCuenta::Proveedor, ['piloto'], $telefono, $persona);

            return $this->estados->registrarPiloto([
                'proveedor_id' => $proveedor->id,
                'usuario_id' => $usuarioId,
                'licencia_numero' => $datos['licencia_numero'],
                'licencia_vence' => $datos['licencia_vence'],
                'categoria_habilitacion' => $datos['categoria_habilitacion'],
                'created_by' => $actorUsuarioId,
                'updated_by' => $actorUsuarioId,
            ]);
        });
    }
}
