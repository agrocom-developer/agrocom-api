<?php

namespace Database\Seeders\Demo;

use App\Dominios\Finanzas\Aplicacion\CrearTarifa;
use App\Dominios\Finanzas\Aplicacion\DatosTarifa;
use App\Dominios\Finanzas\Contratos\ModalidadPago;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Tarifa;
use Illuminate\Database\Seeder;

/**
 * Tarifas base de pago al personal (ADR 0023): la predeterminada, que toda
 * Orden de Trabajo copia si el encargado no negocia otra, y dos más para que
 * el selector tenga de dónde elegir.
 *
 * Va ANTES de {@see OperacionDemoSeeder}: `CrearOrdenTrabajo` exige una
 * tarifa predeterminada y sin ella la operación entera se salteaba en
 * silencio (hallazgo del 22/9/2026 sobre el compose del dueño). Vivía en
 * {@see FinanzasDemoSeeder}, que corre después de la operación.
 *
 * Idempotente por nombre; la predeterminada solo se marca si todavía no hay
 * ninguna, así no se pisa la que el dueño haya elegido.
 */
final class TarifasDemoSeeder extends Seeder
{
    public function __construct(private readonly CrearTarifa $crearTarifa) {}

    public function run(): void
    {
        $catalogo = [
            ['Aplicación estándar', ModalidadPago::PorHa, '12.35', '8.00', true, 'Tarifa base por hectárea aplicada; es la que copia cada orden de trabajo salvo negociación.'],
            ['Cosecha con desecante', ModalidadPago::PorDia, '170.00', '110.00', false, 'Jornada larga: se paga por día con plus por el desecante.'],
            ['Siembra al voleo', ModalidadPago::PorHa, '9.80', '6.50', false, 'Sólidos por hectárea sembrada.'],
        ];

        foreach ($catalogo as [$nombre, $modalidad, $piloto, $auxiliar, $predeterminada, $descripcion]) {
            $existe = Tarifa::query()->whereRaw('lower(nombre) = ?', [mb_strtolower($nombre)])->exists();

            if ($existe) {
                continue;
            }

            $marcarPredeterminada = $predeterminada && ! Tarifa::query()->where('predeterminada', true)->exists();

            $this->crearTarifa->ejecutar(new DatosTarifa($nombre, $modalidad, $piloto, $auxiliar, $marcarPredeterminada, $descripcion));
        }
    }
}
