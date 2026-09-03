<?php

namespace App\Dominios\Finanzas\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Support\Carbon;

/**
 * Anticipo de una persona (espec Sprint 8 §192, tabla `fin_anticipos`; HU-29,
 * tarea 41): "como encargado, quiero registrar anticipos validando el tope,
 * para no adelantar más de lo devengado". Lo crea únicamente
 * `Finanzas/Aplicacion/RegistrarAnticipo.php`, contra el tope calculado por
 * `Finanzas/Aplicacion/CalcularDisponibleAnticipo.php`.
 *
 * `persona_id` referencia `per_personas` solo por FK + entero plano (ADR
 * 0003, regla 3) — sin `belongsTo` cross-módulo, mismo criterio que
 * `DevengoPersonal`.
 *
 * Inmutable salvo baja (invariante de esta tarea, documentada en
 * `Aplicacion/RegistrarAnticipo`): no hay caso de uso de edición de `monto`
 * ni `fecha`.
 *
 * `RegistraBitacora` (invariante 9 de CLAUDE.md): mismo criterio que
 * `DevengoPersonal` — es dinero, se declara el trait acá explícitamente.
 *
 * @property int $id
 * @property int $persona_id
 * @property string $monto
 * @property Carbon $fecha
 * @property string|null $motivo
 */
class Anticipo extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'fin_anticipos';

    /** @var list<string> */
    protected $fillable = [
        'persona_id',
        'monto',
        'fecha',
        'motivo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha' => 'date',
        ];
    }
}
