# 專案重構摘要報告

## 一、已刪除的檔案

### 未使用的 PHP 檔案
1. `add_to_cart.php` - 已被 `api/add_to_cart.php` 取代
2. `header.php` - 空檔案，未被使用
3. `footer.php` - 空檔案，未被使用
4. `member/index.php` - 舊的會員中心，已被 `profile.php` 取代

### 未使用的資源檔案
5. `assets/js/main.js` - 空檔案，未被引用

**總計刪除：5 個檔案**

## 二、資料夾結構規劃

### 前台使用者區（user/）
- `user/index.php` - 首頁（從根目錄 `index.php` 移動）
- `user/products.php` - 商品列表
- `user/product_detail.php` - 商品詳情
- `user/cart/index.php` - 購物車頁面
- `user/checkout/index.php` - 結帳頁面（填寫資料）
- `user/checkout/order_confirm.php` - 訂單確認頁
- `user/checkout/payment_credit_card.php` - 信用卡付款頁
- `user/account/login.php` - 登入頁
- `user/account/register.php` - 註冊頁
- `user/account/profile.php` - 個人檔案頁
- `user/account/logout.php` - 登出處理

### 管理者區（admin/）
- `admin/index.php` - 後台首頁
- `admin/dashboard.php` - 後台儀表板
- `admin/products.php` - 商品管理
- `admin/orders.php` - 訂單管理
- `admin/users.php` - 會員管理
- `admin/auth_check.php` - 權限檢查

### 店員區（staff/）
- `staff/index.php` - 店員系統入口（保留作為未來擴充）
- `staff/dashboard.php` - 店員儀表板

### API 區（api/）
- `api/add_to_cart.php` - 加入購物車 API
- `api/auth/login.php` - 登入 API（若存在）
- `api/auth/logout.php` - 登出 API（若存在）
- `api/auth/check_login.php` - 檢查登入狀態 API（若存在）

### 共用資源（includes/）
- `includes/cart_functions.php` - 購物車相關函數
- `includes/navbar.php` - 導覽列渲染函數
- `includes/checkout_steps.php` - 結帳步驟條函數
- `includes/paths.php` - 路徑常數定義（新增）

## 三、檔案移動計劃

### 需要移動的檔案
1. `index.php` → `user/index.php`
2. `products.php` → `user/products.php`
3. `product_detail.php` → `user/product_detail.php`
4. `cart.php` → `user/cart/index.php`
5. `checkout.php` → `user/checkout/index.php`
6. `order_confirm.php` → `user/checkout/order_confirm.php`
7. `payment_credit_card.php` → `user/checkout/payment_credit_card.php`
8. `login.php` → `user/account/login.php`
9. `register.php` → `user/account/register.php`
10. `profile.php` → `user/account/profile.php`
11. `logout.php` → `user/account/logout.php`

### 保留在根目錄的檔案
- `config.php` - 資料庫設定（所有頁面都需要）
- `authenticate.php` - 登入驗證處理（可考慮移到 `user/account/`）

## 四、路徑更新需求

### 需要更新的路徑類型
1. **require/include 路徑**
   - `require_once 'config.php'` → `require_once '../config.php'`（在子資料夾中）
   - `require_once 'includes/...'` → `require_once '../includes/...'`

2. **HTML 連結路徑**
   - `<a href="products.php">` → `<a href="../products.php">` 或使用相對路徑
   - `<a href="cart.php">` → `<a href="cart/">` 或 `<a href="../cart/">`

3. **表單 action 路徑**
   - `<form action="authenticate.php">` → `<form action="../authenticate.php">`

4. **JavaScript/AJAX 路徑**
   - `fetch('api/add_to_cart.php')` → `fetch('../api/add_to_cart.php')`

5. **CSS/JS 資源路徑**
   - `href="assets/css/style.css"` → `href="../assets/css/style.css"`

6. **重導向路徑**
   - `header('Location: login.php')` → `header('Location: account/login.php')`

## 五、程式碼清理項目

### 需要檢查的檔案
1. 未使用的函式定義
2. 未使用的變數
3. 被註解掉的舊程式碼
4. 重複的程式碼區塊

## 六、命名優化建議

### 可考慮重新命名的檔案
1. `authenticate.php` → `user/account/authenticate.php` 或保持原位置但更名為 `auth_handler.php`
2. API 檔案可考慮更明確的命名，例如：
   - `api/add_to_cart.php` → `api/cart/add.php`

## 七、注意事項

1. **根目錄入口**：考慮在根目錄保留一個 `index.php` 作為重導向到 `user/index.php`
2. **向後相容**：如果需要，可以建立符號連結或重導向來保持舊路徑可用
3. **測試**：移動檔案後需要測試所有主要流程：
   - 瀏覽商品 → 加入購物車 → 結帳 → 訂單確認
   - 會員登入/註冊
   - 個人檔案管理
   - 訂單管理

## 八、執行狀態

- [x] 分析專案結構
- [x] 刪除未使用的檔案
- [x] 建立 user/ 資料夾結構
- [ ] 移動前台使用者頁面
- [ ] 更新所有路徑引用
- [ ] 清理程式碼
- [ ] 添加檔案註解
- [ ] 測試主要流程

