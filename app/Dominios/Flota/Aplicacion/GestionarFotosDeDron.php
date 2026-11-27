<?php

namespace App\Dominios\Flota\Aplicacion;

use App\Dominios\Compartido\Aplicacion\Medios\AlmacenDeMedios;
use App\Dominios\Compartido\Aplicacion\Medios\LimiteDeMedios;
use App\Dominios\Compartido\Dominio\VarianteDeMedio;
use App\Dominios\Flota\Aplicacion\MaquinaEstados\MaquinaEstadosFotoDeDron;
use App\Dominios\Flota\Dominio\Excepciones\OperacionDeFlotaInvalida;
use App\Dominios\Flota\Infraestructura\Eloquent\Dron;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fotos del dron (`negociacion-contrato.md` §9): el proveedor sube las que el administrador revisa
 * (`frontal`, `lateral`, `placa_serie`, `otra`); las aprobadas se muestran al cliente al comparar
 * ofertas. Solo el titular del dron las sube, lista y borra, y solo borra mientras están
 * `en_revision` o `rechazada`. Las archiva en el disco privado (`drones/{id}/fotos/`, nombre generado), nunca en una URL
 * pública, y un dron admite como máximo `medios.limites.por_entidad.dron_fotos` fotos.
 */
final class GestionarFotosDeDron
{
    public const array TIPOS = ['frontal', 'lateral', 'placa_serie', 'otra'];

    public function __construct(
        private readonly LecturaProveedores $proveedores,
        private readonly MaquinaEstadosFotoDeDron $estados,
        private readonly AlmacenDeMedios $almacen,
        private readonly LimiteDeMedios $limites,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws OperacionDeFlotaInvalida
     */
    public function subir(int $usuarioId, int $dronId, string $tipo, UploadedFile $archivo): array
    {
        $dron = $this->dronDelTitular($usuarioId, $dronId);

        $existentes = DB::table('flo_dron_fotos')->where('dron_id', $dron->id)->whereNull('deleted_at')->count();

        if ($this->limites->excede('dron_fotos', $existentes)) {
            throw OperacionDeFlotaInvalida::fotosDeDronLlenas($this->limites->porEntidad('dron_fotos'));
        }

        $ruta = $this->almacen->guardar($archivo, "drones/{$dron->id}/fotos");

        $id = $this->estados->crear($dron->id, $tipo, $ruta, $usuarioId);

        return $this->fila($id);
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws OperacionDeFlotaInvalida
     */
    public function listar(int $usuarioId, int $dronId): array
    {
        $dron = $this->dronDelTitular($usuarioId, $dronId);

        return DB::table('flo_dron_fotos')->where('dron_id', $dron->id)->whereNull('deleted_at')->orderBy('id')->get()
            ->map(fn (object $f): array => $this->armar($f))->all();
    }

    /** @throws OperacionDeFlotaInvalida */
    public function borrar(int $usuarioId, int $dronId, int $fotoId): void
    {
        $dron = $this->dronDelTitular($usuarioId, $dronId);

        $foto = DB::table('flo_dron_fotos')->where('dron_id', $dron->id)->whereNull('deleted_at')->where('id', $fotoId)->first() ?? throw OperacionDeFlotaInvalida::dronAjeno();

        if (! in_array($foto->estado, ['en_revision', 'rechazada'], true)) {
            throw OperacionDeFlotaInvalida::dronNoEditable();
        }

        DB::table('flo_dron_fotos')->where('id', $fotoId)->update(['deleted_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
    }

    /**
     * Aprueba o rechaza una foto (el administrador, con motivo en el rechazo).
     *
     * @throws OperacionDeFlotaInvalida
     */
    public function revisar(int $revisorUsuarioId, int $fotoId, bool $aprobar, ?string $motivo): void
    {
        if (! $aprobar && trim((string) $motivo) === '') {
            throw OperacionDeFlotaInvalida::motivoRequerido();
        }

        $actualizadas = $this->estados->revisar($fotoId, $aprobar, $motivo, $revisorUsuarioId);

        if ($actualizadas === 0) {
            throw OperacionDeFlotaInvalida::dronAjeno();
        }
    }

    /**
     * El archivo de una foto aprobada (la ve el cliente al comparar) o de una propia (la ve su dueño).
     *
     * @return string|null ruta absoluta en el disco (el derivado pedido si ya existe, si no el original), o `null` si no corresponde o el archivo ya no está.
     */
    public function rutaVisible(int $usuarioId, int $fotoId, VarianteDeMedio $variante = VarianteDeMedio::Original): ?string
    {
        $foto = DB::table('flo_dron_fotos')->whereNull('deleted_at')->where('id', $fotoId)->first();
        if ($foto === null) {
            return null;
        }

        $propia = $this->proveedores->proveedorDelTitular($usuarioId)?->id === Dron::query()->whereKey($foto->dron_id)->value('dueno_proveedor_id');

        if ($foto->estado !== 'aprobada' && ! $propia) {
            return null;
        }

        return $this->almacen->rutaAbsoluta($foto->archivo, $variante);
    }

    /** @throws OperacionDeFlotaInvalida */
    private function dronDelTitular(int $usuarioId, int $dronId): Dron
    {
        $proveedor = $this->proveedores->proveedorDelTitular($usuarioId) ?? throw OperacionDeFlotaInvalida::sinProveedor();

        return Dron::query()->where('dueno_proveedor_id', $proveedor->id)->find($dronId) ?? throw OperacionDeFlotaInvalida::dronAjeno();
    }

    /** @return array<string, mixed> */
    private function fila(int $id): array
    {
        return $this->armar(DB::table('flo_dron_fotos')->find($id));
    }

    /** @return array<string, mixed> */
    private function armar(object $foto): array
    {
        return [
            'id' => (int) $foto->id,
            'dron_id' => (int) $foto->dron_id,
            'tipo' => (string) $foto->tipo,
            'estado' => (string) $foto->estado,
            'motivo' => $foto->motivo,
            'url' => "/api/drones/fotos/{$foto->id}/archivo",
            'url_miniatura' => "/api/drones/fotos/{$foto->id}/archivo?variante=miniatura",
            'url_media' => "/api/drones/fotos/{$foto->id}/archivo?variante=media",
        ];
    }
}
