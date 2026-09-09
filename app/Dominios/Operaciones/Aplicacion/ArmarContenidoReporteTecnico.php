<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Comercial\Contratos\LecturaLotes;
use App\Dominios\Operaciones\Dominio\EstadoCoberturaTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Condiciones;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Incidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Ensambla el contenido del reporte técnico (espec §9, "Técnico — por
 * lote"; HU-18, tarea 25) como un array plano — separado de
 * `GenerarReporteTecnico` (que persiste la fila y renderiza el PDF) a
 * propósito: un PDF ya generado por `barryvdh/laravel-dompdf` comprime sus
 * streams (`deflate`), así que no hay forma práctica de que un test verifique
 * "el reporte incluye/no incluye tal dato" abriendo el binario. Esta clase es
 * lo que un test SÍ puede invocar y leer directo (ver
 * `tests/Feature/Api/ReporteTecnicoTest.php`).
 *
 * Todo se recalcula desde las relaciones de `Trabajo` en cada llamada
 * (invariante 6) — el snapshot real que "no se mueve" después es el PDF ya
 * renderizado y las columnas `hora_inicio`/`hora_fin` de `ReporteTecnico`,
 * no esta clase (ver runs/25.md).
 *
 * Nunca incluye mezcla, dosis, receta ni producto — CR-01 (espec §7) saca
 * eso de alcance; no hay ni de dónde leerlo (sin módulo `Mezclas`, sin
 * `receta_id` en el esquema). La única mención es la nota fija de
 * `notaMezcla()`, en texto explícito, no un placeholder vacío.
 */
final class ArmarContenidoReporteTecnico
{
    public function __construct(
        private readonly CalcularCoberturaTrabajo $calcularCobertura,
        private readonly LecturaLotes $lecturaLotes,
    ) {}

    /**
     * @return array{
     *     trabajo_id: int,
     *     imagen_campo_url: string|null,
     *     hora_inicio: CarbonImmutable|null,
     *     hora_fin: CarbonImmutable|null,
     *     acta: array{id: int, fecha_firma: CarbonImmutable|null, firmante: string|null}|null,
     *     resumen: array{hectareas_declaradas: string, litros_por_hectarea: string|null, cobertura: string|null},
     *     condiciones: list<array{sesion_id: int, viento_kmh: string, temperatura_c: string, humedad_pct: string, resultado: string}>,
     *     superficie_no_aplicada: array{hectareas: string, motivo: string|null}|null,
     *     capturas_rc: list<array{secuencia: int, hectareas_declaradas: string, evidencia_url: string}>,
     *     incidencias: list<array{tipo: string, evidencia_url: string}>,
     *     sesiones_detalle: list<array{sesion_id: int, piloto_id: int, dron_id: int|null, hectareas_declaradas: string, motivo_cierre: string|null}>,
     *     nota_mezcla: string,
     * }
     */
    public function ejecutar(Trabajo $trabajo): array
    {
        $trabajo->loadMissing(['sesiones.incidencias.evidenciaFoto', 'sesiones.capturaRc', 'imagenCampoEvidencia', 'acta', 'condiciones']);

        /** @var Collection<int, Sesion> $sesionesVigentes */
        $sesionesVigentes = $trabajo->sesiones->whereNull('anulada_en')->sortBy('secuencia')->values();

        $cobertura = $this->calcularCobertura->ejecutar($trabajo);

        return [
            'trabajo_id' => $trabajo->id,
            'imagen_campo_url' => $trabajo->imagenCampoEvidencia?->archivo_url,
            'hora_inicio' => $sesionesVigentes->min('inicio'),
            'hora_fin' => $sesionesVigentes->max('fin'),
            'acta' => $this->datosActa($trabajo),
            'resumen' => [
                'hectareas_declaradas' => (string) $trabajo->hectareas_declaradas,
                'litros_por_hectarea' => $this->litrosPorHectarea($trabajo),
                'cobertura' => $cobertura?->value,
            ],
            'condiciones' => $trabajo->condiciones
                ->map(fn (Condiciones $condiciones): array => [
                    'sesion_id' => $condiciones->sesion_id,
                    'viento_kmh' => (string) $condiciones->viento_kmh,
                    'temperatura_c' => (string) $condiciones->temperatura_c,
                    'humedad_pct' => (string) $condiciones->humedad_pct,
                    'resultado' => $condiciones->resultado(),
                ])
                ->all(),
            'superficie_no_aplicada' => $this->superficieNoAplicada($trabajo, $cobertura, $sesionesVigentes),
            // Espec §4.3: cada sesión «se cierra con su propia captura de
            // RC» — la foto de la pantalla del control remoto con las
            // hectáreas, el tiempo de vuelo y los litros que el piloto
            // declaró. Es la evidencia que sostiene el número facturado, así
            // que va en el reporte que ve el cliente. Solo las sesiones que
            // efectivamente la tienen (`captura_rc_id` es nullable: una
            // sesión abierta todavía no llegó a su cierre).
            'capturas_rc' => $sesionesVigentes
                ->filter(fn (Sesion $sesion): bool => $sesion->capturaRc !== null)
                ->map(fn (Sesion $sesion): array => [
                    'secuencia' => $sesion->secuencia,
                    'hectareas_declaradas' => (string) $sesion->hectareas_declaradas,
                    'evidencia_url' => (string) $sesion->capturaRc?->archivo_url,
                ])
                ->values()
                ->all(),
            // HU-08 (tarea 22, incidencias con evidencia): cuelgan de la
            // SESIÓN, no del trabajo directo — se recolectan de
            // `$sesionesVigentes`, no de una relación `Trabajo::incidencias()`
            // (no existe, y no hace falta crearla).
            'incidencias' => $sesionesVigentes
                ->flatMap(fn (Sesion $sesion): Collection => $sesion->incidencias)
                ->map(fn (Incidencia $incidencia): array => [
                    'tipo' => $incidencia->tipo->value,
                    'evidencia_url' => $incidencia->evidenciaFoto?->archivo_url,
                ])
                ->all(),
            'sesiones_detalle' => $sesionesVigentes->count() > 1
                ? $sesionesVigentes
                    ->map(fn (Sesion $sesion): array => [
                        'sesion_id' => $sesion->id,
                        'piloto_id' => $sesion->piloto_id,
                        'dron_id' => $sesion->dron_id,
                        'hectareas_declaradas' => (string) $sesion->hectareas_declaradas,
                        'motivo_cierre' => $sesion->motivo_cierre,
                    ])
                    ->all()
                : [],
            'nota_mezcla' => $this->notaMezcla(),
        ];
    }

    /** @return array{id: int, fecha_firma: CarbonImmutable|null, firmante: string|null}|null */
    private function datosActa(Trabajo $trabajo): ?array
    {
        if ($trabajo->acta === null) {
            return null;
        }

        return [
            'id' => $trabajo->acta->id,
            'fecha_firma' => $trabajo->acta->fecha_firma,
            'firmante' => $trabajo->acta->firmante,
        ];
    }

    private function litrosPorHectarea(Trabajo $trabajo): ?string
    {
        $hectareas = BigDecimal::of((string) $trabajo->hectareas_declaradas);

        if ($hectareas->isZero()) {
            return null;
        }

        $consumido = BigDecimal::of($trabajo->cuadreCaldo()['consumido']);

        return (string) $consumido->dividedBy($hectareas, 2, RoundingMode::HalfUp);
    }

    /**
     * Solo se completa para `Parcial` (espec: "superficie no aplicada y
     * motivo"): `Observado` es el caso inverso (exceso sobre el lote, ya
     * cubierto por la bandeja de alertas de HU-19) y `Completo`/`null` no
     * tienen faltante que reportar.
     *
     * @param  Collection<int, Sesion>  $sesionesVigentes
     * @return array{hectareas: string, motivo: string|null}|null
     */
    private function superficieNoAplicada(Trabajo $trabajo, ?EstadoCoberturaTrabajo $cobertura, Collection $sesionesVigentes): ?array
    {
        if ($cobertura !== EstadoCoberturaTrabajo::Parcial) {
            return null;
        }

        $lote = $this->lecturaLotes->obtenerPorId($trabajo->lote_id);

        if ($lote === null) {
            return null;
        }

        $faltante = BigDecimal::of($lote->hectareas)->minus(BigDecimal::of((string) $trabajo->hectareas_declaradas));

        if ($faltante->isNegative()) {
            $faltante = BigDecimal::zero();
        }

        $motivo = $sesionesVigentes
            ->first(fn (Sesion $sesion): bool => $sesion->motivo_cierre !== null && $sesion->motivo_cierre !== 'completado')
            ?->motivo_cierre;

        return [
            'hectareas' => (string) $faltante->toScale(2),
            'motivo' => $motivo,
        ];
    }

    private function notaMezcla(): string
    {
        return 'Mezcla y dosis: fuera de alcance (CR-01, 1/9/2026). El cliente formula, '
            .'prepara y controla la calidad de su propio caldo — Agrocom recibe el caldo '
            .'ya hecho y lo aplica.';
    }
}
