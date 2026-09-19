{{--
    Molecule: step-arrow (19/9/2026) — la máquina de estados de un objeto, dibujada
    como pasos con forma de flecha. COMPARTIDO: no sabe nada de campañas; lo
    puede usar cualquier pantalla con un objeto que cambie de estado
    (contratos, órdenes, …) armando sus pasos con
    `App\Dominios\Compartido\Infraestructura\Http\PasosDeEstado::armar()`, que a
    su vez lee la tabla de transiciones de la propia máquina — así lo que se
    dibuja nunca contradice lo que el servidor permite.

    Cada paso es un estado de la ruta principal y está en uno de cinco casos:
    - `completed`: ya se pasó por ahí (color suave del estado + ícono de check).
    - `current`: el estado actual, relleno sólido con el COLOR DEL ESTADO (el
      mismo tono que su badge) y `aria-current="step"`. Si es un estado FINAL —
      ya no hay a dónde avanzar: ningún paso `next` ni `pending`— se dibuja como
      `completed` (procesado, color suave y check, sin el relleno de "activo") y
      solo conserva `aria-current="step"` (19/9/2026, pedido del usuario). Se
      decide por lo que la máquina ofrece, no por la posición: una ruta con dos
      finales (contrato: «Ejecutado» y «Cancelado») suaviza el que sea, aunque
      solo uno quede al final de la fila. En una ruta en línea recta (campaña)
      es el último paso, como siempre.
    - `next`: un estado al que se puede pasar desde el actual — es un BOTÓN que
      abre el modal de confirmación (`confirm-modal`) que la página ya trae; el
      componente no envía nada ni decide nada.
    - `pending`: se podría pasar, pero el usuario no tiene el permiso — visible
      y deshabilitado, con la pista de por qué.
    - `blocked`: no se puede llegar sin pasar antes por otro estado —
      deshabilitado (`aria-disabled`), con el candado y la pista.

    Props:
    - steps (requerido): list<array{key: string, label: string, tone: string,
      status: 'completed'|'current'|'next'|'pending'|'blocked', modal: ?string,
      hint: ?string, icon?: ?string}>. `label`/`hint` ya traducidos. `tone` es el
      mismo valor que `atoms/badge` (neutral|success|warning|danger|info|alert|
      distintivo-1|2|3|primary-2). `modal` es el `id` del modal que abre un paso
      `next` (de confirmación, o informativo si la pantalla sabe que ese paso
      todavía no se puede dar). `icon` (opcional) reemplaza al check con el que
      se dibuja un paso procesado: un estado de salida como «Cancelado» no es
      un éxito.
    - label (requerido): `aria-label` del `<nav>`, ya traducido.
    - help (nullable): párrafo bajo los pasos, a todo el ancho, que dice qué
      significa el estado actual y por qué conviene avanzar, ya traducido. Lo
      arma `PasosDeEstado::ayuda()` con el texto que el propio objeto define en
      su `lang` para cada estado. Queda asociado al `<nav>` con
      `aria-describedby`.

    Sin colores propios: todo por token (`--ag-color-<tono>-*`), claro y
    oscuro. El paso actual lleva relleno sólido del color del estado, salvo en
    tono neutro, que va solo con contorno. Ocupa todo el ancho de su fila y
    se adapta al ancho del propio componente (no al del viewport): compacto
    bajo 48rem y apilado en vertical, sin puntas, bajo 32rem.
--}}
@props([
    'steps',
    'label',
    'help' => null,
])

@php
    $ayudaId = ($attributes->get('id') ?? 'ag-step-arrow').'-ayuda';
    // Estado final: no queda ningún paso al que ir (ni con permiso ni sin él).
    $esEstadoFinal = collect($steps)->doesntContain(
        fn (array $paso): bool => in_array($paso['status'], ['next', 'pending'], true),
    );
@endphp

<div {{ $attributes->class(['ag-step-arrow']) }}>
    <nav class="ag-step-arrow__nav" aria-label="{{ $label }}" @if ($help) aria-describedby="{{ $ayudaId }}" @endif>
        <ol class="ag-step-arrow__lista">
            @foreach ($steps as $paso)
                @php
                    $estadoPaso = $paso['status'];
                    // Un estado final, cuando es el actual, ya está procesado: se dibuja
                    // como completado. `$estadoPaso` sigue mandando en lo funcional
                    // (botón, aria-current); `$situacion`, en lo visual.
                    $situacion = $estadoPaso === 'current' && $esEstadoFinal ? 'completed' : $estadoPaso;
                    $icono = match ($situacion) {
                        'completed' => $paso['icon'] ?? 'check',
                        'next' => 'arrow_forward',
                        'blocked', 'pending' => 'lock',
                        default => null,
                    };
                    $pista = $paso['hint'] ?? null;
                @endphp
                <li class="ag-step-arrow__item ag-step-arrow__item--{{ $situacion }}" data-tone="{{ $paso['tone'] }}">
                    @if ($estadoPaso === 'next')
                        <button
                            type="button"
                            class="ag-step-arrow__paso"
                            data-bs-toggle="modal"
                            data-bs-target="#{{ $paso['modal'] }}"
                            @if ($pista) title="{{ $pista }}" @endif
                        >
                            <span class="ag-step-arrow__contenido">
                                <span class="ag-step-arrow__etiqueta">{{ $paso['label'] }}</span>
                                <x-atoms.icon :name="$icono" size="sm" />
                            </span>
                        </button>
                    @else
                        <span
                            class="ag-step-arrow__paso"
                            @if ($estadoPaso === 'current') aria-current="step" @endif
                            @if ($estadoPaso === 'blocked' || $estadoPaso === 'pending') aria-disabled="true" @endif
                            @if ($pista) title="{{ $pista }}" @endif
                        >
                            <span class="ag-step-arrow__contenido">
                                @if ($icono && $situacion === 'completed')
                                    <x-atoms.icon :name="$icono" size="sm" />
                                @endif
                                <span class="ag-step-arrow__etiqueta">{{ $paso['label'] }}</span>
                                @if ($icono && $situacion !== 'completed')
                                    <x-atoms.icon :name="$icono" size="sm" />
                                @endif
                            </span>
                        </span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>

    @if ($help)
        <p class="ag-step-arrow__ayuda" id="{{ $ayudaId }}">{{ $help }}</p>
    @endif
</div>
