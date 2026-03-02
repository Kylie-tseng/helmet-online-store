# 專案重構執行計劃

## 已完成的工作

### 1. 刪除未使用的檔案 ✅
- `add_to_cart.php` - 已被 `api/add_to_cart.php` 取代
- `header.php` - 空檔案
- `footer.php` - 空檔案  
- `member/index.php` - 舊的會員中心頁面
- `assets/js/main.js` - 空檔案

**總計刪除：5 個檔案**

### 2. 建立新的資料夾結構 ✅
- `user/` - 前台使用者區
- `user/cart/` - 購物車相關
- `user/account/` - 帳號相關（登入、註冊、個人檔案）
- `user/checkout/` - 結帳流程相關

### 3. 建立路徑管理檔案 ✅
- `includes/paths.php` - 路徑常數定義（可選用）

## 建議的執行方式

由於這是一個大型重構，建議採用以下兩種方式之一：

### 方案 A：完整重構（推薦，但需要大量測試）
1. 移動所有前台使用者頁面到 `user/` 資料夾
2. 更新所有路徑引用（約 50+ 處需要更新）
3. 測試所有功能流程
4. 優點：結構清晰，長期維護容易
5. 缺點：需要大量時間測試和修正路徑

### 方案 B：漸進式重構（實用，風險較低）
1. 保持根目錄的主要入口檔案（index.php, products.php 等）
2. 只移動結帳相關頁面到 `user/checkout/`
3. 移動個人檔案相關到 `user/account/`
4. 優點：風險低，可以逐步進行
5. 缺點：結構不如方案 A 清晰

## 檔案移動建議（方案 A）

### 前台使用者頁面 → user/
```
index.php              → user/index.php
products.php           → user/products.php
product_detail.php     → user/product_detail.php
cart.php               → user/cart/index.php
checkout.php           → user/checkout/index.php
order_confirm.php      → user/checkout/order_confirm.php
payment_credit_card.php → user/checkout/payment_credit_card.php
login.php              → user/account/login.php
register.php           → user/account/register.php
profile.php            → user/account/profile.php
logout.php             → user/account/logout.php
authenticate.php       → user/account/authenticate.php
```

### 路徑更新範例

#### 在 user/ 子資料夾中的檔案：
```php
// 原本
require_once 'config.php';
require_once 'includes/cart_functions.php';

// 更新後
require_once '../config.php';
require_once '../includes/cart_functions.php';
```

#### HTML 連結：
```php
// 原本
<a href="products.php">商品總覽</a>
<a href="cart.php">購物車</a>

// 更新後（在 user/ 子資料夾中）
<a href="../products.php">商品總覽</a>
<a href="cart/">購物車</a>
```

#### CSS/JS 資源：
```php
// 原本
<link rel="stylesheet" href="assets/css/style.css">

// 更新後（在 user/ 子資料夾中）
<link rel="stylesheet" href="../assets/css/style.css">
```

## 需要更新的檔案清單

### 需要大量路徑更新的檔案：
1. `includes/navbar.php` - 導覽列連結
2. 所有前台使用者頁面（約 11 個檔案）
3. `authenticate.php` - 登入後重導向路徑
4. `api/add_to_cart.php` - API 回應中的重導向

### 需要檢查的檔案：
- `admin/` 下的所有檔案（可能需要更新連結到前台的路徑）
- `staff/` 下的所有檔案

## 建議的執行順序

1. **第一階段**：移動結帳相關頁面（風險較低）
   - `cart.php` → `user/cart/index.php`
   - `checkout.php` → `user/checkout/index.php`
   - `order_confirm.php` → `user/checkout/order_confirm.php`
   - `payment_credit_card.php` → `user/checkout/payment_credit_card.php`

2. **第二階段**：移動帳號相關頁面
   - `login.php` → `user/account/login.php`
   - `register.php` → `user/account/register.php`
   - `profile.php` → `user/account/profile.php`
   - `logout.php` → `user/account/logout.php`
   - `authenticate.php` → `user/account/authenticate.php`

3. **第三階段**：移動商品相關頁面
   - `index.php` → `user/index.php`
   - `products.php` → `user/products.php`
   - `product_detail.php` → `user/product_detail.php`

4. **第四階段**：更新所有路徑引用
   - 更新 `includes/navbar.php`
   - 更新所有頁面中的連結
   - 更新所有表單 action
   - 更新所有 JavaScript fetch 路徑

5. **第五階段**：測試與修正
   - 測試瀏覽商品流程
   - 測試購物車流程
   - 測試結帳流程
   - 測試會員功能

## 注意事項

1. **根目錄入口**：建議在根目錄保留一個 `index.php` 作為重導向：
   ```php
   <?php
   header('Location: user/index.php');
   exit;
   ```

2. **向後相容**：如果需要，可以建立 `.htaccess` 重寫規則來保持舊路徑可用

3. **測試重點**：
   - 所有超連結是否正常
   - 表單提交是否正常
   - AJAX 請求是否正常
   - 圖片和 CSS 是否正常載入
   - 登入/登出流程是否正常

## 當前狀態

- ✅ 已刪除未使用的檔案
- ✅ 已建立新的資料夾結構
- ⏳ 待執行：檔案移動和路徑更新（需要大量測試）

## 建議

考慮到這是一個大型重構任務，建議：
1. 先在測試環境執行
2. 逐步移動檔案，每移動一批就測試一次
3. 或者採用方案 B（漸進式重構），風險較低

