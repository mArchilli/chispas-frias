# Chispas Frías

## Actualizar una copia existente del proyecto

Los cambios se comparten a través de la rama `main` de `https://github.com/mArchilli/chispas-frias`.
Desde la carpeta del proyecto, revisá `git status` y guardá tus cambios locales en un commit antes de actualizar si tenés trabajo pendiente. Luego ejecutá, deteniéndote si algún comando falla:

```sh
git switch main
git pull --ff-only origin main
composer install
npm ci
php artisan optimize:clear
php artisan migrate
npm run build
```

El archivo `.env` debe estar configurado para tu entorno y tu base de datos antes de ejecutar los comandos de Artisan. `migrate` aplica las migraciones pendientes; no es necesario recrear la base.

Si `git pull --ff-only` indica que las ramas divergieron, hay commits locales y remotos que necesitan integrarse antes de continuar. Conservá los cambios locales y resolvé la integración con el equipo.

El frontend compilado (`public/build`) no se guarda en Git: después de actualizar los componentes React o CSS hay que ejecutar `npm run build`. Para desarrollo con recarga automática, usá `npm run dev` en una terminal mientras corre Laravel. Si Vite ya estaba abierto, reinicialo después de actualizar las dependencias o el `.env`.

Las imágenes guardadas en `public/images/products` que estén incluidas en commits se descargan con el repositorio. Los productos, precios, stock, pedidos y configuraciones cargados desde el panel se guardan en la base de datos; Git no los sincroniza entre computadoras. Para compartir ese contenido se necesita una base de desarrollo compartida o una exportación de los datos correspondientes. El `.env` y sus credenciales tampoco se comparten por Git.

Para comprobar que una copia está al día, ejecutá `git fetch origin` y `git status -sb`: `main` debe estar sincronizada con `origin/main`, sin commits pendientes de subir o bajar.

Para compartir nuevos cambios, incluí los archivos necesarios en un commit y ejecutá `git push origin main`. Un commit que queda solamente en tu computadora todavía no está disponible para el resto del equipo.

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
