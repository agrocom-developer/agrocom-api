/**
 * Formularios repetibles de Orden de Trabajo (reforma 18/9/2026):
 * - Equipos (N items), cada uno con sus lotes (N items anidados).
 * - Calda/productos (N items).
 *
 * Reutiliza el mecanismo vanilla de clonación de templates que ya usa
 * `asignacion-equipos-form.js`, sin librerías externas.
 */

class OrdenesTrabajoForm {
    constructor(formEl) {
        this.form = formEl;
        this.init();
    }

    init() {
        this.bindEquipoButtons();
        this.bindLoteButtons();
        this.bindCaldaButtons();
        this.bindOrdenSelector();
        this.updateLotesAvailability();
    }

    /**
     * Botones de agregar/quitar equipos.
     */
    bindEquipoButtons() {
        const btn = this.form.querySelector('[data-ag-equipos-agregar]');
        if (!btn) return;

        btn.addEventListener('click', (e) => {
            e.preventDefault();
            this.agregarEquipo();
        });

        // Delegar eventos en contenedor
        const contenedor = this.form.querySelector('[data-ag-equipos-lista]');
        if (contenedor) {
            contenedor.addEventListener('click', (e) => {
                if (e.target.matches('[data-ag-equipo-quitar]')) {
                    e.preventDefault();
                    this.quitarEquipo(e.target);
                }
                if (e.target.matches('[data-ag-equipo-lote-agregar]')) {
                    e.preventDefault();
                    this.agregarLote(e.target);
                }
            });
        }
    }

    /**
     * Botones de agregar/quitar lotes dentro de cada equipo.
     */
    bindLoteButtons() {
        const contenedor = this.form.querySelector('[data-ag-equipos-lista]');
        if (!contenedor) return;

        // Los lotes se agregan de manera dinámica por agregarEquipo(),
        // así que la delegación es en el contenedor padre.
        contenedor.addEventListener('click', (e) => {
            if (e.target.matches('[data-ag-lote-equipo-quitar]')) {
                e.preventDefault();
                this.quitarLote(e.target);
            }
        });
    }

    /**
     * Botones de agregar/quitar productos de calda.
     */
    bindCaldaButtons() {
        const btnAgregar = this.form.querySelector('[data-ag-calda-agregar]');
        if (!btnAgregar) return;

        btnAgregar.addEventListener('click', (e) => {
            e.preventDefault();
            this.agregarCalda();
        });

        // Delegar quitar
        const contenedor = this.form.querySelector('[data-ag-calda-lista]');
        if (contenedor) {
            contenedor.addEventListener('click', (e) => {
                if (e.target.matches('[data-ag-calda-quitar]')) {
                    e.preventDefault();
                    this.quitarCalda(e.target);
                }
            });
        }
    }

    /**
     * Selector de orden: filtrar lotes disponibles cuando se elige una orden.
     */
    bindOrdenSelector() {
        const selector = this.form.querySelector('[data-ag-orden-selector]');
        if (!selector) return;

        selector.addEventListener('change', () => {
            this.updateLotesAvailability();
        });
    }

    updateLotesAvailability() {
        // El controlador ya carga $datosOrden con los lotes por orden.
        // Los <select> de lote en cada fila tienen las opciones completas,
        // así que aquí no hay nada que ocultar/mostrar — la validación
        // ocurre server-side en ActualizarTrabajoRequest.
        // (En una versión más compleja, acá iríamos filtrando las opciones.)
    }

    agregarEquipo() {
        const template = this.form.querySelector('[data-ag-equipo-template]');
        if (!template) return;

        const contenedor = this.form.querySelector('[data-ag-equipos-lista]');
        if (!contenedor) return;

        const indiceNuevo = this.getNextEquipoIndex();
        const html = template.innerHTML.replace(/__INDICE_EQUIPO__/g, indiceNuevo);

        const div = document.createElement('div');
        div.innerHTML = html;
        contenedor.appendChild(div.firstElementChild);
    }

    quitarEquipo(btn) {
        const bloque = btn.closest('[data-ag-equipo-bloque]');
        if (bloque) {
            bloque.remove();
        }
    }

    agregarLote(btn) {
        const bloque = btn.closest('[data-ag-equipo-bloque]');
        if (!bloque) return;

        const template = bloque.querySelector('[data-ag-equipo-lote-template]');
        if (!template) return;

        const contenedor = bloque.querySelector('[data-ag-equipo-lotes-lista]');
        if (!contenedor) return;

        const indiceEquipo = this.getEquipoIndexFromBloque(bloque);
        const indiceNuevo = this.getNextLoteIndex(contenedor);
        const html = template.innerHTML.replace(/__INDICE_LOTE__/g, indiceNuevo);

        const div = document.createElement('div');
        div.innerHTML = html;
        contenedor.appendChild(div.firstElementChild);
    }

    quitarLote(btn) {
        const fila = btn.closest('[data-ag-lote-equipo-fila]');
        if (fila) {
            fila.remove();
        }
    }

    agregarCalda() {
        const template = this.form.querySelector('[data-ag-calda-template]');
        if (!template) return;

        const contenedor = this.form.querySelector('[data-ag-calda-lista]');
        if (!contenedor) return;

        const indiceNuevo = this.getNextCaldaIndex();
        const html = template.innerHTML.replace(/__INDICE_CALDA__/g, indiceNuevo);

        const div = document.createElement('div');
        div.innerHTML = html;
        contenedor.appendChild(div.firstElementChild);
    }

    quitarCalda(btn) {
        const fila = btn.closest('[data-ag-calda-fila]');
        if (fila) {
            fila.remove();
        }
    }

    getNextEquipoIndex() {
        const bloques = this.form.querySelectorAll('[data-ag-equipo-bloque]');
        return bloques.length;
    }

    getNextLoteIndex(contenedor) {
        const filas = contenedor.querySelectorAll('[data-ag-lote-equipo-fila]');
        return filas.length;
    }

    getNextCaldaIndex() {
        const filas = this.form.querySelectorAll('[data-ag-calda-fila]');
        return filas.length;
    }

    getEquipoIndexFromBloque(bloque) {
        const inputs = bloque.querySelectorAll('[name^="equipos["]');
        if (inputs.length === 0) return 0;

        const match = inputs[0].name.match(/equipos\[(\d+)\]/);
        return match ? parseInt(match[1], 10) : 0;
    }
}

/**
 * Inicialización automática al cargar la página.
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-ag-ordenes-trabajo-form]');
    if (form) {
        new OrdenesTrabajoForm(form);
    }
});
