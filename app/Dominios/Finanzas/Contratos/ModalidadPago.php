<?php

namespace App\Dominios\Finanzas\Contratos;

/**
 * Cómo se paga un trabajo al personal de campo (ADR 0023, 22/9/2026): por
 * jornal —un monto por cada día trabajado, sin importar las hectáreas— o por
 * hectárea aplicada. Vive en `Contratos/` y no en `Dominio/` porque
 * Operaciones la guarda en la condición de pago de cada equipo de la Orden
 * de Trabajo (`ope_orden_trabajo_equipos.modalidad_pago`), y entre módulos
 * solo se viaja por contratos (ADR 0003).
 */
enum ModalidadPago: string
{
    case PorDia = 'por_dia';
    case PorHa = 'por_ha';

    /** @return array<string, string> Valor => etiqueta, para selects. */
    public static function opciones(): array
    {
        $opciones = [];

        foreach (self::cases() as $modalidad) {
            $opciones[$modalidad->value] = $modalidad->etiqueta();
        }

        return $opciones;
    }

    public function etiqueta(): string
    {
        return __('finanzas.modalidades.'.$this->value);
    }
}
