<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Comercial\Contratos\LecturaLotes;
use App\Dominios\Mantenimiento\Contratos\LecturaCiclosBateria;
use App\Dominios\Mezclas\Contratos\LecturaMezclas;
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
 * `productosMezcla()` (espec §7, HU-78, tarea 94, revierte CR-01 del
 * 1/9/2026) lista los productos que el piloto transcribió como cargados en
 * el caldo, leídos por `Mezclas\Contratos\LecturaMezclas` (ADR 0003, regla
 * 2 — nunca el Eloquent de `Mezclas`). Sigue sin incluir dosis, orden de
 * incorporación ni compatibilidad entre productos: §7.1 sigue vigente en
 * eso, esta tarea solo revirtió la prohibición de registrar QUÉ se cargó.
 */
final class ArmarContenidoReporteTecnico
{
    public function __construct(
        private readonly CalcularCoberturaTrabajo $calcularCobertura,
        private readonly LecturaLotes $lecturaLotes,
        private readonly LecturaCiclosBateria $lecturaCiclosBateria,
        private readonly LecturaMezclas $lecturaMezclas,
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
     *     productos_mezcla: list<array{producto: string, cantidad: string, unidad: string}>,
     *     equipo: array{
     *         ciclos_bateria: list<array{identificador: string, ciclos_acumulados: int|null}>,
     *         horas_vuelo_dron: string|null,
     *         foto_control_url: string|null,
     *         foto_ciclo_bateria_balanceo_url: string|null,
     *         foto_dron_limpio_url: string|null,
     *     }|null,
     * }
     */
    public function ejecutar(Trabajo $trabajo): array
    {
        $trabajo->loadMissing([
            'sesiones.incidencias.evidenciaFoto',
            'sesiones.capturaRc',
            'sesiones.recargas',
            'imagenCampoEvidencia',
            'acta',
            'condiciones',
            'evidenciaEquipo.fotoControl',
            'evidenciaEquipo.fotoCicloBateriaBalanceo',
            'evidenciaEquipo.fotoDronLimpio',
        ]);

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
            'productos_mezcla' => $this->productosMezcla($trabajo),
            'equipo' => $this->datosEquipo($trabajo, $sesionesVigentes),
        ];
    }

    /**
     * "Reporte de Equipos" (ronda del dueño, 13/9/2026; HU-80, tarea 86):
     * ciclos acumulados de cada batería usada (leídos de `Mantenimiento` vía
     * {@see LecturaCiclosBateria}, nunca calculados acá), horas de vuelo
     * declaradas del dron y las tres fotos de chequeo. `null` completo si el
     * trabajo todavía no tiene su "Reporte de Equipos" cargado — a
     * diferencia del resto de las secciones, esta es siempre opcional: no
     * todo trabajo cerrado antes de HU-80 la va a tener.
     *
     * @param  Collection<int, Sesion>  $sesionesVigentes
     * @return array{ciclos_bateria: list<array{identificador: string, ciclos_acumulados: int|null}>, horas_vuelo_dron: string|null, foto_control_url: string|null, foto_ciclo_bateria_balanceo_url: string|null, foto_dron_limpio_url: string|null}|null
     */
    private function datosEquipo(Trabajo $trabajo, Collection $sesionesVigentes): ?array
    {
        $ciclosBateria = $sesionesVigentes
            ->flatMap(fn (Sesion $sesion): Collection => $sesion->recargas)
            ->pluck('bateria_saliente_id')
            ->unique()
            ->values()
            ->map(fn (string $identificador): array => [
                'identificador' => $identificador,
                'ciclos_acumulados' => $this->lecturaCiclosBateria->obtenerCiclosAcumulados($identificador),
            ])
            ->all();

        $evidenciaEquipo = $trabajo->evidenciaEquipo;

        if ($evidenciaEquipo === null) {
            return $ciclosBateria === [] ? null : [
                'ciclos_bateria' => $ciclosBateria,
                'horas_vuelo_dron' => null,
                'foto_control_url' => null,
                'foto_ciclo_bateria_balanceo_url' => null,
                'foto_dron_limpio_url' => null,
            ];
        }

        return [
            'ciclos_bateria' => $ciclosBateria,
            'horas_vuelo_dron' => (string) $evidenciaEquipo->horas_vuelo_dron,
            'foto_control_url' => $evidenciaEquipo->fotoControl?->archivo_url,
            'foto_ciclo_bateria_balanceo_url' => $evidenciaEquipo->fotoCicloBateriaBalanceo?->archivo_url,
            'foto_dron_limpio_url' => $evidenciaEquipo->fotoDronLimpio?->archivo_url,
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

    /**
     * Espec §7 (HU-78, tarea 94, revierte CR-01): lo que el piloto transcribió
     * como cargado en el caldo de ESTE trabajo, en TODAS sus mezclas (puede
     * haber más de un evento `mezcla`, mismo criterio que
     * `ope_recepciones_caldo`). Lista vacía si no se registró ninguna — no
     * hay nota fija que mostrar en su lugar, el listado vacío ya lo dice.
     *
     * @return list<array{producto: string, cantidad: string, unidad: string}>
     */
    private function productosMezcla(Trabajo $trabajo): array
    {
        return array_map(
            static fn ($producto): array => $producto->toArray(),
            $this->lecturaMezclas->listarPorTrabajoId($trabajo->id),
        );
    }
}
