<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Operaciones\Dominio\EstadoAlerta;
use App\Dominios\Operaciones\Dominio\TipoAlerta;
use Carbon\CarbonImmutable;

/**
 * Alerta de la bandeja por excepción (espec §10, HU-19, tarea 26). Generada
 * por `Aplicacion/GenerarAlertaExcepcion.php` en el punto del código donde ya
 * se persiste el dato que la dispara — nunca creada a mano desde un
 * controlador. Referencia mínima al origen con columnas nullable (ver
 * docblock de la migración): qué columnas trae pobladas depende de `tipo`.
 *
 * `RegistraBitacora`: mismo criterio que `Sesion`/`Trabajo` — `estado`
 * transiciona (`pendiente → atendida`, HU-19) y esta tabla entra en la
 * categoría "estados operativos" del ADR 0007, aunque el gate automático de
 * `tests/Unit/BitacoraAuditoriaTest.php` (que solo cubre roles/permisos hoy)
 * no la exija.
 *
 * @property int $id
 * @property TipoAlerta $tipo
 * @property int|null $trabajo_id
 * @property int|null $sesion_id
 * @property int|null $recarga_id
 * @property int|null $condiciones_id
 * @property int|null $dron_id
 * @property string $mensaje
 * @property EstadoAlerta $estado
 * @property int|null $atendida_por
 * @property CarbonImmutable|null $atendida_en
 */
class Alerta extends ModeloDominio
{
    use RegistraBitacora;

    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'ope_alertas';

    /** @var list<string> */
    protected $fillable = [
        'tipo',
        'trabajo_id',
        'sesion_id',
        'recarga_id',
        'condiciones_id',
        'dron_id',
        'mensaje',
        'estado',
        'atendida_por',
        'atendida_en',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoAlerta::class,
            'estado' => EstadoAlerta::class,
            'atendida_en' => 'immutable_datetime',
        ];
    }
}
