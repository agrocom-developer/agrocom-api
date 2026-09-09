<?php

namespace App\Dominios\Operaciones\Aplicacion\MaquinaEstados;

use App\Dominios\Operaciones\Dominio\EstadoActa;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionActaNoPermitida;
use App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesActa;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Acta;
use Carbon\CarbonImmutable;

/**
 * Única clase que crea/muta el `estado` de `acta` (invariante 7 de
 * CLAUDE.md), mismo criterio que `MaquinaEstadosTrabajo`.
 *
 * Las guardas de NEGOCIO (¿el trabajo está cerrado?, ¿la evidencia de firma
 * existe y es del tipo correcto?, ¿ya hay un acta para este trabajo?) no
 * viven acá: esta clase solo aplica la transición o la rechaza contra
 * {@see TransicionesActa}. Esas guardas viven en
 * `Aplicacion/GenerarActaTrabajo` y `Aplicacion/FirmarActa`, que invocan
 * esta clase recién después de decidir que corresponde — mismo reparto de
 * responsabilidad que `EscrituraSincronizacionEloquent::cerrarTrabajo()`
 * frente a `MaquinaEstadosTrabajo::cerrar()`.
 */
final class MaquinaEstadosActa
{
    /**
     * @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase.
     */
    public function generar(array $atributos): Acta
    {
        return Acta::create([...$atributos, 'estado' => EstadoActa::Pendiente]);
    }

    /**
     * @throws TransicionActaNoPermitida si `$acta` no está `pendiente`.
     */
    public function firmar(Acta $acta, int $evidenciaFirmaId, string $firmante, string $fechaFirma): Acta
    {
        $desde = $acta->estado;
        $hasta = EstadoActa::Firmada;

        if (! TransicionesActa::permitida($desde, $hasta)) {
            throw TransicionActaNoPermitida::entre($desde, $hasta);
        }

        $acta->estado = $hasta;
        $acta->evidencia_firma_id = $evidenciaFirmaId;
        $acta->firmante = $firmante;
        // `->utc()`: `CarbonImmutable::parse()` conserva el offset original
        // del string (p. ej. `-04:00`) como huso horario del objeto, no lo
        // normaliza — y la columna `fecha_firma` es `datetime` sin tz. Sin
        // este `->utc()`, `format()` escribiría la hora local literal
        // ("16:30:00") y una relectura posterior la interpretaría como UTC,
        // corriendo el instante real por el valor del offset (hallazgo
        // propio de esta tarea, ver runs/24.md).
        $acta->fecha_firma = CarbonImmutable::parse($fechaFirma)->utc();
        $acta->save();

        return $acta;
    }
}
