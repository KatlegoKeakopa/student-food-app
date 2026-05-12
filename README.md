# UB Food — University of Botswana Campus Dining App

A full-stack food ordering platform for the University of Botswana's Student Centre. Students and staff browse campus food outlets, place pickup or delivery orders, and track them in real time. Outlet staff manage incoming orders and menus; admins oversee the entire system; delivery drivers receive and action dispatch offers.

## Tech Stack

- **Backend**: PHP 7.4+, PDO, Apache + mod_rewrite
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **Frontend**: Vanilla HTML/CSS/JavaScript (single-page app per role)
- **Font**: Bold Futura font stack (Futura PT → Avenir Next → Montserrat → Arial)
- **Auth**: JWT tokens (Bearer header)
- **Maps**: Google Maps API (optional, delivery location picker)
- **Workers**: DB-backed queue worker for notifications

## Project Structure

```
student-food-app/
├── backend/
│   ├── api/
│   │   ├── auth/           login · signup · logout
│   │   ├── admin.php       system stats, applications, reconciliation
│   │   ├── addresses.php   saved delivery addresses
│   │   ├── devices.php     push device token registration
│   │   ├── dispatch.php    driver dispatch & delivery offers
│   │   ├── drivers.php     driver availability, GPS, earnings
│   │   ├── driver_applications.php
│   │   ├── favorites.php   outlet favourites
│   │   ├── legal.php       policies, consent, data requests
│   │   ├── menu.php        public menu browse
│   │   ├── menu_manage.php staff menu CRUD
│   │   ├── notifications.php
│   │   ├── orders.php
│   │   ├── outlets.php
│   │   ├── payments.php    intents, webhooks, ledger
│   │   ├── promotions.php
│   │   ├── ratings.php
│   │   ├── refunds.php
│   │   ├── support.php
│   │   └── vendor_applications.php
│   ├── workers/notification_worker.php
│   ├── config/db.php       DB connection + JWT config
│   ├── includes/helpers.php
│   └── .htaccess
├── frontend/
│   ├── customer/           Responsive customer SPA (mobile + desktop)
│   │   ├── index.html
│   │   ├── css/app.css
│   │   ├── js/api.js
│   │   └── assets/ub-campus-food-hero.png
│   ├── dashboard/          Outlet vendor/staff dashboard
│   │   └── index.html
│   ├── admin/              Admin control panel
│   │   └── index.html
│   └── driver/             Driver dispatch interface
│       └── index.html
├── database/
│   ├── schema.sql          Full schema + all seed data
│   └── migrations/
│       ├── 2026_05_marketplace_lifecycle.sql
│       └── 2026_05_seed_accounts.sql  ← additive migration for existing DBs
├── .env / .env.example
└── SETUP.txt
```

---

## Setup

### 1. Database

Import `database/schema.sql` into MySQL/MariaDB. This creates all tables and seeds realistic UB Student Centre demo data including outlets, menus, sample orders, customers, vendor staff, drivers, and admin accounts.

**Existing database (already imported schema.sql)?** Run the additive migration instead:

```bash
mysql -u root -p student_food_app < database/migrations/2026_05_seed_accounts.sql
```

### 2. Environment

Copy `.env.example` to `.env` and fill in your values:

```ini
APP_ENV=development
DB_HOST=localhost
DB_USER=student_food_app
DB_PASS=your_password
DB_NAME=student_food_app
JWT_SECRET=replace_with_at_least_32_random_characters
JWT_EXPIRY=3600
CORS_ALLOWED_ORIGINS=http://localhost,http://127.0.0.1
PAYMENT_PROVIDER=sandbox
PAYMENT_PROVIDER_ENABLED=false
EMAIL_PROVIDER=sendgrid
SMS_PROVIDER=orange_botswana
PUSH_PROVIDER=fcm
```

### 3. Apache

Enable `mod_rewrite` and set `AllowOverride All` for the project directory so `.htaccess` routing works.

> **Important**: The frontend must be served through Apache/PHP. Opening `index.html` via `file://` or a plain static server will break all API calls. Use XAMPP/WAMP/Laragon and open via `http://localhost/...`.

### 4. Google Maps (optional)

Add your API key to `frontend/customer/index.html`:

```javascript
window.APP_CONFIG = { googleMapsApiKey: 'YOUR_KEY_HERE', apiBase: '../../backend/api' };
```

The delivery map picker is optional. Without a key the delivery address field remains a plain text input.

### 5. Notification Worker

```bash
composer run worker:notifications
```

Run from cron, Supervisor, or systemd. Integrates with SendGrid, Orange Botswana SMS, and FCM/APNs.

---

## Accessing the App

| Role | URL |
|------|-----|
| **Customer** | `http://localhost/student-food-app/frontend/customer/index.html` |
| **Vendor / Outlet Staff** | `http://localhost/student-food-app/frontend/dashboard/index.html` |
| **Admin** | `http://localhost/student-food-app/frontend/admin/index.html` |
| **Driver** | `http://localhost/student-food-app/frontend/driver/index.html` |

> All pages must be served via Apache. Static-only previews (e.g. GitHub Pages, VS Code Live Server) will show the UI but API calls will fail with 404.

---

## Demo Accounts

### Admin

| Username | Password | Notes |
|----------|----------|-------|
| `ubfood_admin` | `Admin@UBFood26` | **Recommended** — Tebogo Osei-Mensah, UB Food Systems Admin |
| `admin` | `Admin@1234` | Legacy account |

### Vendor / Outlet Staff  *(password: `Vendor@1234`)*

| Username | Outlet | Role |
|----------|--------|------|
| `sefalana_mgr` | Sefalana Bakery & Café | Manager |
| `exec_catering_mgr` | Executive Catering | Manager |
| `exec_catering_staff` | Executive Catering | Staff |
| `moghul_mgr` | Moghul Catering | Manager |
| `moghul_staff` | Moghul Catering | Staff |
| `eastern_mgr` | Eastern Restaurant | Manager |
| `eastern_staff` | Eastern Restaurant | Staff |
| `gaffkan_mgr` | Gaff Kan | Manager |
| `gaffkan_staff` | Gaff Kan | Staff |

**Original staff accounts** *(password: `Staff@1234`)*

| Username | Outlet | Role |
|----------|--------|------|
| `sefalana_staff` | Sefalana Bakery & Café | Staff |
| `bwp_manager` | Blue & White Plate | Manager |
| `hotspot_staff` | Hot Spot Food Court | Staff |

### Customers  *(password: `Student@1234`)*

| Username | Name | Type |
|----------|------|------|
| `tshepo_m` | Tshepo Modise | Student |
| `bontle_k` | Bontle Kgosi | Student |
| `kagiso_d` | Kagiso Ditsele | Student |
| `lesego_b` | Lesego Bogopa | Student |
| `keabetswe_n` | Keabetswe Ntshimologo | Student |
| `refilwe_s` | Refilwe Sithole | Student |
| `oarabile_m` | Oarabile Mokgosi | Student |
| `thato_r` | Thato Ramoroka | Student |
| `mpho_d` | Mpho Dikgole | Student |
| `dr_seele` | Boiki Seele | Staff |
| `lect_phiri` | Grace Phiri | Staff |
| `admin_support` | Moshe Kgomanyane | Staff |
| `itservices_k` | Keitumetse Modiegi | Staff |

### Delivery Drivers

Drivers authenticate via driver ID and API token issued from the admin panel after approval.

| ID | Name | Vehicle | Status |
|----|------|---------|--------|
| 1 | Goitsemang Tshosa | Bicycle | Approved |
| 2 | Mpho Sebego | Scooter | Approved |
| 3 | Tshepiso Molefe | Motorcycle | Approved |
| 4 | Kelebogile Sento | Car | Approved |
| 5 | Neo Ditsele | Walking | Approved |

> Issue driver tokens from the admin panel: Admin → Drivers → Approve & Generate Token.

---

## Navigation Notes (Customer App)

- **"Order food now"** on the landing page opens the full customer app (guest mode, no login required for browsing).
- The **UB Food logo** in the app header and the **Home** button (⌂) both return to the landing page without destroying your session or cart.
- If you were logged in, returning to the landing page and clicking "Order food now" again re-enters the app without re-authentication.
- Signing out from the Profile screen clears the session and returns to the landing page.

---

## Desktop Layout

The customer app now supports full desktop/laptop layouts:

- **Desktop (>1024px)**: full-width up to 1280px, 3-column outlet grid, 3-column menu grid, proper side nav spacing.
- **Tablet (641–1024px)**: 2-column outlet and menu grids.
- **Mobile (≤640px)**: single-column, ≥44px touch targets, preserved bottom navigation.

Admin, vendor dashboard, and driver pages retain their existing sidebar layout which already targets desktop screens.

---

## Order Lifecycle

```
pending_vendor → accepted → preparing → ready_for_pickup
  → driver_assigned → picked_up → delivered_pending_confirmation → completed
```

Terminal: `declined_by_vendor` | `cancelled`

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/signup` | Register a customer |
| POST | `/api/auth/login` | Login (any role) |
| POST | `/api/auth/logout` | Logout |
| GET | `/api/outlets` | List outlets with ratings |
| GET | `/api/menu` | Get menu items (filterable) |
| POST | `/api/menu_manage` | Staff: add/edit menu items |
| GET/POST | `/api/favorites` | Get or toggle favourite outlets |
| GET/POST | `/api/addresses` | Manage delivery addresses |
| POST | `/api/promotions/apply` | Validate and preview promo discounts |
| POST | `/api/orders` | Place an order |
| GET | `/api/orders` | View orders (scoped by role) |
| PATCH | `/api/orders/{id}` | Update order status |
| POST | `/api/orders/{id}/vendor/accept` | Vendor accepts pending order |
| POST | `/api/orders/{id}/vendor/decline` | Vendor declines pending order |
| POST | `/api/orders/{id}/confirm-received` | Customer confirms delivery receipt |
| GET | `/api/orders/{id}/tracking` | Live delivery tracking |
| POST | `/api/payments/intents` | Create payment intent |
| POST | `/api/payments/{id}/confirm` | Confirm sandbox payment |
| GET | `/api/payments/admin` | Admin payment ledger |
| GET/POST | `/api/refunds` | Admin refund management |
| GET/POST | `/api/ratings` | View or submit ratings |
| GET/POST | `/api/notifications` | Notifications + mark-as-read |
| GET/PATCH | `/api/notifications/preferences` | Notification preferences |
| POST | `/api/devices/register` | Register push device token |
| POST | `/api/dispatch/assign` | Admin driver assignment |
| GET | `/api/dispatch/offers` | Driver available delivery offers |
| POST | `/api/dispatch/accept` | Driver accepts offer |
| POST | `/api/dispatch/reject` | Driver rejects offer |
| PATCH | `/api/deliveries/{id}/status` | Driver/admin delivery status |
| POST | `/api/drivers/availability` | Driver online/offline |
| POST | `/api/drivers/location` | Driver GPS heartbeat |
| GET | `/api/drivers/earnings` | Driver earnings ledger |
| GET | `/api/legal/documents` | Active legal policy versions |
| POST | `/api/legal/consents` | Record policy consent |
| POST | `/api/legal/data-requests` | Submit privacy/data request |
| POST | `/api/vendor-applications` | Submit vendor application |
| POST | `/api/driver-applications` | Submit driver application |
| GET | `/api/admin` | System overview and reports |
| POST | `/api/admin/vendor-applications/{id}/approve` | Approve vendor |
| POST | `/api/admin/driver-applications/{id}/approve` | Approve driver |
| POST | `/api/support` | Submit support ticket |

---

## Common Issues

**API returns 404**: The app is likely being opened via `file://` or a plain static server. Serve through Apache (`http://localhost/student-food-app/…`). Ensure `mod_rewrite` is enabled and `AllowOverride All` is set.

**Font not loading**: Montserrat loads via Google Fonts CDN. On offline/intranet setups, the browser falls back gracefully to Avenir Next, Century Gothic, or Arial — all geometric sans-serif fonts compatible with the Futura design intent.

**Driver login not working**: Driver authentication uses an ID + one-time API token generated from the admin panel. The token hash in the seed data is a placeholder. Issue real tokens via Admin → Drivers.
