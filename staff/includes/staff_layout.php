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
            // 順序對應文件：帳號管理 → 商品與訂單監控 → 營運與銷售分析 → 促銷管理 → 其他設定（不含退貨，退貨由店員處理）
            return [
                'dashboard' => ['label' => '工作入口', 'href' => 'dashboard.php'],
                'members' => ['label' => '會員管理', 'href' => 'members.php'],
                'staff_accounts' => ['label' => '員工權限', 'href' => 'staff_accounts.php'],
                'products' => ['label' => '商品與分類', 'href' => 'products.php'],
                'orders' => ['label' => '訂單與營運', 'href' => 'orders.php'],
                'sales' => ['label' => '銷售統計', 'href' => 'sales.php'],
                'coupons' => ['label' => '優惠活動', 'href' => 'coupons.php'],
                'settings' => ['label' => '其他設定', 'href' => 'settings.php'],
            ];
        }

        // staff：店員導覽列（順序與文案依營運流程）
        return [
            'dashboard' => ['label' => '工作入口', 'href' => 'dashboard.php'],
            'orders' => ['label' => '訂單管理', 'href' => 'orders.php'],
            'products' => ['label' => '商品管理', 'href' => 'products.php'],
            'low_stock' => ['label' => '低庫存', 'href' => 'product_catalog.php?filter=low_stock'],
            'returns' => ['label' => '退貨申請', 'href' => 'returns.php'],
            'sales_report' => ['label' => '銷售與營運', 'href' => 'sales_report.php'],
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
        $staffFlashError = '';
        if (!empty($_SESSION['staff_page_flash_error'])) {
            $staffFlashError = trim((string)$_SESSION['staff_page_flash_error']);
            unset($_SESSION['staff_page_flash_error']);
        }
        if ($staffFlashError !== '') {
            echo '<div class="staff-notice staff-notice--error staff-flash-banner" role="alert">' . htmlspecialchars($staffFlashError, ENT_QUOTES, 'UTF-8') . '</div>';
        }
    }
}

if (!function_exists('staffPageEnd')) {
    function staffPageEnd(): void
    {
        $staffToastSuccess = '';
        if (!empty($_SESSION['staff_toast_success'])) {
            $staffToastSuccess = trim((string)$_SESSION['staff_toast_success']);
            unset($_SESSION['staff_toast_success']);
        }
        ?>
                </main>
            </div>
            <div id="staff-success-toast" class="staff-success-toast" role="status" aria-live="polite" hidden>
                <span class="staff-success-toast__text"></span>
            </div>
            <script>
            (function () {
                var msg = <?php echo json_encode($staffToastSuccess, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
                if (!msg) return;
                var root = document.getElementById('staff-success-toast');
                if (!root) return;
                var textEl = root.querySelector('.staff-success-toast__text');
                if (textEl) textEl.textContent = msg;
                root.removeAttribute('hidden');
                requestAnimationFrame(function () {
                    root.classList.add('is-visible');
                });
                setTimeout(function () {
                    root.classList.remove('is-visible');
                    root.classList.add('is-hiding');
                    setTimeout(function () {
                        root.classList.remove('is-hiding');
                        root.setAttribute('hidden', 'hidden');
                    }, 280);
                }, 3200);
            })();
            </script>
            <?php
            require_once __DIR__ . '/../../includes/app_modal.php';
            app_modal_render();
            ?>
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

/**
 * 店員後台：成功訊息用浮動 toast（由 staffPageEnd 輸出後自 session 清除）。
 */
if (!function_exists('staffSetToastSuccess')) {
    function staffSetToastSuccess(string $message): void
    {
        $message = trim($message);
        if ($message === '') {
            return;
        }
        $_SESSION['staff_toast_success'] = $message;
    }
}

/**
 * POST→redirect 流程：依文案分流成功（toast）與失敗／警示（頂部橫幅）。
 */
if (!function_exists('staffNotifyFlashForRedirect')) {
    function staffNotifyFlashForRedirect(string $message): void
    {
        $message = trim($message);
        if ($message === '') {
            return;
        }
        if (staffFlashMessageShouldToastSuccess($message)) {
            $_SESSION['staff_toast_success'] = $message;
            return;
        }
        $_SESSION['staff_page_flash_error'] = $message;
    }
}

if (!function_exists('staffFlashMessageShouldToastSuccess')) {
    function staffFlashMessageShouldToastSuccess(string $message): bool
    {
        if (preg_match('/失敗|無法|找不到|請稍後再試|請稍後|請選擇|請填寫|僅支援|資料不正確|資料表缺少|沒有可用|不支援的操作|不可變|不可設|儲存失敗|更新失敗|刪除失敗|新增失敗|建立失敗|寫入失敗|上傳失敗|移除失敗|缺少/u', $message)) {
            return false;
        }
        if (preg_match('/成功|已儲存|已更新|已刪除|已新增|已建立|已上傳|已移除|資料已|狀態已|分類已|商品已|圖片已|店員備註|配送|物流資訊已|備註儲存|狀態更新成功/u', $message)) {
            return true;
        }
        return false;
    }
}

