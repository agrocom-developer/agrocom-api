<?php

namespace App\Dominios\Comercial\Contratos;

/**
 * Frontera de lectura de Comercial hacia el dashboard del panel (ADR 0003,
 * regla 2; tarea 67). `Operaciones` sabe cuántas sesiones y hectáreas lleva
 * cada `lote_id`, pero no cómo se llama ese lote ni dónde queda: eso es de
 * este módulo, y viaja por acá.
 */
interface LecturaPanelComercial
{
    /**
     * Lotes vigentes indexados por id, para que el consumidor cruce contra
     * los agregados de Operaciones sin un bucle de búsquedas.
     *
     * @return array<int, LotePanel>
     */
    public function lotesPorId(): array;

    /**
     * Centro geográfico de los lotes con geometría cargada — el promedio de
     * sus vértices. El mapa arranca donde de verdad está la operación en vez
     * de en una coordenada fija; `null` si ningún lote tiene perímetro, y
     * ahí el consumidor decide (hoy: no dibuja el mapa).
     *
     * @return array{lat: float, lng: float}|null
     */
    public function centroOperativo(): ?array;

    /**
     * Avance por contrato, del más atrasado al más adelantado: lo que le
     * importa al dueño es qué contrato no va a llegar, no cuál ya cerró.
     *
     * @return list<AvanceClientePanel>
     */
    public function avancePorCliente(int $limite): array;

    /**
     * Estado de cuenta por contrato (contratado, facturado, adelanto y
     * saldo pendiente de facturar), del saldo más alto al más bajo: la
     * plata sin cobrar primero.
     *
     * @return list<EstadoCuentaContratoPanel>
     */
    public function estadoDeCuentas(int $limite): array;
}
