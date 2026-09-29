# CircuitHub — Electronics Store with eSewa Payments

A complete e-commerce website built with **plain HTML, CSS, JavaScript and PHP** (no
framework required) — a storefront, cart, checkout, customer accounts, an admin panel,
and a working **eSewa ePay v2** payment integration with cryptographic verification.

Every request in this codebase has been **tested end-to-end** against a real
MySQL/MariaDB database and a running PHP server (browsing → cart → checkout → a signed
eSewa payment callback → order confirmation → admin dashboard), including negative tests
(a tampered payment signature is correctly rejected and never marks an order as paid).

---

## Folder structure

```
esewa-ecommerce/
├── admin/                  Admin panel (products, orders, dashboard)
│   ├── includes/           Admin-only header/footer
│   ├── dashboard.php
│   ├── login.php / logout.php
│   ├── orders.php / order_detail.php
│   └── products.php / product_form.php / product_delete.php
├── api/
│   └── add_to_cart.php     AJAX endpoint used by the "Add to cart" buttons
├── assets/
│   ├── css/                style.css (storefront), admin.css (admin panel)
│   ├── js/                 main.js, cart.js
│   └── images/             no-image.svg placeholder + products/ (uploads)
├── auth/                   Customer register / login / logout
├── config/
│   ├── database.php        DB credentials + PDO connection helper
│   └── esewa.php           eSewa credentials, URLs, environment switch
├── database/
│   └── schema.sql           Full schema + seed data (run this first)
├── esewa/                  The payment integration
│   ├── helpers.php         HMAC signing / verification / status-check
│   ├── initiate.php        Builds the signed form → redirects to eSewa
│   ├── success.php         Verifies the callback, marks orders paid
│   └── failure.php         Handles cancelled/failed payments
├── includes/
│   ├── bootstrap.php       Included by every page: session, config, helpers
│   ├── functions.php       Helpers: products, cart, money formatting, etc.
│   ├── auth.php            Login/session helpers (shared by site + admin)
│   ├── header.php / footer.php
├── index.php                Homepage / product listing / search
├── product.php               Product detail page
├── cart.php                  Cart page
├── checkout.php               Shipping form + order creation
├── order_success.php           Order confirmation
├── orders.php                  Customer's order history (requires login)
├── order.php                   Single order detail + progress tracker (own orders only)
└── profile.php                 Customer profile: edit details, change password, recent orders
```

---

## Requirements

- PHP **8.1+** with the `pdo_mysql` and `curl` extensions enabled
- MySQL or MariaDB
- Any web server (Apache, Nginx) — or just PHP's built-in server for local testing

## Setup

**1. Import the database.**

```bash
mysql -u root -p < database/schema.sql
```

This creates the `esewa_ecommerce` database, all tables, the electronics categories and
demo products, and one admin account. The file is **safe to re-import** — it resets every
table — so import it again any time you want a clean store (this erases existing data).

**2. Configure the database connection** in `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'esewa_ecommerce');
define('DB_USER', 'root');
define('DB_PASS', '');
```

**3. Point the site at itself** in `config/esewa.php` — update `BASE_URL` to wherever
the site is actually reachable (this is what eSewa redirects the customer's browser
back to after payment, so it must be a real, browser-reachable URL once deployed —
not relevant only when testing on `localhost`):

```php
define('BASE_URL', 'http://localhost:8000');
```

**4. Run it.** For local testing, point PHP's built-in server at the project **root**
(important — the app assumes it's hosted at the domain root, e.g. `/index.php`,
`/admin/login.php`, not `/some-subfolder/index.php`):

```bash
php -S localhost:8000
```

Then visit `http://localhost:8000`. For a real deployment, point your Apache/Nginx
document root at this folder the same way.

## Logging in

| | Email | Password |
|---|---|---|
| **Admin panel** (`/admin/login.php`) | `admin@example.com` | `admin123` |
| **Customer account** | *(register your own at `/auth/register.php`)* | |

**Change the admin password** after your first login (there's no self-serve UI for
this yet — update the `users` table directly, e.g. `UPDATE users SET password =
'<new bcrypt hash>' WHERE email = 'admin@example.com'`, generated with PHP's
`password_hash()`).

---

## How the eSewa payment integration works

This implements **eSewa ePay v2**, the payment flow documented at
[developer.esewa.com.np](https://developer.esewa.com.np/pages/Epay-V2):

1. **`checkout.php`** creates an `orders` row (status `pending`) and stores its ID in
   `$_SESSION['pending_order_id']` — never in the URL, so a visitor can't tamper with
   or guess which order to act on.
2. **`esewa/initiate.php`** reads that order from the session, builds the required
   fields (`amount`, `tax_amount`, `total_amount`, `transaction_uuid`, etc.), signs
   `total_amount,transaction_uuid,product_code` with **HMAC-SHA256** using your secret
   key, and auto-submits a form to eSewa's payment page.
3. The customer pays on eSewa's own site (not on yours — card/PIN details never touch
   this codebase).
4. eSewa redirects the browser back to **`esewa/success.php`** (or `failure.php`) with
   a base64-encoded, signed JSON payload.
5. `success.php` **recomputes the signature itself** and rejects anything that doesn't
   match with `hash_equals()` — before trusting a single field in the response. Only
   then does it check the order exists, the amount matches, and the status is
   `COMPLETE`. It also makes a best-effort **server-to-server status check** call to
   eSewa as a second confirmation. Only after all of that does it mark the order paid
   and decrement stock. It's idempotent — refreshing the success page twice won't
   double-charge stock.

This signing/verification logic was checked against eSewa's official worked example
and cross-verified against an independently published, tested open-source SDK to make
sure the exact message format (`field=value` pairs joined with commas, in the order
given by `signed_field_names`) is right.

### Test credentials (sandbox)

`config/esewa.php` ships pointed at eSewa's public **UAT/sandbox** environment by
default (`ESEWA_ENV = 'test'`) using eSewa's own published test merchant code —
no real money moves and no account is needed to get these:

- **Merchant/product code:** `EPAYTEST`
- **Secret key:** `8gBm/:&EnhH.1/q`
- **To complete a test payment**, log in on eSewa's sandbox page with either set below.
  eSewa's documentation lists both (on two different pages) and doesn't say which is
  active at any moment — if one is rejected, try the other:

  | | eSewa ID | Password |
  |---|---|---|
  | Set A (ePay v2 page) | `9806800001` (also `…02` to `…05`) | `Nepal@123` |
  | Set B (Test-credentials page) | `9711111111` (also `…12`, `…13`) | `Test@123` |

  Then **MPIN** `1122` and **verification token / OTP** `123456` for both.
  The checkout page shows these too while `ESEWA_ENV` is `'test'`.

### Checking the connection (admin → "eSewa check")

Log in as admin and open **eSewa check** in the sidebar. It runs from *your* machine and
tells you, with plain-English explanations, whether:

- PHP's `curl` and `openssl` extensions are enabled (XAMPP: `php.ini`, remove the `;`
  before `extension=curl` / `extension=openssl`, restart)
- request signing matches eSewa's (verified against a real eSewa reply)
- your machine can reach eSewa's payment and status servers
- `BASE_URL` matches the address you opened the site at (same port!)



### Going live

1. Register as an eSewa merchant at [merchant.esewa.com.np](https://merchant.esewa.com.np)
   and get your real `product_code` and `secret_key`.
2. In `config/esewa.php`, set `ESEWA_ENV = 'live'` and fill in
   `ESEWA_MERCHANT_CODE` / `ESEWA_SECRET_KEY` with the real values.
3. Update `BASE_URL` to your real HTTPS domain.
4. Deploy over **HTTPS** — eSewa's callback carries payment confirmation and should
   never travel over plain HTTP.
5. Turn off PHP error display in production (`display_errors = Off` in `php.ini`) —
   errors already go to `error_log()` throughout this codebase instead of the screen.

---

## Notable implementation details

- **Cart** is session-based (no login required to browse or add to cart) — a
  `$_SESSION['cart']` array of `product_id => quantity`, joined against live product
  data on every read so prices/stock are always current.
- **Guest checkout** is supported; logging in just pre-fills shipping info and enables
  order history.
- **Every database query uses PDO prepared statements** — no string-built SQL anywhere.
- **Passwords** are hashed with PHP's `password_hash()` (bcrypt).
- **Admin and customer accounts** share one `users` table, distinguished by a `role`
  column; `require_admin()` / `require_login()` guard the relevant pages.
- **Product images** are uploaded through the admin panel (validated by real MIME type,
  renamed to a random filename, capped at 4MB) and stored in
  `assets/images/products/`. Products without a photo fall back to
  `assets/images/no-image.svg`.
- Deleting a product from the admin panel **soft-deletes** it (hides it from the store)
  rather than removing the row, so past orders still show accurate line items.

## Customizing

- **Delivery fee:** `calculate_delivery_charge()` in `includes/functions.php` (flat
  Rs. 100, free above Rs. 5,000 — change as you like).
- **Branding/colors/fonts:** CSS custom properties at the top of
  `assets/css/style.css`.
- **Categories & sample products:** edit the seed `INSERT`s at the bottom of
  `database/schema.sql`, or manage them from the admin panel after import.

---

## How accounts behave

- **Customers** register/log in on the storefront, and get a **profile page** (`/profile.php`)
  to edit their name, phone and address, change their password, and see recent orders. Full
  history is at `/orders.php`; each order opens a detail page with items, delivery details and
  a progress tracker.
- **The admin only manages the store.** An admin who opens any storefront page is sent back to
  the dashboard, and the cart/checkout endpoints refuse admin accounts. Log out to test the
  store as a customer.
- **Payment status follows the order.** For cash-on-delivery orders, marking the order
  *Completed* marks the payment *Paid* (and undoing it reverts to Pending). An eSewa order
  can't move past *Pending* until eSewa has actually confirmed the payment.


  IF YOU LOVE THIS PROJECT ,PLEASE HIT STAR AND CLONE TO YOU DEVICE 
