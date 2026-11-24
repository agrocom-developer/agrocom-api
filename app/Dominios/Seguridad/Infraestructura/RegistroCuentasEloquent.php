<?php

namespace App\Dominios\Seguridad\Infraestructura;

use App\Dominios\Seguridad\Contratos\CuentaDuplicada;
use App\Dominios\Seguridad\Contratos\DatosCuenta;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;
use App\Dominios\Seguridad\Contratos\RegistroCuentas;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Alta y lectura de cuentas del marketplace (ADR 0036). Las cuentas son
 * `sec_user` de tipo `interno`: es el único tipo que autentica por el token de
 * dispositivo de la app (guard `sanctum` sobre `usuarios_internos`); lo que
 * separa a un cliente de un proveedor es el rol, y los roles del marketplace
 * no tienen ningún permiso de panel salvo los suyos.
 */
final class RegistroCuentasEloquent implements LecturaCuentas, RegistroCuentas
{
    public function crear(string $nombre, string $username, ?string $email, string $password, array $roles): int
    {
        $idsRol = array_map(fn (string $rol): int => $this->idDeRol($rol), $roles);

        return DB::transaction(function () use ($nombre, $username, $email, $password, $idsRol): int {
            $cuenta = new SecUser([
                'name' => $nombre,
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'type' => TipoUsuario::Interno,
                'persona_id' => null,
                'contrato_id' => null,
                'state' => true,
            ]);

            try {
                $cuenta->save();
            } catch (QueryException $excepcion) {
                $this->relanzarComoDuplicada($excepcion, $username, $email);
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
            ->get(['id', 'name', 'username', 'email', 'state'])
            ->mapWithKeys(static fn (SecUser $cuenta): array => [
                $cuenta->id => new DatosCuenta($cuenta->id, $cuenta->name, $cuenta->username, $cuenta->email, (bool) $cuenta->state),
            ])
            ->all();
    }

    public function idPorUsername(string $username): ?int
    {
        $id = SecUser::query()->where('username', $username)->value('id');

        return $id === null ? null : (int) $id;
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
     * El formato del mensaje difiere por driver (Postgres nombra el índice;
     * SQLite, el motor de los tests, nombra tabla.columna): se revisan ambos.
     */
    private function relanzarComoDuplicada(QueryException $excepcion, string $username, ?string $email): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'sec_user_username_unico') || str_contains($mensaje, 'sec_user.username')) {
            throw CuentaDuplicada::porUsername($username);
        }

        if ($email !== null && (str_contains($mensaje, 'sec_user_email_unico') || str_contains($mensaje, 'sec_user.email'))) {
            throw CuentaDuplicada::porEmail($email);
        }

        throw $excepcion;
    }
}
