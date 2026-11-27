<?php

use App\Dominios\Proveedores\Aplicacion\EliminacionDeProveedor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Soporte\Esquema;
use Tests\TestCase;

/*
 * Lo que Proveedores aporta a la eliminación de una cuenta de proveedor (ADR 0047, puntos 3 y 6): los pilotos vinculados
 * activos la impiden; al ejecutarla se borran los documentos con sus archivos y los datos personales de la ficha.
 */

uses(TestCase::class);

const EP_TITULAR = 601;
const EP_PILOTO = 602;

function epMundo(string $estadoDelOtroPiloto = 'aprobado'): void
{
    Esquema::migrar(['create_plt_bitacoras_table', 'create_sol_zonas_table', 'create_prv_proveedores_table', 'create_prv_pilotos_table', 'create_prv_documentos_table', 'retira_zonas']);

    foreach (['prv_documentos', 'prv_pilotos', 'prv_proveedores'] as $tabla) {
        DB::table($tabla)->delete();
    }

    $ahora = now();
    DB::table('prv_proveedores')->insert(['id' => 1, 'usuario_titular_id' => EP_TITULAR, 'tipo' => 'tercero', 'razon_social' => 'Mamani Agroservicios', 'documento_fiscal' => '4521897012', 'telefono' => '+59170012345', 'estado' => 'aprobado', 'created_at' => $ahora, 'updated_at' => $ahora]);
    DB::table('prv_pilotos')->insert([
        ['id' => 11, 'proveedor_id' => 1, 'usuario_id' => EP_TITULAR, 'licencia_numero' => 'RPA-1', 'licencia_vence' => '2027-12-31', 'categoria_habilitacion' => 'RPAS', 'estado' => 'aprobado', 'created_at' => $ahora, 'updated_at' => $ahora],
        ['id' => 12, 'proveedor_id' => 1, 'usuario_id' => EP_PILOTO, 'licencia_numero' => 'RPA-2', 'licencia_vence' => '2027-12-31', 'categoria_habilitacion' => 'RPAS', 'estado' => $estadoDelOtroPiloto, 'created_at' => $ahora, 'updated_at' => $ahora],
    ]);

    Storage::fake('local');
    Storage::disk('local')->put('proveedores/1/documentos/licencia.pdf', 'x');
    DB::table('prv_documentos')->insert(['sujeto_tipo' => 'proveedor', 'sujeto_id' => 1, 'tipo' => 'licencia_piloto', 'archivo_ref' => 'proveedores/1/documentos/licencia.pdf', 'mime' => 'application/pdf', 'bytes' => 1, 'estado' => 'aprobado', 'subido_por' => EP_TITULAR, 'created_at' => $ahora, 'updated_at' => $ahora]);
}

it('un piloto vinculado activo impide que el titular elimine su cuenta', function () {
    epMundo();

    expect(app(EliminacionDeProveedor::class)->impedimentos(EP_TITULAR))->toBe(['pilotos_vinculados']);
});

it('un piloto no aprobado o el propio titular no lo impiden, ni al piloto', function () {
    epMundo('pendiente');
    expect(app(EliminacionDeProveedor::class)->impedimentos(EP_TITULAR))->toBe([]);

    epMundo();
    expect(app(EliminacionDeProveedor::class)->impedimentos(EP_PILOTO))->toBe([]);
});

it('al ejecutar borra los documentos con su archivo y los datos personales de la ficha', function () {
    epMundo('pendiente');

    app(EliminacionDeProveedor::class)->anonimizar(EP_TITULAR);

    expect(DB::table('prv_documentos')->count())->toBe(0);
    Storage::disk('local')->assertMissing('proveedores/1/documentos/licencia.pdf');
    $ficha = DB::table('prv_proveedores')->first();
    expect($ficha->razon_social)->toBe('Cuenta eliminada')->and($ficha->documento_fiscal)->toBeNull()->and($ficha->telefono)->toBeNull();
    expect(DB::table('prv_pilotos')->where('id', 11)->value('licencia_numero'))->toBe('-');
});
