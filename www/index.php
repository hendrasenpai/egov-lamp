<?php
// Workspace GOV - Modern Dashboard Landing Page with Live Container Status & Project Settings

function get_github_config() {
    $token = '';
    if (file_exists('./.github_token')) {
        $token = trim(@file_get_contents('./.github_token'));
    }
    if (empty($token)) {
        $token = getenv('GITHUB_TOKEN') ?: '';
    }
    $org = getenv('GITHUB_ORG') ?: 'tim-it-diskominfobintan';
    return ['token' => $token, 'org' => $org];
}

// 1. Handle API Save Setting (.ws)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_setting') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    $project = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['project'] ?? '');
    $php = $_POST['php'] ?? '7.4';
    $type = $_POST['type'] ?? 'auto';
    $entry = $_POST['entry'] ?? 'auto';
    $ide = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['ide'] ?? 'auto');

    if ($project && is_dir("./$project")) {
        $content = "php={$php}\ntype={$type}\nentry={$entry}\nide={$ide}\n";
        $saved = @file_put_contents("./$project/.ws", $content);
        if ($saved !== false) {
            ensure_code_workspace($project);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menulis file .ws (Permission Denied). Jalankan fix-perms di terminal.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Project tidak valid']);
    }
    exit;
}

// Helper untuk memastikan file workspace (.code-workspace) tersedia
function ensure_code_workspace($project) {
    if (!$project || !is_dir("./$project")) return false;
    $ws_file = "./$project/{$project}.code-workspace";
    if (!file_exists($ws_file)) {
        $data = [
            "folders" => [
                [
                    "name" => $project,
                    "path" => "."
                ]
            ],
            "settings" => new stdClass()
        ];
        @file_put_contents($ws_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @chmod($ws_file, 0666);
    }
    return true;
}

// Helper untuk membuat database MariaDB secara otomatis jika belum ada
function create_mariadb_database($db_name) {
    $clean_db = preg_replace('/[^a-zA-Z0-9_]/', '_', $db_name);
    try {
        $pdo = new PDO("mysql:host=database;port=3306", "root", "tiger", [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 4
        ]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$clean_db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Helper untuk mendeteksi informasi Git dan keterhubungan dengan GitHub
function get_project_git_info($dir) {
    $has_git = is_dir("$dir/.git");
    $remote_url = '';
    $is_github = false;

    if ($has_git) {
        $git_config = "$dir/.git/config";
        if (file_exists($git_config)) {
            $content = @file_get_contents($git_config);
            if ($content && preg_match('/\[remote\s+"origin"\][^\[]*?url\s*=\s*([^\r\n]+)/s', $content, $matches)) {
                $remote_url = trim($matches[1]);
                if (stripos($remote_url, 'github.com') !== false) {
                    $is_github = true;
                }
            }
        }
    }

    return [
        'has_git' => $has_git,
        'remote_url' => $remote_url,
        'is_github' => $is_github
    ];
}

// Helper otomatisasi Laravel siap deploy (Composer, .env, DB, key, storage, migrate/seed)
function run_laravel_auto_setup($dir, $repo, $options, &$steps = []) {
    @set_time_limit(300);

    // 1. Setup .env & Database MariaDB
    if (!empty($options['env_db'])) {
        $clean_db = preg_replace('/[^a-zA-Z0-9_]/', '_', $repo);
        if (file_exists("$dir/.env.example") && !file_exists("$dir/.env")) {
            $env = @file_get_contents("$dir/.env.example");
            if ($env) {
                $env = preg_replace('/^DB_CONNECTION=.*/m', 'DB_CONNECTION=mysql', $env);
                $env = preg_replace('/^DB_HOST=.*/m', 'DB_HOST=database', $env);
                $env = preg_replace('/^DB_PORT=.*/m', 'DB_PORT=3306', $env);
                $env = preg_replace('/^DB_USERNAME=.*/m', 'DB_USERNAME=root', $env);
                $env = preg_replace('/^DB_PASSWORD=.*/m', 'DB_PASSWORD=tiger', $env);
                $env = preg_replace('/^DB_DATABASE=.*/m', "DB_DATABASE={$clean_db}", $env);
                $env = preg_replace('/^REDIS_HOST=.*/m', 'REDIS_HOST=redis', $env);
                @file_put_contents("$dir/.env", $env);
            }
        }
        $db_ok = create_mariadb_database($repo);
        $steps[] = [
            'step' => 'Environment & Database',
            'status' => $db_ok ? 'ok' : 'warn',
            'message' => $db_ok ? "Database '{$clean_db}' dibuat di MariaDB & .env disiapkan." : "File .env disiapkan (koneksi MariaDB timeout)."
        ];
    }

    // 2. Fix Permissions
    @chmod($dir, 0777);
    if (is_dir("$dir/storage")) {
        exec("chmod -R 777 " . escapeshellarg("$dir/storage") . " 2>/dev/null");
    }
    if (is_dir("$dir/bootstrap/cache")) {
        exec("chmod -R 777 " . escapeshellarg("$dir/bootstrap/cache") . " 2>/dev/null");
    }

    // 3. Composer Install
    if (!empty($options['composer']) && file_exists("$dir/composer.json")) {
        $cmd = "cd " . escapeshellarg($dir) . " && export COMPOSER_ALLOW_SUPERUSER=1 && composer install --no-interaction --prefer-dist --optimize-autoloader 2>&1";
        $comp_out = [];
        $comp_ret = 0;
        exec($cmd, $comp_out, $comp_ret);
        $steps[] = [
            'step' => 'Composer Install',
            'status' => ($comp_ret === 0) ? 'ok' : 'warn',
            'message' => ($comp_ret === 0) ? "Dependencies vendor berhasil diinstall." : "Composer install selesai dengan catatan: " . (end($comp_out) ?: 'warning')
        ];
    }

    // 4. Artisan commands (Key & Storage)
    if (file_exists("$dir/artisan")) {
        if (!empty($options['key_storage'])) {
            exec("php " . escapeshellarg("$dir/artisan") . " key:generate --force 2>&1");
            exec("php " . escapeshellarg("$dir/artisan") . " storage:link 2>&1");
            $steps[] = [
                'step' => 'App Key & Storage Link',
                'status' => 'ok',
                'message' => 'Artisan key:generate & storage:link berhasil.'
            ];
        }

        // 5. Migrate & Seed Database
        if (!empty($options['migrate_seed'])) {
            $mig_out = [];
            $mig_ret = 0;
            exec("php " . escapeshellarg("$dir/artisan") . " migrate:fresh --seed --force 2>&1", $mig_out, $mig_ret);
            $steps[] = [
                'step' => 'Migrate & Seed Database',
                'status' => ($mig_ret === 0) ? 'ok' : 'warn',
                'message' => ($mig_ret === 0) ? 'Migrasi tabel & seeder database berhasil dijalankan.' : 'Migrasi selesai: ' . (end($mig_out) ?: 'warning')
            ];
        }
    }
}

// 2. Handle API Ensure Workspace File (.code-workspace)
if (isset($_GET['action']) && $_GET['action'] === 'ensure_workspace') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    $project = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['project'] ?? '');
    $res = ensure_code_workspace($project);
    echo json_encode(['success' => $res]);
    exit;
}

// 3. Handle API Inisialisasi Project Baru dari Template core-laravel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'init_project') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    @set_time_limit(300);

    $name = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['name'] ?? '');
    $remote_url = trim($_POST['remote_url'] ?? '');
    $php = preg_replace('/[^0-9.]/', '', $_POST['php'] ?? '8.2');
    $config = get_github_config();

    if (!$name) {
        echo json_encode(['success' => false, 'message' => 'Nama project tidak valid.']);
        exit;
    }

    if (is_dir("./$name")) {
        echo json_encode(['success' => false, 'message' => "Folder www/{$name} sudah ada di lokal!"]);
        exit;
    }

    // Template core-laravel
    if (!empty($config['token'])) {
        $template_url = "https://oauth2:{$config['token']}@github.com/{$config['org']}/core-laravel.git";
    } else {
        $template_url = "https://github.com/{$config['org']}/core-laravel.git";
    }

    $output = [];
    $return_var = 0;
    exec("git clone " . escapeshellarg($template_url) . " " . escapeshellarg("./$name") . " 2>&1", $output, $return_var);

    if ($return_var !== 0) {
        $msg = implode("\n", $output);
        if (!empty($config['token'])) {
            $msg = str_replace($config['token'], '***', $msg);
        }
        echo json_encode([
            'success' => false, 
            'message' => "Gagal meng-clone template core-laravel: $msg. Pastikan GitHub Token sudah diatur untuk mengakses repository private organisasi."
        ]);
        exit;
    }

    $steps = [];
    $steps[] = [
        'step' => 'Clone Template Core Laravel',
        'status' => 'ok',
        'message' => 'Template core-laravel berhasil di-clone.'
    ];

    // Reset Git: rm -rf .git && git init -b main
    $opt_reset_git = !isset($_POST['opt_reset_git']) || $_POST['opt_reset_git'] === '1' || $_POST['opt_reset_git'] === 'true';
    if ($opt_reset_git) {
        exec("rm -rf " . escapeshellarg("./$name/.git") . " && git -C " . escapeshellarg("./$name") . " init -b main 2>&1");
        $git_msg = "Git direset ke repository baru (branch main).";
        if (!empty($remote_url)) {
            exec("git -C " . escapeshellarg("./$name") . " remote add origin " . escapeshellarg($remote_url) . " 2>&1");
            $git_msg .= " Remote origin diset ke: $remote_url";
        }
        $steps[] = [
            'step' => 'Git Reset & Remote',
            'status' => 'ok',
            'message' => $git_msg
        ];
    }

    // Set versi PHP di .ws
    $content = "php={$php}\ntype=laravel\nentry=public\nide=auto\n";
    @file_put_contents("./$name/.ws", $content);
    @chmod("./$name/.ws", 0666);
    ensure_code_workspace($name);

    // Auto setup
    $options = [
        'env_db' => !isset($_POST['opt_env_db']) || $_POST['opt_env_db'] === '1' || $_POST['opt_env_db'] === 'true',
        'composer' => !isset($_POST['opt_composer']) || $_POST['opt_composer'] === '1' || $_POST['opt_composer'] === 'true',
        'key_storage' => !isset($_POST['opt_key_storage']) || $_POST['opt_key_storage'] === '1' || $_POST['opt_key_storage'] === 'true',
        'migrate_seed' => !isset($_POST['opt_migrate_seed']) || $_POST['opt_migrate_seed'] === '1' || $_POST['opt_migrate_seed'] === 'true',
    ];
    run_laravel_auto_setup("./$name", $name, $options, $steps);

    // Initial commit jika git direset
    $opt_commit = !isset($_POST['opt_initial_commit']) || $_POST['opt_initial_commit'] === '1' || $_POST['opt_initial_commit'] === 'true';
    if ($opt_reset_git && $opt_commit) {
        exec("git -C " . escapeshellarg("./$name") . " add . && git -C " . escapeshellarg("./$name") . " commit -m " . escapeshellarg("chore: initialize project from core-laravel template") . " 2>&1");
        $steps[] = [
            'step' => 'Initial Git Commit',
            'status' => 'ok',
            'message' => 'Initial commit dibuat.'
        ];
    }

    @unlink('./.github_cache.json');

    echo json_encode([
        'success' => true,
        'project' => $name,
        'php' => $php,
        'message' => "Project {$name} berhasil diinisialisasi dari core-laravel!",
        'steps' => $steps
    ]);
    exit;
}

// 4. Handle API Get GitHub Repositories
if (isset($_GET['action']) && $_GET['action'] === 'get_github_repos') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    $config = get_github_config();
    $cache_file = './.github_cache.json';
    $force = isset($_GET['force']) && $_GET['force'] === '1';

    $data = null;
    if (!$force && file_exists($cache_file) && (time() - filemtime($cache_file) < 600)) {
        $data = json_decode(@file_get_contents($cache_file), true);
    }

    if (!$data) {
        $url = "https://api.github.com/orgs/{$config['org']}/repos?per_page=100&sort=updated";
        $headers = [
            'User-Agent: egov-lamp-dashboard',
            'Accept: application/vnd.github.v3+json'
        ];
        if (!empty($config['token'])) {
            $headers[] = 'Authorization: Bearer ' . $config['token'];
        }
        
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => $headers,
                'timeout' => 12,
                'ignore_errors' => true
            ]
        ];
        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);
        
        if ($response) {
            $json = json_decode($response, true);
            if (is_array($json) && !isset($json['message'])) {
                $data = $json;
                @file_put_contents($cache_file, $response);
            } else {
                $err = $json['message'] ?? 'Gagal mengambil data dari GitHub.';
                echo json_encode([
                    'success' => false,
                    'message' => $err,
                    'rate_limit' => strpos($err, 'rate limit') !== false,
                    'has_token' => !empty($config['token']),
                    'org' => $config['org']
                ]);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Tidak dapat terhubung ke GitHub API.']);
            exit;
        }
    }

    // Filter repo yang belum di-clone
    $local_items = scandir('./');
    $available_repos = [];
    $cloned_count = 0;

    foreach ($data as $repo) {
        $name = $repo['name'] ?? '';
        if (!$name || $name === '.github') continue;
        if (in_array($name, $local_items) && is_dir("./$name")) {
            $cloned_count++;
        } else {
            $available_repos[] = [
                'name' => $name,
                'full_name' => $repo['full_name'] ?? '',
                'description' => $repo['description'] ?? 'Tidak ada deskripsi repository.',
                'language' => $repo['language'] ?? 'PHP',
                'is_private' => !empty($repo['private']),
                'clone_url' => $repo['clone_url'] ?? '',
                'ssh_url' => $repo['ssh_url'] ?? '',
                'html_url' => $repo['html_url'] ?? '',
                'stars' => $repo['stargazers_count'] ?? 0,
                'updated_at' => date('d M Y', strtotime($repo['updated_at'] ?? 'now'))
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'org' => $config['org'],
        'has_token' => !empty($config['token']),
        'total_remote' => count($data),
        'total_uncloned' => count($available_repos),
        'total_cloned' => $cloned_count,
        'repos' => $available_repos
    ]);
    exit;
}

// 5. Handle API Clone Repository
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clone_repo') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    @set_time_limit(300);

    $repo = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['repo'] ?? '');
    $php = preg_replace('/[^0-9.]/', '', $_POST['php'] ?? '8.2');
    $config = get_github_config();

    if (!$repo) {
        echo json_encode(['success' => false, 'message' => 'Nama repository tidak valid.']);
        exit;
    }

    if (is_dir("./$repo")) {
        echo json_encode(['success' => false, 'message' => "Folder www/{$repo} sudah ada di lokal!"]);
        exit;
    }

    if (!empty($config['token'])) {
        $clone_url = "https://oauth2:{$config['token']}@github.com/{$config['org']}/{$repo}.git";
    } else {
        $clone_url = "https://github.com/{$config['org']}/{$repo}.git";
    }

    $output = [];
    $return_var = 0;
    exec("git clone " . escapeshellarg($clone_url) . " " . escapeshellarg("./$repo") . " 2>&1", $output, $return_var);

    if ($return_var !== 0) {
        $msg = implode("\n", $output);
        if (!empty($config['token'])) {
            $msg = str_replace($config['token'], '***', $msg);
        }
        echo json_encode(['success' => false, 'message' => "Gagal clone: $msg"]);
        exit;
    }

    $steps = [];
    $steps[] = [
        'step' => 'Git Clone',
        'status' => 'ok',
        'message' => "Repository {$repo} berhasil di-clone."
    ];

    // Set versi PHP di .ws
    $content = "php={$php}\ntype=auto\nentry=auto\n";
    @file_put_contents("./$repo/.ws", $content);
    @chmod("./$repo/.ws", 0666);
    ensure_code_workspace($repo);

    // Auto setup
    $options = [
        'env_db' => !isset($_POST['opt_env_db']) || $_POST['opt_env_db'] === '1' || $_POST['opt_env_db'] === 'true',
        'composer' => !isset($_POST['opt_composer']) || $_POST['opt_composer'] === '1' || $_POST['opt_composer'] === 'true',
        'key_storage' => !isset($_POST['opt_key_storage']) || $_POST['opt_key_storage'] === '1' || $_POST['opt_key_storage'] === 'true',
        'migrate_seed' => !isset($_POST['opt_migrate_seed']) || $_POST['opt_migrate_seed'] === '1' || $_POST['opt_migrate_seed'] === 'true',
    ];
    run_laravel_auto_setup("./$repo", $repo, $options, $steps);

    @unlink('./.github_cache.json');

    echo json_encode([
        'success' => true,
        'project' => $repo,
        'php' => $php,
        'message' => "Project {$repo} berhasil di-clone dan disiapkan!",
        'steps' => $steps
    ]);
    exit;
}

// 4. Handle API Save GitHub Token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_token') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    $token = trim($_POST['token'] ?? '');
    if ($token) {
        @file_put_contents('./.github_token', $token);
    } else {
        @unlink('./.github_token');
    }
    @unlink('./.github_cache.json');
    echo json_encode(['success' => true]);
    exit;
}

$php_version = phpversion();
$apache_version = function_exists('apache_get_version') ? apache_get_version() : 'Apache Server';
$pma_port = getenv('PMA_PORT') ?: '8888';

// Port mapping per PHP version
$php_port_map = [
    '7.4' => '8074',
    '8.0' => '8080',
    '8.1' => '8081',
    '8.2' => '8082',
    '8.3' => '8083'
];

// Scan active project directories
$all_items = scandir('./');
$excluded = ['.', '..', 'assets', '.DS_Store', 'vendor', 'test_db.php', 'test_db_pdo.php', 'phpinfo.php', 'index.php', 'favicon.png', 'composer.json', 'composer.lock', '.github_cache.json', '.github_token'];
$projects = [];
$php_files = [];

foreach ($all_items as $item) {
    if (in_array($item, $excluded)) continue;
    if (is_dir($item)) {
        // Cek config custom .ws
        $ws_file = "$item/.ws";
        $has_custom_ws = file_exists($ws_file);
        $custom_php = '7.4';
        $custom_type = 'auto';
        $custom_entry = 'auto';
        $custom_ide = 'auto';

        if ($has_custom_ws) {
            $ws_data = parse_ini_file($ws_file);
            $custom_php = $ws_data['php'] ?? '7.4';
            $custom_type = $ws_data['type'] ?? 'auto';
            $custom_entry = $ws_data['entry'] ?? 'auto';
            $custom_ide = $ws_data['ide'] ?? 'auto';
        }

        // Deteksi Tipe Framework
        if ($custom_type !== 'auto') {
            $type_names = [
                'laravel' => 'Laravel',
                'ci3' => 'CodeIgniter 3',
                'native' => 'PHP Native'
            ];
            $type = $type_names[$custom_type] ?? 'PHP Native';
        } else {
            $is_laravel = file_exists("$item/artisan") && file_exists("$item/public/index.php");
            $is_ci3 = file_exists("$item/application/config/config.php") || file_exists("$item/system/core/CodeIgniter.php");

            if ($is_laravel) $type = 'Laravel';
            elseif ($is_ci3) $type = 'CodeIgniter 3';
            else $type = 'PHP Native';
        }

        // Deteksi Entry Path (/public atau /)
        if ($custom_entry === 'public') {
            $subpath = "$item/public";
        } elseif ($custom_entry === 'root') {
            $subpath = $item;
        } else {
            // Auto detection (hanya Laravel yang menggunakan /public)
            $subpath = ($type === 'Laravel') ? "$item/public" : $item;
        }

        // Tentukan Port Target
        $target_port = $php_port_map[$custom_php] ?? '8074';
        $full_link = "http://localhost:{$target_port}/{$subpath}";
        $git_info = get_project_git_info($item);

        $projects[] = [
            'name' => $item,
            'link' => $full_link,
            'subpath' => $subpath,
            'type' => $type,
            'php_version' => $custom_php,
            'port' => $target_port,
            'has_custom' => $has_custom_ws,
            'raw_type' => $custom_type,
            'raw_entry' => $custom_entry,
            'raw_ide' => $custom_ide,
            'git_info' => $git_info
        ];
    } elseif (pathinfo($item, PATHINFO_EXTENSION) === 'php') {
        $php_files[] = $item;
    }
}

$unconnected_github = 0;
foreach ($projects as $p) {
    if (!$p['git_info']['is_github']) {
        $unconnected_github++;
    }
}
?>
<!doctype html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>eGov-LAMP — Diskominfo Kabupaten Bintan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-canvas: #090d16;
            --bg-card: #0f172a;
            --bg-card-hover: #131d35;
            --border-subtle: rgba(255, 255, 255, 0.08);
            --border-hover: rgba(56, 189, 248, 0.35);
            --accent-primary: #0284c7;
            --accent-cyan: #38bdf8;
            --accent-emerald: #10b981;
            --accent-rose: #f43f5e;
            --accent-amber: #f59e0b;
            --accent-purple: #a855f7;
            --text-main: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
        }

        body {
            font-family: 'Sora', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-main);
            background-image: 
                radial-gradient(1000px 320px at 50% 0%, rgba(14, 165, 233, 0.08) 0%, transparent 80%),
                radial-gradient(600px 300px at 100% 100%, rgba(99, 102, 241, 0.04) 0%, transparent 80%);
            background-attachment: fixed;
            min-height: 100vh;
        }

        ::selection {
            background: rgba(56, 189, 248, 0.3);
            color: #ffffff;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-canvas); }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }

        /* Command Header */
        .app-header {
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--border-subtle);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .brand-emblem {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, rgba(2, 132, 199, 0.25) 0%, rgba(99, 102, 241, 0.15) 100%);
            border: 1px solid rgba(56, 189, 248, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .badge-active-port {
            background: rgba(56, 189, 248, 0.12);
            color: var(--accent-cyan);
            border: 1px solid rgba(56, 189, 248, 0.25);
            font-size: 0.72rem;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
        }

        /* Engine Hub Pill Bar */
        .engine-cluster {
            background: rgba(0, 0, 0, 0.45);
            border: 1px solid var(--border-subtle);
            padding: 3px;
            border-radius: 30px;
            display: inline-flex;
            gap: 2px;
        }

        .btn-engine {
            padding: 0.2rem 0.65rem;
            border-radius: 20px;
            font-size: 0.74rem;
            font-weight: 600;
            color: var(--text-secondary);
            border: 1px solid transparent;
            text-decoration: none;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
        }

        .btn-engine:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.08);
        }

        .btn-engine.status-active-port {
            background: rgba(56, 189, 248, 0.15);
            border-color: rgba(56, 189, 248, 0.4);
            color: #fff !important;
            box-shadow: 0 0 10px rgba(56, 189, 248, 0.2);
        }

        /* Status Dot */
        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
            transition: all 0.2s ease;
        }
        .status-online { 
            background-color: var(--accent-emerald); 
            box-shadow: 0 0 8px var(--accent-emerald); 
        }
        .status-offline { 
            background-color: var(--text-muted); 
        }

        /* Navigation Pills */
        .custom-pills {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border-subtle);
            padding: 4px;
            border-radius: 10px;
            gap: 4px;
        }
        .custom-pills .nav-link {
            color: var(--text-secondary);
            font-size: 0.82rem;
            font-weight: 600;
            border-radius: 7px;
            padding: 0.35rem 0.75rem;
            transition: all 0.15s ease;
        }
        .custom-pills .nav-link:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.04);
        }
        .custom-pills .nav-link.active {
            background: var(--accent-primary);
            color: #fff;
            box-shadow: 0 2px 8px rgba(2, 132, 199, 0.3);
        }
        .badge-tab {
            font-size: 0.7rem;
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            padding: 0.15rem 0.45rem;
            border-radius: 4px;
            font-variant-numeric: tabular-nums;
        }

        /* Toolbar Actions */
        .btn-primary-action {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            border: 1px solid rgba(56, 189, 248, 0.35);
            color: #fff;
            font-weight: 600;
            font-size: 0.8rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(2, 132, 199, 0.25);
            transition: all 0.15s ease;
        }
        .btn-primary-action:hover {
            background: linear-gradient(135deg, #0369a1 0%, #0284c7 100%);
            border-color: rgba(56, 189, 248, 0.6);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35);
        }

        .search-input-wrap {
            position: relative;
            max-width: 220px;
        }
        .search-input-wrap .search-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.8rem;
            pointer-events: none;
        }
        .custom-search-box {
            background-color: rgba(15, 23, 42, 0.8);
            border: 1px solid var(--border-subtle);
            color: #fff;
            padding-left: 30px;
            border-radius: 8px;
            font-size: 0.8rem;
            transition: all 0.15s ease;
        }
        .custom-search-box:focus {
            background-color: #0f172a;
            border-color: var(--accent-cyan);
            color: #fff;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
        }

        .btn-tool {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid var(--border-subtle);
            color: var(--text-secondary);
            border-radius: 8px;
            padding: 0.3rem 0.6rem;
            font-size: 0.82rem;
            transition: all 0.15s ease;
        }
        .btn-tool:hover {
            background: rgba(30, 41, 59, 0.9);
            border-color: rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        /* Project Cards */
        .project-card, .repo-card {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        }
        .project-card:hover, .repo-card:hover {
            transform: translateY(-2px);
            border-color: var(--border-hover);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.45);
            background: var(--bg-card-hover);
        }

        .project-icon-box {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-subtle);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        /* Badges */
        .badge-engine {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid var(--border-subtle);
            color: var(--accent-cyan);
            font-size: 0.68rem;
            font-weight: 500;
            border-radius: 6px;
            padding: 0.2rem 0.45rem;
            font-variant-numeric: tabular-nums;
        }

        .badge-git-connected {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.25);
            color: #6ee7b7;
            font-size: 0.68rem;
            font-weight: 500;
            border-radius: 6px;
            padding: 0.2rem 0.45rem;
        }

        .badge-git-warning {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fcd34d;
            font-size: 0.68rem;
            font-weight: 500;
            border-radius: 6px;
            padding: 0.2rem 0.45rem;
        }

        .badge-framework-laravel {
            background: rgba(244, 63, 94, 0.12);
            border: 1px solid rgba(244, 63, 94, 0.25);
            color: #fda4af;
            font-size: 0.68rem;
            font-weight: 600;
            border-radius: 6px;
            padding: 0.2rem 0.45rem;
        }

        .badge-framework-ci {
            background: rgba(249, 115, 22, 0.12);
            border: 1px solid rgba(249, 115, 22, 0.25);
            color: #fdba74;
            font-size: 0.68rem;
            font-weight: 600;
            border-radius: 6px;
            padding: 0.2rem 0.45rem;
        }

        .badge-framework-native {
            background: rgba(148, 163, 184, 0.1);
            border: 1px solid rgba(148, 163, 184, 0.2);
            color: #cbd5e1;
            font-size: 0.68rem;
            font-weight: 500;
            border-radius: 6px;
            padding: 0.2rem 0.45rem;
        }

        .badge-private { 
            background: rgba(245, 158, 11, 0.15); 
            border: 1px solid rgba(245, 158, 11, 0.35); 
            color: #fbbf24; 
        }
        .badge-public { 
            background: rgba(6, 182, 212, 0.15); 
            border: 1px solid rgba(6, 182, 212, 0.35); 
            color: #38bdf8; 
        }

        /* Card Action Buttons */
        .btn-card-action {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border-subtle);
            color: var(--text-secondary);
            font-size: 0.72rem;
            font-weight: 500;
            padding: 0.2rem 0.55rem;
            border-radius: 6px;
            transition: all 0.15s ease;
        }
        .btn-card-action:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
            color: #fff;
        }
        .btn-card-action.btn-ide-vscode:hover {
            border-color: rgba(56, 189, 248, 0.4);
            color: var(--accent-cyan);
            background: rgba(56, 189, 248, 0.08);
        }
        .btn-card-action.btn-ide-antigravity:hover {
            border-color: rgba(168, 85, 247, 0.4);
            color: #d8b4fe;
            background: rgba(168, 85, 247, 0.08);
        }

        .btn-card-open {
            background: rgba(2, 132, 199, 0.12);
            border: 1px solid rgba(56, 189, 248, 0.3);
            color: var(--accent-cyan);
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.22rem 0.75rem;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .btn-card-open:hover {
            background: var(--accent-primary);
            border-color: var(--accent-primary);
            color: #fff;
            box-shadow: 0 2px 8px rgba(2, 132, 199, 0.3);
        }

        /* Sidebar Cards */
        .sidebar-card {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
        }
        .sidebar-featured-init {
            background: linear-gradient(180deg, rgba(14, 165, 233, 0.08) 0%, rgba(15, 23, 42, 0.8) 100%);
            border: 1px solid rgba(56, 189, 248, 0.35);
        }

        .sidebar-link-btn {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-subtle);
            color: var(--text-main);
            font-size: 0.82rem;
            font-weight: 500;
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            text-decoration: none;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .sidebar-link-btn:hover {
            background: rgba(255, 255, 255, 0.07);
            border-color: rgba(255, 255, 255, 0.2);
            color: #fff;
            transform: translateX(2px);
        }

        .cred-item {
            padding: 0.4rem 0.6rem;
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid var(--border-subtle);
            border-radius: 6px;
        }

        /* Modals */
        .modal-content {
            background-color: #0f172a;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.7);
        }
        .modal-header {
            border-bottom: 1px solid var(--border-subtle);
            padding: 1rem 1.25rem;
        }
        .modal-footer {
            border-top: 1px solid var(--border-subtle);
            padding: 0.85rem 1.25rem;
        }

        /* IDE Button Visibility: Global Preferences */
        body.hide-vscode .btn-ide-vscode { display: none !important; }
        body.hide-antigravity .btn-ide-antigravity { display: none !important; }

        /* IDE Button Visibility: Per-Project Override via data-ide */
        .project-item[data-ide="none"] .btn-ide-vscode,
        .project-item[data-ide="none"] .btn-ide-antigravity { display: none !important; }

        .project-item[data-ide="vscode"] .btn-ide-antigravity { display: none !important; }
        .project-item[data-ide="vscode"] .btn-ide-vscode { display: inline-flex !important; }

        .project-item[data-ide="antigravity"] .btn-ide-vscode { display: none !important; }
        .project-item[data-ide="antigravity"] .btn-ide-antigravity { display: inline-flex !important; }

        .project-item[data-ide="both"] .btn-ide-vscode,
        .project-item[data-ide="both"] .btn-ide-antigravity { display: inline-flex !important; }
    </style>
</head>
<body>

    <!-- Unified Command Header -->
    <header class="app-header py-3 px-4 mb-4">
        <div class="container-fluid">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <!-- Brand & Stack Meta -->
                <div class="d-flex align-items-center gap-3">
                    <div class="brand-emblem">
                        <i class="bi bi-shield-check text-info fs-5"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h1 class="h6 fw-bold mb-0 text-light tracking-tight">E-GOVERNMENT DISKOMINFO BINTAN</h1>
                            <span class="badge badge-active-port font-monospace"><i class="bi bi-hdd-network me-1"></i>Port: <span id="current-port">...</span></span>
                        </div>
                        <div class="small text-secondary mt-0">
                            Multi-PHP Isolated Development Stack • Active Container: <span class="text-info fw-semibold font-monospace">PHP <?= $php_version ?></span>
                        </div>
                    </div>
                </div>

                <!-- PHP Engines Status Cluster -->
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-secondary small fw-medium me-1 d-none d-md-inline"><i class="bi bi-cpu me-1"></i>PHP Engines:</span>
                    <div class="engine-cluster">
                        <a href="http://localhost:8074" id="btn-php74" class="btn btn-engine" data-port="8074" title="PHP 7.4 (Port 8074)">
                            <span class="status-dot status-offline" id="dot-php74"></span>7.4
                        </a>
                        <a href="http://localhost:8080" id="btn-php80" class="btn btn-engine" data-port="8080" title="PHP 8.0 (Port 8080)">
                            <span class="status-dot status-offline" id="dot-php80"></span>8.0
                        </a>
                        <a href="http://localhost:8081" id="btn-php81" class="btn btn-engine" data-port="8081" title="PHP 8.1 (Port 8081)">
                            <span class="status-dot status-offline" id="dot-php81"></span>8.1
                        </a>
                        <a href="http://localhost:8082" id="btn-php82" class="btn btn-engine" data-port="8082" title="PHP 8.2 (Port 8082)">
                            <span class="status-dot status-offline" id="dot-php82"></span>8.2
                        </a>
                        <a href="http://localhost:8083" id="btn-php83" class="btn btn-engine" data-port="8083" title="PHP 8.3 (Port 8083)">
                            <span class="status-dot status-offline" id="dot-php83"></span>8.3
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Grid -->
    <div class="container-fluid px-4">
        <div class="row g-4">
            
            <!-- Left Column: Project & GitHub Explorer -->
            <div class="col-lg-8">
                <!-- Navigation Tabs & Action Toolbar -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <ul class="nav nav-pills custom-pills" id="projectTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active d-flex align-items-center" id="tab-local" data-bs-toggle="pill" data-bs-target="#pane-local" type="button" role="tab">
                                <i class="bi bi-folder2-open me-2 text-warning"></i>
                                <span>Project Lokal</span>
                                <span class="badge badge-tab ms-2"><?= count($projects) ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link d-flex align-items-center" id="tab-github" data-bs-toggle="pill" data-bs-target="#pane-github" type="button" role="tab" onclick="loadGitHubRepos()">
                                <i class="bi bi-github me-2 text-light"></i>
                                <span>GitHub Repo</span>
                                <span class="badge badge-tab ms-2" id="githubUnclonedCount">...</span>
                            </button>
                        </li>
                    </ul>

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-primary-action py-1 px-3 d-flex align-items-center" onclick="openInitProjectModal()" title="Inisialisasi Project Baru (Template core-laravel)">
                            <i class="bi bi-plus-circle-fill me-1 text-info"></i>
                            <span>Project Baru (Core Laravel)</span>
                        </button>
                        <div class="search-input-wrap">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" id="projectSearch" class="form-control form-control-sm custom-search-box" placeholder="Cari project...">
                        </div>
                        <button class="btn btn-tool" onclick="refreshActiveTab()" title="Muat ulang">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                        <button class="btn btn-tool" onclick="openTokenModal()" title="Pengaturan GitHub Token">
                            <i class="bi bi-key"></i>
                        </button>
                        <button class="btn btn-tool" onclick="openPreferencesModal()" title="Pengaturan Tampilan Dashboard">
                            <i class="bi bi-toggles"></i>
                        </button>
                    </div>
                </div>

                <!-- Tab Content -->
                <div class="tab-content" id="projectTabsContent">
                    <!-- Tab Pane 1: Local Projects -->
                    <div class="tab-pane fade show active" id="pane-local" role="tabpanel">
                        <?php if ($unconnected_github > 0): ?>
                            <div class="alert alert-dark border-warning border-opacity-40 d-flex flex-wrap align-items-center justify-content-between py-2 px-3 mb-3 rounded-3" style="background: rgba(245, 158, 11, 0.06);">
                                <div class="small d-flex align-items-center">
                                    <i class="bi bi-exclamation-triangle-fill text-warning fs-6 me-2"></i>
                                    <span>Terdapat <strong><?= $unconnected_github ?></strong> project lokal yang <strong>belum terhubung ke GitHub</strong>.</span>
                                </div>
                                <button class="btn btn-xs btn-outline-warning py-0 px-2 mt-1 mt-sm-0" style="font-size: 0.75rem;" id="btnToggleUnconnectedGit" onclick="toggleFilterUnconnectedGit()">
                                    <i class="bi bi-funnel me-1"></i><span id="btnFilterGitLabel">Tampilkan Yang Belum Terhubung</span>
                                </button>
                            </div>
                        <?php endif; ?>

                        <div class="row g-3" id="projectGrid">
                            <?php foreach ($projects as $p): ?>
                                <div class="col-md-6 project-item" data-name="<?= strtolower($p['name']) ?>" data-php-port="<?= $p['port'] ?>" data-php-ver="<?= $p['php_version'] ?>" data-ide="<?= htmlspecialchars($p['raw_ide']) ?>" data-has-github="<?= $p['git_info']['is_github'] ? '1' : '0' ?>">
                                    <div class="card project-card h-100 p-3">
                                        <!-- Top Row: Name, Path & Badges -->
                                        <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
                                            <div class="min-w-0 flex-grow-1">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="project-icon-box">
                                                        <i class="bi bi-folder2 text-warning"></i>
                                                    </span>
                                                    <h6 class="card-title fw-bold mb-0 text-truncate font-monospace" title="<?= htmlspecialchars($p['name']) ?>">
                                                        <?= htmlspecialchars($p['name']) ?>
                                                    </h6>
                                                </div>
                                                <div class="text-secondary small font-monospace mt-1" style="font-size: 0.72rem;">
                                                    www/<?= htmlspecialchars($p['name']) ?><?= ($p['type'] === 'Laravel') ? '<span class="text-info opacity-75">/public</span>' : '' ?>
                                                </div>
                                            </div>

                                            <div class="d-flex gap-1 align-items-center flex-wrap justify-content-end flex-shrink-0">
                                                <span class="badge badge-engine" id="port-status-<?= $p['name'] ?>" title="Port PHP <?= $p['port'] ?>">
                                                    <span class="status-dot status-offline" id="card-dot-<?= $p['name'] ?>"></span>PHP <?= $p['php_version'] ?>
                                                </span>
                                                <?php if ($p['git_info']['is_github']): ?>
                                                    <span class="badge badge-git-connected" title="Terhubung ke GitHub: <?= htmlspecialchars($p['git_info']['remote_url']) ?>">
                                                        <i class="bi bi-github me-1"></i>GitHub
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-git-warning" title="<?= $p['git_info']['has_git'] ? 'Belum ada remote origin GitHub' : 'Folder ini belum menjadi git repository' ?>">
                                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>Belum ke GitHub
                                                    </span>
                                                <?php endif; ?>
                                                <?php
                                                    $badge_class = 'badge-framework-native';
                                                    if ($p['type'] === 'Laravel') $badge_class = 'badge-framework-laravel';
                                                    elseif (strpos($p['type'], 'CodeIgniter') !== false) $badge_class = 'badge-framework-ci';
                                                ?>
                                                <span class="badge <?= $badge_class ?>">
                                                    <?= $p['type'] ?>
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Bottom Action Bar -->
                                        <div class="mt-auto d-flex justify-content-between align-items-center pt-2 border-top border-secondary border-opacity-15">
                                            <div class="d-flex gap-1 flex-wrap">
                                                <button class="btn btn-sm btn-card-action" 
                                                        onclick="openSettingModal('<?= $p['name'] ?>', '<?= $p['php_version'] ?>', '<?= $p['raw_type'] ?>', '<?= $p['raw_entry'] ?>', '<?= $p['raw_ide'] ?>', <?= $p['git_info']['has_git'] ? 'true' : 'false' ?>, <?= $p['git_info']['is_github'] ? 'true' : 'false' ?>, '<?= htmlspecialchars(addslashes($p['git_info']['remote_url'])) ?>')">
                                                    <i class="bi bi-gear me-1"></i>Setting
                                                </button>
                                                <button type="button" class="btn btn-sm btn-card-action btn-ide-vscode" title="Buka di VS Code" 
                                                        onclick="openInVSCode('<?= htmlspecialchars($p['name']) ?>')">
                                                    <i class="bi bi-code-slash me-1 text-info"></i>VS Code
                                                </button>
                                                <button type="button" class="btn btn-sm btn-card-action btn-ide-antigravity" title="Buka di Antigravity IDE" 
                                                        onclick="openInAntigravity('<?= htmlspecialchars($p['name']) ?>')">
                                                    <i class="bi bi-rocket-takeoff me-1 text-purple"></i>Antigravity
                                                </button>
                                            </div>
                                            <a href="<?= htmlspecialchars($p['link']) ?>" target="_blank" class="btn btn-sm btn-card-open ms-1"
                                               onclick="return checkContainerBeforeOpen(event, '<?= $p['port'] ?>', '<?= $p['php_version'] ?>')">
                                                <span>Buka</span> <i class="bi bi-arrow-up-right ms-1"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Tab Pane 2: GitHub Repositories -->
                    <div class="tab-pane fade" id="pane-github" role="tabpanel">
                        <!-- Token Notice when PAT is not set -->
                        <div id="githubTokenNotice" class="alert alert-secondary d-flex align-items-center justify-content-between py-2 px-3 mb-3 d-none">
                            <div class="small">
                                <i class="bi bi-info-circle text-info me-1"></i>
                                <span>Menampilkan repositori publik. Masukkan <strong>GitHub Token</strong> untuk mengakses repositori privat organisasi.</span>
                            </div>
                            <button class="btn btn-sm btn-outline-info py-0 px-2 ms-2" onclick="openTokenModal()">
                                <i class="bi bi-key-fill me-1"></i>Atur Token
                            </button>
                        </div>

                        <div id="githubLoading" class="text-center py-5">
                            <div class="spinner-border text-info" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-secondary small mt-2">Mengambil daftar repository dari GitHub organisasi...</p>
                        </div>
                        <div id="githubEmpty" class="text-center py-5 d-none">
                            <i class="bi bi-check-circle-fill text-success fs-1" id="githubEmptyIcon"></i>
                            <h6 class="mt-3 fw-bold" id="githubEmptyTitle">Semua Repository Sudah Di-clone!</h6>
                            <p class="text-secondary small mb-2" id="githubEmptyDesc">Semua project dari organisasi GitHub sudah ada di folder <code>www/</code> lokal Anda.</p>
                            <button class="btn btn-sm btn-outline-info d-none" id="githubEmptyBtn" onclick="openTokenModal()">
                                <i class="bi bi-key-fill me-1"></i>Masukkan GitHub Token
                            </button>
                        </div>
                        <div id="githubError" class="alert alert-warning d-none" role="alert">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <span id="githubErrorMsg"></span>
                                </div>
                                <button class="btn btn-sm btn-outline-warning" onclick="openTokenModal()">Atur Token</button>
                            </div>
                        </div>
                        <div class="row g-3" id="githubGrid">
                            <!-- Injected dynamically via JS -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Shortcuts & Quick Tools -->
            <div class="col-lg-4">
                <!-- Featured Quick Action: Init Core Laravel Project -->
                <div class="card sidebar-card sidebar-featured-init p-3 mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="fw-bold text-uppercase text-info mb-0" style="font-size: 0.8rem; letter-spacing: 0.05rem;">
                            <i class="bi bi-rocket-takeoff-fill me-1 text-warning"></i>Project Baru (Laravel)
                        </h6>
                        <span class="badge bg-primary text-white" style="font-size: 0.65rem;">Template Resmi</span>
                    </div>
                    <p class="text-secondary small mb-3" style="font-size: 0.78rem;">
                        Standard Diskominfo: clone otomatis dari <code>core-laravel</code>, reset git, database lokal, key:generate & migrate.
                    </p>
                    <button type="button" class="btn btn-primary-action btn-sm py-2 w-100 fw-bold" onclick="openInitProjectModal()">
                        <i class="bi bi-plus-circle-fill me-1"></i> Inisialisasi Project Baru
                    </button>
                </div>

                <!-- Shortcuts & Tools -->
                <div class="card sidebar-card p-3 mb-3">
                    <h6 class="fw-bold text-uppercase text-secondary mb-3" style="font-size: 0.78rem; letter-spacing: 0.05rem;">
                        <i class="bi bi-lightning-charge-fill me-1 text-warning"></i>Shortcut & Database
                    </h6>
                    <div class="d-grid gap-2">
                        <a href="http://localhost:8888" target="_blank" class="sidebar-link-btn">
                            <span><i class="bi bi-database me-2 text-success"></i>phpMyAdmin (Port 8888)</span>
                            <i class="bi bi-box-arrow-up-right text-secondary small"></i>
                        </a>
                        <a href="test_db.php" target="_blank" class="sidebar-link-btn">
                            <span><i class="bi bi-check-circle me-2 text-info"></i>Test MySQLi Connection</span>
                            <i class="bi bi-box-arrow-up-right text-secondary small"></i>
                        </a>
                        <a href="test_db_pdo.php" target="_blank" class="sidebar-link-btn">
                            <span><i class="bi bi-check2-all me-2 text-cyan"></i>Test PDO Connection</span>
                            <i class="bi bi-box-arrow-up-right text-secondary small"></i>
                        </a>
                        <a href="phpinfo.php" target="_blank" class="sidebar-link-btn">
                            <span><i class="bi bi-info-circle me-2 text-secondary"></i>PHP Info (<?= $php_version ?>)</span>
                            <i class="bi bi-box-arrow-up-right text-secondary small"></i>
                        </a>
                    </div>
                </div>

                <!-- Database Credential Info -->
                <div class="card sidebar-card p-3 mb-3">
                    <h6 class="fw-bold text-uppercase text-secondary mb-2" style="font-size: 0.78rem; letter-spacing: 0.05rem;">
                        <i class="bi bi-key-fill me-1 text-info"></i>Koneksi Database Lokal
                    </h6>
                    <div class="d-grid gap-1 font-monospace small">
                        <div class="cred-item d-flex justify-content-between align-items-center">
                            <span class="text-secondary">Host:</span>
                            <span class="text-light">127.0.0.1 / database</span>
                        </div>
                        <div class="cred-item d-flex justify-content-between align-items-center">
                            <span class="text-secondary">Port:</span>
                            <span class="text-light">3306</span>
                        </div>
                        <div class="cred-item d-flex justify-content-between align-items-center">
                            <span class="text-secondary">User:</span>
                            <span class="text-light">root / docker</span>
                        </div>
                        <div class="cred-item d-flex justify-content-between align-items-center">
                            <span class="text-secondary">Pass:</span>
                            <span class="text-light">tiger / docker</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Modal Setting Project -->
    <div class="modal fade" id="settingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-secondary text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="bi bi-sliders me-2 text-info"></i>Setting: <span id="modalProjectName" class="text-warning font-monospace"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="settingForm">
                        <input type="hidden" name="project" id="inputProject">
                        <input type="hidden" name="action" value="save_setting">

                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-bold">VERSI PHP DEDIKASI</label>
                            <select class="form-select bg-dark text-light border-secondary" name="php" id="selectPhp">
                                <option value="7.4">PHP 7.4 (Port 8074) - Rekomendasi Legacy / CI3</option>
                                <option value="8.0">PHP 8.0 (Port 8080)</option>
                                <option value="8.1">PHP 8.1 (Port 8081)</option>
                                <option value="8.2">PHP 8.2 (Port 8082)</option>
                                <option value="8.3">PHP 8.3 (Port 8083) - Rekomendasi Laravel 12</option>
                            </select>
                            <small class="text-muted">Tombol 'Buka' akan otomatis membuka port PHP yang Anda pilih di sini.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-bold">TIPE FRAMEWORK</label>
                            <select class="form-select bg-dark text-light border-secondary" name="type" id="selectType">
                                <option value="auto">Auto Detect (Otomatis)</option>
                                <option value="laravel">Laravel</option>
                                <option value="ci3">CodeIgniter 3</option>
                                <option value="native">PHP Native / Web</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-bold">ENTRY URL</label>
                            <select class="form-select bg-dark text-light border-secondary" name="entry" id="selectEntry">
                                <option value="auto">Auto Detect (Laravel pakai /public)</option>
                                <option value="public">Paksa Pakai /public</option>
                                <option value="root">Langsung Root (Tanpa /public)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-bold">TOMBOL EDITOR IDE</label>
                            <select class="form-select bg-dark text-light border-secondary" name="ide" id="selectIde">
                                <option value="auto">Auto (Ikuti Pengaturan Global Tampilan)</option>
                                <option value="antigravity">Hanya Tombol Antigravity</option>
                                <option value="vscode">Hanya Tombol VS Code</option>
                                <option value="both">Paksa Tampilkan Keduanya</option>
                                <option value="none">Sembunyikan Semua Tombol IDE</option>
                            </select>
                        </div>

                        <div class="mb-2 pt-3 border-top border-secondary border-opacity-50" id="settingGitStatusSection">
                            <label class="form-label text-secondary small fw-bold d-flex justify-content-between align-items-center mb-2">
                                <span><i class="bi bi-github me-1"></i>STATUS REPOSITORI GITHUB</span>
                                <span id="settingGitBadge" class="badge bg-secondary">...</span>
                            </label>
                            <div id="settingGitDetails" class="p-2 rounded bg-black bg-opacity-40 border border-secondary border-opacity-50 small">
                                <!-- Dinamis diisi oleh JS -->
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="btnSaveSetting" onclick="saveProjectSetting()">Simpan Pengaturan</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Pengaturan Tampilan Dashboard -->
    <div class="modal fade" id="preferencesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-secondary text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="bi bi-sliders2 me-2 text-info"></i>Pengaturan Tampilan Dashboard</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6 class="text-uppercase text-secondary fw-bold small mb-3">Tombol Editor / IDE di Kartu Project</h6>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="prefShowVSCode" checked onchange="updateIdePreferences()">
                        <label class="form-check-label" for="prefShowVSCode">
                            <i class="bi bi-code-slash text-info me-1"></i> Tampilkan Tombol <strong>VS Code</strong>
                        </label>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="prefShowAntigravity" checked onchange="updateIdePreferences()">
                        <label class="form-check-label" for="prefShowAntigravity">
                            <i class="bi bi-rocket-takeoff text-purple me-1"></i> Tampilkan Tombol <strong>Antigravity IDE</strong>
                        </label>
                    </div>

                    <hr class="border-secondary my-3">

                    <h6 class="text-uppercase text-secondary fw-bold small mb-2">Integrasi Path Windows & WSL</h6>
                    
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-bold">PATH FOLDER WWW</label>
                        <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary font-monospace" id="prefHostPath" placeholder="/home/username/workspace/egov/www">
                        <div class="form-text text-secondary small">
                            Jika project di WSL, masukkan path Linux WSL (contoh: <code>/home/hendra/workspace/egov/www</code>).
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-secondary small fw-bold">DISTRO WSL</label>
                            <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary font-monospace" id="prefWslDistro" placeholder="Ubuntu" value="Ubuntu">
                            <div class="form-text text-secondary" style="font-size: 0.68rem;">Cek: <code>wsl -l -v</code> di PowerShell.</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-secondary small fw-bold">MODE INTEGRASI WSL (VS CODE)</label>
                            <select class="form-select form-select-sm bg-dark text-light border-secondary" id="prefWslMode">
                                <option value="remote">Remote WSL (Rekomendasi)</option>
                                <option value="unc">Network Share (UNC)</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label text-secondary small fw-bold">TARGET BUKA</label>
                            <select class="form-select form-select-sm bg-dark text-light border-secondary" id="prefOpenTarget">
                                <option value="folder">Folder Langsung (Standar /)</option>
                                <option value="workspace">File Workspace (.code-workspace)</option>
                            </select>
                            <div class="form-text text-secondary" style="font-size: 0.68rem;">Pilih Workspace jika folder tidak mau terbuka.</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-secondary small fw-bold">PROTOKOL ANTIGRAVITY</label>
                            <select class="form-select form-select-sm bg-dark text-light border-secondary" id="prefAntigravityProtocol">
                                <option value="antigravity-ide">antigravity-ide:// (Editor Kode)</option>
                                <option value="antigravity">antigravity:// (Desktop Chat Canvas)</option>
                            </select>
                            <div class="form-text text-secondary" style="font-size: 0.68rem;">Default: <code>antigravity-ide://</code></div>
                        </div>
                    </div>

                    <!-- Live Preview & Deep-Link Tester -->
                    <div class="card bg-black bg-opacity-50 border-secondary p-3 mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-uppercase text-secondary fw-bold" style="font-size: 0.72rem; letter-spacing: 0.05rem;">
                                <i class="bi bi-eye-fill me-1 text-info"></i>Live Preview & Pengujian Deep-Link
                            </span>
                            <span class="badge bg-secondary font-monospace" style="font-size: 0.65rem;" id="prefPreviewProjectName">contoh-project</span>
                        </div>
                        
                        <!-- VS Code Preview -->
                        <div class="mb-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small text-info fw-bold" style="font-size: 0.75rem;"><i class="bi bi-code-slash me-1"></i>VS Code URI:</span>
                                <button type="button" class="btn btn-outline-info py-0 px-2" style="font-size: 0.68rem;" onclick="testLaunchEditor('vscode')">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>Tes Buka VS Code
                                </button>
                            </div>
                            <div class="p-1 px-2 bg-dark rounded border border-secondary border-opacity-50 font-monospace text-truncate text-secondary" style="font-size: 0.7rem;" id="prefPreviewVSCodeUri">...</div>
                        </div>

                        <!-- Antigravity Preview -->
                        <div class="mb-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small text-purple fw-bold" style="font-size: 0.75rem;"><i class="bi bi-rocket-takeoff me-1"></i>Antigravity IDE URI:</span>
                                <button type="button" class="btn btn-outline-purple py-0 px-2" style="font-size: 0.68rem;" onclick="testLaunchEditor('antigravity')">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>Tes Buka Antigravity
                                </button>
                            </div>
                            <div class="p-1 px-2 bg-dark rounded border border-secondary border-opacity-50 font-monospace text-truncate text-secondary" style="font-size: 0.7rem;" id="prefPreviewAntigravityUri">...</div>
                        </div>

                        <!-- CLI Command Preview -->
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small text-warning fw-bold" style="font-size: 0.75rem;"><i class="bi bi-terminal me-1"></i>Perintah Terminal CLI:</span>
                                <button type="button" class="btn btn-outline-warning py-0 px-2" style="font-size: 0.68rem;" onclick="copyPreviewCli()">
                                    <i class="bi bi-clipboard me-1"></i>Salin CLI
                                </button>
                            </div>
                            <div class="p-1 px-2 bg-dark rounded border border-secondary border-opacity-50 font-monospace text-truncate text-secondary" style="font-size: 0.7rem;" id="prefPreviewCliCmd">...</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="saveAllPreferences()">Simpan Pengaturan</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Inisialisasi Project Baru (Template core-laravel) -->
    <div class="modal fade" id="initProjectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark border-secondary text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">
                        <i class="bi bi-rocket-takeoff-fill me-2 text-info"></i>Inisialisasi Project Baru <span class="badge bg-primary ms-1" style="font-size: 0.7rem;">core-laravel</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary small mb-3">
                        Membuat project Laravel standar Diskominfo Bintan menggunakan template repository resmi <a href="https://github.com/tim-it-diskominfobintan/core-laravel" target="_blank" class="text-info text-decoration-none"><code>tim-it-diskominfobintan/core-laravel</code></a>.
                    </p>

                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label text-secondary small fw-bold">NAMA PROJECT (NAMA FOLDER LOKAL)</label>
                            <input type="text" class="form-control bg-dark text-light border-secondary font-monospace" id="initProjectName" placeholder="contoh: e-surat, simpeg, srikandi">
                            <div class="form-text text-secondary" style="font-size: 0.72rem;">Hanya huruf, angka, strip (-), dan garis bawah (_). Folder: <code>www/&lt;nama&gt;</code></div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label text-secondary small fw-bold">VERSI PHP</label>
                            <select class="form-select bg-dark text-light border-secondary" id="initProjectPhp">
                                <option value="8.3" selected>PHP 8.3 (Port 8083) - Rekomendasi</option>
                                <option value="8.2">PHP 8.2 (Port 8082)</option>
                                <option value="8.1">PHP 8.1 (Port 8081)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-bold">REMOTE GITHUB BARU (OPSIONAL)</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary font-monospace" id="initProjectRemote" placeholder="https://github.com/tim-it-diskominfobintan/<nama-repo>.git">
                        <div class="form-text text-secondary" style="font-size: 0.72rem;">Jika diisi, remote git origin project ini akan langsung diarahkan ke repo baru tersebut.</div>
                    </div>

                    <div class="card bg-black bg-opacity-40 border-secondary p-3 mb-3">
                        <h6 class="text-uppercase text-secondary fw-bold small mb-2"><i class="bi bi-gear-wide-connected me-1 text-warning"></i>Otomatisasi Siap Pakai (Auto Deploy Pipeline)</h6>
                        <div class="row g-2 small">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="initOptResetGit" checked>
                                    <label class="form-check-label" for="initOptResetGit">Reset Git History (<code>rm -rf .git && git init</code>)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="initOptEnvDb" checked>
                                    <label class="form-check-label" for="initOptEnvDb">Buat <code>.env</code> & Database MariaDB</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="initOptComposer" checked>
                                    <label class="form-check-label" for="initOptComposer">Jalankan <code>composer install</code></label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="initOptKeyStorage" checked>
                                    <label class="form-check-label" for="initOptKeyStorage"><code>key:generate</code> & <code>storage:link</code></label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="initOptMigrateSeed" checked>
                                    <label class="form-check-label" for="initOptMigrateSeed">Migrasi Database (<code>migrate:fresh --seed</code>)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="initOptCommit" checked>
                                    <label class="form-check-label" for="initOptCommit">Initial Git Commit ("chore: initialize...")</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="initProjectAlert" class="alert d-none small mb-2 py-2"></div>
                    <div id="initProjectSteps" class="p-2 rounded bg-black bg-opacity-60 border border-secondary border-opacity-50 small font-monospace d-none" style="max-height: 180px; overflow-y: auto;"></div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="btnCancelInit">Batal</button>
                    <button type="button" class="btn btn-primary" id="btnConfirmInit" onclick="executeInitProject()">
                        <i class="bi bi-rocket-takeoff-fill me-1"></i> Inisialisasi Project Sekarang
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Clone Repo -->
    <div class="modal fade" id="cloneModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark border-secondary text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="bi bi-cloud-arrow-down-fill me-2 text-info"></i>Clone Project: <span id="cloneModalRepoName" class="text-warning font-monospace"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="cloneRepoName">
                    <input type="hidden" id="cloneRepoUrl">
                    <p class="text-secondary small mb-3">
                        Project akan di-clone langsung ke folder <code>www/<span id="cloneTargetFolder"></span></code> dan disiapkan otomatis.
                    </p>
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-bold">PILIH VERSI PHP AWAL</label>
                        <select class="form-select bg-dark text-light border-secondary" id="cloneSelectPhp">
                            <option value="7.4">PHP 7.4 (Port 8074) - Rekomendasi Legacy / CI3</option>
                            <option value="8.0">PHP 8.0 (Port 8080)</option>
                            <option value="8.1">PHP 8.1 (Port 8081)</option>
                            <option value="8.2">PHP 8.2 (Port 8082)</option>
                            <option value="8.3" selected>PHP 8.3 (Port 8083) - Rekomendasi Laravel Terbaru</option>
                        </select>
                        <small class="text-muted">Versi PHP bisa diubah kapan saja di tombol 'Setting'.</small>
                    </div>

                    <div class="card bg-black bg-opacity-40 border-secondary p-3 mb-3">
                        <h6 class="text-uppercase text-secondary fw-bold small mb-2"><i class="bi bi-magic me-1 text-warning"></i>Otomatisasi Siap Deploy (Auto Deploy Pipeline)</h6>
                        <div class="row g-2 small">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="cloneOptEnvDb" checked>
                                    <label class="form-check-label" for="cloneOptEnvDb">Buat <code>.env</code> & Database MariaDB</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="cloneOptComposer" checked>
                                    <label class="form-check-label" for="cloneOptComposer">Jalankan <code>composer install</code></label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="cloneOptKeyStorage" checked>
                                    <label class="form-check-label" for="cloneOptKeyStorage"><code>key:generate</code> & <code>storage:link</code></label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="cloneOptMigrateSeed" checked>
                                    <label class="form-check-label" for="cloneOptMigrateSeed">Migrasi Database (<code>migrate:fresh --seed</code>)</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="cloneAlert" class="alert d-none small mb-2 py-2"></div>
                    <div id="cloneSteps" class="p-2 rounded bg-black bg-opacity-60 border border-secondary border-opacity-50 small font-monospace d-none" style="max-height: 180px; overflow-y: auto;"></div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="btnCancelClone">Batal</button>
                    <button type="button" class="btn btn-success" id="btnConfirmClone" onclick="executeClone()">
                        <i class="bi bi-cloud-download me-1"></i> Mulai Clone & Deploy
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal GitHub Token -->
    <div class="modal fade" id="githubTokenModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-secondary text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="bi bi-key-fill me-2 text-info"></i>Pengaturan GitHub Token</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary mb-3">
                        Token GitHub (Personal Access Token) digunakan untuk mengakses private repository organisasi dan menaikkan batas rate limit GitHub API dari 60 menjadi 5.000 request/jam.
                    </p>
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-bold">GITHUB PERSONAL ACCESS TOKEN (PAT)</label>
                        <input type="password" class="form-control bg-dark text-light border-secondary font-monospace" id="inputGithubToken" placeholder="ghp_xxxxxxxxxxxx atau github_pat_xxxx">
                        <div class="form-text text-secondary small">
                            Minimal hak akses (scope): <code>repo</code> atau <code>read:org</code>. Kosongkan jika ingin menghapus token.
                        </div>
                    </div>
                    <div class="alert alert-info py-2 small mb-0">
                        <i class="bi bi-info-circle me-1"></i> Token disimpan secara aman di file <code>.github_token</code> lokal (terdaftar di <code>.gitignore</code>).
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary" id="btnSaveGithubToken" onclick="saveGithubToken()">Simpan Token</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
        <div id="actionToast" class="toast align-items-center text-bg-dark border-secondary" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastMessage"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const currentPort = window.location.port || '80';
        document.getElementById('current-port').textContent = currentPort;

        const phpContainers = [
            { id: 'php74', port: '8074' },
            { id: 'php80', port: '8080' },
            { id: 'php81', port: '8081' },
            { id: 'php82', port: '8082' },
            { id: 'php83', port: '8083' }
        ];

        phpContainers.forEach(item => {
            const btn = document.getElementById(`btn-${item.id}`);
            if (item.port === currentPort) {
                btn.classList.add('status-active-port', 'fw-bold');
            }
        });

        const activePorts = {};

        phpContainers.forEach(item => {
            const dot = document.getElementById(`dot-${item.id}`);
            const btn = document.getElementById(`btn-${item.id}`);

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 900);

            fetch(`http://localhost:${item.port}/favicon.ico?ping=${Date.now()}`, {
                mode: 'no-cors',
                signal: controller.signal
            }).then(() => {
                clearTimeout(timeoutId);
                activePorts[item.port] = true;
                dot.classList.remove('status-offline');
                dot.classList.add('status-online');
                btn.classList.remove('btn-outline-secondary');
                btn.classList.add('btn-outline-light');
                updateProjectCardsPortStatus(item.port, true);
            }).catch(() => {
                activePorts[item.port] = false;
                dot.classList.remove('status-online');
                dot.classList.add('status-offline');
                updateProjectCardsPortStatus(item.port, false);
            });
        });

        function updateProjectCardsPortStatus(port, isOnline) {
            document.querySelectorAll(`.project-item[data-php-port="${port}"]`).forEach(item => {
                const name = item.getAttribute('data-name');
                const cardDot = document.getElementById(`card-dot-${name}`);
                const cardBadge = document.getElementById(`port-status-${name}`);
                if (cardDot && cardBadge) {
                    if (isOnline) {
                        cardDot.classList.remove('status-offline');
                        cardDot.classList.add('status-online');
                        cardBadge.classList.remove('border-danger', 'text-danger');
                        cardBadge.classList.add('text-info');
                        cardBadge.title = `Container PHP aktif di port ${port}`;
                    } else {
                        cardDot.classList.remove('status-online');
                        cardDot.classList.add('status-offline');
                        cardBadge.classList.remove('text-info');
                        cardBadge.classList.add('border-danger', 'text-danger');
                        cardBadge.title = `PERINGATAN: Container PHP di port ${port} sedang offline!`;
                    }
                }
            });
        }

        function checkContainerBeforeOpen(e, port, phpVer) {
            if (activePorts[port] === false) {
                if (!confirm(`⚠️ PERHATIAN:\nContainer PHP ${phpVer} (Port ${port}) sedang TIDAK AKTIF (Offline)!\n\nUntuk menyalakannya, jalankan perintah 'egov' di terminal lalu pilih nomor versi PHP ${phpVer}.\n\nTetap buka halaman sekarang?`)) {
                    e.preventDefault();
                    return false;
                }
            }
            return true;
        }
        
        // Enhanced Search & Filter (Local Projects & GitHub Repos)
        let filterUnconnectedActive = false;

        function applyLocalProjectFilters() {
            const query = (document.getElementById('projectSearch').value || '').toLowerCase().trim();
            document.querySelectorAll('.project-item').forEach(item => {
                const name = item.getAttribute('data-name') || '';
                const hasGithub = item.getAttribute('data-has-github') === '1';
                const matchQuery = name.includes(query);
                const matchGit = !filterUnconnectedActive || !hasGithub;
                item.style.display = (matchQuery && matchGit) ? '' : 'none';
            });
        }

        function toggleFilterUnconnectedGit() {
            filterUnconnectedActive = !filterUnconnectedActive;
            const label = document.getElementById('btnFilterGitLabel');
            const btn = document.getElementById('btnToggleUnconnectedGit');
            if (label) {
                label.textContent = filterUnconnectedActive ? 'Tampilkan Semua Project' : 'Tampilkan Yang Belum Terhubung';
            }
            if (btn) {
                btn.classList.toggle('btn-warning', filterUnconnectedActive);
                btn.classList.toggle('btn-outline-warning', !filterUnconnectedActive);
            }
            applyLocalProjectFilters();
        }

        document.getElementById('projectSearch').addEventListener('input', function(e) {
            applyLocalProjectFilters();
            const query = e.target.value.toLowerCase().trim();
            // Filter GitHub Repos
            document.querySelectorAll('.github-repo-item').forEach(item => {
                const name = item.getAttribute('data-name') || '';
                item.style.display = name.includes(query) ? '' : 'none';
            });
        });

        // Toast Helper
        function showToast(message) {
            const toastEl = document.getElementById('actionToast');
            const toastMsg = document.getElementById('toastMessage');
            if (toastEl && toastMsg) {
                toastMsg.innerHTML = message;
                const toast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 4000 });
                toast.show();
            }
        }

        // HTML Escape Helper
        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function copyGitInitCmd(cmd) {
            copyText(cmd);
            showToast('<i class="bi bi-clipboard-check text-warning me-2"></i>Perintah Git disalin ke clipboard!');
        }

        // Modal Setting Logic
        let settingModalInstance = null;
        function openSettingModal(project, php, type, entry, ide, hasGit = false, isGithub = false, remoteUrl = '') {
            document.getElementById('modalProjectName').textContent = project;
            document.getElementById('inputProject').value = project;
            document.getElementById('selectPhp').value = php || '7.4';
            document.getElementById('selectType').value = type || 'auto';
            document.getElementById('selectEntry').value = entry || 'auto';
            document.getElementById('selectIde').value = ide || 'auto';

            const badge = document.getElementById('settingGitBadge');
            const details = document.getElementById('settingGitDetails');
            if (badge && details) {
                if (isGithub) {
                    badge.className = 'badge bg-success';
                    badge.innerHTML = '<i class="bi bi-check-circle me-1"></i>Terhubung';
                    details.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-truncate font-monospace text-info me-2 small" title="${escapeHtml(remoteUrl)}">
                                <i class="bi bi-link-45deg me-1"></i>${escapeHtml(remoteUrl)}
                            </span>
                            <a href="${escapeHtml(remoteUrl.replace(/\.git$/, ''))}" target="_blank" class="btn btn-sm btn-outline-info py-0 px-2 text-nowrap" style="font-size: 0.72rem;">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Buka Repo
                            </a>
                        </div>
                    `;
                } else {
                    badge.className = 'badge bg-warning text-dark';
                    badge.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>Belum Terhubung';
                    const gitCmd = hasGit 
                        ? `git remote add origin https://github.com/tim-it-diskominfobintan/${project}.git && git branch -M main && git push -u origin main`
                        : `git init -b main && git add . && git commit -m "chore: initial commit" && git remote add origin https://github.com/tim-it-diskominfobintan/${project}.git && git push -u origin main`;
                    details.innerHTML = `
                        <div class="text-warning mb-1 small"><i class="bi bi-info-circle me-1"></i>${hasGit ? 'Repository lokal belum terhubung ke remote GitHub tim.' : 'Folder ini belum menjadi git repository.'}</div>
                        <div class="text-secondary small mb-1">Hubungkan repository dengan perintah:</div>
                        <div class="d-flex justify-content-between align-items-center bg-dark p-2 rounded border border-secondary border-opacity-50">
                            <pre class="mb-0 text-light font-monospace small" style="font-size: 0.7rem; white-space: pre-wrap; word-break: break-all;">${escapeHtml(gitCmd)}</pre>
                            <button type="button" class="btn btn-sm btn-outline-warning ms-2 py-0 px-2 text-nowrap" style="font-size: 0.72rem;" onclick="copyGitInitCmd('${escapeHtml(gitCmd.replace(/'/g, "\\'"))}')">
                                <i class="bi bi-clipboard me-1"></i>Salin
                            </button>
                        </div>
                    `;
                }
            }

            if (!settingModalInstance) {
                settingModalInstance = new bootstrap.Modal(document.getElementById('settingModal'));
            }
            settingModalInstance.show();
        }

        function saveProjectSetting() {
            const form = document.getElementById('settingForm');
            const formData = new FormData(form);
            const btn = document.getElementById('btnSaveSetting');
            btn.disabled = true;
            btn.textContent = 'Menyimpan...';

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Gagal menyimpan: ' + (data.message || 'Error'));
                    btn.disabled = false;
                    btn.textContent = 'Simpan Pengaturan';
                }
            })
            .catch(err => {
                alert('Terjadi kesalahan koneksi.');
                btn.disabled = false;
                btn.textContent = 'Simpan Pengaturan';
            });
        }

        // Dashboard Preferences Modal Logic
        let preferencesModalInstance = null;

        function applyIdePreferences() {
            const showVSCode = localStorage.getItem('egov_pref_vscode') !== 'false';
            const showAntigravity = localStorage.getItem('egov_pref_antigravity') !== 'false';

            document.body.classList.toggle('hide-vscode', !showVSCode);
            document.body.classList.toggle('hide-antigravity', !showAntigravity);

            const chkVSC = document.getElementById('prefShowVSCode');
            const chkAGY = document.getElementById('prefShowAntigravity');
            if (chkVSC) chkVSC.checked = showVSCode;
            if (chkAGY) chkAGY.checked = showAntigravity;

            const hostPath = localStorage.getItem('egov_host_path') || localStorage.getItem('gov_host_path') || '';
            const inputHostPath = document.getElementById('prefHostPath');
            if (inputHostPath) {
                inputHostPath.value = hostPath;
                inputHostPath.oninput = updatePreviewUrls;
            }

            const wslDistro = localStorage.getItem('egov_wsl_distro') || 'Ubuntu';
            const inputDistro = document.getElementById('prefWslDistro');
            if (inputDistro) {
                inputDistro.value = wslDistro;
                inputDistro.oninput = updatePreviewUrls;
            }

            const wslMode = localStorage.getItem('egov_wsl_mode') || 'remote';
            const selectMode = document.getElementById('prefWslMode');
            if (selectMode) {
                selectMode.value = wslMode;
                selectMode.onchange = updatePreviewUrls;
            }

            const openTarget = localStorage.getItem('egov_open_target') || 'folder';
            const selectTarget = document.getElementById('prefOpenTarget');
            if (selectTarget) {
                selectTarget.value = openTarget;
                selectTarget.onchange = updatePreviewUrls;
            }

            const agyProto = localStorage.getItem('egov_antigravity_protocol') || 'antigravity-ide';
            const selectProto = document.getElementById('prefAntigravityProtocol');
            if (selectProto) {
                selectProto.value = agyProto;
                selectProto.onchange = updatePreviewUrls;
            }

            updatePreviewUrls();
        }

        function updateIdePreferences() {
            const showVSCode = document.getElementById('prefShowVSCode').checked;
            const showAntigravity = document.getElementById('prefShowAntigravity').checked;

            localStorage.setItem('egov_pref_vscode', showVSCode ? 'true' : 'false');
            localStorage.setItem('egov_pref_antigravity', showAntigravity ? 'true' : 'false');

            applyIdePreferences();
            showToast('<i class="bi bi-check-circle-fill text-success me-2"></i>Preferensi tampilan editor diperbarui!');
        }

        function openPreferencesModal() {
            applyIdePreferences();
            if (!preferencesModalInstance) {
                preferencesModalInstance = new bootstrap.Modal(document.getElementById('preferencesModal'));
            }
            preferencesModalInstance.show();
        }

        function saveAllPreferences(closeModal = true) {
            const inputPath = document.getElementById('prefHostPath');
            if (inputPath) {
                const val = inputPath.value.trim().replace(/\\/g, '/').replace(/\/+$/, '');
                if (val) {
                    localStorage.setItem('egov_host_path', val);
                } else {
                    localStorage.removeItem('egov_host_path');
                }
            }

            const inputDistro = document.getElementById('prefWslDistro');
            if (inputDistro) {
                localStorage.setItem('egov_wsl_distro', inputDistro.value.trim() || 'Ubuntu');
            }

            const selectMode = document.getElementById('prefWslMode');
            if (selectMode) {
                localStorage.setItem('egov_wsl_mode', selectMode.value);
            }

            const selectTarget = document.getElementById('prefOpenTarget');
            if (selectTarget) {
                localStorage.setItem('egov_open_target', selectTarget.value);
            }

            const selectProto = document.getElementById('prefAntigravityProtocol');
            if (selectProto) {
                localStorage.setItem('egov_antigravity_protocol', selectProto.value);
            }

            updatePreviewUrls();

            if (closeModal) {
                if (preferencesModalInstance) {
                    preferencesModalInstance.hide();
                }
                showToast('<i class="bi bi-check-circle-fill text-success me-2"></i>Pengaturan preferensi dashboard berhasil disimpan!');
            }
        }

        function getActiveProjectSample() {
            const firstCard = document.querySelector('.project-item');
            return firstCard ? firstCard.getAttribute('data-name') : 'absensi';
        }

        function updatePreviewUrls() {
            const inputPath = document.getElementById('prefHostPath');
            const inputDistro = document.getElementById('prefWslDistro');
            const selectMode = document.getElementById('prefWslMode');
            const selectTarget = document.getElementById('prefOpenTarget');
            const selectProto = document.getElementById('prefAntigravityProtocol');

            let hostPath = inputPath ? inputPath.value.trim().replace(/\\/g, '/').replace(/\/+$/, '') : '';
            const distro = inputDistro ? (inputDistro.value.trim() || 'Ubuntu') : 'Ubuntu';
            const wslMode = selectMode ? selectMode.value : 'remote';
            const openTarget = selectTarget ? selectTarget.value : 'folder';
            const agyProto = selectProto ? selectProto.value : 'antigravity-ide';
            const isWindows = navigator.userAgent.includes('Windows');

            const sampleProject = getActiveProjectSample();
            const badgeProject = document.getElementById('prefPreviewProjectName');
            if (badgeProject) badgeProject.textContent = sampleProject;

            const suffix = (openTarget === 'workspace') 
                ? `${sampleProject}/${sampleProject}.code-workspace` 
                : `${sampleProject}/`;

            let vscodeUri = '';
            let agyUri = '';
            let cliCmd = '';

            if (!hostPath) {
                vscodeUri = '(Masukkan Path Folder www terlebih dahulu)';
                agyUri = '(Masukkan Path Folder www terlebih dahulu)';
                cliCmd = `code www/${sampleProject}`;
            } else {
                if (hostPath.startsWith('//wsl.localhost/') || hostPath.startsWith('//wsl$/')) {
                    vscodeUri = `vscode://file${hostPath}/${suffix}`;
                    agyUri = `${agyProto}://file${hostPath}/${suffix}`;
                    cliCmd = `code "${hostPath.replace(/\//g, '\\')}\\${(openTarget === 'workspace') ? sampleProject + '\\' + sampleProject + '.code-workspace' : sampleProject}"`;
                } else if (isWindows && hostPath.startsWith('/')) {
                    if (wslMode === 'unc') {
                        vscodeUri = `vscode://file//wsl.localhost/${distro}${hostPath}/${suffix}`;
                    } else {
                        vscodeUri = `vscode://vscode-remote/wsl+${distro}${hostPath}/${suffix}`;
                    }
                    agyUri = `${agyProto}://file//wsl.localhost/${distro}${hostPath}/${suffix}`;
                    cliCmd = `code "\\\\wsl.localhost\\${distro}${hostPath.replace(/\//g, '\\')}\\${(openTarget === 'workspace') ? sampleProject + '\\' + sampleProject + '.code-workspace' : sampleProject}"`;
                } else if (/^[a-zA-Z]:/.test(hostPath)) {
                    vscodeUri = `vscode://file/${hostPath}/${suffix}`;
                    agyUri = `${agyProto}://file/${hostPath}/${suffix}`;
                    cliCmd = `code "${hostPath.replace(/\//g, '\\')}\\${(openTarget === 'workspace') ? sampleProject + '\\' + sampleProject + '.code-workspace' : sampleProject}"`;
                } else {
                    vscodeUri = `vscode://file${hostPath}/${suffix}`;
                    agyUri = `${agyProto}://file${hostPath}/${suffix}`;
                    cliCmd = `code "${hostPath}/${(openTarget === 'workspace') ? sampleProject + '/' + sampleProject + '.code-workspace' : sampleProject}"`;
                }
            }

            const elVS = document.getElementById('prefPreviewVSCodeUri');
            if (elVS) elVS.textContent = vscodeUri;

            const elAG = document.getElementById('prefPreviewAntigravityUri');
            if (elAG) elAG.textContent = agyUri;

            const elCli = document.getElementById('prefPreviewCliCmd');
            if (elCli) elCli.textContent = cliCmd;
        }

        // Build Universal Editor URI (Smart Windows WSL / Linux / Mac Translation)
        function buildEditorUri(scheme, projectName) {
            let hostPath = (localStorage.getItem('egov_host_path') || localStorage.getItem('gov_host_path') || '').trim();
            const distro = (localStorage.getItem('egov_wsl_distro') || 'Ubuntu').trim();
            const isWindows = navigator.userAgent.includes('Windows');
            const openTarget = localStorage.getItem('egov_open_target') || 'folder';

            if (!hostPath) {
                const msg = isWindows 
                    ? "Masukkan path folder 'www' di lingkungan Anda:\n\n• Jika di WSL 2: /home/hendra/workspace/egov/www\n• Jika di Windows Drive: D:/egov/www (atau C:/...)\n• Atau UNC: //wsl.localhost/Ubuntu/home/hendra/workspace/egov/www"
                    : "Masukkan path absolut folder 'www':\n(Contoh: /home/hendra/workspace/egov/www)";
                
                hostPath = prompt(msg, "/home/hendra/workspace/egov/www");
                if (hostPath) {
                    hostPath = hostPath.trim().replace(/\/+$/, '');
                    localStorage.setItem('egov_host_path', hostPath);
                } else {
                    return null;
                }
            }

            // Normalisasi backslash ke forward slash
            hostPath = hostPath.replace(/\\/g, '/').replace(/\/+$/, '');

            // Suffix target: folder selalu diakhiri '/' agar VS Code / Antigravity tidak mengiranya file teks biasa
            const suffix = (openTarget === 'workspace') 
                ? `${projectName}/${projectName}.code-workspace` 
                : `${projectName}/`;

            // KASUS 1: Path Windows UNC (//wsl.localhost/ atau //wsl$/)
            if (hostPath.startsWith('//wsl.localhost/') || hostPath.startsWith('//wsl$/')) {
                return `${scheme}://file${hostPath}/${suffix}`;
            }

            // KASUS 2: Path Linux WSL (diawali /home, /var, dll) diakses dari browser Windows
            if (isWindows && hostPath.startsWith('/')) {
                const wslMode = localStorage.getItem('egov_wsl_mode') || 'remote';
                
                // PENTING: Antigravity IDE tidak memiliki ekstensi ms-vscode-remote (proprietary MS)
                // Oleh karena itu, Antigravity IDE di Windows selalu menggunakan format UNC Network Share
                if (scheme.startsWith('antigravity') || wslMode === 'unc') {
                    return `${scheme}://file//wsl.localhost/${distro}${hostPath}/${suffix}`;
                }
                
                // VS Code Resmi (Microsoft Remote - WSL)
                return `${scheme}://vscode-remote/wsl+${distro}${hostPath}/${suffix}`;
            }

            // KASUS 3: Path Drive Windows (C:/... atau D:/...)
            if (/^[a-zA-Z]:/.test(hostPath)) {
                return `${scheme}://file/${hostPath}/${suffix}`;
            }

            // KASUS 4: Path Linux di Linux Native
            return `${scheme}://file${hostPath}/${suffix}`;
        }

        function buildCliCommand(editorCmd, projectName) {
            let hostPath = (localStorage.getItem('egov_host_path') || localStorage.getItem('gov_host_path') || '').trim();
            const distro = (localStorage.getItem('egov_wsl_distro') || 'Ubuntu').trim();
            const isWindows = navigator.userAgent.includes('Windows');
            const openTarget = localStorage.getItem('egov_open_target') || 'folder';
            
            hostPath = hostPath.replace(/\\/g, '/').replace(/\/+$/, '');
            const targetSuffix = (openTarget === 'workspace') ? `${projectName}/${projectName}.code-workspace` : projectName;

            if (isWindows) {
                if (hostPath.startsWith('/')) {
                    const winUnc = `\\\\wsl.localhost\\${distro}${hostPath.replace(/\//g, '\\')}\\${targetSuffix}`;
                    return `${editorCmd} "${winUnc}"`;
                }
                if (/^[a-zA-Z]:/.test(hostPath)) {
                    return `${editorCmd} "${hostPath.replace(/\//g, '\\')}\\${targetSuffix}"`;
                }
            }
            return `${editorCmd} "${hostPath ? hostPath + '/' : 'www/'}${targetSuffix}"`;
        }

        function copyText(str) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(str).then(() => {
                    showToast('<i class="bi bi-check-circle-fill text-success me-2"></i>Perintah CLI disalin ke clipboard!');
                }).catch(() => {});
            }
        }

        function copyPreviewCli() {
            const elCli = document.getElementById('prefPreviewCliCmd');
            if (elCli && elCli.textContent && !elCli.textContent.includes('...')) {
                copyText(elCli.textContent);
            }
        }

        function testLaunchEditor(editorType) {
            saveAllPreferences(false);
            const sampleProject = getActiveProjectSample();
            if (editorType === 'vscode') {
                openInVSCode(sampleProject);
            } else {
                openInAntigravity(sampleProject);
            }
        }

        // Open in VS Code
        function openInVSCode(projectName) {
            // Pastikan file workspace ada di server
            fetch(`index.php?action=ensure_workspace&project=${encodeURIComponent(projectName)}`).catch(() => {});

            const uri = buildEditorUri('vscode', projectName);
            if (!uri) return;

            window.location.href = uri;
            const cliCmd = buildCliCommand('code', projectName);
            if (navigator.clipboard) {
                navigator.clipboard.writeText(cliCmd).catch(() => {});
            }
            showToast(`
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-code-slash text-info me-2"></i>Membuka <strong>${projectName}</strong> di VS Code...<br>
                        <span class="text-secondary small font-monospace">${cliCmd}</span>
                    </div>
                    <button class="btn btn-sm btn-outline-info ms-2 py-0 px-2 text-nowrap" style="font-size: 0.75rem;" onclick="copyText('${cliCmd.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}')">
                        <i class="bi bi-clipboard"></i> Salin
                    </button>
                </div>
            `);
        }

        // Open in Antigravity IDE
        function openInAntigravity(projectName) {
            // Pastikan file workspace ada di server
            fetch(`index.php?action=ensure_workspace&project=${encodeURIComponent(projectName)}`).catch(() => {});

            const scheme = localStorage.getItem('egov_antigravity_protocol') || 'antigravity-ide';
            const uri = buildEditorUri(scheme, projectName);
            if (!uri) return;

            window.location.href = uri;
            const cliCmd = buildCliCommand('antigravity-ide', projectName);
            if (navigator.clipboard) {
                navigator.clipboard.writeText(cliCmd).catch(() => {});
            }
            showToast(`
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-rocket-takeoff text-purple me-2"></i>Membuka Antigravity IDE untuk <strong>${projectName}</strong>...<br>
                        <span class="text-secondary small font-monospace">${cliCmd}</span>
                    </div>
                    <button class="btn btn-sm btn-outline-purple ms-2 py-0 px-2 text-nowrap" style="font-size: 0.75rem;" onclick="copyText('${cliCmd.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}')">
                        <i class="bi bi-clipboard"></i> Salin
                    </button>
                </div>
            `);
        }

        // GitHub Explorer State & Functions
        let githubLoaded = false;
        let cloneModalInstance = null;
        let tokenModalInstance = null;

        function refreshActiveTab() {
            const githubTab = document.getElementById('tab-github');
            if (githubTab && githubTab.classList.contains('active')) {
                loadGitHubRepos(true);
            } else {
                location.reload();
            }
        }

        function loadGitHubRepos(force = false) {
            const loadingEl = document.getElementById('githubLoading');
            const emptyEl = document.getElementById('githubEmpty');
            const errorEl = document.getElementById('githubError');
            const gridEl = document.getElementById('githubGrid');
            const badgeCount = document.getElementById('githubUnclonedCount');

            if (force || !githubLoaded) {
                loadingEl.classList.remove('d-none');
                emptyEl.classList.add('d-none');
                errorEl.classList.add('d-none');
                gridEl.innerHTML = '';
            }

            fetch(`?action=get_github_repos${force ? '&force=1' : ''}`)
                .then(res => res.json())
                .then(data => {
                    loadingEl.classList.add('d-none');
                    githubLoaded = true;

                    if (!data.success) {
                        badgeCount.textContent = '!';
                        errorEl.classList.remove('d-none');
                        document.getElementById('githubErrorMsg').innerHTML = data.message || 'Gagal memuat repository GitHub.';
                        return;
                    }

                    badgeCount.textContent = data.total_uncloned;

                    const noticeEl = document.getElementById('githubTokenNotice');
                    if (noticeEl) {
                        if (!data.has_token) {
                            noticeEl.classList.remove('d-none');
                        } else {
                            noticeEl.classList.add('d-none');
                        }
                    }

                    if (data.total_uncloned === 0) {
                        emptyEl.classList.remove('d-none');
                        gridEl.innerHTML = '';
                        const emptyIcon = document.getElementById('githubEmptyIcon');
                        const emptyTitle = document.getElementById('githubEmptyTitle');
                        const emptyDesc = document.getElementById('githubEmptyDesc');
                        const emptyBtn = document.getElementById('githubEmptyBtn');
                        
                        if (!data.has_token) {
                            if (emptyIcon) emptyIcon.className = 'bi bi-shield-lock-fill text-warning fs-1';
                            if (emptyTitle) emptyTitle.textContent = 'Tidak Ada Repositori Publik Baru';
                            if (emptyDesc) emptyDesc.innerHTML = 'Jika project organisasi Anda bersifat <strong>Private</strong> di GitHub, silakan masukkan GitHub Personal Access Token (PAT) agar dapat ditampilkan.';
                            if (emptyBtn) emptyBtn.classList.remove('d-none');
                        } else {
                            if (emptyIcon) emptyIcon.className = 'bi bi-check-circle-fill text-success fs-1';
                            if (emptyTitle) emptyTitle.textContent = 'Semua Repository Sudah Di-clone!';
                            if (emptyDesc) emptyDesc.innerHTML = 'Semua project dari organisasi GitHub sudah ada di folder <code>www/</code> lokal Anda.';
                            if (emptyBtn) emptyBtn.classList.add('d-none');
                        }
                        return;
                    }

                    emptyEl.classList.add('d-none');
                    renderGitHubRepos(data.repos);
                })
                .catch(err => {
                    loadingEl.classList.add('d-none');
                    badgeCount.textContent = '!';
                    errorEl.classList.remove('d-none');
                    document.getElementById('githubErrorMsg').textContent = 'Koneksi ke server terputus saat mengambil data GitHub.';
                });
        }

        function renderGitHubRepos(repos) {
            const gridEl = document.getElementById('githubGrid');
            const query = (document.getElementById('projectSearch').value || '').toLowerCase().trim();

            gridEl.innerHTML = repos.map(repo => {
                const isHidden = query && !repo.name.toLowerCase().includes(query) ? 'style="display:none;"' : '';
                return `
                    <div class="col-md-6 github-repo-item" data-name="${escapeHtml(repo.name.toLowerCase())}" ${isHidden}>
                        <div class="card repo-card h-100 p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
                                <div class="min-w-0 flex-grow-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="project-icon-box">
                                            <i class="bi bi-github text-light"></i>
                                        </span>
                                        <h6 class="card-title fw-bold mb-0 text-truncate font-monospace" title="${escapeHtml(repo.name)}">
                                            ${escapeHtml(repo.name)}
                                        </h6>
                                    </div>
                                    <div class="text-secondary small font-monospace mt-1" style="font-size: 0.72rem;">
                                        tim-it-diskominfobintan/${escapeHtml(repo.name)}
                                    </div>
                                </div>
                                <div class="d-flex gap-1 align-items-center flex-wrap justify-content-end flex-shrink-0">
                                    <span class="badge ${repo.is_private ? 'badge-private' : 'badge-public'}" style="font-size: 0.68rem;">
                                        ${repo.is_private ? '<i class="bi bi-lock-fill me-1"></i>Private' : '<i class="bi bi-globe me-1"></i>Public'}
                                    </span>
                                    ${repo.language ? `<span class="badge bg-secondary bg-opacity-25 border border-secondary text-light font-monospace" style="font-size: 0.68rem;">${escapeHtml(repo.language)}</span>` : ''}
                                </div>
                            </div>
                            <p class="text-secondary small mb-3 flex-grow-1" style="font-size: 0.78rem; min-height: 2.3rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.45;" title="${escapeHtml(repo.description)}">
                                ${escapeHtml(repo.description)}
                            </p>
                            <div class="mt-auto d-flex justify-content-between align-items-center pt-2 border-top border-secondary border-opacity-15">
                                <div class="d-flex gap-1">
                                    <a href="${escapeHtml(repo.html_url)}" target="_blank" class="btn btn-sm btn-card-action" title="Lihat di GitHub">
                                        <i class="bi bi-box-arrow-up-right me-1"></i>GitHub
                                    </a>
                                    <button type="button" class="btn btn-sm btn-card-action" title="Salin Perintah CLI" onclick="copyCliClone('${escapeHtml(repo.name)}')">
                                        <i class="bi bi-terminal me-1"></i>CLI
                                    </button>
                                </div>
                                <button type="button" class="btn btn-sm btn-card-open" style="background: rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.35); color: #6ee7b7;" onclick="openCloneModal('${escapeHtml(repo.name)}', '${escapeHtml(repo.clone_url)}')">
                                    <i class="bi bi-cloud-arrow-down-fill me-1"></i>Clone
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Init Project (core-laravel template) Modal & Execution
        let initProjectModalInstance = null;
        function openInitProjectModal() {
            document.getElementById('initProjectName').value = '';
            document.getElementById('initProjectRemote').value = '';
            document.getElementById('initProjectAlert').className = 'alert d-none small mb-2 py-2';
            document.getElementById('initProjectAlert').innerHTML = '';
            document.getElementById('initProjectSteps').className = 'p-2 rounded bg-black bg-opacity-60 border border-secondary border-opacity-50 small font-monospace d-none';
            document.getElementById('initProjectSteps').innerHTML = '';

            const btnConfirm = document.getElementById('btnConfirmInit');
            const btnCancel = document.getElementById('btnCancelInit');
            btnConfirm.disabled = false;
            btnCancel.disabled = false;
            btnConfirm.innerHTML = '<i class="bi bi-rocket-takeoff-fill me-1"></i> Inisialisasi Project Sekarang';

            if (!initProjectModalInstance) {
                initProjectModalInstance = new bootstrap.Modal(document.getElementById('initProjectModal'));
            }
            initProjectModalInstance.show();
        }

        function executeInitProject() {
            const name = document.getElementById('initProjectName').value.trim();
            const php = document.getElementById('initProjectPhp').value;
            const remote = document.getElementById('initProjectRemote').value.trim();
            const resetGit = document.getElementById('initOptResetGit').checked;
            const optEnvDb = document.getElementById('initOptEnvDb').checked;
            const optComposer = document.getElementById('initOptComposer').checked;
            const optKeyStorage = document.getElementById('initOptKeyStorage').checked;
            const optMigrateSeed = document.getElementById('initOptMigrateSeed').checked;
            const optCommit = document.getElementById('initOptCommit').checked;

            const alertEl = document.getElementById('initProjectAlert');
            const stepsEl = document.getElementById('initProjectSteps');
            const btnConfirm = document.getElementById('btnConfirmInit');
            const btnCancel = document.getElementById('btnCancelInit');

            if (!name) {
                alertEl.className = 'alert alert-danger small py-2 mb-2';
                alertEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> Harap masukkan nama project!';
                alertEl.classList.remove('d-none');
                return;
            }

            btnConfirm.disabled = true;
            btnCancel.disabled = true;
            btnConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menginisialisasi...';

            alertEl.className = 'alert alert-info small py-2 mb-2';
            alertEl.innerHTML = `<i class="bi bi-hourglass-split me-1"></i> Meng-clone <strong>core-laravel</strong> dan menyiapkan otomatisasi untuk <strong>${escapeHtml(name)}</strong>... Mohon tunggu.`;
            alertEl.classList.remove('d-none');

            stepsEl.classList.remove('d-none');
            stepsEl.innerHTML = '<div class="text-secondary"><i class="bi bi-arrow-repeat me-1 spinner-border spinner-border-sm" style="width:0.8rem;height:0.8rem;"></i> Menjalankan pipeline inisialisasi...</div>';

            const formData = new FormData();
            formData.append('action', 'init_project');
            formData.append('name', name);
            formData.append('php', php);
            formData.append('remote_url', remote);
            formData.append('opt_reset_git', resetGit ? '1' : '0');
            formData.append('opt_env_db', optEnvDb ? '1' : '0');
            formData.append('opt_composer', optComposer ? '1' : '0');
            formData.append('opt_key_storage', optKeyStorage ? '1' : '0');
            formData.append('opt_migrate_seed', optMigrateSeed ? '1' : '0');
            formData.append('opt_initial_commit', optCommit ? '1' : '0');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alertEl.className = 'alert alert-success small py-2 mb-2';
                    alertEl.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> ${escapeHtml(data.message)}`;
                    
                    if (data.steps && data.steps.length > 0) {
                        stepsEl.innerHTML = data.steps.map(s => {
                            const icon = s.status === 'ok' ? '<i class="bi bi-check-circle text-success me-1"></i>' : (s.status === 'skip' ? '<i class="bi bi-dash-circle text-secondary me-1"></i>' : '<i class="bi bi-exclamation-circle text-warning me-1"></i>');
                            return `<div class="mb-1">${icon}<strong>${escapeHtml(s.step)}:</strong> <span class="text-secondary">${escapeHtml(s.message)}</span></div>`;
                        }).join('');
                    }

                    setTimeout(() => {
                        initProjectModalInstance.hide();
                        location.reload();
                    }, 2000);
                } else {
                    alertEl.className = 'alert alert-danger small py-2 mb-2';
                    alertEl.innerHTML = `<i class="bi bi-x-circle-fill me-1"></i> ${escapeHtml(data.message || 'Gagal inisialisasi project.')}`;
                    btnConfirm.disabled = false;
                    btnCancel.disabled = false;
                    btnConfirm.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Coba Lagi';
                }
            })
            .catch(err => {
                alertEl.className = 'alert alert-danger small py-2 mb-2';
                alertEl.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i> Terjadi kesalahan koneksi atau timeout.';
                btnConfirm.disabled = false;
                btnCancel.disabled = false;
                btnConfirm.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Coba Lagi';
            });
        }

        // Clone Modal & Execution
        function openCloneModal(repoName, cloneUrl) {
            document.getElementById('cloneModalRepoName').textContent = repoName;
            document.getElementById('cloneTargetFolder').textContent = repoName;
            document.getElementById('cloneRepoName').value = repoName;
            document.getElementById('cloneRepoUrl').value = cloneUrl;
            document.getElementById('cloneAlert').className = 'alert d-none small mb-2 py-2';
            document.getElementById('cloneAlert').innerHTML = '';
            document.getElementById('cloneSteps').className = 'p-2 rounded bg-black bg-opacity-60 border border-secondary border-opacity-50 small font-monospace d-none';
            document.getElementById('cloneSteps').innerHTML = '';

            const btnConfirm = document.getElementById('btnConfirmClone');
            const btnCancel = document.getElementById('btnCancelClone');
            btnConfirm.disabled = false;
            btnCancel.disabled = false;
            btnConfirm.innerHTML = '<i class="bi bi-cloud-download me-1"></i> Mulai Clone & Deploy';

            if (!cloneModalInstance) {
                cloneModalInstance = new bootstrap.Modal(document.getElementById('cloneModal'));
            }
            cloneModalInstance.show();
        }

        function executeClone() {
            const repo = document.getElementById('cloneRepoName').value;
            const php = document.getElementById('cloneSelectPhp').value;
            const optEnvDb = document.getElementById('cloneOptEnvDb').checked;
            const optComposer = document.getElementById('cloneOptComposer').checked;
            const optKeyStorage = document.getElementById('cloneOptKeyStorage').checked;
            const optMigrateSeed = document.getElementById('cloneOptMigrateSeed').checked;

            const alertEl = document.getElementById('cloneAlert');
            const stepsEl = document.getElementById('cloneSteps');
            const btnConfirm = document.getElementById('btnConfirmClone');
            const btnCancel = document.getElementById('btnCancelClone');

            btnConfirm.disabled = true;
            btnCancel.disabled = true;
            btnConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyiapkan...';
            alertEl.className = 'alert alert-info small py-2 mb-2';
            alertEl.innerHTML = `<i class="bi bi-hourglass-split me-1"></i> Sedang meng-clone repository <strong>${escapeHtml(repo)}</strong> dan menjalankan auto deploy pipeline... Mohon tunggu.`;
            alertEl.classList.remove('d-none');

            stepsEl.classList.remove('d-none');
            stepsEl.innerHTML = '<div class="text-secondary"><i class="bi bi-arrow-repeat me-1 spinner-border spinner-border-sm" style="width:0.8rem;height:0.8rem;"></i> Memulai clone dan dependensi...</div>';

            const formData = new FormData();
            formData.append('action', 'clone_repo');
            formData.append('repo', repo);
            formData.append('php', php);
            formData.append('opt_env_db', optEnvDb ? '1' : '0');
            formData.append('opt_composer', optComposer ? '1' : '0');
            formData.append('opt_key_storage', optKeyStorage ? '1' : '0');
            formData.append('opt_migrate_seed', optMigrateSeed ? '1' : '0');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alertEl.className = 'alert alert-success small py-2 mb-2';
                    alertEl.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> ${escapeHtml(data.message)}`;
                    
                    if (data.steps && data.steps.length > 0) {
                        stepsEl.innerHTML = data.steps.map(s => {
                            const icon = s.status === 'ok' ? '<i class="bi bi-check-circle text-success me-1"></i>' : (s.status === 'skip' ? '<i class="bi bi-dash-circle text-secondary me-1"></i>' : '<i class="bi bi-exclamation-circle text-warning me-1"></i>');
                            return `<div class="mb-1">${icon}<strong>${escapeHtml(s.step)}:</strong> <span class="text-secondary">${escapeHtml(s.message)}</span></div>`;
                        }).join('');
                    }

                    setTimeout(() => {
                        cloneModalInstance.hide();
                        location.reload();
                    }, 2000);
                } else {
                    alertEl.className = 'alert alert-danger small py-2 mb-2';
                    alertEl.innerHTML = `<i class="bi bi-x-circle-fill me-1"></i> ${escapeHtml(data.message || 'Gagal melakukan clone.')}`;
                    btnConfirm.disabled = false;
                    btnCancel.disabled = false;
                    btnConfirm.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Coba Lagi';
                }
            })
            .catch(err => {
                alertEl.className = 'alert alert-danger small py-2 mb-2';
                alertEl.innerHTML = `<i class="bi bi-x-circle-fill me-1"></i> Terjadi kesalahan koneksi saat clone.`;
                btnConfirm.disabled = false;
                btnCancel.disabled = false;
                btnConfirm.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Coba Lagi';
            });
        }

        function copyCliClone(repoName) {
            const cmd = `./cli/clone-project.sh ${repoName}`;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(cmd).catch(() => {});
            }
            showToast(`<i class="bi bi-terminal text-info me-2"></i>Perintah disalin: <code>${cmd}</code>`);
        }

        // GitHub Token Modal & Save
        function openTokenModal() {
            if (!tokenModalInstance) {
                tokenModalInstance = new bootstrap.Modal(document.getElementById('githubTokenModal'));
            }
            tokenModalInstance.show();
        }

        function saveGithubToken() {
            const token = document.getElementById('inputGithubToken').value.trim();
            const btn = document.getElementById('btnSaveGithubToken');
            btn.disabled = true;
            btn.textContent = 'Menyimpan...';

            const formData = new FormData();
            formData.append('action', 'save_token');
            formData.append('token', token);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.textContent = 'Simpan Token';
                if (data.success) {
                    tokenModalInstance.hide();
                    showToast('<i class="bi bi-check-circle-fill text-success me-2"></i>Token GitHub berhasil diperbarui!');
                    loadGitHubRepos(true);
                } else {
                    alert('Gagal menyimpan token.');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.textContent = 'Simpan Token';
                alert('Terjadi kesalahan koneksi.');
            });
        }

        // Init on page load: Apply IDE preferences and fetch GitHub count
        document.addEventListener('DOMContentLoaded', function() {
            applyIdePreferences();
            loadGitHubRepos(false);
        });
    </script>
</body>
</html>