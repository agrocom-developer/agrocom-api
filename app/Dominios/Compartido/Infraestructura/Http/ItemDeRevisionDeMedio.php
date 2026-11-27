<?php

namespace App\Dominios\Compartido\Infraestructura\Http;

use App\Dominios\Compartido\Aplicacion\Medios\ArchivoDeMedio;

/**
 * Un archivo por revisar tal como lo consumen `organisms/cuadricula-medios` y `organisms/modal-revision-medio`:
 * el contrato de {@see ArchivoDeMedio} más lo que la revisión necesita (título, estado, qué acciones puede hacer
 * quien mira y a dónde se envían). La cuadrícula solo usa la miniatura; el modal, el resto. No lleva
 * `url_descarga` salvo que el llamador la haya puesto en el medio por tener el permiso.
 */
final readonly class ItemDeRevisionDeMedio
{
    /**
     * @param  array<string, mixed>  $medio  `ArchivoDeMedio::aArray()`.
     * @param  string  $tono  variante de `atoms/badge` para el estado.
     */
    public function __construct(
        public string $clave,
        public string $titulo,
        public string $estado,
        public string $estadoEtiqueta,
        public string $tono,
        public array $medio,
        public bool $obligatoria = false,
        public bool $pendiente = false,
        public bool $puedeAprobar = false,
        public bool $puedeRechazar = false,
        public ?string $urlRevisar = null,
        public ?string $subtitulo = null,
        public ?string $urlFicha = null,
        public ?string $fecha = null,
        public ?string $motivo = null,
    ) {}

    /** @return array<string, mixed> */
    public function aArray(): array
    {
        return [
            'clave' => $this->clave,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'estado' => $this->estado,
            'estado_etiqueta' => $this->estadoEtiqueta,
            'tono' => $this->tono,
            'obligatoria' => $this->obligatoria,
            'pendiente' => $this->pendiente,
            'puede_aprobar' => $this->puedeAprobar && $this->urlRevisar !== null,
            'puede_rechazar' => $this->puedeRechazar && $this->urlRevisar !== null,
            'url_revisar' => $this->urlRevisar,
            'url_ficha' => $this->urlFicha,
            'fecha' => $this->fecha,
            'motivo' => $this->motivo,
            'medio' => $this->medio,
        ];
    }
}
