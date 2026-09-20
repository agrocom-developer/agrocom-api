<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Infraestructura\Eloquent\Accesorio;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoAccesorio;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use InvalidArgumentException;

/**
 * Agrega un accesorio a una cuadrilla, con su cantidad (tarea
 * "cuadrillas-estadias", pedido del dueño 19/9/2026: machete, palas,
 * linternas…). Recibe `accesorioId` (uno del catálogo) O `nombreNuevo` (texto
 * libre) — exactamente uno de los dos, ya lo exige
 * `AgregarAccesorioEquipoRequest`.
 *
 * Con `nombreNuevo`: busca en el catálogo SIN distinguir mayúsculas ni
 * espacios (mismo criterio que el índice parcial de la migración) y lo crea
 * si no existe — así "Pala", "pala" y "  Pala  " terminan siendo el mismo
 * accesorio.
 *
 * Si la cuadrilla YA tiene ese accesorio asignado, actualiza su cantidad y su
 * observación en vez de duplicar la fila (índice único parcial
 * `per_equipo_accesorios_unico`).
 */
final class AgregarAccesorioEquipo
{
    public function ejecutar(
        EquipoTrabajo $equipo,
        ?int $accesorioId,
        ?string $nombreNuevo,
        int $cantidad,
        ?string $observacion,
    ): EquipoAccesorio {
        $accesorio = $accesorioId !== null
            ? Accesorio::query()->findOrFail($accesorioId)
            : $this->obtenerOCrear($nombreNuevo);

        $existente = EquipoAccesorio::query()
            ->where('equipo_trabajo_id', $equipo->id)
            ->where('accesorio_id', $accesorio->id)
            ->first();

        if ($existente instanceof EquipoAccesorio) {
            $existente->fill(['cantidad' => $cantidad, 'observacion' => $observacion]);
            $existente->save();

            return $existente->refresh();
        }

        return EquipoAccesorio::query()->create([
            'equipo_trabajo_id' => $equipo->id,
            'accesorio_id' => $accesorio->id,
            'cantidad' => $cantidad,
            'observacion' => $observacion,
        ]);
    }

    private function obtenerOCrear(?string $nombreNuevo): Accesorio
    {
        if ($nombreNuevo === null || trim($nombreNuevo) === '') {
            // No debería llegar acá: `AgregarAccesorioEquipoRequest` exige
            // `accesorio_id` o `nombre_nuevo`. Guarda defensiva, no un caso
            // de negocio real.
            throw new InvalidArgumentException('Falta el accesorio: ni accesorio_id ni nombre_nuevo.');
        }

        $normalizado = trim(preg_replace('/\s+/', ' ', $nombreNuevo) ?? $nombreNuevo);

        $existente = Accesorio::query()
            ->whereRaw('lower(nombre) = ?', [mb_strtolower($normalizado)])
            ->first();

        return $existente instanceof Accesorio
            ? $existente
            : Accesorio::query()->create(['nombre' => $normalizado, 'activo' => true]);
    }
}
