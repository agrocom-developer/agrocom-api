# Guion de prueba manual — portal del cliente (tarea 65)

1. `docker compose exec app php artisan migrate --seed --force` (dos veces: confirma que no duplica nada).
2. Entrá a `/portal/login` como `cliente.sanjorge` / `password`.
3. Anotá: hectáreas del avance, actas que aparecen en el listado, y el `id` de la primera acta (URL de su botón de PDF).
4. Cerrá sesión y entrá como `cliente.esperanza` / `password`.
5. Confirmá que avance, actas y reportes son OTROS (hectáreas y montos distintos a los de San Jorge).
6. Con la sesión de `cliente.esperanza` activa, pedí `GET /portal/actas/{id_de_sanjorge}/pdf` (el id del paso 3) → 404.
7. Al revés: con `cliente.sanjorge`, pedí el acta de `cliente.esperanza` → también 404.
