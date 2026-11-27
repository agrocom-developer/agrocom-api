<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidadorContrato;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/configuracion/{grupo}` (tarea 78, HU-55). `valores`/`borrar`
 * llegan como array `clave => ...` (el HTML usa `name="valores[clave]"`,
 * nunca `name="{{ $clave }}"` a secas: un nombre de campo con puntos
 * literales, `mapas.google_maps_api_key`, PHP lo reescribe con guiones bajos
 * antes de que llegue a `$_POST` — el array evita ese problema).
 *
 * Las claves del catálogo traen puntos, así que las reglas por clave no pueden
 * ir en `rules()` (Laravel leería los puntos como niveles): se validan aparte,
 * con los nombres planos, en {@see after()}. Hoy solo el grupo `correo` las tiene.
 */
final class ActualizarConfiguracionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'valores' => ['present', 'array'],
            'valores.*' => ['nullable', 'string', 'max:1000'],
            'borrar' => ['array'],
            'borrar.*' => ['boolean'],
        ];
    }

    /** @return list<callable(ValidadorContrato): void> */
    public function after(): array
    {
        return [function (ValidadorContrato $validador): void {
            if ($this->route('grupo') !== 'correo' || $validador->errors()->isNotEmpty()) {
                return;
            }

            /** @var array<string, ?string> $valores */
            $valores = (array) $this->input('valores', []);
            $claves = ['proveedor', 'host', 'puerto', 'cifrado', 'usuario', 'remitente', 'remitente_nombre'];
            $plano = [];

            foreach ($claves as $clave) {
                if (array_key_exists("correo.{$clave}", $valores)) {
                    $plano[$clave] = $valores["correo.{$clave}"];
                }
            }

            $propio = Validator::make($plano, [
                'proveedor' => ['sometimes', 'required', Rule::in((array) config('configuracion.claves')['correo.proveedor']['opciones'])],
                'host' => ['sometimes', 'required', 'string', 'max:253', 'regex:/^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$/'],
                'puerto' => ['sometimes', 'required', 'integer', 'between:1,65535'],
                'cifrado' => ['sometimes', 'required', Rule::in((array) config('configuracion.claves')['correo.cifrado']['opciones'])],
                'usuario' => ['sometimes', 'nullable', 'string', 'max:255'],
                'remitente' => ['sometimes', 'required', 'email', 'max:255'],
                'remitente_nombre' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[^\r\n<>]*$/'],
            ], [], (array) trans('configuracion.campos_correo'));

            $propio->fails();

            foreach ($propio->errors()->messages() as $campo => $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $validador->errors()->add("valores.correo.{$campo}", $mensaje);
                }
            }
        }];
    }
}
