<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Aplicacion\Lote\GuardadoLote;
use App\Dominios\Comercial\Dominio\Excepciones\LoteDuplicado;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * "Crear Lotes" con un solo botón (HU-72, tarea 88 — reconstruida
 * 16/9/2026 contra `Propiedad → Lote` directo; la original vivía en
 * `CrearCampo`/`CamposController`, borrados enteros al colapsar `Campo`,
 * ADR 0020, sin que nadie la migrara al modelo nuevo).
 *
 * Genera `$cantidad` lotes con código provisorio ("Lote N") y una
 * hectárea placeholder — a corregir después desde la ficha de cada lote
 * (renombrar, dibujar el polígono y ahí "usar superficie" copia la del
 * dibujo). Si viene cultivo+campaña, siembra cada lote recién creado
 * reusando {@see GuardarSiembraCampania} (mismo patrón que el `CrearCampo`
 * viejo): misma transacción, así que una campaña inválida revierte también
 * los lotes.
 */
final class CrearLotesMasivo
{
    private const HECTAREAS_PLACEHOLDER = '1.00';

    private const INTENTOS_MAXIMOS = 200;

    public function __construct(private readonly GuardarSiembraCampania $guardarSiembraCampania) {}

    /** @return list<Lote> */
    public function ejecutar(Propiedad $propiedad, int $cantidad, ?int $cultivoId, ?int $campaniaId): array
    {
        return DB::transaction(function () use ($propiedad, $cantidad, $cultivoId, $campaniaId): array {
            $lotes = [];
            $siguienteNumero = $propiedad->lotes()->count() + 1;

            for ($i = 0; $i < $cantidad; $i++) {
                [$lote, $siguienteNumero] = $this->crearConCodigoLibre($propiedad, $siguienteNumero);
                $lotes[] = $lote;
            }

            if ($cultivoId !== null && $campaniaId !== null) {
                $this->sembrarLotesGenerados($propiedad, $cultivoId, $campaniaId, $lotes);
            }

            return $lotes;
        });
    }

    /**
     * @return array{0: Lote, 1: int} el lote creado y el próximo número a intentar.
     *
     * @throws RuntimeException si se agotan los intentos (defensivo: no
     *                          debería pasar generando lotes nuevos).
     */
    private function crearConCodigoLibre(Propiedad $propiedad, int $numero): array
    {
        for ($intento = 0; $intento < self::INTENTOS_MAXIMOS; $intento++) {
            try {
                $lote = GuardadoLote::guardar($propiedad->lotes()->make(), [
                    'codigo' => "Lote {$numero}",
                    'hectareas' => self::HECTAREAS_PLACEHOLDER,
                    'geometria' => null,
                    'restricciones' => null,
                    'desnivel' => null,
                    'limpieza' => null,
                ]);

                return [$lote, $numero + 1];
            } catch (LoteDuplicado) {
                $numero++;
            }
        }

        throw new RuntimeException('No se pudo generar un código de lote libre tras '.self::INTENTOS_MAXIMOS.' intentos.');
    }

    /**
     * Una fila de siembra por lote recién creado, con las mismas hectáreas
     * placeholder del lote — igual que el generador viejo, ajustable
     * después desde `propiedades/siembra`.
     *
     * @param  list<Lote>  $lotes
     */
    private function sembrarLotesGenerados(Propiedad $propiedad, int $cultivoId, int $campaniaId, array $lotes): void
    {
        $filas = array_map(fn (Lote $lote): array => [
            'lote_id' => $lote->id,
            'cultivo_id' => $cultivoId,
            'hectareas_sembradas' => (string) $lote->hectareas,
            'fecha_siembra' => null,
            'fecha_cosecha_estimada' => null,
        ], $lotes);

        $this->guardarSiembraCampania->ejecutar($propiedad, $campaniaId, $filas);
    }
}
