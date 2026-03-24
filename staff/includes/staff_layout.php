<?php
require_once __DIR__ . '/../../includes/order_status_helpers.php';
if (!function_exists('staffRequireAuth')) {
    function staffRequireAuth(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ../login.php');
            exit;
        }

        $role = (string)($_SESSION['role'] ?? '');
        if ($role !== 'staff' && $role !== 'admin') {
            header('Location: ../index.php');
            exit;
        }
    }
}

if (!function_exists('staffNavItems')) {
    function staffNavItems(): array
    {
        return [
            'dashboard' => ['label' => '工作入口', 'href' => 'dashboard.php'],
            'orders' => ['label' => '訂單處理', 'href' => 'orders.php'],
            'products' => ['label' => '商品管理', 'href' => 'products.php'],
            'sales_report' => ['label' => '銷售統計', 'href' => 'sales_report.php'],
            'returns' => ['label' => '退貨申請', 'href' => 'returns.php'],
        ];
    }
}

if (!function_exists('staffPageStart')) {
    function staffPageStart(PDO $pdo, string $title, string $activeKey): void
    {
        unset($pdo);
        $cssVersion = @filemtime(__DIR__ . '/../../assets/css/style.css');
        $cssVersion = $cssVersion ? (string)$cssVersion : '1';
        $items = staffNavItems();
        ?>
        <!DOCTYPE html>
        <html lang="zh-TW">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo htmlspecialchars($title); ?> - HelmetVRse</title>
            <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo urlencode($cssVersion); ?>">
        </head>
        <body class="staff-page">
            <nav class="navbar unified-navbar staff-simple-navbar">
                <div class="nav-container">
                    <div class="nav-logo home-navbar-left">
                        <a href="dashboard.php">HelmetVRse</a>
                    </div>
                    <div class="nav-right home-navbar-right">
                        <span class="staff-navbar-user">店員</span>
                        <a href="../logout.php" class="staff-navbar-logout">登出</a>
                    </div>
                </div>
            </nav>
            <div class="staff-workspace container">
                <header class="staff-page-header">
                    <div>
                        <h1 class="staff-page-title"><?php echo htmlspecialchars($title); ?></h1>
                        <p class="staff-page-subtitle">HelmetVRse 店員模式</p>
                    </div>
                </header>
                <nav class="staff-subnav" aria-label="店員功能導覽">
                    <?php foreach ($items as $key => $item): ?>
                        <a href="<?php echo htmlspecialchars($item['href']); ?>" class="staff-subnav-link <?php echo $activeKey === $key ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($item['label']); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <main class="staff-main">
        <?php
    }
}

if (!function_exists('staffPageEnd')) {
    function staffPageEnd(): void
    {
        ?>
                </main>
            </div>
        </body>
        </html>
        <?php
    }
}

if (!function_exists('staffStatusBadgeClass')) {
    function staffStatusBadgeClass(string $status): string
    {
        return appStatusBadgeClass($status);
    }
}

if (!function_exists('staffStatusLabel')) {
    function staffStatusLabel(string $status): string
    {
        return appOrderStatusLabel($status);
    }
}

if (!function_exists('staffCurrency')) {
    function staffCurrency(float $amount): string
    {
        return 'NT$ ' . number_format($amount, 0);
    }
}

