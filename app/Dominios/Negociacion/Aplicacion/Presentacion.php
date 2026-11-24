<?php

namespace App\Dominios\Negociacion\Aplicacion;

use App\Dominios\Negociacion\Infraestructura\Eloquent\Avance;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Mensaje;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Negociacion\Infraestructura\Eloquent\PagoConfirmado;

/** Arma las formas de respuesta de la negociación (las que documenta el OpenAPI). */
final class Presentacion
{
    /** @return array<string, mixed> */
    public static function oferta(Oferta $oferta): array
    {
        return [
            'id' => $oferta->id,
            'uuid_cliente' => $oferta->uuid_cliente,
            'solicitud_id' => $oferta->solicitud_id,
            'proveedor_id' => $oferta->proveedor_id,
            'equipo_id' => $oferta->equipo_id,
            'numero' => $oferta->numero,
            'autor' => $oferta->autor,
            'tipo' => $oferta->tipo,
            'precio_ha' => $oferta->precio_ha,
            'monto_estimado' => $oferta->monto_estimado,
            'estado' => $oferta->estado->value,
            'es_definitiva' => $oferta->es_definitiva,
            'nota' => $oferta->nota,
            'oferta_anterior_id' => $oferta->oferta_anterior_id,
            'distancia_km' => $oferta->distancia_km,
            'vence_en' => $oferta->vence_en->toIso8601String(),
            'respondida_en' => $oferta->respondida_en?->toIso8601String(),
            'creada_en' => $oferta->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function mensaje(Mensaje $mensaje, int $usuarioId): array
    {
        return [
            'id' => $mensaje->id,
            'uuid_cliente' => $mensaje->uuid_cliente,
            'solicitud_id' => $mensaje->solicitud_id,
            'autor_rol' => $mensaje->autor_rol,
            'propio' => $mensaje->autor_usuario_id === $usuarioId,
            'texto' => $mensaje->texto,
            'leido_en' => $mensaje->leido_en?->toIso8601String(),
            'creado_en' => $mensaje->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function avance(Avance $avance): array
    {
        return [
            'id' => $avance->id,
            'uuid_cliente' => $avance->uuid_cliente,
            'tipo' => $avance->tipo,
            'hectareas_ejecutadas' => $avance->hectareas_ejecutadas,
            'porcentaje' => $avance->porcentaje,
            'nota' => $avance->nota,
            'registrado_en' => $avance->registrado_en->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function evidencia(Evidencia $evidencia): array
    {
        return [
            'id' => $evidencia->id,
            'uuid_cliente' => $evidencia->uuid_cliente,
            'tipo' => $evidencia->tipo,
            'avance_id' => $evidencia->avance_id,
            'url' => "/api/evidencias/{$evidencia->id}/archivo",
            'subida_en' => $evidencia->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function pago(PagoConfirmado $pago): array
    {
        return [
            'id' => $pago->id,
            'uuid_cliente' => $pago->uuid_cliente,
            'solicitud_id' => $pago->solicitud_id,
            'monto' => $pago->monto,
            'metodo' => $pago->metodo,
            'nota' => $pago->nota,
            'confirmado_en' => $pago->confirmado_en->toIso8601String(),
        ];
    }
}
