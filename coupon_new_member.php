<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';

$coupon_code = 'NEW100';
$coupon_message = '';
$coupon_message_type = 'success';
$is_logged_in = isset($_SESSION['user_id']);
$user_profile = null;

if ($is_logged_in) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, username, email FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => (int)$_SESSION['user_id']]);
        $user_profile = $stmt->fetch();
    } catch (PDOException $e) {
        $user_profile = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'claim_coupon') {
    if (!$is_logged_in) {
        $coupon_message = '請先登入會員再領取優惠券';
        $coupon_message_type = 'error';
    } else {
        $claim_result = claimUserCoupon($pdo, (int)$_SESSION['user_id'], $coupon_code);
        $coupon_message = $claim_result['message'];
        $coupon_message_type = $claim_result['success'] ? 'success' : 'warning';
    }
}

$is_claimed = $is_logged_in ? hasUserCoupon($pdo, (int)$_SESSION['user_id'], $coupon_code) : false;

try {
    $stmt = $pdo->query("SELECT id, name, description FROM categories ORDER BY id");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

$parts_category_id = null;
try {
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = '周邊與配件' LIMIT 1");
    $stmt->execute();
    $parts_category = $stmt->fetch();
    if ($parts_category) {
        $parts_category_id = $parts_category['id'];
    }
} catch (PDOException $e) {
    // ignore
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新會員優惠 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            font-family: "Noto Sans TC", "Inter", sans-serif;
            background: #f3f4f6;
        }

        .new-member-wrap {
            padding: 34px 0 48px;
        }

        .nm-hero-card {
            max-width: 860px;
            margin: 0 auto 22px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            padding: 24px;
            text-align: center;
            border: 1px solid #e5e7eb;
        }

        .nm-title {
            margin: 0 0 10px;
            color: #333333;
            font-size: 34px;
            font-weight: 800;
        }

        .nm-subtitle {
            margin: 0 0 8px;
            font-size: 18px;
            color: #444444;
        }

        .nm-code {
            display: inline-block;
            margin: 0 0 16px;
            color: #333333;
            border: 1px solid #CFCFCF;
            border-radius: 999px;
            padding: 6px 14px;
            background: #F2F2F2;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .nm-main-btn {
            display: inline-block;
            border: 0;
            border-radius: 999px;
            background: #333333;
            color: #ffffff;
            text-decoration: none;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .nm-main-btn:hover {
            background: #555555;
        }

        .nm-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 18px;
        }

        .nm-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            padding: 24px;
            border: 1px solid #e5e7eb;
        }

        .nm-card h2 {
            margin: 0 0 12px;
            font-size: 22px;
            color: #333333;
            font-weight: 700;
        }

        .nm-list {
            margin: 0;
            padding-left: 18px;
            color: #4b5563;
            line-height: 1.9;
        }

        .nm-status-card {
            background: #F2F2F2;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
            padding: 24px;
            border: 1px solid #D9D9D9;
        }

        .nm-status-title {
            margin: 0 0 12px;
            font-size: 22px;
            color: #333333;
            font-weight: 700;
        }

        .nm-row {
            margin: 6px 0;
            color: #444444;
        }

        .nm-actions {
            margin-top: 14px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        @media (max-width: 768px) {
            .nm-title {
                font-size: 28px;
            }

            .nm-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <section class="new-member-wrap">
        <div class="container">
            <section class="nm-hero-card">
                <h1 class="nm-title">新會員優惠</h1>
                <p class="nm-subtitle">滿 NT$500 折 NT$100</p>
                <div class="nm-code">優惠碼 NEW100</div>
                <div>
                    <a href="products.php" class="nm-main-btn">前往購物</a>
                </div>
            </section>

            <?php if ($coupon_message !== ''): ?>
                <div class="cart-message <?php echo htmlspecialchars($coupon_message_type); ?>">
                    <?php echo htmlspecialchars($coupon_message); ?>
                </div>
            <?php endif; ?>

            <div class="nm-grid">
                <article class="nm-card">
                    <h2>優惠內容</h2>
                    <ul class="nm-list">
                        <li>新會員註冊後可領取 NEW100 優惠券</li>
                        <li>單筆消費滿 NT$500 可折抵 NT$100</li>
                        <li>有效期限：2025-01-01 ~ 2099-12-31</li>
                    </ul>
                </article>
                <article class="nm-card">
                    <h2>使用條件</h2>
                    <ul class="nm-list">
                        <li>僅限會員帳號領取與使用</li>
                        <li>每個會員帳號限領一次</li>
                        <li>套用時需符合最低消費門檻</li>
                    </ul>
                </article>
            </div>

            <section class="nm-status-card">
                <h2 class="nm-status-title">優惠券狀態</h2>
                <?php if ($is_logged_in): ?>
                    <p class="nm-row">會員名稱：<?php echo htmlspecialchars($user_profile['name'] ?? $_SESSION['user_name'] ?? '會員'); ?></p>
                    <p class="nm-row">帳號：<?php echo htmlspecialchars($user_profile['username'] ?? ''); ?></p>
                    <p class="nm-row">Email：<?php echo htmlspecialchars($user_profile['email'] ?? ''); ?></p>
                    <?php if ($is_claimed): ?>
                        <p class="cart-message success" style="margin-top: 10px;">已領取優惠券</p>
                        <p class="nm-row">優惠碼：NEW100</p>
                        <div class="nm-actions">
                            <a href="products.php" class="btn">前往購物</a>
                        </div>
                    <?php else: ?>
                        <p class="nm-row">領取狀態：尚未領取</p>
                        <div class="nm-actions">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="claim_coupon">
                                <button type="submit" class="btn">立即領取優惠券</button>
                            </form>
                            <a href="products.php" class="btn">前往購物</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="nm-row">您尚未登入。登入後可領取 NEW100 優惠券。</p>
                    <div class="nm-actions">
                        <a href="login.php?redirect=<?php echo urlencode('coupon_new_member.php'); ?>" class="btn">前往登入</a>
                        <a href="register.php" class="btn">前往註冊</a>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </section>
</body>
</html>
