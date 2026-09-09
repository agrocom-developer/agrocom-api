<?php

namespace App\Dominios\Compartido\Aplicacion;

use App\Dominios\Compartido\Infraestructura\Eloquent\Configuracion;

/**
 * Guarda un grupo entero de `/panel/configuracion` de una vez (tarea 78,
 * HU-55) — el formulario de cada pestaña (mapas/correo/integraciones) postea
 * todas sus claves juntas.
 *
 * Reglas de secretos (la otra mitad del corazón de la tarea, ver
 * {@see ListarConfiguracionPorGrupo}):
 * - `valor` vacío en una clave `es_secreto`: se IGNORA, nunca borra lo que ya
 *   había — el `<input>` de un secreto siempre llega vacío desde la vista
 *   (nunca se prellena), así que "vacío" no distingue "no lo toqué" de
 *   "quiero borrarlo". Por eso el borrado es un campo aparte y explícito.
 * - `borrar[$clave]`: únicamente esto pone `valor` en NULL. No secreto:
 *   vaciar el campo SÍ actualiza a vacío, sin necesitar el checkbox — no hay
 *   nada que ocultar en un valor no secreto.
 */
final class GuardarConfiguracion
{
    /**
     * @param  array<string, ?string>  $valores  clave => valor tal como llegó del formulario
     * @param  array<string, bool>  $borrar  clave => true si se marcó "Borrar"
     */
    public function ejecutar(string $grupo, array $valores, array $borrar): void
    {
        $catalogo = collect((array) config('configuracion.claves'))->filter(
            fn (array $meta): bool => $meta['grupo'] === $grupo,
        );

        foreach ($catalogo as $clave => $meta) {
            $esSecreto = (bool) $meta['es_secreto'];

            if ($borrar[$clave] ?? false) {
                $this->fila($clave)?->update(['valor' => null]);

                continue;
            }

            $valor = $valores[$clave] ?? null;

            if (($valor === null || $valor === '') && $esSecreto) {
                continue;
            }

            $fila = $this->fila($clave) ?? new Configuracion([
                'clave' => $clave,
                'grupo' => $grupo,
                'es_secreto' => $esSecreto,
            ]);

            $fila->valor = $valor ?? '';
            $fila->save();
        }
    }

    private function fila(string $clave): ?Configuracion
    {
        return Configuracion::query()->where('clave', $clave)->first();
    }
}
