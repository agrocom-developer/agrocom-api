<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Requests;

use App\Dominios\Finanzas\Infraestructura\Http\Requests\Concerns\ReglasGasto;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /panel/gastos` (HU-33, tarea 47). La autorización (permiso
 * `finanzas.gasto.crear`) se verifica en el controlador, contra el rol
 * activo — no acá, mismo criterio que `CrearAnticipoRequest`.
 *
 * Reglas en `Concerns/ReglasGasto` (tarea 134): compartidas con
 * `ActualizarGastoRequest`, la edición no cambia ni un campo respecto del
 * alta.
 *
 * `cantidad`/`precio_unitario` > 0 replican los `CHECK` de
 * `database/migrations/2026_09_03_100003_create_fin_gastos_table` — así el
 * usuario ve un error de validación de Laravel, nunca el `QueryException`
 * crudo de Postgres.
 *
 * `comprobante` es OPCIONAL: la especificación completa sugiere que sea
 * obligatorio según el `medio_pago` del gasto (p. ej. efectivo sin
 * comprobante posible en algunos casos), pero esa columna queda fuera de
 * esta tarea (ver el prompt de la HU-33) — sin ese dato, no hay regla real
 * que sostenga cuándo exigirlo, así que se deja opcional. Tipo (imagen o
 * PDF) y tamaño máximo (10 MB, generoso para una foto de un comprobante de
 * papel) sí se validan siempre que el archivo venga: mismo criterio de
 * "sobre" que `SubirEvidenciaRequest`, pero acá SÍ es un 422 estándar de
 * Laravel — no hay vocabulario `rechazado` de sync que preservar, esta
 * subida no participa del motor de sync.
 *
 * `subrubro_id` valida solo que exista (no que pertenezca al `rubro_id`
 * elegido): el CA esencial es "categorías de catálogo", no una guarda de
 * consistencia rubro↔subrubro — el `<select>` de la vista ya filtra por
 * rubro en JS, así que un descalce solo puede venir de un POST manual.
 *
 * `campania_id` (ADR 0015 punto 6, tarea 69) es OPCIONAL — vacío es gasto
 * interno que no pertenece a ninguna campaña. Solo valida que exista entre
 * filas activas: que no esté `cerrada` es una guarda de negocio y vive en
 * `Aplicacion/CrearGasto`, mismo criterio que `campania_id` en contratos.
 *
 * `equipo_trabajo_id` (tarea 73, HU-50) es OPCIONAL, mismo criterio de
 * "solo valida que exista" que `base_id`/`trabajo_id`/`campania_id` — es el
 * camino PRINCIPAL de imputación (el formulario lo ofrece primero), sin
 * guarda de negocio adicional.
 */
final class CrearGastoRequest extends FormRequest
{
    use ReglasGasto;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->reglasGasto();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->mensajesGasto();
    }
}
