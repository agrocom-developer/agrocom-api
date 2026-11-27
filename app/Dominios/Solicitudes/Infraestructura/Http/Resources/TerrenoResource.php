<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Resources;

use App\Dominios\Solicitudes\Aplicacion\GestionarFotosDeTerreno;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Terreno;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\TerrenoFoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/** @mixin Terreno */
#[OA\Schema(
    schema: 'Terreno',
    title: 'Terreno',
    description: 'Terreno de un cliente sobre el que pide servicios (especificación §4.1). Hectáreas como decimal exacto en string.',
    required: ['id', 'nombre', 'hectareas', 'acceso_dificil'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 12),
        new OA\Property(property: 'nombre', type: 'string', example: 'Lote Norte'),
        new OA\Property(property: 'hectareas', type: 'string', example: '120.5000'),
        new OA\Property(property: 'cultivo', type: 'string', nullable: true, example: 'Soya'),
        new OA\Property(property: 'geometria', description: 'Polígono GeoJSON del terreno.', type: 'object', nullable: true),
        new OA\Property(property: 'referencias_acceso', type: 'string', nullable: true),
        new OA\Property(property: 'colindantes_sensibles', description: 'Apiarios, cultivos orgánicos, viviendas.', type: 'string', nullable: true),
        new OA\Property(property: 'acceso_dificil', type: 'boolean', example: false),
        new OA\Property(property: 'departamento_codigo', description: 'Código INE del departamento (`GET /api/catalogos/ubicaciones`). Obligatorio para publicar una solicitud sobre el terreno.', type: 'string', nullable: true, example: '07'),
        new OA\Property(property: 'provincia_codigo', description: 'Código INE de la provincia, del mismo departamento. Obligatorio para publicar una solicitud sobre el terreno.', type: 'string', nullable: true, example: '0702'),
        new OA\Property(property: 'municipio', description: 'Texto libre de referencia.', type: 'string', nullable: true, example: 'Warnes'),
        new OA\Property(property: 'comunidad', description: 'Texto libre de referencia.', type: 'string', nullable: true, example: 'Los Álamos'),
        new OA\Property(property: 'fotos', description: 'Fotos del estado del terreno (hasta 10, `medios.limites.por_entidad.terreno_fotos`). Las URLs son firmadas y de corta vida: se piden de nuevo con el terreno.', type: 'array', items: new OA\Items(ref: '#/components/schemas/FotoDeTerreno')),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'FotoDeTerreno',
    title: 'Foto del estado de un terreno',
    required: ['id', 'url'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 5),
        new OA\Property(property: 'descripcion', type: 'string', nullable: true, example: 'Borde norte con maleza alta'),
        new OA\Property(property: 'url', description: 'El original: URL firmada y de corta vida (relativa a la base de la API); se pide sin token.', type: 'string', example: '/api/terrenos/fotos/5/archivo?expires=1764000000&signature=ab12'),
        new OA\Property(property: 'url_miniatura', description: 'Derivado chico, también firmado; úsalo por defecto en listas y cuadrículas y pide `url` solo en el visor.', type: 'string', example: '/api/terrenos/fotos/5/archivo?variante=miniatura&expires=1764000000&signature=cd34'),
        new OA\Property(property: 'url_media', description: 'Derivado medio, también firmado, para la vista previa.', type: 'string', example: '/api/terrenos/fotos/5/archivo?variante=media&expires=1764000000&signature=ef56'),
    ],
    type: 'object',
)]
final class TerrenoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'hectareas' => $this->hectareas,
            'cultivo' => $this->cultivo,
            'geometria' => $this->geometria,
            'referencias_acceso' => $this->referencias_acceso,
            'colindantes_sensibles' => $this->colindantes_sensibles,
            'acceso_dificil' => $this->acceso_dificil,
            'departamento_codigo' => $this->departamento_codigo,
            'provincia_codigo' => $this->provincia_codigo,
            'municipio' => $this->municipio,
            'comunidad' => $this->comunidad,
            'fotos' => $this->whenLoaded('fotos', fn () => $this->fotos->map(fn (TerrenoFoto $foto): array => [
                'id' => $foto->id,
                'descripcion' => $foto->descripcion,
                ...GestionarFotosDeTerreno::urls($foto->id),
            ])->all()),
        ];
    }
}
