<?php

namespace App\Dominios\Negociacion\Aplicacion;

use App\Dominios\Compartido\Aplicacion\Medios\AlmacenDeMedios;
use App\Dominios\Compartido\Aplicacion\Medios\LimiteDeMedios;
use App\Dominios\Compartido\Dominio\VarianteDeMedio;
use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Mensaje;
use App\Dominios\Solicitudes\Contratos\DatosSolicitudNegociable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

/**
 * Chat sencillo entre el cliente y el proveedor adjudicado (`negociacion-contrato.md` §5): mensajes
 * de texto, fotos (con las reglas de las evidencias: peso máximo y disco privado) y eventos de
 * sistema que el servidor escribe al cambiar la solicitud.
 * Desde `adjudicada`; se escribe hasta el cierre del plazo de calificación (7 días
 * después de `finalizada`) o mientras haya una disputa abierta, y después queda de solo
 * lectura. La consulta es incremental por cursor (el id del último mensaje recibido): el
 * cliente sondea cada pocos segundos con el chat abierto; un canal en tiempo real
 * podría reemplazar el sondeo sin cambiar los recursos.
 */
final class Mensajeria
{
    public const int LIMITE_TEXTO = 1000;

    /** Límite del pie de una foto. */
    public const int LIMITE_PIE = 280;

    /** Hecho de la negociación (tipo de `AvisoDeNegociacion`) => evento de sistema del chat. */
    public const array EVENTOS_DE_SISTEMA = [
        'solicitud_adjudicada' => 'solicitud_adjudicada',
        'ejecucion_iniciada' => 'servicio_iniciado',
        'solicitud_finalizada' => 'servicio_finalizado',
        'precio_final_confirmado' => 'precio_final_confirmado',
    ];

    private const string ESPACIO_UUID_EVENTOS = '6f1b8c9e-3d2a-4b7e-9a51-0c4d7e2f8a13';

    public function __construct(
        private readonly Participantes $participantes,
        private readonly ParametrosDeNegociacion $parametros,
        private readonly Avisador $avisos,
        private readonly AlmacenDeMedios $almacen,
        private readonly LimiteDeMedios $limites,
    ) {}

    /**
     * @return array{0: Mensaje, 1: bool} el mensaje y si se creó ahora (`false` = reintento del mismo uuid).
     *
     * @throws NegociacionInvalida
     */
    public function enviar(int $usuarioId, int $solicitudId, string $uuidCliente, string $texto): array
    {
        [$solicitud, $lado] = $this->participantes->lado($usuarioId, $solicitudId);

        $existente = Mensaje::query()->where('uuid_cliente', $uuidCliente)->first();
        if ($existente !== null) {
            if ($existente->solicitud_id !== $solicitudId || $existente->autor_usuario_id !== $usuarioId) {
                throw NegociacionInvalida::dato('uuid_en_uso', 'uuid_cliente');
            }

            return [$existente, false];
        }

        $this->exigirAbierto($solicitud);

        $texto = trim($texto);
        if ($texto === '' || mb_strlen($texto) > self::LIMITE_TEXTO) {
            throw NegociacionInvalida::dato('mensaje_invalido', 'texto', ['maximo' => self::LIMITE_TEXTO]);
        }

        try {
            $mensaje = Mensaje::query()->create([
                'uuid_cliente' => $uuidCliente,
                'solicitud_id' => $solicitudId,
                'autor_usuario_id' => $usuarioId,
                'autor_rol' => $lado,
                'tipo' => 'texto',
                'texto' => $texto,
            ]);
        } catch (UniqueConstraintViolationException) {
            return [Mensaje::query()->where('uuid_cliente', $uuidCliente)->firstOrFail(), false];
        }

        $destinatarios = $lado === Participantes::CLIENTE ? $this->participantes->cuentasDelProveedor($solicitud) : [$solicitud->clienteUsuarioId];
        $this->avisos->avisar('mensaje_nuevo', "mensaje_nuevo:{$mensaje->id}", ['solicitud' => $solicitudId], $solicitudId, array_values(array_diff($destinatarios, [$usuarioId])), 'mensajes');

        return [$mensaje, true];
    }

    /**
     * Envía una foto al chat. Mismas reglas que una evidencia: pesa menos de `seguimiento.evidencia_max_kb`
     * (la app la comprime antes) y se guarda en el disco privado. Idempotente por `uuid_cliente`.
     *
     * @return array{0: Mensaje, 1: bool} el mensaje y si se creó ahora.
     *
     * @throws NegociacionInvalida
     */
    public function enviarFoto(int $usuarioId, int $solicitudId, string $uuidCliente, UploadedFile $archivo, ?string $pie): array
    {
        [$solicitud, $lado] = $this->participantes->lado($usuarioId, $solicitudId);

        $existente = Mensaje::query()->where('uuid_cliente', $uuidCliente)->first();
        if ($existente !== null) {
            if ($existente->solicitud_id !== $solicitudId || $existente->autor_usuario_id !== $usuarioId) {
                throw NegociacionInvalida::dato('uuid_en_uso', 'uuid_cliente');
            }

            return [$existente, false];
        }

        $this->exigirAbierto($solicitud);

        $pie = trim((string) $pie);
        if (mb_strlen($pie) > self::LIMITE_PIE) {
            throw NegociacionInvalida::dato('mensaje_invalido', 'texto', ['maximo' => self::LIMITE_PIE]);
        }

        if ($archivo->getSize() > $this->parametros->evidenciaMaxKb() * 1024) {
            throw NegociacionInvalida::dato('evidencia_muy_grande', 'archivo', ['maximo' => $this->parametros->evidenciaMaxKb()]);
        }

        if ($this->limites->excede('servicio_chat', Mensaje::query()->where('solicitud_id', $solicitudId)->where('tipo', 'foto')->count())) {
            throw NegociacionInvalida::dato('limite_fotos_del_chat', 'archivo', ['maximo' => $this->limites->porEntidad('servicio_chat')]);
        }

        $ruta = $this->almacen->guardar($archivo, "servicios/{$solicitudId}/chat", $uuidCliente);

        try {
            $mensaje = Mensaje::query()->create([
                'uuid_cliente' => $uuidCliente,
                'solicitud_id' => $solicitudId,
                'autor_usuario_id' => $usuarioId,
                'autor_rol' => $lado,
                'tipo' => 'foto',
                'texto' => $pie,
                'archivo' => $ruta,
                'hash' => hash_file('sha256', $archivo->getRealPath()) ?: '',
                'bytes' => (int) $archivo->getSize(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return [Mensaje::query()->where('uuid_cliente', $uuidCliente)->firstOrFail(), false];
        }

        $destinatarios = $lado === Participantes::CLIENTE ? $this->participantes->cuentasDelProveedor($solicitud) : [$solicitud->clienteUsuarioId];
        $this->avisos->avisar('mensaje_nuevo', "mensaje_nuevo:{$mensaje->id}", ['solicitud' => $solicitudId], $solicitudId, array_values(array_diff($destinatarios, [$usuarioId])), 'mensajes');

        return [$mensaje, true];
    }

    /**
     * La ruta de la foto de un mensaje, para las dos partes del servicio.
     *
     * @return array{0: Mensaje, 1: string}
     *
     * @throws NegociacionInvalida `evidencia_ajena` (404)
     */
    public function archivoDeFoto(int $usuarioId, int $mensajeId, VarianteDeMedio $variante = VarianteDeMedio::Original): array
    {
        $mensaje = Mensaje::query()->where('tipo', 'foto')->find($mensajeId) ?? throw NegociacionInvalida::ajeno('evidencia_ajena');
        $this->participantes->lado($usuarioId, $mensaje->solicitud_id);

        $ruta = $this->almacen->rutaAbsoluta($mensaje->archivo, $variante);

        if ($ruta === null) {
            throw NegociacionInvalida::ajeno('evidencia_ajena');
        }

        return [$mensaje, $ruta];
    }

    /**
     * Escribe un mensaje de sistema («El piloto inició el servicio») cuando la negociación avisa de un
     * hecho de la solicitud. Sin autor ni aviso propio (el hecho ya avisó); se escribe aunque el chat
     * esté en solo lectura. Idempotente por la clave del hecho: repetirlo no duplica la línea.
     *
     * @param  array<string, string|int>  $parametros
     */
    public function registrarEvento(int $solicitudId, string $evento, string $claveEvento, array $parametros = []): ?Mensaje
    {
        $uuid = Uuid::uuid5(self::ESPACIO_UUID_EVENTOS, $claveEvento)->toString();

        if (Mensaje::query()->where('uuid_cliente', $uuid)->exists()) {
            return null;
        }

        try {
            return Mensaje::query()->create([
                'uuid_cliente' => $uuid,
                'solicitud_id' => $solicitudId,
                'autor_usuario_id' => null,
                'autor_rol' => 'sistema',
                'tipo' => 'sistema',
                'evento' => $evento,
                'texto' => Texto::de("negociacion.chat_sistema.{$evento}", $parametros),
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /**
     * Los mensajes posteriores al cursor, en orden de creación.
     *
     * @return array{mensajes: list<Mensaje>, cursor: int|null, solo_lectura: bool}
     *
     * @throws NegociacionInvalida
     */
    public function listar(int $usuarioId, int $solicitudId, ?int $despues, int $limite): array
    {
        [$solicitud] = $this->participantes->lado($usuarioId, $solicitudId);

        $mensajes = Mensaje::query()
            ->where('solicitud_id', $solicitudId)
            ->when($despues !== null, fn ($q) => $q->where('id', '>', $despues))
            ->orderBy('id')
            ->limit(max(1, min($limite, 100)))
            ->get();

        return [
            'mensajes' => $mensajes->all(),
            'cursor' => $mensajes->isEmpty() ? $despues : $mensajes->last()->id,
            'solo_lectura' => $this->soloLectura($solicitud),
        ];
    }

    /** Marca como leídos los mensajes de la contraparte hasta el cursor. */
    public function marcarLeidos(int $usuarioId, int $solicitudId, int $hasta): int
    {
        $this->participantes->lado($usuarioId, $solicitudId);

        return Mensaje::query()
            ->where('solicitud_id', $solicitudId)
            ->where('id', '<=', $hasta)
            ->where('tipo', '!=', 'sistema')
            ->where('autor_usuario_id', '!=', $usuarioId)
            ->whereNull('leido_en')
            ->update(['leido_en' => Carbon::now()]);
    }

    /** @throws NegociacionInvalida `chat_cerrado` (409) */
    private function exigirAbierto(DatosSolicitudNegociable $solicitud): void
    {
        if ($this->soloLectura($solicitud)) {
            throw NegociacionInvalida::conflicto('chat_cerrado');
        }
    }

    private function soloLectura(DatosSolicitudNegociable $solicitud): bool
    {
        return match ($solicitud->estado) {
            'adjudicada', 'en_ejecucion', 'disputada' => false,
            'finalizada', 'calificada' => $solicitud->finalizadaEn === null
                || Carbon::parse($solicitud->finalizadaEn)->addDays($this->parametros->plazoCalificacionDias())->lt(Carbon::now()),
            default => true,
        };
    }
}
