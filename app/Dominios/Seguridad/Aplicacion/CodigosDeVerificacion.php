<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Contratos\CanalDeCodigo;
use App\Dominios\Seguridad\Contratos\DestinoDeCodigo;
use App\Dominios\Seguridad\Contratos\EnviadorDeCodigo;
use App\Dominios\Seguridad\Contratos\PropositoDeCodigo;
use App\Dominios\Seguridad\Dominio\Excepciones\CanalNoDisponible;
use App\Dominios\Seguridad\Dominio\Excepciones\CodigoInvalido;
use App\Dominios\Seguridad\Dominio\Excepciones\DemasiadosCodigos;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecCodigoVerificacion;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Códigos de un solo uso de las cuentas de app (ADR 0046, puntos 27 a 30): los emite por el canal que la persona elige y
 * los confirma. Un mismo mecanismo sirve a la verificación del teléfono, la recuperación del acceso, el cambio de
 * contraseña y de teléfono y la eliminación de la cuenta.
 *
 * El código tiene 6 dígitos, se guarda con HMAC (nunca en claro), vence a los 10 minutos, se usa una vez y admite 5
 * intentos. Hay un solo código vigente por cuenta y propósito: pedir otro anula el anterior. Cada cuenta puede pedir 3 por
 * hora y 10 por día, y entre dos por el mismo canal pasan al menos 30 segundos (429 con `Retry-After` en ambos casos). El código no se escribe en logs ni en respuestas.
 */
final class CodigosDeVerificacion
{
    public const CODIGO_DE_DESARROLLO = '000000';

    public function __construct(private readonly EnviadorDeCodigo $enviador) {}

    /** El código fijo solo vale con `VERIFICACION_MODO_DESARROLLO` en `local` o `testing`: en otro entorno se ignora. */
    public function modoDeDesarrollo(): bool
    {
        return (bool) config('verificacion.modo_desarrollo') && in_array(config('app.env'), ['local', 'testing'], true);
    }

    /**
     * Se comprueba ANTES de buscar la cuenta: la disponibilidad es del sistema, no de la cuenta, y así un `503` nunca
     * revela si la cuenta existe.
     *
     * @throws CanalNoDisponible
     */
    public function exigirCanalDisponible(CanalDeCodigo $canal): void
    {
        if (! $this->enviador->disponible($canal)) {
            throw new CanalNoDisponible($canal);
        }
    }

    /**
     * Emite y envía un código nuevo, anulando el vigente del mismo propósito.
     *
     * @param  string|null  $destinoAlterno  Teléfono (E.164) o correo a donde enviarlo en lugar del de la cuenta: el teléfono
     *                                       nuevo de un cambio de teléfono.
     * @return string|null el destino enmascarado (`+591 ••••• 45`), o `null` si la cuenta no tiene a dónde enviarlo por ese canal (sin teléfono o sin correo): no se envió nada.
     *
     * @throws CanalNoDisponible
     * @throws DemasiadosCodigos
     */
    public function emitir(SecUser $usuario, PropositoDeCodigo $proposito, CanalDeCodigo $canal, ?string $destinoAlterno = null): ?string
    {
        $this->exigirCanalDisponible($canal);

        $valor = $destinoAlterno ?? ($canal->esDeTelefono() ? $usuario->telefono : $usuario->email);

        if ($valor === null || $valor === '') {
            return null;
        }

        $this->exigirCupo($usuario);
        $this->exigirIntervalo($usuario, $canal);

        $codigo = $this->modoDeDesarrollo()
            ? self::CODIGO_DE_DESARROLLO
            : str_pad((string) random_int(0, 999999), (int) config('verificacion.codigo.longitud', 6), '0', STR_PAD_LEFT);

        $registro = DB::transaction(function () use ($usuario, $proposito, $canal, $valor, $codigo): SecCodigoVerificacion {
            SecCodigoVerificacion::query()
                ->where('usuario_id', $usuario->id)
                ->where('proposito', $proposito->value)
                ->whereNull('usado_en')
                ->update(['usado_en' => now()]);

            return SecCodigoVerificacion::query()->create([
                'usuario_id' => $usuario->id,
                'proposito' => $proposito->value,
                'canal' => $canal->value,
                'destino_enmascarado' => $this->enmascarar($canal, $valor),
                'codigo_hash' => $this->hash($usuario->id, $proposito, $codigo),
                'intentos' => 0,
                'vence_en' => now()->addMinutes((int) config('verificacion.codigo.vigencia_minutos', 10)),
            ]);
        });

        try {
            $this->enviador->enviar(new DestinoDeCodigo($canal, $valor, $usuario->name), $codigo, $proposito);
        } catch (Throwable $falla) {
            // Un envío que falló no consume cupo ni deja un código que nadie recibió.
            $registro->delete();

            throw $falla;
        }

        return $registro->destino_enmascarado;
    }

    /**
     * Confirma el código vigente del propósito. Gasta un intento por cada confirmación fallida y lo usa al acertar.
     *
     * @return CanalDeCodigo el canal por el que se había enviado: confirmarlo prueba la posesión de ese teléfono o correo.
     *
     * @throws CodigoInvalido
     */
    public function confirmar(SecUser $usuario, PropositoDeCodigo $proposito, string $codigo): CanalDeCodigo
    {
        $resultado = DB::transaction(function () use ($usuario, $proposito, $codigo): ?CanalDeCodigo {
            $registro = SecCodigoVerificacion::query()
                ->where('usuario_id', $usuario->id)
                ->where('proposito', $proposito->value)
                ->whereNull('usado_en')
                ->where('vence_en', '>', now())
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($registro === null || $registro->intentos >= (int) config('verificacion.codigo.intentos', 5)) {
                return null;
            }

            $registro->intentos++;

            if (! hash_equals($registro->codigo_hash, $this->hash($usuario->id, $proposito, $codigo))) {
                $registro->save();

                return null;
            }

            $registro->usado_en = now();
            $registro->save();

            return $registro->canal;
        });

        return $resultado ?? throw new CodigoInvalido;
    }

    /** @throws DemasiadosCodigos */
    private function exigirCupo(SecUser $usuario): void
    {
        $porHora = SecCodigoVerificacion::query()->where('usuario_id', $usuario->id)->where('created_at', '>', now()->subHour())->count();
        $porDia = SecCodigoVerificacion::query()->where('usuario_id', $usuario->id)->where('created_at', '>', now()->subDay())->count();

        if ($porHora >= (int) config('verificacion.codigo.envios_por_hora', 3)) {
            throw new DemasiadosCodigos($this->segundosHasta($usuario, now()->subHour()));
        }

        if ($porDia >= (int) config('verificacion.codigo.envios_por_dia', 10)) {
            throw new DemasiadosCodigos($this->segundosHasta($usuario, now()->subDay(), 86400));
        }
    }

    /**
     * Espera mínima entre dos códigos por el mismo canal de la misma cuenta (`verificacion.codigo.intervalo_reenvio_segundos`).
     *
     * @throws DemasiadosCodigos
     */
    private function exigirIntervalo(SecUser $usuario, CanalDeCodigo $canal): void
    {
        $intervalo = (int) config('verificacion.codigo.intervalo_reenvio_segundos', 30);

        if ($intervalo <= 0) {
            return;
        }

        $ultimo = SecCodigoVerificacion::query()->where('usuario_id', $usuario->id)->where('canal', $canal->value)->max('created_at');

        if ($ultimo === null) {
            return;
        }

        $faltan = (int) ceil(now()->diffInSeconds(Carbon::parse($ultimo)->addSeconds($intervalo), false));

        if ($faltan > 0) {
            throw new DemasiadosCodigos($faltan, DemasiadosCodigos::INTERVALO);
        }
    }

    private function segundosHasta(SecUser $usuario, Carbon $desde, int $ventana = 3600): int
    {
        $primero = SecCodigoVerificacion::query()->where('usuario_id', $usuario->id)->where('created_at', '>', $desde)->min('created_at');

        if ($primero === null) {
            return $ventana;
        }

        return max(1, (int) now()->diffInSeconds(Carbon::parse($primero)->addSeconds($ventana), false));
    }

    private function hash(int $usuarioId, PropositoDeCodigo $proposito, string $codigo): string
    {
        return hash_hmac('sha256', $usuarioId.'|'.$proposito->value.'|'.trim($codigo), (string) config('app.key'));
    }

    /** `+591 ••••• 45` o `ca•••@correo.com`: lo justo para que la persona reconozca adónde fue, sin guardar el dato. */
    private function enmascarar(CanalDeCodigo $canal, string $valor): string
    {
        if ($canal === CanalDeCodigo::Correo) {
            [$local, $dominio] = array_pad(explode('@', $valor, 2), 2, '');

            return mb_substr($local, 0, 2).'•••@'.$dominio;
        }

        return mb_substr($valor, 0, 4).' ••••• '.mb_substr($valor, -2);
    }
}
