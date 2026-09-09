<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Aplicacion\AgregarPausasPorCausa;
use App\Dominios\Operaciones\Contratos\AlertaPanel;
use App\Dominios\Operaciones\Contratos\EquipoPersonaPanel;
use App\Dominios\Operaciones\Contratos\EvidenciaPanel;
use App\Dominios\Operaciones\Contratos\LecturaPanelOperaciones;
use App\Dominios\Operaciones\Contratos\ResumenLotePanel;
use App\Dominios\Operaciones\Contratos\SesionPanel;
use App\Dominios\Operaciones\Dominio\EstadoAlerta;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\TonoEstadoSesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Alerta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;

/**
 * Implementación Eloquent del contrato de contenido del dashboard (tarea
 * 67). Vive fuera de `Infraestructura/Eloquent/` por el mismo motivo que
 * {@see LecturaContadoresPanelEloquent}: no es un modelo, es el adaptador
 * que el `ServiceProvider` liga al contrato.
 *
 * Dos decisiones que se repiten en varios métodos:
 *
 * - **Las hectáreas se suman con `BigDecimal` en PHP, nunca con `SUM()` de
 *   SQL** (invariante 6). En SQLite —el motor de los tests— la agregación
 *   numérica pasa por REAL/float; el dashboard muestra las mismas hectáreas
 *   que después se facturan, así que no puede redondear distinto que
 *   `ObtenerAvanceComercial`.
 * - **Las sesiones anuladas (`anulada_en`) no cuentan para ningún agregado**
 *   pero sí aparecen en los listados: la invariante 2 prohíbe pisar la fila
 *   rechazada, así que sigue existiendo y hay que excluirla explícitamente
 *   de toda suma en vez de confiar en el estado.
 */
final class LecturaPanelOperacionesEloquent implements LecturaPanelOperaciones
{
    public function __construct(private readonly AgregarPausasPorCausa $pausasPorCausa) {}

    public function sesionesRecientes(int $limite, ?int $pilotoId = null): array
    {
        $sesiones = Sesion::query()
            ->with(['trabajo:id,lote_id', 'dron:id,identificador'])
            ->when($pilotoId !== null, fn ($consulta) => $consulta->where(
                fn ($anidada) => $anidada->where('piloto_id', $pilotoId)->orWhere('auxiliar_id', $pilotoId)
            ))
            ->orderByDesc('inicio')
            ->orderByDesc('id')
            ->limit($limite)
            ->get();

        return $sesiones->map(fn (Sesion $sesion) => $this->aSesionPanel($sesion))->all();
    }

    public function colaValidacion(int $limite): array
    {
        $sesiones = Sesion::query()
            ->with(['trabajo:id,lote_id', 'dron:id,identificador'])
            ->where('estado', EstadoSesion::Cerrado)
            ->whereNull('anulada_en')
            ->orderBy('inicio')
            ->orderBy('id')
            ->limit($limite)
            ->get();

        return $sesiones->map(fn (Sesion $sesion) => $this->aSesionPanel($sesion))->all();
    }

    public function distribucionPorEstado(?int $pilotoId = null): array
    {
        $conteos = Sesion::query()
            ->whereNull('anulada_en')
            ->when($pilotoId !== null, fn ($consulta) => $consulta->where(
                fn ($anidada) => $anidada->where('piloto_id', $pilotoId)->orWhere('auxiliar_id', $pilotoId)
            ))
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $distribucion = [];

        foreach (EstadoSesion::cases() as $estado) {
            $distribucion[] = [
                'estado' => $estado->value,
                'tono' => TonoEstadoSesion::deSesion($estado)->value,
                'valor' => (int) ($conteos[$estado->value] ?? 0),
            ];
        }

        return $distribucion;
    }

    public function hectareasPorDia(int $dias, ?int $pilotoId = null): array
    {
        $desde = Carbon::today()->subDays($dias - 1)->startOfDay();

        $sesiones = Sesion::query()
            ->where('estado', EstadoSesion::Validado)
            ->whereNull('anulada_en')
            ->where('inicio', '>=', $desde)
            ->when($pilotoId !== null, fn ($consulta) => $consulta->where(
                fn ($anidada) => $anidada->where('piloto_id', $pilotoId)->orWhere('auxiliar_id', $pilotoId)
            ))
            ->get(['id', 'inicio', 'hectareas_declaradas']);

        $acumulado = [];

        foreach ($sesiones as $sesion) {
            $dia = $sesion->inicio->format('Y-m-d');
            $acumulado[$dia] = ($acumulado[$dia] ?? BigDecimal::zero())
                ->plus(BigDecimal::of($sesion->hectareas_declaradas));
        }

        // Los días sin vuelo van en cero y no se saltean: un área que omite
        // un día comprime el eje y dibuja una pendiente que no existió.
        $serie = [];

        for ($i = 0; $i < $dias; $i++) {
            $fecha = $desde->copy()->addDays($i)->format('Y-m-d');
            $serie[] = [
                'fecha' => $fecha,
                'hectareas' => $this->aEscalaDos($acumulado[$fecha] ?? BigDecimal::zero()),
            ];
        }

        return $serie;
    }

    public function resumenPorLote(): array
    {
        $sesiones = Sesion::query()
            ->with('trabajo:id,lote_id')
            ->whereNull('anulada_en')
            ->orderBy('inicio')
            ->get();

        /** @var array<int, array{sesiones: int, validadas: int, hectareas: BigDecimal, litros: BigDecimal, minutos: int, ultima: ?string, estados: list<EstadoSesion>}> $porLote */
        $porLote = [];

        foreach ($sesiones as $sesion) {
            $loteId = $sesion->trabajo?->lote_id;

            if ($loteId === null) {
                continue;
            }

            $acumulado = $porLote[$loteId] ?? [
                'sesiones' => 0,
                'validadas' => 0,
                'hectareas' => BigDecimal::zero(),
                'litros' => BigDecimal::zero(),
                'minutos' => 0,
                'ultima' => null,
                'estados' => [],
            ];

            $acumulado['sesiones']++;
            $acumulado['estados'][] = $sesion->estado;
            $acumulado['ultima'] = $sesion->inicio->toIso8601String();

            if ($sesion->litros_consumidos !== null) {
                $acumulado['litros'] = $acumulado['litros']->plus(BigDecimal::of($sesion->litros_consumidos));
            }

            if ($sesion->fin !== null) {
                $acumulado['minutos'] += (int) round($sesion->inicio->diffInMinutes($sesion->fin));
            }

            if ($sesion->estado === EstadoSesion::Validado) {
                $acumulado['validadas']++;
                $acumulado['hectareas'] = $acumulado['hectareas']->plus(BigDecimal::of($sesion->hectareas_declaradas));
            }

            $porLote[$loteId] = $acumulado;
        }

        $resumen = [];

        foreach ($porLote as $loteId => $acumulado) {
            $resumen[$loteId] = new ResumenLotePanel(
                loteId: $loteId,
                sesiones: $acumulado['sesiones'],
                sesionesValidadas: $acumulado['validadas'],
                hectareasAplicadas: $this->aEscalaDos($acumulado['hectareas']),
                tono: $this->tonoAgregado($acumulado['estados'])->value,
                ultimaSesion: $acumulado['ultima'],
                litrosConsumidos: $this->aEscalaDos($acumulado['litros']),
                minutosVuelo: $acumulado['minutos'],
            );
        }

        return $resumen;
    }

    public function sesionesConCapturas(int $limite): array
    {
        $sesiones = Sesion::query()
            ->with(['capturaRc', 'incidencias.evidenciaFoto', 'trabajo:id,lote_id', 'dron:id,identificador'])
            ->whereNull('anulada_en')
            ->where(fn ($consulta) => $consulta
                ->whereNotNull('captura_rc_id')
                ->orWhereHas('incidencias', fn ($sub) => $sub->whereNotNull('evidencia_foto_id')))
            ->orderByDesc('inicio')
            ->orderByDesc('id')
            ->limit($limite)
            ->get();

        $conCapturas = [];

        foreach ($sesiones as $sesion) {
            $capturas = $this->capturasDe($sesion);

            if ($capturas === []) {
                continue;
            }

            $conCapturas[] = ['sesion' => $this->aSesionPanel($sesion), 'capturas' => $capturas];
        }

        return $conCapturas;
    }

    /**
     * Evidencia gráfica de una sesión: su captura del control remoto más las
     * fotos de sus incidencias.
     *
     * @return list<EvidenciaPanel>
     */
    private function capturasDe(Sesion $sesion): array
    {
        $evidencias = [];

        if ($sesion->capturaRc !== null) {
            $evidencias[] = $sesion->capturaRc;
        }

        foreach ($sesion->incidencias as $incidencia) {
            if ($incidencia->evidenciaFoto !== null) {
                $evidencias[] = $incidencia->evidenciaFoto;
            }
        }

        return array_map(fn (Evidencia $evidencia) => new EvidenciaPanel(
            id: $evidencia->id,
            tipo: $evidencia->tipo->value,
            // `archivo_url` es una ruta privada del disco `r2`, nunca una URL
            // pública: se sirve por streaming con el permiso reverificado.
            url: route('panel.evidencias.archivo', $evidencia->id),
            fecha: $evidencia->fecha->toIso8601String(),
            loteId: $sesion->trabajo?->lote_id,
            trabajoId: $sesion->trabajo_id,
        ), $evidencias);
    }

    public function alertasRecientes(int $limite): array
    {
        $alertas = Alerta::query()
            ->orderByRaw('CASE WHEN estado = ? THEN 0 ELSE 1 END', [EstadoAlerta::Pendiente->value])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limite)
            ->get();

        return $alertas
            ->map(fn (Alerta $alerta) => new AlertaPanel(
                id: $alerta->id,
                tipo: $alerta->tipo->value,
                mensaje: $alerta->mensaje,
                creadaEn: $alerta->created_at?->toIso8601String() ?? '',
                pendiente: $alerta->estado === EstadoAlerta::Pendiente,
            ))
            ->all();
    }

    public function pausasPorCausaDelMes(): array
    {
        // Delega en el caso de uso de HU-44 en vez de reagrupar: el
        // dashboard y `/panel/pausas` tienen que contar los mismos minutos,
        // y el catálogo completo de causas (incluidas las que están en cero)
        // ya lo resuelve ese caso de uso.
        return $this->pausasPorCausa->ejecutar(Carbon::now()->format('Y-m'));
    }

    public function equiposDePersonaDelMes(int $personaId): array
    {
        $sesiones = Sesion::query()
            ->with('dron:id,identificador,modelo')
            ->whereNull('anulada_en')
            ->whereNotNull('dron_id')
            ->whereBetween('inicio', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->where(fn ($consulta) => $consulta->where('piloto_id', $personaId)->orWhere('auxiliar_id', $personaId))
            ->orderBy('inicio')
            ->get();

        /** @var array<int, array{dron: Dron, sesiones: int, hectareas: BigDecimal, minutos: int, ultimo: ?string}> $porDron */
        $porDron = [];

        foreach ($sesiones as $sesion) {
            $dron = $sesion->dron;

            if ($dron === null) {
                continue;
            }

            $acumulado = $porDron[$dron->id] ?? [
                'dron' => $dron,
                'sesiones' => 0,
                'hectareas' => BigDecimal::zero(),
                'minutos' => 0,
                'ultimo' => null,
            ];

            $acumulado['sesiones']++;
            $acumulado['hectareas'] = $acumulado['hectareas']->plus(BigDecimal::of($sesion->hectareas_declaradas));
            $acumulado['ultimo'] = $sesion->inicio->toIso8601String();

            if ($sesion->fin !== null) {
                $acumulado['minutos'] += (int) round($sesion->inicio->diffInMinutes($sesion->fin));
            }

            $porDron[$dron->id] = $acumulado;
        }

        $equipos = array_map(fn (array $fila) => new EquipoPersonaPanel(
            dronId: $fila['dron']->id,
            identificador: $fila['dron']->identificador,
            modelo: $fila['dron']->modelo,
            sesiones: $fila['sesiones'],
            hectareas: $this->aEscalaDos($fila['hectareas']),
            minutosVuelo: $fila['minutos'],
            ultimoVuelo: $fila['ultimo'],
        ), array_values($porDron));

        usort($equipos, fn (EquipoPersonaPanel $a, EquipoPersonaPanel $b) => $b->sesiones <=> $a->sesiones);

        return $equipos;
    }

    public function totalesDelMesPorPersona(int $personaId): array
    {
        $sesiones = Sesion::query()
            ->whereNull('anulada_en')
            ->whereBetween('inicio', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->where(fn ($consulta) => $consulta->where('piloto_id', $personaId)->orWhere('auxiliar_id', $personaId))
            ->get(['id', 'estado', 'hectareas_declaradas']);

        $hectareas = BigDecimal::zero();
        $validadas = 0;

        foreach ($sesiones as $sesion) {
            if ($sesion->estado === EstadoSesion::Validado) {
                $validadas++;
                $hectareas = $hectareas->plus(BigDecimal::of($sesion->hectareas_declaradas));
            }
        }

        return [
            'sesiones' => $sesiones->count(),
            'hectareas' => $this->aEscalaDos($hectareas),
            'sesionesValidadas' => $validadas,
        ];
    }

    private function aSesionPanel(Sesion $sesion): SesionPanel
    {
        return new SesionPanel(
            id: $sesion->id,
            trabajoId: $sesion->trabajo_id,
            loteId: (int) ($sesion->trabajo->lote_id ?? 0),
            pilotoId: $sesion->piloto_id,
            estado: $sesion->estado->value,
            tono: TonoEstadoSesion::deSesion($sesion->estado, $sesion->anulada_en !== null)->value,
            hectareas: $this->aEscalaDos(BigDecimal::of($sesion->hectareas_declaradas)),
            inicio: $sesion->inicio->toIso8601String(),
            fin: $sesion->fin?->toIso8601String(),
            dronCodigo: $sesion->dron?->identificador,
            tieneCapturaRc: $sesion->captura_rc_id !== null,
            anulada: $sesion->anulada_en !== null,
            litrosConsumidos: $sesion->litros_consumidos !== null
                ? $this->aEscalaDos(BigDecimal::of($sesion->litros_consumidos))
                : null,
            // `diffInMinutes` devuelve float en Carbon 3: el casteo es
            // explícito, no implícito (que además es deprecación en PHP 8.3).
            minutosVuelo: $sesion->fin !== null ? (int) round($sesion->inicio->diffInMinutes($sesion->fin)) : null,
        );
    }

    /**
     * Tono agregado de un lote: el peor estado de sus sesiones manda. Un
     * lote con cinco sesiones validadas y una cerrada sigue teniendo trabajo
     * pendiente, así que se pinta como pendiente — no como completado.
     *
     * @param  list<EstadoSesion>  $estados
     */
    private function tonoAgregado(array $estados): TonoEstadoSesion
    {
        if (in_array(EstadoSesion::Abierto, $estados, true)) {
            return TonoEstadoSesion::Info;
        }

        if (in_array(EstadoSesion::Cerrado, $estados, true)) {
            return TonoEstadoSesion::Warning;
        }

        return $estados === [] ? TonoEstadoSesion::Neutral : TonoEstadoSesion::Success;
    }

    private function aEscalaDos(BigDecimal $valor): string
    {
        return (string) $valor->toScale(2, RoundingMode::HalfUp);
    }
}
