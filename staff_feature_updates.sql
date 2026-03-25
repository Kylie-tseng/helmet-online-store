-- Staff practical feature updates (orders / returns)

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS staff_note TEXT NULL AFTER status,
    ADD COLUMN IF NOT EXISTS shipping_company VARCHAR(100) NULL AFTER shipping_method,
    ADD COLUMN IF NOT EXISTS tracking_number VARCHAR(120) NULL AFTER shipping_company;

CREATE TABLE IF NOT EXISTS return_requests (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id INT(11) NOT NULL,
    user_id INT(11) DEFAULT NULL,
    reason TEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'pending',
    refund_status VARCHAR(40) NOT NULL DEFAULT 'pending_refund',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE return_requests
    ADD COLUMN IF NOT EXISTS refund_status VARCHAR(40) NOT NULL DEFAULT 'pending_refund' AFTER status,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at;
