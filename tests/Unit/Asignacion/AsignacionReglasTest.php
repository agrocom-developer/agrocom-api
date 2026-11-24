<?php

use App\Dominios\Asignacion\Dominio\CausaCancelacion;
use App\Dominios\Asignacion\Dominio\ElegibilidadDeProveedor;
use App\Dominios\Asignacion\Dominio\EstadoOferta;
use App\Dominios\Asignacion\Dominio\Geografia;
use App\Dominios\Asignacion\Dominio\GrupoOferta;
use App\Dominios\Asignacion\Dominio\HechosDeSancion;
use App\Dominios\Asignacion\Dominio\MotivoExclusion;
use App\Dominios\Asignacion\Dominio\PlanDeRondas;
use App\Dominios\Asignacion\Dominio\PoliticaDeSanciones;
use App\Dominios\Asignacion\Dominio\PuntuacionDeTercero;
use App\Dominios\Asignacion\Dominio\TasasDeProveedor;
use App\Dominios\Asignacion\Dominio\TipoSancion;
use App\Dominios\Asignacion\Dominio\TransicionesOferta;

/*
 * Reglas puras de la asignación (especificación del marketplace §5.3 y §6, ADR
 * 0030 y 0038): el plan de rondas, la elegibilidad, la puntuación de terceros,
 * las tasas y la política de sanciones. Sin Eloquent ni base.
 */

// ── Plan de rondas ───────────────────────────────────────────────────────────

test('la ronda 1 es de propios sin radio y las siguientes son de terceros con radios crecientes', function () {
    $plan = new PlanDeRondas(['50', '100', '150']);

    expect($plan->total())->toBe(4)
        ->and($plan->ronda(1)->grupo)->toBe(GrupoOferta::Propio)
        ->and($plan->ronda(1)->radioKm)->toBeNull()
        ->and($plan->ronda(2)->grupo)->toBe(GrupoOferta::Tercero)
        ->and($plan->ronda(2)->radioKm)->toBe('50')
        ->and($plan->ronda(3)->radioKm)->toBe('100')
        ->and($plan->ronda(4)->radioKm)->toBe('150')
        ->and($plan->ronda(4)->esUltima)->toBeTrue()
        ->and($plan->ronda(3)->esUltima)->toBeFalse();
});

test('agotado el plan no hay más rondas, y una ronda inválida no existe', function () {
    $plan = new PlanDeRondas(['50']);

    expect($plan->ronda(3))->toBeNull()
        ->and($plan->ronda(0))->toBeNull()
        ->and((new PlanDeRondas([]))->ronda(1)->esUltima)->toBeTrue();
});

// ── Estados de la oferta ─────────────────────────────────────────────────────

test('una oferta viva puede aceptarse, rechazarse, vencer o retirarse; las demás son terminales', function () {
    expect(TransicionesOferta::destinosDesde(EstadoOferta::Ofrecida))
        ->toBe([EstadoOferta::Aceptada, EstadoOferta::Rechazada, EstadoOferta::Vencida, EstadoOferta::Retirada])
        ->and(TransicionesOferta::destinosDesde(EstadoOferta::Rechazada))->toBe([])
        ->and(TransicionesOferta::destinosDesde(EstadoOferta::Vencida))->toBe([])
        ->and(TransicionesOferta::destinosDesde(EstadoOferta::Retirada))->toBe([])
        ->and(TransicionesOferta::destinosDesde(EstadoOferta::Aceptada))->toBe([EstadoOferta::Retirada]);
});

test('nadie se transiciona a sí mismo y solo la oferta ofrecida está viva', function () {
    foreach (EstadoOferta::cases() as $estado) {
        expect(TransicionesOferta::permitida($estado, $estado))->toBeFalse()
            ->and($estado->esViva())->toBe($estado === EstadoOferta::Ofrecida);
    }
});

test('el grupo de un proveedor sale de su tipo: solo el propio es propio', function () {
    expect(GrupoOferta::deTipoDeProveedor('propio'))->toBe(GrupoOferta::Propio)
        ->and(GrupoOferta::deTipoDeProveedor('independiente'))->toBe(GrupoOferta::Tercero)
        ->and(GrupoOferta::deTipoDeProveedor('tercero'))->toBe(GrupoOferta::Tercero);
});

// ── Elegibilidad ─────────────────────────────────────────────────────────────

function elegibilidad(array $cambios = []): array
{
    $base = [
        'grupo' => GrupoOferta::Tercero,
        'tieneEquipoLibre' => true,
        'sancionVigente' => false,
        'calificacionPromedio' => '4.50',
        'calificacionesConteo' => 20,
        'umbralCalificacion' => '3.3',
        'minimoCalificaciones' => 10,
        'distanciaKm' => '30.00',
        'radioOperacionKm' => '100.0',
        'radioDeLaRondaKm' => '50',
        'esUltimaRonda' => false,
    ];

    return ElegibilidadDeProveedor::motivosDeExclusion(...[...$base, ...$cambios]);
}

test('un proveedor con equipo libre, dentro de los radios y sin sanción es elegible', function () {
    expect(elegibilidad())->toBe([]);
});

test('sin equipo libre, con sanción o con calificación bajo el umbral no recibe ofertas', function () {
    expect(elegibilidad(['tieneEquipoLibre' => false]))->toBe([MotivoExclusion::SinEquipoLibre])
        ->and(elegibilidad(['sancionVigente' => true]))->toBe([MotivoExclusion::SancionVigente])
        ->and(elegibilidad(['calificacionPromedio' => '3.20']))->toBe([MotivoExclusion::CalificacionBajoUmbral]);
});

test('el umbral de calificación solo aplica con el mínimo de calificaciones', function () {
    expect(elegibilidad(['calificacionPromedio' => '2.00', 'calificacionesConteo' => 9]))->toBe([]);
});

test('un tercero fuera del radio de la ronda o del suyo de operación se excluye; un propio no mira el radio de la ronda', function () {
    expect(elegibilidad(['distanciaKm' => '60.00']))->toBe([MotivoExclusion::FueraDelRadioDeLaRonda])
        ->and(elegibilidad(['distanciaKm' => '120.00', 'radioDeLaRondaKm' => '150']))->toBe([MotivoExclusion::FueraDelRadioDeOperacion])
        ->and(elegibilidad(['grupo' => GrupoOferta::Propio, 'radioDeLaRondaKm' => null, 'distanciaKm' => '60.00']))->toBe([]);
});

test('quien no tiene ubicación medible solo entra en la última ronda de terceros', function () {
    expect(elegibilidad(['distanciaKm' => null]))->toBe([MotivoExclusion::SinUbicacion])
        ->and(elegibilidad(['distanciaKm' => null, 'esUltimaRonda' => true]))->toBe([])
        ->and(elegibilidad(['grupo' => GrupoOferta::Propio, 'distanciaKm' => null, 'radioDeLaRondaKm' => null]))->toBe([]);
});

// ── Puntuación de terceros ───────────────────────────────────────────────────

const PESOS = ['cercania' => '0.40', 'calificacion' => '0.35', 'aceptacion' => '0.15', 'cancelacion' => '0.10'];

test('la puntuación combina cercanía, calificación, aceptación y cancelación con los pesos de la especificación', function () {
    // cercanía 1 - 25/50 = 0.5; calificación 4/5 = 0.8; aceptación 0.9; (1 - 0.1) = 0.9
    // 0.40*0.5 + 0.35*0.8 + 0.15*0.9 + 0.10*0.9 = 0.2 + 0.28 + 0.135 + 0.09 = 0.705
    expect(PuntuacionDeTercero::calcular(PESOS, '25.00', '50', '4.00', '0.9000', '0.1000'))->toBe('0.7050');
});

test('el proveedor perfecto y pegado al lote suma 1; sin distancia la cercanía vale cero', function () {
    expect(PuntuacionDeTercero::calcular(PESOS, '0.00', '50', '5.00', '1.0000', '0.0000'))->toBe('1.0000')
        ->and(PuntuacionDeTercero::calcular(PESOS, null, '50', '5.00', '1.0000', '0.0000'))->toBe('0.6000');
});

test('los valores fuera de rango se acotan y el resultado es decimal exacto', function () {
    expect(PuntuacionDeTercero::calcular(PESOS, '80.00', '50', '5.00', '1.0000', '0.0000'))->toBe('0.6000')
        ->and(PuntuacionDeTercero::calcular(PESOS, '10.00', '50', '4.20', '0.5000', '0.2500'))->toBe('0.7640');
});

test('a igual lo demás, el más cercano puntúa más', function () {
    $cerca = PuntuacionDeTercero::calcular(PESOS, '10.00', '50', '4.00', '0.8000', '0.0500');
    $lejos = PuntuacionDeTercero::calcular(PESOS, '40.00', '50', '4.00', '0.8000', '0.0500');

    expect((float) $cerca)->toBeGreaterThan((float) $lejos);
});

// ── Geografía ────────────────────────────────────────────────────────────────

test('la distancia es simétrica y razonable entre Santa Cruz y Warnes', function () {
    $ida = Geografia::distanciaKm(-17.7833, -63.1821, -17.5141, -63.1668);
    $vuelta = Geografia::distanciaKm(-17.5141, -63.1668, -17.7833, -63.1821);

    expect($ida)->toBe($vuelta)
        ->and($ida)->toBeGreaterThan(28.0)->toBeLessThan(32.0)
        ->and(Geografia::distanciaKm(-17.7833, -63.1821, -17.7833, -63.1821))->toBe(0.0);
});

test('el centro de un polígono es el promedio de los vértices de su anillo exterior', function () {
    $poligono = ['type' => 'Polygon', 'coordinates' => [[[-63.0, -17.0], [-63.2, -17.0], [-63.2, -17.2], [-63.0, -17.2]]]];
    $centro = Geografia::centroDe($poligono);

    expect(round($centro['lat'], 4))->toBe(-17.1)->and(round($centro['lng'], 4))->toBe(-63.1);
});

test('una geometría ausente o rota no tiene centro', function () {
    expect(Geografia::centroDe(null))->toBeNull()
        ->and(Geografia::centroDe(['type' => 'Point', 'coordinates' => [1, 2]]))->toBeNull()
        ->and(Geografia::centroDe(['type' => 'Polygon', 'coordinates' => [[]]]))->toBeNull();
});

// ── Tasas ────────────────────────────────────────────────────────────────────

test('la tasa de aceptación cuenta solo lo que el proveedor respondió o dejó vencer', function () {
    expect(TasasDeProveedor::aceptacion(3, 1, 0))->toBe('0.7500')
        ->and(TasasDeProveedor::aceptacion(0, 2, 1))->toBe('0.0000')
        ->and(TasasDeProveedor::aceptacion(1, 0, 2))->toBe('0.3333')
        ->and(TasasDeProveedor::aceptacion(0, 0, 0))->toBe('1.0000');
});

test('la tasa de cancelación pesa sobre lo aceptado y nunca pasa de 1', function () {
    expect(TasasDeProveedor::cancelacion(0, 10))->toBe('0.0000')
        ->and(TasasDeProveedor::cancelacion(3, 10))->toBe('0.3000')
        ->and(TasasDeProveedor::cancelacion(5, 2))->toBe('1.0000');
});

test('una cancelación con menos de 12 horas pesa el doble; una emergencia o una falla avisada a tiempo no pesan', function () {
    expect(TasasDeProveedor::pesoDeCancelacion(CausaCancelacion::Otra, 30.0, 12, 2))->toBe(1)
        ->and(TasasDeProveedor::pesoDeCancelacion(CausaCancelacion::Otra, 5.0, 12, 2))->toBe(2)
        ->and(TasasDeProveedor::pesoDeCancelacion(CausaCancelacion::FallaDeEquipo, 30.0, 12, 2))->toBe(0)
        ->and(TasasDeProveedor::pesoDeCancelacion(CausaCancelacion::FallaDeEquipo, 5.0, 12, 2))->toBe(2)
        ->and(TasasDeProveedor::pesoDeCancelacion(CausaCancelacion::Emergencia, 1.0, 12, 2))->toBe(0);
});

// ── Política de sanciones ────────────────────────────────────────────────────

const CALIFICACION = ['minimo_conteo' => 10, 'advertencia_bajo' => '4.0', 'restriccion_tras_plazo_bajo' => '3.7', 'restriccion_inmediata_bajo' => '3.3', 'plazo_mejora_dias' => 30];
const CANCELACION = ['advertencia_desde' => 3, 'restriccion_desde' => 5, 'restriccion_dias' => 7];
const ACEPTACION = ['umbral' => '0.60', 'minimo_ofertas' => 5];

function hechos(array $cambios = []): HechosDeSancion
{
    return new HechosDeSancion(...[
        'cancelacionesEnVentana' => 0,
        'restriccionPorCancelacionesVigente' => false,
        'advertenciaPorCancelacionesVigente' => false,
        'hayRestriccionPorCancelacionesReciente' => false,
        'calificacionPromedio' => '4.60',
        'calificacionesConteo' => 30,
        'restriccionPorCalificacionVigente' => false,
        'advertenciaPorCalificacionVigente' => false,
        'diasDesdeAdvertenciaDeCalificacion' => null,
        'tasaAceptacion' => '0.9000',
        'ofertasRespondidasEnVentana' => 10,
        'advertenciaPorAceptacionVigente' => false,
        ...$cambios,
    ]);
}

function tipos(array $cambios): array
{
    return array_map(
        fn ($decision): string => $decision->tipo->value.':'.$decision->causa->value,
        PoliticaDeSanciones::evaluar(hechos($cambios), CALIFICACION, CANCELACION, ACEPTACION),
    );
}

test('un proveedor en regla no recibe ninguna sanción', function () {
    expect(tipos([]))->toBe([]);
});

test('tres cancelaciones en 30 días son una advertencia, una sola vez; cinco son una restricción temporal', function () {
    expect(tipos(['cancelacionesEnVentana' => 2]))->toBe([])
        ->and(tipos(['cancelacionesEnVentana' => 3]))->toBe(['advertencia:cancelaciones'])
        ->and(tipos(['cancelacionesEnVentana' => 4, 'advertenciaPorCancelacionesVigente' => true]))->toBe([])
        ->and(tipos(['cancelacionesEnVentana' => 5]))->toBe(['restriccion_ofertas:cancelaciones'])
        ->and(tipos(['cancelacionesEnVentana' => 6, 'restriccionPorCancelacionesVigente' => true]))->toBe([]);
});

test('la restricción por cancelaciones dura 7 días y la reincidencia abre una revisión de suspensión sin fecha', function () {
    $primera = PoliticaDeSanciones::evaluar(hechos(['cancelacionesEnVentana' => 5]), CALIFICACION, CANCELACION, ACEPTACION)[0];
    $reincidente = PoliticaDeSanciones::evaluar(hechos(['cancelacionesEnVentana' => 5, 'hayRestriccionPorCancelacionesReciente' => true]), CALIFICACION, CANCELACION, ACEPTACION)[0];

    expect($primera->tipo)->toBe(TipoSancion::RestriccionOfertas)->and($primera->diasDeVigencia)->toBe(7)
        ->and($reincidente->tipo)->toBe(TipoSancion::RevisionSuspension)->and($reincidente->diasDeVigencia)->toBeNull();
});

test('calificación bajo 4.0 es advertencia; bajo 3.3 restricción inmediata; bajo 3.7 pasado el plazo, restricción', function () {
    expect(tipos(['calificacionPromedio' => '3.90']))->toBe(['advertencia:calificacion'])
        ->and(tipos(['calificacionPromedio' => '3.90', 'advertenciaPorCalificacionVigente' => true]))->toBe([])
        ->and(tipos(['calificacionPromedio' => '3.20']))->toBe(['restriccion_ofertas:calificacion'])
        ->and(tipos(['calificacionPromedio' => '3.60', 'diasDesdeAdvertenciaDeCalificacion' => 10]))->toBe(['advertencia:calificacion'])
        ->and(tipos(['calificacionPromedio' => '3.60', 'diasDesdeAdvertenciaDeCalificacion' => 31]))->toBe(['restriccion_ofertas:calificacion']);
});

test('la calificación solo se evalúa con el mínimo de calificaciones', function () {
    expect(tipos(['calificacionPromedio' => '2.00', 'calificacionesConteo' => 9]))->toBe([]);
});

test('una tasa de aceptación bajo 60 % con ofertas suficientes es una advertencia, una sola vez', function () {
    expect(tipos(['tasaAceptacion' => '0.5000']))->toBe(['advertencia:aceptacion'])
        ->and(tipos(['tasaAceptacion' => '0.5000', 'ofertasRespondidasEnVentana' => 4]))->toBe([])
        ->and(tipos(['tasaAceptacion' => '0.5000', 'advertenciaPorAceptacionVigente' => true]))->toBe([])
        ->and(tipos(['tasaAceptacion' => '0.6000']))->toBe([]);
});

test('solo la advertencia deja recibir ofertas', function () {
    expect(TipoSancion::Advertencia->impideOfertas())->toBeFalse()
        ->and(TipoSancion::RestriccionOfertas->impideOfertas())->toBeTrue()
        ->and(TipoSancion::RevisionSuspension->impideOfertas())->toBeTrue();
});
