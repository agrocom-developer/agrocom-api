<?php

namespace App\Dominios\Negociacion\Aplicacion;

use App\Dominios\Compartido\Aplicacion\ExigirCuentaAprobada;
use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Dominio\Excepciones\CuentaNoAprobada;
use App\Dominios\Flota\Contratos\DatosEquipoApto;
use App\Dominios\Flota\Contratos\LecturaEquipos;
use App\Dominios\Negociacion\Aplicacion\MaquinaEstados\MaquinaEstadosOferta;
use App\Dominios\Negociacion\Dominio\EstadoOferta;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Cancelacion;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Proveedores\Contratos\DatosProveedor;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Solicitudes\Contratos\DatosSolicitudNegociable;
use App\Dominios\Solicitudes\Contratos\LecturaEstadoDeCliente;
use App\Dominios\Solicitudes\Contratos\PasosDeSolicitud;
use App\Dominios\Solicitudes\Contratos\SolicitudNoNegociable;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * La negociación de punta a punta (`negociacion-contrato.md` §2 y §3): el proveedor
 * oferta (acepta el precio propuesto o contraoferta), cada parte responde a la
 * oferta vigente de la otra (acepta, contraoferta o rechaza) y el autor puede
 * retirarla. Aceptar adjudica la solicitud y cierra las demás ofertas.
 *
 * Toda decisión bloquea primero la fila de la solicitud y después las de sus
 * ofertas, siempre en ese orden, así dos aceptaciones simultáneas dejan una
 * `aceptada` y la otra con 409. El `lado` (`cliente` o `proveedor`) lo decide el
 * permiso del rol del token, no el cuerpo del request.
 */
final class Negociar
{
    public const string LADO_CLIENTE = 'cliente';

    public const string LADO_PROVEEDOR = 'proveedor';

    public function __construct(
        private readonly PasosDeSolicitud $solicitudes,
        private readonly LecturaProveedores $proveedores,
        private readonly LecturaEquipos $equipos,
        private readonly MaquinaEstadosOferta $estados,
        private readonly ParametrosDeNegociacion $parametros,
        private readonly Avisador $avisos,
        private readonly SinAutocontratacion $autocontratacion,
        private readonly ExigirCuentaAprobada $compuerta,
        private readonly LecturaEstadoDeCliente $estadoDeClientes,
    ) {}

    /**
     * La primera oferta de una negociación (`POST /solicitudes/{id}/ofertas`).
     *
     * @param  array{uuid_cliente: string, tipo: string, precio_ha?: string|int|float|null, equipo_id?: int|null, nota?: string|null}  $datos
     * @return array{0: Oferta, 1: bool} la oferta y si se creó ahora (`false` = reintento del mismo uuid).
     *
     * @throws NegociacionInvalida
     */
    public function ofertar(int $usuarioId, int $solicitudId, array $datos): array
    {
        $proveedor = $this->proveedores->proveedorDeLaCuenta($usuarioId) ?? throw NegociacionInvalida::ajeno('sin_proveedor');

        $existente = Oferta::query()->where('uuid_cliente', $datos['uuid_cliente'])->first();
        if ($existente !== null) {
            return [$this->deProveedor($existente, $proveedor, $solicitudId), false];
        }

        // Solo un proveedor aprobado oferta (ADR 0046, punto 12): `403 cuenta_no_aprobada` con su estado.
        $this->exigirAprobada(self::LADO_PROVEEDOR, $usuarioId, $proveedor);

        try {
            return [DB::transaction(function () use ($proveedor, $solicitudId, $datos): Oferta {
                $solicitud = $this->bloquear($solicitudId, true);

                if ($solicitud->venceEn !== null && Carbon::parse($solicitud->venceEn)->lte(Carbon::now())) {
                    throw NegociacionInvalida::conflicto('solicitud_no_abierta');
                }

                // Ni sobre lo publicado por su propia cuenta de cliente ni por otra cuenta de la misma persona (ADR 0046, punto 10).
                if ($this->autocontratacion->esPropia($solicitud->clienteUsuarioId, $proveedor->id)) {
                    throw NegociacionInvalida::dato('propia_cuenta');
                }

                $equipo = $this->equipoApto($proveedor->id, $datos['equipo_id'] ?? null);
                $numero = $this->numeroDeLaNuevaNegociacion($solicitudId, $proveedor->id);
                $precio = $this->precioDeLaPrimera($solicitud, $datos);

                $oferta = $this->estados->crear([
                    'uuid_cliente' => $datos['uuid_cliente'],
                    'solicitud_id' => $solicitudId,
                    'proveedor_id' => $proveedor->id,
                    'equipo_id' => $equipo->equipoId,
                    'numero' => $numero,
                    'autor' => self::LADO_PROVEEDOR,
                    'tipo' => $datos['tipo'],
                    'precio_ha' => $precio,
                    'monto_estimado' => $this->monto($solicitud, $precio),
                    'es_definitiva' => $numero >= $this->parametros->maxOfertas(),
                    'nota' => $this->nota($datos['nota'] ?? null),
                    'vence_en' => $this->venceEn($solicitud),
                ]);

                $this->solicitudes->marcarConOfertas($solicitudId);

                $this->avisos->avisar('oferta_recibida', "oferta_recibida:{$oferta->id}", [
                    'solicitud' => $solicitudId,
                    'proveedor' => $proveedor->razonSocial,
                    'precio' => $precio,
                ], $solicitudId, [$solicitud->clienteUsuarioId], 'ofertas');

                return $oferta;
            }), true];
        } catch (UniqueConstraintViolationException) {
            // Dos reintentos en paralelo, o dos ofertas vigentes del mismo proveedor: gana la primera.
            $ganadora = Oferta::query()->where('uuid_cliente', $datos['uuid_cliente'])->first();
            if ($ganadora === null) {
                throw NegociacionInvalida::conflicto('negociacion_vigente');
            }

            return [$this->deProveedor($ganadora, $proveedor, $solicitudId), false];
        }
    }

    /**
     * Responde con una contraoferta a la oferta vigente de la contraparte.
     *
     * @return array{0: Oferta, 1: bool} la oferta nueva y si se creó ahora.
     *
     * @throws NegociacionInvalida
     */
    public function contraofertar(string $lado, int $usuarioId, int $ofertaId, string $precioHa, ?string $nota, ?string $uuidCliente): array
    {
        $uuidCliente ??= (string) Str::uuid();

        $previa = Oferta::query()->where('uuid_cliente', $uuidCliente)->first();
        if ($previa !== null) {
            $this->autorizar($lado, $usuarioId, $previa->id === $ofertaId ? $previa : (Oferta::query()->find($ofertaId) ?? throw NegociacionInvalida::ajeno('oferta_ajena')));

            return [$previa, false];
        }

        return [DB::transaction(function () use ($lado, $usuarioId, $ofertaId, $precioHa, $nota, $uuidCliente): Oferta {
            [$solicitud, $actual] = $this->bloquearConOferta($lado, $usuarioId, $ofertaId);

            // Contraofertar es de la cuenta aprobada (ADR 0046, punto 12).
            $this->exigirAprobada($lado, $usuarioId);

            $this->exigirRespondible($lado, $solicitud, $actual);

            if ($actual->es_definitiva) {
                throw NegociacionInvalida::conflicto('oferta_definitiva');
            }

            $this->validarPrecio($precioHa);
            if (BigDecimal::of($precioHa)->isEqualTo(BigDecimal::of((string) $actual->precio_ha))) {
                throw NegociacionInvalida::dato('precio_sin_cambio', 'precio_ha');
            }

            $precio = (string) BigDecimal::of($precioHa)->toScale(4);
            $this->estados->transicionar($actual, EstadoOferta::Reemplazada);

            $nueva = $this->estados->crear([
                'uuid_cliente' => $uuidCliente,
                'solicitud_id' => $actual->solicitud_id,
                'proveedor_id' => $actual->proveedor_id,
                'equipo_id' => $actual->equipo_id,
                'numero' => $actual->numero + 1,
                'autor' => $lado,
                'tipo' => 'contraoferta',
                'precio_ha' => $precio,
                'monto_estimado' => $this->monto($solicitud, $precio),
                'es_definitiva' => $actual->numero + 1 >= $this->parametros->maxOfertas(),
                'nota' => $this->nota($nota),
                'oferta_anterior_id' => $actual->id,
                'vence_en' => $this->venceEn($solicitud),
                'distancia_km' => $actual->distancia_km,
            ]);

            $this->avisos->avisar('oferta_recibida', "oferta_recibida:{$nueva->id}", [
                'solicitud' => $solicitud->id,
                'proveedor' => $this->nombreDeProveedor($actual->proveedor_id),
                'precio' => $precio,
            ], $solicitud->id, $lado === self::LADO_CLIENTE ? $this->usuariosDe($actual->proveedor_id) : [$solicitud->clienteUsuarioId], 'ofertas');

            return $nueva;
        }), true];
    }

    /**
     * Acepta la oferta vigente de la contraparte: adjudica la solicitud, fija el precio
     * acordado y cierra las demás ofertas vigentes. Aceptar dos veces la misma oferta
     * devuelve la misma oferta.
     *
     * @throws NegociacionInvalida
     */
    public function aceptar(string $lado, int $usuarioId, int $ofertaId): Oferta
    {
        return DB::transaction(function () use ($lado, $usuarioId, $ofertaId): Oferta {
            [$solicitud, $actual] = $this->bloquearConOferta($lado, $usuarioId, $ofertaId);

            if ($actual->estado === EstadoOferta::Aceptada && $solicitud->ofertaAdjudicadaId === $actual->id) {
                return $actual;
            }

            // Aceptar adjudica: es de la cuenta aprobada, de cualquiera de las dos partes (ADR 0046, punto 12).
            $this->exigirAprobada($lado, $usuarioId);

            if ($solicitud->estado === 'adjudicada' || $solicitud->estado === 'en_ejecucion') {
                throw NegociacionInvalida::conflicto('solicitud_ya_adjudicada');
            }

            $this->exigirRespondible($lado, $solicitud, $actual);

            $proveedor = $this->proveedores->proveedor($actual->proveedor_id);
            $equipo = $proveedor === null || ! $proveedor->estaAprobado() || $actual->equipo_id === null
                ? null
                : $this->equipoDeLaOferta($actual->proveedor_id, $actual->equipo_id);

            if ($equipo === null || $this->solicitudes->equipoOcupado($equipo->equipoId, $solicitud->ventanaDesde, $solicitud->ventanaHasta, $solicitud->id)) {
                throw NegociacionInvalida::conflicto('equipo_no_disponible');
            }

            $this->estados->transicionar($actual, EstadoOferta::Aceptada);

            Oferta::query()
                ->where('solicitud_id', $solicitud->id)
                ->where('estado', EstadoOferta::Vigente->value)
                ->whereKeyNot($actual->id)
                ->lockForUpdate()
                ->get()
                ->each(fn (Oferta $otra) => $this->estados->transicionar($otra, EstadoOferta::Cerrada));

            try {
                $this->solicitudes->adjudicar($solicitud->id, $actual->proveedor_id, $equipo->equipoId, $actual->id, (string) $actual->precio_ha, $lado, $usuarioId);
            } catch (SolicitudNoNegociable) {
                throw NegociacionInvalida::conflicto('solicitud_ya_adjudicada');
            }

            $this->avisos->avisar('solicitud_adjudicada', "solicitud_adjudicada:{$solicitud->id}", [
                'solicitud' => $solicitud->id,
                'proveedor' => $proveedor->razonSocial,
                'precio' => (string) $actual->precio_ha,
            ], $solicitud->id, [$solicitud->clienteUsuarioId, ...$this->usuariosDe($actual->proveedor_id)]);

            return $actual;
        });
    }

    /**
     * Rechaza la oferta vigente de la contraparte: cierra la negociación y el proveedor no
     * puede abrir otra en esa solicitud.
     *
     * @throws NegociacionInvalida
     */
    public function rechazar(string $lado, int $usuarioId, int $ofertaId): Oferta
    {
        return DB::transaction(function () use ($lado, $usuarioId, $ofertaId): Oferta {
            [$solicitud, $actual] = $this->bloquearConOferta($lado, $usuarioId, $ofertaId);

            if ($actual->estado === EstadoOferta::Rechazada) {
                return $actual;
            }

            $this->exigirRespondible($lado, $solicitud, $actual);
            $this->estados->transicionar($actual, EstadoOferta::Rechazada);
            $this->reabrirSiNoQuedan($solicitud->id);

            return $actual;
        });
    }

    /**
     * El autor retira su oferta mientras sigue vigente.
     *
     * @throws NegociacionInvalida
     */
    public function retirar(string $lado, int $usuarioId, int $ofertaId): Oferta
    {
        return DB::transaction(function () use ($lado, $usuarioId, $ofertaId): Oferta {
            [$solicitud, $actual] = $this->bloquearConOferta($lado, $usuarioId, $ofertaId);

            if ($actual->estado === EstadoOferta::Retirada) {
                return $actual;
            }

            if ($actual->autor !== $lado) {
                throw NegociacionInvalida::conflicto('no_es_su_turno');
            }

            $this->estados->transicionar($actual, EstadoOferta::Retirada);
            $this->reabrirSiNoQuedan($solicitud->id);

            return $actual;
        });
    }

    /** Reabre la solicitud (`con_ofertas` → `abierta`) cuando no queda ninguna oferta vigente. */
    public function reabrirSiNoQuedan(int $solicitudId): void
    {
        if (! Oferta::query()->where('solicitud_id', $solicitudId)->where('estado', EstadoOferta::Vigente->value)->exists()) {
            $this->solicitudes->reabrirSinOfertas($solicitudId);
        }
    }

    /** @throws NegociacionInvalida `solicitud_ajena` (404) si no existe. */
    private function bloquear(int $solicitudId, bool $enMercado): DatosSolicitudNegociable
    {
        $solicitud = $this->solicitudes->datos($solicitudId, true) ?? throw NegociacionInvalida::ajeno('solicitud_ajena');

        if ($enMercado && ! $solicitud->enMercado()) {
            throw NegociacionInvalida::conflicto('solicitud_no_abierta');
        }

        return $solicitud;
    }

    /**
     * Bloquea la solicitud y después la oferta (siempre en ese orden) y comprueba que la cuenta
     * es parte de la negociación.
     *
     * @return array{0: DatosSolicitudNegociable, 1: Oferta}
     */
    private function bloquearConOferta(string $lado, int $usuarioId, int $ofertaId): array
    {
        $previa = Oferta::query()->find($ofertaId) ?? throw NegociacionInvalida::ajeno('oferta_ajena');
        $this->autorizar($lado, $usuarioId, $previa);

        $solicitud = $this->solicitudes->datos($previa->solicitud_id, true) ?? throw NegociacionInvalida::ajeno('solicitud_ajena');
        $oferta = Oferta::query()->lockForUpdate()->findOrFail($ofertaId);

        return [$solicitud, $oferta];
    }

    /**
     * La compuerta de la cuenta (ADR 0046, punto 12): el cliente y el proveedor no aprobados entran y miran, pero
     * no ofertan, contraofertan ni aceptan. Rechazar y retirar no se frenan: salir de una negociación siempre se puede.
     *
     * @throws CuentaNoAprobada `403 cuenta_no_aprobada` con el estado de la cuenta.
     */
    private function exigirAprobada(string $lado, int $usuarioId, ?DatosProveedor $proveedor = null): void
    {
        if ($lado === self::LADO_PROVEEDOR) {
            $proveedor ??= $this->proveedores->proveedorDeLaCuenta($usuarioId);

            if ($proveedor !== null) {
                $this->compuerta->exigir(EstadoAprobacion::from($proveedor->estado));
            }

            return;
        }

        $estado = $this->estadoDeClientes->deLaCuenta($usuarioId);

        if ($estado !== null) {
            $this->compuerta->exigir($estado);
        }
    }

    /** @throws NegociacionInvalida 404 si la oferta no es de la cuenta. */
    public function autorizar(string $lado, int $usuarioId, Oferta $oferta): void
    {
        if ($lado === self::LADO_PROVEEDOR) {
            $proveedor = $this->proveedores->proveedorDeLaCuenta($usuarioId);
            if ($proveedor === null || $proveedor->id !== $oferta->proveedor_id) {
                throw NegociacionInvalida::ajeno('oferta_ajena');
            }

            return;
        }

        $solicitud = $this->solicitudes->datos($oferta->solicitud_id);
        if ($solicitud === null || $solicitud->clienteUsuarioId !== $usuarioId) {
            throw NegociacionInvalida::ajeno('oferta_ajena');
        }
    }

    /** Responder (aceptar, rechazar, contraofertar) exige la vigente y vigente de la contraparte, en el mercado. */
    private function exigirRespondible(string $lado, DatosSolicitudNegociable $solicitud, Oferta $oferta): void
    {
        if (! $oferta->estado->estaVigente() || $oferta->vence_en->lte(Carbon::now())) {
            throw NegociacionInvalida::conflicto('oferta_no_vigente');
        }

        if (! $solicitud->enMercado()) {
            throw NegociacionInvalida::conflicto('solicitud_no_abierta');
        }

        if ($oferta->autor === $lado) {
            throw NegociacionInvalida::conflicto('no_es_su_turno');
        }
    }

    private function deProveedor(Oferta $oferta, DatosProveedor $proveedor, int $solicitudId): Oferta
    {
        if ($oferta->proveedor_id !== $proveedor->id || $oferta->solicitud_id !== $solicitudId) {
            throw NegociacionInvalida::dato('uuid_en_uso', 'uuid_cliente');
        }

        return $oferta;
    }

    /** @throws NegociacionInvalida `equipo_no_apto` (422) si no hay un equipo apto. */
    private function equipoApto(int $proveedorId, ?int $equipoId): DatosEquipoApto
    {
        $aptos = $this->equipos->aptosDe($proveedorId);

        foreach ($aptos as $equipo) {
            if ($equipoId === null || $equipo->equipoId === $equipoId) {
                return $equipo;
            }
        }

        throw NegociacionInvalida::dato('equipo_no_apto', 'equipo_id');
    }

    private function equipoDeLaOferta(int $proveedorId, int $equipoId): ?DatosEquipoApto
    {
        foreach ($this->equipos->aptosDe($proveedorId) as $equipo) {
            if ($equipo->equipoId === $equipoId) {
                return $equipo;
            }
        }

        return null;
    }

    /**
     * El número de la oferta con que arranca una negociación: 1, o el siguiente impar si el
     * proveedor ya tuvo una cerrada por vencimiento o retiro.
     *
     * @throws NegociacionInvalida si hay una negociación vigente o cerrada para siempre.
     */
    private function numeroDeLaNuevaNegociacion(int $solicitudId, int $proveedorId): int
    {
        $previas = Oferta::query()->where('solicitud_id', $solicitudId)->where('proveedor_id', $proveedorId)->get();

        if ($previas->contains(fn (Oferta $o): bool => $o->estado === EstadoOferta::Vigente)) {
            throw NegociacionInvalida::conflicto('negociacion_vigente');
        }

        if ($previas->contains(fn (Oferta $o): bool => $o->estado === EstadoOferta::Rechazada || $o->estado === EstadoOferta::Aceptada)
            || Cancelacion::query()->where('solicitud_id', $solicitudId)->where('proveedor_id', $proveedorId)->exists()) {
            throw NegociacionInvalida::conflicto('negociacion_cerrada');
        }

        $maximo = (int) $previas->max('numero');
        $numero = $maximo === 0 ? 1 : ($maximo % 2 === 0 ? $maximo + 1 : $maximo + 2);

        if ($numero > $this->parametros->maxOfertas()) {
            throw NegociacionInvalida::conflicto('negociacion_cerrada');
        }

        return $numero;
    }

    /** @param  array{tipo: string, precio_ha?: string|int|float|null}  $datos */
    private function precioDeLaPrimera(DatosSolicitudNegociable $solicitud, array $datos): string
    {
        $propuesto = (string) BigDecimal::of((string) $solicitud->precioHaPropuesto)->toScale(4);

        if ($datos['tipo'] === 'acepta_propuesta') {
            return $propuesto;
        }

        $precio = (string) ($datos['precio_ha'] ?? '');
        $this->validarPrecio($precio);

        if (BigDecimal::of($precio)->isEqualTo(BigDecimal::of($propuesto))) {
            throw NegociacionInvalida::dato('precio_igual_propuesto', 'precio_ha');
        }

        return (string) BigDecimal::of($precio)->toScale(4);
    }

    /** @throws NegociacionInvalida */
    private function validarPrecio(string $precio): void
    {
        if ($precio === '' || ! is_numeric($precio) || BigDecimal::of($precio)->isLessThanOrEqualTo(0)) {
            throw NegociacionInvalida::dato('precio_invalido', 'precio_ha');
        }

        if (BigDecimal::of($precio)->isGreaterThan(BigDecimal::of($this->parametros->precioMaximo()))) {
            throw NegociacionInvalida::dato('precio_excede_maximo', 'precio_ha', ['maximo' => $this->parametros->precioMaximo()]);
        }
    }

    private function monto(DatosSolicitudNegociable $solicitud, string $precioHa): string
    {
        return (string) BigDecimal::of($solicitud->hectareasSolicitadas)->multipliedBy(BigDecimal::of($precioHa))->toScale(2, RoundingMode::HalfUp);
    }

    /** `min(ahora + vigencia de la oferta, vence_en de la solicitud)`. */
    private function venceEn(DatosSolicitudNegociable $solicitud): Carbon
    {
        $propia = Carbon::now()->addHours($this->parametros->vigenciaOfertaHoras());

        return $solicitud->venceEn !== null && Carbon::parse($solicitud->venceEn)->lt($propia) ? Carbon::parse($solicitud->venceEn) : $propia;
    }

    private function nota(?string $nota): ?string
    {
        $nota = $nota === null ? null : trim($nota);

        return $nota === '' ? null : $nota;
    }

    private function nombreDeProveedor(int $proveedorId): string
    {
        return $this->proveedores->proveedor($proveedorId)->razonSocial ?? '';
    }

    /** @return list<int> */
    private function usuariosDe(int $proveedorId): array
    {
        return $this->proveedores->usuariosDeAviso($proveedorId);
    }
}
