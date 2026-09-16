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
 * `ventanas` es opcional (HU-47, tarea 70, pedido del dueño del 7/9/2026):
 * cero ventanas significa "día completo", no un formulario incompleto — la
 * guarda de `MaquinaEstadosContrato::activar()` que antes exigía al menos
 * una para pasar a `vigente` se retiró. `hora_inicio`/`hora_fin` tampoco son
 * requeridas por sí solas: `required_with` mutuo exige que, si una fila trae
 * UNA hora, traiga las dos — evita una fila a medias que rompería el `NOT
 * NULL` de `com_contrato_ventanas` — y `hora_fin` usa además
 * `after:ventanas.*.hora_inicio` (Laravel resuelve el wildcard contra el
 * MISMO índice de fila) para replicar el `CHECK (hora_fin > hora_inicio)`
 * SOLO cuando la fila está completa: con `hora_inicio` ausente, la regla
 * `after` no tiene contra qué comparar y Laravel la da por cumplida (ver
 * `ValidatesAttributes::checkDateTimeOrder()`), así que la completitud de la
 * fila la sigue garantizando el `required_with`. El solapamiento entre
 * ventanas (que ningún `CHECK` puede expresar) se valida aparte, en
 * `Aplicacion/CrearContrato` vía `ValidadorSolapamientoVentanas`.
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
 * propiedad del cliente elegido ni que esa propiedad tenga superficie libre
 * en la campaña: ambas cruzan varias tablas y son guardas de negocio, viven
 * en `Aplicacion/CrearContrato` vía
 * `Aplicacion/Contrato/VerificadorLotesDelContrato` ({@see
 * \App\Dominios\Comercial\Dominio\Excepciones\LoteAjenoAlCliente}, {@see
 * \App\Dominios\Comercial\Dominio\Excepciones\LotesDePropiedadAgotados}).
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
            'ventanas' => ['nullable', 'array'],
            'ventanas.*.hora_inicio' => ['nullable', 'required_with:ventanas.*.hora_fin', 'date_format:H:i'],
            'ventanas.*.hora_fin' => ['nullable', 'required_with:ventanas.*.hora_inicio', 'date_format:H:i', 'after:ventanas.*.hora_inicio'],
            'lotes' => ['required', 'array', 'min:1'],
            'lotes.*' => ['integer', Rule::exists('com_lotes', 'id')->whereNull('deleted_at')],
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
            'ventanas.*.hora_inicio.required_with' => __('comercial.contratos.error_ventana_incompleta'),
            'ventanas.*.hora_fin.required_with' => __('comercial.contratos.error_ventana_incompleta'),
            'ventanas.*.hora_fin.after' => __('comercial.contratos.error_ventana_horas'),
            'lotes.required' => __('comercial.contratos.error_lotes_requeridos'),
            'lotes.*.exists' => __('comercial.contratos.error_lote_invalido'),
        ];
    }
}
