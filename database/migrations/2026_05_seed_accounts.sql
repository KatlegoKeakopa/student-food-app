-- ============================================================
-- Migration: 2026_05_seed_accounts.sql
-- Additive seed — can be run against existing database.
-- Adds: 4 new outlets, 5 vendor staff, 5 drivers, 10 customers,
--        1 improved admin account.
--
-- Demo credentials (all plaintext shown for development only):
--   Customers (original 3) : Student@1234
--   Customers (new 10)     : Student@1234
--   Original staff (3)     : Staff@1234
--   New vendor staff (9)   : Vendor@1234
--   Admin (legacy)         : Admin@1234
--   Admin (recommended)    : Admin@UBFood26
-- ============================================================

USE student_food_app;

-- ============================================================
-- 0. FIX BROKEN PASSWORD HASHES FROM ORIGINAL SCHEMA SEED
--    The original schema.sql had incorrect hashes for 3 customers
--    and 3 staff accounts. This section corrects them.
--    Safe to run multiple times.
-- ============================================================

-- Fix original 3 customers  (Student@1234)
UPDATE customers SET password_hash = '$2y$12$81csDCWKDWSYmo9jDGyAuuiS5KQANsbpnRjxpYYUNDjb7MEGiD1LO'
WHERE username IN ('tshepo_m', 'bontle_k', 'kagiso_d');

-- Fix original 3 outlet staff  (Staff@1234)
UPDATE outlet_staff SET password_hash = '$2y$12$.ltc52bPvskGnfQA8s4s9ObT9zsqSkz42US0R7IpetngbxH/Tsanm'
WHERE username IN ('sefalana_staff', 'bwp_manager', 'hotspot_staff');

-- Fix legacy admin  (Admin@1234)
UPDATE admins SET password_hash = '$2y$12$pRAgBmF4mK0SMqnnn/cgWe.I64yp0L5uIBLXE/TTOdI2dTt86wJF.'
WHERE username = 'admin';

-- ============================================================
-- A. NEW FOOD OUTLETS
--    Sefalana is already in seed data as id=1.
--    We add Executive Catering, Moghul, Eastern, Gaff Kan.
-- ============================================================
INSERT IGNORE INTO food_outlets
  (id, name, description, image_url, cuisine,
   opening_time, closing_time, accepts_delivery, accepts_pickup, is_active)
VALUES
(4,  'Executive Catering',
     'Premium campus catering for events, board lunches, and daily specials. Buffets, platters and à la carte at the Student Centre.',
     'images/executive-catering.jpg', 'Continental & Local',
     '07:30:00', '16:30:00', 1, 1, 1),

(5,  'Moghul Catering',
     'Authentic North Indian cuisine — biryani, curries, naan, and tandoori specials prepared fresh daily on campus.',
     'images/moghul-catering.jpg', 'Indian',
     '10:00:00', '18:00:00', 1, 1, 1),

(6,  'Eastern Restaurant',
     'Pan-Asian flavours featuring stir-fries, rice boxes, dim sum, and noodle soups made to order at the UB Student Centre.',
     'images/eastern-restaurant.jpg', 'Pan-Asian',
     '09:00:00', '17:30:00', 1, 1, 1),

(7,  'Gaff Kan',
     'Popular campus grill serving flame-grilled meats, boerewors rolls, pap & gravy, and ice-cold drinks in a relaxed setting.',
     'images/gaff-kan.jpg', 'Grills & Braai',
     '10:30:00', '19:00:00', 1, 1, 1);

-- ============================================================
-- B. VENDOR / OUTLET STAFF ACCOUNTS
--    password: Vendor@1234
--    hash: $2y$12$tX/2NoFD.zS.vg8nF24/2e3k5JR0HZAdcgdZtpA2c9d8SFPmGc2bq
-- ============================================================
INSERT IGNORE INTO outlet_staff
  (username, password_hash, first_name, last_name, email, outlet_id, role)
VALUES
-- Sefalana Bakery & Café (outlet 1) — new manager account
('sefalana_mgr',
 '$2y$12$tX/2NoFD.zS.vg8nF24/2e3k5JR0HZAdcgdZtpA2c9d8SFPmGc2bq',
 'Onkemetse', 'Seleka',
 'onkemetse.seleka@sefalana.ub.ac.bw', 1, 'manager'),

-- Executive Catering (outlet 4)
('exec_catering_mgr',
 '$2y$12$tX/2NoFD.zS.vg8nF24/2e3k5JR0HZAdcgdZtpA2c9d8SFPmGc2bq',
 'Dineo', 'Tau',
 'dineo.tau@executive.ub.ac.bw', 4, 'manager'),

('exec_catering_staff',
 '$2y$12$tX/2NoFD.zS.vg8nF24/2e3k5JR0HZAdcgdZtpA2c9d8SFPmGc2bq',
 'Thapelo', 'Mosweu',
 'thapelo.mosweu@executive.ub.ac.bw', 4, 'staff'),

-- Moghul Catering (outlet 5)
('moghul_mgr',
 '$2y$12$tX/2NoFD.zS.vg8nF24/2e3k5JR0HZAdcgdZtpA2c9d8SFPmGc2bq',
 'Ranjit', 'Singh',
 'ranjit.singh@moghul.ub.ac.bw', 5, 'manager'),

('moghul_staff',
 '$2y$12$tX/2NoFD.zS.vg8nF24/2e3k5JR0HZAdcgdZtpA2c9d8SFPmGc2bq',
 'Priya', 'Naidoo',
 'priya.naidoo@moghul.ub.ac.bw', 5, 'staff'),

-- Eastern Restaurant (outlet 6)
('eastern_mgr',
 '$2y$12$tX/2NoFD.zS.vg8nF24/2e3k5JR0HZAdcgdZtpA2c9d8SFPmGc2bq',
 'Wei', 'Zhang',
 'wei.zhang@eastern.ub.ac.bw', 6, 'manager'),

('eastern_staff',
 '$2y$12$tX/2NoFD.zS.vg8nF24/2e3k5JR0HZAdcgdZtpA2c9d8SFPmGc2bq',
 'Mei', 'Lim',
 'mei.lim@eastern.ub.ac.bw', 6, 'staff'),

-- Gaff Kan (outlet 7)
('gaffkan_mgr',
 '$2y$12$tX/2NoFD.zS.vg8nF24/2e3k5JR0HZAdcgdZtpA2c9d8SFPmGc2bq',
 'Boitumelo', 'Kgaswane',
 'boitumelo.kgaswane@gaffkan.ub.ac.bw', 7, 'manager'),

('gaffkan_staff',
 '$2y$12$tX/2NoFD.zS.vg8nF24/2e3k5JR0HZAdcgdZtpA2c9d8SFPmGc2bq',
 'Kenanao', 'Ramotse',
 'kenanao.ramotse@gaffkan.ub.ac.bw', 7, 'staff');

-- ============================================================
-- C. DELIVERY DRIVER ACCOUNTS
--    password: Driver@1234
--    hash: $2y$12$4Vj7.ymj6V80sYaxkh8RLeTmuRnbXmMLgg63Yh/tyX8KVPeQV3iZy
--
--    Vehicles: bicycle, scooter, motorcycle, car, walking
--    Service area: University of Botswana, Gaborone
-- ============================================================
INSERT IGNORE INTO drivers
  (id, full_name, phone, email, vehicle_type, verification_status, api_token_hash)
VALUES
(1, 'Goitsemang Tshosa',
    '+267 72 101 001',
    'goitsemang.tshosa@driver.ubfood.bw',
    'bicycle',     'approved',
    -- token: drv_tok_goitsemang_001  (SHA-256 placeholder; real token issued by admin panel)
    '$2y$12$4Vj7.ymj6V80sYaxkh8RLeTmuRnbXmMLgg63Yh/tyX8KVPeQV3iZy'),

(2, 'Mpho Sebego',
    '+267 73 202 002',
    'mpho.sebego@driver.ubfood.bw',
    'scooter',     'approved',
    '$2y$12$4Vj7.ymj6V80sYaxkh8RLeTmuRnbXmMLgg63Yh/tyX8KVPeQV3iZy'),

(3, 'Tshepiso Molefe',
    '+267 74 303 003',
    'tshepiso.molefe@driver.ubfood.bw',
    'motorcycle',  'approved',
    '$2y$12$4Vj7.ymj6V80sYaxkh8RLeTmuRnbXmMLgg63Yh/tyX8KVPeQV3iZy'),

(4, 'Kelebogile Sento',
    '+267 75 404 004',
    'kelebogile.sento@driver.ubfood.bw',
    'car',         'approved',
    '$2y$12$4Vj7.ymj6V80sYaxkh8RLeTmuRnbXmMLgg63Yh/tyX8KVPeQV3iZy'),

(5, 'Neo Ditsele',
    '+267 71 505 005',
    'neo.ditsele@driver.ubfood.bw',
    'walking',     'approved',
    '$2y$12$4Vj7.ymj6V80sYaxkh8RLeTmuRnbXmMLgg63Yh/tyX8KVPeQV3iZy');

-- Driver availability rows
INSERT IGNORE INTO driver_availability
  (driver_id, is_online, current_lat, current_lng)
VALUES
(1, 0, -24.6617, 25.9326),
(2, 0, -24.6617, 25.9326),
(3, 0, -24.6617, 25.9326),
(4, 0, -24.6617, 25.9326),
(5, 0, -24.6617, 25.9326);

-- ============================================================
-- D. CUSTOMER ACCOUNTS
--    password: Student@1234
--    hash: $2y$12$C5J/54ZH8sZRz7h717278e02eFNnNqdryAGtss5Hq7EOBG8dVUaYG
--
--    Mix: 6 students + 4 staff
--    tshepo_m, bontle_k, kagiso_d already exist (from schema.sql)
-- ============================================================
INSERT IGNORE INTO customers
  (username, password_hash, first_name, last_name, email,
   phone, account_type, student_id, work_id)
VALUES
-- ── Students ────────────────────────────────────────────────
('lesego_b',
 '$2y$12$C5J/54ZH8sZRz7h717278e02eFNnNqdryAGtss5Hq7EOBG8dVUaYG',
 'Lesego', 'Bogopa',
 'lesego.bogopa@ub.ac.bw', '+267 71 601 001',
 'student', 'UB20230101', NULL),

('keabetswe_n',
 '$2y$12$C5J/54ZH8sZRz7h717278e02eFNnNqdryAGtss5Hq7EOBG8dVUaYG',
 'Keabetswe', 'Ntshimologo',
 'keabetswe.n@ub.ac.bw', '+267 72 602 002',
 'student', 'UB20240078', NULL),

('refilwe_s',
 '$2y$12$C5J/54ZH8sZRz7h717278e02eFNnNqdryAGtss5Hq7EOBG8dVUaYG',
 'Refilwe', 'Sithole',
 'refilwe.sithole@ub.ac.bw', '+267 73 603 003',
 'student', 'UB20220156', NULL),

('oarabile_m',
 '$2y$12$C5J/54ZH8sZRz7h717278e02eFNnNqdryAGtss5Hq7EOBG8dVUaYG',
 'Oarabile', 'Mokgosi',
 'oarabile.mokgosi@ub.ac.bw', '+267 74 604 004',
 'student', 'UB20250032', NULL),

('thato_r',
 '$2y$12$C5J/54ZH8sZRz7h717278e02eFNnNqdryAGtss5Hq7EOBG8dVUaYG',
 'Thato', 'Ramoroka',
 'thato.ramoroka@ub.ac.bw', '+267 75 605 005',
 'student', 'UB20240199', NULL),

('mpho_d',
 '$2y$12$C5J/54ZH8sZRz7h717278e02eFNnNqdryAGtss5Hq7EOBG8dVUaYG',
 'Mpho', 'Dikgole',
 'mpho.dikgole@ub.ac.bw', '+267 71 606 006',
 'student', 'UB20210309', NULL),

-- ── Staff members ────────────────────────────────────────────
('dr_seele',
 '$2y$12$C5J/54ZH8sZRz7h717278e02eFNnNqdryAGtss5Hq7EOBG8dVUaYG',
 'Boiki', 'Seele',
 'b.seele@ub.ac.bw', '+267 72 701 001',
 'staff', NULL, 'UB-STAFF-0142'),

('lect_phiri',
 '$2y$12$C5J/54ZH8sZRz7h717278e02eFNnNqdryAGtss5Hq7EOBG8dVUaYG',
 'Grace', 'Phiri',
 'g.phiri@ub.ac.bw', '+267 73 702 002',
 'staff', NULL, 'UB-STAFF-0278'),

('admin_support',
 '$2y$12$C5J/54ZH8sZRz7h717278e02eFNnNqdryAGtss5Hq7EOBG8dVUaYG',
 'Moshe', 'Kgomanyane',
 'm.kgomanyane@ub.ac.bw', '+267 74 703 003',
 'staff', NULL, 'UB-STAFF-0391'),

('itservices_k',
 '$2y$12$C5J/54ZH8sZRz7h717278e02eFNnNqdryAGtss5Hq7EOBG8dVUaYG',
 'Keitumetse', 'Modiegi',
 'k.modiegi@ub.ac.bw', '+267 75 704 004',
 'staff', NULL, 'UB-STAFF-0445');

-- ============================================================
-- E. ADMIN ACCOUNT (improved, with first/last name)
--    password: Admin@UBFood26
--    hash: $2y$12$xCRknt3AZmtYyH55vFyRdukhb/kfjPYJSuB/wMCCOpH8pKQo9y7E2
-- ============================================================
INSERT IGNORE INTO admins
  (username, password_hash, email)
VALUES
('ubfood_admin',
 '$2y$12$xCRknt3AZmtYyH55vFyRdukhb/kfjPYJSuB/wMCCOpH8pKQo9y7E2',
 'ubfood.admin@ub.ac.bw');

-- NOTE: The admins table does not have first_name/last_name columns.
--       Documented name: Tebogo Osei-Mensah (UB Food Systems Admin)

-- ============================================================
-- F. NOTIFICATION PREFERENCES (default rows for new customers)
-- ============================================================
INSERT IGNORE INTO notification_preferences
  (user_role, user_identifier, email_enabled, sms_enabled, push_enabled, marketing_enabled)
VALUES
('customer', 'lesego_b',      1, 1, 1, 1),
('customer', 'keabetswe_n',   1, 1, 1, 0),
('customer', 'refilwe_s',     1, 0, 1, 1),
('customer', 'oarabile_m',    1, 1, 0, 0),
('customer', 'thato_r',       1, 1, 1, 1),
('customer', 'mpho_d',        1, 0, 1, 0),
('customer', 'dr_seele',      1, 1, 1, 0),
('customer', 'lect_phiri',    1, 1, 1, 0),
('customer', 'admin_support', 1, 1, 0, 0),
('customer', 'itservices_k',  1, 0, 1, 0);

-- ============================================================
-- SUMMARY OF DEMO CREDENTIALS
-- ============================================================
-- CUSTOMER APP   : /frontend/customer/index.html
--   tshepo_m     / Student@1234   (student, existing)
--   bontle_k     / Student@1234   (student, existing)
--   kagiso_d     / Student@1234   (student, existing)
--   lesego_b     / Student@1234   (student)
--   keabetswe_n  / Student@1234   (student)
--   refilwe_s    / Student@1234   (student)
--   oarabile_m   / Student@1234   (student)
--   thato_r      / Student@1234   (student)
--   mpho_d       / Student@1234   (student)
--   dr_seele     / Student@1234   (staff)
--   lect_phiri   / Student@1234   (staff)
--   admin_support/ Student@1234   (staff)
--   itservices_k / Student@1234   (staff)
--
-- VENDOR DASHBOARD: /frontend/dashboard/index.html
--   sefalana_staff    / Staff@1234  (existing, outlet 1 — Sefalana)
--   sefalana_mgr      / Vendor@1234 (new,      outlet 1 — Sefalana)
--   bwp_manager       / Staff@1234  (existing, outlet 2 — Blue & White Plate)
--   hotspot_staff     / Staff@1234  (existing, outlet 3 — Hot Spot)
--   exec_catering_mgr / Vendor@1234 (new,      outlet 4 — Executive Catering)
--   moghul_mgr        / Vendor@1234 (new,      outlet 5 — Moghul Catering)
--   eastern_mgr       / Vendor@1234 (new,      outlet 6 — Eastern Restaurant)
--   gaffkan_mgr       / Vendor@1234 (new,      outlet 7 — Gaff Kan)
--
-- ADMIN PANEL   : /frontend/admin/index.html
--   admin         / Admin@1234      (legacy account — hash fixed by this migration)
--   ubfood_admin  / Admin@UBFood26  (recommended account)
--
-- DRIVER PANEL  : /frontend/driver/index.html
--   Driver ID: 1, Token: use API token hash above (login via admin panel)
--   Goitsemang Tshosa  — bicycle
--   Mpho Sebego        — scooter
--   Tshepiso Molefe    — motorcycle
--   Kelebogile Sento   — car
--   Neo Ditsele        — walking
-- ============================================================
