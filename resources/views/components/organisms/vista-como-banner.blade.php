{{--
    Organism: vista-como-banner (tarea 140)
    Franja PERSISTENTE que acompaña toda pantalla del panel y del portal
    mientras un administrador mira el sistema como otra cuenta: "Viendo como:
    Fulano — Rol" (o "— Cliente"), la marca de solo lectura y «Volver a mi
    vista». Existe para que esa vista NUNCA sea indistinguible de una sesión
    real de esa persona: quien mira siempre ve, en cada pantalla, que no es
    la persona.

    Sin props: lee `$vistaComo`, la variable que `AplicarVistaComo` comparte
    con todas las vistas del request (nombre de la cuenta observada, su rol o
    "cliente", el administrador real y la URL de salida). Sin esa variable no
    pinta nada — es lo que hace seguro incluirla en los dos layouts.

    «Volver a mi vista» es un `<form>` POST (es la única escritura que el
    servidor deja pasar en este modo) — nunca un enlace: salir cambia estado.

    Además, si el request anterior cerró la vista por sí solo (la cuenta se
    bloqueó, el administrador perdió el permiso), `session('vista_como_fin')`
    trae el aviso y se muestra una vez, en el mismo lugar.

    Estilos en resources/css/components/vista-como-banner.css — solo tokens.
--}}
@if (session('vista_como_fin'))
    <div class="ag-vista-como-fin" role="status">
        <x-atoms.icon name="info" size="sm" />
        <span>{{ session('vista_como_fin') }}</span>
    </div>
@endif

@isset($vistaComo)
    <div class="ag-vista-como" role="status" data-ag-vista-como-banner>
        <x-atoms.icon name="visibility" size="sm" class="ag-vista-como__icono" />

        <p class="ag-vista-como__texto">
            <span class="ag-vista-como__etiqueta">{{ __('seguridad.vista_como.banner_viendo_como') }}:</span>
            <strong class="ag-vista-como__nombre">{{ $vistaComo['nombre'] }}</strong>
            <span class="ag-vista-como__rol">— {{ $vistaComo['rol'] ?? __('seguridad.vista_como.tipo_cliente') }}</span>
        </p>

        <span class="ag-vista-como__modo">
            {{ __('seguridad.vista_como.banner_solo_lectura') }}
            <span class="ag-vista-como__admin">· {{ __('seguridad.vista_como.banner_sesion_de', ['admin' => $vistaComo['admin']]) }}</span>
        </span>

        <form method="POST" action="{{ $vistaComo['salirUrl'] }}" class="ag-vista-como__salir">
            @csrf
            <button type="submit" class="ag-vista-como__boton">
                <x-atoms.icon name="logout" size="sm" />
                {{ __('seguridad.vista_como.volver') }}
            </button>
        </form>
    </div>
@endisset
