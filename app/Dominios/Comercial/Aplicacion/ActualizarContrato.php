<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Campania\Dominio\Excepciones\CampaniaCerradaNoAdmiteImputaciones;
use App\Dominios\Comercial\Dominio\Excepciones\CampaniaDeOtroCliente;
use App\Dominios\Comercial\Dominio\Excepciones\VentanasContratoSolapadas;
use App\Dominios\Comercial\Dominio\ValidadorSolapamientoVentanas;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\ContratoVentana;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Edición de un contrato con sus ventanas horarias en una sola operación
 * (HU-23, tarea 34): mismo criterio que `ActualizarCliente` — una sola
 * transacción para contrato y ventanas.
 *
 * El set de ventanas recibido es el COMPLETO y definitivo, no un delta: las
 * que faltan respecto a las actuales se dan de baja (soft delete), las que
 * traen `id` se actualizan y las que no traen `id` se crean — mismo criterio
 * que `ActualizarCliente::sincronizarContactos`.
 *
 * `estado` nunca viaja en `$datosContrato` (invariante 7): esta clase no lo
 * toca — el cambio de estado es responsabilidad exclusiva de
 * `Aplicacion/CambiarEstadoContrato`.
 *
 * Misma guarda de campaña que `CrearContrato` (ADR 0015 punto 1, corregido
 * el 8/9/2026): la campaña elegida tiene que ser del mismo cliente y no
 * puede estar `cerrada` — ver ese docblock para el criterio de lectura vía
 * `DB::table`.
 */
final class ActualizarContrato
{
    /**
     * @param  array<string, mixed>  $datosContrato  sin `estado` ni `monto_total`: este último lo recalcula esta clase.
     * @param  list<array{id: int|null, hora_inicio: string, hora_fin: string}>  $ventanas  set completo y definitivo
     *
     * @throws VentanasContratoSolapadas si dos ventanas del set final se solapan entre sí.
     * @throws CampaniaDeOtroCliente si la campaña elegida no es del cliente del contrato.
     * @throws CampaniaCerradaNoAdmiteImputaciones si la campaña elegida está `cerrada`.
     */
    public function ejecutar(Contrato $contrato, array $datosContrato, array $ventanas): Contrato
    {
        $this->verificarCampania((int) $datosContrato['cliente_id'], (int) $datosContrato['campania_id']);

        $solapamiento = ValidadorSolapamientoVentanas::primerSolapamiento($ventanas);

        if ($solapamiento !== null) {
            [$a, $b] = $solapamiento;

            throw VentanasContratoSolapadas::entre($a['hora_inicio'], $a['hora_fin'], $b['hora_inicio'], $b['hora_fin']);
        }

        return DB::transaction(function () use ($contrato, $datosContrato, $ventanas): Contrato {
            $datosContrato['monto_total'] = $this->calcularMontoTotal(
                (string) $datosContrato['hectareas_contratadas'],
                (int) $datosContrato['aplicaciones_previstas'],
                (string) $datosContrato['precio_ha'],
            );

            $contrato->fill($datosContrato);
            $contrato->save();

            $this->sincronizarVentanas($contrato, $ventanas);

            return $contrato->refresh();
        });
    }

    /** @param  list<array{id: int|null, hora_inicio: string, hora_fin: string}>  $ventanas */
    private function sincronizarVentanas(Contrato $contrato, array $ventanas): void
    {
        $idsEnviados = array_values(array_filter(array_column($ventanas, 'id')));

        // `whereNotIn` con un array vacío no excluye nada (Laravel: "verdadero
        // para toda fila"): si ninguna ventana enviada trae `id`, esto da de
        // baja a todas las actuales — correcto, el set enviado las reemplaza
        // por completo.
        $contrato->ventanas()
            ->whereNotIn('id', $idsEnviados)
            ->get()
            ->each(function (ContratoVentana $ventana): void {
                $usuarioId = Auth::id();

                if ($usuarioId !== null) {
                    $ventana->updated_by = (int) $usuarioId;
                    $ventana->save();
                }

                $ventana->delete();
            });

        foreach ($ventanas as $datos) {
            $id = $datos['id'];
            unset($datos['id']);

            $ventana = $id !== null
                ? $contrato->ventanas()->whereKey($id)->firstOrFail()
                : new ContratoVentana(['contrato_id' => $contrato->id]);

            $ventana->fill($datos);
            $ventana->save();
        }
    }

    /**
     * @throws CampaniaDeOtroCliente si la campaña elegida no es del cliente del contrato.
     * @throws CampaniaCerradaNoAdmiteImputaciones si la campaña elegida está `cerrada`.
     */
    private function verificarCampania(int $clienteId, int $campaniaId): void
    {
        $campania = DB::table('cpn_campanias')->where('id', $campaniaId)->first();

        if ($campania === null) {
            return;
        }

        if ((int) $campania->cliente_id !== $clienteId) {
            throw CampaniaDeOtroCliente::paraCampania($campania->codigo);
        }

        if ($campania->estado === 'cerrada') {
            throw CampaniaCerradaNoAdmiteImputaciones::paraCampania($campania->codigo);
        }
    }

    /**
     * Mismo cálculo que `CrearContrato::calcularMontoTotal()` — `monto_total`
     * se recalcula siempre desde los tres factores de origen (invariante 6),
     * nunca se acepta el valor que venga en el formulario.
     */
    private function calcularMontoTotal(string $hectareas, int $aplicaciones, string $precioHa): string
    {
        return (string) BigDecimal::of($hectareas)
            ->multipliedBy($aplicaciones)
            ->multipliedBy($precioHa)
            ->toScale(2, RoundingMode::HalfUp);
    }
}
