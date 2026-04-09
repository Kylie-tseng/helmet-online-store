<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';
require_once 'includes/product_query_helpers.php';
require_once 'includes/product_card_image.php';

// 取得商品 ID
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 查詢商品資料
$product = null;
$error_message = '';

if ($product_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name
                               FROM products p
                               INNER JOIN categories c ON p.category_id = c.id
                               WHERE p.id = :product_id AND p.status = 'active'");
        $stmt->execute([':product_id' => $product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            $error_message = '找不到此商品或已下架';
        }
    } catch (PDOException $e) {
        $error_message = '查詢商品時發生錯誤：' . $e->getMessage();
    }
} else {
    $error_message = '找不到此商品或已下架';
}

// 檢查是否已登入
$is_logged_in = isset($_SESSION['user_id']);
$is_favorited = false;
if ($is_logged_in && $product_id > 0) {
    $is_favorited = isProductFavorited($pdo, (int)$_SESSION['user_id'], $product_id);
}

// 查詢所有分類（用於導覽列）
try {
    $stmt = $pdo->query("SELECT id, name, description FROM categories ORDER BY id");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// 查詢「周邊與配件」的分類 ID
$parts_category_id = null;
try {
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = '周邊與配件' LIMIT 1");
    $stmt->execute();
    $parts_category = $stmt->fetch();
    if ($parts_category) {
        $parts_category_id = $parts_category['id'];
    }
} catch (PDOException $e) {
    // 如果查詢失敗，保持為 null
}

// 查詢商品尺寸庫存（包含庫存為0的尺寸，僅顯示資料表實際有的尺寸）
$product_sizes = [];
$show_helmet_size_block = false;
$sizes_in_stock = false;
$first_in_stock = null;
if ($product) {
    try {
        $stmt = $pdo->prepare("SELECT size, stock FROM product_sizes WHERE product_id = :product_id ORDER BY FIELD(size, 'S', 'M', 'L', 'XL')");
        $stmt->execute([':product_id' => $product_id]);
        $product_sizes = $stmt->fetchAll();
        foreach ($product_sizes as $ps) {
            if ((int)$ps['stock'] > 0) {
                $sizes_in_stock = true;
                if ($first_in_stock === null) {
                    $first_in_stock = $ps;
                }
            }
        }
        // 安全帽尺寸區塊：非配件、且有尺寸列
        $show_helmet_size_block = (int)($product['is_addon'] ?? 0) !== 1 && !empty($product_sizes);
    } catch (PDOException $e) {
        $product_sizes = [];
    }
}

// 相關商品推薦（最多 4 筆）：先同分類同風格，不足再補同分類
$related_products = [];
if ($product) {
    try {
        $current_id = (int)$product['id'];
        $category_id = (int)$product['category_id'];
        $style_value = trim((string)($product['style'] ?? ''));

        $first_sql = "SELECT p.id, p.name, p.price, p.category_id,
                             " . primaryImageSubquery('p', 'pi') . " AS primary_image
                      FROM products p
                      WHERE p.status = 'active'
                        AND p.id <> :current_id
                        AND p.category_id = :category_id";
        $first_params = [
            ':current_id' => $current_id,
            ':category_id' => $category_id
        ];

        if ($style_value !== '') {
            $first_sql .= " AND p.style = :style";
            $first_params[':style'] = $style_value;
        } else {
            $first_sql .= " AND (p.style IS NULL OR p.style = '')";
        }

        $first_sql .= " ORDER BY p.id DESC LIMIT 4";
        $stmt = $pdo->prepare($first_sql);
        $stmt->execute($first_params);
        $first_batch = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $related_products = $first_batch ?: [];
        $picked_ids = array_map('intval', array_column($related_products, 'id'));

        if (count($related_products) < 4) {
            $needed = 4 - count($related_products);
            $exclude_ids = array_merge([$current_id], $picked_ids);
            $exclude_ids = array_values(array_unique(array_map('intval', $exclude_ids)));

            $exclude_placeholders = implode(',', array_fill(0, count($exclude_ids), '?'));
            $second_sql = "SELECT p.id, p.name, p.price, p.category_id,
                                  " . primaryImageSubquery('p', 'pi') . " AS primary_image
                           FROM products p
                           WHERE p.status = 'active'
                             AND p.category_id = ?
                             AND p.id NOT IN ($exclude_placeholders)
                           ORDER BY p.id DESC
                           LIMIT $needed";
            $second_params = array_merge([$category_id], $exclude_ids);
            $stmt = $pdo->prepare($second_sql);
            $stmt->execute($second_params);
            $second_batch = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($second_batch)) {
                $related_products = array_merge($related_products, $second_batch);
            }
        }
    } catch (PDOException $e) {
        $related_products = [];
    }
}

// 商品多圖（product_images）：sort_order ASC, id ASC；第一張為主圖
$product_gallery_urls = [];
$gallery_images_dir = 'assets/images/products/';
$gallery_default = $gallery_images_dir . 'default.jpg';

if ($product) {
    try {
        $img_stmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = :product_id " . productImageOrderClause());
        $img_stmt->execute([':product_id' => $product_id]);
        $img_rows = $img_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($img_rows as $img_row) {
            $rel = trim((string)($img_row['image_url'] ?? ''));
            if ($rel === '') {
                continue;
            }
            $rel = str_replace('\\', '/', $rel);
            if (strpos($rel, '..') !== false) {
                continue;
            }
            $rel = ltrim($rel, '/');
            $product_gallery_urls[] = $gallery_images_dir . $rel;
        }
    } catch (PDOException $e) {
        // 資料表不存在或其他錯誤時略過，改走下方 fallback
        $product_gallery_urls = [];
    }

    if (empty($product_gallery_urls)) {
        $product_gallery_urls[] = $gallery_default;
    }
}

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product ? htmlspecialchars($product['name']) . ' - ' : ''; ?>商品詳情 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<!-- 導覽列 -->
    <?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <!-- 商品詳情內容 -->
    <div class="product-detail-container product-detail-page">
        <div class="container">
            <?php if ($error_message): ?>
                <div class="product-detail-error">
                    <p><?php echo htmlspecialchars($error_message); ?></p>
                    <a href="products.php" class="btn-primary">返回商品總覽</a>
                </div>
            <?php elseif ($product): ?>
                <div class="product-detail-wrapper product-detail-layout">
                    <!-- 左側：縮圖列表 + 右側：主圖（多圖切換） -->
                    <?php
                    $gallery_count = count($product_gallery_urls);
                    $main_image_src = $product_gallery_urls[0];
                    $gallery_single_class = $gallery_count <= 1 ? ' product-detail-gallery--single' : '';
                    ?>
                    <div class="product-detail-gallery product-left product-image-gallery<?php echo $gallery_single_class; ?>">
                        <div class="product-detail-main-image product-detail-image product-image-box product-main-image">
                            <img
                                id="mainProductImage"
                                src="<?php echo htmlspecialchars($main_image_src, ENT_QUOTES); ?>"
                                alt="<?php echo htmlspecialchars($product['name']); ?>"
                            >
                        </div>
                        <?php if ($gallery_count > 1): ?>
                            <div class="product-detail-thumbs product-thumbnails" role="tablist" aria-label="商品圖片縮圖">
                                <?php foreach ($product_gallery_urls as $gi => $gurl): ?>
                                    <button
                                        type="button"
                                        class="product-detail-thumb<?php echo $gi === 0 ? ' is-active' : ''; ?>"
                                        data-src="<?php echo htmlspecialchars($gurl, ENT_QUOTES); ?>"
                                        onclick="document.getElementById('mainProductImage').src=this.dataset.src;"
                                        aria-label="檢視圖片 <?php echo (int)($gi + 1); ?>"
                                        aria-pressed="<?php echo $gi === 0 ? 'true' : 'false'; ?>"
                                    >
                                        <img class="thumbnail-img" src="<?php echo htmlspecialchars($gurl, ENT_QUOTES); ?>" alt="" loading="lazy">
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 右側資訊區 -->
                    <div class="product-detail-info product-right">
                        <div class="product-detail-header">
                            <span class="product-detail-category"><?php echo htmlspecialchars($product['category_name']); ?></span>
                            <h1 class="product-detail-name product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                            <div class="product-price-row">
                                <div class="product-detail-price product-price">
                                    NT$ <?php echo number_format($product['price'], 0); ?>
                                </div>
                                <form action="api/toggle_favorite.php" method="POST" class="product-favorite-form">
                                    <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars('product_detail.php?id=' . (int)$product['id']); ?>">
                                    <button
                                        type="submit"
                                        class="favorite-btn favorite-icon-btn <?php echo $is_favorited ? 'active' : ''; ?>"
                                        aria-label="<?php echo $is_favorited ? '取消收藏' : '加入收藏'; ?>"
                                        title="<?php echo $is_favorited ? '取消收藏' : '加入收藏'; ?>"
                                    >
                                        <svg class="heart-icon" viewBox="0 0 24 24" aria-hidden="true">
                                            <path class="heart-outline" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"></path>
                                            <path class="heart-fill" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"></path>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <?php if (!empty($product['description'])): ?>
                            <div class="product-detail-description product-description-block">
                                <h3>商品描述</h3>
                                <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!$show_helmet_size_block): ?>
                            <p class="product-detail-unified-size-msg">此商品為統一尺寸</p>
                        <?php endif; ?>

                        <!-- 互動區塊 -->
                        <div class="product-detail-actions product-options-block">
                            <?php if ($is_logged_in): ?>
                                <?php if ($show_helmet_size_block && !$sizes_in_stock): ?>
                                    <div class="product-detail-no-size">
                                        <p>此商品所有尺寸目前皆已售完，無法加入購物車。</p>
                                    </div>
                                <?php elseif ($show_helmet_size_block): ?>
                                    <form class="product-detail-form" id="addToCartForm">
                                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">
                                        
                                        <div class="form-group">
                                            <label class="form-label">尺寸</label>
                                            <select name="size" class="form-input" id="sizeSelect" required>
                                                <?php foreach ($product_sizes as $ps): 
                                                    $is_out_of_stock = (int)$ps['stock'] <= 0;
                                                    $is_selected = !$is_out_of_stock && $first_in_stock && ($ps['size'] === $first_in_stock['size']);
                                                ?>
                                                    <option value="<?php echo htmlspecialchars($ps['size']); ?>" 
                                                            data-stock="<?php echo htmlspecialchars($ps['stock']); ?>"
                                                            <?php echo $is_out_of_stock ? 'disabled' : ''; ?>
                                                            <?php echo $is_selected ? 'selected' : ''; ?>>
                                                        <?php 
                                                        if ($is_out_of_stock) {
                                                            echo htmlspecialchars($ps['size']) . '（已售完）';
                                                        } else {
                                                            echo htmlspecialchars($ps['size']) . '（剩餘 ' . htmlspecialchars($ps['stock']) . ' 件）';
                                                        }
                                                        ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">數量</label>
                                            <div class="custom-qty-dropdown" id="customQtyDropdown">
                                                <button type="button" class="custom-qty-trigger" id="customQtyTrigger" aria-haspopup="listbox" aria-expanded="false">
                                                    <span id="customQtyValue">1</span>
                                                    <span class="custom-qty-arrow">▾</span>
                                                </button>
                                                <div class="custom-qty-menu" id="customQtyMenu" role="listbox">
                                                    <?php
                                                    $max_qty = $first_in_stock ? max(1, (int)$first_in_stock['stock']) : 1;
                                                    for ($i = 1; $i <= $max_qty; $i++):
                                                    ?>
                                                        <button type="button" class="custom-qty-option" data-value="<?php echo $i; ?>"><?php echo $i; ?></button>
                                                    <?php endfor; ?>
                                                </div>
                                                <input type="hidden" name="quantity" id="quantityHiddenInput" value="1" required>
                                            </div>
                                        </div>

                                        <div class="product-cta-group">
                                            <button type="submit" class="btn-primary product-cta-primary product-detail-add-cart">加入購物車</button>
                                            <button type="button" class="btn-secondary product-cta-secondary product-detail-buy-now">立即購買</button>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <!-- 配件（is_addon=1）或無 product_sizes：不顯示尺寸；後端購物車 size 寫入 F -->
                                    <form class="product-detail-form" id="addToCartFormSimple">
                                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">
                                        <div class="form-group">
                                            <label class="form-label">數量</label>
                                            <div class="custom-qty-dropdown" id="customQtyDropdownSimple">
                                                <button type="button" class="custom-qty-trigger" id="customQtyTriggerSimple" aria-haspopup="listbox" aria-expanded="false">
                                                    <span id="customQtyValueSimple">1</span>
                                                    <span class="custom-qty-arrow">▾</span>
                                                </button>
                                                <div class="custom-qty-menu" id="customQtyMenuSimple" role="listbox">
                                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                                        <button type="button" class="custom-qty-option" data-value="<?php echo $i; ?>"><?php echo $i; ?></button>
                                                    <?php endfor; ?>
                                                </div>
                                                <input type="hidden" name="quantity" id="quantityHiddenInputSimple" value="1" required>
                                            </div>
                                        </div>
                                        <div class="product-cta-group">
                                            <button type="submit" class="btn-primary product-cta-primary product-detail-add-cart">加入購物車</button>
                                            <button type="button" class="btn-secondary product-cta-secondary product-detail-buy-now">立即購買</button>
                                        </div>
                                    </form>
                                <?php endif; ?>

                                <?php if ($show_helmet_size_block && $sizes_in_stock || (!$show_helmet_size_block && $product)): ?>
                                    <!-- Toast 訊息（有加入購物車表單時顯示） -->
                                    <div id="cartToast" class="cart-toast" style="display: none;">
                                        <span id="cartToastMessage"></span>
                                    </div>
                                    
                                    <script>
                                        function showToast(message, type) {
                                            var toast = document.getElementById('cartToast');
                                            var toastMessage = document.getElementById('cartToastMessage');
                                            if (!toast || !toastMessage) return;
                                            toastMessage.textContent = message;
                                            toast.className = 'cart-toast ' + type;
                                            toast.style.display = 'block';
                                            setTimeout(function() { toast.style.display = 'none'; }, 3000);
                                        }

                                        function bindAddToCartForm(formId, qtyResetId) {
                                            var form = document.getElementById(formId);
                                            if (!form) return;
                                            var buyNow = false;
                                            var buyNowBtn = form.querySelector('.product-detail-buy-now');
                                            if (buyNowBtn) {
                                                buyNowBtn.addEventListener('click', function() {
                                                    buyNow = true;
                                                    form.requestSubmit();
                                                });
                                            }
                                            form.addEventListener('submit', function(e) {
                                                e.preventDefault();
                                                var submitBtn = form.querySelector('button[type="submit"]');
                                                var originalText = submitBtn.textContent;
                                                submitBtn.disabled = true;
                                                submitBtn.textContent = '處理中...';
                                                fetch('api/add_to_cart.php', {
                                                    method: 'POST',
                                                    body: new FormData(form)
                                                })
                                                .then(function(r) { return r.json(); })
                                                .then(function(data) {
                                                    if (data.success) {
                                                        if (typeof window.updateNavbarBadges === 'function') {
                                                            window.updateNavbarBadges({ cart_count: data.cart_count });
                                                        } else {
                                                            window.dispatchEvent(new CustomEvent('cart:updated', { detail: { cart_count: data.cart_count } }));
                                                        }
                                                        showToast(data.message, 'success');
                                                        var q = document.getElementById(qtyResetId);
                                                        if (q) q.value = 1;
                                                        if (buyNow) {
                                                            window.location.href = 'checkout.php';
                                                            return;
                                                        }
                                                    } else {
                                                        if (data.redirect) {
                                                            window.location.href = data.redirect;
                                                        } else {
                                                            showToast(data.message, 'error');
                                                        }
                                                    }
                                                })
                                                .catch(function(err) {
                                                    showToast('加入購物車時發生錯誤', 'error');
                                                    console.error(err);
                                                })
                                                .finally(function() {
                                                    submitBtn.disabled = false;
                                                    submitBtn.textContent = originalText;
                                                    buyNow = false;
                                                });
                                            });
                                        }

                                        function initCustomQtyDropdown(config) {
                                            var dropdown = document.getElementById(config.dropdownId);
                                            var trigger = document.getElementById(config.triggerId);
                                            var valueEl = document.getElementById(config.valueId);
                                            var menu = document.getElementById(config.menuId);
                                            var hidden = document.getElementById(config.hiddenId);
                                            if (!dropdown || !trigger || !valueEl || !menu || !hidden) return null;

                                            function closeMenu() {
                                                dropdown.classList.remove('is-open');
                                                trigger.setAttribute('aria-expanded', 'false');
                                            }

                                            function openMenu() {
                                                dropdown.classList.add('is-open');
                                                trigger.setAttribute('aria-expanded', 'true');
                                            }

                                            function setValue(value) {
                                                var v = String(value || '1');
                                                valueEl.textContent = v;
                                                hidden.value = v;
                                            }

                                            function bindMenuOptions() {
                                                menu.querySelectorAll('.custom-qty-option').forEach(function(optionBtn) {
                                                    optionBtn.addEventListener('click', function() {
                                                        setValue(optionBtn.getAttribute('data-value'));
                                                        closeMenu();
                                                    });
                                                });
                                            }

                                            function rebuildOptions(maxValue) {
                                                var max = parseInt(maxValue, 10) || 1;
                                                if (max < 1) max = 1;
                                                var current = parseInt(hidden.value || '1', 10);
                                                menu.innerHTML = '';
                                                for (var i = 1; i <= max; i++) {
                                                    var btn = document.createElement('button');
                                                    btn.type = 'button';
                                                    btn.className = 'custom-qty-option';
                                                    btn.setAttribute('data-value', String(i));
                                                    btn.textContent = String(i);
                                                    menu.appendChild(btn);
                                                }
                                                setValue(Math.min(current, max));
                                                bindMenuOptions();
                                            }

                                            trigger.addEventListener('click', function() {
                                                if (dropdown.classList.contains('is-open')) {
                                                    closeMenu();
                                                } else {
                                                    openMenu();
                                                }
                                            });

                                            document.addEventListener('click', function(e) {
                                                if (!dropdown.contains(e.target)) {
                                                    closeMenu();
                                                }
                                            });

                                            bindMenuOptions();
                                            setValue(hidden.value || '1');

                                            return { rebuildOptions: rebuildOptions, setValue: setValue };
                                        }

                                        <?php if ($show_helmet_size_block && $sizes_in_stock): ?>
                                        (function() {
                                            var sizeSelect = document.getElementById('sizeSelect');
                                            var qtyDropdown = initCustomQtyDropdown({
                                                dropdownId: 'customQtyDropdown',
                                                triggerId: 'customQtyTrigger',
                                                valueId: 'customQtyValue',
                                                menuId: 'customQtyMenu',
                                                hiddenId: 'quantityHiddenInput'
                                            });
                                            if (sizeSelect && qtyDropdown) {
                                                var initialOpt = sizeSelect.options[sizeSelect.selectedIndex];
                                                qtyDropdown.rebuildOptions(initialOpt ? initialOpt.getAttribute('data-stock') : 1);

                                                sizeSelect.addEventListener('change', function() {
                                                    var opt = this.options[this.selectedIndex];
                                                    qtyDropdown.rebuildOptions(opt ? opt.getAttribute('data-stock') : 1);
                                                });
                                            }
                                        })();
                                        bindAddToCartForm('addToCartForm', 'quantityHiddenInput');
                                        <?php else: ?>
                                        initCustomQtyDropdown({
                                            dropdownId: 'customQtyDropdownSimple',
                                            triggerId: 'customQtyTriggerSimple',
                                            valueId: 'customQtyValueSimple',
                                            menuId: 'customQtyMenuSimple',
                                            hiddenId: 'quantityHiddenInputSimple'
                                        });
                                        bindAddToCartForm('addToCartFormSimple', 'quantityHiddenInputSimple');
                                        <?php endif; ?>
                                    </script>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="product-detail-login-prompt">
                                    <p>請先登入才能加入購物車</p>
                                    <a href="login.php?redirect=<?php echo urlencode('product_detail.php?id=' . $product_id); ?>" class="btn-primary">
                                        前往登入
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                        </div>
                    </div>
                </div>

                <?php if (!empty($related_products)): ?>
                    <section class="product-related-section" aria-label="相關商品推薦">
                        <div class="product-related-header">
                            <h2 class="product-related-title">相關商品推薦</h2>
                            <p class="product-related-subtitle">依目前商品類型推薦相似商品</p>
                        </div>
                        <div class="product-related-grid">
                            <?php foreach ($related_products as $rp): ?>
                                <?php $rp_img = resolve_product_card_image_src($rp['primary_image'] ?? null); ?>
                                <article class="product-related-card">
                                    <a class="product-related-image-link" href="product_detail.php?id=<?php echo (int)$rp['id']; ?>">
                                        <img class="product-related-image" src="<?php echo htmlspecialchars($rp_img, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars((string)$rp['name']); ?>">
                                    </a>
                                    <div class="product-related-body">
                                        <h3 class="product-related-name">
                                            <a href="product_detail.php?id=<?php echo (int)$rp['id']; ?>"><?php echo htmlspecialchars((string)$rp['name']); ?></a>
                                        </h3>
                                        <p class="product-related-price">NT$ <?php echo number_format((float)$rp['price'], 0); ?></p>
                                        <a class="product-related-link" href="product_detail.php?id=<?php echo (int)$rp['id']; ?>">查看詳情</a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
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
                        <li><a href="faq.php">常見問題</a></li>
                        <li><a href="return.php">退換貨政策</a></li>
                        <li><a href="shipping.php">運送說明</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3 class="footer-title">聯絡我們</h3>
                    <ul class="footer-links">
                        <li>電話：02-2905-2000</li>
                        <li>Email：helmetvrsefju@gmail.com</li>
                        <li>地址：新北市新莊區中正路510號</li>
                        <li class="social-links">
                            <a href="#" class="social-icon">Facebook</a>
                            <a href="#" class="social-icon">Instagram</a>
                            <a href="#" class="social-icon">Line</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>Powered by HelmetVRse</p>
            </div>
        </div>
    </footer>

    <script>
// 安全帽側邊欄互動功能
        (function() {
            try {
                const helmetMenu = document.querySelector('.helmet-menu');
                const helmetMenuToggle = document.getElementById('helmetMenuToggle');
                if (!helmetMenu || !helmetMenuToggle) return;

                let isHelmetLocked = false;

                helmetMenu.addEventListener('mouseenter', function() {
                    if (!isHelmetLocked) {
                        helmetMenu.classList.add('open');
                    }
                });

                helmetMenu.addEventListener('mouseleave', function() {
                    if (!isHelmetLocked) {
                        helmetMenu.classList.remove('open');
                    }
                });

                helmetMenuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    isHelmetLocked = !isHelmetLocked;
                    if (isHelmetLocked) {
                        helmetMenu.classList.add('open');
                    } else {
                        helmetMenu.classList.remove('open');
                    }
                });

                document.addEventListener('click', function(e) {
                    if (!helmetMenu.contains(e.target) && isHelmetLocked) {
                        isHelmetLocked = false;
                        helmetMenu.classList.remove('open');
                    }
                });
            } catch (error) {
                console.error('安全帽選單功能錯誤:', error);
            }
        })();

        // 漢堡選單切換
        (function() {
            const navToggle = document.getElementById('navToggle');
            const navMenu = document.getElementById('navMenu');
            
            if (navToggle && navMenu) {
                navToggle.addEventListener('click', function() {
                    navMenu.classList.toggle('active');
                    navToggle.classList.toggle('active');
                });
            }
        })();

        // 商品詳情：縮圖切換主圖
        (function() {
            const mainImg = document.getElementById('mainProductImage');
            if (!mainImg) return;
            document.querySelectorAll('.product-detail-thumb').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const src = btn.getAttribute('data-src');
                    if (src) {
                        mainImg.src = src;
                    }
                    document.querySelectorAll('.product-detail-thumb').forEach(function(b) {
                        b.classList.remove('is-active');
                        b.setAttribute('aria-pressed', 'false');
                    });
                    btn.classList.add('is-active');
                    btn.setAttribute('aria-pressed', 'true');
                });
            });
        })();
    </script>
</body>
</html>

