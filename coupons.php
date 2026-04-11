<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';

$today = new DateTime('today');
$coupon_activities = [
    [
        'tag' => '新會員優惠',
        'title' => '新會員專屬優惠',
        'benefit' => '滿 NT$500 折 NT$100',
        'code' => 'NEW100',
        'validity' => $today->format('Y-m-d') . ' ～ ' . $today->modify('+6 months')->format('Y-m-d'),
        'detail_url' => 'coupon_new_member.php',
        'claim_url' => 'coupon_new_member.php#claim'
    ],
    [
        'tag' => '限時折扣',
        'title' => '安全帽週年慶',
        'benefit' => '全館商品 9 折',
        'code' => 'HELMET10',
        'validity' => '活動期間請見詳細頁說明',
        'detail_url' => 'coupon_anniversary.php',
        'claim_url' => 'coupon_anniversary.php#claim'
    ],
    [
        'tag' => '滿額折扣',
        'title' => '滿額折扣活動',
        'benefit' => '滿 NT$2000 折 NT$300',
        'code' => 'SAVE300',
        'validity' => '領取後 3 個月內有效',
        'detail_url' => 'coupon_discount.php',
        'claim_url' => 'coupon_discount.php#claim'
    ],
    [
        'tag' => '節慶活動',
        'title' => '騎士節活動',
        'benefit' => '全館商品 8 折',
        'code' => 'RIDER20',
        'validity' => '活動期間請見詳細頁說明',
        'detail_url' => 'coupon_rider_day.php',
        'claim_url' => 'coupon_rider_day.php#claim'
    ],
    [
        'tag' => '免運活動',
        'title' => '滿三千免運',
        'benefit' => '全站消費滿 NT$3000 即享免運',
        'code' => null,
        'validity' => '活動期間請見詳細頁說明',
        'detail_url' => 'coupon_free_shipping.php',
        'claim_url' => 'coupon_free_shipping.php#claim'
    ]
];

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
    <title>優惠券專區 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20260408-2">
    <style>
        .coupon-page-directory .coupon-item-btn-primary,
        .coupon-page-directory .coupon-item-btn-primary.js-claim-coupon {
            background: #475569;
            color: #fff;
            border: 1px solid #475569;
            border-radius: 12px;
            box-shadow: none;
        }

        .coupon-page-directory .coupon-item-btn-primary:hover,
        .coupon-page-directory .coupon-item-btn-primary.js-claim-coupon:hover {
            background: #334155;
            border-color: #334155;
            color: #fff;
        }

        .coupon-page-directory .coupon-item-btn-secondary {
            background: #fff;
            color: #475569;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            box-shadow: none;
        }

        .coupon-page-directory .coupon-item-btn-secondary:hover {
            background: #f8fafc;
            color: #475569;
            border-color: #cbd5e1;
        }

        .coupon-page-directory .coupon-item-btn.is-claimed,
        .coupon-page-directory .coupon-item-btn.is-claimed:hover {
            background: #9aa0a6;
            border-color: #9aa0a6;
            color: #fff;
            cursor: not-allowed;
            pointer-events: none;
            opacity: 0.95;
        }

        .coupon-page-directory .coupon-item-btn-primary {
            background-color: #2563eb !important;
            border-color: #2563eb !important;
        }

        .coupon-page-directory .coupon-item-btn-primary:hover {
            background-color: #1d4ed8 !important;
            border-color: #1d4ed8 !important;
        }
    </style>
</head>
<body class="coupon-page-directory">
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <main class="coupon-page-shell">
        <section class="coupon-page-hero" aria-label="優惠券專區標題">
            <div class="container">
                <div class="section-header section-header--hero-lines">
                    <div class="section-eyebrow">COUPONS</div>
                    <h1 class="section-title">優惠券專區</h1>
                    <p class="section-subtitle">一次掌握目前所有活動優惠與折扣資訊</p>
                </div>
            </div>
        </section>
        <div class="page-container coupon-page-container">
            <section class="coupon-list-panel" aria-label="優惠券總覽列表">
                <?php foreach ($coupon_activities as $activity): ?>
                    <article class="coupon-card">
                        <div class="coupon-card__inner">
                            <div class="coupon-card__content">
                                <div class="coupon-badge">
                                    <?php echo htmlspecialchars($activity['tag']); ?>
                                </div>

                                <h2 class="coupon-title">
                                    <?php echo htmlspecialchars($activity['title']); ?>
                                </h2>

                                <p class="coupon-subtitle">
                                    <?php echo htmlspecialchars($activity['benefit']); ?>
                                </p>

                                <div class="coupon-meta">
                                    <div class="coupon-meta-item">
                                        <div class="coupon-meta-label">優惠碼</div>
                                        <div class="coupon-meta-value">
                                            <?php echo htmlspecialchars($activity['code'] ?? '免輸入，系統自動套用'); ?>
                                        </div>
                                    </div>
                                    <div class="coupon-meta-item">
                                        <div class="coupon-meta-label">有效期限</div>
                                        <div class="coupon-meta-value">
                                            <?php echo htmlspecialchars($activity['validity']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="coupon-card__actions">
                                <?php if (($activity['title'] ?? '') === '滿三千免運'): ?>
                                    <a href="products.php"
                                       class="coupon-item-btn coupon-item-btn-primary"
                                       style="background:#4e6e8e !important; border:none !important; color:#ffffff !important;"
                                    >前往購物</a>
                                <?php else: ?>
                                    <button
                                        type="button"
                                        class="coupon-item-btn coupon-item-btn-primary js-claim-coupon"
                                        data-post-url="<?php echo htmlspecialchars($activity['detail_url']); ?>"
                                        style="background:#4e6e8e !important; border:none !important; color:#ffffff !important;"
                                    >立即領取優惠</button>
                                <?php endif; ?>
                                <a href="<?php echo htmlspecialchars($activity['detail_url']); ?>" class="coupon-item-btn coupon-item-btn-secondary">查看詳情</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3 class="footer-title">關於我們</h3>
                    <ul class="footer-links">
                        <li><a href="about.php">公司簡介</a></li>
                        <li><a href="about.php#history">發展歷程</a></li>
                        <li><a href="about.php#mission">經營理念</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3 class="footer-title">顧客服務</h3>
                    <ul class="footer-links">
                        <li><a href="guide.php">購物指南</a></li>
                        <li><a href="faq.php">常見問題 FAQ</a></li>
                        <li><a href="return.php">退貨政策</a></li>
                        <li><a href="shipping.php">運送說明</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3 class="footer-title">聯絡我們</h3>
                    <ul class="footer-links">
                        <li>電話：02-2905-2000</li>
                        <li>Email：helmetvrsefju@gmail.com</li>
                        <li>地址：新北市新莊區中正路510號</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>Powered by HelmetVRse</p>
            </div>
        </div>
    </footer>

    <?php require_once __DIR__ . '/includes/app_modal.php';
    app_modal_render(); ?>

    <script>
        (function () {
            function toFormUrlEncoded(obj) {
                return Object.keys(obj)
                    .map(key => encodeURIComponent(key) + '=' + encodeURIComponent(obj[key]))
                    .join('&');
            }

            function parseClaimResult(htmlText) {
                const doc = new DOMParser().parseFromString(htmlText, 'text/html');
                const messageEl = doc.querySelector('.cart-message');
                const message = messageEl ? (messageEl.textContent || '').trim() : '';
                const isSuccess = !!doc.querySelector('.cart-message.success');
                const isClaimedText = (doc.body && doc.body.textContent) ? doc.body.textContent.includes('已領取優惠券') : false;
                const looksClaimed = isSuccess || isClaimedText || message.includes('已領取');

                return { success: looksClaimed, message };
            }

            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.js-claim-coupon').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (btn.disabled || btn.classList.contains('is-claimed')) return;

                        const postUrl = btn.dataset.postUrl;
                        if (!postUrl) return;

                        const originalText = btn.textContent;
                        btn.disabled = true;
                        btn.textContent = '領取中...';

                        fetch(postUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: toFormUrlEncoded({ action: 'claim_coupon' })
                        })
                            .then(function (res) { return res.text(); })
                            .then(function (text) {
                                const result = parseClaimResult(text);
                                if (result.success) {
                                    btn.textContent = '已領取';
                                    btn.classList.add('is-claimed');
                                    btn.disabled = true;
                                    return;
                                }

                                btn.disabled = false;
                                btn.textContent = originalText || '立即領取優惠';
                                if (window.AppModal && AppModal.alert) {
                                    AppModal.alert({ title: '領取失敗', message: result.message || '領取失敗，請稍後再試' });
                                }
                            })
                            .catch(function () {
                                btn.disabled = false;
                                btn.textContent = originalText || '立即領取優惠';
                                if (window.AppModal && AppModal.alert) {
                                    AppModal.alert({ title: '系統錯誤', message: '系統錯誤，請稍後再試' });
                                }
                            });
                    });
                });
            });
        })();
    </script>
</body>
</html>
