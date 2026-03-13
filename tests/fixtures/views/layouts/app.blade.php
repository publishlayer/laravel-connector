<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@hasSection('title')@yield('title')@else Test Layout @endif</title>
    @stack('head')
</head>
<body>
    @yield('content')
</body>
</html>
