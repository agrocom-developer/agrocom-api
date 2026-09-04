<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de Operaciones hacia otros módulos (ADR 0003, regla 2):
 * `Comercial\Aplicacion\EmitirFactura` (HU-31, tarea 45) necesita, de un acta
 * ajena, si está firmada, sus hectáreas conformadas y el contrato al que
 * pertenece — sin importar `Acta`, `Trabajo` ni `OrdenAplicacion`. Resuelve
 * la cadena `Acta.trabajo_id → Trabajo.orden_id → OrdenAplicacion.contrato_id`
 * puertas adentro de este módulo.
 *
 * Tres métodos: `obtenerPorActaId` resuelve la emisión puntual (el caso de
 * uso ya conoce el `acta_id` elegido); `listarFirmadas` alimenta la pantalla
 * de alta, que necesita ofrecer un selector de "actas firmadas" antes de
 * intentar emitir. A propósito NO se llama `listarFirmadasSinFacturar`:
 * cuáles de esas actas ya tienen factura es un dato de `com_facturas`, que
 * este módulo no conoce ni debe conocer (ADR 0003, regla 1 — un módulo solo
 * escribe/lee lo suyo) — ese filtro final lo hace `Comercial` contra su
 * propia tabla.
 *
 * `listarFirmadasPorContrato` (HU-41, tarea 55): mismo criterio que
 * `listarFirmadas`, acotado a un contrato — lo usa `Portal` para la pantalla
 * de actas del cliente, que SIEMPRE resuelve desde el `contrato_id` de la
 * sesión (invariante 5 de CLAUDE.md), nunca filtrando la lista completa
 * después de traerla.
 */
interface LecturaActaConformada
{
    public function obtenerPorActaId(int $actaId): ?DatosActaConformada;

    /** @return list<DatosActaConformada> todas las actas firmadas, sin filtrar por facturación */
    public function listarFirmadas(): array;

    /** @return list<DatosActaConformada> actas firmadas del contrato dado, sin filtrar por facturación */
    public function listarFirmadasPorContrato(int $contratoId): array;
}
