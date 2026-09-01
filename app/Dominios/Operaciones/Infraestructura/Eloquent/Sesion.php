<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use Carbon\CarbonImmutable;

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
 * `validado_por`/`fecha_validacion`/`motivo_cierre` (HU-05/HU-14).
 *
 * Las transiciones de `estado` (abierto → cerrado) pasan por
 * `Aplicacion/MaquinaEstados/MaquinaEstadosSesion.php` (invariante 7); este
 * modelo no ofrece atajos para mutarlas. `RegistraBitacora`: `estado` es un
 * estado operativo (ADR 0007), misma categoría que `ope_ordenes_aplicacion`.
 *
 * @property int $id
 * @property string $uuid_cliente
 * @property int $trabajo_id
 * @property int $secuencia
 * @property int $piloto_id
 * @property int|null $auxiliar_id
 * @property string $hectareas_declaradas
 * @property EstadoSesion $estado
 * @property CarbonImmutable $inicio
 * @property CarbonImmutable|null $fin
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
        'hectareas_declaradas',
        'estado',
        'inicio',
        'fin',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'secuencia' => 'integer',
            'hectareas_declaradas' => 'decimal:2',
            'estado' => EstadoSesion::class,
            'inicio' => 'immutable_datetime',
            'fin' => 'immutable_datetime',
        ];
    }
}
