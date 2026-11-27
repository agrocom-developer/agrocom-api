<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Contratos\CanalDeCodigo;
use App\Dominios\Seguridad\Contratos\ParticipanteDeEliminacion;
use App\Dominios\Seguridad\Contratos\PropositoDeCodigo;
use App\Dominios\Seguridad\Dominio\EstadoDeEliminacion;
use App\Dominios\Seguridad\Dominio\Excepciones\CanalNoDisponible;
use App\Dominios\Seguridad\Dominio\Excepciones\CodigoInvalido;
use App\Dominios\Seguridad\Dominio\Excepciones\ContrasenaActualIncorrecta;
use App\Dominios\Seguridad\Dominio\Excepciones\DemasiadosCodigos;
use App\Dominios\Seguridad\Dominio\Excepciones\EliminacionBloqueada;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecEliminacion;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * La persona elimina su cuenta desde la app (ADR 0047, puntos 2 a 5): la pide con su contraseña o con un código (por el
 * canal que elige), el servidor revisa lo que la impide, cierra todas las sesiones, deshace lo que quedaba abierto y le da
 * 7 días para arrepentirse. La anonimización la ejecuta {@see EjecutarEliminacionesDeCuenta} cuando vence la gracia.
 */
final class EliminarMiCuenta
{
    /** @param  iterable<ParticipanteDeEliminacion>  $participantes */
    public function __construct(
        private readonly CodigosDeVerificacion $codigos,
        private readonly RevocarTokensDeCuenta $revocar,
        private readonly iterable $participantes,
    ) {}

    /** La solicitud vigente (`solicitada` o `pospuesta`) de la cuenta, si la hay. */
    public function vigente(SecUser $usuario): ?SecEliminacion
    {
        return SecEliminacion::query()
            ->where('usuario_id', $usuario->id)
            ->whereIn('estado', [EstadoDeEliminacion::Solicitada->value, EstadoDeEliminacion::Pospuesta->value])
            ->latest('id')
            ->first();
    }

    /**
     * Lo que impide eliminar la cuenta ahora, sin duplicados.
     *
     * @return list<string>
     */
    public function impedimentos(SecUser $usuario): array
    {
        $motivos = [];

        foreach ($this->participantes as $participante) {
            array_push($motivos, ...$participante->impedimentos($usuario->id));
        }

        return array_values(array_unique($motivos));
    }

    /**
     * @return string|null el destino enmascarado, o `null` si la cuenta no tiene a dónde enviarlo por ese canal.
     *
     * @throws CanalNoDisponible
     * @throws DemasiadosCodigos
     */
    public function pedirCodigo(SecUser $usuario, CanalDeCodigo $canal): ?string
    {
        return $this->codigos->emitir($usuario, PropositoDeCodigo::EliminarCuenta, $canal);
    }

    /**
     * Pide la eliminación. Es idempotente: si ya hay una vigente, la devuelve.
     *
     * @throws ContrasenaActualIncorrecta la contraseña no es la de la cuenta.
     * @throws CodigoInvalido el código no sirve.
     * @throws EliminacionBloqueada algo la impide.
     */
    public function solicitar(SecUser $usuario, ?string $password, ?string $codigo): SecEliminacion
    {
        $this->confirmarIdentidad($usuario, $password, $codigo);

        if (($vigente = $this->vigente($usuario)) !== null) {
            return $vigente;
        }

        $motivos = $this->impedimentos($usuario);

        if ($motivos !== []) {
            throw new EliminacionBloqueada($motivos);
        }

        return DB::transaction(function () use ($usuario): SecEliminacion {
            $eliminacion = SecEliminacion::query()->create([
                'usuario_id' => $usuario->id,
                'tipo_cuenta' => $usuario->tipo_cuenta->value,
                'origen' => 'app',
                'estado' => EstadoDeEliminacion::Solicitada->value,
                'solicitada_en' => now(),
                'ejecutar_desde' => now()->addDays((int) config('eliminacion.gracia_dias', 7)),
            ]);

            $this->revocar->ejecutar($usuario);

            foreach ($this->participantes as $participante) {
                $participante->alSolicitar($usuario->id);
            }

            return $eliminacion;
        });
    }

    /** Se arrepintió dentro de la gracia: la cuenta vuelve a operar (lo cancelado al pedirla no se restaura). */
    public function cancelar(SecUser $usuario): ?SecEliminacion
    {
        $vigente = $this->vigente($usuario);

        if ($vigente === null) {
            return null;
        }

        $vigente->estado = EstadoDeEliminacion::Cancelada;
        $vigente->cancelada_en = now();
        $vigente->save();

        return $vigente;
    }

    /**
     * Basta uno de los dos: la contraseña o el código. Sin ninguno no se acepta nada.
     *
     * @throws ContrasenaActualIncorrecta
     * @throws CodigoInvalido
     */
    private function confirmarIdentidad(SecUser $usuario, ?string $password, ?string $codigo): void
    {
        if ($codigo !== null && $codigo !== '') {
            $this->codigos->confirmar($usuario, PropositoDeCodigo::EliminarCuenta, $codigo);

            return;
        }

        if ($password === null || $password === '' || ! Hash::check($password, $usuario->password)) {
            throw ContrasenaActualIncorrecta::porIntento();
        }
    }
}
