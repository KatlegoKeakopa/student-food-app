-- ============================================================
-- Student Centre Food Ordering Application
-- University of Botswana
-- Database Schema  (Audit-updated — May 2026)
-- ============================================================

CREATE DATABASE IF NOT EXISTS student_food_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE student_food_app;

-- ============================================================
-- FOOD OUTLETS
-- ============================================================
CREATE TABLE food_outlets (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                  VARCHAR(100) NOT NULL,
    description           TEXT,
    image_url             VARCHAR(255),
    cuisine               VARCHAR(80),
    opening_time          TIME,
    closing_time          TIME,
    accepts_delivery      TINYINT(1) NOT NULL DEFAULT 1,
    accepts_pickup        TINYINT(1) NOT NULL DEFAULT 1,
    is_temporarily_closed TINYINT(1) NOT NULL DEFAULT 0,
    is_active             TINYINT(1) NOT NULL DEFAULT 1,
    created_by_username   VARCHAR(50),
    updated_by_username   VARCHAR(50),
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Realistic UB Student Centre outlets
INSERT INTO food_outlets (name, description, image_url, cuisine, opening_time, closing_time, accepts_delivery, accepts_pickup) VALUES
('Sefalana Bakery & Café',
 'Freshly baked goods, artisan coffee, and light bites made daily in our Student Centre kitchen.',
 'images/sefalana.jpg', 'Bakery & Café', '07:00:00', '17:00:00', 1, 1),
('Blue & White Plate',
 'Authentic Botswana home cooking — seswaa, morogo, dikgobe, and pap prepared fresh every day.',
 'images/bluwhiteplate.jpg', 'Traditional', '10:00:00', '15:00:00', 1, 1),
('Hot Spot Food Court',
 'Burgers, loaded chips, wraps, and ice-cold drinks in a vibrant campus court.',
 'images/hotspot.jpg', 'Fast Food', '08:00:00', '19:00:00', 1, 1);

-- ============================================================
-- FOOD ITEMS (Menu)
-- Audit: ADD CHECK (stock_qty >= 0)
-- ============================================================
CREATE TABLE food_items (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outlet_id     INT UNSIGNED NOT NULL,
    name          VARCHAR(150) NOT NULL,
    description   TEXT,
    price         DECIMAL(8,2) NOT NULL,
    image_url     VARCHAR(255),
    category      VARCHAR(80),
    dietary_tags  VARCHAR(255),
    allergen_tags VARCHAR(255),
    stock_qty     INT CHECK (stock_qty >= 0),
    prep_minutes  SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    is_available  TINYINT(1) NOT NULL DEFAULT 1,
    created_by_username VARCHAR(50),
    updated_by_username VARCHAR(50),
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_item_outlet FOREIGN KEY (outlet_id) REFERENCES food_outlets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Sefalana Bakery & Café (outlet_id = 1) ──────────────────
INSERT INTO food_items (outlet_id, name, description, price, category, dietary_tags, allergen_tags, prep_minutes) VALUES
(1, 'Butter Croissant',
    'Freshly baked, golden and flaky — made with imported French butter.',
    12.00, 'Pastries', 'vegetarian', 'gluten,dairy', 8),
(1, 'Blueberry Muffin',
    'Bursting with fresh blueberries and topped with crunchy turbinado sugar.',
    10.00, 'Pastries', 'vegetarian', 'gluten,dairy,eggs', 8),
(1, 'Club Sandwich',
    'Triple-decker with grilled chicken, crispy bacon, iceberg lettuce, and tomato on toasted white bread.',
    38.00, 'Sandwiches', '', 'gluten', 12),
(1, 'Egg & Cheese Baguette',
    'Scrambled free-range eggs and melted cheddar in a crisp French baguette.',
    28.00, 'Sandwiches', 'vegetarian', 'gluten,dairy,eggs', 10),
(1, 'Cappuccino',
    'Double-shot espresso topped with velvety steamed milk foam. Available in regular or large.',
    18.00, 'Beverages', 'vegetarian', 'dairy', 5),
(1, 'Fresh Orange Juice',
    'Pressed daily from South African Valencia oranges — no sugar, no concentrates.',
    16.00, 'Beverages', 'vegan', '', 3),
(1, 'Carrot Cake Slice',
    'Moist, warmly spiced carrot cake with a generous layer of cream cheese frosting.',
    22.00, 'Cakes', 'vegetarian', 'gluten,dairy,eggs,nuts', 5);

-- ── Blue & White Plate (outlet_id = 2) ──────────────────────
INSERT INTO food_items (outlet_id, name, description, price, category, dietary_tags, allergen_tags, prep_minutes) VALUES
(2, 'Seswaa & Pap',
    'Slow-pounded beef served with soft pap and braised morogo (wild spinach). A Botswana classic.',
    55.00, 'Mains', 'gluten-free', '', 20),
(2, 'Chicken Stew',
    'Tender free-range chicken pieces in a rich tomato and herb sauce, served with steamed white rice.',
    48.00, 'Mains', 'gluten-free', '', 18),
(2, 'Mogodu',
    'Traditional tripe, slow-cooked with onions, garlic, and aromatic spices. Comfort food at its best.',
    42.00, 'Mains', 'gluten-free', '', 20),
(2, 'Dikgobe',
    'Hearty mixed bean and sorghum stew — a Motswana staple that warms you from the inside out.',
    35.00, 'Vegetarian', 'vegan,gluten-free', '', 15),
(2, 'Morogo Salad',
    'Fresh wild spinach, cherry tomatoes, sliced onion, and a light lemon dressing.',
    25.00, 'Vegetarian', 'vegan,gluten-free', '', 8),
(2, 'Maheu',
    'Chilled, naturally fermented maize drink — traditionally sweet and refreshing.',
    12.00, 'Beverages', 'vegan,gluten-free', '', 2),
(2, 'Mopane Worms (Phane)',
    'Sun-dried and pan-fried with chilli and salt — a cherished campus delicacy high in protein.',
    30.00, 'Mains', 'gluten-free', '', 10);

-- ── Hot Spot Food Court (outlet_id = 3) ─────────────────────
INSERT INTO food_items (outlet_id, name, description, price, category, dietary_tags, allergen_tags, prep_minutes) VALUES
(3, 'Classic Beef Burger',
    '150g pure beef patty, iceberg lettuce, sliced tomato, pickles, and our house sauce in a sesame bun.',
    45.00, 'Burgers', '', 'gluten,sesame', 14),
(3, 'Spicy Chicken Burger',
    'Crispy fried chicken fillet marinated in jalapeño brine, topped with sriracha mayo in a brioche bun.',
    48.00, 'Burgers', '', 'gluten,dairy', 14),
(3, 'Cheese & Bacon Wrap',
    'Grilled chicken strips, streaky bacon, melted cheddar, iceberg lettuce rolled in a flour tortilla.',
    40.00, 'Wraps', '', 'gluten,dairy', 12),
(3, 'Veggie Hummus Wrap',
    'Oven-roasted vegetables, creamy hummus, baby spinach, and sundried tomatoes in a wholemeal wrap.',
    35.00, 'Wraps', 'vegan', 'gluten,sesame', 10),
(3, 'Loaded Cheese Chips',
    'Thick-cut chips smothered in nacho cheese sauce, topped with spring onions and pickled jalapeños.',
    28.00, 'Sides', 'vegetarian', 'dairy', 10),
(3, 'Coleslaw',
    'Creamy house coleslaw with shredded cabbage, carrots, and a hint of apple cider vinegar.',
    12.00, 'Sides', 'vegetarian', 'eggs', 5),
(3, 'Passion Fruit Cooler',
    'Fresh passion fruit pulp, muddled mint, lime juice, and soda water over crushed ice.',
    18.00, 'Beverages', 'vegan', '', 5);

-- ============================================================
-- CUSTOMERS
-- ============================================================
CREATE TABLE customers (
    username      VARCHAR(50)  NOT NULL PRIMARY KEY,
    password_hash VARCHAR(255) NOT NULL,
    first_name    VARCHAR(80)  NOT NULL,
    last_name     VARCHAR(80)  NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    phone         VARCHAR(20),
    student_id    VARCHAR(20),
    status        ENUM('active','disabled','deleted') NOT NULL DEFAULT 'active',
    deleted_at    DATETIME,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE customer_addresses (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_username VARCHAR(50) NOT NULL,
    label             VARCHAR(80) NOT NULL,
    address_line      TEXT NOT NULL,
    lat               DECIMAL(10,7),
    lng               DECIMAL(10,7),
    is_default        TINYINT(1) NOT NULL DEFAULT 0,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_addr_customer FOREIGN KEY (customer_username) REFERENCES customers(username) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE customer_payment_methods (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_username VARCHAR(50) NOT NULL,
    provider          VARCHAR(40) NOT NULL,
    provider_token    VARCHAR(255) NOT NULL,
    brand             VARCHAR(40),
    last4             CHAR(4),
    expires_at        DATE,
    is_default        TINYINT(1) NOT NULL DEFAULT 0,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pm_customer FOREIGN KEY (customer_username) REFERENCES customers(username) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE favorites (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_username VARCHAR(50) NOT NULL,
    outlet_id         INT UNSIGNED,
    food_item_id      INT UNSIGNED,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fav_customer FOREIGN KEY (customer_username) REFERENCES customers(username) ON DELETE CASCADE,
    CONSTRAINT fk_fav_outlet   FOREIGN KEY (outlet_id)         REFERENCES food_outlets(id) ON DELETE CASCADE,
    CONSTRAINT fk_fav_item     FOREIGN KEY (food_item_id)      REFERENCES food_items(id) ON DELETE CASCADE,
    CONSTRAINT uq_fav UNIQUE (customer_username, outlet_id, food_item_id)
) ENGINE=InnoDB;

-- Realistic sample customers  (password: Student@1234)
-- bcrypt hash of 'Student@1234' at cost 12
INSERT INTO customers (username, password_hash, first_name, last_name, email, phone, student_id) VALUES
('tshepo_m',  '$2y$12$Qn5CUfvY8KpW2.sLdJ3FpO7BnWlZ0hX9m4tE6qR1dA8yN2vI5uKeO',
              'Tshepo',  'Modise',   'tshepo.modise@ub.bw',   '+267 71 234 567', 'UB20210045'),
('bontle_k',  '$2y$12$Qn5CUfvY8KpW2.sLdJ3FpO7BnWlZ0hX9m4tE6qR1dA8yN2vI5uKeO',
              'Bontle',  'Kgosi',    'bontle.kgosi@ub.bw',    '+267 72 345 678', 'UB20190112'),
('kagiso_d',  '$2y$12$Qn5CUfvY8KpW2.sLdJ3FpO7BnWlZ0hX9m4tE6qR1dA8yN2vI5uKeO',
              'Kagiso',  'Ditsele',  'kagiso.ditsele@ub.bw',  '+267 73 456 789', 'UB20220078');

-- ============================================================
-- OUTLET STAFF
-- ============================================================
CREATE TABLE outlet_staff (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name    VARCHAR(80)  NOT NULL,
    last_name     VARCHAR(80)  NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    outlet_id     INT UNSIGNED NOT NULL,
    role          ENUM('staff','manager') NOT NULL DEFAULT 'staff',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_staff_outlet FOREIGN KEY (outlet_id) REFERENCES food_outlets(id)
) ENGINE=InnoDB;

-- ============================================================
-- ADMINISTRATORS
-- ============================================================
CREATE TABLE admins (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- ORDERS
-- Audit: ADD idempotency_key, tip_amount, item_name snapshot
-- ============================================================
CREATE TABLE orders (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_username  VARCHAR(50) NOT NULL,
    outlet_id          INT UNSIGNED NOT NULL,
    order_type         ENUM('delivery','pickup') NOT NULL,
    status             ENUM('pending','preparing','ready','on_transit','delivered','cancelled') NOT NULL DEFAULT 'pending',
    delivery_address   TEXT,
    delivery_lat       DECIMAL(10,7),
    delivery_lng       DECIMAL(10,7),
    estimated_ready_at DATETIME,
    delivery_fee       DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
    tip_amount         DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
    total_amount       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method     ENUM('cash_on_delivery','cash_on_pickup','card','mobile_money') NOT NULL DEFAULT 'cash_on_pickup',
    payment_status     ENUM('not_required','pending','paid','failed','refunded') NOT NULL DEFAULT 'not_required',
    cancellation_reason VARCHAR(255),
    special_notes      TEXT,
    idempotency_key    VARCHAR(64) NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_customer FOREIGN KEY (customer_username) REFERENCES customers(username),
    CONSTRAINT fk_order_outlet   FOREIGN KEY (outlet_id)         REFERENCES food_outlets(id),
    UNIQUE KEY uq_idempotency (customer_username, idempotency_key)
) ENGINE=InnoDB;

CREATE TABLE order_status_history (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id       INT UNSIGNED NOT NULL,
    from_status    VARCHAR(40),
    to_status      VARCHAR(40) NOT NULL,
    actor_username VARCHAR(50),
    actor_role     VARCHAR(30),
    note           VARCHAR(255),
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- ORDER ITEMS
-- Audit: ADD item_name snapshot so history is immutable
-- ============================================================
CREATE TABLE order_items (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id     INT UNSIGNED NOT NULL,
    food_item_id INT UNSIGNED NOT NULL,
    item_name    VARCHAR(150) NOT NULL DEFAULT '',
    quantity     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    unit_price   DECIMAL(8,2) NOT NULL,
    CONSTRAINT fk_oi_order FOREIGN KEY (order_id)     REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_oi_item  FOREIGN KEY (food_item_id) REFERENCES food_items(id)
) ENGINE=InnoDB;

-- ============================================================
-- RATINGS & REVIEWS
-- ============================================================
CREATE TABLE ratings (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id          INT UNSIGNED NOT NULL UNIQUE,
    customer_username VARCHAR(50) NOT NULL,
    outlet_id         INT UNSIGNED NOT NULL,
    rating            TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
    review            TEXT,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rating_order    FOREIGN KEY (order_id)         REFERENCES orders(id),
    CONSTRAINT fk_rating_customer FOREIGN KEY (customer_username) REFERENCES customers(username),
    CONSTRAINT fk_rating_outlet   FOREIGN KEY (outlet_id)         REFERENCES food_outlets(id)
) ENGINE=InnoDB;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE notifications (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_username VARCHAR(50) NOT NULL,
    order_id          INT UNSIGNED,
    message           VARCHAR(255) NOT NULL,
    is_read           TINYINT(1) NOT NULL DEFAULT 0,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_customer FOREIGN KEY (customer_username) REFERENCES customers(username) ON DELETE CASCADE,
    CONSTRAINT fk_notif_order    FOREIGN KEY (order_id)          REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- PROMOTIONS  (Audit: add max_uses_per_customer + order_promotions junction)
-- ============================================================
CREATE TABLE promotions (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                 VARCHAR(40) NOT NULL UNIQUE,
    description          VARCHAR(255),
    discount_type        ENUM('percent','flat') NOT NULL,
    discount_value       DECIMAL(8,2) NOT NULL,
    min_order_amount     DECIMAL(8,2),
    max_redemptions      INT UNSIGNED,
    max_uses_per_customer TINYINT UNSIGNED NOT NULL DEFAULT 1,
    valid_from           DATETIME,
    valid_until          DATETIME,
    is_active            TINYINT(1) NOT NULL DEFAULT 1,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE order_promotions (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id       INT UNSIGNED NOT NULL,
    promotion_id   INT UNSIGNED NOT NULL,
    discount_amount DECIMAL(8,2) NOT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_op_order     FOREIGN KEY (order_id)     REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_op_promotion FOREIGN KEY (promotion_id) REFERENCES promotions(id)
) ENGINE=InnoDB;

-- ============================================================
-- SUPPORT TICKETS  (Audit: add ticket_replies)
-- ============================================================
CREATE TABLE support_tickets (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_username VARCHAR(50),
    order_id          INT UNSIGNED,
    subject           VARCHAR(150) NOT NULL,
    message           TEXT NOT NULL,
    status            ENUM('open','waiting','resolved','closed') NOT NULL DEFAULT 'open',
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ticket_customer FOREIGN KEY (customer_username) REFERENCES customers(username) ON DELETE SET NULL,
    CONSTRAINT fk_ticket_order    FOREIGN KEY (order_id)          REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE ticket_replies (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id  INT UNSIGNED NOT NULL,
    author     VARCHAR(80) NOT NULL,
    role       VARCHAR(30) NOT NULL,
    message    TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reply_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- AUTH ATTEMPTS
-- ============================================================
CREATE TABLE auth_attempts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(80) NOT NULL,
    role       VARCHAR(30) NOT NULL,
    ip_address VARCHAR(64) NOT NULL,
    success    TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- AUDIT LOGS
-- ============================================================
CREATE TABLE audit_logs (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_username VARCHAR(80),
    actor_role     VARCHAR(30),
    action         VARCHAR(80) NOT NULL,
    entity_type    VARCHAR(80) NOT NULL,
    entity_id      VARCHAR(80),
    ip_address     VARCHAR(64),
    metadata       JSON,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- SSR LOG  (Audit: debug tracing via announce() helper)
-- ============================================================
CREATE TABLE ssr_log (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message    VARCHAR(1000) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- DEFAULT ADMIN ACCOUNT  (password: Admin@1234)
-- ============================================================
INSERT INTO admins (username, password_hash, email) VALUES
('admin', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@ub.ac.bw');

-- ============================================================
-- OUTLET STAFF  (password: Staff@1234)
-- ============================================================
INSERT INTO outlet_staff (username, password_hash, first_name, last_name, email, outlet_id, role) VALUES
('sefalana_staff', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                   'Lesego', 'Dube',    'sefalana@ub.ac.bw',  1, 'staff'),
('bwp_manager',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                   'Kagiso', 'Molefe',  'bwp@ub.ac.bw',       2, 'manager'),
('hotspot_staff',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                   'Mpho',   'Sithole', 'hotspot@ub.ac.bw',   3, 'staff');

-- ============================================================
-- PRE-SEEDED ORDERS (realistic demo data)
-- ============================================================
INSERT INTO orders
  (id, customer_username, outlet_id, order_type, status,
   delivery_address, delivery_fee, tip_amount, total_amount,
   payment_method, payment_status, created_at)
VALUES
(1, 'tshepo_m',  3, 'delivery', 'delivered',
   'Main Campus Block A, Room 12', 5.00, 0.00, 123.00,
   'cash_on_delivery', 'not_required', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 'bontle_k',  1, 'delivery', 'on_transit',
   'Library Block, 2nd Floor',     5.00, 0.00, 79.00,
   'cash_on_delivery', 'not_required', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(3, 'kagiso_d',  2, 'pickup',   'preparing',
   NULL,                            0.00, 0.00, 90.00,
   'cash_on_pickup',  'not_required', DATE_SUB(NOW(), INTERVAL 20 MINUTE));

-- Order ORD-2024001: 2× Classic Beef Burger + 1× Loaded Cheese Chips (outlet 3)
INSERT INTO order_items (order_id, food_item_id, item_name, quantity, unit_price) VALUES
(1, 15, 'Classic Beef Burger',  2, 45.00),
(1, 19, 'Loaded Cheese Chips',  1, 28.00);

-- Order ORD-2024002: 1× Club Sandwich + 2× Cappuccino (outlet 1)
INSERT INTO order_items (order_id, food_item_id, item_name, quantity, unit_price) VALUES
(2, 3, 'Club Sandwich', 1, 38.00),
(2, 5, 'Cappuccino',    2, 18.00);

-- Order ORD-2024003: 1× Seswaa & Pap + 1× Dikgobe (outlet 2)
INSERT INTO order_items (order_id, food_item_id, item_name, quantity, unit_price) VALUES
(3, 8,  'Seswaa & Pap', 1, 55.00),
(3, 11, 'Dikgobe',      1, 35.00);

-- Status history for seeded orders
INSERT INTO order_status_history (order_id, from_status, to_status, actor_username, actor_role, note) VALUES
(1, NULL,         'pending',    'tshepo_m',      'customer', 'Order placed'),
(1, 'pending',    'preparing',  'hotspot_staff', 'staff',    'Accepted'),
(1, 'preparing',  'ready',      'hotspot_staff', 'staff',    'Ready for collection'),
(1, 'ready',      'on_transit', 'hotspot_staff', 'staff',    'Picked up by rider'),
(1, 'on_transit', 'delivered',  'hotspot_staff', 'staff',    'Delivered'),
(2, NULL,         'pending',    'bontle_k',      'customer', 'Order placed'),
(2, 'pending',    'preparing',  'sefalana_staff','staff',    'Accepted'),
(2, 'preparing',  'ready',      'sefalana_staff','staff',    'Ready'),
(2, 'ready',      'on_transit', 'sefalana_staff','staff',    'Out for delivery'),
(3, NULL,         'pending',    'kagiso_d',      'customer', 'Order placed'),
(3, 'pending',    'preparing',  'bwp_manager',   'staff',    'Accepted');

-- Notifications for seeded customers
INSERT INTO notifications (customer_username, order_id, message, is_read) VALUES
('tshepo_m', 1, 'Your order from Hot Spot Food Court has been delivered! Enjoy your meal.', 0),
('bontle_k', 2, 'Your order from Sefalana Bakery & Café is on its way 🛵', 0),
('kagiso_d', 3, 'Blue & White Plate is now preparing your order.',            1);

-- Launch promo code
INSERT INTO promotions (code, description, discount_type, discount_value, min_order_amount,
                        max_redemptions, max_uses_per_customer, valid_from, valid_until, is_active)
VALUES ('UBFOOD20', 'P20.00 off your first order — UB Student Centre launch offer.',
        'flat', 20.00, 30.00, 500, 1,
        DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 90 DAY), 1);

-- ============================================================
-- INDEXES for query performance
-- ============================================================
CREATE INDEX idx_orders_customer         ON orders(customer_username);
CREATE INDEX idx_orders_outlet           ON orders(outlet_id);
CREATE INDEX idx_orders_status           ON orders(status);
CREATE INDEX idx_order_items_order       ON order_items(order_id);
CREATE INDEX idx_notifications_customer  ON notifications(customer_username, is_read);
CREATE INDEX idx_food_items_outlet       ON food_items(outlet_id, is_available);
CREATE INDEX idx_food_items_search       ON food_items(name, category, is_available);
CREATE INDEX idx_addresses_customer      ON customer_addresses(customer_username);
CREATE INDEX idx_favorites_customer      ON favorites(customer_username);
CREATE INDEX idx_order_status_history    ON order_status_history(order_id, created_at);
CREATE INDEX idx_auth_attempts_lookup    ON auth_attempts(username, role, ip_address, created_at);
CREATE INDEX idx_audit_logs_entity       ON audit_logs(entity_type, entity_id, created_at);
CREATE INDEX idx_order_promotions_order  ON order_promotions(order_id);
CREATE INDEX idx_ssr_log_ts              ON ssr_log(created_at);
