<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Proveedores\Aplicacion\MaquinaEstados\MaquinaEstadosAprobacion;
use App\Dominios\Proveedores\Contratos\Eventos\ProveedorRegistrado;
use App\Dominios\Proveedores\Dominio\Excepciones\RegistroDuplicado;
use App\Dominios\Proveedores\Dominio\TipoProveedor;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Piloto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use App\Dominios\Seguridad\Contratos\CuentaDuplicada;
use App\Dominios\Seguridad\Contratos\RegistroCuentas;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Registro público de un proveedor independiente o tercero (ADR 0032, punto 1):
 * crea la cuenta (rol `proveedor`, más `piloto` si es independiente), el
 * proveedor `pendiente` y, para el independiente, su propio piloto `pendiente`.
 * Nada queda aprobado: solo un administrador aprueba. Todo en una transacción:
 * si algo falla no queda una cuenta huérfana.
 *
 * Nunca registra el tipo `propio`: Agrocom se siembra, no se auto-registra.
 */
final class RegistrarProveedor
{
    public function __construct(
        private readonly RegistroCuentas $cuentas,
        private readonly MaquinaEstadosAprobacion $estados,
    ) {}

    /**
     * @param  array{tipo: string, nombre: string, username: string, email: ?string, password: string, telefono: ?string, razon_social: string, documento_fiscal: ?string, zona_base_id: ?int, radio_operacion_km: ?string, licencia_numero?: ?string, licencia_vence?: ?string, categoria_habilitacion?: ?string, nombres?: ?string, apellidos?: ?string, documento_identidad?: ?string, fecha_nacimiento?: ?string}  $datos
     *
     * @throws CuentaDuplicada si el `username` o el `email` ya existen.
     * @throws RegistroDuplicado si el documento fiscal ya pertenece a otro proveedor.
     */
    public function ejecutar(array $datos): Proveedor
    {
        $tipo = TipoProveedor::from($datos['tipo']);

        if (! $tipo->seRegistraPorSiMismo()) {
            throw new \InvalidArgumentException('El proveedor propio no se registra por la API.');
        }

        $proveedor = DB::transaction(function () use ($datos, $tipo): Proveedor {
            $roles = $tipo->titularEsPiloto() ? ['proveedor', 'piloto'] : ['proveedor'];

            $usuarioId = $this->cuentas->crear($datos['nombre'], $datos['username'], $datos['email'] ?? null, $datos['password'], $roles);

            try {
                $proveedor = $this->estados->registrarProveedor([
                    'usuario_titular_id' => $usuarioId,
                    'tipo' => $tipo,
                    'razon_social' => $datos['razon_social'],
                    'documento_fiscal' => $datos['documento_fiscal'] ?? null,
                    'telefono' => $datos['telefono'] ?? null,
                    'nombres' => $datos['nombres'] ?? null,
                    'apellidos' => $datos['apellidos'] ?? null,
                    'documento_identidad' => $datos['documento_identidad'] ?? null,
                    'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? null,
                    'zona_base_id' => $datos['zona_base_id'] ?? null,
                    'radio_operacion_km' => $datos['radio_operacion_km'] ?? config('proveedores.radio_operacion_defecto_km'),
                    'created_by' => $usuarioId,
                    'updated_by' => $usuarioId,
                ]);
            } catch (QueryException $excepcion) {
                if (str_contains($excepcion->getMessage(), 'prv_proveedores_documento_unico') || str_contains($excepcion->getMessage(), 'prv_proveedores.documento_fiscal')) {
                    throw RegistroDuplicado::porDocumentoFiscal((string) $datos['documento_fiscal']);
                }

                throw $excepcion;
            }

            if ($tipo->titularEsPiloto()) {
                $this->estados->registrarPiloto([
                    'proveedor_id' => $proveedor->id,
                    'usuario_id' => $usuarioId,
                    'licencia_numero' => (string) $datos['licencia_numero'],
                    'licencia_vence' => (string) $datos['licencia_vence'],
                    'categoria_habilitacion' => (string) $datos['categoria_habilitacion'],
                    'created_by' => $usuarioId,
                    'updated_by' => $usuarioId,
                ]);
            }

            return $proveedor;
        });

        event(new ProveedorRegistrado($proveedor->id, $proveedor->razon_social, $tipo->value));

        return $proveedor->refresh();
    }
}
