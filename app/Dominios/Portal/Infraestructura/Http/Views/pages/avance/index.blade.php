{{--
    Page: portal/avance/index (GET /portal/avance, portal.avance.index)
    HU-41 (tarea 55): avance comercial del contrato del cliente autenticado
    — hectáreas contratadas, aplicadas y monto facturado, SIN desglose de
    costo (espec §13, "no ve": costos/márgenes/personal/gastos/inventario).

    Datos esperados (ver AvancePortalController::index()): $userName, $tema
    (cáscara mínima de AutorizacionPortalCliente::cascara()) más:
    - $avance (?DatosAvanceComercial, Comercial\Contratos, tarea 68): ya
      agregado por ObtenerAvanceComercial (Comercial, HU-32) vía
      LecturaAvanceComercial, esta vista no calcula nada. `null` solo si el
      contrato ni siquiera existe más (borrado lógico) — un contrato sin
      actas/facturas todavía sigue devolviendo la fila con ceros (ver
      ObtenerAvanceComercial), así que esto no es el estado "vacío" normal
      del portal recién estrenado.
--}}
<x-templates.panel-shell :title="__('portal.avance.titulo')" :tema="$tema" :tema-url="route('portal.preferencias.tema')">
    <x-templates.portal-layout :user-name="$userName">
        <div class="ag-portal-avance">
            <x-organisms.page-header
                :title="__('portal.avance.titulo')"
                :subtitle="__('portal.avance.subtitulo')"
            />

            @if ($avance === null)
                <x-molecules.alert-strip variant="info" icon="insert_chart">
                    {{ __('portal.avance.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-portal-avance__cards">
                    <x-molecules.stat-card
                        :label="__('portal.avance.card_contratadas')"
                        icon="landscape"
                        :value="__('portal.avance.hectareas_valor', ['cantidad' => number_format((float) $avance->hectareasContratadas, 2, ',', '.')])"
                    />

                    <x-molecules.stat-card
                        :label="__('portal.avance.card_aplicadas')"
                        icon="flight_takeoff"
                        :value="__('portal.avance.hectareas_valor', ['cantidad' => number_format((float) $avance->hectareasAplicadas, 2, ',', '.')])"
                    />

                    <x-molecules.stat-card
                        :label="__('portal.avance.card_facturado')"
                        icon="receipt_long"
                        :value="__('portal.avance.monto_valor', ['monto' => number_format((float) $avance->montoFacturado, 2, ',', '.')])"
                    />
                </div>
            @endif
        </div>
    </x-templates.portal-layout>
</x-templates.panel-shell>
