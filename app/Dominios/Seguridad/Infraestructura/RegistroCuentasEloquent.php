<?php

namespace App\Dominios\Seguridad\Infraestructura;

use App\Dominios\Seguridad\Contratos\CuentaDuplicada;
use App\Dominios\Seguridad\Contratos\CuentaYaRegistrada;
use App\Dominios\Seguridad\Contratos\DatosCuenta;
use App\Dominios\Seguridad\Contratos\DatosDePersona;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;
use App\Dominios\Seguridad\Contratos\RegistroCuentas;
use App\Dominios\Seguridad\Contratos\TipoCuenta;
use App\Dominios\Seguridad\Dominio\NumeroDeTelefono;
use App\Dominios\Seguridad\Dominio\TipoDocumento;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Alta y lectura de cuentas del marketplace (ADR 0036 y 0046). Las cuentas son
 * `sec_user` de tipo `cliente` o `proveedor` (`tipo_cuenta`): los únicos que
 * autentican por el token de dispositivo de la app (guard `sanctum` sobre
 * `usuarios_app`) y ninguno entra al panel. Los roles de app (`cliente`,
 * `proveedor`, `piloto`) son transitorios hasta la fase A5 y no tienen ningún
 * permiso de panel.
 */
final class RegistroCuentasEloquent implements LecturaCuentas, RegistroCuentas
{
    public function crear(
        string $nombre,
        string $username,
        ?string $email,
        string $password,
        TipoCuenta $tipoCuenta,
        array $roles,
        ?string $telefono = null,
        ?DatosDePersona $persona = null,
    ): int {
        if (! $tipoCuenta->esDeApp()) {
            throw new InvalidArgumentException('Una cuenta de panel no se crea por el registro de cuentas de app.');
        }

        $telefonoNormalizado = $this->normalizarTelefono($telefono);
        $idsRol = array_map(fn (string $rol): int => $this->idDeRol($rol), $roles);

        return DB::transaction(function () use ($nombre, $username, $email, $password, $tipoCuenta, $idsRol, $telefonoNormalizado, $persona): int {
            // Teléfono y documento: si ya hay una cuenta de este tipo, no se crea nada y no se dice más (punto 26).
            if ($telefonoNormalizado !== null && $this->hayCuentaConTelefono($tipoCuenta, $telefonoNormalizado)) {
                throw new CuentaYaRegistrada($tipoCuenta);
            }

            [$personaId, $difiere] = $persona !== null ? $this->buscarOCrearPersona($persona, $tipoCuenta) : [null, false];

            $cuenta = new SecUser([
                'name' => $nombre,
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'type' => TipoUsuario::Interno,
                'tipo_cuenta' => $tipoCuenta,
                'telefono' => $telefonoNormalizado,
                'persona_id' => $personaId,
                'persona_datos_difieren' => $difiere,
                'state' => true,
            ]);

            try {
                $cuenta->save();
            } catch (QueryException $excepcion) {
                $this->relanzarComoDuplicada($excepcion, $username, $email, $tipoCuenta);
            }

            foreach ($idsRol as $idRol) {
                (new SecUserRole(['id_user' => $cuenta->id, 'id_role' => $idRol]))->save();
            }

            return $cuenta->id;
        });
    }

    public function agregarRol(int $usuarioId, string $rol): void
    {
        $idRol = $this->idDeRol($rol);

        $existe = SecUserRole::query()->where('id_user', $usuarioId)->where('id_role', $idRol)->exists();

        if (! $existe) {
            (new SecUserRole(['id_user' => $usuarioId, 'id_role' => $idRol]))->save();
        }
    }

    public function quitarRol(int $usuarioId, string $rol): void
    {
        SecUserRole::query()
            ->where('id_user', $usuarioId)
            ->where('id_role', $this->idDeRol($rol))
            ->get()
            ->each(static fn (SecUserRole $asignacion) => $asignacion->delete());
    }

    public function porIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return SecUser::query()
            ->whereIn('id', array_values(array_unique($ids)))
            ->get(['id', 'name', 'username', 'email', 'state', 'tipo_cuenta'])
            ->mapWithKeys(static fn (SecUser $cuenta): array => [
                $cuenta->id => new DatosCuenta($cuenta->id, $cuenta->name, $cuenta->username, $cuenta->email, (bool) $cuenta->state, $cuenta->tipo_cuenta),
            ])
            ->all();
    }

    public function idPorUsername(string $username): ?int
    {
        $id = SecUser::query()->where('username', $username)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function normalizarTelefono(?string $telefono): ?string
    {
        if ($telefono === null || trim($telefono) === '') {
            return null;
        }

        return NumeroDeTelefono::normalizar($telefono)
            ?? throw new InvalidArgumentException('El teléfono no tiene un formato válido.');
    }

    private function hayCuentaConTelefono(TipoCuenta $tipoCuenta, string $telefono): bool
    {
        return SecUser::query()->where('tipo_cuenta', $tipoCuenta->value)->where('telefono', $telefono)->exists();
    }

    /**
     * La persona se busca por su documento y, si no existe, se crea. Si ya existía, NO se
     * sobrescribe: si los datos enviados difieren de los que ya tenía, se marca la cuenta
     * para quien revisa. Si esa persona ya tiene una cuenta de ESTE tipo, no se crea otra.
     *
     * @return array{0: int, 1: bool} id de la persona y si los datos enviados difieren de los guardados
     *
     * @throws CuentaYaRegistrada
     */
    private function buscarOCrearPersona(DatosDePersona $datos, TipoCuenta $tipoCuenta): array
    {
        $tipo = TipoDocumento::tryFrom($datos->documentoTipo) ?? throw new InvalidArgumentException('El tipo de documento no es válido.');
        $numero = TipoDocumento::normalizarNumero($datos->documentoNumero);

        if ($numero === '') {
            throw new InvalidArgumentException('El número de documento no puede estar vacío.');
        }

        $existente = SecPersona::query()->where('documento_tipo', $tipo->value)->where('documento_numero', $numero)->first();

        if ($existente === null) {
            try {
                // Transacción anidada = punto de guardado: si el índice único rechaza el alta,
                // la transacción de afuera sigue viva para volver a buscarla (Postgres la
                // marcaría abortada si el error saliera sin él).
                $existente = DB::transaction(fn (): SecPersona => SecPersona::query()->create([
                    'documento_tipo' => $tipo,
                    'documento_numero' => $numero,
                    'nombres' => $datos->nombres,
                    'apellidos' => $datos->apellidos,
                    'fecha_nacimiento' => $datos->fechaNacimiento,
                ]));

                return [$existente->id, false];
            } catch (QueryException $excepcion) {
                // Otro registro creó a la misma persona entre la búsqueda y el alta: se usa esa.
                $existente = SecPersona::query()->where('documento_tipo', $tipo->value)->where('documento_numero', $numero)->first()
                    ?? throw $excepcion;
            }
        }

        if (SecUser::query()->where('persona_id', $existente->id)->where('tipo_cuenta', $tipoCuenta->value)->exists()) {
            throw new CuentaYaRegistrada($tipoCuenta);
        }

        $difiere = TipoDocumento::normalizarNombre($existente->nombres) !== TipoDocumento::normalizarNombre($datos->nombres)
            || TipoDocumento::normalizarNombre($existente->apellidos) !== TipoDocumento::normalizarNombre($datos->apellidos)
            || ($datos->fechaNacimiento !== null && $existente->fecha_nacimiento !== null && $existente->fecha_nacimiento->toDateString() !== $datos->fechaNacimiento);

        return [$existente->id, $difiere];
    }

    private function idDeRol(string $rol): int
    {
        $id = SecRole::query()->where('name', $rol)->value('id');

        if ($id === null) {
            throw new LogicException("El rol «{$rol}» no existe: corre SeguridadSeeder.");
        }

        return (int) $id;
    }

    /**
     * El formato del mensaje difiere por driver: Postgres nombra el índice; SQLite, el
     * motor de los tests, nombra tabla.columna. Se revisan ambos. Una carrera entre dos
     * registros iguales cae acá porque el índice único es la última defensa.
     */
    private function relanzarComoDuplicada(QueryException $excepcion, string $username, ?string $email, TipoCuenta $tipoCuenta): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'sec_user_username_unico') || str_contains($mensaje, 'sec_user.username')) {
            throw CuentaDuplicada::porUsername($username);
        }

        if (str_contains($mensaje, 'sec_user_telefono_tipo_unico') || str_contains($mensaje, 'sec_user.telefono')
            || str_contains($mensaje, 'sec_user_persona_tipo_unico') || str_contains($mensaje, 'sec_user.persona_id')) {
            throw new CuentaYaRegistrada($tipoCuenta);
        }

        if ($email !== null && (str_contains($mensaje, 'sec_user_email_tipo_unico') || str_contains($mensaje, 'sec_user.email'))) {
            throw CuentaDuplicada::porEmail($email);
        }

        throw $excepcion;
    }
}
