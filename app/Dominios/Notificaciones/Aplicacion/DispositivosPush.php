<?php

namespace App\Dominios\Notificaciones\Aplicacion;

use App\Dominios\Notificaciones\Contratos\EnviadorDePush;
use App\Dominios\Notificaciones\Infraestructura\Eloquent\DispositivoPush;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Los tokens de push de la cuenta (`negociacion-contrato.md` §7). El alcance
 * sale siempre de la cuenta del token: se registra y se retira solo lo propio, y
 * retirar el token de otra cuenta responde como si no existiera (invariante 5).
 *
 * Registrar es idempotente por token: repetirlo deja una sola fila activa. Si el
 * aparato ya estaba registrado a otra cuenta (cambió de sesión sin retirarlo), el
 * registro pasa a la cuenta actual para que los avisos de la anterior no le lleguen.
 */
final class DispositivosPush
{
    public const array PLATAFORMAS = ['android', 'ios'];

    public function __construct(private readonly EnviadorDePush $enviador) {}

    /** @return array{0: DispositivoPush, 1: bool} el registro y si es nuevo. */
    public function registrar(int $usuarioId, string $token, string $plataforma): array
    {
        return DB::transaction(function () use ($usuarioId, $token, $plataforma): array {
            $existente = DispositivoPush::query()->where('token', $token)->lockForUpdate()->first();

            if ($existente !== null) {
                $existente->forceFill(['usuario_id' => $usuarioId, 'plataforma' => $plataforma, 'ultimo_uso_en' => Carbon::now()])->save();

                return [$existente, false];
            }

            return [DispositivoPush::query()->create([
                'usuario_id' => $usuarioId,
                'token' => $token,
                'plataforma' => $plataforma,
                'ultimo_uso_en' => Carbon::now(),
            ]), true];
        });
    }

    /** Retira un token propio; `false` si no existe o es de otra cuenta. */
    public function retirar(int $usuarioId, string $token): bool
    {
        $dispositivo = DispositivoPush::query()->where('usuario_id', $usuarioId)->where('token', $token)->first();

        if ($dispositivo === null) {
            return false;
        }

        $dispositivo->delete();

        return true;
    }

    /**
     * Avisa por push a los aparatos de la cuenta. Un fallo del envío nunca rompe la entrega
     * del aviso: la lista `GET /api/notificaciones` es la fuente de verdad.
     *
     * @param  array<string, scalar|null>  $datos
     */
    public function avisar(int $usuarioId, string $tipo, array $datos): void
    {
        $tokens = DispositivoPush::query()->where('usuario_id', $usuarioId)->pluck('token')->all();

        if ($tokens === []) {
            return;
        }

        try {
            $this->enviador->enviar($tokens, $tipo, $datos);
        } catch (Throwable $excepcion) {
            report($excepcion);
        }
    }
}
