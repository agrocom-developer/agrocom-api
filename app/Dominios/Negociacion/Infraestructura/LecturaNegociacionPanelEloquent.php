<?php

namespace App\Dominios\Negociacion\Infraestructura;

use App\Dominios\Negociacion\Contratos\LecturaNegociacionPanel;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Avance;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Mensaje;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Oferta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Implementación Eloquent de {@see LecturaNegociacionPanel}. */
final class LecturaNegociacionPanelEloquent implements LecturaNegociacionPanel
{
    public function ofertas(int $solicitudId): array
    {
        return Oferta::query()
            ->where('solicitud_id', $solicitudId)
            ->orderBy('proveedor_id')
            ->orderBy('numero')
            ->get()
            ->map(static fn (Oferta $oferta): array => [
                'id' => $oferta->id,
                'numero' => $oferta->numero,
                'autor' => $oferta->autor,
                'tipo' => $oferta->tipo,
                'proveedor_id' => $oferta->proveedor_id,
                'equipo_id' => $oferta->equipo_id,
                'precio_ha' => (string) $oferta->precio_ha,
                'monto_estimado' => (string) $oferta->monto_estimado,
                'estado' => $oferta->estado->value,
                'es_definitiva' => (bool) $oferta->es_definitiva,
                'nota' => $oferta->nota,
                'vence_en' => $oferta->vence_en->toIso8601String(),
                'respondida_en' => $oferta->respondida_en?->toIso8601String(),
                'creada_en' => $oferta->created_at?->toIso8601String() ?? '',
            ])
            ->values()
            ->all();
    }

    public function ofertasVigentesPorSolicitud(array $solicitudIds): array
    {
        if ($solicitudIds === []) {
            return [];
        }

        return Oferta::query()
            ->whereIn('solicitud_id', $solicitudIds)
            ->where('estado', 'vigente')
            ->selectRaw('solicitud_id, COUNT(*) as cantidad')
            ->groupBy('solicitud_id')
            ->pluck('cantidad', 'solicitud_id')
            ->map(static fn ($cantidad): int => (int) $cantidad)
            ->all();
    }

    public function mensajes(int $solicitudId): array
    {
        return Mensaje::query()
            ->where('solicitud_id', $solicitudId)
            ->orderBy('id')
            ->get()
            ->map(static fn (Mensaje $mensaje): array => [
                'id' => $mensaje->id,
                'autor_usuario_id' => $mensaje->autor_usuario_id,
                'autor_rol' => $mensaje->autor_rol,
                'texto' => $mensaje->texto,
                'creado_en' => $mensaje->created_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    public function avances(int $solicitudId): array
    {
        return Avance::query()
            ->where('solicitud_id', $solicitudId)
            ->orderBy('secuencia')
            ->orderBy('id')
            ->get()
            ->map(static fn (Avance $avance): array => [
                'id' => $avance->id,
                'tipo' => $avance->tipo,
                'hectareas' => $avance->hectareas_ejecutadas === null ? null : (string) $avance->hectareas_ejecutadas,
                'porcentaje' => $avance->porcentaje === null ? null : (string) $avance->porcentaje,
                'nota' => $avance->nota,
                'motivo_correccion' => $avance->motivo_correccion,
                'registrado_en' => $avance->registrado_en->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    public function evidencias(int $solicitudId): array
    {
        return Evidencia::query()
            ->where('solicitud_id', $solicitudId)
            ->orderBy('id')
            ->get()
            ->map(static fn (Evidencia $evidencia): array => [
                'id' => $evidencia->id,
                'tipo' => $evidencia->tipo,
                'bytes' => $evidencia->bytes,
                'avance_id' => $evidencia->avance_id,
                'creada_en' => $evidencia->created_at?->toIso8601String() ?? '',
            ])
            ->values()
            ->all();
    }

    public function ubicacion(int $solicitudId): ?array
    {
        $fila = DB::table('neg_ubicaciones')->where('solicitud_id', $solicitudId)->first();

        if ($fila === null) {
            return null;
        }

        return [
            'latitud' => (string) $fila->latitud,
            'longitud' => (string) $fila->longitud,
            'precision_m' => $fila->precision_m === null ? null : (string) $fila->precision_m,
            'capturado_en' => (string) $fila->capturado_en,
        ];
    }

    public function rutaDeEvidencia(int $evidenciaId): ?string
    {
        $evidencia = Evidencia::query()->find($evidenciaId);

        if ($evidencia === null || ! Storage::disk('local')->exists($evidencia->archivo)) {
            return null;
        }

        return Storage::disk('local')->path($evidencia->archivo);
    }
}
