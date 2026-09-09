<?php

namespace App\Dominios\Compartido\Aplicacion;

use App\Dominios\Compartido\Contratos\LecturaConfiguracion;
use App\Dominios\Compartido\Infraestructura\Eloquent\Configuracion;

/**
 * Arma el estado de un grupo de `/panel/configuracion` (tarea 78, HU-55) para
 * la vista — el corazón de la tarea: de acá para afuera (el controlador, el
 * Blade) NUNCA circula un valor secreto completo, solo su estado y, como
 * mucho, los últimos 4 caracteres.
 *
 * Para una clave `es_secreto`, "configurada" mira SOLO la fila de
 * `plt_configuraciones` (nunca el respaldo de `.env`): esta pantalla
 * administra la base, no el archivo — el respaldo es cosa de
 * {@see LecturaConfiguracion} para el consumidor final (tarea 79), no algo
 * que el dueño edite acá. Para una clave NO secreta, en cambio, sí se
 * prellena con el valor EFECTIVO (base o respaldo) porque no hay nada que
 * ocultar y es lo que el dueño espera ver/editar.
 *
 * @phpstan-type FilaConfiguracion array{
 *     clave: string,
 *     descripcion: string,
 *     esSecreto: bool,
 *     configurada: bool,
 *     ultimos4: ?string,
 *     valorVisible: ?string,
 * }
 */
final class ListarConfiguracionPorGrupo
{
    public function __construct(private readonly LecturaConfiguracion $lectura) {}

    /**
     * @return list<array{clave: string, descripcion: string, esSecreto: bool, configurada: bool, ultimos4: ?string, valorVisible: ?string}>
     */
    public function ejecutar(string $grupo): array
    {
        $catalogo = collect((array) config('configuracion.claves'))->filter(
            fn (array $meta): bool => $meta['grupo'] === $grupo,
        );

        // `trans('configuracion.claves')` como ARRAY (dos segmentos: archivo +
        // clave), después búsqueda literal adentro — mismo motivo que
        // `ResolutorConfiguracion::respaldo()`: `$clave` ya trae puntos
        // propios (`mapas.google_maps_api_key`) y `__("configuracion.claves.{$clave}")`
        // los leería como más niveles anidados, no como la llave plana que
        // declara `lang/es/configuracion.php`.
        $descripciones = trans('configuracion.claves');

        $filas = Configuracion::query()->whereIn('clave', $catalogo->keys())->get()->keyBy('clave');

        return $catalogo->map(function (array $meta, string $clave) use ($filas, $descripciones): array {
            $fila = $filas->get($clave);
            $esSecreto = (bool) $meta['es_secreto'];
            $descripcion = $descripciones[$clave] ?? $clave;

            if ($esSecreto) {
                $configurada = $fila !== null && $fila->valor !== null;

                return [
                    'clave' => $clave,
                    'descripcion' => $descripcion,
                    'esSecreto' => true,
                    'configurada' => $configurada,
                    'ultimos4' => $configurada ? substr((string) $fila->valor, -4) : null,
                    'valorVisible' => null,
                ];
            }

            $valorEfectivo = $this->lectura->valor($clave);

            return [
                'clave' => $clave,
                'descripcion' => $descripcion,
                'esSecreto' => false,
                'configurada' => $valorEfectivo !== null,
                'ultimos4' => null,
                'valorVisible' => $valorEfectivo,
            ];
        })->values()->all();
    }
}
