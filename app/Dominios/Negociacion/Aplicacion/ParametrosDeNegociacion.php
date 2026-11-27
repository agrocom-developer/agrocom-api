<?php

namespace App\Dominios\Negociacion\Aplicacion;

/** Parámetros de la negociación y del seguimiento (`config/negociacion.php`, `config/seguimiento.php`; ADR 0016). Nada de esto es un secreto. */
final class ParametrosDeNegociacion
{
    public function vigenciaOfertaHoras(): int
    {
        return max(1, (int) config('negociacion.vigencia_oferta_horas', 24));
    }

    public function maxOfertas(): int
    {
        return max(1, (int) config('negociacion.max_ofertas_por_negociacion', 5));
    }

    public function precioMaximo(): string
    {
        return (string) config('negociacion.precio_ha_maximo', '10000.0000');
    }

    public function ventanaCancelacionDias(): int
    {
        return max(1, (int) config('negociacion.ventana_cancelacion_dias', 90));
    }

    public function umbralCancelacion(): string
    {
        return (string) config('negociacion.umbral_cancelacion_alerta', '0.30');
    }

    public function plazoCalificacionDias(): int
    {
        return max(1, (int) config('negociacion.plazo_calificacion_dias', 7));
    }

    public function toleranciaHectareasPct(): string
    {
        return (string) config('seguimiento.tolerancia_hectareas_pct', '5');
    }

    public function evidenciasMinimasCierre(): int
    {
        return max(0, (int) config('seguimiento.evidencias_minimas_cierre', 1));
    }

    public function evidenciaMaxKb(): int
    {
        return max(1, (int) config('seguimiento.evidencia_max_kb', 300));
    }

    public function ubicacionCaducaS(): int
    {
        return max(1, (int) config('seguimiento.ubicacion_caduca_s', 300));
    }

    public function inactividadHoras(): int
    {
        return max(1, (int) config('seguimiento.inactividad_horas', 72));
    }
}
