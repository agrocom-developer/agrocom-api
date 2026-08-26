<?php

namespace App\Dominios\Compartido\Infraestructura\Http;

use OpenApi\Attributes as OA;

/**
 * Raíz del documento OpenAPI (ADR 0014). Acá vive solo lo transversal a toda
 * la API: la info general, el servidor y los esquemas de plataforma
 * (paginación de Laravel, error de validación 422). Cada módulo documenta sus
 * endpoints y esquemas con atributos OA\... junto a su propio código en
 * `app/Dominios/<Módulo>/Infraestructura/Http/` — esta clase no acumula
 * documentación de módulos.
 *
 * El spec generado desde estos atributos es el contrato que consume
 * `agrocom-field`; la copia versionada vive en `docs/api/openapi.yaml`
 * (`composer openapi` la regenera).
 */
#[OA\Info(
    version: '0.1.0',
    description: 'API del Sistema de Gestión de Operaciones de Fumigación de Agrocom SRL. '
        .'Sirve a las apps de campo (`agrocom-field`) y al portal del cliente; el spec generado '
        .'desde el código es el contrato de la API (ADR 0014). Dinero y hectáreas viajan como '
        .'string decimal, nunca como número de punto flotante.',
    title: 'Agrocom API',
)]
#[OA\Server(
    url: '/',
    description: 'Servidor actual — la URL base la define el entorno (local, staging, producción).',
)]
#[OA\Schema(
    schema: 'PaginacionLinks',
    title: 'Links de paginación',
    description: 'Links de navegación de la respuesta paginada estándar de Laravel.',
    required: ['first', 'last', 'prev', 'next'],
    properties: [
        new OA\Property(property: 'first', description: 'URL de la primera página.', type: 'string', example: 'http://localhost:8000/api/ordenes?page=1'),
        new OA\Property(property: 'last', description: 'URL de la última página.', type: 'string', example: 'http://localhost:8000/api/ordenes?page=3'),
        new OA\Property(property: 'prev', description: 'URL de la página anterior; null en la primera.', type: 'string', example: null, nullable: true),
        new OA\Property(property: 'next', description: 'URL de la página siguiente; null en la última.', type: 'string', example: 'http://localhost:8000/api/ordenes?page=2', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'PaginacionMeta',
    title: 'Metadatos de paginación',
    description: 'Metadatos de la respuesta paginada estándar de Laravel.',
    required: ['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total'],
    properties: [
        new OA\Property(property: 'current_page', description: 'Página actual.', type: 'integer', example: 1),
        new OA\Property(property: 'from', description: 'Posición del primer registro de la página; null si está vacía.', type: 'integer', example: 1, nullable: true),
        new OA\Property(property: 'last_page', description: 'Última página disponible.', type: 'integer', example: 3),
        new OA\Property(
            property: 'links',
            description: 'Elementos del paginador para renderizar la navegación.',
            type: 'array',
            items: new OA\Items(
                required: ['url', 'label', 'page', 'active'],
                properties: [
                    new OA\Property(property: 'url', type: 'string', example: 'http://localhost:8000/api/ordenes?page=1', nullable: true),
                    new OA\Property(property: 'label', type: 'string', example: '1'),
                    new OA\Property(property: 'page', type: 'integer', example: 1, nullable: true),
                    new OA\Property(property: 'active', type: 'boolean', example: true),
                ],
                type: 'object',
            ),
        ),
        new OA\Property(property: 'path', description: 'URL base del listado, sin query string.', type: 'string', example: 'http://localhost:8000/api/ordenes', nullable: true),
        new OA\Property(property: 'per_page', description: 'Registros por página.', type: 'integer', example: 15),
        new OA\Property(property: 'to', description: 'Posición del último registro de la página; null si está vacía.', type: 'integer', example: 15, nullable: true),
        new OA\Property(property: 'total', description: 'Total de registros que matchean los filtros.', type: 'integer', example: 42),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ErrorValidacion',
    title: 'Error de validación',
    description: 'Respuesta 422 estándar de Laravel: un mensaje general y los mensajes por campo.',
    required: ['message', 'errors'],
    properties: [
        new OA\Property(property: 'message', description: 'Mensaje general del primer error.', type: 'string', example: 'El valor seleccionado para estado no es válido.'),
        new OA\Property(
            property: 'errors',
            description: 'Mensajes de validación agrupados por campo.',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string'),
            ),
        ),
    ],
    type: 'object',
)]
final class DocumentacionApi
{
    // Intencionalmente vacía: existe solo como ancla de los atributos OpenAPI.
}
