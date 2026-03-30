<?php
require_once '../config.php';
require_once __DIR__ . '/includes/staff_layout.php';
require_once __DIR__ . '/../includes/reviews_init.php';

staffRequireAuth();

$reviews = [];
$flashMessage = '';

$reviewsEnsure = reviewsEnsureTable($pdo);
$reviewsTableExists = (bool)($reviewsEnsure['table_exists'] ?? false);
$hiddenColumnName = (string)($reviewsEnsure['hidden_column'] ?? '');

$hasHiddenColumn = $hiddenColumnName !== '';
// --- 搜尋/篩選參數 ---
$q = trim((string)($_GET['q'] ?? ''));
$ratingFilter = trim((string)($_GET['rating'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? 'all')); // all/normal/hidden

$allowedRatings = ['', '1', '2', '3', '4', '5'];
if (!in_array($ratingFilter, $allowedRatings, true)) {
    $ratingFilter = '';
}
$allowedStatus = ['all', 'normal', 'hidden'];
if (!in_array($statusFilter, $allowedStatus, true)) {
    $statusFilter = 'all';
}

// --- POST 操作：隱藏/取消隱藏/刪除 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reviewsTableExists) {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'delete_review') {
        $reviewId = (int)($_POST['review_id'] ?? 0);
        if ($reviewId > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $reviewId]);
                $flashMessage = '評論已刪除。';
            } catch (Throwable $e) {
                $flashMessage = '刪除失敗，請稍後再試。';
            }
        }
    } elseif ($action === 'set_review_hidden' && $hasHiddenColumn) {
        $reviewId = (int)($_POST['review_id'] ?? 0);
        $newHidden = (int)($_POST['new_hidden'] ?? 0);
        if ($reviewId > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE reviews
                                           SET {$hiddenColumnName} = :hidden,
                                               updated_at = NOW()
                                         WHERE id = :id");
                $stmt->execute([':hidden' => ($newHidden === 1 ? 1 : 0), ':id' => $reviewId]);
                $flashMessage = $newHidden === 1 ? '評論已隱藏。' : '評論已取消隱藏。';
            } catch (Throwable $e) {
                $flashMessage = '操作失敗，請稍後再試。';
            }
        }
    }
}

// --- 列表查詢 ---
if ($reviewsTableExists) {
    try {
        $where = [];
        $params = [];

        if ($q !== '') {
            // 搜尋商品、會員、評論內容
            $where[] = "(p.name LIKE :q OR u.name LIKE :q OR r.comment LIKE :q)";
            $params[':q'] = '%' . $q . '%';
        }
        if ($ratingFilter !== '') {
            $where[] = "r.rating = :rating";
            $params[':rating'] = (int)$ratingFilter;
        }

        if ($statusFilter !== 'all' && $hasHiddenColumn) {
            if ($statusFilter === 'normal') {
                $where[] = "r.{$hiddenColumnName} = 0";
            } elseif ($statusFilter === 'hidden') {
                $where[] = "r.{$hiddenColumnName} = 1";
            }
        } elseif ($statusFilter !== 'all' && !$hasHiddenColumn) {
            // 沒有隱藏欄位時：hidden/normal 無法篩選
            $where[] = "1=0";
        }

        $whereSql = !empty($where) ? (' WHERE ' . implode(' AND ', $where)) : '';
        $hiddenSelect = $hasHiddenColumn ? ", r.{$hiddenColumnName} AS is_hidden_value" : "";

        $sql = "SELECT r.id, r.rating, r.comment, r.created_at,
                       p.name AS product_name,
                       u.name AS user_name
                       {$hiddenSelect}
                FROM reviews r
                LEFT JOIN products p ON p.id = r.product_id
                LEFT JOIN users u ON u.id = r.user_id
                {$whereSql}
                ORDER BY r.created_at DESC
                LIMIT 120";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $reviews = [];
    }
}

staffPageStart($pdo, '評價管理', 'reviews');
?>
<section class="staff-panel">
    <?php if ($flashMessage !== ''): ?>
        <div class="staff-notice"><?php echo htmlspecialchars($flashMessage); ?></div>
    <?php endif; ?>
    <?php if (!$reviewsTableExists): ?>
        <div class="staff-empty-hint">
            目前資料庫尚未建立 <code>reviews</code> 資料表，因此暫時無法顯示評論清單。完成資料表後此頁會自動接上真實評論資料。
        </div>
    <?php else: ?>
        <div class="staff-notice">
            以商品、會員與評論內容進行搜尋與管理；可隱藏、取消隱藏或刪除不當評論。
        </div>
        <form method="GET" action="reviews.php" class="staff-toolbar">
            <input
                type="text"
                name="q"
                class="staff-input"
                placeholder="搜尋商品 / 會員 / 評論內容"
                value="<?php echo htmlspecialchars($q); ?>"
            >

            <select name="rating" class="staff-select">
                <option value="">全部星等</option>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo $ratingFilter === (string)$i ? 'selected' : ''; ?>>
                        <?php echo $i; ?> 星
                    </option>
                <?php endfor; ?>
            </select>

            <select name="status" class="staff-select">
                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>全部狀態</option>
                <option value="normal" <?php echo $statusFilter === 'normal' ? 'selected' : ''; ?>>正常</option>
                <option value="hidden" <?php echo $statusFilter === 'hidden' ? 'selected' : ''; ?>>已隱藏</option>
            </select>

            <button type="submit" class="staff-btn">查詢</button>
        </form>

        <div class="staff-table-wrap">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>評價編號</th>
                        <th>商品</th>
                        <th>會員</th>
                        <th>評分</th>
                        <th>評論內容</th>
                        <th>日期</th>
                        <th>狀態</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reviews)): ?>
                        <tr>
                            <td colspan="8">目前沒有評論資料。</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reviews as $row): ?>
                            <?php $isHidden = (int)($row['is_hidden_value'] ?? 0) === 1; ?>
                            <tr>
                                <td>#<?php echo (int)($row['id'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars((string)($row['product_name'] ?? '未知商品')); ?></td>
                                <td><?php echo htmlspecialchars((string)($row['user_name'] ?? '未知會員')); ?></td>
                                <td><?php echo number_format((int)($row['rating'] ?? 0)); ?>/5</td>
                                <td><?php echo htmlspecialchars((string)($row['comment'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d', strtotime((string)$row['created_at']))); ?></td>
                                <td>
                                    <span class="staff-badge <?php echo $isHidden ? 'danger' : 'done'; ?>">
                                        <?php echo $isHidden ? '已隱藏' : '正常'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="staff-return-actions">
                                        <?php if ($hasHiddenColumn): ?>
                                            <form method="POST" class="staff-inline-form">
                                                <input type="hidden" name="action" value="set_review_hidden">
                                                <input type="hidden" name="review_id" value="<?php echo (int)($row['id'] ?? 0); ?>">
                                                <input type="hidden" name="new_hidden" value="<?php echo $isHidden ? 0 : 1; ?>">
                                                <button
                                                    type="submit"
                                                    class="staff-action-btn <?php echo $isHidden ? 'staff-action-btn-muted' : 'staff-action-btn-danger'; ?>"
                                                >
                                                    <?php echo $isHidden ? '取消隱藏' : '隱藏評論'; ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="POST" class="staff-inline-form" onsubmit="return confirm('確定刪除此評論？');">
                                            <input type="hidden" name="action" value="delete_review">
                                            <input type="hidden" name="review_id" value="<?php echo (int)($row['id'] ?? 0); ?>">
                                            <button type="submit" class="staff-action-btn staff-action-btn-danger">刪除</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php staffPageEnd(); ?>

