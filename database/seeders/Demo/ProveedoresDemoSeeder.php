<?php

namespace Database\Seeders\Demo;

use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\TipoDocumento;
use App\Dominios\Flota\Aplicacion\CrearEquipo;
use App\Dominios\Flota\Aplicacion\RegistrarDron;
use App\Dominios\Flota\Aplicacion\ResolverAprobacionDron;
use App\Dominios\Flota\Infraestructura\Eloquent\Dron as DronMarketplace;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron as DronOperativo;
use App\Dominios\Proveedores\Aplicacion\CargarDocumento;
use App\Dominios\Proveedores\Aplicacion\ProcesarVencimientos;
use App\Dominios\Proveedores\Aplicacion\RegistrarPiloto;
use App\Dominios\Proveedores\Aplicacion\RegistrarProveedor;
use App\Dominios\Proveedores\Aplicacion\ResolverAprobacion;
use App\Dominios\Proveedores\Aplicacion\RevisarDocumento;
use App\Dominios\Proveedores\Dominio\RequisitosDocumentales;
use App\Dominios\Proveedores\Dominio\TipoProveedor;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Documento;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use App\Dominios\Solicitudes\Aplicacion\RegistrarCliente;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Zona;
use Database\Seeders\Catalogo\ProveedorPropioSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Marketplace: proveedores, pilotos, drones, equipos y clientes con
 * datos creíbles de Santa Cruz (ADR 0028, 0029, 0032). Cubre cada estado de la
 * cola de aprobación para que el panel se vea vivo:
 *
 * - Agrocom (propio): aprobado, con dos pilotos, tres drones
 *   (enlazados a los de `ope_drones`) y dos equipos.
 * - Mamani Agroservicios (independiente): aprobado, con su dron y su equipo.
 * - Fumigaciones del Oriente SRL (tercero): aprobado, con un piloto pendiente,
 *   un dron pendiente y otro suspendido por el sistema por un seguro vencido.
 * - Rosa Condori (independiente): pendiente, con documentos en revisión.
 * - Servicios Aéreos Chiquitano (tercero): rechazado, con su motivo.
 * - Tres clientes.
 *
 * Todo pasa por los casos de uso reales (invariante 7): aprobar, rechazar y
 * suspender dejan su historial y sus avisos, igual que desde el panel. Los
 * centinelas son los logins; si el tercero ya existe no vuelve a sembrar.
 *
 * Contraseña de las cuentas demo: la misma del resto de la familia Demo.
 */
class ProveedoresDemoSeeder extends Seeder
{
    public const PASSWORD = '0000';

    public const CENTINELA_TERCERO = 'fumigaciones.oriente';

    private int $admin = 0;

    public function __construct(
        private readonly RegistrarProveedor $registrarProveedor,
        private readonly RegistrarPiloto $registrarPiloto,
        private readonly RegistrarCliente $registrarCliente,
        private readonly RegistrarDron $registrarDron,
        private readonly CrearEquipo $crearEquipo,
        private readonly CargarDocumento $cargarDocumento,
        private readonly RevisarDocumento $revisarDocumento,
        private readonly ResolverAprobacion $resolverAprobacion,
        private readonly ResolverAprobacionDron $resolverAprobacionDron,
        private readonly ProcesarVencimientos $procesarVencimientos,
    ) {}

    public function run(): void
    {
        $this->admin = (int) Auth::guard('interno')->id();

        if ($this->admin === 0 || Proveedor::query()->whereHas('pilotos')->where('razon_social', 'Fumigaciones del Oriente SRL')->exists()) {
            return;
        }

        $this->call(ProveedorPropioSeeder::class);

        // La demo tiene un solo administrador, que registra y aprueba: sin esto
        // la separación de funciones (quien registra no aprueba lo suyo) frenaría
        // la siembra. Solo vale mientras siembra; en el panel sigue vigente.
        $separar = config('proveedores.separar_funciones');
        config(['proveedores.separar_funciones' => false]);

        try {
            $this->agrocom();
            $this->independienteAprobado();
            $this->terceroAprobado();
            $this->independientePendiente();
            $this->terceroRechazado();
            $this->clientes();

            // Un seguro que venció ayer: el proceso diario lo marca y suspende al dron.
            $this->procesarVencimientos->ejecutar();
        } finally {
            config(['proveedores.separar_funciones' => $separar]);
        }
    }

    /** Agrocom: titular el admin de la plataforma, que ya tiene sesión en la siembra. */
    private function agrocom(): void
    {
        $propio = Proveedor::query()->where('tipo', TipoProveedor::Propio->value)->firstOrFail();
        $propio->usuario_titular_id = $this->admin;
        $propio->documento_fiscal = '1018273029';
        $propio->telefono = '+591 3 3512000';
        $propio->save();

        $this->documentosYAprobacion(TipoSujeto::Proveedor, $propio->id);

        $rodrigo = $this->registrarPiloto->ejecutar($this->admin, [
            'nombre' => 'Rodrigo Suárez Peña', 'username' => 'rodrigo.suarez', 'email' => 'rodrigo.suarez@agrocom.example',
            'password' => self::PASSWORD, 'licencia_numero' => 'RPA-1180', 'licencia_vence' => now()->addMonths(14)->toDateString(),
            'categoria_habilitacion' => 'RPAS agrícola',
        ]);
        $valeria = $this->registrarPiloto->ejecutar($this->admin, [
            'nombre' => 'Valeria Montaño Roca', 'username' => 'valeria.montano', 'email' => null,
            'password' => self::PASSWORD, 'licencia_numero' => 'RPA-1204', 'licencia_vence' => now()->addMonths(11)->toDateString(),
            'categoria_habilitacion' => 'RPAS agrícola',
        ]);

        foreach ([$rodrigo, $valeria] as $piloto) {
            $this->documentosYAprobacion(TipoSujeto::Piloto, $piloto->id);
        }

        // Los tres drones de la flota operativa de Agrocom entran al marketplace
        // enlazados a su fila de `ope_drones` (la ejecución en el lote los usa).
        $drones = [];
        foreach ([
            ['AG-02', 'DJI', 'Agras T30', '1ZNBJ4A00B00K9', '30.00', '9.00', 212],
            ['AG-07', 'DJI', 'Agras T50', '1ZNBJ7C00C00F3', '50.00', '11.00', 148],
            ['AG-09', 'DJI', 'Agras T60', '1ZNBJ9D00D00R2', '60.00', '12.00', 64],
        ] as [$identificador, $marca, $modelo, $serie, $litros, $ancho, $ciclos]) {
            $dron = $this->registrarDron->ejecutar($this->admin, [
                'marca' => $marca, 'modelo' => $modelo, 'numero_serie' => $serie,
                'capacidad_tanque_l' => $litros, 'ancho_pasada_m' => $ancho,
            ]);

            DronMarketplace::query()->whereKey($dron->id)->update([
                'ope_dron_id' => DronOperativo::query()->where('identificador', $identificador)->value('id'),
                'ciclos_bateria' => $ciclos,
            ]);

            $this->documentosYAprobacion(TipoSujeto::Dron, $dron->id);
            $drones[$identificador] = $dron;
        }

        $desde = now()->subMonth()->toDateString();
        $this->crearEquipo->ejecutar($this->admin, $rodrigo->id, $drones['AG-02']->id, null, $desde);
        $this->crearEquipo->ejecutar($this->admin, $valeria->id, $drones['AG-07']->id, null, $desde);
    }

    private function independienteAprobado(): void
    {
        $proveedor = $this->registrarProveedor->ejecutar([
            'tipo' => 'independiente', 'nombre' => 'Carlos Mamani Quispe', 'username' => 'carlos.mamani',
            'email' => 'carlos@mamaniagro.example', 'password' => self::PASSWORD, 'telefono' => '+591 70012345',
            'razon_social' => 'Mamani Agroservicios', 'documento_fiscal' => '4521897012',
            'zona_base_id' => $this->zona('Montero'), 'radio_operacion_km' => '90',
            'licencia_numero' => 'RPA-2291', 'licencia_vence' => now()->addMonths(18)->toDateString(),
            'categoria_habilitacion' => 'RPAS agrícola',
        ]);

        $piloto = $proveedor->pilotos()->firstOrFail();
        $this->documentosYAprobacion(TipoSujeto::Proveedor, $proveedor->id);
        $this->documentosYAprobacion(TipoSujeto::Piloto, $piloto->id);

        $dron = $this->registrarDron->ejecutar($proveedor->usuario_titular_id, [
            'marca' => 'DJI', 'modelo' => 'Agras T40', 'numero_serie' => '1WNDH2K00A0417',
            'capacidad_tanque_l' => '40.00', 'ancho_pasada_m' => '11.00',
        ]);
        $this->documentosYAprobacion(TipoSujeto::Dron, $dron->id);

        $this->crearEquipo->ejecutar($proveedor->usuario_titular_id, $piloto->id, $dron->id, null, now()->subWeeks(3)->toDateString());
    }

    private function terceroAprobado(): void
    {
        $proveedor = $this->registrarProveedor->ejecutar([
            'tipo' => 'tercero', 'nombre' => 'Jhonny Salvatierra Rivero', 'username' => self::CENTINELA_TERCERO,
            'email' => 'gerencia@fumigacionesoriente.example', 'password' => self::PASSWORD, 'telefono' => '+591 3 3461122',
            'razon_social' => 'Fumigaciones del Oriente SRL', 'documento_fiscal' => '1023456028',
            'zona_base_id' => $this->zona('San Julián'), 'radio_operacion_km' => '120',
        ]);
        $titular = (int) $proveedor->usuario_titular_id;

        $this->documentosYAprobacion(TipoSujeto::Proveedor, $proveedor->id);

        $marcelo = $this->registrarPiloto->ejecutar($titular, [
            'nombre' => 'Marcelo Terrazas Aguilera', 'username' => 'marcelo.terrazas', 'email' => null, 'password' => self::PASSWORD,
            'licencia_numero' => 'RPA-1977', 'licencia_vence' => now()->addMonths(9)->toDateString(), 'categoria_habilitacion' => 'RPAS agrícola',
        ]);
        $ivan = $this->registrarPiloto->ejecutar($titular, [
            'nombre' => 'Iván Cuéllar Soliz', 'username' => 'ivan.cuellar', 'email' => null, 'password' => self::PASSWORD,
            'licencia_numero' => 'RPA-2350', 'licencia_vence' => now()->addMonths(20)->toDateString(), 'categoria_habilitacion' => 'RPAS agrícola',
        ]);
        $this->documentosYAprobacion(TipoSujeto::Piloto, $marcelo->id);
        // Iván todavía no cargó sus antecedentes: queda pendiente con lo que ya subió en revisión.
        $this->subirDocumentos(TipoSujeto::Piloto, $ivan->id, ['identidad', 'licencia_piloto']);

        $uno = $this->registrarDron->ejecutar($titular, [
            'marca' => 'DJI', 'modelo' => 'Agras T40', 'numero_serie' => '1WNDH2K00A0522', 'capacidad_tanque_l' => '40.00', 'ancho_pasada_m' => '11.00',
        ]);
        $dos = $this->registrarDron->ejecutar($titular, [
            'marca' => 'DJI', 'modelo' => 'Agras T25', 'numero_serie' => '1WNDH1G00A0188', 'capacidad_tanque_l' => '25.00', 'ancho_pasada_m' => '7.50',
        ]);
        $tres = $this->registrarDron->ejecutar($titular, [
            'marca' => 'XAG', 'modelo' => 'P100 Pro', 'numero_serie' => 'XAGP100-77412', 'capacidad_tanque_l' => '50.00', 'ancho_pasada_m' => '10.00',
        ]);

        // El tercer dron queda pendiente: una foto aprobada y dos por revisar, para recorrer la revisión del panel.
        $this->fotosDelDron($tres->id, ['frontal' => 'aprobada', 'lateral' => 'en_revision', 'placa_serie' => 'en_revision']);
        $this->documentosYAprobacion(TipoSujeto::Dron, $uno->id);
        // El T25: el seguro venció ayer. El proceso diario lo marca vencido y suspende al dron.
        $this->documentosYAprobacion(TipoSujeto::Dron, $dos->id, venceSeguroAyer: true);
        // El P100 Pro todavía espera revisión: sus documentos están cargados, sin revisar.
        $this->subirDocumentos(TipoSujeto::Dron, $tres->id, RequisitosDocumentales::POR_DEFECTO['dron']);

        $this->crearEquipo->ejecutar($titular, $marcelo->id, $uno->id, null, now()->subWeeks(5)->toDateString());
    }

    private function independientePendiente(): void
    {
        $proveedor = $this->registrarProveedor->ejecutar([
            'tipo' => 'independiente', 'nombre' => 'Rosa Condori Vargas', 'username' => 'rosa.condori',
            'email' => null, 'password' => self::PASSWORD, 'telefono' => '+591 76543210',
            'razon_social' => 'Condori Drones Agrícolas', 'documento_fiscal' => '6718342015',
            'zona_base_id' => $this->zona('Cuatro Cañadas'), 'radio_operacion_km' => '70',
            'licencia_numero' => 'RPA-2488', 'licencia_vence' => now()->addMonths(16)->toDateString(),
            'categoria_habilitacion' => 'RPAS agrícola',
        ]);

        $this->subirDocumentos(TipoSujeto::Proveedor, $proveedor->id, ['identidad']);
        $this->subirDocumentos(TipoSujeto::Piloto, $proveedor->pilotos()->firstOrFail()->id, ['identidad', 'licencia_piloto', 'antecedentes']);
    }

    private function terceroRechazado(): void
    {
        $proveedor = $this->registrarProveedor->ejecutar([
            'tipo' => 'tercero', 'nombre' => 'Alberto Hurtado Cruz', 'username' => 'alberto.hurtado',
            'email' => null, 'password' => self::PASSWORD, 'telefono' => '+591 3 9462210',
            'razon_social' => 'Servicios Aéreos Chiquitano', 'documento_fiscal' => '1029384027',
            'zona_base_id' => $this->zona('San José de Chiquitos'), 'radio_operacion_km' => '100',
        ]);

        $this->resolverAprobacion->ejecutar(
            TipoSujeto::Proveedor,
            $proveedor->id,
            AccionAprobacion::Rechazar,
            $this->admin,
            'La identidad del titular no se lee y falta el seguro de responsabilidad civil. Cárgalos de nuevo y reenvía.',
        );
    }

    private function clientes(): void
    {
        foreach ([
            ['empresa', 'Rosario Antelo Vaca', 'agro.sanrafael', 'Agropecuaria San Rafael SRL', '1029384027', '+591 76543210', [
                ['nombre' => 'Rosario Antelo Vaca', 'telefono' => '+591 76543210', 'cargo' => 'Gerente', 'es_principal' => true],
                ['nombre' => 'Mario Suárez Rojas', 'telefono' => '+591 71234567', 'cargo' => 'Encargado de campo'],
            ]],
            ['persona', 'Luis Antelo Ribera', 'luis.antelo', null, null, '+591 72288410', []],
            ['empresa', 'Gonzalo Parada Egüez', 'coop.valle.grande', 'Cooperativa Agrícola Valle Grande', '1034556021', '+591 3 9452216', [
                ['nombre' => 'Gonzalo Parada Egüez', 'telefono' => '+591 3 9452216', 'cargo' => 'Presidente'],
            ]],
        ] as [$tipo, $nombre, $username, $razonSocial, $documento, $telefono, $contactos]) {
            if (Cliente::query()->where('razon_social', $razonSocial ?? $nombre)->exists()) {
                continue;
            }

            $this->registrarCliente->ejecutar([
                'tipo' => $tipo, 'nombre' => $nombre, 'username' => $username, 'email' => null, 'password' => self::PASSWORD,
                'razon_social' => $razonSocial, 'documento_fiscal' => $documento, 'telefono' => $telefono, 'contactos' => $contactos,
            ]);
        }
    }

    /**
     * Carga los documentos obligatorios del sujeto, los aprueba y lo aprueba.
     * Con `$venceSeguroAyer` deja el seguro vencido de ayer (solo para el dron
     * que ilustra la suspensión automática).
     */
    private function documentosYAprobacion(TipoSujeto $sujeto, int $id, bool $venceSeguroAyer = false): void
    {
        $documentos = $this->subirDocumentos($sujeto, $id, RequisitosDocumentales::POR_DEFECTO[$sujeto->value]);

        foreach ($documentos as $documento) {
            $this->revisarDocumento->aprobar($documento->id, $this->admin);
        }

        if ($sujeto === TipoSujeto::Dron) {
            // Un dron no se aprueba sin las fotos obligatorias aprobadas.
            $this->fotosDelDron($id, array_fill_keys((array) config('flota.fotos_obligatorias'), 'aprobada'));
            $this->resolverAprobacionDron->ejecutar($id, AccionAprobacion::Aprobar, $this->admin);
        } else {
            $this->resolverAprobacion->ejecutar($sujeto, $id, AccionAprobacion::Aprobar, $this->admin);
        }

        if ($venceSeguroAyer) {
            Documento::query()
                ->where('sujeto_tipo', $sujeto->value)
                ->where('sujeto_id', $id)
                ->where('tipo', TipoDocumento::SeguroResponsabilidad->value)
                ->update(['vence_en' => now()->subDay()->toDateString()]);
        }
    }

    /**
     * @param  list<string>  $tipos
     * @return list<Documento>
     */
    private function subirDocumentos(TipoSujeto $sujeto, int $id, array $tipos): array
    {
        $vencen = ['licencia_piloto' => 12, 'seguro_responsabilidad' => 10, 'habilitacion_dron' => 14, 'registro_dron' => 24];
        $cargados = [];

        foreach ($tipos as $tipo) {
            $cargados[] = $this->cargarDocumento->ejecutar(
                $sujeto,
                $id,
                TipoDocumento::from($tipo),
                $this->archivoDemo($tipo),
                isset($vencen[$tipo]) ? now()->addMonths($vencen[$tipo])->toDateString() : null,
                $this->admin,
            );
        }

        return $cargados;
    }

    /**
     * Las fotos que el proveedor sube del dron, en el disco privado y con el estado que se pide.
     * Una imagen real y liviana por foto, para verla desde el panel.
     *
     * @param  array<string, string>  $estados  tipo de foto → estado (`aprobada`, `en_revision`, `rechazada`)
     */
    private function fotosDelDron(int $dronId, array $estados): void
    {
        foreach ($estados as $tipo => $estado) {
            if (DB::table('flo_dron_fotos')->where('dron_id', $dronId)->where('tipo', $tipo)->whereNull('deleted_at')->exists()) {
                continue;
            }

            $imagen = imagecreatetruecolor(640, 480);
            imagefilledrectangle($imagen, 0, 0, 640, 480, imagecolorallocate($imagen, 70, 90, 100));
            imagefilledrectangle($imagen, 0, 340, 640, 480, imagecolorallocate($imagen, 120, 130, 120));
            imagestring($imagen, 5, 20, 20, "Dron {$dronId} - {$tipo}", imagecolorallocate($imagen, 255, 255, 255));

            $ruta = "drones/{$dronId}/fotos/{$tipo}-demo.jpg";
            $temporal = (string) tempnam(sys_get_temp_dir(), 'dron-demo-');
            imagejpeg($imagen, $temporal, 70);
            Storage::disk('local')->put($ruta, (string) file_get_contents($temporal));

            DB::table('flo_dron_fotos')->insert([
                'dron_id' => $dronId,
                'tipo' => $tipo,
                'archivo' => $ruta,
                'estado' => $estado,
                'revisado_por' => $estado === 'en_revision' ? null : $this->admin,
                'revisado_en' => $estado === 'en_revision' ? null : now(),
                'created_by' => $this->admin,
                'updated_by' => $this->admin,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** Un PDF mínimo y válido por documento: alcanza para abrirlo desde el panel. */
    private function archivoDemo(string $tipo): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'doc-demo-');
        file_put_contents($ruta, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 300 120]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n% Documento de demostración: {$tipo}\n");

        return new UploadedFile($ruta, "{$tipo}-demo.pdf", 'application/pdf', null, true);
    }

    private function zona(string $nombre): ?int
    {
        return Zona::query()->where('nombre', $nombre)->value('id');
    }
}
