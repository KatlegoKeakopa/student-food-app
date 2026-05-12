-- Additive migration for existing student_food_app databases.
-- Run after backing up your local database.

USE student_food_app;

ALTER TABLE customers
  ADD COLUMN account_type ENUM('student','staff') NOT NULL DEFAULT 'student' AFTER phone,
  ADD COLUMN work_id VARCHAR(30) NULL AFTER student_id;

ALTER TABLE orders
  MODIFY status ENUM(
    'pending','ready','on_transit','delivered',
    'pending_vendor','accepted','preparing','ready_for_pickup','driver_assigned',
    'picked_up','delivered_pending_confirmation','completed','declined_by_vendor','cancelled'
  ) NOT NULL DEFAULT 'pending_vendor';

UPDATE orders SET status = 'pending_vendor' WHERE status = 'pending';
UPDATE orders SET status = 'ready_for_pickup' WHERE status = 'ready';
UPDATE orders SET status = 'picked_up' WHERE status = 'on_transit';
UPDATE orders SET status = 'completed' WHERE status = 'delivered';

UPDATE order_status_history SET from_status = 'pending_vendor' WHERE from_status = 'pending';
UPDATE order_status_history SET to_status = 'pending_vendor' WHERE to_status = 'pending';
UPDATE order_status_history SET from_status = 'ready_for_pickup' WHERE from_status = 'ready';
UPDATE order_status_history SET to_status = 'ready_for_pickup' WHERE to_status = 'ready';
UPDATE order_status_history SET from_status = 'picked_up' WHERE from_status = 'on_transit';
UPDATE order_status_history SET to_status = 'picked_up' WHERE to_status = 'on_transit';
UPDATE order_status_history SET from_status = 'completed' WHERE from_status = 'delivered';
UPDATE order_status_history SET to_status = 'completed' WHERE to_status = 'delivered';

ALTER TABLE orders
  MODIFY status ENUM(
    'pending_vendor','accepted','preparing','ready_for_pickup','driver_assigned',
    'picked_up','delivered_pending_confirmation','completed','declined_by_vendor','cancelled'
  ) NOT NULL DEFAULT 'pending_vendor';

ALTER TABLE vendor_applications
  MODIFY status ENUM('submitted','under_review','needs_changes','approved','rejected','activated') NOT NULL DEFAULT 'submitted',
  ADD COLUMN pickup_available TINYINT(1) NOT NULL DEFAULT 1 AFTER service_modes,
  ADD COLUMN delivery_available TINYINT(1) NOT NULL DEFAULT 1 AFTER pickup_available,
  ADD COLUMN food_safety_reference VARCHAR(120) NULL AFTER licence_number,
  ADD COLUMN document_url VARCHAR(255) NULL AFTER food_safety_reference,
  ADD COLUMN notes TEXT NULL AFTER document_url;

ALTER TABLE driver_applications
  MODIFY status ENUM('submitted','under_review','needs_changes','approved','rejected','activated') NOT NULL DEFAULT 'submitted',
  ADD COLUMN campus_id VARCHAR(40) NULL AFTER payout_method,
  ADD COLUMN identity_reference VARCHAR(120) NULL AFTER campus_id,
  ADD COLUMN document_url VARCHAR(255) NULL AFTER identity_reference,
  ADD COLUMN notes TEXT NULL AFTER document_url;
