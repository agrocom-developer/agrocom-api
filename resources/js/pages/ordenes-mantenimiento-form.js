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
 * 2. Cierre (ordenes/_repuestos-seccion.blade.php, HU-57 tarea 80; pasado a
 *    tabla de detalle en la tarea 116): una fila por repuesto del catálogo,
 *    con su casilla, su cantidad y su base. Los campos de cada fila nacen
 *    deshabilitados —un control `disabled` no viaja en el POST—, así que acá
 *    se habilitan/deshabilitan según la casilla de esa fila, se sincroniza
 *    `base_id` con la base de la orden mientras la fila no tenga una propia,
 *    se calcula la disponibilidad/aviso de stock (presentación — la guarda
 *    real sigue en el servidor, `CerrarOrdenMantenimientoRequest` +
 *    `MaquinaEstadosOrdenMantenimiento::cerrar()`) y se lleva el contador de
 *    elegidos. Sin reindexado de nombres: cada línea ya nace como
 *    `repuestos[{repuesto_id}][...]`, así que no hace falta reescribir
 *    `name` al marcar/desmarcar.
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

    /**
     * Copia la base de la orden a la línea, salvo que esa fila ya haya
     * elegido la suya. Al mover el `<select>` de la fila hay que avisarle con
     * un `change`, que es lo que escucha el combobox de `atoms/select` para
     * repintar su texto visible; `data-sincronizando` distingue ese cambio
     * programático del que hace el usuario, para no marcar la fila como si
     * hubiera elegido base propia.
     */
    function sincronizarConBaseGlobal(bloque) {
        const campos = camposDe(bloque);
        if (!campos.baseId) return;

        if (!tieneBasePropia(bloque)) {
            const base = selectBaseGlobal?.value ?? '';

            campos.baseId.value = base;

            if (campos.baseOverride && campos.baseOverride.value !== base) {
                bloque.dataset.sincronizando = '1';
                campos.baseOverride.value = base;
                campos.baseOverride.dispatchEvent(new Event('change', { bubbles: true }));
                delete bloque.dataset.sincronizando;
            }
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
    }

    // Separado de `alternarCampos` (el handler de `change`) porque también
    // hace falta al cargar la página: la fila de `_repuestos-seccion.blade.php`
    // ya nace habilitada cuando el repuesto viene de un repintado tras un
    // error de validación (`$marcado`, el mismo booleano que tilda la casilla),
    // sin este paso en la inicialización el `base_id` de esa línea no se
    // sincronizaría con la base global recién elegida en esta carga.
    function aplicarEstadoMarcado(bloque, marcado) {
        const campos = camposDe(bloque);

        [campos.repuestoId, campos.baseId, campos.cantidad, campos.baseOverride].forEach((campo) => {
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

        // `data-base-propia` ya viene del Blade: tras un error de validación,
        // una línea que había elegido una base distinta de la de la orden se
        // repinta con la suya.
        bloque.dataset.basePropia ??= '0';

        campos.baseOverride?.addEventListener('change', () => {
            // Elegir la base de esta fila a mano la desengancha de la base de
            // la orden; el eco de `sincronizarConBaseGlobal` no cuenta.
            if (bloque.dataset.sincronizando !== '1') {
                bloque.dataset.basePropia = '1';
            }

            if (campos.baseId) campos.baseId.value = campos.baseOverride.value;
            actualizarDisponibilidad(bloque);
        });

        campos.cantidad?.addEventListener('input', () => {
            actualizarDisponibilidad(bloque);
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
