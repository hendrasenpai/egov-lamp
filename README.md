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
   - [Pengguna Windows (WSL 2 Ubuntu)](#-c-instalasi-di-windows-wsl-2-ubuntu)
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
- **Interactive Control Center (`egov`)**: Antarmuka menu terminal interaktif untuk mengontrol container (start, stop, status, fix permissions, clone repo) hanya dengan satu ketukan angka.
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
- **Auto-Clone & Setup Project Diskominfo**: Ambil repository dari organisasi GitHub [tim-it-diskominfobintan](https://github.com/tim-it-diskominfobintan) dalam 1 perintah (`egov clone <nama_repo>` atau menu `[7]`). Otomatis konfigurasi file `.ws`, `.env` MariaDB, permission, dan Composer!

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

### 🪟 C. Instalasi di Windows (WSL 2 Ubuntu)
*(Standar Resmi Lingkungan Developer Windows — Performa 10x Lebih Cepat dari NTFS)*

#### 1. Persiapan Docker Desktop & Integrasi WSL 2
1. Di Windows, buka **Docker Desktop**.
2. Masuk ke menu **Settings** (ikon gear ⚙️) ➔ **General** ➔ Pastikan opsi **"Use the WSL 2 based engine"** tercentang.
3. Masuk ke **Settings** ➔ **Resources** ➔ **WSL Integration**:
   - Centang **"Enable integration with my default WSL distro"**.
   - Di daftar distro di bawahnya, aktifkan toggle pada **Ubuntu** (atau distro WSL yang Anda gunakan).
4. Klik **Apply & restart**.

#### 2. Clone Repository di dalam Linux Filesystem WSL
> [!IMPORTANT]
> Selalu simpan file project di dalam home Linux (`~/egov-lamp` atau `~/workspace/`), **JANGAN** menyimpannya di direktori mount Windows (`/mnt/c/...`). Menyimpan file di filesystem Linux asli membuat eksekusi PHP, Composer, dan I/O Docker hingga **10x lebih cepat**!

Buka terminal **Ubuntu (WSL 2)**:
```bash
# Clone via HTTPS:
git clone https://github.com/hendrasenpai/egov-lamp.git ~/egov-lamp

# Atau via SSH (jika sudah ada SSH Key GitHub):
# git clone git@github.com:hendrasenpai/egov-lamp.git ~/egov-lamp

cd ~/egov-lamp
cp .env.example .env
chmod +x cli/egov cli/clone-project.sh
```

#### 3. Pasang CLI Helper ke Bash Terminal WSL
```bash
echo "source $(pwd)/cli/docker-php-helpers.sh" >> ~/.bashrc
source ~/.bashrc
```

#### 4. Akses Folder Project dari Windows Explorer
Jika Anda ingin menyalin file project dari Windows ke folder `www/` di WSL:
- Di terminal WSL, ketik:
  ```bash
  explorer.exe www
  ```
- Windows Explorer akan otomatis terbuka di path jaringan WSL:
  `\\wsl.localhost\Ubuntu\home\<user>\egov-lamp\www`
- Anda dapat melakukan copy-paste file kodingan Anda ke folder tersebut seperti biasa!

#### 5. Selesai!
Ketik `egov` di terminal WSL untuk menyalakan stack, atau langsung buka project ke editor favorit Anda:
- Ke VS Code: `code .`
- Ke Antigravity IDE: `antigravity .` atau `ide .`

---

## 🎮 Menggunakan Interactive Launcher (egov)

Untuk mengelola seluruh stack tanpa perlu mengingat perintah Docker yang panjang, jalankan di terminal mana saja (Linux / macOS / WSL 2):
```bash
egov
```

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
  7) Clone Project dari GitHub (tim-it-diskominfobintan)
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
- **`[7] Clone Project dari GitHub (tim-it-diskominfobintan)`**:
  Mengunduh repository dari organisasi [tim-it-diskominfobintan](https://github.com/tim-it-diskominfobintan), otomatis membuat konfigurasi `.ws`, menyiapkan `.env` database Docker, mengatur permission, dan menawarkan instalasi Composer.

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

### 4. Clone & Setup Project Otomatis (`egov clone`)
Untuk mengunduh project baru dari organisasi resmi GitHub [tim-it-diskominfobintan](https://github.com/tim-it-diskominfobintan), Anda tidak perlu lagi melakukan clone manual, membuat `.env`, atau mengetik perintah docker. Cukup ketik:

```bash
# 1. Mode Interaktif (akan menanyakan nama repo, versi PHP, dan opsi lainnya):
egov clone

# 2. Langsung sebutkan nama repository:
egov clone web_bintan

# 3. Langsung tentukan versi PHP (misal PHP 8.2):
egov clone b-smart 82

# 4. Alias shortcut praktis:
clone-project ak1_disnaker
```

**Alur yang Dijalankan Secara Otomatis:**
1. Meng-clone repo dari `https://github.com/tim-it-diskominfobintan/<nama-repo>.git` ke `www/<nama-repo>/`.
2. Membuat file konfigurasi `.ws` sesuai versi PHP yang dipilih.
3. Menyiapkan file `.env` Laravel dengan koneksi database MariaDB Docker (`DB_HOST=database`, `DB_PORT=3306`, `DB_USERNAME=root`, `DB_PASSWORD=tiger`, `DB_DATABASE=<nama_project>`).
4. Memperbaiki izin tulis (*permissions*) pada folder `storage/`, `bootstrap/cache/`, dan `.ws`.
5. Menawarkan eksekusi `composer install` dan `artisan key:generate` langsung di dalam container PHP yang sesuai.
6. Menawarkan untuk membuka project langsung ke **Antigravity IDE** atau **VS Code**!

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

Dashboard web `egov-lamp` (tersedia di port `8074`, `8080`, `8081`, `8082`, dan `8083`) dilengkapi dengan sistem tab interaktif, penjelajah repository GitHub, dan integrasi IDE modern:

```text
┌────────────────────────────────────────────────────────────────────────┐
│ [📁 Project Lokal (12)]   [☁️ GitHub Repo (4)]    [ 🔍 Cari... ]  [🔑] │
├────────────────────────────────────────────────────────────────────────┤
│ ┌──────────────────────────────────┐  ┌──────────────────────────────┐ │
│ │ 📁 b-smart          [🟢 PHP 8.2] │  │ ☁️ web_disnaker     [Public] │ │
│ │ Laravel Application              │  │ Web resmi Disnaker Bintan    │ │
│ │ ──────────────────────────────── │  │ ──────────────────────────── │ │
│ │ [⚙️ Setting] [</> Code] [🪐 IDE] │  │ [GitHub ↗] [📋 CLI] [⬇ Clone]│ │
│ │                         [🚀 Buka]│  │                              │ │
│ └──────────────────────────────────┘  └──────────────────────────────┘ │
└────────────────────────────────────────────────────────────────────────┘
```

### 1. Tab "Project Lokal"
- **Live Health Indicator**: Titik di sudut kanan kartu menampilkan status real-time container PHP target (`🟢 Hijau` = Aktif & siap diakses, `🔴 Merah` = Container sedang mati).
- **Tombol `🪐 Antigravity`**: Membuka project langsung ke **Google Antigravity IDE** (`antigravity://file...`) dan otomatis menyalin perintah terminal `antigravity www/<project>` ke clipboard sebagai fallback instan!
- **Tombol `</> VS Code`**: Membuka project langsung ke Visual Studio Code host (`vscode://file...`).
- **Modal `⚙️ Setting`**: Mengatur file konfigurasi `.ws` secara visual tanpa harus mengedit file teks (versi PHP, framework, dan entry path).

### 2. Tab "GitHub Repo" (Integrasi Organisasi tim-it-diskominfobintan)
- **Auto-Discovery Uncloned Repos**: Menampilkan daftar repository dari GitHub organisasi yang belum ada di folder `www/` lokal Anda secara otomatis.
- **1-Click Clone dari Web**: Klik tombol **`Clone`**, pilih versi PHP target (7.4 - 8.3), dan sistem akan langsung melakukan clone, men-generate file `.ws`, membuat file `.env` dengan kredensial database MariaDB Docker, serta mengonfigurasi permission folder secara otomatis!
- **Kunci Token (🔑)**: Mendukung penyimpanan GitHub Personal Access Token (PAT) secara aman di `.github_token` lokal untuk menampilkan repositori **Private** organisasi dan menaikkan batas rate limit GitHub API (dari 60 menjadi 5.000 request/jam).
- **Pencarian Real-Time**: Kolom pencarian di kanan atas dapat menyaring project lokal maupun repository remote GitHub secara cepat.

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
│   ├── egov                    # Interactive Terminal Launcher (Linux / macOS / WSL 2)
│   ├── docker-php-helpers.sh   # Helper & Smart Router (Bash/Zsh)
│   └── clone-project.sh        # Otomatisasi Clone & Setup Project Diskominfo
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

### Q3: Mengapa di Windows wajib menggunakan WSL 2 dan tidak menggunakan PowerShell biasa?
**Penjelasan & Keuntungan**:
1. **Performa 10x Lebih Cepat**: Docker Desktop di WSL 2 berjalan di atas kernel Linux murni dan filesystem virtual ext4. Operasi file I/O (seperti `composer install`, `npm install`, reload halaman web Laravel) berjalan hingga **10x lebih cepat** dibandingkan jika dijalankan di filesystem NTFS Windows native.
2. **Standarisasi Lingkungan (Identik dengan Production)**: Server produksi Pemkab Bintan menggunakan Linux. Dengan menggunakan WSL 2 di Windows, seluruh alur kerja, permission folder (`chmod`), dan ekstensi PHP yang berjalan di laptop developer dijamin 100% identik dengan server live.
3. **Bebas Masalah Permission**: Mencegah konflik hak akses file Windows vs Linux yang sering membuat web server error saat membuat file cache/log.

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

### Q7: Bagaimana cara membuka folder project via terminal WSL ke Editor Windows (VS Code & Google Antigravity)?
Dari dalam terminal WSL 2 di folder project (misalnya `~/egov-lamp/www/web_bintan`), Anda dapat langsung meluncurkan editor koding yang terinstall di Windows host:
- **Visual Studio Code**:
  ```bash
  code .
  ```
- **Google Antigravity IDE (Code Editor)**:
  Sama persis seperti `code .`, Anda dapat membuka folder project langsung ke Antigravity IDE di Windows dengan perintah:
  ```bash
  antigravity .
  # atau shortcut ringkas:
  ide .
  ```
  > *💡 **Catatan Teknis & Troubleshooting Antigravity IDE**:*
  > - **Nama Perintah di Windows**: Di sistem operasi Windows, perintah CLI Antigravity IDE terdaftar pada Environment Variables (PATH) dengan nama **`antigravity-ide`**.
  > - **Alur Kerja Bridge**: Script helper `egov-lamp` secara otomatis mengonversi path Linux ke UNC path Windows (`\\wsl.localhost\Ubuntu\...`) lalu mengeksekusi `antigravity-ide` ke Windows CMD di latar belakang.
  > - **Perbedaan Aplikasi**: Pastikan yang terpasang di Windows adalah **Antigravity IDE** (editor koding berbasis VS Code dengan tab editor dan file tree), bukan sekadar **Antigravity 2.0 Desktop** (aplikasi chat manager agent bawaan).
  > - **Tes Manual dari WSL**: Jika ingin menguji secara manual dari terminal WSL:
  >   ```bash
  >   cmd.exe /c antigravity-ide "$(wslpath -w .)"
  >   ```
- **Google Antigravity CLI (Agent Interaktif di Terminal)**:
  ```bash
  agy .
  ```

---

### Q8: Muncul error `permission denied while trying to connect to the Docker daemon socket` saat menjalankan `docker` di WSL 2?
**Penyebab**: User akun Linux Anda di WSL 2 belum dimasukkan ke dalam group sistem `docker`, atau integrasi WSL 2 di Docker Desktop Windows belum diaktifkan.  
**Solusi**:
1. Masukkan user Anda ke grup `docker` di WSL 2:
   ```bash
   sudo usermod -aG docker $USER
   newgrp docker
   ```
2. Pastikan aplikasi **Docker Desktop** di Windows sedang berjalan (ikon paus hijau di taskbar).
3. Pastikan integrasi WSL sudah aktif di Docker Desktop:
   - Buka Docker Desktop di Windows ➔ Klik **Settings** (⚙️) ➔ **Resources** ➔ **WSL Integration**.
   - Pastikan toggle distro **Ubuntu** dalam posisi **ON / Aktif**.
   - Klik **Apply & restart**.
4. Jika koneksi socket masih belum tersambung, restart instance WSL dari PowerShell Windows:
   ```powershell
   wsl --shutdown
   ```
   Lalu buka kembali terminal Ubuntu WSL Anda.

---

### Q9: Muncul error `Permission denied (publickey)` saat clone repository di WSL 2?
**Penyebab**: Anda menggunakan URL SSH (`git@github.com:...`) namun terminal WSL 2 belum memiliki SSH Key yang didaftarkan ke akun GitHub Anda.  
**Solusi**:
- **Solusi Termudah**: Clone menggunakan protokol **HTTPS**:
  ```bash
  git clone https://github.com/hendrasenpai/egov-lamp.git ~/egov-lamp
  ```
- **Solusi Menggunakan SSH Key**: Jika ingin tetap menggunakan SSH:
  1. Generate SSH Key baru di terminal WSL:
     ```bash
     ssh-keygen -t ed25519 -C "email_anda@domain.com"
     ```
     *(Tekan Enter terus sampai selesai).*
  2. Tampilkan isi public key yang baru dibuat:
     ```bash
     cat ~/.ssh/id_ed25519.pub
     ```
  3. Buka GitHub di browser ➔ **Settings** ➔ **SSH and GPG keys** ➔ Klik **New SSH Key** ➔ Paste key tersebut lalu simpan.

---

### Q10: Bagaimana cara clone dan setup project baru dari GitHub tim-it-diskominfobintan?
**Solusi**:
Gunakan fitur bawaan `egov clone` yang dirancang khusus untuk tim developer Diskominfo Bintan:
```bash
egov clone <nama_repository>
# Contoh:
egov clone web_bintan
# Atau di Windows PowerShell / CMD:
egov clone web_bintan
```
Atau buka menu launcher `egov` lalu pilih menu **`[7] Clone Project dari GitHub`**.

Script secara otomatis:
1. Mengunduh source code dari `https://github.com/tim-it-diskominfobintan/<nama_repository>.git` ke `www/<nama_repository>/`.
2. Mengonfigurasi versi PHP di file `.ws`.
3. Menyiapkan file `.env` Laravel dengan koneksi database container MariaDB.
4. Menjalankan `fix-perms` agar direktori project dan `storage/` langsung writable.
5. Menawarkan eksekusi `composer install` dan membuka ke editor (Antigravity IDE / VS Code).

---

## 👥 Kontribusi & Pemeliharaan

Environment ini dikembangkan dan dikelola khusus untuk standardisasi alur kerja tim developer di lingkungan:
**Dinas Komunikasi dan Informatika Kabupaten Bintan — Bidang e-Government**.

Jika ada kebutuhan penambahan ekstensi PHP, modul Apache, atau perbaikan script, silakan buat *Pull Request* atau hubungi tim pengelola repositori.

---
**Lisensi**: [MIT License](LICENSE) © Diskominfo Kabupaten Bintan.
