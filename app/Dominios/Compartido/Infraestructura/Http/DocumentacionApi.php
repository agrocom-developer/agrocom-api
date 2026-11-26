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
        new OA\Property(property: 'first', description: 'URL de la primera página.', type: 'string', example: 'http://localhost:8000/api/solicitudes?page=1'),
        new OA\Property(property: 'last', description: 'URL de la última página.', type: 'string', example: 'http://localhost:8000/api/solicitudes?page=3'),
        new OA\Property(property: 'prev', description: 'URL de la página anterior; null en la primera.', type: 'string', example: null, nullable: true),
        new OA\Property(property: 'next', description: 'URL de la página siguiente; null en la última.', type: 'string', example: 'http://localhost:8000/api/solicitudes?page=2', nullable: true),
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
                    new OA\Property(property: 'url', type: 'string', example: 'http://localhost:8000/api/solicitudes?page=1', nullable: true),
                    new OA\Property(property: 'label', type: 'string', example: '1'),
                    new OA\Property(property: 'page', type: 'integer', example: 1, nullable: true),
                    new OA\Property(property: 'active', type: 'boolean', example: true),
                ],
                type: 'object',
            ),
        ),
        new OA\Property(property: 'path', description: 'URL base del listado, sin query string.', type: 'string', example: 'http://localhost:8000/api/solicitudes', nullable: true),
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
#[OA\Schema(
    schema: 'CuentaNoAprobada',
    title: 'Cuenta no aprobada',
    description: 'Respuesta 403 de la compuerta de la cuenta (ADR 0046, punto 12): la cuenta existe pero su estado no permite publicar, aceptar o contraofertar (cliente), ni ofertar, contraofertar o aceptar (proveedor). La app lo muestra como la pantalla de verificación, no como un error genérico. Distinto de `proveedor_no_habilitado` (falta un equipo apto). La app no replica la regla: la decide el servidor.',
    required: ['message', 'codigo', 'estado', 'falta'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Tu cuenta está en revisión. Cuando la aprobemos podrás publicar y negociar; mientras tanto puedes revisar tus datos.'),
        new OA\Property(property: 'codigo', type: 'string', enum: ['cuenta_no_aprobada'], example: 'cuenta_no_aprobada'),
        new OA\Property(property: 'estado', type: 'string', enum: ['pendiente', 'rechazado', 'suspendido', 'baja'], example: 'pendiente'),
        new OA\Property(property: 'falta', description: 'Qué impide aprobar la cuenta (`verificacion`, `documentos`, `dron_fotos`, `equipo`…). Mientras no existan esos pasos viene vacío.', type: 'array', items: new OA\Items(type: 'string'), example: []),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CuentaYaRegistrada',
    title: 'Cuenta ya registrada',
    description: 'Respuesta 409 de un registro (ADR 0046): el teléfono o la persona (documento) ya tiene una cuenta de ESE tipo. No dice cuál de los dos datos chocó, ni datos de nadie, ni si la cuenta está completa: la persona inicia sesión o recupera su acceso. Cada 409 cuenta contra un tope por hora por IP, teléfono y documento (después, 429).',
    required: ['message', 'codigo', 'tipo_cuenta'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Ya tienes una cuenta de cliente. Inicia sesión o recupera tu acceso.'),
        new OA\Property(property: 'codigo', type: 'string', enum: ['cuenta_ya_registrada'], example: 'cuenta_ya_registrada'),
        new OA\Property(property: 'tipo_cuenta', description: 'Tipo de la cuenta que ya existe.', type: 'string', enum: ['cliente', 'proveedor'], example: 'cliente'),
    ],
    type: 'object',
)]
final class DocumentacionApi
{
    // Intencionalmente vacía: existe solo como ancla de los atributos OpenAPI.
}
