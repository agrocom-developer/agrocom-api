<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Proveedores\Aplicacion\MaquinaEstados\MaquinaEstadosAprobacion;
use App\Dominios\Proveedores\Contratos\Eventos\ProveedorRegistrado;
use App\Dominios\Proveedores\Dominio\Excepciones\RegistroDuplicado;
use App\Dominios\Proveedores\Dominio\TipoProveedor;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Piloto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use App\Dominios\Seguridad\Contratos\CuentaDuplicada;
use App\Dominios\Seguridad\Contratos\CuentaYaRegistrada;
use App\Dominios\Seguridad\Contratos\DatosDePersona;
use App\Dominios\Seguridad\Contratos\RegistroCuentas;
use App\Dominios\Seguridad\Contratos\ReglasDeContacto;
use App\Dominios\Seguridad\Contratos\TipoCuenta;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Registro público de un proveedor independiente o tercero (ADR 0032, punto 1):
 * crea la cuenta (rol `proveedor`, más `piloto` si es independiente), el
 * proveedor `pendiente` y, para el independiente, su propio piloto `pendiente`.
 * Nada queda aprobado: solo un administrador aprueba. Todo en una transacción:
 * si algo falla no queda una cuenta huérfana.
 *
 * Nunca registra el tipo `propio`: Agrocom se siembra, no se auto-registra. El panel
 * lo reutiliza para el alta en la oficina (`registrado_por` es quien lo carga y no
 * podrá aprobarlo).
 */
final class RegistrarProveedor
{
    public function __construct(
        private readonly RegistroCuentas $cuentas,
        private readonly MaquinaEstadosAprobacion $estados,
    ) {}

    /**
     * @param  array{tipo: string, nombre: string, username: string, email: ?string, password: string, telefono: ?string, razon_social: string, documento_fiscal: ?string, licencia_numero?: ?string, licencia_vence?: ?string, categoria_habilitacion?: ?string, nombres?: ?string, apellidos?: ?string, documento_identidad?: ?string, fecha_nacimiento?: ?string, registrado_por?: ?int, documento_tipo?: ?string, documento_numero?: ?string}  $datos
     *
     * @throws CuentaDuplicada si el `username` o el `email` ya existen.
     * @throws CuentaYaRegistrada si el teléfono o el documento ya tienen una cuenta de proveedor.
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

            // Teléfono en E.164 (único por tipo) y persona por documento (ADR 0046): quien ya tiene una cuenta
            // de proveedor, por teléfono o por documento, no abre otra. `documento_identidad` es el nombre de
            // siempre del número de documento; `documento_numero` es el nuevo y manda si llega.
            $telefono = ReglasDeContacto::telefono($datos['telefono'] ?? null);
            $numero = $datos['documento_numero'] ?? $datos['documento_identidad'] ?? null;
            $persona = ! empty($numero) && ! empty($datos['nombres']) && ! empty($datos['apellidos'])
                ? new DatosDePersona($datos['documento_tipo'] ?? 'ci', (string) $numero, $datos['nombres'], $datos['apellidos'], $datos['fecha_nacimiento'] ?? null)
                : null;

            $usuarioId = $this->cuentas->crear($datos['nombre'], $datos['username'], $datos['email'] ?? null, $datos['password'], TipoCuenta::Proveedor, $roles, $telefono, $persona);
            // Quien registra: la propia cuenta (app) o la persona de la oficina que lo carga a mano (panel).
            $autorId = $datos['registrado_por'] ?? $usuarioId;

            try {
                $proveedor = $this->estados->registrarProveedor([
                    'usuario_titular_id' => $usuarioId,
                    'tipo' => $tipo,
                    'razon_social' => $datos['razon_social'],
                    'documento_fiscal' => $datos['documento_fiscal'] ?? null,
                    'telefono' => $telefono ?? ($datos['telefono'] ?? null),
                    'nombres' => $datos['nombres'] ?? null,
                    'apellidos' => $datos['apellidos'] ?? null,
                    'documento_identidad' => $datos['documento_numero'] ?? $datos['documento_identidad'] ?? null,
                    'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? null,
                    'created_by' => $autorId,
                    'updated_by' => $autorId,
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
                    'created_by' => $autorId,
                    'updated_by' => $autorId,
                ]);
            }

            return $proveedor;
        });

        event(new ProveedorRegistrado($proveedor->id, $proveedor->razon_social, $tipo->value));

        return $proveedor->refresh();
    }
}
