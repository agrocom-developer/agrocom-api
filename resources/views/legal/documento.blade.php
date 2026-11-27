{{--
    Page: documento legal público (GET /terminos → legal.terminos,
    GET /privacidad → legal.privacidad). Sin sesión: lo enlazan las fichas de
    las tiendas y las apps (docs/politicas/legal.md).

    Datos:
    - $documento ('terminos' | 'privacidad'): qué documento se muestra. Su texto
      vive en `lang/es/legal.php`; su versión y su vigencia, en `config/legal.php`.

    El marcador `[POR DEFINIR]` del texto se resalta con un <mark> (el texto se
    parte y se escapa por trozos: no hay HTML crudo).
--}}
@php
    $otro = $documento === 'terminos' ? 'privacidad' : 'terminos';
    $config = config("legal.documentos.{$documento}");
    $vigencia = \Illuminate\Support\Carbon::parse($config['vigente_desde'])
        ->locale(app()->getLocale())
        ->translatedFormat('j \d\e F \d\e Y');
    $reemplazos = [':meses' => (string) config('legal.conservacion_verificacion_meses')];
    $secciones = __("legal.{$documento}.secciones");
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Agrocom') }} — {{ __("legal.{$documento}.titulo_pestana") }}</title>

    <x-atoms.tema-inicial />

    @vite(['resources/css/app.css'])
</head>
<body>
    <x-templates.legal-layout
        :volver-label="__('legal.comun.volver')"
        :pie-aria-label="__('legal.comun.pie_aria_label')"
    >
        @if (config('legal.borrador'))
            <x-slot:banner>
                <x-molecules.alert-strip variant="warning" icon="edit_note" data-ag-legal-borrador>
                    <strong>{{ __('legal.comun.borrador_titulo') }}</strong>
                    {{ __('legal.comun.borrador_detalle') }}
                </x-molecules.alert-strip>
            </x-slot:banner>
        @endif

        <article class="ag-legal__documento">
            <h1 class="ag-legal__titulo">{{ __("legal.{$documento}.titulo") }}</h1>

            <p class="ag-legal__meta" data-ag-legal-version>
                <span>{{ __('legal.comun.version', ['version' => $config['version']]) }}</span>
                <span aria-hidden="true">·</span>
                <span>{{ __('legal.comun.vigente_desde', ['fecha' => $vigencia]) }}</span>
            </p>

            @foreach (__("legal.{$documento}.introduccion") as $parrafo)
                <p class="ag-legal__parrafo">
                    <x-atoms.texto-legal :texto="strtr($parrafo, $reemplazos)" />
                </p>
            @endforeach

            <nav class="ag-legal__indice" aria-label="{{ __('legal.comun.indice_aria_label') }}">
                <h2 class="ag-legal__indice-titulo">{{ __('legal.comun.indice') }}</h2>
                <ol class="ag-legal__indice-lista">
                    @foreach ($secciones as $indice => $seccion)
                        <li><a href="#seccion-{{ $indice + 1 }}">{{ $seccion['titulo'] }}</a></li>
                    @endforeach
                </ol>
            </nav>

            @foreach ($secciones as $indice => $seccion)
                <section class="ag-legal__seccion" id="seccion-{{ $indice + 1 }}" aria-labelledby="seccion-{{ $indice + 1 }}-titulo">
                    <h2 class="ag-legal__seccion-titulo" id="seccion-{{ $indice + 1 }}-titulo">{{ $indice + 1 }}. {{ $seccion['titulo'] }}</h2>

                    @foreach ($seccion['parrafos'] ?? [] as $parrafo)
                        <p class="ag-legal__parrafo">
                            <x-atoms.texto-legal :texto="strtr($parrafo, $reemplazos)" />
                        </p>
                    @endforeach

                    @if (! empty($seccion['items']))
                        <ul class="ag-legal__lista">
                            @foreach ($seccion['items'] as $item)
                                <li>
                                    <x-atoms.texto-legal :texto="strtr($item, $reemplazos)" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </article>

        <x-slot:footer>
            <span>{{ __('legal.comun.otro_documento') }}</span>
            <a href="{{ route("legal.{$otro}") }}" class="ag-legal__enlace">{{ __("legal.{$documento}.enlace_otro") }}</a>
        </x-slot:footer>
    </x-templates.legal-layout>

    @vite('resources/js/app.js')
</body>
</html>
