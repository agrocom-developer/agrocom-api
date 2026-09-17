<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Aplicacion\Lote\GuardadoLote;
use App\Dominios\Comercial\Dominio\Excepciones\LoteDuplicado;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * "Crear Lotes" con un solo botón (HU-72, tarea 88 — reconstruida
 * 16/9/2026 contra `Propiedad → Lote` directo; la original vivía en
 * `CrearCampo`/`CamposController`, borrados enteros al colapsar `Campo`,
 * ADR 0020, sin que nadie la migrara al modelo nuevo).
 *
 * Solo crea ESTRUCTURA: `$cantidad` lotes con los MISMOS atributos de
 * terreno (desnivel/limpieza/restricciones) — pedido directo, 16/9/2026:
 * una sola carga, aplicada a todos, no una fila por lote (si un lote
 * particular necesita algo distinto, se ajusta después desde su propia
 * ficha). `hectareas` queda en un placeholder (a corregir después
 * dibujando el polígono y usando "usar superficie").
 *
 * SIN cultivo ni campaña a propósito (16/9/2026, corregido tras confundir
 * los dos conceptos): eso es SIEMBRA, no estructura del lote — vive en
 * `GuardarSiembraCampania`/`propiedades/siembra`, nunca acá. Un lote no
 * "es" de un cultivo (relación lote×campaña, ADR 0015 punto 4).
 *
 * `codigo` se arma con un PREFIJO elegido (no fijo "Lote"): el número
 * correlativo continúa desde el máximo ya usado en la propiedad CON ESE
 * MISMO prefijo, para que tandas sucesivas no choquen entre sí (ver
 * {@see siguienteNumero}).
 */
final class CrearLotesMasivo
{
    private const HECTAREAS_PLACEHOLDER = '1.00';

    private const INTENTOS_MAXIMOS = 200;

    /**
     * @param  array{desnivel: string|null, limpieza: string|null, restricciones: string|null}  $atributosTerreno  aplicados a TODOS los lotes generados.
     * @return list<Lote>
     */
    public function ejecutar(Propiedad $propiedad, string $prefijo, int $cantidad, array $atributosTerreno): array
    {
        return DB::transaction(function () use ($propiedad, $prefijo, $cantidad, $atributosTerreno): array {
            $lotes = [];
            $siguienteNumero = $this->siguienteNumero($propiedad, $prefijo);

            for ($i = 0; $i < $cantidad; $i++) {
                [$lote, $siguienteNumero] = $this->crearConCodigoLibre($propiedad, $prefijo, $siguienteNumero, $atributosTerreno);
                $lotes[] = $lote;
            }

            return $lotes;
        });
    }

    /**
     * Primer número libre para `$prefijo` en esta propiedad: entre los
     * códigos que ya empiezan con él, el que queda después de la parte
     * numérica más alta — códigos con el mismo prefijo pero un resto no
     * numérico (p. ej. "Lote Norte" con prefijo "Lote ") no cuentan.
     */
    private function siguienteNumero(Propiedad $propiedad, string $prefijo): int
    {
        $maximo = $propiedad->lotes()
            ->pluck('codigo')
            ->filter(fn (string $codigo) => str_starts_with($codigo, $prefijo))
            ->map(fn (string $codigo) => substr($codigo, strlen($prefijo)))
            ->filter(fn (string $resto) => preg_match('/^\d+$/', $resto) === 1)
            ->map(fn (string $resto) => (int) $resto)
            ->max();

        return ($maximo ?? 0) + 1;
    }

    /**
     * @param  array{desnivel: string|null, limpieza: string|null, restricciones: string|null}  $atributosTerreno
     * @return array{0: Lote, 1: int} el lote creado y el próximo número a intentar.
     *
     * @throws RuntimeException si se agotan los intentos (defensivo: no
     *                          debería pasar con la numeración correlativa).
     */
    private function crearConCodigoLibre(Propiedad $propiedad, string $prefijo, int $numero, array $atributosTerreno): array
    {
        for ($intento = 0; $intento < self::INTENTOS_MAXIMOS; $intento++) {
            try {
                $lote = GuardadoLote::guardar($propiedad->lotes()->make(), [
                    'codigo' => $prefijo.$numero,
                    'hectareas' => self::HECTAREAS_PLACEHOLDER,
                    'geometria' => null,
                    'restricciones' => $atributosTerreno['restricciones'],
                    'desnivel' => $atributosTerreno['desnivel'],
                    'limpieza' => $atributosTerreno['limpieza'],
                ]);

                return [$lote, $numero + 1];
            } catch (LoteDuplicado) {
                $numero++;
            }
        }

        throw new RuntimeException(Texto::de('comercial.errores.lote_codigo_libre_agotado', ['intentos' => self::INTENTOS_MAXIMOS]));
    }
}
