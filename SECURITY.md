# Keamanan dashboard

## Konfigurasi produksi

Aplikasi mendukung kredensial melalui environment variable agar hash kata
sandi tidak perlu diubah di source code:

- `DASHBOARD_USERNAME`
- `DASHBOARD_PASSWORD_HASH`
- `DASHBOARD_SESSION_SECRET` (wajib di Vercel, minimal 32 karakter)

Buat hash Argon2id baru dengan PHP:

```sh
php -r "echo password_hash('KATA_SANDI_BARU', PASSWORD_ARGON2ID), PHP_EOL;"
```

Atur kedua environment variable tersebut pada konfigurasi Apache/PHP, lalu
restart Apache. Jika environment variable tidak tersedia, aplikasi memakai
akun awal bawaan.

## Rekomendasi server

1. Gunakan HTTPS untuk akses selain `localhost`. Cookie session otomatis
   memakai flag `Secure` saat aplikasi dibuka melalui HTTPS.
2. Jangan membuka port Apache langsung ke internet. Gunakan firewall, VPN,
   atau reverse proxy dengan TLS.
3. Batasi hak tulis akun Apache hanya ke folder CSV dan `storage/security`.
4. Cadangkan data CSV secara rutin dan jangan menyimpan cadangan di web root.
5. Tinjau `storage/security/events.log` untuk percobaan login gagal, session
   yang ditolak, dan kegagalan CSRF.
6. Ganti kata sandi secara berkala melalui `DASHBOARD_PASSWORD_HASH`.
7. Untuk Vercel, hubungkan PostgreSQL dan jangan pernah menaruh
   `DATABASE_URL` atau session secret di source code.

## Kontrol yang aktif

- Hash kata sandi Argon2id dan dukungan konfigurasi environment.
- Batas percobaan login per akun/IP dan per IP dengan lockout 15 menit.
- Penundaan acak pada login gagal untuk memperlambat brute-force.
- Session ID 384-bit di Apache atau cookie session bertanda tangan di Vercel,
  idle timeout 30 menit, dan batas maksimal session 8 jam.
- Cookie `HttpOnly`, `SameSite=Strict`, serta `Secure` pada HTTPS.
- Session terikat ke alamat IP dan user-agent.
- Token CSRF untuk login, logout, dan seluruh perubahan data API.
- CSP dan security headers untuk mengurangi XSS, clickjacking, MIME sniffing,
  dan kebocoran referrer.
- API hanya dapat diakses dari origin aplikasi dan tidak lagi membuka CORS.
- Library JavaScript dipatok versinya dan disajikan secara lokal, bukan
  dieksekusi langsung dari CDN pihak ketiga.
- Berkas CSV, log, rate-limit, dan helper autentikasi tidak dapat diakses
  langsung dari web.
