<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;

/**
 * Condiciones de vuelo (espec §4.3, tabla ope_condiciones; HU-06, tarea 17).
 * Nace en la app de campo con su `uuid_cliente` (invariante 1), escrita por
 * `EscrituraSincronizacionEloquent::registrarCondiciones()` — nunca por un
 * caso de uso ajeno a `Operaciones` (ADR 0003, regla 2).
 *
 * No transiciona: a diferencia de `Sesion`/`Trabajo`, este es un registro de
 * un HECHO puntual (las condiciones medidas en un momento dado), no una
 * entidad con máquina de estados propia — por eso no hay
 * `Aplicacion/MaquinaEstados/` para este modelo ni encaja en la categoría
 * "estado operativo" que amerita `RegistraBitacora` (ver esa decisión en
 * runs/17.md).
 *
 * `autorizado`: ver el docblock de la migración
 * (`2026_09_01_100008_create_ope_condiciones_table.php`) para la semántica
 * completa. `trabajo_id` es redundante con `sesion.trabajo_id`, denormalizado
 * a propósito (la propia espec lo lista como columna de `condiciones`).
 *
 * @property int $id
 * @property string $uuid_cliente
 * @property int $trabajo_id
 * @property int $sesion_id
 * @property string $momento
 * @property string $viento_kmh
 * @property string $temperatura_c
 * @property string $humedad_pct
 * @property bool $autorizado
 * @property string|null $observacion_agronomo
 * @property string|null $firma_observacion
 */
class Condiciones extends ModeloDominio
{
    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'ope_condiciones';

    /** @var list<string> */
    protected $fillable = [
        'uuid_cliente',
        'trabajo_id',
        'sesion_id',
        'momento',
        'viento_kmh',
        'temperatura_c',
        'humedad_pct',
        'autorizado',
        'observacion_agronomo',
        'firma_observacion',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'viento_kmh' => 'decimal:2',
            'temperatura_c' => 'decimal:2',
            'humedad_pct' => 'decimal:2',
            'autorizado' => 'boolean',
        ];
    }

    /**
     * Proyección de lectura de la etiqueta de la espec §5 (mismo criterio que
     * `Trabajo::estadoTablero()`, tarea 15: no se agrega una columna nueva
     * para algo derivable de las que ya existen).
     */
    public function resultado(): string
    {
        return $this->autorizado ? 'autorizado' : 'autorizado_con_observacion';
    }
}
