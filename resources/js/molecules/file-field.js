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

const urlsPorPreview = new WeakMap();
const contenidoOriginalPorPreview = new WeakMap();

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

    const preview = input.closest('.ag-file-field')?.querySelector('.ag-file-field__preview');

    if (!preview) {
        return;
    }

    const archivo = input.files?.[0];

    if (!archivo || !esImagen(archivo)) {
        restaurarPreview(preview);

        return;
    }

    mostrarPreview(preview, archivo);
});
