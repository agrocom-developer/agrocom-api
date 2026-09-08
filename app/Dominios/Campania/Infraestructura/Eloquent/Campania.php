<?php

namespace App\Dominios\Campania\Infraestructura\Eloquent;

use App\Dominios\Campania\Dominio\EstadoCampania;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Carbon\CarbonImmutable;

/**
 * Campaña **del cliente** (ADR 0015 punto 1, corregido el 8/9/2026, tabla
 * `cpn_campanias`): el eje transversal del que cuelgan
 * `com_contratos.campania_id` y `fin_gastos.campania_id`. Las transiciones de
 * `estado` pasan por `Aplicacion/MaquinaEstados/MaquinaEstadosCampania`
 * (invariante 7 de CLAUDE.md).
 *
 * `cliente_id` es FK real + entero plano (ADR 0003 regla 3): sin `belongsTo`
 * hacia `Comercial\Infraestructura\Eloquent\Cliente`, que es de otro módulo.
 *
 * `RegistraBitacora` (invariante 9): el esquema no la marca como catálogo de
 * rol/permiso (no lo exige el gate automático de
 * `tests/Unit/BitacoraAuditoriaTest.php`), pero es una máquina de estados de
 * la que cuelga dinero — mismo criterio adoptado ya en {@see
 * \App\Dominios\Comercial\Infraestructura\Eloquent\Contrato}.
 *
 * @property int $id
 * @property int $cliente_id
 * @property string $codigo
 * @property string|null $nombre
 * @property CarbonImmutable $fecha_inicio
 * @property CarbonImmutable $fecha_fin
 * @property EstadoCampania $estado
 */
class Campania extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'cpn_campanias';

    /** @var list<string> */
    protected $fillable = [
        'cliente_id',
        'codigo',
        'nombre',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'immutable_date',
            'fecha_fin' => 'immutable_date',
            'estado' => EstadoCampania::class,
        ];
    }
}
