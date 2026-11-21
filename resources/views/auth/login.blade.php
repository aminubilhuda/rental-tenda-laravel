<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Masuk · CampRent</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="login-body">
<main class="login-shell">
    <section class="login-art"><div class="brand-mark">⌁</div><div class="art-copy"><span class="eyebrow">OUTDOOR RENTAL MANAGEMENT</span><h1>Petualangan dimulai dari persiapan yang tepat.</h1><p>Kelola persewaan perlengkapan camping dengan lebih rapi, cepat, dan siap berangkat.</p></div><div class="art-foot">CAMPRENT <span>•</span> SISTEM MANAJEMEN PERSEWAAN</div></section>
    <section class="login-panel"><div class="login-form-wrap"><span class="eyebrow green">SELAMAT DATANG KEMBALI</span><h2>Masuk ke akun</h2><p class="muted">Gunakan username dan password Anda untuk melanjutkan.</p>
        @if ($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
        <form action="{{ route('login.store') }}" method="POST" class="stack-form">@csrf
            <label>Username<input name="username" value="{{ old('username') }}" required autofocus autocomplete="username" placeholder="Masukkan username"></label>
            <label>Password<input type="password" name="password" required autocomplete="current-password" placeholder="Masukkan password"></label>
            <label class="check-label"><input type="checkbox" name="remember" value="1"> Ingat saya di perangkat ini</label>
            <button class="button button-primary button-wide" type="submit">Masuk ke dashboard <span>↗</span></button>
        </form><div class="login-help"><strong>Akun demo</strong><span>owner / password</span><span>admin / password</span><span>kasir / password</span><small>Ganti password awal setelah masuk.</small></div>
    </div><footer>© {{ now()->year }} CampRent <span>•</span> Dibuat untuk alam bebas</footer></section>
</main></body></html>
