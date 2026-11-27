/*
 * Organism: modal-revision-medio (resources/views/components/organisms/modal-revision-medio.blade.php)
 *
 * Rellena el modal con el ítem elegido de la cuadrícula: contenido completo, datos y acciones. Todo texto
 * entra con textContent (nunca innerHTML). Abre desde cualquier elemento con `data-medio-modal` y
 * `data-medio-clave`, recorre los archivos con Anterior/Siguiente (y las flechas del teclado) y, tras aprobar
 * o rechazar, la página se recarga y el modal vuelve a abrirse en el siguiente pendiente (sessionStorage).
 * El modal es el de Bootstrap: trampa de foco y Esc vienen de ahí.
 */

const CLAVE_SIGUIENTE = 'ag-medios-siguiente:';

function leerSiguiente(id) {
    try {
        const valor = window.sessionStorage.getItem(CLAVE_SIGUIENTE + id);
        window.sessionStorage.removeItem(CLAVE_SIGUIENTE + id);
        return valor;
    } catch {
        return null;
    }
}

function guardarSiguiente(id, clave) {
    try {
        window.sessionStorage.setItem(CLAVE_SIGUIENTE + id, clave);
    } catch {
        // Sin almacenamiento: el modal simplemente no se reabre solo.
    }
}

function pesoLegible(bytes) {
    if (typeof bytes !== 'number') return null;
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toLocaleString('es', { maximumFractionDigits: 0 })} KB`;
    return `${(bytes / (1024 * 1024)).toLocaleString('es', { maximumFractionDigits: 1 })} MB`;
}

function plantilla(texto, valores) {
    return Object.entries(valores).reduce((salida, [nombre, valor]) => salida.replace(`:${nombre}`, valor), texto);
}

class ModalRevisionMedio {
    constructor(el) {
        this.el = el;
        const datos = JSON.parse(el.querySelector('script[data-medio="datos"]').textContent);
        this.items = datos.items;
        this.textos = datos.textos;
        this.indice = 0;
        this.disparador = null;
        this.zoom = false;
        this.q = (nombre) => el.querySelector(`[data-medio="${nombre}"]`);
        this.modal = window.bootstrap.Modal.getOrCreateInstance(el);

        this.q('anterior').addEventListener('click', () => this.ir(-1));
        this.q('siguiente').addEventListener('click', () => this.ir(1));
        this.q('zoom').addEventListener('click', () => this.alternarZoom());
        this.q('rechazar').addEventListener('click', () => this.rechazo(true));
        this.q('cancelar-rechazo').addEventListener('click', () => this.rechazo(false));
        this.q('form').addEventListener('submit', () => this.alEnviar());
        el.addEventListener('keydown', (evento) => this.alTeclear(evento));
        el.addEventListener('hidden.bs.modal', () => {
            this.q('visor').replaceChildren();
            if (this.disparador && document.contains(this.disparador)) this.disparador.focus();
        });
    }

    abrir(clave, disparador) {
        const indice = this.items.findIndex((item) => item.clave === clave);
        if (indice < 0) return;
        this.disparador = disparador ?? document.activeElement;
        this.indice = indice;
        this.pintar();
        this.modal.show();
    }

    ir(paso) {
        const total = this.items.length;
        this.indice = (this.indice + paso + total) % total;
        this.pintar();
    }

    alTeclear(evento) {
        const enCampo = ['TEXTAREA', 'INPUT', 'SELECT'].includes(evento.target.tagName);
        if (enCampo || evento.altKey || evento.ctrlKey || evento.metaKey) return;
        if (evento.key === 'ArrowLeft') { evento.preventDefault(); this.ir(-1); }
        if (evento.key === 'ArrowRight') { evento.preventDefault(); this.ir(1); }
    }

    rechazo(abierto, enfocar = true) {
        const caja = this.q('rechazo');
        const campo = caja.querySelector('textarea');
        caja.hidden = !abierto;
        campo.disabled = !abierto;
        this.q('acciones').hidden = abierto;
        if (enfocar) (abierto ? campo : this.q('rechazar')).focus();
    }

    // Antes de recargar, deja anotado el siguiente pendiente para reabrir el modal ahí.
    alEnviar() {
        const actual = this.items[this.indice];
        const pendientes = this.items.filter((item) => item.pendiente && item.clave !== actual.clave);
        const despues = pendientes.find((item) => this.items.indexOf(item) > this.indice) ?? pendientes[0];
        if (despues) guardarSiguiente(this.el.id, despues.clave);
    }

    alternarZoom() {
        const item = this.items[this.indice];
        this.zoom = !this.zoom;
        const visor = this.q('visor');
        const img = visor.querySelector('img');
        if (!img) return;
        visor.classList.toggle('ag-modal-medio__visor--zoom', this.zoom);
        // El original solo se pide al ampliar (queda en la bitácora de accesos).
        if (this.zoom && item.medio.url_original) img.src = item.medio.url_original;
        this.q('zoom').setAttribute('aria-pressed', String(this.zoom));
        this.q('zoom-texto').textContent = this.zoom ? this.textos.reducir : this.textos.ampliar;
    }

    pintarVisor(item) {
        const visor = this.q('visor');
        const { medio } = item;
        visor.replaceChildren();
        visor.classList.remove('ag-modal-medio__visor--zoom');
        this.zoom = false;

        const sinContenido = (icono, texto) => {
            const caja = document.createElement('div');
            caja.className = 'ag-modal-medio__sin-vista';
            const i = document.createElement('span');
            i.className = 'material-symbols-rounded ag-icon ag-icon--lg';
            i.setAttribute('aria-hidden', 'true');
            i.textContent = icono;
            const t = document.createElement('p');
            t.textContent = texto;
            caja.append(i, t);
            visor.append(caja);
        };

        if (!medio.existe) {
            sinContenido('broken_image', this.textos.sin_vista_previa);
        } else if (medio.tipo === 'imagen') {
            const img = document.createElement('img');
            img.src = medio.url_media ?? medio.url_original;
            img.alt = plantilla(this.textos.imagen_alt, { titulo: item.titulo });
            visor.append(img);
        } else if (medio.tipo === 'pdf') {
            const marco = document.createElement('iframe');
            marco.src = medio.url_original;
            marco.title = plantilla(this.textos.pdf_titulo, { titulo: item.titulo });
            visor.append(marco);
        } else {
            sinContenido('description', this.textos.sin_vista_previa);
        }
    }

    pintar() {
        const item = this.items[this.indice];
        const { medio } = item;
        const texto = (nombre, valor) => { this.q(nombre).textContent = valor; };

        texto('titulo', item.titulo);
        const insignia = this.q('estado');
        insignia.className = `ag-badge ag-badge--${item.tono}`;
        insignia.firstElementChild.textContent = item.estado_etiqueta;

        this.pintarVisor(item);
        this.q('zoom').hidden = !(medio.existe && medio.tipo === 'imagen');
        this.q('zoom').setAttribute('aria-pressed', 'false');
        texto('zoom-texto', this.textos.ampliar);
        const abrir = this.q('abrir');
        abrir.hidden = !(medio.existe && medio.url_original);
        if (!abrir.hidden) abrir.href = medio.url_original;

        const sinDato = this.textos.sin_dato;
        texto('tipo', this.textos.tipos[medio.tipo] ?? this.textos.tipos.otro);
        texto('estado-texto', item.estado_etiqueta);
        texto('fecha', item.fecha ?? sinDato);
        texto('peso', pesoLegible(medio.peso) ?? sinDato);
        texto('mime', medio.mime ?? sinDato);
        texto('obligatoria', item.obligatoria ? this.textos.si : this.textos.no);
        this.q('origen').parentElement.hidden = !item.subtitulo;
        texto('origen', item.subtitulo ?? '');

        const ficha = this.q('ficha');
        ficha.hidden = !item.url_ficha;
        if (item.url_ficha) ficha.href = item.url_ficha;

        this.q('no-existe').hidden = medio.existe;
        const motivo = this.q('motivo');
        motivo.hidden = !(item.estado === 'rechazada' || item.estado === 'rechazado') || !item.motivo;
        if (!motivo.hidden) motivo.textContent = plantilla(this.textos.motivo_anterior, { motivo: item.motivo });

        const form = this.q('form');
        const puede = item.puede_aprobar || item.puede_rechazar;
        form.hidden = !puede;
        if (puede) form.action = item.url_revisar;
        const aprobar = this.q('aprobar');
        aprobar.hidden = !item.puede_aprobar;
        aprobar.disabled = !medio.existe;
        this.q('rechazar').hidden = !item.puede_rechazar;
        this.rechazo(false, false);
        this.q('rechazo').querySelector('textarea').value = '';

        const descargar = this.q('descargar');
        descargar.hidden = !medio.url_descarga;
        descargar.removeAttribute('href');
        if (medio.url_descarga) descargar.href = medio.url_descarga;

        texto('posicion', plantilla(this.textos.posicion, { actual: this.indice + 1, total: this.items.length }));
        const varios = this.items.length > 1;
        this.q('anterior').disabled = !varios;
        this.q('siguiente').disabled = !varios;
    }
}

const modales = new Map();

function instanciaDe(id) {
    if (!modales.has(id)) {
        const el = document.getElementById(id);
        if (!el || !window.bootstrap) return null;
        modales.set(id, new ModalRevisionMedio(el));
    }
    return modales.get(id);
}

document.addEventListener('click', (evento) => {
    const disparador = evento.target.closest('[data-medio-modal]');
    if (!disparador) return;
    evento.preventDefault();
    instanciaDe(disparador.dataset.medioModal)?.abrir(disparador.dataset.medioClave, disparador);
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-ag-modal-medio]').forEach((el) => {
        const clave = leerSiguiente(el.id);
        if (clave) instanciaDe(el.id)?.abrir(clave, null);
    });
});
