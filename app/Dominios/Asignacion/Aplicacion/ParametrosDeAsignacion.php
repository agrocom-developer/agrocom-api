<?php

namespace App\Dominios\Asignacion\Aplicacion;

use App\Dominios\Asignacion\Dominio\PlanDeRondas;

/**
 * Los parámetros del motor (ADR 0016 y 0038), leídos de `config/asignacion.php`
 * cada vez que se piden: un valor cambiado en la configuración rige desde la
 * siguiente oferta, sin reiniciar nada.
 */
final class ParametrosDeAsignacion
{
    public function ventanaOfertaMinutos(bool $urgente): int
    {
        return max(1, (int) config($urgente ? 'asignacion.ventana_oferta_urgente_min' : 'asignacion.ventana_oferta_programada_min'));
    }

    public function maxProveedoresPorRonda(): int
    {
        return max(1, (int) config('asignacion.max_proveedores_por_ronda'));
    }

    public function plan(): PlanDeRondas
    {
        return new PlanDeRondas(array_values(array_map('strval', (array) config('asignacion.radios_km'))));
    }

    /** @return array{cercania: string, calificacion: string, aceptacion: string, cancelacion: string} */
    public function pesos(): array
    {
        return [
            'cercania' => (string) config('asignacion.pesos.cercania'),
            'calificacion' => (string) config('asignacion.pesos.calificacion'),
            'aceptacion' => (string) config('asignacion.pesos.aceptacion'),
            'cancelacion' => (string) config('asignacion.pesos.cancelacion'),
        ];
    }

    public function calificacionNeutra(): string
    {
        return (string) config('asignacion.calificacion_neutra');
    }

    public function exigirEnLineaProgramada(): bool
    {
        return (bool) config('asignacion.exigir_en_linea_programada');
    }

    public function capacidadMinimaTanqueL(): string
    {
        return (string) config('asignacion.capacidad_minima_tanque_l');
    }

    public function umbralCalificacionParaOfertas(): string
    {
        return (string) config('asignacion.calificacion.restriccion_inmediata_bajo');
    }

    public function minimoCalificaciones(): int
    {
        return (int) config('asignacion.calificacion.minimo_conteo');
    }

    public function umbralAceptacion(): string
    {
        return (string) config('asignacion.aceptacion.umbral');
    }

    public function comisionExternoPorcentaje(): string
    {
        return (string) config('asignacion.comision_porcentaje_externo');
    }

    /** @return array{minimo_conteo: int, advertencia_bajo: string, restriccion_tras_plazo_bajo: string, restriccion_inmediata_bajo: string, plazo_mejora_dias: int} */
    public function calificacion(): array
    {
        return [
            'minimo_conteo' => (int) config('asignacion.calificacion.minimo_conteo'),
            'advertencia_bajo' => (string) config('asignacion.calificacion.advertencia_bajo'),
            'restriccion_tras_plazo_bajo' => (string) config('asignacion.calificacion.restriccion_tras_plazo_bajo'),
            'restriccion_inmediata_bajo' => (string) config('asignacion.calificacion.restriccion_inmediata_bajo'),
            'plazo_mejora_dias' => (int) config('asignacion.calificacion.plazo_mejora_dias'),
        ];
    }

    /** @return array{advertencia_desde: int, restriccion_desde: int, restriccion_dias: int} */
    public function cancelacion(): array
    {
        return [
            'advertencia_desde' => (int) config('asignacion.cancelacion.advertencia_desde'),
            'restriccion_desde' => (int) config('asignacion.cancelacion.restriccion_desde'),
            'restriccion_dias' => (int) config('asignacion.cancelacion.restriccion_dias'),
        ];
    }

    public function ventanaCancelacionDias(): int
    {
        return (int) config('asignacion.cancelacion.ventana_dias');
    }

    public function reincidenciaDias(): int
    {
        return (int) config('asignacion.cancelacion.reincidencia_dias');
    }

    public function horasCancelacionTardia(): int
    {
        return (int) config('asignacion.cancelacion.horas_tardia');
    }

    public function pesoCancelacionTardia(): int
    {
        return (int) config('asignacion.cancelacion.peso_tardia');
    }

    /** @return array{umbral: string, minimo_ofertas: int} */
    public function aceptacion(): array
    {
        return [
            'umbral' => (string) config('asignacion.aceptacion.umbral'),
            'minimo_ofertas' => (int) config('asignacion.aceptacion.minimo_ofertas'),
        ];
    }

    public function ventanaAceptacionDias(): int
    {
        return (int) config('asignacion.aceptacion.ventana_dias');
    }
}
