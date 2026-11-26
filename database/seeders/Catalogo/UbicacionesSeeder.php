<?php

namespace Database\Seeders\Catalogo;

use App\Dominios\Solicitudes\Infraestructura\Eloquent\Departamento;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Provincia;
use Illuminate\Database\Seeder;

/**
 * Catálogo oficial de ubicaciones de Bolivia (ADR 0045, punto 3): los 9
 * departamentos y las 112 provincias con su código del INE, de solo lectura
 * y sin geometría. Corre en todos los entornos, producción incluida. Idempotente
 * por código: correrlo de nuevo no duplica nada y corrige un nombre si cambió.
 */
class UbicacionesSeeder extends Seeder
{
    /** @var list<array{string, string, list<array{string, string}>}> código de departamento, nombre y sus provincias (código de cuatro dígitos, nombre) */
    private const DEPARTAMENTOS = [
        ['01', 'Chuquisaca', [
            ['0101', 'Oropeza'],
            ['0102', 'Azurduy'],
            ['0103', 'Zudáñez'],
            ['0104', 'Tomina'],
            ['0105', 'Hernando Siles'],
            ['0106', 'Yamparáez'],
            ['0107', 'Nor Cinti'],
            ['0108', 'Belisario Boeto'],
            ['0109', 'Sud Cinti'],
            ['0110', 'Luis Calvo'],
        ]],
        ['02', 'La Paz', [
            ['0201', 'Murillo'],
            ['0202', 'Omasuyos'],
            ['0203', 'Pacajes'],
            ['0204', 'Camacho'],
            ['0205', 'Muñecas'],
            ['0206', 'Larecaja'],
            ['0207', 'Franz Tamayo'],
            ['0208', 'Ingavi'],
            ['0209', 'Loayza'],
            ['0210', 'Inquisivi'],
            ['0211', 'Sud Yungas'],
            ['0212', 'Los Andes'],
            ['0213', 'Aroma'],
            ['0214', 'Nor Yungas'],
            ['0215', 'Abel Iturralde'],
            ['0216', 'Bautista Saavedra'],
            ['0217', 'Manco Kapac'],
            ['0218', 'Gualberto Villarroel'],
            ['0219', 'José Manuel Pando'],
            ['0220', 'Caranavi'],
        ]],
        ['03', 'Cochabamba', [
            ['0301', 'Cercado'],
            ['0302', 'Campero'],
            ['0303', 'Ayopaya'],
            ['0304', 'Esteban Arce'],
            ['0305', 'Arani'],
            ['0306', 'Arque'],
            ['0307', 'Capinota'],
            ['0308', 'Germán Jordán'],
            ['0309', 'Quillacollo'],
            ['0310', 'Chapare'],
            ['0311', 'Tapacarí'],
            ['0312', 'Carrasco'],
            ['0313', 'Mizque'],
            ['0314', 'Punata'],
            ['0315', 'Bolívar'],
            ['0316', 'Tiraque'],
        ]],
        ['04', 'Oruro', [
            ['0401', 'Cercado'],
            ['0402', 'Eduardo Abaroa'],
            ['0403', 'Carangas'],
            ['0404', 'Sajama'],
            ['0405', 'Litoral'],
            ['0406', 'Poopó'],
            ['0407', 'Pantaleón Dalence'],
            ['0408', 'Ladislao Cabrera'],
            ['0409', 'Sabaya'],
            ['0410', 'Saucarí'],
            ['0411', 'Tomás Barrón'],
            ['0412', 'Sur Carangas'],
            ['0413', 'San Pedro de Totora'],
            ['0414', 'Sebastián Pagador'],
            ['0415', 'Mejillones'],
            ['0416', 'Nor Carangas'],
        ]],
        ['05', 'Potosí', [
            ['0501', 'Tomás Frías'],
            ['0502', 'Rafael Bustillo'],
            ['0503', 'Cornelio Saavedra'],
            ['0504', 'Chayanta'],
            ['0505', 'Charcas'],
            ['0506', 'Nor Chichas'],
            ['0507', 'Alonso de Ibáñez'],
            ['0508', 'Sud Chichas'],
            ['0509', 'Nor Lípez'],
            ['0510', 'Sud Lípez'],
            ['0511', 'José María Linares'],
            ['0512', 'Antonio Quijarro'],
            ['0513', 'Bernardino Bilbao'],
            ['0514', 'Daniel Campos'],
            ['0515', 'Modesto Omiste'],
            ['0516', 'Enrique Baldivieso'],
        ]],
        ['06', 'Tarija', [
            ['0601', 'Cercado'],
            ['0602', 'Aniceto Arce'],
            ['0603', 'Gran Chaco'],
            ['0604', 'José María Avilés'],
            ['0605', 'Méndez'],
            ['0606', 'Burdett O\'Connor'],
        ]],
        ['07', 'Santa Cruz', [
            ['0701', 'Andrés Ibáñez'],
            ['0702', 'Warnes'],
            ['0703', 'Velasco'],
            ['0704', 'Ichilo'],
            ['0705', 'Chiquitos'],
            ['0706', 'Sara'],
            ['0707', 'Cordillera'],
            ['0708', 'Vallegrande'],
            ['0709', 'Florida'],
            ['0710', 'Obispo Santistevan'],
            ['0711', 'Ñuflo de Chávez'],
            ['0712', 'Ángel Sandóval'],
            ['0713', 'Caballero'],
            ['0714', 'Germán Busch'],
            ['0715', 'Guarayos'],
        ]],
        ['08', 'Beni', [
            ['0801', 'Cercado'],
            ['0802', 'Vaca Díez'],
            ['0803', 'José Ballivián'],
            ['0804', 'Yacuma'],
            ['0805', 'Moxos'],
            ['0806', 'Marbán'],
            ['0807', 'Mamoré'],
            ['0808', 'Iténez'],
        ]],
        ['09', 'Pando', [
            ['0901', 'Nicolás Suárez'],
            ['0902', 'Manuripi'],
            ['0903', 'Madre de Dios'],
            ['0904', 'Abuná'],
            ['0905', 'Federico Román'],
        ]],
    ];

    public function run(): void
    {
        foreach (self::DEPARTAMENTOS as [$codigo, $nombre, $provincias]) {
            Departamento::query()->updateOrCreate(['codigo' => $codigo], ['nombre' => $nombre]);

            foreach ($provincias as [$codigoProvincia, $nombreProvincia]) {
                Provincia::query()->updateOrCreate(
                    ['codigo' => $codigoProvincia],
                    ['departamento_codigo' => $codigo, 'nombre' => $nombreProvincia],
                );
            }
        }
    }
}
