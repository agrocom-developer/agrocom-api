<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Compartido\Aplicacion\Medios\AlmacenDeMedios;
use App\Dominios\Compartido\Aplicacion\Medios\LimiteDeMedios;
use App\Dominios\Compartido\Dominio\Excepciones\MedioNoGuardado;
use App\Dominios\Compartido\Dominio\TipoDocumento;
use App\Dominios\Proveedores\Aplicacion\MaquinaEstados\MaquinaEstadosDocumento;
use App\Dominios\Proveedores\Contratos\CargaDocumentos;
use App\Dominios\Proveedores\Contratos\DocumentoInvalido;
use App\Dominios\Proveedores\Dominio\EstadoDocumento;
use App\Dominios\Proveedores\Dominio\Excepciones\RegistroInvalido;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Documento;
use Illuminate\Http\UploadedFile;

/**
 * Carga un documento de un sujeto y lo deja `en_revision` (ADR 0032, punto 3).
 * El archivo va al disco privado bajo la ruta por objeto del ADR 0026:
 * `{objeto}/{id}/documentos/{nombre-aleatorio}.{ext}`, con nombre generado por
 * el servidor (no se adivina, no lleva datos personales), nunca el que subió el
 * usuario; la base guarda solo esa ruta relativa. Un documento nuevo no reemplaza
 * al anterior: el aprobado más reciente y vigente es el que cuenta. Un sujeto no
 * puede tener más de `medios.limites.por_entidad.documentos` documentos en
 * revisión a la vez.
 *
 * Que quien carga pueda gestionar a este sujeto lo verifica
 * {@see AlcanceDelSolicitante} (API) o el módulo que llama (el dron, en Flota).
 */
final class CargarDocumento implements CargaDocumentos
{
    private const array RAIZ_POR_SUJETO = [
        'proveedor' => 'proveedores',
        'piloto' => 'pilotos',
        'dron' => 'drones',
    ];

    public function __construct(
        private readonly MaquinaEstadosDocumento $estados,
        private readonly AlmacenDeMedios $almacen,
        private readonly LimiteDeMedios $limites,
    ) {}

    public function cargar(string $sujetoTipo, int $sujetoId, string $tipo, UploadedFile $archivo, ?string $venceEn, int $usuarioId): int
    {
        try {
            return $this->ejecutar(TipoSujeto::from($sujetoTipo), $sujetoId, TipoDocumento::from($tipo), $archivo, $venceEn, $usuarioId)->id;
        } catch (RegistroInvalido $excepcion) {
            throw new DocumentoInvalido($excepcion->getMessage());
        }
    }

    /**
     * @throws RegistroInvalido si el documento ya vence en el pasado o el sujeto ya tiene el máximo en revisión.
     */
    public function ejecutar(
        TipoSujeto $sujeto,
        int $sujetoId,
        TipoDocumento $tipo,
        UploadedFile $archivo,
        ?string $venceEn,
        int $usuarioId,
    ): Documento {
        if ($venceEn !== null && $venceEn < now()->toDateString()) {
            throw RegistroInvalido::documentoVencido();
        }

        $enRevision = Documento::query()
            ->where('sujeto_tipo', $sujeto->value)
            ->where('sujeto_id', $sujetoId)
            ->where('estado', EstadoDocumento::EnRevision->value)
            ->count();

        if ($this->limites->excede('documentos', $enRevision)) {
            throw RegistroInvalido::limiteDeDocumentos($this->limites->porEntidad('documentos'));
        }

        $disco = (string) config('proveedores.disco_documentos', 'local');

        try {
            $ruta = $this->almacen->guardar($archivo, self::RAIZ_POR_SUJETO[$sujeto->value]."/{$sujetoId}/documentos", null, $disco);
        } catch (MedioNoGuardado) {
            throw RegistroInvalido::archivoNoDisponible();
        }

        return $this->estados->cargar([
            'sujeto_tipo' => $sujeto,
            'sujeto_id' => $sujetoId,
            'tipo' => $tipo,
            'archivo_ref' => $ruta,
            'nombre_original' => mb_substr($archivo->getClientOriginalName(), 0, 150),
            'mime' => (string) ($archivo->getMimeType() ?? $archivo->getClientMimeType()),
            'bytes' => (int) $archivo->getSize(),
            'vence_en' => $venceEn,
            'subido_por' => $usuarioId,
            'created_by' => $usuarioId,
            'updated_by' => $usuarioId,
        ]);
    }
}
