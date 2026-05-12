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
 'images/hotspot.jpg', 'Fast Food', '08:00:00', '19:00:00', 1, 1),
('Executive Catering',
 'Premium campus catering for events, board lunches, and daily specials. Buffets, platters and à la carte at the Student Centre.',
 'images/executive-catering.jpg', 'Continental & Local', '07:30:00', '16:30:00', 1, 1),
('Moghul Catering',
 'Authentic North Indian cuisine — biryani, curries, naan, and tandoori specials prepared fresh daily on campus.',
 'images/moghul-catering.jpg', 'Indian', '10:00:00', '18:00:00', 1, 1),
('Eastern Restaurant',
 'Pan-Asian flavours featuring stir-fries, rice boxes, dim sum, and noodle soups made to order at the UB Student Centre.',
 'images/eastern-restaurant.jpg', 'Pan-Asian', '09:00:00', '17:30:00', 1, 1),
('Gaff Kan',
 'Popular campus grill serving flame-grilled meats, boerewors rolls, pap & gravy, and ice-cold drinks in a relaxed setting.',
 'images/gaff-kan.jpg', 'Grills & Braai', '10:30:00', '19:00:00', 1, 1);

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
    account_type  ENUM('student','staff') NOT NULL DEFAULT 'student',
    student_id    VARCHAR(20),
    work_id       VARCHAR(30),
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
-- Original 3 accounts use the schema-seeded hash; 10 new use a freshly generated hash.
-- New hash for Student@1234: $2y$12$C5J/54ZH8sZRz7h717278e02eFNnNqdryAGtss5Hq7EOBG8dVUaYG
INSERT INTO customers (username, password_hash, first_name, last_name, email, phone, account_type, student_id, work_id) VALUES
-- ── Original seed (students) ──────────────────────────────────────────────────
('tshepo_m', '$2y$12$81csDCWKDWSYmo9jDGyAuuiS5KQANsbpnRjxpYYUNDjb7MEGiD1LO',
             'Tshepo', 'Modise', 'tshepo.modise@ub.ac.bw', '+267 71 234 567', 'student', 'UB20210045', NULL),
('bontle_k', '$2y$12$81csDCWKDWSYmo9jDGyAuuiS5KQANsbpnRjxpYYUNDjb7MEGiD1LO',
             'Bontle', 'Kgosi',  'bontle.kgosi@ub.ac.bw',  '+267 72 345 678', 'student', 'UB20190112', NULL),
('kagiso_d', '$2y$12$81csDCWKDWSYmo9jDGyAuuiS5KQANsbpnRjxpYYUNDjb7MEGiD1LO',
             'Kagiso', 'Ditsele','kagiso.ditsele@ub.ac.bw', '+267 73 456 789', 'student', 'UB20220078', NULL),
-- ── Additional students ───────────────────────────────────────────────────────
('lesego_b', '$2y$12$C5J/54ZH8sZRz7h717278e02eFNnNqdryAGtss5Hq7EOBG8dVUaYG',
             'Lesego', 'Bogopa', 'lesego.bogopa@ub.ac.bw', '+267 71 601 001', 'student', 'UB20230101', NULL),
('keabetswe_n', '$2y$12$C5J/54ZH8sZRz7h717278e02eFNnNqdryAGtss5Hq7EOBG8dVUaYG',
               'Keabetswe', 'Ntshimologo', 'keabetswe.n@ub.ac.bw', '+267 72 602 002', 'student', 'UB20240078', NULL),
('refilwe_s', '$2y$12$C5J/54ZH8sZRz7h717278e02eFNnNqdryAGtss5Hq7EOBG8dVUaYG',
              'Refilwe', 'Sithole', 'refilwe.sithole@ub.ac.bw', '+267 73 603 003', 'student', 'UB20220156', NULL),
('oarabile_m', '$2y$12$C5J/54ZH8sZRz7h717278e02eFNnNqdryAGtss5Hq7EOBG8dVUaYG',
               'Oarabile', 'Mokgosi', 'oarabile.mokgosi@ub.ac.bw', '+267 74 604 004', 'student', 'UB20250032', NULL),
('thato_r', '$2y$12$C5J/54ZH8sZRz7h717278e02eFNnNqdryAGtss5Hq7EOBG8dVUaYG',
            'Thato', 'Ramoroka', 'thato.ramoroka@ub.ac.bw', '+267 75 605 005', 'student', 'UB20240199', NULL),
('mpho_d', '$2y$12$C5J/54ZH8sZRz7h717278e02eFNnNqdryAGtss5Hq7EOBG8dVUaYG',
           'Mpho', 'Dikgole', 'mpho.dikgole@ub.ac.bw', '+267 71 606 006', 'student', 'UB20210309', NULL),
-- ── Staff members ─────────────────────────────────────────────────────────────
('dr_seele', '$2y$12$C5J/54ZH8sZRz7h717278e02eFNnNqdryAGtss5Hq7EOBG8dVUaYG',
             'Boiki', 'Seele', 'b.seele@ub.ac.bw', '+267 72 701 001', 'staff', NULL, 'UB-STAFF-0142'),
('lect_phiri', '$2y$12$C5J/54ZH8sZRz7h717278e02eFNnNqdryAGtss5Hq7EOBG8dVUaYG',
               'Grace', 'Phiri', 'g.phiri@ub.ac.bw', '+267 73 702 002', 'staff', NULL, 'UB-STAFF-0278'),
('admin_support', '$2y$12$C5J/54ZH8sZRz7h717278e02eFNnNqdryAGtss5Hq7EOBG8dVUaYG',
                  'Moshe', 'Kgomanyane', 'm.kgomanyane@ub.ac.bw', '+267 74 703 003', 'staff', NULL, 'UB-STAFF-0391'),
('itservices_k', '$2y$12$C5J/54ZH8sZRz7h717278e02eFNnNqdryAGtss5Hq7EOBG8dVUaYG',
                 'Keitumetse', 'Modiegi', 'k.modiegi@ub.ac.bw', '+267 75 704 004', 'staff', NULL, 'UB-STAFF-0445');

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
    status             ENUM('pending_vendor','accepted','preparing','ready_for_pickup','driver_assigned','picked_up','delivered_pending_confirmation','completed','declined_by_vendor','cancelled') NOT NULL DEFAULT 'pending_vendor',
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
-- PAYMENTS, REFUNDS, PAYOUTS & RECONCILIATION
-- Provider adapters must never store raw card numbers or CVV.
-- ============================================================
CREATE TABLE payment_intents (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id           INT UNSIGNED NOT NULL,
    customer_username  VARCHAR(50) NOT NULL,
    provider           VARCHAR(40) NOT NULL,
    method             ENUM('card','mobile_money') NOT NULL,
    provider_reference VARCHAR(120),
    amount             DECIMAL(10,2) NOT NULL,
    currency           CHAR(3) NOT NULL DEFAULT 'BWP',
    status             ENUM('requires_action','pending','succeeded','failed','cancelled','expired') NOT NULL DEFAULT 'pending',
    checkout_url       VARCHAR(500),
    mobile_number      VARCHAR(30),
    idempotency_key    VARCHAR(80),
    expires_at         DATETIME,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pi_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_pi_customer FOREIGN KEY (customer_username) REFERENCES customers(username),
    UNIQUE KEY uq_payment_intent_idempotency (customer_username, idempotency_key)
) ENGINE=InnoDB;

CREATE TABLE payments (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id           INT UNSIGNED NOT NULL,
    payment_intent_id  INT UNSIGNED NOT NULL,
    provider           VARCHAR(40) NOT NULL,
    method             ENUM('card','mobile_money') NOT NULL,
    amount             DECIMAL(10,2) NOT NULL,
    currency           CHAR(3) NOT NULL DEFAULT 'BWP',
    status             ENUM('pending','paid','failed','refunded','partially_refunded') NOT NULL DEFAULT 'pending',
    provider_reference VARCHAR(120),
    paid_at            DATETIME,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_intent FOREIGN KEY (payment_intent_id) REFERENCES payment_intents(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payment_events (
    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider           VARCHAR(40) NOT NULL,
    provider_event_id  VARCHAR(160),
    provider_reference VARCHAR(120),
    event_type         VARCHAR(120) NOT NULL,
    signature_valid    TINYINT(1) NOT NULL DEFAULT 0,
    payload_json       JSON,
    processed_at       DATETIME,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payment_provider_event (provider, provider_event_id)
) ENGINE=InnoDB;

CREATE TABLE refunds (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id           INT UNSIGNED NOT NULL,
    payment_id         INT UNSIGNED NOT NULL,
    amount             DECIMAL(10,2) NOT NULL,
    reason             VARCHAR(255) NOT NULL,
    status             ENUM('requested','approved','processing','succeeded','failed','cancelled') NOT NULL DEFAULT 'requested',
    provider_reference VARCHAR(120),
    requested_by       VARCHAR(80),
    approved_by        VARCHAR(80),
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_refund_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_refund_payment FOREIGN KEY (payment_id) REFERENCES payments(id)
) ENGINE=InnoDB;

CREATE TABLE payout_accounts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_type      ENUM('vendor','driver') NOT NULL,
    owner_id        INT UNSIGNED NOT NULL,
    provider        VARCHAR(40) NOT NULL,
    account_type    ENUM('bank','mobile_wallet') NOT NULL,
    account_label   VARCHAR(120) NOT NULL,
    encrypted_ref   VARBINARY(512) NOT NULL,
    last4           VARCHAR(8),
    is_verified     TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE payouts (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payout_account_id  INT UNSIGNED NOT NULL,
    amount             DECIMAL(10,2) NOT NULL,
    currency           CHAR(3) NOT NULL DEFAULT 'BWP',
    status             ENUM('queued','processing','paid','failed','cancelled') NOT NULL DEFAULT 'queued',
    provider_reference VARCHAR(120),
    scheduled_for      DATE,
    paid_at            DATETIME,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payout_account FOREIGN KEY (payout_account_id) REFERENCES payout_accounts(id)
) ENGINE=InnoDB;

CREATE TABLE reconciliation_entries (
    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider           VARCHAR(40) NOT NULL,
    provider_reference VARCHAR(120) NOT NULL,
    order_id           INT UNSIGNED,
    expected_amount    DECIMAL(10,2),
    settled_amount     DECIMAL(10,2),
    currency           CHAR(3) NOT NULL DEFAULT 'BWP',
    status             ENUM('matched','mismatch','missing_internal','missing_provider','pending') NOT NULL DEFAULT 'pending',
    notes              VARCHAR(255),
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_recon_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
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
-- NOTIFICATION PROVIDER QUEUE, PREFERENCES & DEVICES
-- ============================================================
CREATE TABLE notification_templates (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(80) NOT NULL,
    channel     ENUM('email','sms','push','in_app') NOT NULL,
    locale      VARCHAR(12) NOT NULL DEFAULT 'en-BW',
    subject     VARCHAR(160),
    body        TEXT NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_template (template_key, channel, locale)
) ENGINE=InnoDB;

CREATE TABLE notification_jobs (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient     VARCHAR(160) NOT NULL,
    user_role     VARCHAR(30),
    channel       ENUM('email','sms','push','in_app') NOT NULL,
    template_key  VARCHAR(80) NOT NULL,
    payload_json  JSON,
    status        ENUM('queued','sending','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
    retry_count   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    next_attempt_at DATETIME,
    last_error    VARCHAR(255),
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE notification_deliveries (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id         BIGINT UNSIGNED NOT NULL,
    provider       VARCHAR(40),
    provider_message_id VARCHAR(160),
    delivery_status VARCHAR(80) NOT NULL,
    event_json     JSON,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_delivery_job FOREIGN KEY (job_id) REFERENCES notification_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notification_preferences (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_role         VARCHAR(30) NOT NULL,
    user_identifier   VARCHAR(80) NOT NULL,
    email_enabled     TINYINT(1) NOT NULL DEFAULT 1,
    sms_enabled       TINYINT(1) NOT NULL DEFAULT 1,
    push_enabled      TINYINT(1) NOT NULL DEFAULT 1,
    marketing_enabled TINYINT(1) NOT NULL DEFAULT 0,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_notification_pref (user_role, user_identifier)
) ENGINE=InnoDB;

CREATE TABLE device_tokens (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_role       VARCHAR(30) NOT NULL,
    user_identifier VARCHAR(80) NOT NULL,
    platform        ENUM('web','android','ios') NOT NULL,
    token           VARCHAR(255) NOT NULL,
    last_seen       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_device_token (token)
) ENGINE=InnoDB;

-- ============================================================
-- DRIVER DISPATCH & LIVE TRACKING
-- ============================================================
CREATE TABLE drivers (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED,
    full_name      VARCHAR(160) NOT NULL,
    phone          VARCHAR(30) NOT NULL,
    email          VARCHAR(160),
    vehicle_type   VARCHAR(60),
    verification_status ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
    api_token_hash VARCHAR(255),
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE driver_availability (
    driver_id       INT UNSIGNED PRIMARY KEY,
    is_online       TINYINT(1) NOT NULL DEFAULT 0,
    current_delivery_id INT UNSIGNED,
    current_lat     DECIMAL(10,7),
    current_lng     DECIMAL(10,7),
    last_seen_at    DATETIME,
    CONSTRAINT fk_avail_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE deliveries (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED NOT NULL UNIQUE,
    driver_id       INT UNSIGNED,
    pickup_address  VARCHAR(255),
    dropoff_address TEXT,
    status          ENUM('unassigned','offered','accepted','heading_to_vendor','arrived_vendor','picked_up','heading_to_customer','arrived_customer','delivered','disputed','cancelled') NOT NULL DEFAULT 'unassigned',
    eta_minutes     SMALLINT UNSIGNED,
    delivery_pin    VARCHAR(10),
    assigned_at     DATETIME,
    picked_up_at    DATETIME,
    delivered_at    DATETIME,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_delivery_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_delivery_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE driver_locations (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    driver_id   INT UNSIGNED NOT NULL,
    delivery_id INT UNSIGNED,
    lat         DECIMAL(10,7) NOT NULL,
    lng         DECIMAL(10,7) NOT NULL,
    accuracy_m  DECIMAL(8,2),
    heading     SMALLINT UNSIGNED,
    speed_mps   DECIMAL(8,2),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_loc_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    CONSTRAINT fk_loc_delivery FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE dispatch_attempts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id    INT UNSIGNED NOT NULL,
    driver_id   INT UNSIGNED NOT NULL,
    response    ENUM('offered','accepted','rejected','timed_out','cancelled') NOT NULL DEFAULT 'offered',
    offered_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME,
    CONSTRAINT fk_attempt_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_attempt_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE proof_of_delivery (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    delivery_id INT UNSIGNED NOT NULL,
    proof_type  ENUM('pin','photo','signature') NOT NULL,
    proof_ref   VARCHAR(255) NOT NULL,
    lat         DECIMAL(10,7),
    lng         DECIMAL(10,7),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pod_delivery FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE driver_earnings (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    driver_id   INT UNSIGNED NOT NULL,
    delivery_id INT UNSIGNED NOT NULL,
    delivery_fee DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    tip_amount   DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    bonus_amount DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    payout_status ENUM('unpaid','queued','paid','held') NOT NULL DEFAULT 'unpaid',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_earn_driver FOREIGN KEY (driver_id) REFERENCES drivers(id),
    CONSTRAINT fk_earn_delivery FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- LEGAL DOCUMENTS, CONSENT & DATA REQUESTS
-- ============================================================
CREATE TABLE legal_documents (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_type  ENUM('privacy_policy','terms','refund_policy','vendor_agreement','driver_agreement','cookie_notice') NOT NULL,
    version        VARCHAR(40) NOT NULL,
    title          VARCHAR(160) NOT NULL,
    content_url    VARCHAR(255) NOT NULL,
    effective_date DATE NOT NULL,
    is_active      TINYINT(1) NOT NULL DEFAULT 1,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_legal_doc (document_type, version)
) ENGINE=InnoDB;

CREATE TABLE user_consents (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_role       VARCHAR(30) NOT NULL,
    user_identifier VARCHAR(80) NOT NULL,
    legal_document_id INT UNSIGNED NOT NULL,
    accepted_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address      VARCHAR(64),
    CONSTRAINT fk_consent_doc FOREIGN KEY (legal_document_id) REFERENCES legal_documents(id)
) ENGINE=InnoDB;

CREATE TABLE data_requests (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_role       VARCHAR(30) NOT NULL,
    user_identifier VARCHAR(80) NOT NULL,
    request_type    ENUM('access','delete','export','correction') NOT NULL,
    status          ENUM('open','in_review','fulfilled','rejected') NOT NULL DEFAULT 'open',
    notes           TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE incidents (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(160) NOT NULL,
    severity    ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    status      ENUM('open','contained','resolved','closed') NOT NULL DEFAULT 'open',
    description TEXT,
    created_by  VARCHAR(80),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- VENDOR & DRIVER APPLICATIONS / COMPLIANCE
-- ============================================================
CREATE TABLE vendor_applications (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_name   VARCHAR(160) NOT NULL,
    trading_name    VARCHAR(160) NOT NULL,
    contact_name    VARCHAR(160) NOT NULL,
    email           VARCHAR(160) NOT NULL,
    phone           VARCHAR(30) NOT NULL,
    location        VARCHAR(255) NOT NULL,
    cuisine_type    VARCHAR(80),
    operating_hours VARCHAR(160),
    service_modes   VARCHAR(80),
    pickup_available TINYINT(1) NOT NULL DEFAULT 1,
    delivery_available TINYINT(1) NOT NULL DEFAULT 1,
    payout_method   VARCHAR(80),
    licence_number  VARCHAR(80),
    food_safety_reference VARCHAR(120),
    document_url    VARCHAR(255),
    notes           TEXT,
    status          ENUM('submitted','under_review','needs_changes','approved','rejected','activated') NOT NULL DEFAULT 'submitted',
    reviewer_notes  TEXT,
    reviewed_by     VARCHAR(80),
    reviewed_at     DATETIME,
    created_outlet_id INT UNSIGNED,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_vendor_created_outlet FOREIGN KEY (created_outlet_id) REFERENCES food_outlets(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE vendor_application_documents (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    document_type  VARCHAR(80) NOT NULL,
    file_url       VARCHAR(255) NOT NULL,
    status         ENUM('pending','verified','rejected','expired') NOT NULL DEFAULT 'pending',
    expires_at     DATE,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_vendor_doc_app FOREIGN KEY (application_id) REFERENCES vendor_applications(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE vendor_compliance_checks (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    check_key      VARCHAR(80) NOT NULL,
    status         ENUM('pending','passed','failed','waived') NOT NULL DEFAULT 'pending',
    notes          VARCHAR(255),
    checked_by     VARCHAR(80),
    checked_at     DATETIME,
    CONSTRAINT fk_vendor_check_app FOREIGN KEY (application_id) REFERENCES vendor_applications(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE driver_applications (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legal_name      VARCHAR(160) NOT NULL,
    email           VARCHAR(160),
    phone           VARCHAR(30) NOT NULL,
    id_number       VARCHAR(80),
    licence_number  VARCHAR(80),
    vehicle_type    VARCHAR(60),
    vehicle_registration VARCHAR(80),
    emergency_contact VARCHAR(160),
    service_areas   VARCHAR(255),
    payout_method   VARCHAR(80),
    campus_id       VARCHAR(40),
    identity_reference VARCHAR(120),
    document_url    VARCHAR(255),
    notes           TEXT,
    status          ENUM('submitted','under_review','needs_changes','approved','rejected','activated') NOT NULL DEFAULT 'submitted',
    reviewer_notes  TEXT,
    reviewed_by     VARCHAR(80),
    reviewed_at     DATETIME,
    created_driver_id INT UNSIGNED,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE driver_application_documents (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    document_type  VARCHAR(80) NOT NULL,
    file_url       VARCHAR(255) NOT NULL,
    status         ENUM('pending','verified','rejected','expired') NOT NULL DEFAULT 'pending',
    expires_at     DATE,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_driver_doc_app FOREIGN KEY (application_id) REFERENCES driver_applications(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE driver_compliance_checks (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    check_key      VARCHAR(80) NOT NULL,
    status         ENUM('pending','passed','failed','waived') NOT NULL DEFAULT 'pending',
    notes          VARCHAR(255),
    checked_by     VARCHAR(80),
    checked_at     DATETIME,
    CONSTRAINT fk_driver_check_app FOREIGN KEY (application_id) REFERENCES driver_applications(id) ON DELETE CASCADE
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
-- ADMIN ACCOUNTS
--   admin        — original account (password hash from schema seed)
--   ubfood_admin — recommended account  password: Admin@UBFood26
--     Name: Tebogo Osei-Mensah (UB Food Systems Administrator)
-- ============================================================
INSERT INTO admins (username, password_hash, email) VALUES
('admin',
 '$2y$12$pRAgBmF4mK0SMqnnn/cgWe.I64yp0L5uIBLXE/TTOdI2dTt86wJF.',
 'admin@ub.ac.bw'),
('ubfood_admin',
 '$2y$12$xCRknt3AZmtYyH55vFyRdukhb/kfjPYJSuB/wMCCOpH8pKQo9y7E2',
 'ubfood.admin@ub.ac.bw');

-- ============================================================
-- OUTLET STAFF
--   Original accounts  password: Staff@1234
--   New accounts       password: Vendor@1234
--   Vendor hash: $2y$12$tX/2NoFD.zS.vg8nF24/2e3k5JR0HZAdcgdZtpA2c9d8SFPmGc2bq
-- ============================================================
INSERT INTO outlet_staff (username, password_hash, first_name, last_name, email, outlet_id, role) VALUES
-- ── Original staff (outlets 1-3) ───────────────────────────
('sefalana_staff', '$2y$12$.ltc52bPvskGnfQA8s4s9ObT9zsqSkz42US0R7IpetngbxH/Tsanm',
                   'Lesego',    'Dube',       'sefalana@ub.ac.bw',           1, 'staff'),
('bwp_manager',    '$2y$12$.ltc52bPvskGnfQA8s4s9ObT9zsqSkz42US0R7IpetngbxH/Tsanm',
                   'Kagiso',    'Molefe',     'bwp@ub.ac.bw',                2, 'manager'),
('hotspot_staff',  '$2y$12$.ltc52bPvskGnfQA8s4s9ObT9zsqSkz42US0R7IpetngbxH/Tsanm',
                   'Mpho',      'Sithole',    'hotspot@ub.ac.bw',            3, 'staff'),
-- ── Sefalana — new manager ──────────────────────────────────
('sefalana_mgr',   '$2y$12$tX/2NoFD.zS.vg8nF24/2e3k5JR0HZAdcgdZtpA2c9d8SFPmGc2bq',
                   'Onkemetse', 'Seleka',     'onkemetse.seleka@sefalana.ub.ac.bw', 1, 'manager'),
-- ── Executive Catering (outlet 4) ──────────────────────────
('exec_catering_mgr',   '$2y$12$tX/2NoFD.zS.vg8nF24/2e3k5JR0HZAdcgdZtpA2c9d8SFPmGc2bq',
                         'Dineo',    'Tau',        'dineo.tau@executive.ub.ac.bw',   4, 'manager'),
('exec_catering_staff', '$2y$12$tX/2NoFD.zS.vg8nF24/2e3k5JR0HZAdcgdZtpA2c9d8SFPmGc2bq',
                         'Thapelo',  'Mosweu',     'thapelo.mosweu@executive.ub.ac.bw', 4, 'staff'),
-- ── Moghul Catering (outlet 5) ─────────────────────────────
('moghul_mgr',     '$2y$12$tX/2NoFD.zS.vg8nF24/2e3k5JR0HZAdcgdZtpA2c9d8SFPmGc2bq',
                   'Ranjit',    'Singh',      'ranjit.singh@moghul.ub.ac.bw',   5, 'manager'),
('moghul_staff',   '$2y$12$tX/2NoFD.zS.vg8nF24/2e3k5JR0HZAdcgdZtpA2c9d8SFPmGc2bq',
                   'Priya',     'Naidoo',     'priya.naidoo@moghul.ub.ac.bw',   5, 'staff'),
-- ── Eastern Restaurant (outlet 6) ──────────────────────────
('eastern_mgr',    '$2y$12$tX/2NoFD.zS.vg8nF24/2e3k5JR0HZAdcgdZtpA2c9d8SFPmGc2bq',
                   'Wei',       'Zhang',      'wei.zhang@eastern.ub.ac.bw',     6, 'manager'),
('eastern_staff',  '$2y$12$tX/2NoFD.zS.vg8nF24/2e3k5JR0HZAdcgdZtpA2c9d8SFPmGc2bq',
                   'Mei',       'Lim',        'mei.lim@eastern.ub.ac.bw',       6, 'staff'),
-- ── Gaff Kan (outlet 7) ─────────────────────────────────────
('gaffkan_mgr',    '$2y$12$tX/2NoFD.zS.vg8nF24/2e3k5JR0HZAdcgdZtpA2c9d8SFPmGc2bq',
                   'Boitumelo', 'Kgaswane',   'boitumelo.kgaswane@gaffkan.ub.ac.bw', 7, 'manager'),
('gaffkan_staff',  '$2y$12$tX/2NoFD.zS.vg8nF24/2e3k5JR0HZAdcgdZtpA2c9d8SFPmGc2bq',
                   'Kenanao',   'Ramotse',    'kenanao.ramotse@gaffkan.ub.ac.bw',    7, 'staff');

-- ============================================================
-- PRE-SEEDED ORDERS (realistic demo data)
-- ============================================================
INSERT INTO orders
  (id, customer_username, outlet_id, order_type, status,
   delivery_address, delivery_fee, tip_amount, total_amount,
   payment_method, payment_status, created_at)
VALUES
(1, 'tshepo_m',  3, 'delivery', 'completed',
   'Main Campus Block A, Room 12', 5.00, 0.00, 123.00,
   'cash_on_delivery', 'not_required', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 'bontle_k',  1, 'delivery', 'picked_up',
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
(1, NULL,         'pending_vendor', 'tshepo_m',      'customer', 'Order placed'),
(1, 'pending_vendor', 'accepted',   'hotspot_staff', 'staff',    'Accepted'),
(1, 'accepted',   'preparing',      'hotspot_staff', 'staff',    'Preparing'),
(1, 'preparing',  'ready_for_pickup','hotspot_staff','staff',    'Ready for collection'),
(1, 'ready_for_pickup','picked_up', 'hotspot_staff', 'staff',    'Picked up by rider'),
(1, 'picked_up',  'completed',      'tshepo_m',      'customer', 'Customer confirmed receipt'),
(2, NULL,         'pending_vendor', 'bontle_k',      'customer', 'Order placed'),
(2, 'pending_vendor', 'accepted',   'sefalana_staff','staff',    'Accepted'),
(2, 'accepted',   'preparing',      'sefalana_staff','staff',    'Preparing'),
(2, 'preparing',  'ready_for_pickup','sefalana_staff','staff',   'Ready'),
(2, 'ready_for_pickup','picked_up', 'sefalana_staff','staff',    'Out for delivery'),
(3, NULL,         'pending_vendor', 'kagiso_d',      'customer', 'Order placed'),
(3, 'pending_vendor', 'accepted',   'bwp_manager',   'staff',    'Accepted'),
(3, 'accepted',   'preparing',      'bwp_manager',   'staff',    'Preparing');

-- ============================================================
-- SEEDED DRIVERS (5 approved drivers)
--   api_token_hash stores bcrypt of  Driver@1234
--   Real one-time token issued via admin panel after approval.
-- ============================================================
INSERT INTO drivers
  (id, full_name, phone, email, vehicle_type, verification_status, api_token_hash)
VALUES
(1, 'Goitsemang Tshosa',  '+267 72 101 001', 'goitsemang.tshosa@driver.ubfood.bw',
    'bicycle',    'approved',
    '$2y$12$4Vj7.ymj6V80sYaxkh8RLeTmuRnbXmMLgg63Yh/tyX8KVPeQV3iZy'),
(2, 'Mpho Sebego',        '+267 73 202 002', 'mpho.sebego@driver.ubfood.bw',
    'scooter',    'approved',
    '$2y$12$4Vj7.ymj6V80sYaxkh8RLeTmuRnbXmMLgg63Yh/tyX8KVPeQV3iZy'),
(3, 'Tshepiso Molefe',    '+267 74 303 003', 'tshepiso.molefe@driver.ubfood.bw',
    'motorcycle', 'approved',
    '$2y$12$4Vj7.ymj6V80sYaxkh8RLeTmuRnbXmMLgg63Yh/tyX8KVPeQV3iZy'),
(4, 'Kelebogile Sento',   '+267 75 404 004', 'kelebogile.sento@driver.ubfood.bw',
    'car',        'approved',
    '$2y$12$4Vj7.ymj6V80sYaxkh8RLeTmuRnbXmMLgg63Yh/tyX8KVPeQV3iZy'),
(5, 'Neo Ditsele',        '+267 71 505 005', 'neo.ditsele@driver.ubfood.bw',
    'walking',    'approved',
    '$2y$12$4Vj7.ymj6V80sYaxkh8RLeTmuRnbXmMLgg63Yh/tyX8KVPeQV3iZy');

INSERT INTO driver_availability (driver_id, is_online, current_lat, current_lng) VALUES
(1, 0, -24.6617, 25.9326),
(2, 0, -24.6617, 25.9326),
(3, 0, -24.6617, 25.9326),
(4, 0, -24.6617, 25.9326),
(5, 0, -24.6617, 25.9326);

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

INSERT INTO legal_documents (document_type, version, title, content_url, effective_date, is_active) VALUES
('privacy_policy', '2026.05', 'Privacy Policy', '/legal/privacy-policy-2026-05.html', CURDATE(), 1),
('terms', '2026.05', 'Terms of Service', '/legal/terms-2026-05.html', CURDATE(), 1),
('refund_policy', '2026.05', 'Refund and Cancellation Policy', '/legal/refund-policy-2026-05.html', CURDATE(), 1),
('vendor_agreement', '2026.05', 'Vendor Agreement', '/legal/vendor-agreement-2026-05.html', CURDATE(), 1),
('driver_agreement', '2026.05', 'Driver Agreement', '/legal/driver-agreement-2026-05.html', CURDATE(), 1);

INSERT INTO notification_templates (template_key, channel, subject, body) VALUES
('order_placed', 'email', 'Your UB Food order was placed', 'Order {{order_id}} was placed successfully.'),
('order_status', 'sms', NULL, 'UB Food order {{order_id}} is now {{status}}.'),
('driver_assigned', 'push', 'Driver assigned', 'Your driver is on the way to collect order {{order_id}}.'),
('payment_receipt', 'email', 'Payment receipt', 'We received BWP {{amount}} for order {{order_id}}.'),
('vendor_application_received', 'email', 'Vendor application received', 'Vendor application {{application_id}} has been received for review.'),
('driver_application_received', 'sms', NULL, 'UB Food received driver application {{application_id}} for review.');

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
CREATE INDEX idx_payment_intents_order   ON payment_intents(order_id, status);
CREATE INDEX idx_payments_order          ON payments(order_id, status);
CREATE INDEX idx_payment_events_ref      ON payment_events(provider, provider_reference);
CREATE INDEX idx_refunds_order           ON refunds(order_id, status);
CREATE INDEX idx_notification_jobs_due   ON notification_jobs(status, next_attempt_at);
CREATE INDEX idx_device_tokens_user      ON device_tokens(user_role, user_identifier);
CREATE INDEX idx_deliveries_order        ON deliveries(order_id, status);
CREATE INDEX idx_driver_locations_latest ON driver_locations(driver_id, created_at);
CREATE INDEX idx_dispatch_attempts_order ON dispatch_attempts(order_id, response);
CREATE INDEX idx_user_consents_user      ON user_consents(user_role, user_identifier);
CREATE INDEX idx_data_requests_user      ON data_requests(user_role, user_identifier, status);
CREATE INDEX idx_vendor_app_status       ON vendor_applications(status, created_at);
CREATE INDEX idx_driver_app_status       ON driver_applications(status, created_at);

