<?php

namespace App\Dominios\Finanzas\Contratos;

/**
 * Frontera de lectura de Finanzas hacia el dashboard (ADR 0003, regla 2;
 * tarea 67). Hermano de {@see LecturaContadoresPanel}, que devuelve el total
 * del mes para el badge del menú: esto devuelve el detalle que ve la persona
 * en su propio tablero.
 *
 * Siempre acotado a UNA persona: no existe hoy una pantalla de devengos de
 * toda la empresa, y este contrato no la inventa por la ventana.
 */
interface LecturaPanelFinanzas
{
    /**
     * Devengos de la persona en el mes calendario en curso, del más reciente
     * al más viejo, con el total del período ya sumado con `BigDecimal`.
     *
     * @return array{devengos: list<DevengoPanel>, total: string, periodo: string}
     */
    public function devengosDelMes(int $personaId, int $limite): array;

    /**
     * Anticipos que la persona pidió en el mes en curso, con el total y el
     * SALDO contra lo devengado.
     *
     * Va junto a los devengos y no en una sección aparte porque son las dos
     * mitades de la misma frase: lo que ganó y lo que ya cobró a cuenta. Un
     * piloto que ve solo el devengado se lleva una cifra que no es la que va
     * a recibir.
     *
     * @return array{anticipos: list<AnticipoPanel>, total: string, saldo: string}
     */
    public function anticiposDelMes(int $personaId, int $limite): array;
}
