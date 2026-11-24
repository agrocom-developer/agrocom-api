{{--
    Hoja de estilo común de los PDF de Operaciones (acta y reporte técnico).
    Los colores salen de `PaletaPdf`: es la única fuente — ver su docblock.
--}}
@use('App\Dominios\Operaciones\Infraestructura\Pdf\PaletaPdf')
body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: {{ PaletaPdf::TEXTO }}; }
h1 { font-size: 18px; margin-bottom: 4px; }
p.subtitulo { color: {{ PaletaPdf::TEXTO_SUAVE }}; margin-top: 0; }
table { width: 100%; border-collapse: collapse; margin-top: 16px; }
th, td { border: 1px solid {{ PaletaPdf::BORDE }}; padding: 6px 8px; text-align: left; }
th { background-color: {{ PaletaPdf::FONDO_ENCABEZADO }}; width: 40%; }
