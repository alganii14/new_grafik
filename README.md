# Dashboard Grafik DPK & BriLink

Aplikasi tetap dapat dijalankan dengan XAMPP seperti sebelumnya dan sudah
disiapkan untuk deployment dari GitHub ke Vercel.

## Menjalankan secara lokal

Pastikan Apache/PHP aktif, lalu buka folder proyek melalui localhost. Pada mode
lokal, perubahan data disimpan langsung ke berkas di folder `csv_*`.

## Deployment ke Vercel

1. Push repository ini ke GitHub.
2. Di Vercel, pilih **Add New Project**, import repository GitHub, dan biarkan
   Framework Preset sebagai **Other**. Konfigurasi runtime dan route sudah ada
   di `vercel.json`.
3. Tambahkan PostgreSQL (misalnya Neon) melalui menu **Storage/Marketplace**
   pada project Vercel dan hubungkan ke project. Pastikan integration tersebut
   membuat environment variable `DATABASE_URL`.
4. Tambahkan environment variable berikut untuk Production, Preview, dan
   Development:

   - `DASHBOARD_USERNAME`: username login produksi.
   - `DASHBOARD_PASSWORD_HASH`: hash Argon2id kata sandi.
   - `DASHBOARD_SESSION_SECRET`: string acak minimal 32 karakter.

5. Jalankan **Redeploy** setelah database dan environment variable tersedia.

Tabel `dashboard_csv_files` dibuat otomatis ketika aplikasi pertama kali
terhubung ke database. Data awal tetap dibaca dari CSV repository; setiap data
yang diubah melalui dashboard akan disimpan secara persisten ke PostgreSQL.

Membuat hash kata sandi dan secret dengan PHP:

```powershell
php -r "echo password_hash('GANTI_DENGAN_PASSWORD_KUAT', PASSWORD_ARGON2ID), PHP_EOL;"
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Jangan commit nilai asli environment variable atau file `.env` ke Git.

## Push ke GitHub

Periksa perubahan sebelum commit karena folder CSV berisi data dashboard:

```powershell
git status
git add -A
git commit -m "Siapkan deployment Vercel"
git push origin main
```
