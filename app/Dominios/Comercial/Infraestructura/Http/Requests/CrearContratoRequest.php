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
 * `lotes.*.hora_inicio`/`lotes.*.hora_fin` (reemplazo del 16/9/2026 de las
 * ventanas de contrato: el rango horario para fumigar pasó de ser un dato
 * del contrato completo a ser un dato de CADA LOTE — ver el docblock de
 * `ContratoLote`, `com_contrato_ventanas` ya no existe): ambas son
 * opcionales, "las dos NULL" significa "ese lote, día completo" (mismo
 * criterio de "cero ventana = día completo" que regía a nivel contrato,
 * HU-47, trasladado a nivel de lote). Ninguna es requerida por sí sola:
 * `required_with` mutuo exige que, si el lote trae UNA hora, traiga las dos
 * — evita una fila a medias que rompería el `NOT NULL AND NOT NULL` del
 * `CHECK` de `com_contrato_lotes` — y `hora_fin` usa además
 * `after:lotes.*.hora_inicio` (Laravel resuelve el wildcard contra el MISMO
 * índice de fila) para replicar `CHECK (hora_fin > hora_inicio)` SOLO cuando
 * el lote está completo: con `hora_inicio` ausente, `after` no tiene contra
 * qué comparar y Laravel la da por cumplida (ver
 * `ValidatesAttributes::checkDateTimeOrder()`), así que la completitud del
 * lote la sigue garantizando el `required_with`. A diferencia de las
 * ventanas viejas (N filas por contrato, podían solaparse entre sí), acá
 * cada lote tiene A LO SUMO un rango horario propio: no hay nada que
 * solapar dentro de la misma fila, así que no hace falta ningún validador
 * de solapamiento del lado de `Aplicacion/CrearContrato`.
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
            'lotes.*.hora_inicio' => ['nullable', 'date_format:H:i', 'required_with:lotes.*.hora_fin'],
            'lotes.*.hora_fin' => ['nullable', 'date_format:H:i', 'required_with:lotes.*.hora_inicio', 'after:lotes.*.hora_inicio'],
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
            'lotes.*.hora_inicio.required_with' => __('comercial.contratos.error_lote_horario_incompleto'),
            'lotes.*.hora_fin.required_with' => __('comercial.contratos.error_lote_horario_incompleto'),
            'lotes.*.hora_fin.after' => __('comercial.contratos.error_lote_horario_invalido'),
        ];
    }
}
