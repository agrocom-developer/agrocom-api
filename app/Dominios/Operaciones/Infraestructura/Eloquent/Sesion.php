<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Sesión (espec §4.3, tabla ope_sesiones; TE-05): unidad de trabajo continua
 * de un piloto con un dron. Nace en la app de campo con su `uuid_cliente` y
 * referencia su `trabajo` — que puede haber llegado en el mismo lote de sync
 * y todavía no tener id de servidor al momento de armar el payload — por el
 * `uuid_cliente` de ese trabajo (espec §2.1 punto 5); esa resolución es
 * responsabilidad del caso de uso de `Sincronizacion`, no de este modelo.
 *
 * `piloto_id`/`auxiliar_id` referencian `per_personas` (módulo `Personal`)
 * solo por FK + entero plano (ADR 0003, regla 3).
 *
 * Recorte de alcance de la tarea 09: sin `dron_id` (ADR 0011 punto 3, tabla
 * `drones` inexistente) ni `captura_rc_id` (evidencias, TE-07). Sin
 * `validado_por`/`fecha_validacion` (HU-14).
 *
 * Las transiciones de `estado` (abierto → cerrado) pasan por
 * `Aplicacion/MaquinaEstados/MaquinaEstadosSesion.php` (invariante 7); este
 * modelo no ofrece atajos para mutarlas. `RegistraBitacora`: `estado` es un
 * estado operativo (ADR 0007), misma categoría que `ope_ordenes_aplicacion`.
 *
 * `motivo_cierre` (HU-05, tarea 13): catálogo de la espec §4.3, columna
 * simple sin la lógica de relevo de HU-07. `cierre_uuid_cliente`:
 * `uuid_cliente` del EVENTO de cierre, distinto del de apertura — mecanismo
 * de idempotencia documentado en runs/13.md.
 *
 * `litros_consumidos` (espec §7.2, HU-10 redefinida por CR-01, tarea 18):
 * declarado al cerrar la sesión, opcional (`null` si el cierre no lo trae —
 * ver `Contratos/CierreSesion`). Junto con `Trabajo::$litros_sobrante` y
 * `RecepcionCaldo::$litros`, alimenta `Trabajo::cuadreCaldo()`.
 *
 * `validado_por`/`fecha_validacion` (HU-14, tarea 14): quién y cuándo
 * aprobó la sesión — únicas columnas nuevas que escribe la transición
 * `cerrado → validado`. `anulada_en`: marca no-de-negocio de que esta sesión
 * fue RECHAZADA (ver `App\Dominios\Operaciones\Infraestructura\Eloquent\SesionRechazo`,
 * runs/14.md) — deliberadamente NO es un `estado`: la invariante 2 de
 * CLAUDE.md prohíbe sobrescribir lo ya registrado, así que un rechazo nunca
 * pisa `estado`/`motivo_cierre`/`hectareas_declaradas`/etc. de esta fila.
 *
 * `dron_id`/`hectarea_inicial_acumulada` (HU-07, tarea 20): relevo de piloto
 * y cambio de dron. `dron_id` referencia `ope_drones` (catálogo mínimo,
 * mismo módulo — ver `Dron`) por FK + entero plano, NULLABLE (a diferencia
 * de `piloto_id`, ver runs/20.md). `hectarea_inicial_acumulada`: el
 * acumulado de DJI al momento de abrir esta sesión (control de doble
 * conteo, espec §5) — dato de trazabilidad; el servidor no recalcula
 * `hectareas_declaradas` a partir de él, esa diferencia ya la trae calculada
 * el dispositivo en `CierreSesion::$hectareasDeclaradas`.
 *
 * @property int $id
 * @property string $uuid_cliente
 * @property int $trabajo_id
 * @property int $secuencia
 * @property int $piloto_id
 * @property int|null $auxiliar_id
 * @property int|null $dron_id
 * @property string $hectareas_declaradas
 * @property string|null $hectarea_inicial_acumulada
 * @property EstadoSesion $estado
 * @property CarbonImmutable $inicio
 * @property CarbonImmutable|null $fin
 * @property string|null $motivo_cierre
 * @property string|null $cierre_uuid_cliente
 * @property int|null $validado_por
 * @property CarbonImmutable|null $fecha_validacion
 * @property CarbonImmutable|null $anulada_en
 * @property string|null $litros_consumidos
 */
class Sesion extends ModeloDominio
{
    use RegistraBitacora;

    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'ope_sesiones';

    /** @var list<string> */
    protected $fillable = [
        'uuid_cliente',
        'trabajo_id',
        'secuencia',
        'piloto_id',
        'auxiliar_id',
        'dron_id',
        'hectareas_declaradas',
        'hectarea_inicial_acumulada',
        'estado',
        'inicio',
        'fin',
        'motivo_cierre',
        'cierre_uuid_cliente',
        'validado_por',
        'fecha_validacion',
        'anulada_en',
        'litros_consumidos',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'secuencia' => 'integer',
            'hectareas_declaradas' => 'decimal:2',
            'hectarea_inicial_acumulada' => 'decimal:2',
            'estado' => EstadoSesion::class,
            'inicio' => 'immutable_datetime',
            'fin' => 'immutable_datetime',
            'fecha_validacion' => 'immutable_datetime',
            'anulada_en' => 'immutable_datetime',
            'litros_consumidos' => 'decimal:2',
        ];
    }

    /**
     * La corrección que anuló esta sesión, si la hay (HU-14: inversa de
     * `SesionRechazo::sesion()`). Solo lectura, para mostrar el motivo del
     * rechazo en el detalle de HU-15 — nunca se usa para decidir nada acá
     * (esa lógica vive en `Aplicacion/RechazarSesion.php`).
     *
     * @return HasOne<SesionRechazo, $this>
     */
    public function rechazo(): HasOne
    {
        return $this->hasOne(SesionRechazo::class, 'anula_a_id');
    }

    /**
     * Incidencias registradas durante esta sesión (HU-08, tarea 22): a
     * diferencia de `Condiciones` (denormaliza `trabajo_id`), `Incidencia`
     * solo referencia `sesion_id` — se recolectan por sesión, no por trabajo
     * directo (ver `ArmarContenidoReporteTecnico`).
     *
     * @return HasMany<Incidencia, $this>
     */
    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class, 'sesion_id');
    }
}
