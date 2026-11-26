<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Seguridad\Contratos\CuentaDuplicada;
use App\Dominios\Seguridad\Contratos\CuentaYaRegistrada;
use App\Dominios\Seguridad\Contratos\DatosDePersona;
use App\Dominios\Seguridad\Contratos\RegistroCuentas;
use App\Dominios\Seguridad\Contratos\ReglasDeContacto;
use App\Dominios\Seguridad\Contratos\TipoCuenta;
use App\Dominios\Solicitudes\Aplicacion\MaquinaEstados\MaquinaEstadosCliente;
use App\Dominios\Solicitudes\Dominio\Excepciones\ClienteDuplicado;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Dominio\TipoCliente;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Registro público de un cliente (especificación §10.1, `POST /clientes/registro`):
 * crea la cuenta de tipo `cliente` y su ficha `sol_clientes`. El cliente nace
 * `pendiente` (ADR 0046): entra, arma sus terrenos y borradores, pero no publica
 * ni negocia hasta que un administrador lo aprueba. Una empresa nace
 * con al menos un contacto de referencia. El panel lo reutiliza para el alta en la
 * oficina (`registrado_por` es quien lo carga).
 */
final class RegistrarCliente
{
    public function __construct(
        private readonly RegistroCuentas $cuentas,
        private readonly GestionarContactos $contactos,
        private readonly MaquinaEstadosCliente $estados,
    ) {}

    /**
     * @param  array{nombre: string, username: string, email: ?string, password: string, tipo: string, razon_social: ?string, documento_fiscal: ?string, telefono: ?string, contactos?: list<array{nombre: string, telefono: string, cargo?: ?string, es_principal?: ?bool}>, registrado_por?: ?int}  $datos
     *
     * @throws CuentaDuplicada si el `username` o el `email` ya existen.
     * @throws CuentaYaRegistrada si el teléfono o el documento ya tienen una cuenta de cliente.
     * @throws ClienteDuplicado si el documento fiscal ya pertenece a otro cliente.
     * @throws SolicitudInvalida si es una empresa y no trae al menos un contacto de referencia.
     */
    public function ejecutar(array $datos): Cliente
    {
        $cliente = DB::transaction(function () use ($datos): Cliente {
            // El teléfono se guarda en E.164 en la cuenta (único por tipo) y en la ficha. La persona se
            // identifica por su documento (ADR 0046): sin documento, la cuenta queda sin persona (TRANSITORIO).
            $telefono = ReglasDeContacto::telefono($datos['telefono'] ?? null);
            $persona = ! empty($datos['documento_numero'])
                ? new DatosDePersona($datos['documento_tipo'] ?? 'ci', $datos['documento_numero'], $datos['nombres'], $datos['apellidos'], $datos['fecha_nacimiento'] ?? null)
                : null;

            $usuarioId = $this->cuentas->crear($datos['nombre'], $datos['username'], $datos['email'] ?? null, $datos['password'], TipoCuenta::Cliente, ['cliente'], $telefono, $persona);

            // Quien registra: la propia cuenta (app) o la persona de la oficina que lo carga a mano (panel).
            $autorId = $datos['registrado_por'] ?? $usuarioId;

            try {
                // Nace `pendiente` (ADR 0046): puede entrar y armar sus terrenos y borradores, pero no publicar
                // ni negociar hasta que un administrador lo apruebe.
                $cliente = $this->estados->registrar([
                    'usuario_id' => $usuarioId,
                    'tipo' => TipoCliente::from($datos['tipo']),
                    'razon_social' => $datos['razon_social'] ?? $datos['nombre'],
                    'documento_fiscal' => $datos['documento_fiscal'] ?? null,
                    'telefono' => $telefono ?? ($datos['telefono'] ?? null),
                    'created_by' => $autorId,
                    'updated_by' => $autorId,
                ], $autorId);
            } catch (QueryException $excepcion) {
                if (str_contains($excepcion->getMessage(), 'sol_clientes_documento_unico') || str_contains($excepcion->getMessage(), 'sol_clientes.documento_fiscal')) {
                    throw ClienteDuplicado::porDocumentoFiscal((string) $datos['documento_fiscal']);
                }

                throw $excepcion;
            }

            $this->contactos->registrarIniciales($cliente, $datos['contactos'] ?? []);

            return $cliente;
        });

        // Trae los valores por defecto de la base (calificación en cero, etc.).
        return $cliente->refresh();
    }
}
