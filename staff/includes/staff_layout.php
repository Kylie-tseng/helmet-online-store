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
        $role = (string)($_SESSION['role'] ?? 'staff');

        // admin：進階版管理導覽列（與 staff 版型共用）
        if ($role === 'admin') {
            return [
                'dashboard' => ['label' => '工作入口', 'href' => 'dashboard.php'],
                'orders' => ['label' => '訂單與營運', 'href' => 'orders.php'],
                'products' => ['label' => '商品與分類', 'href' => 'products.php'],
                'sales' => ['label' => '銷售統計', 'href' => 'sales.php'],
                'returns' => ['label' => '退貨申請', 'href' => 'returns.php'],
                'reviews' => ['label' => '評價管理', 'href' => 'reviews.php'],
                'coupons' => ['label' => '優惠活動', 'href' => 'coupons.php'],
                'members' => ['label' => '會員管理', 'href' => 'members.php'],
                'staff_accounts' => ['label' => '員工權限', 'href' => 'staff_accounts.php'],
                'settings' => ['label' => '系統設定', 'href' => 'settings.php'],
            ];
        }

        // staff：維持既有店員導覽列
        return [
            'dashboard' => ['label' => '工作入口', 'href' => 'dashboard.php'],
            'orders' => ['label' => '訂單處理', 'href' => 'orders.php'],
            'products' => ['label' => '商品管理', 'href' => 'products.php'],
            'sales_report' => ['label' => '銷售統計', 'href' => 'sales_report.php'],
            'returns' => ['label' => '退貨申請', 'href' => 'returns.php'],
            'reviews' => ['label' => '評價管理', 'href' => 'reviews.php'],
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
        $role = (string)($_SESSION['role'] ?? 'staff');
        $roleText = $role === 'admin' ? '管理者' : '店員';
        $subtitleText = $role === 'admin' ? 'HelmetVRse 管理者模式' : 'HelmetVRse 店員模式';
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
                        <span class="staff-navbar-user"><?php echo htmlspecialchars($roleText); ?></span>
                        <a href="../logout.php" class="staff-navbar-logout">登出</a>
                    </div>
                </div>
            </nav>
            <div class="staff-workspace container">
                <header class="staff-page-header">
                    <div>
                        <h1 class="staff-page-title"><?php echo htmlspecialchars($title); ?></h1>
                        <p class="staff-page-subtitle"><?php echo htmlspecialchars($subtitleText); ?></p>
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

