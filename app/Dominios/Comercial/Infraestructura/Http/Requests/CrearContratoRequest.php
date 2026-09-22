<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/contratos` (HU-23, tarea 34). La autorización (permiso
 * `comercial.contrato.crear`) se verifica en el controlador, contra el rol
 * activo — no acá, mismo criterio que `CrearClienteRequest`.
 *
 * Los rangos replican, uno a uno, los `CHECK` de
 * `database/migrations/2026_08_26_100003_create_com_contratos_table.php`
 * que corresponden a un campo del formulario (los otros dos —`estado` y
 * `monto_total`— no son input: los fija el servicio de dominio, ver
 * `Aplicacion/CrearContrato`) — así el usuario ve un error de validación de
 * Laravel, nunca el `QueryException` crudo de Postgres.
 *
 * Sin parámetros de vuelo ni `adelanto_pct` (HU-91, tarea 106): esos 7
 * límites de condiciones heredan siempre de la Orden o del valor por defecto
 * del sistema (RF-60), nunca del contrato.
 *
 * `campania_id` (ADR 0015 punto 1, corregido el 8/9/2026) solo valida que
 * exista entre filas activas: que pertenezca al MISMO cliente del contrato
 * es una guarda de negocio cruzando dos tablas, y vive en
 * `Aplicacion/CrearContrato` (invariante 5, mismo criterio que "el portal
 * consulta desde el contrato del usuario" aplicado acá al panel interno).
 *
 * `lotes` (pedido del dueño, tarea "contratos-lotes", 16/9/2026): al menos un
 * lote concreto, cada uno existente entre `com_lotes` activos — igual que
 * `campania_id`, solo se valida ACÁ que el ID exista, nunca que sea de una
 * propiedad del cliente elegido ni que el lote esté libre en la campaña
 * (que no lo retenga otro contrato vigente o pausado, ADR 0021): ambas cruzan
 * varias tablas y son guardas de negocio, viven en `Aplicacion/CrearContrato`
 * vía `Aplicacion/Contrato/VerificadorLotesDelContrato` (excepciones
 * `LoteAjenoAlCliente` y `LotesYaContratados`).
 *
 * De cada lote solo se valida `lote_id`: el día completo y el horario de
 * cada lote salieron del contrato el 21/9/2026 y se cargan en la orden de
 * trabajo (antes, `lotes.*.hora_inicio`/`lotes.*.hora_fin`).
 */
final class CrearContratoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'integer', Rule::exists('com_clientes', 'id')->whereNull('deleted_at')],
            'campania_id' => ['required', 'integer', Rule::exists('cpn_campanias', 'id')->whereNull('deleted_at')],
            'hectareas_contratadas' => ['required', 'numeric', 'gt:0'],
            'aplicaciones_previstas' => ['required', 'integer', 'min:1'],
            'precio_ha' => ['required', 'numeric', 'min:0'],
            'adelanto_monto' => ['nullable', 'numeric', 'min:0'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'brinda_alimentacion' => ['boolean'],
            'brinda_hospedaje' => ['boolean'],
            'brinda_combustible' => ['boolean'],
            'observaciones_logistica' => ['nullable', 'string'],
            'lotes' => ['required', 'array', 'min:1'],
            'lotes.*.lote_id' => ['required', 'integer', Rule::exists('com_lotes', 'id')->whereNull('deleted_at')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cliente_id.required' => __('comercial.contratos.error_cliente_requerido'),
            'cliente_id.exists' => __('comercial.contratos.error_cliente_invalido'),
            'campania_id.required' => __('comercial.contratos.error_campania_requerida'),
            'campania_id.exists' => __('comercial.contratos.error_campania_invalida'),
            'hectareas_contratadas.required' => __('comercial.contratos.error_hectareas_contratadas_requeridas'),
            'aplicaciones_previstas.required' => __('comercial.contratos.error_aplicaciones_previstas_requeridas'),
            'precio_ha.required' => __('comercial.contratos.error_precio_ha_requerido'),
            'fecha_inicio.required' => __('comercial.contratos.error_fecha_inicio_requerida'),
            'lotes.required' => __('comercial.contratos.error_lotes_requeridos'),
            'lotes.*.lote_id.required' => __('comercial.contratos.error_lote_invalido'),
            'lotes.*.lote_id.exists' => __('comercial.contratos.error_lote_invalido'),
        ];
    }
}
