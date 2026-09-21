<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTableroTrabajo;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\Turno;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Trabajo (espec §4.3, tabla ope_trabajos; TE-05). Nace en la app de campo
 * con su `uuid_cliente` (invariante 1 de CLAUDE.md, resuelto por el motor de
 * sync).
 *
 * `orden_id` y `lote_id` referencian tablas de otro módulo (`Operaciones`
 * dueño de `ope_ordenes_aplicacion`, `Comercial` dueño de `com_lotes`) solo
 * por FK + entero plano (ADR 0003, regla 3) — sin relaciones Eloquent
 * cruzadas. `sesiones()`, en cambio, SÍ es una relación Eloquent normal:
 * `Sesion` vive en el mismo módulo (ADR 0003, regla 1).
 *
 * Las transiciones de `estado` (abierto → cerrado) pasan por
 * `Aplicacion/MaquinaEstados/MaquinaEstadosTrabajo.php` (invariante 7); este
 * modelo no ofrece atajos para mutarlas. `RegistraBitacora`: `estado` es un
 * estado operativo (ADR 0007), misma categoría que `ope_ordenes_aplicacion`.
 *
 * `cierre_uuid_cliente` (HU-05, tarea 13): `uuid_cliente` del EVENTO de
 * cierre, distinto del de apertura — mecanismo de idempotencia de una
 * mutación sobre fila existente, documentado en runs/13.md.
 *
 * `litros_sobrante` (espec §7.2, HU-10 redefinida por CR-01, tarea 18):
 * declarado al cerrar el trabajo, opcional (ver `Contratos/CierreTrabajo`).
 * Junto con `recepcionesCaldo()` y `Sesion::$litros_consumidos`, alimenta
 * {@see self::cuadreCaldo()}.
 *
 * `imagen_campo_evidencia_id` (espec §9/§10, HU-09, tarea 21): FK a
 * `ope_evidencias.id`, completada al cerrar el trabajo — sin ella, el cierre
 * se rechaza (ver `EscrituraSincronizacionEloquent::cerrarTrabajo()`). A
 * diferencia de `orden_id`/`lote_id` (otro módulo, FK plano sin relación
 * Eloquent, ADR 0003 regla 3), `Evidencia` vive en el mismo módulo
 * `Operaciones`, así que sí tiene relación Eloquent normal
 * ({@see self::imagenCampoEvidencia()}).
 *
 * `equipo_trabajo_id` (HU-70, tarea 85): equipo al que el jefe de campo le
 * asignó este trabajo desde el panel — `NULL` en los trabajos que nacen por
 * sync (ver `MaquinaEstadosTrabajo::abrir()`) o que existían antes de esta
 * tarea. FK plana a `per_equipos_trabajo` (otro módulo, ADR 0003 regla 3):
 * sin relación Eloquent, se lee por `Personal\Contratos\LecturaEquipoTrabajo`.
 *
 * `orden_trabajo_id` (reforma 18/9/2026, "Orden de Trabajo"/tandas): la tanda
 * a la que pertenece este trabajo — `NULL` en trabajos que nacen por sync sin
 * pasar por `AsignarEquiposOrden`. `OrdenTrabajo` es del mismo módulo →
 * relación Eloquent real (`ordenTrabajo()`, ADR 0003 regla 1), a diferencia
 * de `equipo_trabajo_id` (otro módulo, FK plana).
 *
 * Los 8 campos de límites climáticos y parámetros de vuelo vivieron acá muy
 * brevemente (migración `2026_09_18_100001_mueve_clima_vuelo_de_ordenes_a_trabajos_table`,
 * el mismo día): describían el vuelo de ESE equipo ese día. La reforma
 * `create_ope_ordenes_trabajo_table` (mismo día) los mueve una vez más, esta
 * vez a `OrdenTrabajo` — son compartidos por TODA la tanda (que puede cubrir
 * varios equipos), no por cada `Trabajo` individual. Se leen vía
 * `$trabajo->ordenTrabajo?->humedad_min_pct` etc.
 *
 * `turno`/`turno_hora_inicio`/`turno_hora_fin` (misma reforma): qué turno
 * cubre ESTE equipo en ESTE lote dentro de la tanda — mañana, noche o todo
 * el día, con su horario. Propio de cada fila (mismo nivel que `lote_id`),
 * no de la cabecera. `NULL` en trabajos que nacen por sync sin pasar por
 * `AsignarEquiposOrden`, donde los tres son obligatorios juntos.
 *
 * @property int $id
 * @property string $uuid_cliente
 * @property int $orden_id
 * @property int $lote_id
 * @property int|null $orden_trabajo_id
 * @property int|null $equipo_trabajo_id
 * @property int $nro_aplicacion
 * @property string $hectareas_declaradas
 * @property EstadoTrabajo $estado
 * @property Turno|null $turno
 * @property string|null $turno_hora_inicio
 * @property string|null $turno_hora_fin
 * @property CarbonImmutable $inicio
 * @property CarbonImmutable|null $fin
 * @property string|null $cierre_uuid_cliente
 * @property string|null $litros_sobrante
 * @property int|null $imagen_campo_evidencia_id
 */
class Trabajo extends ModeloDominio
{
    use RegistraBitacora;

    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'ope_trabajos';

    /** @var list<string> */
    protected $fillable = [
        'uuid_cliente',
        'orden_id',
        'lote_id',
        'orden_trabajo_id',
        'equipo_trabajo_id',
        'nro_aplicacion',
        'hectareas_declaradas',
        'estado',
        'turno',
        'turno_hora_inicio',
        'turno_hora_fin',
        'inicio',
        'fin',
        'cierre_uuid_cliente',
        'litros_sobrante',
        'imagen_campo_evidencia_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'nro_aplicacion' => 'integer',
            'orden_trabajo_id' => 'integer',
            'equipo_trabajo_id' => 'integer',
            'hectareas_declaradas' => 'decimal:2',
            'estado' => EstadoTrabajo::class,
            'turno' => Turno::class,
            'turno_hora_inicio' => 'datetime:H:i',
            'turno_hora_fin' => 'datetime:H:i',
            'inicio' => 'immutable_datetime',
            'fin' => 'immutable_datetime',
            'litros_sobrante' => 'decimal:2',
            'imagen_campo_evidencia_id' => 'integer',
        ];
    }

    /** @return BelongsTo<OrdenTrabajo, $this> */
    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_trabajo_id');
    }

    /** @return HasMany<Sesion, $this> */
    public function sesiones(): HasMany
    {
        return $this->hasMany(Sesion::class, 'trabajo_id');
    }

    /** @return BelongsTo<Evidencia, $this> */
    public function imagenCampoEvidencia(): BelongsTo
    {
        return $this->belongsTo(Evidencia::class, 'imagen_campo_evidencia_id');
    }

    /** @return HasMany<RecepcionCaldo, $this> */
    public function recepcionesCaldo(): HasMany
    {
        return $this->hasMany(RecepcionCaldo::class, 'trabajo_id');
    }

    /** @return HasOne<Acta, $this> */
    public function acta(): HasOne
    {
        return $this->hasOne(Acta::class, 'trabajo_id');
    }

    /**
     * Condiciones de vuelo registradas al iniciar cada sesión (HU-06, tarea
     * 17): `trabajo_id` está denormalizado en `ope_condiciones` justo para
     * poder leerlas por trabajo sin pasar por `sesiones` (ver docblock de
     * `Condiciones`). Puede haber más de una fila — una por sesión.
     *
     * @return HasMany<Condiciones, $this>
     */
    public function condiciones(): HasMany
    {
        return $this->hasMany(Condiciones::class, 'trabajo_id');
    }

    /** @return HasOne<ReporteTecnico, $this> */
    public function reporteTecnico(): HasOne
    {
        return $this->hasOne(ReporteTecnico::class, 'trabajo_id');
    }

    /**
     * "Reporte de Equipos" de este trabajo (HU-80, tarea 86): horas de
     * vuelo declaradas y las tres fotos de chequeo. `hasOne` porque
     * `ope_evidencias_equipo.trabajo_id` es `UNIQUE` — un solo registro por
     * trabajo, mismo criterio que `reporteTecnico()`.
     *
     * @return HasOne<EvidenciaEquipo, $this>
     */
    public function evidenciaEquipo(): HasOne
    {
        return $this->hasOne(EvidenciaEquipo::class, 'trabajo_id');
    }

    /**
     * Cuadre de caldo (espec §7.3, criterio de aceptación 4 de la tarea 18):
     * `recibido` (suma de `ope_recepciones_caldo.litros` de este trabajo),
     * `consumido` (suma de `ope_sesiones.litros_consumidos` de sus sesiones,
     * `NULL` cuenta como cero vía `SUM`) y `sobrante` (`litros_sobrante` de
     * este trabajo, cero si no se declaró). Los tres se recalculan desde los
     * registros de origen en cada llamada — nunca un total cacheado
     * (invariante 6 de CLAUDE.md: "todo monto derivado debe poder
     * recalcularse... y cuadrar exacto").
     *
     * `sum()` sin filas devuelve el entero `0` (no `'0.00'`) — se normaliza
     * con `BigDecimal::toScale(2, ...)` para que los tres campos tengan
     * siempre el mismo formato de `DECIMAL`, se hayan sumado filas o no.
     * `cuadra` compara con `Brick\Math\BigDecimal` (no `float`), mismo
     * criterio que `Finanzas\Aplicacion\GenerarDevengosSesion::calcularMonto()`.
     *
     * No bloquea ni valida nada (a propósito, ver el prompt de la tarea 18):
     * el desvío se alerta recién en HU-19 (bandeja de alertas, sprint 5) —
     * acá el dato solo tiene que quedar consultable y ser exacto.
     *
     * @return array{recibido: string, consumido: string, sobrante: string, cuadra: bool}
     */
    public function cuadreCaldo(): array
    {
        // `(string)` antes de `BigDecimal::of()`: `sum()` puede devolver un
        // entero `0` (sin filas) o, según el driver, un número que
        // `BigDecimal::of()` no acepta directo (su firma es
        // `BigNumber|int|string`, sin `float`) — mismo motivo por el que el
        // resto del código castea toda suma de `DECIMAL` a string antes de
        // usarla (ver `EscrituraSincronizacionEloquent::sumaHectareasSesiones()`).
        $recibido = BigDecimal::of((string) $this->recepcionesCaldo()->sum('litros'))->toScale(2);
        $consumido = BigDecimal::of((string) $this->sesiones()->sum('litros_consumidos'))->toScale(2);
        $sobrante = BigDecimal::of($this->litros_sobrante ?? 0)->toScale(2);

        return [
            'recibido' => (string) $recibido,
            'consumido' => (string) $consumido,
            'sobrante' => (string) $sobrante,
            'cuadra' => $recibido->isEqualTo($consumido->plus($sobrante)),
        ];
    }

    /**
     * Estado de tablero (HU-15): "abierto" mientras el trabajo sigue en
     * curso; "cerrado" cuando terminó pero queda alguna sesión vigente (no
     * rechazada) sin aprobar, o no tiene ninguna sesión vigente todavía;
     * "validado" solo cuando TODAS sus sesiones vigentes ya pasaron por
     * HU-14. Requiere `sesiones` cargada (`with('sesiones')`) — no dispara
     * una consulta nueva por trabajo.
     */
    public function estadoTablero(): EstadoTableroTrabajo
    {
        if ($this->estado === EstadoTrabajo::Abierto) {
            return EstadoTableroTrabajo::Abierto;
        }

        $sesionesVigentes = $this->sesiones->whereNull('anulada_en');

        if ($sesionesVigentes->isNotEmpty() && $sesionesVigentes->every(fn (Sesion $sesion): bool => $sesion->estado === EstadoSesion::Validado)) {
            return EstadoTableroTrabajo::Validado;
        }

        return EstadoTableroTrabajo::Cerrado;
    }

    /**
     * Versión SQL de {@see self::estadoTablero()}, para filtrar el listado
     * paginado sin traer todo a PHP (`Aplicacion/ListarTrabajos.php`,
     * HU-15). Misma regla, expresada con `whereHas`/`whereDoesntHave` sobre
     * `sesiones`.
     *
     * @param  Builder<Trabajo>  $query
     * @return Builder<Trabajo>
     */
    public function scopeConEstadoTablero(Builder $query, EstadoTableroTrabajo $estado): Builder
    {
        return match ($estado) {
            EstadoTableroTrabajo::Abierto => $query->where('estado', EstadoTrabajo::Abierto),
            EstadoTableroTrabajo::Validado => $query->where('estado', EstadoTrabajo::Cerrado)
                ->whereHas('sesiones', fn (Builder $sesiones) => $sesiones->whereNull('anulada_en'))
                ->whereDoesntHave('sesiones', fn (Builder $sesiones) => $sesiones->whereNull('anulada_en')->where('estado', '!=', EstadoSesion::Validado)),
            EstadoTableroTrabajo::Cerrado => $query->where('estado', EstadoTrabajo::Cerrado)
                ->where(fn (Builder $subconsulta) => $subconsulta
                    ->whereDoesntHave('sesiones', fn (Builder $sesiones) => $sesiones->whereNull('anulada_en'))
                    ->orWhereHas('sesiones', fn (Builder $sesiones) => $sesiones->whereNull('anulada_en')->where('estado', '!=', EstadoSesion::Validado))),
        };
    }
}
