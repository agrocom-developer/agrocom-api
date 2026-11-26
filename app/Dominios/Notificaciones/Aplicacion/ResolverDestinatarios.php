<?php

namespace App\Dominios\Notificaciones\Aplicacion;

use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Seguridad\Contratos\LecturaUsuariosPorRol;
use Throwable;

/**
 * Convierte lo que una regla declara (roles o cuentas concretas) en las cuentas a las que
 * se reparte el aviso (ADR 0025, punto 3). Es la única clase que sabe qué contrato
 * consultar para cada tipo de destinatario; `Notificaciones` no lee ninguna tabla ajena,
 * todo va por `Contratos/`.
 *
 * - `rol`: toda cuenta de panel activa con el rol ASIGNADO, aunque hoy opere con otro.
 *   Una notificación no es un permiso (invariante 10).
 * - `usuario`: esa cuenta.
 *
 * Los destinatarios por persona y por equipo (modelo de personal anterior) se retiraron
 * con `sec_user.persona_id` (ADR 0046): la persona es ahora el documento de una cuenta de
 * app, no un vínculo que se resuelva a una cuenta.
 *
 * Cada destinatario se resuelve aislado: si uno falla, se reporta y los demás siguen.
 */
final class ResolverDestinatarios
{
    public function __construct(private readonly LecturaUsuariosPorRol $usuariosPorRol) {}

    /**
     * @param  list<Destinatario>  $destinatarios
     * @return list<int> ids de `sec_user`, sin repetir y en orden ascendente
     */
    public function ejecutar(array $destinatarios): array
    {
        $cuentas = [];

        foreach ($destinatarios as $destinatario) {
            // Cada destinatario se resuelve aislado: uno con datos rotos se reporta y no anula el aviso de los demás.
            try {
                foreach ($this->cuentasDe($destinatario) as $usuarioId) {
                    $cuentas[$usuarioId] = $usuarioId;
                }
            } catch (Throwable $excepcion) {
                report($excepcion);
            }
        }

        ksort($cuentas);

        return array_values($cuentas);
    }

    /** @return list<int> */
    private function cuentasDe(Destinatario $destinatario): array
    {
        return match ($destinatario->tipo) {
            Destinatario::ROL => $this->usuariosPorRol->idsConRol((string) $destinatario->referencia),
            Destinatario::USUARIO => [(int) $destinatario->referencia],
            default => [],
        };
    }
}
