# 🏛️ Gov-LAMP — Multi-PHP Development Environment

[![PHP Versions](https://img.shields.io/badge/PHP-7.4%20%7C%208.0%20%7C%208.1%20%7C%208.2%20%7C%208.3-777bb4?logo=php&logoColor=white)](https://php.net)
[![MariaDB](https://img.shields.io/badge/MariaDB-10.6-003545?logo=mariadb&logoColor=white)](https://mariadb.org)
[![phpMyAdmin](https://img.shields.io/badge/phpMyAdmin-8888-orange?logo=phpmyadmin&logoColor=white)](http://localhost:8888)
[![Redis](https://img.shields.io/badge/Redis-6379-dc382d?logo=redis&logoColor=white)](https://redis.io)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ed?logo=docker&logoColor=white)](https://docker.com)

Lingkungan pengembangan lokal modern berbasis **Docker Compose** yang dirancang khusus untuk menangani berbagai project web PHP legacy hingga modern (Laravel 8–11, CodeIgniter 3/4, Native PHP) secara terisolasi tanpa perlu menginstall PHP atau MySQL di mesin host.

---

## 🌟 Fitur Unggulan

- **Multi-PHP Paralel / On-Demand**: Mendukung PHP **7.4, 8.0, 8.1, 8.2, dan 8.3** sekaligus dalam port berbeda.
- **Interactive Terminal Launcher (`gov`)**: Menu terminal interaktif untuk menyalakan/mematikan container hanya dengan memilih nomor.
- **Smart Web Dashboard**:
  - Deteksi otomatis framework project (**Laravel**, **CodeIgniter 3**, **CodeIgniter 4**, **PHP Native**).
  - Indikator status container aktif secara *real-time* (live ping).
  - Tombol **`< / > VS Code`** untuk membuka project langsung di editor.
  - Modal **`⚙️ Setting`** untuk menentukan versi PHP per-project via file `.ws`.
- **CLI Wrappers**:
  - `php74`, `php80`, `php81`, `php82`, `php83`
  - `composer74` s/d `composer83`
  - `artisan74` s/d `artisan83`
  - Perintah pintar `artisan` & `composer` otomatis membaca versi PHP yang dibutuhkan project.
  - Perintah `fix-perms` untuk memperbaiki masalah permission folder `storage/` dan `bootstrap/cache/` Laravel secara instan.

---

## 🔌 Daftar Port Layanan

| Layanan | Versi / Tipe | URL / Host | Port |
| :--- | :--- | :--- | :--- |
| **PHP 7.4** | Apache (Bullseye) | `http://localhost:8074` | `8074` |
| **PHP 8.0** | Apache (Bullseye) | `http://localhost:8080` | `8080` |
| **PHP 8.1** | Apache (Bullseye) | `http://localhost:8081` | `8081` |
| **PHP 8.2** | Apache (Bookworm) | `http://localhost:8082` | `8082` |
| **PHP 8.3** | Apache (Bookworm) | `http://localhost:8083` | `8083` |
| **MariaDB** | 10.6 | `127.0.0.1` / `database` | `3306` |
| **phpMyAdmin** | Web GUI DB | `http://localhost:8888` | `8888` |
| **Redis** | In-Memory Cache | `127.0.0.1` / `redis` | `6379` |

### 🔑 Kredensial Database Default
- **Host**: `127.0.0.1` (dari aplikasi di host) atau `database` (dari file `.env` Laravel di Docker)
- **Port**: `3306`
- **Username**: `root` atau `docker`
- **Password**: `tiger` atau `docker`
- **Database**: `docker`

---

## 🚀 Panduan Mulai Cepat (Quick Start)

### 1. Clone Repository
```bash
git clone git@github.com:hendrasenpai/gov-lamp.git ~/gov-lamp
cd ~/gov-lamp
```

### 2. Siapkan File Environment
```bash
cp .env.example .env
```

### 3. Jalankan Menggunakan Interactive Launcher (`gov`)
```bash
chmod +x cli/gov
./cli/gov
```
Pilih opsi:
- Ketik `1` untuk memilih versi PHP tertentu (misal: hanya PHP 7.4 atau PHP 8.2).
- Ketik `2` untuk menjalankan mode rekomendasi default (PHP 7.4 + MariaDB + phpMyAdmin + Redis).
- Ketik `3` untuk menyalakan seluruh versi PHP secara bersamaan.

Buka browser di:
👉 **`http://localhost:8074`** atau **`http://localhost:8083`**

---

## 🛠️ Instalasi CLI Helper ke Terminal Host (Opsional tapi Sangat Direkomendasikan)

Agar Anda bisa menjalankan perintah `gov`, `php74`, `composer82`, `artisan`, dan `fix-perms` dari direktori mana saja di terminal:

1. **Buat Symlink `gov` Launcher**:
   ```bash
   mkdir -p ~/bin
   ln -sf ~/gov-lamp/cli/gov ~/bin/gov
   ```
   *(Pastikan `~/bin` ada di dalam `$PATH` Anda).*

2. **Daftarkan Shell Helpers**:
   Tambahkan baris berikut di baris paling bawah file `~/.bashrc` atau `~/.zshrc`:
   ```bash
   source ~/gov-lamp/cli/docker-php-helpers.sh
   ```
   Lalu reload terminal:
   ```bash
   source ~/.bashrc
   ```

Setelah itu, Anda bisa langsung mengetik `gov` di mana saja untuk mengontrol container!

---

## 📁 Struktur Direktori

```text
gov-lamp/
├── bin/                 # Dockerfiles untuk masing-masing versi PHP & MariaDB
│   ├── mariadb106/
│   ├── php74/
│   ├── php8/
│   ├── php81/
│   ├── php82/
│   └── php83/
├── cli/                 # Script pembantu terminal
│   ├── gov              # Interactive terminal launcher
│   └── docker-php-helpers.sh # Shell functions & auto router
├── config/              # Konfigurasi Apache, PHP.ini, dan vhosts
│   ├── php/
│   ├── phpmyadmin/
│   └── vhosts/
├── data/                # Data persistent MariaDB (di-ignore oleh git)
├── logs/                # Apache & MySQL logs (di-ignore oleh git)
├── www/                 # Tempat menaruh folder project PHP Anda
│   ├── index.php        # Dashboard utama interaktif
│   └── test_db.php      # Skrip verifikasi koneksi MariaDB
├── docker-compose.yml   # Definisi orkestrasi container Docker
├── .env.example         # Template konfigurasi environment
└── README.md
```

---

## ⚙️ Konfigurasi Project (`.ws`)

Dashboard akan otomatis mendeteksi framework Anda. Jika Anda ingin mengunci project tertentu agar selalu menggunakan versi PHP tertentu, cukup klik tombol **`⚙️ Setting`** di dashboard web, atau buat file `.ws` di dalam folder project Anda:

Contoh file `www/my-project/.ws`:
```ini
php=7.4
type=laravel
entry=public
```

Saat Anda berada di dalam folder project tersebut dan menjalankan:
```bash
artisan migrate
```
Sistem akan otomatis mengeksekusi artisan di dalam container **PHP 7.4**!

---

## 📄 Lisensi
Open Source di bawah lisensi MIT. Silakan gunakan dan sesuaikan untuk kebutuhan kerja tim Anda!
