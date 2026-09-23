<?php

namespace App\Dominios\Seguridad\Dominio;

/**
 * La vista "como otro usuario" que tiene abierta una sesión (tarea 140), tal
 * como viaja en la sesión de Laravel bajo {@see self::CLAVE_SESION}.
 *
 * Es solo la bandera: no autentica nada. La sesión de autenticación real del
 * administrador (`login_interno_*`) nunca se toca; esta clave le dice al
 * middleware `AplicarVistaComo` a QUIÉN evaluar en cada request, y guarda lo
 * necesario para volver exactamente al estado de origen — el administrador
 * real y el rol activo con el que entró.
 *
 * Se guarda con tipos planos (ids y cadenas) y se lee con {@see self::desdeSesion()},
 * que descarta cualquier cosa mal formada en vez de adivinarla: una bandera
 * ilegible equivale a "no hay vista", nunca a una vista a medias.
 */
final readonly class VistaComoActiva
{
    public const CLAVE_SESION = 'vista_como';

    /**
     * @param  int  $registroId  fila de `sec_vistas_como` que la respalda y audita.
     * @param  int  $adminId  quien mira de verdad; nunca cambia durante la vista.
     * @param  int  $adminRolId  rol activo del administrador al entrar, para restaurarlo al salir.
     * @param  int  $usuarioId  cuenta observada.
     * @param  int|null  $rolId  rol activo bajo el que se ve una cuenta interna; `null` en una de portal.
     */
    public function __construct(
        public int $registroId,
        public TipoUsuario $tipo,
        public int $adminId,
        public int $adminRolId,
        public int $usuarioId,
        public ?int $rolId,
    ) {}

    /** El guard de autenticación bajo el que se evalúa al observado. */
    public function guard(): string
    {
        return $this->tipo->value;
    }

    /** @return array<string, int|string|null> */
    public function paraSesion(): array
    {
        return [
            'registro_id' => $this->registroId,
            'tipo' => $this->tipo->value,
            'admin_id' => $this->adminId,
            'admin_rol_id' => $this->adminRolId,
            'usuario_id' => $this->usuarioId,
            'rol_id' => $this->rolId,
        ];
    }

    /** `null` si el valor no es exactamente lo que {@see self::paraSesion()} escribe. */
    public static function desdeSesion(mixed $valor): ?self
    {
        if (! is_array($valor)) {
            return null;
        }

        $tipo = is_string($valor['tipo'] ?? null) ? TipoUsuario::tryFrom($valor['tipo']) : null;

        $registroId = $valor['registro_id'] ?? null;
        $adminId = $valor['admin_id'] ?? null;
        $adminRolId = $valor['admin_rol_id'] ?? null;
        $usuarioId = $valor['usuario_id'] ?? null;
        $rolId = $valor['rol_id'] ?? null;

        if (
            $tipo === null
            || ! is_int($registroId)
            || ! is_int($adminId)
            || ! is_int($adminRolId)
            || ! is_int($usuarioId)
            || ($rolId !== null && ! is_int($rolId))
        ) {
            return null;
        }

        // Una cuenta interna se ve bajo un rol; una de portal no tiene ninguno.
        if (($tipo === TipoUsuario::Interno) !== ($rolId !== null)) {
            return null;
        }

        return new self($registroId, $tipo, $adminId, $adminRolId, $usuarioId, $rolId);
    }
}
