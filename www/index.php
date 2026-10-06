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
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menulis file .ws (Permission Denied). Jalankan fix-perms di terminal.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Project tidak valid']);
    }
    exit;
}

// 2. Handle API Get GitHub Repositories
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

// 3. Handle API Clone Repository
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clone_repo') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
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

    // Set versi PHP di .ws
    $content = "php={$php}\ntype=auto\nentry=auto\n";
    @file_put_contents("./$repo/.ws", $content);
    @chmod("./$repo/.ws", 0666);

    // Setup .env jika ada .env.example
    if (file_exists("./$repo/.env.example") && !file_exists("./$repo/.env")) {
        $env = @file_get_contents("./$repo/.env.example");
        if ($env) {
            $env = preg_replace('/^DB_HOST=.*/m', 'DB_HOST=database', $env);
            $env = preg_replace('/^DB_PORT=.*/m', 'DB_PORT=3306', $env);
            $env = preg_replace('/^DB_USERNAME=.*/m', 'DB_USERNAME=root', $env);
            $env = preg_replace('/^DB_PASSWORD=.*/m', 'DB_PASSWORD=tiger', $env);
            $env = preg_replace('/^DB_DATABASE=.*/m', "DB_DATABASE={$repo}", $env);
            $env = preg_replace('/^REDIS_HOST=.*/m', 'REDIS_HOST=redis', $env);
            @file_put_contents("./$repo/.env", $env);
        }
    }

    // Fix permissions
    @chmod("./$repo", 0777);
    if (is_dir("./$repo/storage")) {
        exec("chmod -R 777 " . escapeshellarg("./$repo/storage") . " 2>/dev/null");
    }
    if (is_dir("./$repo/bootstrap/cache")) {
        exec("chmod -R 777 " . escapeshellarg("./$repo/bootstrap/cache") . " 2>/dev/null");
    }

    @unlink('./.github_cache.json');

    echo json_encode([
        'success' => true,
        'message' => "Project {$repo} berhasil di-clone dengan PHP {$php}!"
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
            'raw_ide' => $custom_ide
        ];
    } elseif (pathinfo($item, PATHINFO_EXTENSION) === 'php') {
        $php_files[] = $item;
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
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background-color: #0f172a; color: #f8fafc; }
        .hero { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 1px solid #334155; }
        .project-card { transition: transform 0.15s ease, border-color 0.15s ease; background-color: #1e293b; border: 1px solid #334155; }
        .project-card:hover { transform: translateY(-3px); border-color: #38bdf8; }
        .badge-php { background-color: #6366f1; }
        .badge-laravel { background-color: #ef4444; }
        .badge-ci { background-color: #f97316; }
        .search-box { background-color: #1e293b; border: 1px solid #334155; color: #fff; }
        .search-box:focus { background-color: #1e293b; color: #fff; border-color: #38bdf8; box-shadow: none; }
        .sidebar-card { background-color: #1e293b; border: 1px solid #334155; }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .status-online { background-color: #22c55e; box-shadow: 0 0 8px #22c55e; }
        .status-offline { background-color: #64748b; }
        .status-active-port { border-color: #38bdf8 !important; color: #38bdf8 !important; }

        /* Custom buttons & tabs */
        .btn-outline-purple { color: #c084fc; border-color: #a855f7; }
        .btn-outline-purple:hover { background-color: #a855f7; color: #fff; }
        .badge-private { background-color: #f59e0b; color: #000; }
        .badge-public { background-color: #06b6d4; color: #000; }
        .nav-pills .nav-link { color: #94a3b8; border-radius: 8px; font-weight: 500; }
        .nav-pills .nav-link:hover { color: #f8fafc; }
        .nav-pills .nav-link.active { background-color: #0284c7; color: #fff; }
        .repo-card { transition: transform 0.15s ease, border-color 0.15s ease; background-color: #1e293b; border: 1px solid #334155; }
        .repo-card:hover { transform: translateY(-3px); border-color: #38bdf8; }

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

    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg border-bottom border-secondary border-opacity-25 px-4 py-2">
        <div class="container-fluid">
            <span class="navbar-brand fw-bold text-info"><i class="bi bi-shield-check me-2"></i>E-GOVERNMENT DISKOMINFO BINTAN</span>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-secondary font-monospace"><i class="bi bi-hdd-network me-1"></i>Port: <span id="current-port"></span></span>
                <span class="badge badge-php font-monospace"><i class="bi bi-filetype-php me-1"></i>PHP <?= $php_version ?></span>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero py-4 px-4 mb-4">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <h1 class="h3 fw-bold mb-1">Portal Aplikasi e-Government Diskominfo Bintan</h1>
                    <p class="text-secondary mb-0">Lingkungan kerja lokal terisolasi multi-PHP Bidang e-Government Diskominfo Kabupaten Bintan.</p>
                </div>
                <div class="col-md-5 text-md-end mt-3 mt-md-0">
                    <div class="d-inline-flex flex-wrap gap-2 justify-content-md-end align-items-center">
                        <span class="text-secondary small me-1"><i class="bi bi-cpu me-1"></i>PHP Containers:</span>
                        
                        <a href="http://localhost:8074" id="btn-php74" class="btn btn-sm btn-outline-secondary" data-port="8074">
                            <span class="status-dot status-offline" id="dot-php74"></span>7.4
                        </a>
                        <a href="http://localhost:8080" id="btn-php80" class="btn btn-sm btn-outline-secondary" data-port="8080">
                            <span class="status-dot status-offline" id="dot-php80"></span>8.0
                        </a>
                        <a href="http://localhost:8081" id="btn-php81" class="btn btn-sm btn-outline-secondary" data-port="8081">
                            <span class="status-dot status-offline" id="dot-php81"></span>8.1
                        </a>
                        <a href="http://localhost:8082" id="btn-php82" class="btn btn-sm btn-outline-secondary" data-port="8082">
                            <span class="status-dot status-offline" id="dot-php82"></span>8.2
                        </a>
                        <a href="http://localhost:8083" id="btn-php83" class="btn btn-sm btn-outline-secondary" data-port="8083">
                            <span class="status-dot status-offline" id="dot-php83"></span>8.3
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid px-4">
        <div class="row g-4">
            
            <!-- Left Column: Project & GitHub Explorer -->
            <div class="col-lg-8">
                <!-- Navigation Tabs & Toolbar -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <ul class="nav nav-pills" id="projectTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-1 px-3" id="tab-local" data-bs-toggle="pill" data-bs-target="#pane-local" type="button" role="tab">
                                <i class="bi bi-folder2-open me-1 text-warning"></i>Project Lokal <span class="badge bg-secondary ms-1"><?= count($projects) ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-1 px-3" id="tab-github" data-bs-toggle="pill" data-bs-target="#pane-github" type="button" role="tab" onclick="loadGitHubRepos()">
                                <i class="bi bi-github me-1 text-light"></i>GitHub Repo <span class="badge bg-info text-dark ms-1" id="githubUnclonedCount">...</span>
                            </button>
                        </li>
                    </ul>

                    <div class="d-flex align-items-center gap-2">
                        <div class="input-group input-group-sm" style="max-width: 230px;">
                            <span class="input-group-text bg-transparent border-secondary border-opacity-25 text-secondary"><i class="bi bi-search"></i></span>
                            <input type="text" id="projectSearch" class="form-control search-box" placeholder="Cari project...">
                        </div>
                        <button class="btn btn-sm btn-outline-secondary" onclick="refreshActiveTab()" title="Muat ulang">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="openTokenModal()" title="Pengaturan GitHub Token">
                            <i class="bi bi-key"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="openPreferencesModal()" title="Pengaturan Tampilan Dashboard">
                            <i class="bi bi-toggles"></i>
                        </button>
                    </div>
                </div>

                <!-- Tab Content -->
                <div class="tab-content" id="projectTabsContent">
                    <!-- Tab Pane 1: Local Projects -->
                    <div class="tab-pane fade show active" id="pane-local" role="tabpanel">
                        <div class="row g-3" id="projectGrid">
                            <?php foreach ($projects as $p): ?>
                                <div class="col-md-6 project-item" data-name="<?= strtolower($p['name']) ?>" data-php-port="<?= $p['port'] ?>" data-php-ver="<?= $p['php_version'] ?>" data-ide="<?= htmlspecialchars($p['raw_ide']) ?>">
                                    <div class="card project-card h-100 p-3">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title fw-bold mb-0 text-truncate font-monospace" style="max-width: 55%;">
                                                <?= htmlspecialchars($p['name']) ?>
                                            </h6>
                                            <div class="d-flex gap-1 align-items-center flex-wrap justify-content-end">
                                                <span class="badge bg-dark border border-secondary text-info font-monospace" style="font-size: 0.68rem;" id="port-status-<?= $p['name'] ?>" title="Port PHP <?= $p['port'] ?>">
                                                    <span class="status-dot status-offline" id="card-dot-<?= $p['name'] ?>"></span>PHP <?= $p['php_version'] ?>
                                                </span>
                                                <?php
                                                    $badge_class = 'bg-secondary';
                                                    if ($p['type'] === 'Laravel') $badge_class = 'badge-laravel';
                                                    elseif (strpos($p['type'], 'CodeIgniter') !== false) $badge_class = 'badge-ci';
                                                ?>
                                                <span class="badge <?= $badge_class ?> text-white" style="font-size: 0.68rem;">
                                                    <?= $p['type'] ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="mt-auto d-flex justify-content-between align-items-center pt-2 border-top border-secondary border-opacity-25">
                                            <div class="d-flex gap-1 flex-wrap">
                                                <button class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;" 
                                                        onclick="openSettingModal('<?= $p['name'] ?>', '<?= $p['php_version'] ?>', '<?= $p['raw_type'] ?>', '<?= $p['raw_entry'] ?>', '<?= $p['raw_ide'] ?>')">
                                                    <i class="bi bi-gear me-1"></i>Setting
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-info py-0 px-2 btn-ide-vscode" style="font-size: 0.75rem;" title="Buka di VS Code" 
                                                        onclick="openInVSCode('<?= htmlspecialchars($p['name']) ?>')">
                                                    <i class="bi bi-code-slash me-1"></i>VS Code
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-purple py-0 px-2 btn-ide-antigravity" style="font-size: 0.75rem;" title="Buka di Antigravity IDE" 
                                                        onclick="openInAntigravity('<?= htmlspecialchars($p['name']) ?>')">
                                                    <i class="bi bi-rocket-takeoff me-1"></i>Antigravity
                                                </button>
                                            </div>
                                            <a href="<?= htmlspecialchars($p['link']) ?>" target="_blank" class="btn btn-sm btn-outline-primary py-1 px-3 ms-1"
                                               onclick="return checkContainerBeforeOpen(event, '<?= $p['port'] ?>', '<?= $p['php_version'] ?>')">
                                                Buka <i class="bi bi-box-arrow-up-right ms-1"></i>
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
                <div class="card sidebar-card p-3 mb-3">
                    <h6 class="fw-bold text-uppercase text-secondary mb-3" style="font-size: 0.8rem; letter-spacing: 0.05rem;">
                        <i class="bi bi-lightning-charge-fill me-1 text-warning"></i>Shortcut & Database
                    </h6>
                    <div class="d-grid gap-2">
                        <a href="http://localhost:8888" target="_blank" class="btn btn-outline-success text-start d-flex justify-content-between align-items-center py-2">
                            <span><i class="bi bi-database me-2"></i>phpMyAdmin (Port 8888)</span>
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                        <a href="test_db.php" target="_blank" class="btn btn-outline-secondary text-start d-flex justify-content-between align-items-center py-2">
                            <span><i class="bi bi-check-circle me-2"></i>Test MySQLi Connection</span>
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                        <a href="test_db_pdo.php" target="_blank" class="btn btn-outline-secondary text-start d-flex justify-content-between align-items-center py-2">
                            <span><i class="bi bi-check2-all me-2"></i>Test PDO Connection</span>
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                        <a href="phpinfo.php" target="_blank" class="btn btn-outline-secondary text-start d-flex justify-content-between align-items-center py-2">
                            <span><i class="bi bi-info-circle me-2"></i>PHP Info (<?= $php_version ?>)</span>
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Database Credential Info -->
                <div class="card sidebar-card p-3 mb-3">
                    <h6 class="fw-bold text-uppercase text-secondary mb-2" style="font-size: 0.8rem; letter-spacing: 0.05rem;">
                        <i class="bi bi-key-fill me-1 text-info"></i>Koneksi Database Lokal
                    </h6>
                    <ul class="list-unstyled small mb-0 font-monospace">
                        <li class="mb-1"><span class="text-secondary">Host:</span> 127.0.0.1 (atau <code>database</code>)</li>
                        <li class="mb-1"><span class="text-secondary">Port:</span> 3306</li>
                        <li class="mb-1"><span class="text-secondary">User:</span> root / docker</li>
                        <li><span class="text-secondary">Pass:</span> tiger / docker</li>
                    </ul>
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
                        </div>
                        <div class="col-6">
                            <label class="form-label text-secondary small fw-bold">MODE INTEGRASI WSL</label>
                            <select class="form-select form-select-sm bg-dark text-light border-secondary" id="prefWslMode">
                                <option value="remote">Remote WSL (Rekomendasi)</option>
                                <option value="unc">Network Share (UNC)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label text-secondary small fw-bold">PROTOKOL ANTIGRAVITY</label>
                        <select class="form-select form-select-sm bg-dark text-light border-secondary" id="prefAntigravityProtocol">
                            <option value="antigravity-ide">antigravity-ide:// (Antigravity IDE Code Editor - Default)</option>
                            <option value="antigravity">antigravity:// (Antigravity 2.0 Desktop Chat Canvas)</option>
                        </select>
                        <div class="form-text text-secondary small">
                            Gunakan <code>antigravity-ide://</code> agar membuka editor koding, bukan aplikasi chat bawaan.
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

    <!-- Modal Clone Repo -->
    <div class="modal fade" id="cloneModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-secondary text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="bi bi-cloud-arrow-down-fill me-2 text-info"></i>Clone Project: <span id="cloneModalRepoName" class="text-warning font-monospace"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="cloneRepoName">
                    <input type="hidden" id="cloneRepoUrl">
                    <p class="text-secondary small mb-3">
                        Project akan di-clone langsung ke folder <code>www/<span id="cloneTargetFolder"></span></code> dan dikonfigurasi otomatis.
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
                    <div id="cloneAlert" class="alert d-none small mb-0 py-2"></div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="btnCancelClone">Batal</button>
                    <button type="button" class="btn btn-success" id="btnConfirmClone" onclick="executeClone()">
                        <i class="bi bi-cloud-download me-1"></i> Mulai Clone
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
        
        // Enhanced Search Filter (Local Projects & GitHub Repos)
        document.getElementById('projectSearch').addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase().trim();
            // Filter Local Projects
            document.querySelectorAll('.project-item').forEach(item => {
                const name = item.getAttribute('data-name') || '';
                item.style.display = name.includes(query) ? '' : 'none';
            });
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

        // Modal Setting Logic
        let settingModalInstance = null;
        function openSettingModal(project, php, type, entry, ide) {
            document.getElementById('modalProjectName').textContent = project;
            document.getElementById('inputProject').value = project;
            document.getElementById('selectPhp').value = php || '7.4';
            document.getElementById('selectType').value = type || 'auto';
            document.getElementById('selectEntry').value = entry || 'auto';
            document.getElementById('selectIde').value = ide || 'auto';

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
            if (inputHostPath) inputHostPath.value = hostPath;

            const wslDistro = localStorage.getItem('egov_wsl_distro') || 'Ubuntu';
            const inputDistro = document.getElementById('prefWslDistro');
            if (inputDistro) inputDistro.value = wslDistro;

            const wslMode = localStorage.getItem('egov_wsl_mode') || 'remote';
            const selectMode = document.getElementById('prefWslMode');
            if (selectMode) selectMode.value = wslMode;

            const agyProto = localStorage.getItem('egov_antigravity_protocol') || 'antigravity-ide';
            const selectProto = document.getElementById('prefAntigravityProtocol');
            if (selectProto) selectProto.value = agyProto;
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

        function saveAllPreferences() {
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

            const selectProto = document.getElementById('prefAntigravityProtocol');
            if (selectProto) {
                localStorage.setItem('egov_antigravity_protocol', selectProto.value);
            }

            if (preferencesModalInstance) {
                preferencesModalInstance.hide();
            }
            showToast('<i class="bi bi-check-circle-fill text-success me-2"></i>Pengaturan preferensi dashboard berhasil disimpan!');
        }

        // Build Universal Editor URI (Smart Windows WSL / Linux / Mac Translation)
        function buildEditorUri(scheme, projectName) {
            let hostPath = (localStorage.getItem('egov_host_path') || localStorage.getItem('gov_host_path') || '').trim();
            const distro = (localStorage.getItem('egov_wsl_distro') || 'Ubuntu').trim();
            const isWindows = navigator.userAgent.includes('Windows');

            if (!hostPath) {
                const msg = isWindows 
                    ? "Masukkan path folder 'www' di WSL Anda:\n(Contoh: /home/hendra/workspace/egov/www)\n\nJika menggunakan Windows biasa, masukkan drive path (Contoh: D:/egov/www)"
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

            // KASUS 1: Path Windows UNC (//wsl.localhost/ atau //wsl$/)
            if (hostPath.startsWith('//wsl.localhost/') || hostPath.startsWith('//wsl$/')) {
                return `${scheme}://file${hostPath}/${projectName}`;
            }

            // KASUS 2: Path Linux WSL (diawali /home, /var, dll) diakses dari browser Windows
            if (isWindows && hostPath.startsWith('/')) {
                const wslMode = localStorage.getItem('egov_wsl_mode') || 'remote';
                if (wslMode === 'unc') {
                    // Windows UNC Network Path (\\wsl.localhost\Ubuntu\...)
                    return `${scheme}://file//wsl.localhost/${distro}${hostPath}/${projectName}`;
                }
                // Default: Format Resmi Remote WSL (vscode-remote://...)
                return `${scheme}://vscode-remote/wsl+${distro}${hostPath}/${projectName}`;
            }

            // KASUS 3: Path Drive Windows (C:/... atau D:/...)
            if (/^[a-zA-Z]:/.test(hostPath)) {
                return `${scheme}://file/${hostPath}/${projectName}`;
            }

            // KASUS 4: Path Linux di Linux Native
            return `${scheme}://file${hostPath}/${projectName}`;
        }

        // Open in VS Code
        function openInVSCode(projectName) {
            const uri = buildEditorUri('vscode', projectName);
            if (!uri) return;

            window.location.href = uri;
            const cliCmd = `code www/${projectName}`;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(cliCmd).catch(() => {});
            }
            showToast(`<i class="bi bi-code-slash text-info me-2"></i>Membuka <strong>${projectName}</strong> di VS Code...<br><span class="text-secondary small font-monospace">CLI: ${cliCmd} (disalin)</span>`);
        }

        // Open in Antigravity IDE
        function openInAntigravity(projectName) {
            const scheme = localStorage.getItem('egov_antigravity_protocol') || 'antigravity-ide';
            const uri = buildEditorUri(scheme, projectName);
            if (!uri) return;

            window.location.href = uri;
            const cliCmd = `antigravity-ide www/${projectName}`;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(cliCmd).catch(() => {});
            }
            showToast(`<i class="bi bi-rocket-takeoff text-purple me-2"></i>Membuka Antigravity IDE untuk <strong>${projectName}</strong>...<br><span class="text-secondary small font-monospace">CLI: ${cliCmd} (disalin)</span>`);
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
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="card-title fw-bold mb-0 text-truncate font-monospace" style="max-width: 60%;" title="${escapeHtml(repo.name)}">
                                    <i class="bi bi-github me-1 text-secondary"></i>${escapeHtml(repo.name)}
                                </h6>
                                <div class="d-flex gap-1 align-items-center flex-wrap justify-content-end">
                                    <span class="badge ${repo.is_private ? 'badge-private' : 'badge-public'}" style="font-size: 0.68rem;">
                                        ${repo.is_private ? '<i class="bi bi-lock-fill me-1"></i>Private' : '<i class="bi bi-globe me-1"></i>Public'}
                                    </span>
                                    ${repo.language ? `<span class="badge bg-secondary text-light font-monospace" style="font-size: 0.68rem;">${escapeHtml(repo.language)}</span>` : ''}
                                </div>
                            </div>
                            <p class="text-secondary small mb-3 flex-grow-1" style="font-size: 0.8rem; min-height: 2.4rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" title="${escapeHtml(repo.description)}">
                                ${escapeHtml(repo.description)}
                            </p>
                            <div class="mt-auto d-flex justify-content-between align-items-center pt-2 border-top border-secondary border-opacity-25">
                                <div class="d-flex gap-1">
                                    <a href="${escapeHtml(repo.html_url)}" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;" title="Lihat di GitHub">
                                        <i class="bi bi-box-arrow-up-right me-1"></i>GitHub
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;" title="Salin Perintah CLI" onclick="copyCliClone('${escapeHtml(repo.name)}')">
                                        <i class="bi bi-terminal me-1"></i>CLI
                                    </button>
                                </div>
                                <button type="button" class="btn btn-sm btn-success py-1 px-3" onclick="openCloneModal('${escapeHtml(repo.name)}', '${escapeHtml(repo.clone_url)}')">
                                    <i class="bi bi-cloud-arrow-down-fill me-1"></i>Clone
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Clone Modal & Execution
        function openCloneModal(repoName, cloneUrl) {
            document.getElementById('cloneModalRepoName').textContent = repoName;
            document.getElementById('cloneTargetFolder').textContent = repoName;
            document.getElementById('cloneRepoName').value = repoName;
            document.getElementById('cloneRepoUrl').value = cloneUrl;
            document.getElementById('cloneAlert').classList.add('d-none');

            const btnConfirm = document.getElementById('btnConfirmClone');
            btnConfirm.disabled = false;
            btnConfirm.innerHTML = '<i class="bi bi-cloud-download me-1"></i> Mulai Clone';

            if (!cloneModalInstance) {
                cloneModalInstance = new bootstrap.Modal(document.getElementById('cloneModal'));
            }
            cloneModalInstance.show();
        }

        function executeClone() {
            const repo = document.getElementById('cloneRepoName').value;
            const php = document.getElementById('cloneSelectPhp').value;
            const alertEl = document.getElementById('cloneAlert');
            const btnConfirm = document.getElementById('btnConfirmClone');
            const btnCancel = document.getElementById('btnCancelClone');

            btnConfirm.disabled = true;
            btnCancel.disabled = true;
            btnConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Meng-clone...';
            alertEl.className = 'alert alert-info small py-2 mb-0';
            alertEl.innerHTML = `<i class="bi bi-hourglass-split me-1"></i> Sedang meng-clone repository <strong>${repo}</strong> dan menyiapkan konfigurasi environment...`;
            alertEl.classList.remove('d-none');

            const formData = new FormData();
            formData.append('action', 'clone_repo');
            formData.append('repo', repo);
            formData.append('php', php);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alertEl.className = 'alert alert-success small py-2 mb-0';
                    alertEl.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> ${data.message}`;
                    setTimeout(() => {
                        cloneModalInstance.hide();
                        location.reload();
                    }, 1200);
                } else {
                    alertEl.className = 'alert alert-danger small py-2 mb-0';
                    alertEl.innerHTML = `<i class="bi bi-x-circle-fill me-1"></i> ${data.message || 'Gagal melakukan clone.'}`;
                    btnConfirm.disabled = false;
                    btnCancel.disabled = false;
                    btnConfirm.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Coba Lagi';
                }
            })
            .catch(err => {
                alertEl.className = 'alert alert-danger small py-2 mb-0';
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