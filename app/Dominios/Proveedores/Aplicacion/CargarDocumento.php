<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Compartido\Dominio\TipoDocumento;
use App\Dominios\Proveedores\Aplicacion\MaquinaEstados\MaquinaEstadosDocumento;
use App\Dominios\Proveedores\Contratos\CargaDocumentos;
use App\Dominios\Proveedores\Contratos\DocumentoInvalido;
use App\Dominios\Proveedores\Dominio\Excepciones\RegistroInvalido;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Documento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Carga un documento de un sujeto y lo deja `en_revision` (ADR 0032, punto 3).
 * El archivo va al disco privado bajo la ruta por objeto del ADR 0026:
 * `{objeto}/{id}/documentos/{tipo}-{timestamp}.{ext}`, con nombre generado por
 * el servidor, nunca el que subió el usuario. Un documento nuevo no reemplaza
 * al anterior: el aprobado más reciente y vigente es el que cuenta.
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

    public function __construct(private readonly MaquinaEstadosDocumento $estados) {}

    public function cargar(string $sujetoTipo, int $sujetoId, string $tipo, UploadedFile $archivo, ?string $venceEn, int $usuarioId): int
    {
        try {
            return $this->ejecutar(TipoSujeto::from($sujetoTipo), $sujetoId, TipoDocumento::from($tipo), $archivo, $venceEn, $usuarioId)->id;
        } catch (RegistroInvalido $excepcion) {
            throw new DocumentoInvalido($excepcion->getMessage());
        }
    }

    /**
     * @throws RegistroInvalido si el documento ya vence en el pasado.
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

        $disco = (string) config('proveedores.disco_documentos', 'local');
        $extension = strtolower($archivo->getClientOriginalExtension() ?: ($archivo->guessExtension() ?? 'bin'));
        $carpeta = self::RAIZ_POR_SUJETO[$sujeto->value]."/{$sujetoId}/documentos";
        $nombre = $tipo->value.'-'.now()->format('YmdHisv').'.'.$extension;

        $ruta = Storage::disk($disco)->putFileAs($carpeta, $archivo, $nombre);

        if ($ruta === false) {
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
