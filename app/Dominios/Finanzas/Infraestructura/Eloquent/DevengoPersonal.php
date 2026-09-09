<?php

namespace App\Dominios\Finanzas\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Support\Carbon;

/**
 * Devengo de una persona por una sesión (espec §4.4, tabla
 * `devengos_personal`; HU-16, tarea 16): "se calcula por sesión, no por
 * lote... se genera automáticamente al validar un trabajo, nunca al
 * cerrarlo" (invariante 3 de CLAUDE.md). Lo crea únicamente
 * `Finanzas/Aplicacion/GenerarDevengosSesion.php`, en respuesta al evento
 * `App\Dominios\Operaciones\Contratos\Eventos\SesionValidada`.
 *
 * `sesion_id`/`persona_id` referencian `ope_sesiones`/`per_personas` solo por
 * FK + entero plano (ADR 0003, regla 3) — sin `belongsTo` cross-módulo.
 *
 * `hectareas`/`tarifa_ha`/`monto` son una copia congelada al momento de
 * validar (ver el docblock de la migración `create_fin_devengos_personal_table`),
 * no una referencia recalculable contra el estado actual de `per_personas`
 * ni de `ope_sesiones`.
 *
 * `RegistraBitacora` (invariante 9 de CLAUDE.md): `tests/Unit/BitacoraAuditoriaTest.php`
 * (aduana desde la tarea 06) todavía no detecta "dinero" como señal
 * automática — su propio comentario deja esa categoría diferida
 * explícitamente a "la tarea que implemente la máquina de
 * estados/devengos" (ADR 0007, nota 31/8/2026). Esta es esa tarea, y este
 * modelo es, literalmente, el que crea dinero: se declara el trait acá, en
 * el modelo nuevo que le corresponde, sin extender la aduana automática a
 * TODAS las tablas de dinero del esquema (`com_contratos`, `com_lotes`
 * incluidas) — ese es un cambio de alcance mucho mayor, fuera de lo que esta
 * tarea puede tocar, y queda anotado en runs/16.md para no perderse.
 *
 * @property int $id
 * @property int $sesion_id
 * @property int $persona_id
 * @property string $hectareas
 * @property string $tarifa_ha
 * @property string $monto
 * @property Carbon $fecha
 */
class DevengoPersonal extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'fin_devengos_personal';

    /** @var list<string> */
    protected $fillable = [
        'sesion_id',
        'persona_id',
        'hectareas',
        'tarifa_ha',
        'monto',
        'fecha',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hectareas' => 'decimal:2',
            'tarifa_ha' => 'decimal:2',
            'monto' => 'decimal:2',
            'fecha' => 'date',
        ];
    }
}
