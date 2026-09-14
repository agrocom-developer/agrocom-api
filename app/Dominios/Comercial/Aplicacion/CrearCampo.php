<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Aplicacion\Lote\GuardadoLote;
use App\Dominios\Comercial\Dominio\Excepciones\CampaniaDeOtroCliente;
use App\Dominios\Comercial\Dominio\Excepciones\CampoDuplicado;
use App\Dominios\Comercial\Dominio\Excepciones\LoteDuplicado;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Alta de un campo con sus lotes en una sola operación (HU-24, tarea 35):
 * mismo criterio que `CrearCliente` con sus contactos — el formulario es uno
 * solo, así que el campo y sus lotes nacen en la misma transacción.
 *
 * `cultivoId`/`campaniaId` (HU-72, tarea 88): el generador de alta masiva del
 * formulario solo rellena el mismo array `$lotes` — no hay endpoint paralelo.
 * Lo que sí es nuevo es la siembra: si el formulario trae un cultivo por
 * defecto, cada lote recién creado se siembra en la campaña elegida con ese
 * cultivo, reusando {@see GuardarSiembraCampania} (tarea 71) en vez de
 * reimplementar el alta de siembra — misma transacción, así que una campaña
 * de otro cliente revierte también el campo y sus lotes.
 */
final class CrearCampo
{
    public function __construct(private readonly GuardarSiembraCampania $guardarSiembraCampania) {}

    /**
     * @param  list<array{codigo: string, hectareas: string, geometria: array<string, mixed>|null, restricciones: string|null, desnivel: string|null, limpieza: string|null}>  $lotes
     *
     * @throws CampoDuplicado si el nombre ya pertenece a otro campo activo
     *                        de la misma propiedad (índice parcial `com_campos_nombre_unico`).
     * @throws LoteDuplicado si el código de un lote ya pertenece a otro lote
     *                       activo del mismo campo (índice parcial `com_lotes_codigo_unico`).
     * @throws CampaniaDeOtroCliente si `$campaniaId` no es de una campaña del
     *                               cliente dueño de la propiedad.
     */
    public function ejecutar(int $propiedadId, string $nombre, array $lotes, ?int $cultivoId = null, ?int $campaniaId = null): Campo
    {
        return DB::transaction(function () use ($propiedadId, $nombre, $lotes, $cultivoId, $campaniaId): Campo {
            $campo = new Campo([
                'propiedad_id' => $propiedadId,
                'nombre' => $nombre,
            ]);

            try {
                $campo->save();
            } catch (QueryException $excepcion) {
                $this->relanzarCampoComoDuplicado($excepcion, $nombre);
            }

            $lotesCreados = [];
            foreach ($lotes as $datos) {
                $lotesCreados[] = GuardadoLote::guardar($campo->lotes()->make(), $datos);
            }

            if ($cultivoId !== null && $campaniaId !== null) {
                $this->sembrarLotesGenerados($campo, $cultivoId, $campaniaId, $lotesCreados);
            }

            return $campo->refresh();
        });
    }

    /**
     * Una fila de siembra por lote recién creado, con las mismas hectáreas
     * del lote — el generador no pide una superficie sembrada aparte, asume
     * el lote sembrado entero (ajustable después desde `campos/siembra`).
     *
     * @param  list<Lote>  $lotes
     *
     * @throws CampaniaDeOtroCliente si `$campaniaId` no es de una campaña del cliente dueño del campo.
     */
    private function sembrarLotesGenerados(Campo $campo, int $cultivoId, int $campaniaId, array $lotes): void
    {
        $filas = array_map(fn (Lote $lote): array => [
            'lote_id' => $lote->id,
            'cultivo_id' => $cultivoId,
            'hectareas_sembradas' => (string) $lote->hectareas,
            'fecha_siembra' => null,
            'fecha_cosecha_estimada' => null,
        ], $lotes);

        $this->guardarSiembraCampania->ejecutar($campo, $campaniaId, $filas);
    }

    /**
     * @throws CampoDuplicado si la violación corresponde al nombre.
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarCampoComoDuplicado(QueryException $excepcion, string $nombre): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'com_campos_nombre_unico') || str_contains($mensaje, 'com_campos.nombre')) {
            throw CampoDuplicado::porNombre($nombre);
        }

        throw $excepcion;
    }
}
