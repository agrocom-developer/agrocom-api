{{--
    Partial: botón «Eliminar» (dar de baja) del encabezado de una ficha con máquina de
    estados de aprobación (ADR 0032). Eliminar NO es un paso de la máquina: va junto a
    Volver y Editar, al final, en peligro (rojo vino), solo si el servidor ofreció la
    acción `dar_de_baja` (estado, permiso y separación de funciones ya filtrados en
    `$acciones`). Abre el modal de confirmación con motivo de `_modales-aprobacion`.

    Espera: $acciones (list<AccionAprobacion>), $modalBase (string, el mismo de los modales).
--}}
@if (collect($acciones)->contains(\App\Dominios\Compartido\Dominio\AccionAprobacion::DarDeBaja))
    <x-atoms.button
        type="button"
        variant="danger"
        icon="delete"
        data-bs-toggle="modal"
        data-bs-target="#{{ $modalBase }}-dar_de_baja-modal"
    >{{ __('aprobacion.accion.dar_de_baja') }}</x-atoms.button>
@endif
