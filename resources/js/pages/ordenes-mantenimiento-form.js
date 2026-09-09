/**
 * Dos comportamientos independientes del panel de órdenes de mantenimiento,
 * cada uno guardado por la presencia de su propio marcador en el DOM (mismo
 * criterio que el resto de `resources/js/pages/`): en cualquier página sin
 * esos marcadores, el bloque correspondiente no hace nada.
 *
 * 1. Alta (ordenes/create.blade.php, HU-37 tarea 53): muestra/oculta el
 *    select de equipo que corresponde al `equipo_tipo` elegido
 *    (`dron`/`vehiculo`) — ambos comparten `name="equipo_id"`, así que solo
 *    el visible/habilitado viaja en el POST (un campo `disabled` no se
 *    envía). Es presentación, no validación — el servidor revalida en
 *    `CrearOrdenMantenimientoRequest`.
 *
 * 2. Cierre (ordenes/edit.blade.php, HU-57 tarea 80): selector de repuestos
 *    por casillas — reemplaza la fila repetible de dos selects + cantidad
 *    de la tarea 53. Cada repuesto del catálogo YA tiene sus campos
 *    (`_repuesto-campos.blade.php`, inyectados por `extraPorOpcion` de
 *    `x-atoms.checkbox-group`) deshabilitados de fábrica: acá se
 *    habilitan/deshabilitan según la casilla hermana, se sincroniza
 *    `base_id` con la base global de la orden (salvo override propio de la
 *    línea), se calcula la disponibilidad/aviso de stock (presentación — la
 *    guarda real sigue en el servidor, `CerrarOrdenMantenimientoRequest` +
 *    `MaquinaEstadosOrdenMantenimiento::cerrar()`) y se arma el resumen
 *    siempre visible. Sin reindexado de nombres: cada línea ya nace como
 *    `repuestos[{repuesto_id}][...]`, así que no hace falta reescribir
 *    `name` al marcar/desmarcar (a diferencia de la tarea 53, que reindexaba
 *    0..n-1 al agregar una fila).
 */
document.addEventListener('DOMContentLoaded', () => {
    const selectEquipoTipo = document.querySelector('[data-ag-orden-equipo-tipo]');
    const camposEquipo = Array.from(document.querySelectorAll('[data-ag-orden-campo]'));

    if (selectEquipoTipo && camposEquipo.length > 0) {
        const aplicarVisibilidad = () => {
            const tipo = selectEquipoTipo.value;

            camposEquipo.forEach((campo) => {
                const visible = campo.dataset.agOrdenCampo === tipo;

                campo.hidden = !visible;
                campo.querySelectorAll('select, input').forEach((control) => {
                    control.disabled = !visible;
                });
            });
        };

        selectEquipoTipo.addEventListener('change', aplicarVisibilidad);
        aplicarVisibilidad();
    }

    inicializarSelectorRepuestos();
});

function inicializarSelectorRepuestos() {
    const contenedor = document.querySelector('[data-ag-repuestos]');
    const checkboxes = Array.from(document.querySelectorAll('input[name="repuestos_marcados[]"]'));

    if (!contenedor || checkboxes.length === 0) return;

    const stockPorRepuesto = JSON.parse(contenedor.dataset.stockPorRepuesto || '{}');
    const plantillaDisponible = contenedor.dataset.plantillaDisponible || '';
    const plantillaAviso = contenedor.dataset.plantillaAviso || '';
    const textoSinBase = contenedor.dataset.textoSinBase || '';

    const selectBaseGlobal = document.querySelector('[data-ag-orden-base-global]');
    const resumenLista = document.querySelector('[data-ag-repuestos-resumen-lista]');
    const resumenVacio = document.querySelector('[data-ag-repuestos-resumen-vacio]');
    const resumenContador = document.querySelector('[data-ag-repuestos-resumen-contador]');
    const plantillaContador = document.querySelector('[data-ag-repuestos-resumen]')?.dataset.plantillaContador || '';

    function bloqueDe(repuestoId) {
        return document.querySelector(`[data-ag-repuesto-campos][data-repuesto-id="${repuestoId}"]`);
    }

    function camposDe(bloque) {
        return {
            repuestoId: bloque.querySelector('[data-ag-repuesto-campo="repuesto_id"]'),
            baseId: bloque.querySelector('[data-ag-repuesto-campo="base_id"]'),
            cantidad: bloque.querySelector('[data-ag-repuesto-campo="cantidad"]'),
            disponibilidad: bloque.querySelector('[data-ag-repuesto-disponibilidad]'),
            aviso: bloque.querySelector('[data-ag-repuesto-aviso]'),
            avisoTexto: bloque.querySelector('[data-ag-repuesto-aviso-texto]'),
            botonCambiarBase: bloque.querySelector('[data-ag-repuesto-cambiar-base]'),
            baseOverrideWrap: bloque.querySelector('[data-ag-repuesto-base-override-wrap]'),
            baseOverride: bloque.querySelector('[data-ag-repuesto-base-override]'),
        };
    }

    function disponibilidadPara(repuestoId, baseId) {
        if (!baseId) return null;

        const porBase = stockPorRepuesto[repuestoId];

        return porBase && porBase[baseId] !== undefined ? porBase[baseId] : '0';
    }

    function actualizarDisponibilidad(bloque) {
        const campos = camposDe(bloque);
        if (!campos.baseId || !campos.disponibilidad || !campos.aviso) return;

        const disponible = disponibilidadPara(bloque.dataset.repuestoId, campos.baseId.value);

        if (disponible === null) {
            campos.disponibilidad.textContent = textoSinBase;
            campos.aviso.hidden = true;
            return;
        }

        campos.disponibilidad.textContent = plantillaDisponible.replace('__CANTIDAD__', disponible);

        const cantidad = parseFloat(campos.cantidad?.value ?? '');
        const superaStock = !Number.isNaN(cantidad) && cantidad > parseFloat(disponible);

        campos.aviso.hidden = !superaStock;
        if (superaStock && campos.avisoTexto) {
            campos.avisoTexto.textContent = plantillaAviso.replace('__DISPONIBLE__', disponible);
        }
    }

    function tieneBasePropia(bloque) {
        return bloque.dataset.basePropia === '1';
    }

    function sincronizarConBaseGlobal(bloque) {
        const campos = camposDe(bloque);
        if (!campos.baseId) return;

        if (!tieneBasePropia(bloque)) {
            campos.baseId.value = selectBaseGlobal?.value ?? '';
        }

        actualizarDisponibilidad(bloque);
    }

    function actualizarResumen() {
        const elegidos = checkboxes.filter((casilla) => casilla.checked);

        if (resumenVacio) resumenVacio.hidden = elegidos.length > 0;
        if (resumenContador) {
            resumenContador.hidden = elegidos.length === 0;
            resumenContador.textContent = plantillaContador.replace('__CANTIDAD__', String(elegidos.length));
        }

        if (!resumenLista) return;

        resumenLista.replaceChildren(
            ...elegidos.map((casilla) => {
                const bloque = bloqueDe(casilla.value);
                const campos = bloque ? camposDe(bloque) : null;
                const etiqueta = casilla.closest('label')?.querySelector('.ag-checkbox-group__option-label')?.textContent ?? '';
                const cantidad = campos?.cantidad?.value || '0';

                const item = document.createElement('li');
                item.className = 'ag-repuestos-resumen__item';
                item.textContent = `${etiqueta}: ${cantidad}`;
                return item;
            }),
        );
    }

    // Separado de `alternarCampos` (el handler de `change`) porque también
    // hace falta al cargar la página: `_repuesto-campos.blade.php` ya nace
    // habilitado cuando el repuesto viene de un repintado tras un error de
    // validación (prop `marcado`, mismo booleano que tilda la casilla), pero
    // sin este paso en la inicialización el `base_id` de esa línea no se
    // sincronizaría con la base global recién elegida en esta carga.
    function aplicarEstadoMarcado(bloque, marcado) {
        const campos = camposDe(bloque);

        [campos.repuestoId, campos.baseId, campos.cantidad].forEach((campo) => {
            if (campo) campo.disabled = !marcado;
        });

        if (marcado) {
            sincronizarConBaseGlobal(bloque);
        } else if (campos.aviso) {
            campos.aviso.hidden = true;
        }
    }

    function alternarCampos(casilla) {
        const bloque = bloqueDe(casilla.value);
        if (!bloque) return;

        aplicarEstadoMarcado(bloque, casilla.checked);
        if (casilla.checked) camposDe(bloque).cantidad?.focus();

        actualizarResumen();
    }

    function inicializarBloque(bloque) {
        const campos = camposDe(bloque);

        bloque.dataset.basePropia = campos.baseOverrideWrap && !campos.baseOverrideWrap.hidden ? '1' : '0';

        campos.botonCambiarBase?.addEventListener('click', () => {
            campos.botonCambiarBase.hidden = true;
            if (campos.baseOverrideWrap) campos.baseOverrideWrap.hidden = false;
            campos.baseOverride?.focus();
            bloque.dataset.basePropia = '1';
        });

        campos.baseOverride?.addEventListener('change', () => {
            if (campos.baseId) campos.baseId.value = campos.baseOverride.value;
            actualizarDisponibilidad(bloque);
            actualizarResumen();
        });

        campos.cantidad?.addEventListener('input', () => {
            actualizarDisponibilidad(bloque);
            actualizarResumen();
        });
    }

    checkboxes.forEach((casilla) => {
        const bloque = bloqueDe(casilla.value);
        if (!bloque) return;

        inicializarBloque(bloque);
        aplicarEstadoMarcado(bloque, casilla.checked);

        casilla.addEventListener('change', () => alternarCampos(casilla));
    });

    selectBaseGlobal?.addEventListener('change', () => {
        checkboxes
            .filter((casilla) => casilla.checked)
            .forEach((casilla) => {
                const bloque = bloqueDe(casilla.value);
                if (bloque) sincronizarConBaseGlobal(bloque);
            });

        actualizarResumen();
    });

    actualizarResumen();
}
