<?php
// Workspace GOV - Modern Dashboard Landing Page with Live Container Status & Project Settings

// Handle API Save Setting (.ws)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_setting') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    $project = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['project'] ?? '');
    $php = $_POST['php'] ?? '7.4';
    $type = $_POST['type'] ?? 'auto';
    $entry = $_POST['entry'] ?? 'auto';

    if ($project && is_dir("./$project")) {
        $content = "php={$php}\ntype={$type}\nentry={$entry}\n";
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
$excluded = ['.', '..', 'assets', '.DS_Store', 'vendor', 'test_db.php', 'test_db_pdo.php', 'phpinfo.php', 'index.php', 'favicon.png', 'composer.json', 'composer.lock'];
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

        if ($has_custom_ws) {
            $ws_data = parse_ini_file($ws_file);
            $custom_php = $ws_data['php'] ?? '7.4';
            $custom_type = $ws_data['type'] ?? 'auto';
            $custom_entry = $ws_data['entry'] ?? 'auto';
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
            'raw_entry' => $custom_entry
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
            
            <!-- Left Column: Project List -->
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-semibold mb-0"><i class="bi bi-folder2-open me-2 text-warning"></i>Daftar Project (<?= count($projects) ?>)</h5>
                    <div class="w-50">
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-secondary border-opacity-25 text-secondary"><i class="bi bi-search"></i></span>
                            <input type="text" id="projectSearch" class="form-control search-box" placeholder="Cari nama project...">
                        </div>
                    </div>
                </div>

                <div class="row g-3" id="projectGrid">
                    <?php foreach ($projects as $p): ?>
                        <div class="col-md-6 project-item" data-name="<?= strtolower($p['name']) ?>" data-php-port="<?= $p['port'] ?>" data-php-ver="<?= $p['php_version'] ?>">
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
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;" 
                                                onclick="openSettingModal('<?= $p['name'] ?>', '<?= $p['php_version'] ?>', '<?= $p['raw_type'] ?>', '<?= $p['raw_entry'] ?>')">
                                            <i class="bi bi-gear me-1"></i>Setting
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-info py-0 px-2" style="font-size: 0.75rem;" title="Buka di VS Code" 
                                                onclick="openInVSCode('<?= htmlspecialchars($p['name']) ?>')">
                                            <i class="bi bi-code-slash me-1"></i>VS Code
                                        </button>
                                    </div>
                                    <a href="<?= htmlspecialchars($p['link']) ?>" target="_blank" class="btn btn-sm btn-outline-primary py-1 px-3"
                                       onclick="return checkContainerBeforeOpen(event, '<?= $p['port'] ?>', '<?= $p['php_version'] ?>')">
                                        Buka <i class="bi bi-box-arrow-up-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
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
                    </form>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="btnSaveSetting" onclick="saveProjectSetting()">Simpan Pengaturan</button>
                </div>
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
        
        // Search filter
        document.getElementById('projectSearch').addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase();
            document.querySelectorAll('.project-item').forEach(item => {
                const name = item.getAttribute('data-name');
                if (name.includes(query)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // Modal Setting Logic
        let settingModalInstance = null;
        function openSettingModal(project, php, type, entry) {
            document.getElementById('modalProjectName').textContent = project;
            document.getElementById('inputProject').value = project;
            document.getElementById('selectPhp').value = php || '7.4';
            document.getElementById('selectType').value = type || 'auto';
            document.getElementById('selectEntry').value = entry || 'auto';

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
        function openInVSCode(projectName) {
            let hostPath = localStorage.getItem('egov_host_path') || localStorage.getItem('gov_host_path');
            if (!hostPath) {
                hostPath = prompt("Untuk integrasi VS Code, masukkan path absolut folder 'www' di laptop Anda:\n(Contoh: /home/username/workspace/egov/www)", "");
                if (hostPath) {
                    hostPath = hostPath.trim().replace(/\/+$/, '');
                    localStorage.setItem('egov_host_path', hostPath);
                } else {
                    return;
                }
            }
            window.location.href = `vscode://file${hostPath}/${projectName}`;
        }
    </script>
</body>
</html>