{{--
    Page: documento legal público (GET /terminos → legal.terminos,
    GET /privacidad → legal.privacidad). Sin sesión: lo enlazan las fichas de
    las tiendas y las apps (docs/politicas/legal.md).

    Datos:
    - $documento ('terminos' | 'privacidad'): qué documento se muestra. Su texto
      vive en `lang/es/legal.php`; su versión y su vigencia, en `config/legal.php`.

    Los marcadores del texto (`:producto`, `:titular`, `:meses`…) se reemplazan
    con `config/legal.php`. Cada sección puede traer `condicionales`: líneas que
    solo salen si el dato de contacto está configurado. La última sección,
    «Contacto y datos del titular», se arma con lo que esté configurado y omite
    lo nulo. El texto se escapa siempre: no hay HTML crudo.
--}}
@php
    $otro = $documento === 'terminos' ? 'privacidad' : 'terminos';
    $config = config("legal.documentos.{$documento}");
    $vigencia = \Illuminate\Support\Carbon::parse($config['vigente_desde'])
        ->locale(app()->getLocale())
        ->translatedFormat('j \d\e F \d\e Y');

    $producto = (string) config('legal.producto');
    $reemplazos = [
        ':producto' => $producto,
        ':titular' => (string) config('legal.titular'),
        ':sitio_empresa' => (string) config('legal.sitio_empresa'),
        ':sitio' => (string) config('legal.sitio'),
        ':direccion' => (string) config('legal.direccion'),
        ':meses' => (string) config('legal.conservacion_verificacion_meses'),
        ':dias_sin_antecedentes' => (string) config('legal.conservacion_verificacion_dias_sin_antecedentes'),
        ':gracia' => (string) config('legal.eliminacion_gracia_dias'),
        ':plazo' => (string) config('legal.eliminacion_plazo_dias'),
        ':respaldos' => (string) config('legal.respaldos_dias'),
        ':servicio_meses' => (string) config('legal.conservacion_servicio_meses'),
        ':emergencia_dias' => (string) config('legal.conservacion_emergencia_dias'),
    ];

    // Datos opcionales: solo cuenta lo que tenga valor.
    $presente = fn ($valor) => is_string($valor) && trim($valor) !== '';
    $redes = collect(config('legal.redes', []))
        ->filter($presente)
        ->map(fn ($cuenta, $red) => __("legal.comun.redes.{$red}").' ('.trim($cuenta).')')
        ->values();
    $valores = array_filter([
        'jurisdiccion' => config('legal.empresa.jurisdiccion'),
        'correo' => config('legal.contacto.correo'),
        'telefono' => config('legal.contacto.telefono'),
        'whatsapp' => config('legal.contacto.whatsapp'),
        'redes' => $redes->isNotEmpty() ? $redes->implode(', ') : null,
    ], $presente);

    $secciones = __("legal.{$documento}.secciones");

    // Sección final de contacto: etiqueta => valor, sin nulos.
    $contacto = array_filter([
        'titular' => config('legal.titular'),
        'razon_social' => config('legal.empresa.razon_social'),
        'nit' => config('legal.empresa.nit'),
        'representante' => config('legal.empresa.representante'),
        'direccion' => config('legal.direccion'),
        'correo' => config('legal.contacto.correo'),
        'telefono' => config('legal.contacto.telefono'),
        'whatsapp' => config('legal.contacto.whatsapp'),
        'sitio' => config('legal.sitio'),
        'sitio_empresa' => config('legal.sitio_empresa'),
        'redes' => $valores['redes'] ?? null,
    ], $presente);
    $numeroContacto = count($secciones) + 1;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $producto }} — {{ __("legal.{$documento}.titulo_pestana") }}</title>

    <x-atoms.tema-inicial />

    @vite(['resources/css/app.css'])
</head>
<body>
    <x-templates.legal-layout
        :volver-label="__('legal.comun.volver')"
        :pie-aria-label="__('legal.comun.pie_aria_label')"
    >
        <article class="ag-legal__documento">
            <h1 class="ag-legal__titulo">{{ __("legal.{$documento}.titulo") }}</h1>

            <p class="ag-legal__meta" data-ag-legal-version>
                <span>{{ __('legal.comun.version', ['version' => $config['version']]) }}</span>
                <span aria-hidden="true">·</span>
                <span>{{ __('legal.comun.vigente_desde', ['fecha' => $vigencia]) }}</span>
            </p>

            @foreach (__("legal.{$documento}.introduccion") as $parrafo)
                <p class="ag-legal__parrafo">{{ strtr($parrafo, $reemplazos) }}</p>
            @endforeach

            <nav class="ag-legal__indice" aria-label="{{ __('legal.comun.indice_aria_label') }}">
                <h2 class="ag-legal__indice-titulo">{{ __('legal.comun.indice') }}</h2>
                <ol class="ag-legal__indice-lista">
                    @foreach ($secciones as $indice => $seccion)
                        <li><a href="#seccion-{{ $indice + 1 }}">{{ strtr($seccion['titulo'], $reemplazos) }}</a></li>
                    @endforeach
                    <li><a href="#seccion-{{ $numeroContacto }}">{{ __('legal.comun.contacto.titulo') }}</a></li>
                </ol>
            </nav>

            @foreach ($secciones as $indice => $seccion)
                @php
                    $condicionales = collect($seccion['condicionales'] ?? [])
                        ->filter(fn ($c) => isset($valores[$c['si']]))
                        ->map(fn ($c) => strtr(strtr($c['texto'], [':valor' => $valores[$c['si']]]), $reemplazos))
                        ->values();
                    $conLista = ! empty($seccion['items']);
                @endphp
                <section class="ag-legal__seccion" id="seccion-{{ $indice + 1 }}" aria-labelledby="seccion-{{ $indice + 1 }}-titulo">
                    <h2 class="ag-legal__seccion-titulo" id="seccion-{{ $indice + 1 }}-titulo">{{ $indice + 1 }}. {{ strtr($seccion['titulo'], $reemplazos) }}</h2>

                    @foreach ($seccion['parrafos'] ?? [] as $parrafo)
                        <p class="ag-legal__parrafo">{{ strtr($parrafo, $reemplazos) }}</p>
                    @endforeach

                    @if ($conLista)
                        <ul class="ag-legal__lista">
                            @foreach ($seccion['items'] as $item)
                                <li>{{ strtr($item, $reemplazos) }}</li>
                            @endforeach
                            @foreach ($condicionales as $texto)
                                <li>{{ $texto }}</li>
                            @endforeach
                        </ul>
                    @else
                        @foreach ($condicionales as $texto)
                            <p class="ag-legal__parrafo">{{ $texto }}</p>
                        @endforeach
                    @endif

                    @foreach ($seccion['parrafos_finales'] ?? [] as $parrafo)
                        <p class="ag-legal__parrafo">{{ strtr($parrafo, $reemplazos) }}</p>
                    @endforeach
                </section>
            @endforeach

            <section class="ag-legal__seccion" id="seccion-{{ $numeroContacto }}" aria-labelledby="seccion-{{ $numeroContacto }}-titulo" data-ag-legal-contacto>
                <h2 class="ag-legal__seccion-titulo" id="seccion-{{ $numeroContacto }}-titulo">{{ $numeroContacto }}. {{ __('legal.comun.contacto.titulo') }}</h2>
                <p class="ag-legal__parrafo">{{ __('legal.comun.contacto.introduccion') }}</p>
                <ul class="ag-legal__lista">
                    @foreach ($contacto as $clave => $valor)
                        <li><strong>{{ __("legal.comun.contacto.{$clave}") }}:</strong> {{ $valor }}</li>
                    @endforeach
                </ul>
            </section>
        </article>

        <x-slot:footer>
            <span>{{ __('legal.comun.otro_documento') }}</span>
            <a href="{{ route("legal.{$otro}") }}" class="ag-legal__enlace">{{ __("legal.{$documento}.enlace_otro") }}</a>
        </x-slot:footer>
    </x-templates.legal-layout>

    @vite('resources/js/app.js')
</body>
</html>
