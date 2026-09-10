<?php

/*
 * Buscador global del panel (`/panel/buscar`, 9/9/2026).
 *
 * `bloques` son los rótulos de cada grupo de resultados: la clave coincide
 * con la que devuelve `ProveedorBusqueda::clave()`, y la resuelve la clase
 * base del proveedor. Un bloque nuevo se rotula agregando su clave acá.
 */

return [

    'titulo' => 'Resultados de búsqueda',
    'titulo_sin_consulta' => 'Buscar',
    'subtitulo' => 'Coincidencias en todo lo que podés ver con tu rol activo.',

    'resumen' => ':total coincidencias para «:consulta»',
    'resumen_uno' => '1 coincidencia para «:consulta»',

    'vacio_titulo' => 'Sin coincidencias para «:consulta»',
    'vacio_ayuda' => 'Probá con menos palabras, o con una parte del nombre: la búsqueda encuentra el texto aunque esté en el medio.',

    'inicial_titulo' => 'Escribí algo para buscar',
    'inicial_ayuda' => 'Se busca en clientes, propiedades, lotes, campañas, personas, drones y equipos. Podés escribir varias palabras y no hace falta que estén en orden ni con acentos.',

    'corta_titulo' => 'Escribí un poco más',
    'corta_ayuda' => 'Con una sola letra entra casi todo. Probá con al menos dos.',

    'ver_todos' => 'Ver los :total en :bloque',

    'bloques' => [
        'clientes' => 'Clientes',
        'personas' => 'Personas',
        'propiedades' => 'Propiedades',
        'campos' => 'Campos',
        'drones' => 'Drones',
        'lotes' => 'Lotes',
        'campanias' => 'Campañas',
        'cultivos' => 'Cultivos',
        'equipos_trabajo' => 'Equipos de trabajo',
        'bases' => 'Bases',
        'baterias' => 'Baterías',
        'vehiculos' => 'Vehículos',
        'generadores' => 'Generadores',
        'repuestos' => 'Repuestos',
    ],

    'detalle' => [
        'nit' => 'NIT :nit',
        'hectareas' => ':hectareas ha',
        'ciclos' => ':ciclos ciclos',
    ],

];
