<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Dominio\MotivoFinVistaComo;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Dominio\VistaComoActiva;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecVistaComo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use LogicException;

/**
 * Cierra la vista "como otro usuario" de la sesión y devuelve al
 * administrador a su estado exacto de origen (tarea 140): borra la bandera de
 * sesión, anota en `sec_vistas_como` cuándo y por qué terminó — lo que la
 * bitácora registra sola como un `actualizado`, con el administrador real como
 * actor — y, si la vista era de una cuenta interna, restaura el rol activo con
 * el que había entrado.
 *
 * Como no hubo un login como el otro, no hay nada que deshacer en la
 * autenticación: por eso salir no puede dejar un estado a mitad de camino.
 *
 * Precondición que se EXIGE, no se supone: el guard `interno` tiene que ser el
 * administrador real. El actor de la bitácora (y `updated_by`) salen del guard,
 * y si en este punto el guard fuera la cuenta observada, la salida quedaría
 * firmada por quien no la hizo. Quien llama ({@see AplicarVistaComo} y el
 * controlador de salida) lo garantiza cerrando ANTES de sustituir el guard.
 */
final class TerminarVistaComo
{
    public function __construct(private readonly ElegirRolActivo $elegirRolActivo) {}

    /**
     * @throws LogicException si el guard `interno` no es el administrador que abrió la vista.
     */
    public function ejecutar(VistaComoActiva $vista, MotivoFinVistaComo $motivo): void
    {
        if ((int) Auth::guard('interno')->id() !== $vista->adminId) {
            throw new LogicException('La vista como otro usuario solo se cierra con el guard interno siendo el administrador que la abrió.');
        }

        DB::transaction(function () use ($vista, $motivo): void {
            $registro = SecVistaComo::query()->find($vista->registroId);

            if ($registro !== null && $registro->finalizada_at === null) {
                $registro->finalizada_at = now();
                $registro->motivo_fin = $motivo;
                $registro->save();
            }
        });

        Session::forget(VistaComoActiva::CLAVE_SESION);

        if ($vista->tipo === TipoUsuario::Interno) {
            $admin = SecUser::query()->find($vista->adminId);

            if ($admin !== null) {
                $this->elegirRolActivo->restaurarTrasVistaComo($admin, $vista->adminRolId);
            }
        }
    }
}
