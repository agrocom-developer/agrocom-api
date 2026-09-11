<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecDatosEmpresa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Persiste los datos de presentación de la empresa (nombre, rubro, contacto)
 * — siempre la única fila viva de `sec_datos_empresa`, nunca una segunda: no
 * hay "alta"/"edición" separadas porque hay una sola empresa. Mismo criterio
 * que `GuardarDatosFiscales`.
 *
 * Logo (ADR 0019, excepción puntual a ADR 0009 categoría 3 — "assets de
 * marca van en el repo"): a diferencia del resto de los campos, SÍ es un
 * archivo subido por el usuario, guardado en el disco `public` (no `r2`,
 * reservado a evidencias/reportes privados con URL firmada) bajo
 * `logos/empresa/`, con nombre generado por el servidor — nunca el nombre
 * original del upload. `$eliminarLogo` solo actúa si no vino `$logo` nuevo
 * (subir y tildar "eliminar" a la vez no tiene sentido; el nuevo archivo
 * gana). El archivo viejo se borra del disco al reemplazar o eliminar —
 * distinto de una evidencia (ADR 0007/0009: nunca se sobrescribe ni se
 * borra), porque esto es un asset de presentación editable, no un registro
 * de auditoría de campo.
 */
final class GuardarDatosEmpresa
{
    /**
     * @param  array{nombre: string, rubro: string, email: ?string, telefono: ?string, direccion: ?string}  $datos
     */
    public function ejecutar(array $datos, ?UploadedFile $logo = null, bool $eliminarLogo = false): SecDatosEmpresa
    {
        $empresa = SecDatosEmpresa::query()->first() ?? new SecDatosEmpresa;

        $empresa->fill($datos);

        if ($logo !== null) {
            $this->reemplazarLogo($empresa, $logo);
        } elseif ($eliminarLogo && $empresa->logo_path !== null) {
            $this->borrarLogo($empresa);
        }

        $empresa->save();

        return $empresa;
    }

    private function reemplazarLogo(SecDatosEmpresa $empresa, UploadedFile $logo): void
    {
        if ($empresa->logo_path !== null) {
            Storage::disk('public')->delete($empresa->logo_path);
        }

        $extension = $logo->extension() ?: 'bin';
        $ruta = sprintf('logos/empresa/logo-%d.%s', now()->timestamp, $extension);

        Storage::disk('public')->put($ruta, (string) file_get_contents($logo->getRealPath()));

        $empresa->logo_path = $ruta;
    }

    private function borrarLogo(SecDatosEmpresa $empresa): void
    {
        Storage::disk('public')->delete((string) $empresa->logo_path);

        $empresa->logo_path = null;
    }
}
