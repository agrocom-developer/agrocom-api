<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Operaciones\Contratos\RegistroEvidencia;
use App\Dominios\Operaciones\Contratos\ResultadoSincronizacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Caso de uso de `POST /api/evidencias` (TE-07 parte servidor, tarea 19;
 * espec §2.1 punto 7: "evidencias en cola separada"). Guarda el archivo en el
 * disco `r2` y deja constancia del hecho — nunca comprime ni reintenta, eso
 * es de `agrocom-field`.
 *
 * Mismo vocabulario que `ResultadoSincronizacion` (`aplicado`/`duplicado`/
 * `rechazado`) aunque este endpoint vive fuera del motor de sync — por eso
 * no implementa `EscrituraSincronizacion` (esa interfaz es específicamente lo
 * que consume `Sincronizacion\Aplicacion\SincronizarLote` para el lote
 * `ORDEN_CAUSAL`; una evidencia no participa de ese orden).
 *
 * Idempotencia (invariante 1): el `INSERT` va SIEMPRE antes que el `Storage::
 * put()`, dentro de la misma transacción. Un reintento con el mismo
 * `uuid_cliente` choca contra el índice único parcial y lanza
 * `QueryException` ANTES de tocar el disco — así el archivo ya guardado
 * nunca se sobrescribe, sin necesidad de un `SELECT`/`Storage::exists()`
 * previo (el mismo mecanismo `ON CONFLICT` que el resto del motor de sync,
 * ver `EscrituraSincronizacionEloquent::resultadoDesdeExcepcion()`). Si el
 * `Storage::put()` falla, la excepción revierte también el `INSERT` — no
 * queda una fila sin archivo real detrás.
 *
 * Ruta de almacenamiento (ADR 0009): `evidencias/{tipo}/{yyyy}/{mm}/
 * {uuid_cliente}-{id}.{ext}`, con `{yyyy}/{mm}` tomados de `$datos->fecha`
 * (el momento del HECHO, no de cuándo llegó la red — coherente con
 * offline-first, la subida puede llegar días después). El `-{id}` no está
 * en el texto del ADR: se agrega porque el índice único de `uuid_cliente` es
 * parcial (`WHERE deleted_at IS NULL`, invariante 8) y permite reinsertar el
 * mismo `uuid_cliente` tras un soft delete — sin el `id`, ese reintento
 * pisaría en la misma clave S3 el archivo del registro anterior, que se
 * supone intacto para auditoría (ver runs/19.md, hallazgo 2 de la revisión
 * crítica). El `id` está disponible recién después del `INSERT`, así que la
 * ruta final se arma DESPUÉS de `create()`, antes del `put()`.
 */
final class RegistrarEvidencia
{
    public function ejecutar(RegistroEvidencia $datos, ?UploadedFile $archivo, ?int $subidoPor): ResultadoSincronizacion
    {
        if ($archivo === null || ! $archivo->isValid() || $archivo->getSize() === 0) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.errores.evidencia_archivo_ausente'));
        }

        try {
            $momento = Carbon::parse($datos->fecha);
        } catch (InvalidArgumentException) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.errores.evidencia_fecha_invalida'));
        }

        $hash = hash_file('sha256', $archivo->getRealPath());

        if ($datos->hashDispositivo !== null && ! hash_equals($datos->hashDispositivo, $hash)) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.errores.evidencia_hash_no_coincide'));
        }

        $extension = $archivo->extension() ?: 'bin';

        try {
            return DB::transaction(function () use ($datos, $archivo, $subidoPor, $hash, $momento, $extension): ResultadoSincronizacion {
                $evidencia = Evidencia::query()->create([
                    'uuid_cliente' => $datos->uuidCliente,
                    'tipo' => $datos->tipo,
                    'archivo_url' => '',
                    'hash' => $hash,
                    'subido_por' => $subidoPor,
                    'fecha' => $datos->fecha,
                ]);

                $ruta = sprintf(
                    'evidencias/%s/%s/%s/%s-%d.%s',
                    $datos->tipo->value,
                    $momento->format('Y'),
                    $momento->format('m'),
                    $datos->uuidCliente,
                    $evidencia->id,
                    $extension,
                );

                $guardado = Storage::disk('r2')->put($ruta, file_get_contents($archivo->getRealPath()));

                if (! $guardado) {
                    throw new RuntimeException(Texto::de('operaciones.errores.evidencia_guardado_disco_fallido', ['ruta' => $ruta]));
                }

                $evidencia->update(['archivo_url' => $ruta]);

                return ResultadoSincronizacion::aplicado();
            });
        } catch (QueryException $excepcion) {
            return $this->resultadoDesdeExcepcion($excepcion);
        } catch (RuntimeException) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.errores.evidencia_archivo_no_guardado'));
        }
    }

    /**
     * Mismo criterio que `EscrituraSincronizacionEloquent::resultadoDesdeExcepcion()`:
     * el formato del mensaje de la violación difiere por driver (Postgres
     * nombra el índice; SQLite, el motor de los tests, nombra tabla.columna).
     */
    private function resultadoDesdeExcepcion(QueryException $excepcion): ResultadoSincronizacion
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'ope_evidencias_uuid_cliente_unico') || str_contains($mensaje, 'ope_evidencias.uuid_cliente')) {
            return ResultadoSincronizacion::duplicado();
        }

        return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.registro_invalido'));
    }
}
