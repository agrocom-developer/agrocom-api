<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Operaciones\Dominio\CausaPausa;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pausa de sesión con causa atribuible (espec DS-01; HU-44, tarea 58): "como
 * jefe de campo, quiero registrar las pausas con su causa atribuible, para
 * saber qué tiempo se pierde y por qué". Alta manual desde el panel — la
 * crea únicamente `Aplicacion/RegistrarPausa`, nunca el motor de sync (ver
 * docblock de la migración).
 *
 * No transiciona: registra un HECHO puntual (mismo criterio que
 * `Condiciones`/`Incidencia`), no una entidad con máquina de estados propia
 * — sin `RegistraBitacora` (no es dinero, roles/permisos ni estado
 * operativo; `tests/Unit/BitacoraAuditoriaTest.php` no la exige).
 *
 * `sesion` SÍ es un `belongsTo` legítimo: `Sesion` vive en el mismo módulo
 * `Operaciones` (ADR 0003 regla 2 solo prohíbe cruzar hacia otro módulo).
 *
 * @property int $id
 * @property int $sesion_id
 * @property CausaPausa $causa
 * @property CarbonImmutable $inicio
 * @property CarbonImmutable $fin
 * @property int $duracion_minutos
 */
class Pausa extends ModeloDominio
{
    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'ope_pausas';

    /** @var list<string> */
    protected $fillable = [
        'sesion_id',
        'causa',
        'inicio',
        'fin',
        'duracion_minutos',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'causa' => CausaPausa::class,
            'inicio' => 'immutable_datetime',
            'fin' => 'immutable_datetime',
            'duracion_minutos' => 'integer',
        ];
    }

    /** @return BelongsTo<Sesion, $this> */
    public function sesion(): BelongsTo
    {
        return $this->belongsTo(Sesion::class, 'sesion_id');
    }
}
