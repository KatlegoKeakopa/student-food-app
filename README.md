# Student Centre Food Ordering App

A full-stack food ordering platform for the University of Botswana's Student Centre. Students can browse campus food outlets, place pickup or delivery orders, and track them in real time. Outlet staff manage incoming orders and menus; admins oversee the entire system.

## Tech Stack

- **Backend**: PHP 7.4+, PDO, Apache + mod_rewrite
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **Frontend**: Vanilla HTML/CSS/JavaScript (SPA per role)
- **Auth**: JWT tokens
- **Maps**: Google Maps API (delivery tracking)

## Project Structure

```
student-food-app/
├── backend/
│   ├── api/
│   │   ├── auth/          # login, signup, logout
│   │   ├── admin.php
│   │   ├── addresses.php
│   │   ├── favorites.php
│   │   ├── menu.php / menu_manage.php
│   │   ├── notifications.php
│   │   ├── orders.php
│   │   ├── outlets.php
│   │   ├── promotions.php
│   │   ├── ratings.php
│   │   └── support.php
│   ├── config/db.php      # DB connection & JWT config
│   ├── includes/helpers.php
│   └── .htaccess
├── frontend/
│   ├── customer/          # Mobile-responsive customer app
│   ├── dashboard/         # Outlet staff dashboard
│   └── admin/             # Admin panel
├── database/schema.sql
├── .env.example
└── SETUP.txt
```

## Setup

### 1. Database

Import `database/schema.sql` into MySQL/MariaDB. This creates 9 tables and seeds 3 outlets with sample data.

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
```

### 3. Apache

Enable `mod_rewrite` and set `AllowOverride All` for the project directory so `.htaccess` routing works.

### 4. Google Maps API

Add your API key to `frontend/customer/index.html`:

```javascript
window.APP_CONFIG = { googleMapsApiKey: 'YOUR_KEY_HERE' };
```

### 5. API Base URL

If deploying to a subfolder, update `API_BASE` in `frontend/customer/js/api.js` and the equivalent in the dashboard and admin HTML files.

## Accessing the App

| Role | URL |
|------|-----|
| Customer | `http://localhost/student-food-app/frontend/customer/index.html` |
| Outlet Staff | `http://localhost/student-food-app/frontend/dashboard/index.html` |
| Admin | `http://localhost/student-food-app/frontend/admin/index.html` |

## Default Accounts

| Role | Username | Password |
|------|----------|----------|
| Admin | `admin` | `Admin@1234` |
| Sefalana Bakery Staff | `sefalana_staff` | `Staff@1234` |
| Blue & White Plate Staff | `bwp_manager` | `Staff@1234` |
| Hot Spot Food Court Staff | `hotspot_staff` | `Staff@1234` |

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/signup` | Register a customer |
| POST | `/api/auth/login` | Login (any role) |
| POST | `/api/auth/logout` | Logout |
| GET | `/api/outlets` | List outlets with ratings |
| GET | `/api/menu` | Get menu items (filter by category) |
| POST | `/api/menu_manage` | Staff: add/edit menu items |
| GET/POST | `/api/favorites` | Get or toggle favorite outlets |
| POST | `/api/orders` | Place an order |
| GET | `/api/orders` | View orders (scoped by role) |
| PUT | `/api/orders` | Update order status |
| GET/POST | `/api/ratings` | View or submit ratings |
| GET/POST | `/api/notifications` | Get notifications / mark as read |
| GET | `/api/admin` | System overview & reports |
| GET/POST | `/api/addresses` | Manage delivery addresses |
| POST | `/api/support` | Submit a support ticket |
| GET | `/api/promotions` | View active promotions |
