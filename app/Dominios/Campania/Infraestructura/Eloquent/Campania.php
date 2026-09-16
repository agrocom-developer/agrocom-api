<?php

namespace App\Dominios\Campania\Infraestructura\Eloquent;

use App\Dominios\Campania\Dominio\EstadoCampania;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Carbon\CarbonImmutable;

/**
 * Campaña (ADR 0015, corregida el 15/9/2026, tabla `cpn_campanias`):
 * catálogo COMPARTIDO — la temporada ("Verano 2026-2027"), no la de un
 * cliente en particular. El vínculo con el cliente vive en
 * `com_contratos.campania_id` + `com_contratos.cliente_id`, nunca acá.
 * Es el eje transversal del que cuelgan `com_contratos.campania_id` y
 * `fin_gastos.campania_id`. Las transiciones de `estado` pasan por
 * `Aplicacion/MaquinaEstados/MaquinaEstadosCampania` (invariante 7 de
 * CLAUDE.md) y afectan a TODOS los contratos que la usan a la vez.
 *
 * `RegistraBitacora` (invariante 9): el esquema no la marca como catálogo de
 * rol/permiso (no lo exige el gate automático de
 * `tests/Unit/BitacoraAuditoriaTest.php`), pero es una máquina de estados de
 * la que cuelga dinero — mismo criterio adoptado ya en {@see
 * \App\Dominios\Comercial\Infraestructura\Eloquent\Contrato}.
 *
 * @property int $id
 * @property string $codigo
 * @property string|null $nombre
 * @property string $estacion
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
        'codigo',
        'nombre',
        'estacion',
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

    /**
     * "Activa"/"Inactiva" (HU-77, tarea 93): etiqueta de presentación pedida
     * por el dueño para el panel — nunca una columna propia. Deriva de
     * `estado`: `planificada`/`abierta` son actividad en curso o por venir,
     * `cerrada` es terminal (ADR 0015 punto 1) y no vuelve atrás.
     */
    public function esActiva(): bool
    {
        return $this->estado !== EstadoCampania::Cerrada;
    }
}
