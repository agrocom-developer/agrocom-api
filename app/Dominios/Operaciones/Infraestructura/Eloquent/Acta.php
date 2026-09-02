<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Operaciones\Dominio\EstadoActa;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Acta de conformidad por lote (espec §4.3, tabla `ope_actas`; HU-17, tarea
 * 24). Nace de una acción del piloto/jefe de campo en `agrocom-field` con su
 * `uuid_cliente` propio (invariante 1 de CLAUDE.md) — ver docblock de la
 * migración para el porqué, aunque este endpoint viva fuera de `POST
 * /api/sync`.
 *
 * Las transiciones de `estado` (pendiente → firmada) pasan por
 * `Aplicacion/MaquinaEstados/MaquinaEstadosActa.php` (invariante 7); este
 * modelo no ofrece atajos para mutarlas. `RegistraBitacora`: `estado` es un
 * estado operativo (ADR 0007), misma categoría que `Trabajo`/`Sesion`.
 *
 * `hectareas_conformadas` es un SNAPSHOT tomado al generar el acta, nunca
 * recalculado — a propósito distinto de `Trabajo::hectareas_declaradas`
 * (ese sí es derivado en vivo). Ver runs/24.md.
 *
 * @property int $id
 * @property string $uuid_cliente
 * @property int $trabajo_id
 * @property string $hectareas_conformadas
 * @property string|null $pdf_path
 * @property string|null $firmante
 * @property CarbonImmutable|null $fecha_firma
 * @property int|null $evidencia_firma_id
 * @property string|null $observaciones
 * @property EstadoActa $estado
 */
class Acta extends ModeloDominio
{
    use RegistraBitacora;

    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'ope_actas';

    /** @var list<string> */
    protected $fillable = [
        'uuid_cliente',
        'trabajo_id',
        'hectareas_conformadas',
        'pdf_path',
        'firmante',
        'fecha_firma',
        'evidencia_firma_id',
        'observaciones',
        'estado',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'trabajo_id' => 'integer',
            'hectareas_conformadas' => 'decimal:2',
            'fecha_firma' => 'immutable_datetime',
            'evidencia_firma_id' => 'integer',
            'estado' => EstadoActa::class,
        ];
    }

    /** @return BelongsTo<Trabajo, $this> */
    public function trabajo(): BelongsTo
    {
        return $this->belongsTo(Trabajo::class, 'trabajo_id');
    }

    /** @return BelongsTo<Evidencia, $this> */
    public function evidenciaFirma(): BelongsTo
    {
        return $this->belongsTo(Evidencia::class, 'evidencia_firma_id');
    }
}
