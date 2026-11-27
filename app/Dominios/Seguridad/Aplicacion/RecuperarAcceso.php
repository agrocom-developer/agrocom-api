<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Contratos\CanalDeCodigo;
use App\Dominios\Seguridad\Contratos\PropositoDeCodigo;
use App\Dominios\Seguridad\Contratos\TipoCuenta;
use App\Dominios\Seguridad\Dominio\Excepciones\CanalNoDisponible;
use App\Dominios\Seguridad\Dominio\Excepciones\CodigoInvalido;
use App\Dominios\Seguridad\Dominio\Excepciones\DemasiadosCodigos;
use App\Dominios\Seguridad\Dominio\NumeroDeTelefono;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioApp;
use Illuminate\Support\Facades\DB;

/**
 * Recuperar el acceso desde la app (ADR 0046, punto 27): la persona da su usuario, teléfono o correo, la app desde la que
 * escribe y el canal por el que quiere recibir el código; con el código fija una contraseña nueva y la app le muestra su
 * usuario. Nada de lo que responde distingue una cuenta que existe de una que no.
 */
final class RecuperarAcceso
{
    public function __construct(
        private readonly CodigosDeVerificacion $codigos,
        private readonly RevocarTokensDeCuenta $revocar,
    ) {}

    /**
     * Envía el código si la cuenta existe. Si no existe, está bloqueada, no tiene a dónde enviarlo o ya pidió demasiados,
     * no hace nada: quien llama responde lo mismo en todos los casos.
     *
     * @throws CanalNoDisponible el canal no está disponible en el sistema (no depende de la cuenta).
     */
    public function solicitar(string $identificador, TipoCuenta $app, CanalDeCodigo $canal): void
    {
        $this->codigos->exigirCanalDisponible($canal);

        $usuario = $this->cuenta($identificador, $app);

        if ($usuario === null || ! $usuario->state) {
            return;
        }

        try {
            $this->codigos->emitir($usuario, PropositoDeCodigo::RecuperarAcceso, $canal);
        } catch (DemasiadosCodigos) {
            // Se calla a propósito: responder distinto revelaría que la cuenta existe.
        }
    }

    /**
     * Fija la contraseña nueva con el código, cierra todas las sesiones y devuelve la cuenta (para mostrar su usuario).
     *
     * @throws CodigoInvalido cuenta inexistente, código errado, vencido, usado o con los intentos agotados: siempre igual.
     */
    public function confirmar(string $identificador, TipoCuenta $app, string $codigo, string $passwordNueva): SecUser
    {
        $usuario = $this->cuenta($identificador, $app);

        if ($usuario === null || ! $usuario->state) {
            throw new CodigoInvalido;
        }

        $canal = $this->codigos->confirmar($usuario, PropositoDeCodigo::RecuperarAcceso, $codigo);

        DB::transaction(function () use ($usuario, $canal, $passwordNueva): void {
            $usuario->password = $passwordNueva;
            (new MarcarCanalVerificado)->ejecutar($usuario, $canal);
            $this->revocar->ejecutar($usuario);
        });

        return $usuario;
    }

    /** Una cuenta de la app dada por usuario, teléfono (en cualquier formato) o correo; `null` si no hay una sola. */
    private function cuenta(string $identificador, TipoCuenta $app): ?SecUser
    {
        $texto = trim($identificador);

        if ($texto === '') {
            return null;
        }

        $telefono = NumeroDeTelefono::normalizar($texto);

        $cuentas = SecUsuarioApp::query()
            ->where('tipo_cuenta', $app->value)
            ->where(function ($consulta) use ($texto, $telefono): void {
                $consulta->where('username', $texto)->orWhereRaw('lower(email) = ?', [mb_strtolower($texto)]);

                if ($telefono !== null) {
                    $consulta->orWhere('telefono', $telefono);
                }
            })
            ->limit(2)
            ->get();

        return $cuentas->count() === 1 ? $cuentas->first() : null;
    }
}
