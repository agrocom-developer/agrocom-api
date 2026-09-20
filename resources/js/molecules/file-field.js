// Comportamiento de la molécula `file-field`
// (resources/views/components/molecules/file-field.blade.php): vista previa
// EN VIVO del archivo recién elegido, antes de guardar.
//
// El componente en sí sigue "SIN JavaScript" para lo que importa (subir,
// reemplazar y quitar funcionan con `<label for>`/checkbox nativos — ver el
// docblock del propio componente, ADR 0019): esto es una mejora progresiva
// que solo agrega la vista previa. Sin este script, el campo sigue
// funcionando exactamente igual que antes — solo no se ve el archivo elegido
// hasta guardar.
//
// No hay forma de previsualizar un archivo local sin JS (no existe
// equivalente HTML/CSS puro a `URL.createObjectURL`), así que esta vista
// previa no puede ser tan "sin JS" como el resto del control.
//
// Además de la imagen, el campo muestra el nombre y el peso del archivo recién
// elegido (tarea 118): un PDF no tiene vista previa, y sin esto el usuario no
// tendría ninguna señal de que el campo lo tomó. Al quitar la elección vuelve
// lo que había (el archivo ya guardado, o nada).

const urlsPorPreview = new WeakMap();
const contenidoOriginalPorPreview = new WeakMap();
const metaOriginalPorMeta = new WeakMap();

function esImagen(archivo) {
    return archivo.type.startsWith('image/');
}

function limpiarUrlAnterior(preview) {
    const urlAnterior = urlsPorPreview.get(preview);

    if (urlAnterior) {
        URL.revokeObjectURL(urlAnterior);
        urlsPorPreview.delete(preview);
    }
}

function restaurarPreview(preview) {
    limpiarUrlAnterior(preview);

    const original = contenidoOriginalPorPreview.get(preview);

    if (original !== undefined) {
        preview.innerHTML = original;
    }
}

// Mismo criterio que `pesoLegible()` de los controladores que lo pintan del
// lado del servidor: bytes por debajo de 1 KB, KB redondeados por encima.
function pesoLegible(bytes) {
    return bytes < 1024 ? `${bytes} B` : `${Math.round(bytes / 1024)} KB`;
}

function restaurarMeta(meta) {
    const original = metaOriginalPorMeta.get(meta);

    if (original !== undefined) {
        meta.innerHTML = original;
    }
}

function mostrarMeta(meta, archivo) {
    if (!metaOriginalPorMeta.has(meta)) {
        metaOriginalPorMeta.set(meta, meta.innerHTML);
    }

    const nombre = document.createElement('span');
    nombre.className = 'ag-file-field__name';
    nombre.textContent = archivo.name;

    const peso = document.createElement('span');
    peso.className = 'ag-file-field__size';
    peso.textContent = pesoLegible(archivo.size);

    meta.replaceChildren(nombre, peso);
}

function mostrarPreview(preview, archivo) {
    if (!contenidoOriginalPorPreview.has(preview)) {
        contenidoOriginalPorPreview.set(preview, preview.innerHTML);
    }

    limpiarUrlAnterior(preview);

    const url = URL.createObjectURL(archivo);
    urlsPorPreview.set(preview, url);

    preview.innerHTML = '';

    const img = document.createElement('img');
    img.src = url;
    img.alt = '';
    img.className = 'ag-file-field__preview-img';
    preview.appendChild(img);
}

document.addEventListener('change', (event) => {
    const input = event.target.closest('.ag-file-field__native[type="file"]');

    if (!input) {
        return;
    }

    const campo = input.closest('.ag-file-field');
    const preview = campo?.querySelector('.ag-file-field__preview');
    const meta = campo?.querySelector('.ag-file-field__meta');

    if (!preview) {
        return;
    }

    const archivo = input.files?.[0];

    if (meta && archivo) {
        mostrarMeta(meta, archivo);
    } else if (meta) {
        restaurarMeta(meta);
    }

    if (!archivo || !esImagen(archivo)) {
        restaurarPreview(preview);

        return;
    }

    mostrarPreview(preview, archivo);
});
