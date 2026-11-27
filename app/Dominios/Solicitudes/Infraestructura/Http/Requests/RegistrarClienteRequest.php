<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Requests;

use App\Dominios\Seguridad\Contratos\ReglasDeContacto;
use App\Dominios\Seguridad\Contratos\TipoCuenta;
use App\Dominios\Solicitudes\Dominio\TipoCliente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cuerpo de `POST /api/clientes/registro` (registro público, especificación §10.1). */
final class RegistrarClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return self::reglasDeRegistro();
    }

    /**
     * Las reglas del registro, que el alta manual del panel reutiliza tal cual.
     *
     * @return array<string, mixed>
     */
    public static function reglasDeRegistro(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoCliente::class)],
            'nombre' => ['required', 'string', 'max:150'],
            'username' => [
                'required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('sec_user', 'username')->whereNull('deleted_at'),
            ],
            'email' => ['nullable', 'email:rfc', 'max:150', ReglasDeContacto::correoUnico(TipoCuenta::Cliente)],
            'password' => ReglasDeContacto::reglasDeContrasena(),
            // E.164 con Bolivia por defecto (ADR 0046): único entre las cuentas de cliente.
            'telefono' => ['required', 'string', 'max:30', ReglasDeContacto::reglaTelefono()],
            // La persona de la cuenta (ADR 0046): una persona real tiene a lo sumo una cuenta de cliente. TRANSITORIO:
            // opcional hasta que la app del cliente la envíe (fase F4 de field); sin documento no hay persona y la
            // cuenta queda sin ella. Con documento, el nombre y los apellidos son obligatorios.
            'documento_tipo' => ['nullable', 'string', Rule::in(ReglasDeContacto::tiposDeDocumento())],
            'documento_numero' => ['nullable', 'string', 'max:40'],
            'nombres' => ['nullable', 'required_with:documento_numero', 'string', 'max:80'],
            'apellidos' => ['nullable', 'required_with:documento_numero', 'string', 'max:80'],
            'fecha_nacimiento' => ['nullable', 'date_format:Y-m-d'],
            // Una empresa se identifica por su razón social y su NIT; una persona, por su nombre.
            'razon_social' => ['required_if:tipo,empresa', 'nullable', 'string', 'max:150'],
            'documento_fiscal' => ['required_if:tipo,empresa', 'nullable', 'string', 'max:30'],
            // Contactos de referencia: si una empresa no los envía, el contacto es quien registra la cuenta.
            'contactos' => ['nullable', 'array', 'max:10'],
            'contactos.*.nombre' => ['required', 'string', 'max:150'],
            'contactos.*.telefono' => ['required', 'string', 'max:30', 'regex:/^[0-9 +().-]{6,30}$/'],
            'contactos.*.cargo' => ['nullable', 'string', 'max:80'],
            'contactos.*.es_principal' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('solicitudes.campos');
    }
}
