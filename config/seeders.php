<?php

/*
|--------------------------------------------------------------------------
| Datos de los seeders (solo desarrollo)
|--------------------------------------------------------------------------
|
| Los seeders NUNCA corren en producción (ver DatabaseSeeder y UserSeeder). Acá se lee, desde
| el .env, el administrador inicial que crea UserSeeder en un entorno nuevo. Si no hay
| contraseña, el seeder genera una aleatoria y la muestra una sola vez por consola. No hay
| credenciales escritas en el código.
|
*/

return [
    'admin_email' => env('SEED_ADMIN_EMAIL'),
    'admin_password' => env('SEED_ADMIN_PASSWORD'),
    'admin_nombre' => env('SEED_ADMIN_NOMBRE', 'Admin'),
];
