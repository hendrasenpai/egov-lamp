# 🏛️ eGov-LAMP — Multi-PHP Development Environment

[![PHP Versions](https://img.shields.io/badge/PHP-7.4%20%7C%208.0%20%7C%208.1%20%7C%208.2%20%7C%208.3-777bb4?logo=php&logoColor=white)](https://php.net)
[![MariaDB](https://img.shields.io/badge/MariaDB-10.6-003545?logo=mariadb&logoColor=white)](https://mariadb.org)
[![phpMyAdmin](https://img.shields.io/badge/phpMyAdmin-8888-orange?logo=phpmyadmin&logoColor=white)](http://localhost:8888)
[![Redis](https://img.shields.io/badge/Redis-6379-dc382d?logo=redis&logoColor=white)](https://redis.io)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ed?logo=docker&logoColor=white)](https://docker.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

Lingkungan pengembangan lokal modern berbasis **Docker Compose** yang dirancang khusus untuk tim **Bidang e-Government Dinas Komunikasi dan Informatika Kabupaten Bintan**. 

Stack ini memungkinkan Anda menjalankan berbagai aplikasi web pemerintah daerah lintas generasi (mulai dari CodeIgniter 3, PHP Native lawas, hingga Laravel modern 8–11) secara terisolasi tanpa konflik versi PHP atau MySQL di mesin host.

---

## 📑 Daftar Isi
1. [🌟 Fitur Unggulan](#-fitur-unggulan)
2. [🔌 Pemetaan Port & Layanan](#-pemetaan-port--layanan)
3. [💻 Prasyarat Sistem](#-prasyarat-sistem)
4. [📦 Panduan Instalasi](#-panduan-instalasi)
   - [Pengguna Linux (Ubuntu, Debian, Fedora, Arch)](#-a-instalasi-di-linux)
   - [Pengguna macOS (Apple Silicon & Intel)](#-b-instalasi-di-macos)
   - [Pengguna Windows (PowerShell & Command Prompt)](#-c-instalasi-di-windows)
5. [🎮 Menggunakan Interactive Launcher (egov)](#-menggunakan-interactive-launcher-egov)
6. [⚡ Smart CLI & Perintah Terminal (Auto-Routing)](#-smart-cli--perintah-terminal-auto-routing)
7. [💡 Contoh Praktis Penggunaan (Real-World Examples)](#-contoh-praktis-penggunaan-real-world-examples)
   - [Contoh 1: Menjalankan Laravel PHP 8.3 — web_bintan](#contoh-1-menjalankan-laravel-php-83--web_bintan)
   - [Contoh 2: Menjalankan Laravel PHP 8.2 — b-smart](#contoh-2-menjalankan-laravel-php-82--b-smart)
   - [Contoh 3: Menjalankan CodeIgniter 3 PHP 7.4 — ak1_disnaker](#contoh-3-menjalankan-codeigniter-3-php-74--ak1_disnaker)
   - [Contoh 4: Menjalankan Script PHP Native Sederhana](#contoh-4-menjalankan-script-php-native-sederhana)
   - [Contoh 5: Menghubungkan GUI Database (DBeaver / Navicat / VS Code)](#contoh-5-menghubungkan-gui-database-dbeaver--navicat--vs-code)
8. [🎛️ Konfigurasi Project (.ws) & Dashboard Web](#️-konfigurasi-project-ws--dashboard-web)
9. [📁 Struktur Direktori](#-struktur-direktori)
10. [❓ Tanya Jawab & Solusi Masalah (Troubleshooting)](#-tanya-jawab--solusi-masalah-troubleshooting)

---

## 🌟 Fitur Unggulan

- **Multi-PHP Paralel / On-Demand**: Mendukung PHP **7.4, 8.0, 8.1, 8.2, dan 8.3** sekaligus pada port berbeda. Anda dapat menyalakan hanya PHP yang dibutuhkan untuk menghemat RAM laptop.
- **Interactive Control Center (`egov` / `egov.bat`)**: Antarmuka menu terminal interaktif untuk mengontrol container (start, stop, status, fix permissions) hanya dengan satu ketukan angka.
- **Smart Web Dashboard**:
  - Live indicator status container (lampu hijau/merah) secara *real-time*.
  - Deteksi otomatis jenis framework project (**Laravel**, **CodeIgniter 3**, **PHP Native**).
  - Tombol integrasi **`< / > VS Code`** untuk membuka project di editor host dalam satu klik.
  - Modal **`⚙️ Setting`** untuk mengatur versi PHP dan direktori publik per-project.
- **Smart Terminal CLI (Auto-Routing)**:
  - Cukup ketik `artisan` atau `composer` di dalam folder project, sistem otomatis mengeksekusi perintah di container PHP yang sesuai dengan kebutuhan project tersebut.
  - Tersedia shortcut eksplisit: `php74`..`php83`, `composer74`..`composer83`, `artisan74`..`artisan83`.
  - Fitur **Auto-Wakeup**: Jika container yang dipanggil belum aktif, helper CLI akan menyalakannya secara otomatis.
- **Auto-Fix Permissions (`fix-perms`)**: Perbaiki masalah *permission denied* pada folder `storage/`, `bootstrap/cache/`, dan file `.ws` dalam sekejap.

---

## 🔌 Pemetaan Port & Layanan

| Layanan | Versi / Tipe | URL Browser | Host Mesin | Port Host |
| :--- | :--- | :--- | :--- | :--- |
| **PHP 7.4** | Apache 2.4 (Debian Bullseye) | `http://localhost:8074` | `localhost` | `8074` *(SSL: `8474`)* |
| **PHP 8.0** | Apache 2.4 (Debian Bullseye) | `http://localhost:8080` | `localhost` | `8080` *(SSL: `8480`)* |
| **PHP 8.1** | Apache 2.4 (Debian Bookworm) | `http://localhost:8081` | `localhost` | `8081` *(SSL: `8481`)* |
| **PHP 8.2** | Apache 2.4 (Debian Bookworm) | `http://localhost:8082` | `localhost` | `8082` *(SSL: `8482`)* |
| **PHP 8.3** | Apache 2.4 (Debian Bookworm) | `http://localhost:8083` | `localhost` | `8083` *(SSL: `8483`)* |
| **MariaDB** | 10.6.x (Database Utama) | Akses via DBeaver/Navicat | `127.0.0.1` | `3306` |
| **phpMyAdmin** | Web Database Manager | `http://localhost:8888` | `localhost` | `8888` *(SSL: `8889`)* |
| **Redis** | In-Memory Cache & Session | Driver Redis / Redis CLI | `127.0.0.1` | `6379` |

### 🔑 Kredensial Database MariaDB
- **Host dari dalam container Docker** (misal file `.env` Laravel atau `database.php` CI3): `database`
- **Host dari mesin komputer Host** (misal DBeaver, TablePlus, Navicat, VS Code): `127.0.0.1` atau `localhost`
- **Port**: `3306`
- **User Root**: `root` | **Password**: `tiger`
- **User Default**: `docker` | **Password**: `docker`
- **Database Bawaan**: `docker`

---

## 💻 Prasyarat Sistem

Sebelum memasang, pastikan laptop / PC Anda telah terpasang:
1. **Docker Desktop** (untuk Windows & macOS) atau **Docker Engine + Docker Compose Plugin** (untuk Linux).
2. **Git**.
3. **RAM**: Minimal 4 GB (Rekomendasi 8 GB+). Fitur On-Demand memungkinkan Anda hanya menyalakan 1 versi PHP saat bekerja, sehingga sangat ringan di laptop spesifikasi standar.

---

## 📦 Panduan Instalasi

### 🐧 A. Instalasi di Linux
*(Ubuntu, Linux Mint, Debian, Fedora, Arch Linux, dll)*

#### 1. Pasang Docker & Docker Compose (Jika belum ada)
Untuk Ubuntu / Debian:
```bash
sudo apt update
sudo apt install -y docker.io docker-compose-plugin git
sudo usermod -aG docker $USER
```
> *Catatan: Setelah menjalankan `usermod`, logout lalu login kembali agar user Anda dapat menjalankan Docker tanpa `sudo`.*

#### 2. Clone Repository
```bash
git clone git@github.com:hendrasenpai/egov-lamp.git ~/workspace/egov
# Atau via HTTPS jika belum memasang SSH Key:
# git clone https://github.com/hendrasenpai/egov-lamp.git ~/workspace/egov

cd ~/workspace/egov
cp .env.example .env
chmod +x cli/egov
```

#### 3. Pasang CLI Helper ke Shell Anda
Daftarkan helper ke `~/.bashrc` (atau `~/.zshrc` jika memakai Zsh):
```bash
# Untuk pengguna Bash:
echo "source $(pwd)/cli/docker-php-helpers.sh" >> ~/.bashrc
source ~/.bashrc

# Untuk pengguna Zsh:
echo "source $(pwd)/cli/docker-php-helpers.sh" >> ~/.zshrc
source ~/.zshrc
```

#### 4. Selesai!
Ketik `egov` di terminal mana saja untuk mulai mengelola stack!

---

### 🍏 B. Instalasi di macOS
*(Apple Silicon M1/M2/M3/M4 & Intel)*

#### 1. Pasang Docker Desktop
Unduh dan install [Docker Desktop for Mac](https://www.docker.com/products/docker-desktop/). Buka Docker Desktop dan pastikan statusnya sudah **Running**.

#### 2. Clone Repository
Buka Terminal macOS:
```zsh
git clone git@github.com:hendrasenpai/egov-lamp.git ~/egov-lamp
# Atau via HTTPS:
# git clone https://github.com/hendrasenpai/egov-lamp.git ~/egov-lamp

cd ~/egov-lamp
cp .env.example .env
chmod +x cli/egov
```

#### 3. Pasang CLI Helper ke Zsh Terminal
```zsh
echo "source $(pwd)/cli/docker-php-helpers.sh" >> ~/.zshrc
source ~/.zshrc
```

#### 4. Selesai!
Ketik `egov` di Terminal untuk membuka Control Center!

---

### 🪟 C. Instalasi di Windows
*(Windows 10 / 11 Native dengan PowerShell & Command Prompt)*

#### 1. Pasang Docker Desktop for Windows
- Unduh dan pasang [Docker Desktop for Windows](https://www.docker.com/products/docker-desktop/).
- Pastikan opsi **WSL 2 backend** dicentang saat instalasi.
- Buka Docker Desktop hingga ikon paus di pojok kanan bawah (taskbar) berstatus hijau/running.

#### 2. Izinkan Eksekusi Script PowerShell
Buka **PowerShell** (bisa user biasa, tidak harus Admin), lalu ketik:
```powershell
Set-ExecutionPolicy RemoteSigned -Scope CurrentUser
```
*(Ketik `Y` lalu Enter jika ada konfirmasi).*

#### 3. Clone Repository
```powershell
git clone https://github.com/hendrasenpai/egov-lamp.git C:\egov-lamp
cd C:\egov-lamp
Copy-Item .env.example .env
```

#### 4. Pasang CLI Helper ke PowerShell Secara Otomatis
Jalankan file batch launcher:
```cmd
.\cli\egov.bat
```
*(Atau Anda bisa langsung klik dua kali file `egov.bat` di folder `cli` melalui File Explorer Windows).*

Di menu yang muncul:
- Pilih opsi **`[6] Pasang Shortcut CLI ke PowerShell`**.
- Tekan Enter. Script akan otomatis mendaftarkan fungsi helper ke profil PowerShell Anda (`$PROFILE`).
- Tutup dan buka kembali jendela PowerShell Anda.

Sekarang perintah `egov`, `artisan`, `composer`, `php74`..`php83`, dan `fix-perms` dapat dipanggil dari folder mana pun di Windows PowerShell!

---

## 🎮 Menggunakan Interactive Launcher (egov)

Untuk mengelola seluruh stack tanpa perlu mengingat perintah Docker yang panjang, jalankan:
- Di Linux/macOS: `egov`
- Di Windows: `egov` (di PowerShell) atau jalankan `cli\egov.bat`

Tampilan menu utama:
```text
=====================================================
   🏛️   E-GOVERNMENT DISKOMINFO KABUPATEN BINTAN      
       Multi-PHP Local Development Environment       
=====================================================
Direktori : /home/hendra/workspace/egov

Pilih aksi yang ingin dilakukan:
  1) Nyalakan PHP Tertentu (Pilih Versi: 7.4 - 8.3)
  2) Nyalakan Default / Rekomendasi (PHP 7.4 + DB + PMA)
  3) Nyalakan SEMUA Versi PHP Sekaligus
  4) Matikan Semua Container egov-lamp
  5) Cek Status Container Aktif
  6) Perbaiki Permission Folder (fix-perms)
  0) Keluar
```

### Penjelasan Pilihan Menu:
- **`[1] Nyalakan PHP Tertentu (On-Demand / Hemat RAM)`**:
  Pilihan terbaik jika laptop Anda memiliki memori terbatas. Anda dapat memilih hanya 1 atau beberapa versi PHP yang sedang Anda kerjakan.
  *Contoh input*:
  - Ketik `4` jika hanya ingin menyalakan **PHP 8.2** (otomatis menyalakan MariaDB, phpMyAdmin, Redis, dan PHP 8.2).
  - Ketik `1 4` jika ingin menyalakan **PHP 7.4** dan **PHP 8.2** secara bersamaan.
- **`[2] Nyalakan Default / Rekomendasi`**:
  Menyalakan paket standar aplikasi instansi: MariaDB + phpMyAdmin + Redis + PHP 7.4.
- **`[3] Nyalakan SEMUA Versi PHP Sekaligus`**:
  Menyalakan seluruh container (PHP 7.4, 8.0, 8.1, 8.2, 8.3, MariaDB, phpMyAdmin, Redis) secara paralel.
- **`[4] Matikan Semua Container egov-lamp`**:
  Mematikan seluruh layanan yang sedang berjalan secara bersih (`docker compose down`).
- **`[5] Cek Status Container Aktif`**:
  Menampilkan tabel container yang sedang hidup beserta port mapping-nya.
- **`[6] Perbaiki Permission Folder (fix-perms)`**:
  Mereset hak akses direktori `storage/` dan `bootstrap/cache/` Laravel menjadi writable (`777`) di semua project.

---

## ⚡ Smart CLI & Perintah Terminal (Auto-Routing)

Setelah memasang CLI helper, terminal Anda memiliki kemampuan eksekusi cerdas:

### 1. Smart Commands (Otomatis Deteksi Versi PHP)
Ketika Anda masuk ke folder project di dalam `www/`, Anda tidak perlu lagi mengingat apakah project ini memakai PHP 7.4 atau PHP 8.2. Cukup jalankan perintah standar:
```bash
# Otomatis menjalankan artisan pada versi PHP yang ditentukan di file .ws project
artisan migrate
artisan db:seed
artisan tinker

# Otomatis menjalankan composer pada versi PHP yang sesuai
composer install
composer update
composer require vendor/package
```

### 2. Explicit Commands (Bebas Memilih Versi Kapan Saja)
Jika Anda ingin memaksa menjalankan perintah dengan versi PHP tertentu:
- **PHP CLI**: `php74`, `php80`, `php81`, `php82`, `php83`
  ```bash
  php82 -v
  php74 script_testing.php
  ```
- **Composer**: `composer74`, `composer80`, `composer81`, `composer82`, `composer83`
  ```bash
  composer82 install
  ```
- **Artisan**: `artisan74`, `artisan80`, `artisan81`, `artisan82`, `artisan83`
  ```bash
  artisan82 optimize:clear
  ```

### 3. Auto-Wakeup & Path Translation Cerdas
- **Auto-Wakeup**: Jika Anda menjalankan `artisan82 migrate` tetapi container PHP 8.2 belum hidup, sistem akan secara otomatis menyalakan container tersebut tanpa Anda harus membuka Docker Desktop atau menu launcher terlebih dahulu!
- **Path Mapping**: Ketika Anda berada di folder `www/b-smart/app/Models` di host, helper secara presisi mengeksekusi perintah di dalam path container yang sesuai (`/var/www/html/b-smart/app/Models`).

---

## 💡 Contoh Praktis Penggunaan (Real-World Examples)

Berikut adalah panduan langkah demi langkah untuk berbagai skenario project di lingkungan Diskominfo:

### Contoh 1: Menjalankan Laravel PHP 8.3 — `web_bintan`
Portal resmi Pemerintah Kabupaten Bintan berbasis Laravel modern yang berjalan di environment **PHP 8.3**.

1. **Letakkan project**: Pastikan source code berada di:
   ```text
   egov-lamp/www/web_bintan/
   ```
2. **Kunci versi PHP**: File `.ws` di dalam `www/web_bintan/.ws`:
   ```ini
   php=8.3
   type=laravel
   entry=public
   ```
   *(Atau buka dashboard web `http://localhost:8083` lalu klik tombol `⚙️ Setting` pada kartu web_bintan)*.
3. **Konfigurasi file `.env` Laravel**:
   Buka file `www/web_bintan/.env` dan sesuaikan koneksi database & URL:
   ```env
   APP_NAME="Web Bintan"
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://localhost:8083/web_bintan/public

   DB_CONNECTION=mysql
   DB_HOST=database
   DB_PORT=3306
   DB_DATABASE=db_web_bintan
   DB_USERNAME=root
   DB_PASSWORD=tiger

   REDIS_HOST=redis
   REDIS_PORT=6379
   ```
4. **Jalankan dependensi & migrasi di Terminal Host**:
   ```bash
   cd www/web_bintan
   composer install
   artisan key:generate
   artisan migrate
   fix-perms
   ```
   *(Sistem otomatis mendeteksi `.ws` dan mengeksekusi composer/artisan di dalam container **PHP 8.3**!)*
5. **Buka di Browser**:
   Akses: 👉 **`http://localhost:8083/web_bintan/public/`**

---

### Contoh 2: Menjalankan Laravel PHP 8.2 — `b-smart`
Aplikasi layanan kepegawaian dan administrasi internal Pemerintah Kabupaten Bintan berbasis Laravel 12 yang berjalan di **PHP 8.2**.

1. **Letakkan project**: Pastikan source code berada di:
   ```text
   egov-lamp/www/b-smart/
   ```
2. **Kunci versi PHP**: File `.ws` di dalam `www/b-smart/.ws`:
   ```ini
   php=8.2
   type=laravel
   entry=public
   ```
   *(Atau klik `⚙️ Setting` pada kartu b-smart di dashboard `http://localhost:8082`)*.
3. **Konfigurasi file `.env` Laravel**:
   Buka file `www/b-smart/.env` dan sesuaikan koneksi database:
   ```env
   APP_NAME="B-Smart Bintan"
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://localhost:8082/b-smart/public

   DB_CONNECTION=mysql
   DB_HOST=database
   DB_PORT=3306
   DB_DATABASE=db_bsmart
   DB_USERNAME=root
   DB_PASSWORD=tiger

   REDIS_HOST=redis
   REDIS_PORT=6379
   ```
4. **Jalankan di Terminal Host**:
   ```bash
   cd www/b-smart
   composer install
   artisan key:generate
   artisan migrate
   fix-perms
   ```
   *(Sistem otomatis mengeksekusi di dalam container **PHP 8.2**!)*
5. **Buka di Browser**:
   Akses: 👉 **`http://localhost:8082/b-smart/public/`**

---

### Contoh 3: Menjalankan CodeIgniter 3 PHP 7.4 — `ak1_disnaker`
Aplikasi pelayanan AK-1 (Kartu Kuning) Dinas Tenaga Kerja Kabupaten Bintan berbasis CodeIgniter 3 yang membutuhkan **PHP 7.4**.

1. **Letakkan project**: Pastikan source code berada di:
   ```text
   egov-lamp/www/ak1_disnaker/
   ```
2. **Kunci versi PHP**: File `.ws` di dalam `www/ak1_disnaker/.ws`:
   ```ini
   php=7.4
   type=ci3
   entry=.
   ```
3. **Konfigurasi Database di `application/config/database.php`**:
   Buka file `application/config/database.php` dan pastikan hostname mengarah ke `database`:
   ```php
   $db['default'] = array(
       'dsn'      => '',
       'hostname' => 'database',
       'username' => 'root',
       'password' => 'tiger',
       'database' => 'silancar',
       'dbdriver' => 'mysqli',
       'pconnect' => FALSE,
       'db_debug' => (ENVIRONMENT !== 'production'),
       ...
   );
   ```
4. **Jalankan Dependensi (Jika Menggunakan Composer)**:
   ```bash
   cd www/ak1_disnaker
   composer install
   ```
   *(Sistem otomatis mengeksekusi Composer pada **PHP 7.4**!)*
5. **Buka di Browser**:
   Akses: 👉 **`http://localhost:8074/ak1_disnaker/`**

---

### Contoh 4: Menjalankan Script PHP Native Sederhana
Jika Anda ingin membuat script uji coba atau modul PHP native tanpa framework:

1. Buat folder baru: `www/cek-koneksi/`
2. Buat file `index.php` di dalamnya:
   ```php
   <?php
   echo "<h1>Halo dari Dinas Komunikasi dan Informatika Bintan!</h1>";
   echo "<p>Versi PHP saat ini: " . phpversion() . "</p>";

   $db = new mysqli("database", "root", "tiger", "docker");
   if ($db->connect_error) {
       die("Gagal konek DB: " . $db->connect_error);
   }
   echo "<p style='color:green'>✔ Koneksi MariaDB Berhasil!</p>";
   ```
3. Anda bisa mengaksesnya di versi PHP mana saja yang sedang menyala:
   - PHP 7.4: `http://localhost:8074/cek-koneksi/`
   - PHP 8.1: `http://localhost:8081/cek-koneksi/`
   - PHP 8.2: `http://localhost:8082/cek-koneksi/`
   - PHP 8.3: `http://localhost:8083/cek-koneksi/`

---

### Contoh 5: Menghubungkan GUI Database (DBeaver / Navicat / VS Code)
Jika Anda menggunakan aplikasi manajemen database di laptop Anda:

| Parameter | Nilai Konfigurasi |
| :--- | :--- |
| **Driver** | MySQL atau MariaDB |
| **Host** | `127.0.0.1` atau `localhost` |
| **Port** | `3306` |
| **Database** | `docker` *(atau nama database yang Anda buat)* |
| **Username** | `root` *(atau `docker`)* |
| **Password** | `tiger` *(atau `docker`)* |

> **Tips Manajemen via Browser:** Anda juga dapat mengelola database langsung melalui phpMyAdmin di browser: 👉 **`http://localhost:8888`** (User: `root`, Pass: `tiger`).

---

## 🎛️ Konfigurasi Project (.ws) & Dashboard Web

Dashboard web `egov-lamp` dilengkapi dengan sistem kartu interaktif untuk setiap folder yang ada di dalam `www/`:

```text
┌──────────────────────────────────────────────┐
│  📁 b-smart                    [🟢 PHP 8.2] │
│  Laravel Application                         │
│  ────────────────────────────────────────── │
│  [ 🚀 Buka ]    [ < / > VS Code ]   [ ⚙️ ]  │
└──────────────────────────────────────────────┘
```

1. **Live Health Indicator**:
   Titik di sudut kanan kartu menampilkan status real-time container PHP target (`🟢 Hijau` = Aktif & siap diakses, `🔴 Merah` = Container sedang mati). Jika Anda mengeklik **Buka** saat kontainer mati, dashboard akan memberi tahu Anda agar tidak terjadi error koneksi.
2. **Tombol `< / > VS Code`**:
   Membuka project langsung ke aplikasi Visual Studio Code di komputer host Anda menggunakan URI handler `vscode://file/...`.
3. **Modal `⚙️ Setting`**:
   Mengatur file konfigurasi `.ws` secara visual tanpa harus mengedit file teks:
   - Pilihan Versi PHP (`7.4`, `8.0`, `8.1`, `8.2`, `8.3`)
   - Tipe Framework (`laravel`, `ci3`, `native`)
   - Direktori Entry (`public` untuk Laravel, `.` untuk CI3/Native)

Format file `.ws`:
```ini
php=8.2
type=laravel
entry=public
```

---

## 📁 Struktur Direktori

```text
egov-lamp/
├── bin/                        # Dockerfile & konfigurasi build tiap container
│   ├── mariadb106/             # Konfigurasi MariaDB 10.6
│   ├── php74/                  # PHP 7.4 + Apache (Bullseye)
│   ├── php8/                   # PHP 8.0 + Apache (Bullseye)
│   ├── php81/                  # PHP 8.1 + Apache (Bookworm)
│   ├── php82/                  # PHP 8.2 + Apache (Bookworm)
│   └── php83/                  # PHP 8.3 + Apache (Bookworm)
├── cli/                        # Script kontrol dan helper terminal
│   ├── egov                    # Interactive Terminal Launcher (Bash - Linux/macOS)
│   ├── egov.bat                # Interactive Terminal Launcher (Windows CMD/PowerShell)
│   ├── docker-php-helpers.sh   # Helper & Smart Router (Bash/Zsh)
│   └── docker-php-helpers.ps1  # Helper & Smart Router (Windows PowerShell)
├── config/                     # Konfigurasi server
│   ├── php/                    # php.ini kustom untuk upload_max_filesize, memory_limit
│   ├── phpmyadmin/             # Konfigurasi phpMyAdmin
│   └── vhosts/                 # Konfigurasi VirtualHost Apache
├── data/                       # Penyimpanan persistent MariaDB (di-ignore oleh git)
├── logs/                       # Log Apache & MySQL untuk debugging (di-ignore oleh git)
├── www/                        # Direktori utama tempat seluruh project diletakkan
│   ├── index.php               # Portal Dashboard interaktif egov-lamp
│   └── test_db.php             # Script uji coba koneksi MariaDB
├── docker-compose.yml          # Definisi orkestrasi container Docker
├── .env.example                # Template konfigurasi environment (port, password)
├── .env                        # Konfigurasi lokal aktif
└── README.md                   # Dokumentasi lengkap
```

---

## ❓ Tanya Jawab & Solusi Masalah (Troubleshooting)

### Q1: Muncul error `port is already allocated` saat menyalakan container?
**Penyebab**: Port tersebut (misalnya port `3306` untuk MySQL atau `8074` untuk Apache) sedang digunakan oleh service lain di komputer host Anda (seperti XAMPP, Laragon, MySQL lokal, atau web server lokal lainnya).  
**Solusi**:
1. Matikan service lokal yang bentrok:
   - Di Linux: `sudo systemctl stop mysql` atau `sudo systemctl stop apache2`
   - Di Windows: Buka `services.msc`, cari service `MySQL` atau `Apache`, lalu klik **Stop**.
2. Atau ubah port host pada file `.env` egov-lamp:
   ```env
   HOST_MACHINE_MYSQL_PORT=3307
   ```

---

### Q2: Muncul error `Permission denied` pada folder `storage/logs/laravel.log`?
**Penyebab**: Folder `storage` dan `bootstrap/cache` dibuat oleh user host sehingga container Apache (`www-data`) tidak memiliki hak tulis.  
**Solusi**:
Cukup jalankan perintah perbaikan otomatis:
```bash
fix-perms
```
Atau pilih menu **`[6] Perbaiki Permission Folder`** di menu launcher `egov`.

---

### Q3: Di Windows PowerShell muncul error `running scripts is disabled on this system`?
**Penyebab**: Kebijakan keamanan eksekusi PowerShell bawaan Windows masih dalam mode Restricted.  
**Solusi**:
Buka PowerShell dan jalankan perintah:
```powershell
Set-ExecutionPolicy RemoteSigned -Scope CurrentUser
```

---

### Q4: Database tidak bisa terhubung dari file `.env` Laravel?
**Penyebab**: Variabel `DB_HOST` masih bernilai `127.0.0.1` atau `localhost`. Ingat bahwa di dalam lingkungan Docker, tiap container memiliki jaringannya sendiri.  
**Solusi**:
Ubah `DB_HOST` menjadi nama service container di file `.env` project:
```env
DB_HOST=database
DB_PORT=3306
DB_USERNAME=root
DB_PASSWORD=tiger
```

---

### Q5: Bagaimana cara melihat log error Apache / PHP jika website blank?
**Solusi**:
Semua log tersimpan rapi di direktori `logs/apache2/` pada root folder `egov-lamp`:
```bash
tail -f logs/apache2/error_php82.log
```
Atau lihat langsung via Docker:
```bash
docker logs -f egov-php82
```

---

### Q6: Gagal menyimpan pengaturan di Dashboard web / Permission Denied pada `.ws`?
**Penyebab**: Folder project di dalam `www/` baru saja di-copy ke Linux/WSL sehingga memiliki izin default `755` milik user host. Akibatnya, web server Apache di Docker (`www-data`) tidak diizinkan membuat file konfigurasi baru `.ws`.  
**Solusi**:
Jalankan perintah perbaikan izin otomatis:
```bash
fix-perms
```
Atau berikan izin tulis langsung ke folder `www/`:
```bash
chmod -R 777 ~/egov-lamp/www
```

---

### Q7: Bagaimana cara membuka folder project via terminal ke Editor (VS Code & Google Antigravity)?
- **Visual Studio Code**:
  Masuk ke folder project di terminal lalu ketik:
  ```bash
  code .
  ```
- **Google Antigravity IDE**:
  Sama persis seperti VS Code, Anda dapat membuka folder project langsung ke Antigravity IDE dengan perintah:
  ```bash
  antigravity .
  ```
  > *Catatan: Jika perintah `antigravity` belum terdaftar di terminal Anda: Buka Antigravity IDE ➔ Tekan `Ctrl+Shift+P` (atau `Cmd+Shift+P` di Mac) ➔ Ketik `Shell Command: Install 'antigravity' command in PATH` ➔ Tekan Enter.*
- **Google Antigravity CLI (Agent Interaktif di Terminal)**:
  ```bash
  agy .
  ```

---

## 👥 Kontribusi & Pemeliharaan

Environment ini dikembangkan dan dikelola khusus untuk standardisasi alur kerja tim developer di lingkungan:
**Dinas Komunikasi dan Informatika Kabupaten Bintan — Bidang e-Government**.

Jika ada kebutuhan penambahan ekstensi PHP, modul Apache, atau perbaikan script, silakan buat *Pull Request* atau hubungi tim pengelola repositori.

---
**Lisensi**: [MIT License](LICENSE) © Diskominfo Kabupaten Bintan.
