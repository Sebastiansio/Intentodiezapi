<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Aplicación')</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-GTPkKgd6Y5j2k2m6G1k2JY1m3Q6z9ZlQ2u6r9ZlQ2u6r9ZlQ2u6r9ZlQ2u6r9ZlQ" crossorigin="anonymous">
    @stack('css')
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
    <a class="navbar-brand" href="/">Sinacol</a>
</nav>

<main>
    @yield('content')
</main>

<!-- Use full jQuery (required by some plugins) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJ+YRFp2v3Qm6a6QeY5f3b6a5j5j5j5j5j5jE=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

@stack('scripts')
</body>
</html>
