/**
 * Alta de Orden de Trabajo (reforma 18/9/2026; reordenada el 19/9/2026).
 *
 * Los bloques de equipo ya NO se agregan ni se quitan acá: cuántos hay lo
 * define la orden de aplicación (`cantidad_equipos_necesarios`) y los dibuja
 * el servidor. Este script se ocupa de:
 * - Recargar la pantalla con `?orden_id=` al elegir otra orden — equipos,
 *   lotes y calda dependen todos de ella — sin perder lo ya cargado en
 *   calda, clima y vuelo (p. ej. al pasar de una orden a otra).
 * - Que una escuadra elegida en un equipo deje de ofrecerse en los demás.
 * - Las filas repetibles de lote dentro de cada equipo.
 * - El reparto automático de hectáreas entre los equipos (parejo o por
 *   dificultad de los lotes), para que solo quede elegir la escuadra.
 * - El resumen de hectáreas repartidas contra lo que le queda a la orden.
 *
 * Vanilla, sin librerías. Los textos llegan traducidos por `data-*`.
 */

class OrdenesTrabajoForm {
    static CLAVE_PARAMETROS = 'ag:orden-trabajo:parametros';

    constructor(formEl) {
        this.form = formEl;
        this.lista = formEl.querySelector('[data-ag-equipos-lista]');
        this.init();
    }

    init() {
        this.restaurarParametros();
        this.bindOrdenSelector();

        if (!this.lista) return;

        this.bindLotes();
        this.bindEscuadras();
        this.bindReparto();
        this.bindRepartoAutomatico();
    }

    /**
     * Al cambiar la orden se vuelve a pedir el formulario para ESA orden.
     */
    bindOrdenSelector() {
        const selector = this.form.querySelector('[data-ag-orden-selector]');
        const urlCrear = this.form.dataset.agUrlCrear;
        if (!selector || !urlCrear) return;

        const ordenInicial = selector.value;

        selector.addEventListener('change', () => {
            if (selector.value === ordenInicial) return;

            const destino = new URL(urlCrear, window.location.origin);
            if (selector.value !== '') {
                destino.searchParams.set('orden_id', selector.value);
            }
            this.guardarParametros();
            window.location.assign(destino.toString());
        });
    }

    /**
     * Clima y vuelo se pueden cargar antes de elegir la orden, y elegirla (o
     * cambiarla) recarga la pantalla: lo tipeado viaja por `sessionStorage` y se repone al
     * volver. Los equipos NO viajan — sus lotes son de la orden anterior. Es de
     * un solo uso: se borra al leerlo, así no reaparece en un alta posterior.
     */
    guardarParametros() {
        const valores = {};

        this.form.querySelectorAll('[name^="parametros["]').forEach((campo) => {
            if (campo.type === 'checkbox') {
                // Las casillas de la calda comparten nombre: se distinguen por valor.
                if (campo.checked) valores[`${campo.name}::${campo.value}`] = true;
            } else if (campo.type !== 'hidden' && campo.value !== '') {
                valores[campo.name] = campo.value;
            }
        });

        try {
            sessionStorage.setItem(OrdenesTrabajoForm.CLAVE_PARAMETROS, JSON.stringify(valores));
        } catch {
            // Sin sessionStorage (modo privado estricto) solo se pierde la comodidad.
        }
    }

    restaurarParametros() {
        let valores = null;

        try {
            valores = JSON.parse(sessionStorage.getItem(OrdenesTrabajoForm.CLAVE_PARAMETROS) ?? 'null');
            sessionStorage.removeItem(OrdenesTrabajoForm.CLAVE_PARAMETROS);
        } catch {
            return;
        }
        if (valores === null || typeof valores !== 'object') return;

        const campos = [...this.form.querySelectorAll('[name^="parametros["]')].filter((c) => c.type !== 'hidden');

        Object.entries(valores).forEach(([clave, valor]) => {
            const campo = campos.find((c) => (c.type === 'checkbox' ? `${c.name}::${c.value}` : c.name) === clave);
            if (!campo) return;

            if (campo.type === 'checkbox') {
                campo.checked = valor === true;
            } else {
                campo.value = String(valor);
            }
            campo.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    /**
     * Filas repetibles de lote dentro de cada equipo.
     */
    bindLotes() {
        this.lista.addEventListener('click', (evento) => {
            const agregar = evento.target.closest('[data-ag-equipo-lote-agregar]');
            if (agregar) {
                evento.preventDefault();
                this.agregarLote(agregar)?.querySelector('select, input')?.focus();
                return;
            }

            const quitar = evento.target.closest('[data-ag-lote-equipo-quitar]');
            if (quitar) {
                evento.preventDefault();
                quitar.closest('[data-ag-lote-equipo-fila]')?.remove();
                this.actualizarReparto();
            }
        });
    }

    agregarLote(boton) {
        const bloque = boton.closest('[data-ag-equipo-bloque]');
        const template = bloque?.querySelector('[data-ag-equipo-lote-template]');
        const contenedor = bloque?.querySelector('[data-ag-equipo-lotes-lista]');
        if (!template || !contenedor) return;

        // El índice nunca se reutiliza: tras quitar una fila del medio,
        // `length` repetiría el de la última y el servidor pisaría una con otra.
        const indiceNuevo = Number(contenedor.dataset.agProximoIndice ?? contenedor.querySelectorAll('[data-ag-lote-equipo-fila]').length);
        contenedor.dataset.agProximoIndice = String(indiceNuevo + 1);

        const envoltorio = document.createElement('div');
        envoltorio.innerHTML = template.innerHTML.replace(/__INDICE_LOTE__/g, String(indiceNuevo));
        const fila = envoltorio.firstElementChild;
        if (!fila) return;

        contenedor.appendChild(fila);
        // La fila llega después de la carga: `atoms/select.js` arma sus selects.
        fila.dispatchEvent(new CustomEvent('ag:select:inicializar', { bubbles: true }));

        return fila;
    }

    /**
     * Reparto automático (pedido del dueño, 19/9/2026). Al elegir un criterio
     * se completan los lotes y las hectáreas de cada equipo; "A mano" no toca
     * nada. Solo corre cuando se CAMBIA el criterio: al volver de un error de
     * validación se respeta lo que ya estaba cargado.
     */
    bindRepartoAutomatico() {
        const opciones = this.form.querySelectorAll('[data-ag-reparto-modo]');
        if (opciones.length === 0) return;

        try {
            this.lotesOrden = JSON.parse(this.lista.dataset.agLotes || '[]');
        } catch {
            this.lotesOrden = [];
        }

        opciones.forEach((opcion) => {
            opcion.addEventListener('change', () => {
                if (opcion.checked && opcion.value !== 'manual') {
                    this.repartir(opcion.value);
                }
            });
        });
    }

    /**
     * "Parejo": cada equipo recibe la misma cantidad de hectáreas. Los lotes
     * se recorren en orden y se van llenando los equipos de a uno, así a cada
     * equipo le tocan lotes contiguos y solo se parte el que cae en el límite
     * entre dos equipos.
     *
     * "Por dificultad": los lotes con obstáculos se reparten ENTEROS, del más
     * difícil al menos y cada uno al equipo que menos lleva — nadie se queda
     * con todos los difíciles y ninguno se parte. Las hectáreas se emparejan
     * después con los lotes del grado más fácil que tenga la orden, que son
     * los únicos que se dividen. No usa coeficientes inventados por grado.
     *
     * Todo en centésimas enteras: las hectáreas no se suman en coma flotante.
     */
    calcularReparto(modo, cantidadEquipos) {
        const aCentesimas = (texto) => Math.round(Number.parseFloat(texto || '0') * 100) || 0;
        const ordenDificultad = ['muchos_obstaculos', 'algunos_obstaculos', 'pocos_obstaculos', 'limpio'];

        const lotes = this.lotesOrden
            .map((lote) => ({ id: String(lote.lote_id), centesimas: aCentesimas(lote.restantes), limpieza: lote.limpieza ?? null }))
            .filter((lote) => lote.centesimas > 0);

        /** @type {Array<Map<string, number>>} lote → centésimas, por equipo */
        const reparto = Array.from({ length: cantidadEquipos }, () => new Map());
        const cargas = new Array(cantidadEquipos).fill(0);
        const asignar = (equipo, lote, centesimas) => {
            reparto[equipo].set(lote.id, (reparto[equipo].get(lote.id) ?? 0) + centesimas);
            cargas[equipo] += centesimas;
        };

        let paraEmparejar = lotes;

        if (modo === 'dificultad') {
            // Un lote sin el dato del terreno va con los limpios: no hay por qué
            // tratarlo como difícil.
            const grupos = ordenDificultad
                .map((grado) => lotes.filter((lote) => (ordenDificultad.includes(lote.limpieza) ? lote.limpieza : 'limpio') === grado))
                .filter((grupo) => grupo.length > 0);

            paraEmparejar = grupos.pop() ?? [];

            grupos.forEach((grupo) => {
                [...grupo]
                    .sort((a, b) => b.centesimas - a.centesimas)
                    .forEach((lote) => asignar(cargas.indexOf(Math.min(...cargas)), lote, lote.centesimas));
            });
        }

        const cuotas = this.nivelar(cargas, paraEmparejar.reduce((suma, lote) => suma + lote.centesimas, 0));

        let equipo = 0;
        paraEmparejar.forEach((lote) => {
            let queda = lote.centesimas;

            while (queda > 0) {
                while (equipo < cantidadEquipos && cuotas[equipo] === 0) {
                    equipo += 1;
                }
                if (equipo >= cantidadEquipos) break;

                const toma = Math.min(queda, cuotas[equipo]);
                asignar(equipo, lote, toma);
                cuotas[equipo] -= toma;
                queda -= toma;
            }
        });

        return reparto;
    }

    /**
     * Cuánto más le toca a cada equipo para que todos terminen con lo mismo,
     * dado lo que ya lleva cada uno y lo que queda por repartir. Un equipo que
     * ya lleva más que el promedio no recibe nada, y el promedio se recalcula
     * entre los demás.
     */
    nivelar(cargas, porRepartir) {
        let activos = cargas.map((_, indice) => indice);
        let objetivo = 0;
        let sobrante = 0;

        for (;;) {
            const suma = porRepartir + activos.reduce((total, indice) => total + cargas[indice], 0);
            objetivo = Math.floor(suma / activos.length);
            sobrante = suma % activos.length;

            const siguen = activos.filter((indice) => cargas[indice] <= objetivo);
            if (siguen.length === activos.length) break;
            activos = siguen;
        }

        const cuotas = new Array(cargas.length).fill(0);
        activos.forEach((indice, posicion) => {
            cuotas[indice] = objetivo - cargas[indice] + (posicion < sobrante ? 1 : 0);
        });

        return cuotas;
    }

    repartir(modo) {
        const bloques = [...this.lista.querySelectorAll('[data-ag-equipo-bloque]')];
        const reparto = this.calcularReparto(modo, bloques.length);

        bloques.forEach((bloque, indice) => {
            const asignados = [...reparto[indice].entries()];
            const contenedor = bloque.querySelector('[data-ag-equipo-lotes-lista]');
            const boton = bloque.querySelector('[data-ag-equipo-lote-agregar]');
            if (!contenedor || !boton) return;

            let filas = [...contenedor.querySelectorAll('[data-ag-lote-equipo-fila]')];

            // El turno y las horas ya cargados en la primera fila valen para
            // todas las del equipo: no se pierden ni hay que repetirlos.
            const turnoDe = (fila, sufijo) => fila?.querySelector(`[name$="[${sufijo}]"]`)?.value ?? '';
            const turno = ['turno', 'turno_hora_inicio', 'turno_hora_fin'].map((sufijo) => [sufijo, turnoDe(filas[0], sufijo)]);

            // La primera fila nunca se quita; sobran las que excedan lo repartido.
            filas.slice(Math.max(asignados.length, 1)).forEach((fila) => fila.remove());
            while (contenedor.querySelectorAll('[data-ag-lote-equipo-fila]').length < asignados.length) {
                this.agregarLote(boton);
            }
            filas = [...contenedor.querySelectorAll('[data-ag-lote-equipo-fila]')];

            filas.forEach((fila, i) => {
                const [loteId, centesimas] = asignados[i] ?? ['', 0];

                this.fijarValor(fila.querySelector('[name$="[lote_id]"]'), loteId);
                this.fijarValor(fila.querySelector('[name$="[hectareas]"]'), centesimas > 0 ? (centesimas / 100).toFixed(2) : '');
                if (i > 0) {
                    turno.forEach(([sufijo, valor]) => this.fijarValor(fila.querySelector(`[name$="[${sufijo}]"]`), valor));
                }
            });
        });

        this.actualizarReparto();
    }

    /** `change` e `input`: el select del panel y los resúmenes escuchan esos eventos. */
    fijarValor(campo, valor) {
        if (!campo) return;

        campo.value = valor;
        campo.dispatchEvent(new Event('input', { bubbles: true }));
        campo.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /**
     * Una escuadra cubre un solo equipo de la tanda: la elegida en un bloque
     * se deshabilita en los demás. `atoms/select.js` observa el `disabled` de
     * cada `<option>` y refresca su lista sola.
     */
    bindEscuadras() {
        this.lista.addEventListener('change', (evento) => {
            if (evento.target.matches('[data-ag-equipo-selector]')) {
                this.actualizarEscuadras();
            }
        });
        this.actualizarEscuadras();
    }

    actualizarEscuadras() {
        const selectores = [...this.lista.querySelectorAll('[data-ag-equipo-selector]')];

        selectores.forEach((selector) => {
            const tomadas = new Set(
                selectores.filter((otro) => otro !== selector && otro.value !== '').map((otro) => otro.value),
            );

            [...selector.options].forEach((opcion) => {
                if (opcion.value === '') return;

                const tomada = tomadas.has(opcion.value);
                if (opcion.disabled !== tomada) {
                    opcion.disabled = tomada;
                }
            });
        });
    }

    /**
     * Resumen en vivo: hectáreas repartidas en esta tanda contra lo que le
     * queda por repartir a la orden. Es una ayuda visual — el tope real por
     * lote lo valida el servidor.
     */
    bindReparto() {
        this.reparto = this.form.querySelector('[data-ag-reparto]');
        if (!this.reparto) return;

        this.lista.addEventListener('input', (evento) => {
            if (evento.target.matches('[data-ag-lote-hectareas]')) {
                this.actualizarReparto();
            }
        });
        this.actualizarReparto();
    }

    actualizarReparto() {
        if (!this.reparto) return;

        // En centésimas enteras: sumar 0.1 + 0.2 en coma flotante no da 0.3.
        const aCentesimas = (texto) => Math.round(Number.parseFloat(texto || '0') * 100) || 0;
        const formato = new Intl.NumberFormat('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const repartidas = [...this.lista.querySelectorAll('[data-ag-lote-hectareas]')]
            .reduce((suma, campo) => suma + aCentesimas(campo.value), 0);
        const total = aCentesimas(this.reparto.dataset.agRepartoTotal);

        this.reparto.textContent = (this.reparto.dataset.agRepartoPlantilla ?? '')
            .replace(':repartidas', formato.format(repartidas / 100))
            .replace(':total', formato.format(total / 100));
        this.reparto.classList.toggle('ag-ordenes-trabajo-form__reparto--excedido', repartidas > total);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-ag-ordenes-trabajo-form]');
    if (form) {
        new OrdenesTrabajoForm(form);
    }
});
