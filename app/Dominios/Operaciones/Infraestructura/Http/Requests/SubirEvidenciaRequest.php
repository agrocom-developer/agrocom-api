<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /api/evidencias` (TE-07 parte servidor, tarea 19). Mismo criterio que
 * `SincronizarLoteRequest`: valida solo el "sobre" (nada acá es obligatorio a
 * nivel HTTP), la forma real de cada campo la valida
 * `RegistroEvidencia::intentarDesdeArreglo()` y el archivo lo valida
 * `RegistrarEvidencia` — ambos devuelven `rechazado` en el cuerpo de la
 * respuesta en vez de un 422, mismo vocabulario que `ResultadoSync`.
 *
 * La única regla real es el tope de tamaño de `archivo`: no es el criterio de
 * aceptación 3 del prompt (ese es explícitamente responsabilidad del cliente,
 * que comprime a <300 KB antes de subir — TE-07 lado app, otro repo), sino
 * una guarda de plataforma para no aceptar un archivo absurdo — por eso SÍ es
 * un 422 estándar y no un `rechazado` de negocio. 20 MB es un límite
 * generoso frente al objetivo de <300 KB del cliente (decisión propia de esta
 * tarea, sin respaldo textual en la espec).
 */
final class SubirEvidenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorización real es el guard `auth:sanctum` de la ruta
        // (token por dispositivo, HU-03); acá solo se valida la forma.
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'uuid_cliente' => ['sometimes'],
            'tipo' => ['sometimes'],
            'fecha' => ['sometimes'],
            'archivo' => ['sometimes', 'max:20480'],
        ];
    }
}
