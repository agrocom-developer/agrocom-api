<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Demo;

/**
 * ============================== MOCK ==============================
 * Datos de DEMO del tab "Multimedia" del dashboard: las 21 capturas reales
 * de `docs/gestion/respuestas_campo/capturas_rc/` (fotos de la pantalla del
 * control remoto del dron durante el vuelo — evidencia real "RC" del
 * repo, copiadas a `public/demo/capturas-rc/` como excepción deliberada al
 * ADR 0009, que reserva `public/` a assets de marca: son fixtures de
 * demo, no evidencia real fluyendo por el pipeline de R2 con URL firmada).
 *
 * Agrupadas en 10 "sesiones de vuelo" narrativas (identidad de piloto/lote/
 * fecha inventada, mismo criterio que {@see DatosDemoPanel::sesiones()});
 * las cifras de rendimiento (hectáreas, tiempo de vuelo, litros de
 * pesticida) SON reales, transcritas en
 * `docs/especificacion/analisis_capturas_rc.md` §8 — la pantalla del RC no
 * muestra identidad de sesión, solo rendimiento (ver §4 de ese documento).
 * ==================================================================
 */
final class DatosDemoCapturasRc
{
    /**
     * @return list<array{
     *     sesion: string, fecha: string, piloto: string, lote: string, dron: string,
     *     hectareas: string, tiempoVuelo: string, pesticidaLitros: string,
     *     capturas: list<array{archivo: string, tipo: string, descripcion: string}>
     * }>
     */
    public function sesiones(): array
    {
        return [
            [
                'sesion' => 'S-01', 'fecha' => '01/08', 'piloto' => 'R. Vaca', 'lote' => 'Lote 12 — San Marcos', 'dron' => 'T50 · AG-04',
                'hectareas' => '9,82 ha', 'tiempoVuelo' => '4:56', 'pesticidaLitros' => '9,8 L · 12,15 L/ha',
                'capturas' => [
                    ['archivo' => 'rc_14.jpeg', 'tipo' => 'mapa', 'descripcion' => 'Mapa de misión — inicio de vuelo'],
                    ['archivo' => 'rc_01.jpeg', 'tipo' => 'hud', 'descripcion' => 'Retoma en vuelo — alerta de pesticida'],
                    ['archivo' => 'rc_03.jpeg', 'tipo' => 'carta', 'descripcion' => 'Carta de confirmación de efecto laboral'],
                ],
            ],
            [
                'sesion' => 'S-02', 'fecha' => '04/08', 'piloto' => 'M. Ordóñez', 'lote' => 'Lote 3 — El Carmen', 'dron' => 'T70 · AG-07',
                'hectareas' => '24,5 ha', 'tiempoVuelo' => '3:34', 'pesticidaLitros' => '6,5 L · 10,50 L/ha',
                'capturas' => [
                    ['archivo' => 'rc_15.jpeg', 'tipo' => 'mapa', 'descripcion' => 'Mapa de misión — previo al despegue'],
                    ['archivo' => 'rc_02.jpeg', 'tipo' => 'hud', 'descripcion' => 'Retoma en vuelo — nivel de batería'],
                    ['archivo' => 'rc_06.jpeg', 'tipo' => 'carta', 'descripcion' => 'Carta de confirmación de efecto laboral'],
                ],
            ],
            [
                'sesion' => 'S-03', 'fecha' => '07/08', 'piloto' => 'J. Peña', 'lote' => 'Lote 8 — El Carmen', 'dron' => 'T30 · AG-02',
                'hectareas' => '8,21 ha', 'tiempoVuelo' => '8:10', 'pesticidaLitros' => '20,8 L · 9,15 L/ha',
                'capturas' => [
                    ['archivo' => 'rc_16.jpeg', 'tipo' => 'mapa', 'descripcion' => 'Mapa de misión — obstáculos marcados'],
                    ['archivo' => 'rc_04.jpeg', 'tipo' => 'hud', 'descripcion' => 'Retoma en vuelo — alerta de pesticida'],
                    ['archivo' => 'rc_07.jpeg', 'tipo' => 'carta', 'descripcion' => 'Carta de confirmación de efecto laboral'],
                ],
            ],
            [
                'sesion' => 'S-04', 'fecha' => '10/08', 'piloto' => 'M. Ordóñez', 'lote' => 'Lote 1 — Santa Rosa', 'dron' => 'T100 · AG-09',
                'hectareas' => '15,8 ha', 'tiempoVuelo' => '2:54', 'pesticidaLitros' => '2,7 L · 10,06 L/ha',
                'capturas' => [
                    ['archivo' => 'rc_05.jpeg', 'tipo' => 'hud', 'descripcion' => 'Retoma en vuelo'],
                    ['archivo' => 'rc_09.jpeg', 'tipo' => 'carta', 'descripcion' => 'Carta de confirmación de efecto laboral'],
                    ['archivo' => 'rc_10.jpeg', 'tipo' => 'carta', 'descripcion' => 'Carta de confirmación — segunda captura'],
                ],
            ],
            [
                'sesion' => 'S-05', 'fecha' => '13/08', 'piloto' => 'R. Vaca', 'lote' => 'Lote 12 — San Marcos', 'dron' => 'T50 · AG-04',
                'hectareas' => '12,3 ha', 'tiempoVuelo' => '5:45', 'pesticidaLitros' => '10,4 L · 10,69 L/ha',
                'capturas' => [
                    ['archivo' => 'rc_08.jpeg', 'tipo' => 'hud', 'descripcion' => 'Retoma en vuelo'],
                    ['archivo' => 'rc_13.jpeg', 'tipo' => 'carta', 'descripcion' => 'Carta de confirmación de efecto laboral'],
                ],
            ],
            [
                'sesion' => 'S-06', 'fecha' => '16/08', 'piloto' => 'M. Ordóñez', 'lote' => 'Lote 3 — El Carmen', 'dron' => 'T70 · AG-07',
                'hectareas' => '3,87 ha', 'tiempoVuelo' => '7:38', 'pesticidaLitros' => '13,2 L · 10,05 L/ha',
                'capturas' => [
                    ['archivo' => 'rc_12.jpeg', 'tipo' => 'hud', 'descripcion' => 'Retoma en vuelo — batería bajo nivel 1'],
                    ['archivo' => 'rc_18.jpeg', 'tipo' => 'carta', 'descripcion' => 'Carta de confirmación de efecto laboral'],
                ],
            ],
            [
                'sesion' => 'S-07', 'fecha' => '19/08', 'piloto' => 'J. Peña', 'lote' => 'Lote 8 — El Carmen', 'dron' => 'T30 · AG-02',
                'hectareas' => '17,6 ha', 'tiempoVuelo' => '5:10', 'pesticidaLitros' => '9,0 L · 10,05 L/ha',
                'capturas' => [
                    ['archivo' => 'rc_21.jpeg', 'tipo' => 'mapa', 'descripcion' => 'Mapa de misión — post-vuelo, pasadas pintadas'],
                    ['archivo' => 'rc_19.jpeg', 'tipo' => 'carta', 'descripcion' => 'Carta de confirmación de efecto laboral'],
                ],
            ],
            [
                'sesion' => 'S-08', 'fecha' => '22/08', 'piloto' => 'M. Ordóñez', 'lote' => 'Lote 1 — Santa Rosa', 'dron' => 'T100 · AG-09',
                'hectareas' => '1,82 ha', 'tiempoVuelo' => '10:40', 'pesticidaLitros' => '11,0 L',
                'capturas' => [
                    ['archivo' => 'rc_11.jpeg', 'tipo' => 'carta', 'descripcion' => 'Carta de confirmación — versión corta'],
                ],
            ],
            [
                'sesion' => 'S-09', 'fecha' => '25/08', 'piloto' => 'R. Vaca', 'lote' => 'Lote 12 — San Marcos', 'dron' => 'T50 · AG-04',
                'hectareas' => '1,04 ha', 'tiempoVuelo' => '5:59', 'pesticidaLitros' => '16,1 L',
                'capturas' => [
                    ['archivo' => 'rc_17.jpeg', 'tipo' => 'carta', 'descripcion' => 'Carta de confirmación — versión corta'],
                ],
            ],
            [
                'sesion' => 'S-10', 'fecha' => '28/08', 'piloto' => 'M. Ordóñez', 'lote' => 'Lote 3 — El Carmen', 'dron' => 'T70 · AG-07',
                'hectareas' => '3,82 ha', 'tiempoVuelo' => '7:10', 'pesticidaLitros' => '6,7 L',
                'capturas' => [
                    ['archivo' => 'rc_20.jpeg', 'tipo' => 'carta', 'descripcion' => 'Carta de confirmación — versión corta'],
                ],
            ],
        ];
    }
}
