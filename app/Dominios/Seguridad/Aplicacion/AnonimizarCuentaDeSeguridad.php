<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Anonimiza lo que Seguridad guarda de una cuenta eliminada (ADR 0047, punto 6): nombre, usuario, correo, teléfono y
 * contraseña pasan a valores sin dato personal (el teléfono y el correo quedan libres), se borra la foto y se borran, de
 * verdad, los tokens, las preferencias y los códigos. La fila de la cuenta se conserva porque servicios, ofertas y
 * calificaciones la referencian: la otra parte ve «Cuenta eliminada». Va por consulta directa, no por el modelo, para que la
 * bitácora no registre valores personales nuevos. Es idempotente.
 */
final class AnonimizarCuentaDeSeguridad
{
    public function __construct(private readonly GestionarFotoDePerfil $fotos) {}

    public function ejecutar(int $usuarioId): void
    {
        $usuario = SecUser::query()->find($usuarioId);

        if ($usuario === null) {
            return;
        }

        if ($usuario->foto_perfil_ref !== null) {
            $this->fotos->quitar($usuarioId);
        }

        DB::table('sec_token_dispositivo')->where('user_id', $usuarioId)->delete();
        DB::table('sec_user_preferencia')->where('user_id', $usuarioId)->delete();
        DB::table('sec_codigos_verificacion')->where('usuario_id', $usuarioId)->delete();

        DB::table('sec_user')->where('id', $usuarioId)->update([
            'name' => __('seguridad.eliminacion.nombre_anonimo'),
            'username' => 'eliminada-'.$usuarioId,
            'email' => null,
            'telefono' => null,
            'telefono_verificado_en' => null,
            'telefono_verificado_por' => null,
            'email_verificado_en' => null,
            // Una contraseña que nadie conoce: inutiliza el acceso aunque el estado se reactivara por error.
            'password' => bcrypt(Str::random(64)),
            'remember_token' => null,
            'state' => false,
            'updated_at' => now(),
        ]);
    }
}
