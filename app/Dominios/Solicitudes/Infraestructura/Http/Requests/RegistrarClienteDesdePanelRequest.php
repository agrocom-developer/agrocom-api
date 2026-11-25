<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Requests;

use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cuerpo de `POST /panel/clientes`: la oficina registra a mano a un cliente
 * (persona o empresa). Las reglas de la cuenta y del cliente son las del
 * registro público ({@see RegistrarClienteRequest::reglasDeRegistro()}); acá
 * solo se suma lo propio del formulario: una empresa exige al menos un
 * contacto de referencia, y el formulario manda tres filas de contacto y cuál
 * es el principal, no una lista.
 */
final class RegistrarClienteDesdePanelRequest extends FormRequest
{
    public const PERMISO = 'solicitudes.cliente.crear';

    /** Filas de contacto que ofrece el formulario. */
    public const FILAS_DE_CONTACTO = 3;

    public function authorize(): bool
    {
        return app(AutorizacionPanelWeb::class)->tienePermiso($this, self::PERMISO);
    }

    /** Descarta las filas de contacto vacías (sin renumerar las demás) y marca como principal la elegida. */
    protected function prepareForValidation(): void
    {
        $filas = (array) $this->input('contactos', []);
        $principal = $this->input('contacto_principal');
        $contactos = [];

        foreach ($filas as $indice => $fila) {
            $fila = is_array($fila) ? $fila : [];
            $nombre = trim((string) ($fila['nombre'] ?? ''));
            $telefono = trim((string) ($fila['telefono'] ?? ''));
            $cargo = trim((string) ($fila['cargo'] ?? ''));

            if ($nombre === '' && $telefono === '' && $cargo === '') {
                continue;
            }

            // Conserva el índice de la fila: un error se pinta junto a la fila que lo causó.
            $contactos[$indice] = [
                'nombre' => $nombre,
                'telefono' => $telefono,
                'cargo' => $cargo === '' ? null : $cargo,
                'es_principal' => (string) $indice === (string) $principal,
            ];
        }

        $this->merge(['contactos' => $contactos]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...RegistrarClienteRequest::reglasDeRegistro(),
            'contactos' => ['required_if:tipo,empresa', 'nullable', 'array', 'max:'.self::FILAS_DE_CONTACTO],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('solicitudes.campos');
    }
}
