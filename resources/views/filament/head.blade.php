<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap">
@php($jozAdminCss = public_path('assets/css/joz-admin.css'))
<link rel="stylesheet" href="{{ asset('assets/css/joz-admin.css') }}?v={{ is_file($jozAdminCss) ? filemtime($jozAdminCss) : 1 }}">
