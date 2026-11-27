<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Controllers\Api;

use App\Dominios\Compartido\Infraestructura\Http\RechazoDeDominio;
use App\Dominios\Proveedores\Aplicacion\RegistrarProveedor;
use App\Dominios\Proveedores\Dominio\Excepciones\RegistroDuplicado;
use App\Dominios\Proveedores\Infraestructura\Http\Requests\RegistrarProveedorRequest;
use App\Dominios\Proveedores\Infraestructura\Http\Resources\ProveedorResource;
use App\Dominios\Seguridad\Contratos\CuentaDuplicada;
use App\Dominios\Seguridad\Contratos\CuentaYaRegistrada;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Registro público de proveedores (ADR 0032, punto 1; especificación §10.2).
 * Adaptador delgado (ADR 0008): la regla vive en {@see RegistrarProveedor}.
 */
#[OA\Tag(
    name: 'Proveedores',
    description: 'Registro, personal, documentos y reenvío a revisión de proveedores del marketplace (ADR 0028, 0032).',
)]
final class RegistroProveedorController
{
    public function __construct(private readonly RegistrarProveedor $registrar) {}

    #[OA\Post(
        path: '/api/proveedores/registro',
        operationId: 'registrarProveedor',
        description: 'Alta pública de un proveedor independiente o tercero. Crea la cuenta (rol `proveedor`, más `piloto` si es '
            .'independiente) y deja al proveedor `pendiente`: nadie opera sin aprobación de un administrador. El '
            .'independiente es su propio piloto y envía su licencia. No devuelve token: la persona ingresa luego por '
            .'`POST /api/auth/token`. Limita los intentos por IP: superado el límite responde 429.',
        summary: 'Registro público de un proveedor',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['tipo', 'nombre', 'username', 'password', 'telefono', 'razon_social', 'documento_fiscal', 'nombres', 'apellidos', 'documento_identidad', 'fecha_nacimiento'],
                properties: [
                    new OA\Property(property: 'tipo', type: 'string', enum: ['independiente', 'tercero'], example: 'independiente'),
                    new OA\Property(property: 'nombre', description: 'Nombre del titular.', type: 'string', example: 'Carlos Mamani Quispe'),
                    new OA\Property(property: 'username', description: 'Login (nunca un correo): letras, números, punto, guion.', type: 'string', example: 'carlos.mamani'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'password', description: 'Mínimo 4 caracteres, sin otras reglas.', type: 'string', format: 'password'),
                    new OA\Property(property: 'telefono', description: 'Teléfono de la cuenta: un celular de Bolivia de 8 dígitos o un número con su código de país. Se guarda en E.164 y es único entre las cuentas de proveedor.', type: 'string', example: '+591 70012345'),
                    new OA\Property(property: 'razon_social', type: 'string', example: 'Mamani Agroservicios'),
                    new OA\Property(property: 'nombres', description: 'Datos de la persona (piloto o titular); el administrador los revisa al aprobar.', type: 'string', example: 'Carlos'),
                    new OA\Property(property: 'apellidos', type: 'string', example: 'Mamani Quispe'),
                    new OA\Property(property: 'documento_tipo', description: 'Tipo del documento de la persona (por defecto `ci`).', type: 'string', enum: ['ci', 'pasaporte', 'licencia_conducir'], example: 'ci', nullable: true),
                    new OA\Property(property: 'documento_numero', description: 'Número del documento. Es el nombre nuevo de `documento_identidad`: si llegan los dos, manda este. La persona es única por tipo y número normalizado (mayúsculas, sin espacios ni puntos).', type: 'string', example: '7845123 SC', nullable: true),
                    new OA\Property(property: 'documento_identidad', description: 'Número del documento (nombre histórico de `documento_numero`; obligatorio si no se envía `documento_numero`).', type: 'string', example: '7845123 SC', nullable: true),
                    new OA\Property(property: 'fecha_nacimiento', type: 'string', format: 'date', example: '1988-04-12'),
                    new OA\Property(property: 'documento_fiscal', description: 'NIT o documento de identidad.', type: 'string', example: '4521897012'),
                    new OA\Property(property: 'licencia_numero', description: 'Obligatoria si el tipo es `independiente`.', type: 'string', example: 'RPA-2291', nullable: true),
                    new OA\Property(property: 'licencia_vence', description: 'Obligatoria si el tipo es `independiente`; debe ser futura.', type: 'string', format: 'date', example: '2027-06-30', nullable: true),
                    new OA\Property(property: 'categoria_habilitacion', description: 'Obligatoria si el tipo es `independiente`.', type: 'string', example: 'RPAS agrícola', nullable: true),
                ],
                type: 'object',
            ),
        ),
        tags: ['Proveedores'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Proveedor creado en estado `pendiente`.',
                content: new OA\JsonContent(
                    required: ['proveedor'],
                    properties: [new OA\Property(property: 'proveedor', ref: '#/components/schemas/Proveedor')],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 409, description: 'Ese teléfono o esa persona (documento) ya tiene una cuenta de proveedor: `codigo: cuenta_ya_registrada`. No crea nada ni dice cuál de los dos datos chocó; la persona inicia sesión o recupera su acceso.', content: new OA\JsonContent(ref: '#/components/schemas/CuentaYaRegistrada')),
            new OA\Response(response: 422, description: 'Datos inválidos o login, correo o documento ya registrados.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
            new OA\Response(response: 429, description: 'Demasiados registros desde la misma IP, o demasiados `409` seguidos para esa IP, ese teléfono o ese documento; reintentar tras `Retry-After` segundos.', headers: [new OA\Header(header: 'Retry-After', description: 'Segundos para reintentar, según la ventana real del límite (cupo por hora o día, intervalo entre códigos, bloqueo del login o frecuencia por IP).', schema: new OA\Schema(type: 'integer', minimum: 1, example: 30))]),
        ],
    )]
    public function store(RegistrarProveedorRequest $request): JsonResponse
    {
        try {
            $proveedor = $this->registrar->ejecutar($request->validated());
        } catch (CuentaYaRegistrada $excepcion) {
            return response()->json($excepcion->cuerpo(), 409);
        } catch (CuentaDuplicada|RegistroDuplicado $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['proveedor' => new ProveedorResource($proveedor)], 201);
    }
}
