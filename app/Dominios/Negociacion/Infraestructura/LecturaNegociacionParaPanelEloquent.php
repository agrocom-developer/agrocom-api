<?php

namespace App\Dominios\Negociacion\Infraestructura;

use App\Dominios\Negociacion\Contratos\LecturaNegociacionParaPanel;
use App\Dominios\Negociacion\Dominio\EstadoOferta;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Avance;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Mensaje;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** @see LecturaNegociacionParaPanel */
final class LecturaNegociacionParaPanelEloquent implements LecturaNegociacionParaPanel
{
    public function __construct(private readonly LecturaProveedores $proveedores) {}

    public function deLaSolicitud(int $solicitudId): array
    {
        $ofertas = Oferta::query()->where('solicitud_id', $solicitudId)->orderBy('proveedor_id')->orderBy('numero')->get();

        $negociaciones = $ofertas->groupBy('proveedor_id')->map(function ($delProveedor, $proveedorId): array {
            return [
                'proveedor_id' => (int) $proveedorId,
                'proveedor' => $this->proveedores->proveedor((int) $proveedorId)->razonSocial ?? "#{$proveedorId}",
                'vigente' => $delProveedor->contains(fn (Oferta $oferta): bool => $oferta->estado === EstadoOferta::Vigente),
                'ofertas' => $delProveedor->map(fn (Oferta $oferta): array => [
                    'numero' => $oferta->numero,
                    'autor' => $oferta->autor,
                    'tipo' => $oferta->tipo,
                    'precio_ha' => (string) $oferta->precio_ha,
                    'monto_estimado' => (string) $oferta->monto_estimado,
                    'estado' => $oferta->estado->value,
                    'es_definitiva' => $oferta->es_definitiva,
                    'nota' => $oferta->nota,
                    'motivo' => $oferta->motivo,
                    'vence_en' => $oferta->vence_en,
                    'respondida_en' => $oferta->respondida_en,
                ])->values()->all(),
            ];
        })->values()->all();

        $ubicacion = DB::table('neg_ubicaciones')->where('solicitud_id', $solicitudId)->first();

        return [
            'negociaciones' => $negociaciones,
            'mensajes' => Mensaje::query()->where('solicitud_id', $solicitudId)->orderBy('id')->get()
                ->map(fn (Mensaje $mensaje): array => ['autor_rol' => $mensaje->autor_rol, 'texto' => $mensaje->texto, 'creado_en' => $mensaje->created_at])
                ->all(),
            'avances' => Avance::query()->where('solicitud_id', $solicitudId)->orderBy('registrado_en')->orderBy('id')->get()
                ->map(fn (Avance $avance): array => [
                    'tipo' => $avance->tipo,
                    'hectareas_ejecutadas' => $avance->hectareas_ejecutadas === null ? null : (string) $avance->hectareas_ejecutadas,
                    'porcentaje' => $avance->porcentaje === null ? null : (string) $avance->porcentaje,
                    'nota' => $avance->nota,
                    'registrado_en' => $avance->registrado_en,
                ])->all(),
            'fotos' => Evidencia::query()->where('solicitud_id', $solicitudId)->orderBy('id')->get()
                ->map(fn (Evidencia $foto): array => ['id' => $foto->id, 'tipo' => $foto->tipo, 'bytes' => $foto->bytes, 'subida_en' => $foto->created_at])
                ->all(),
            'ubicacion' => $ubicacion === null ? null : [
                'latitud' => (string) $ubicacion->latitud,
                'longitud' => (string) $ubicacion->longitud,
                'precision_m' => $ubicacion->precision_m === null ? null : (string) $ubicacion->precision_m,
                'capturado_en' => Carbon::parse($ubicacion->capturado_en),
            ],
        ];
    }
}
