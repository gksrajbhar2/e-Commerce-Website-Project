<?php
/**
 * General-purpose helper functions used across the site.
 */

/** Escape a string for safe HTML output. */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Format a number as Nepali Rupees, e.g. "Rs. 1,250.00". */
function money(float $amount): string
{
    return 'Rs. ' . number_format($amount, 2);
}

/** First letter of a name for avatars — UTF-8 safe, no mbstring extension needed. */
function name_initial(string $name): string
{
    $name = trim($name);
    if ($name !== '' && preg_match('/^./us', $name, $m)) {
        return strtoupper($m[0]);
    }
    return '?';
}

/** Turn "Wireless Headphones" into "wireless-headphones". */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

/** A random, URL-safe, eSewa-compatible transaction id (alphanumeric + hyphen only). */
function generate_transaction_uuid(): string
{
    return date('Ymd-His') . '-' . bin2hex(random_bytes(4));
}

/** Fetch all active categories. */
function get_categories(): array
{
    $stmt = db()->query('SELECT * FROM categories ORDER BY name ASC');
    return $stmt->fetchAll();
}

/** Only categories that currently have at least one visible product (used for the shop filter chips). */
function get_categories_with_products(): array
{
    $stmt = db()->query(
        'SELECT c.* FROM categories c
         WHERE EXISTS (SELECT 1 FROM products p WHERE p.category_id = c.id AND p.is_active = 1)
         ORDER BY c.name ASC'
    );
    return $stmt->fetchAll();
}

/** Fetch active products, optionally filtered by category slug and/or search term. */
function get_products(?string $categorySlug = null, ?string $search = null): array
{
    $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.is_active = 1';
    $params = [];

    if ($categorySlug) {
        $sql .= ' AND c.slug = :slug';
        $params['slug'] = $categorySlug;
    }
    if ($search) {
        $sql .= ' AND p.name LIKE :search';
        $params['search'] = '%' . $search . '%';
    }

    $sql .= ' ORDER BY p.created_at DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Fetch one active product by id, or null if not found. */
function get_product(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE id = :id AND is_active = 1');
    $stmt->execute(['id' => $id]);
    $product = $stmt->fetch();
    return $product ?: null;
}

/** URL to a product's image, falling back to the placeholder graphic. */
function product_image_url(?string $filename): string
{
    $filename = $filename ?: 'no-image.svg';
    $path = __DIR__ . '/../assets/images/products/' . $filename;
    if ($filename !== 'no-image.svg' && !is_file($path)) {
        $filename = 'no-image.svg';
    }
    $folder = $filename === 'no-image.svg' ? 'assets/images' : 'assets/images/products';
    return base_path($folder . '/' . $filename);
}

/**
 * Build a URL relative to the site root, e.g. base_path('cart.php').
 * Assumes the project folder is the web server's document root (see
 * README.md) so this works the same from any sub-folder (admin/, auth/...).
 */
function base_path(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}

/** Redirect the browser to a site-relative path and stop execution. */
function redirect(string $path): void
{
    header('Location: ' . base_path($path));
    exit;
}

/** Flash message helpers (one-time messages shown after a redirect). */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/**
 * ------------------------------------------------------------------
 * Cart (session-based — no login required to add items to the cart)
 * ------------------------------------------------------------------
 */

/** Full cart contents joined with live product data. Drops any product that's gone/inactive. */
function get_cart_items(): array
{
    if (empty($_SESSION['cart'])) {
        return [];
    }

    $ids = array_map('intval', array_keys($_SESSION['cart']));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND is_active = 1");
    $stmt->execute($ids);
    $products = $stmt->fetchAll();

    $items = [];
    foreach ($products as $product) {
        $qty = max(1, (int) $_SESSION['cart'][$product['id']]);
        $qty = min($qty, $product['stock'] > 0 ? $product['stock'] : $qty); // don't exceed stock
        $items[] = [
            'product'  => $product,
            'quantity' => $qty,
            'subtotal' => $qty * (float) $product['price'],
        ];
    }
    return $items;
}

function get_cart_total(): float
{
    $total = 0.0;
    foreach (get_cart_items() as $item) {
        $total += $item['subtotal'];
    }
    return $total;
}

function get_cart_count(): int
{
    return array_sum(array_map('intval', $_SESSION['cart'] ?? []));
}

function add_to_cart(int $productId, int $quantity = 1): void
{
    $quantity = max(1, $quantity);
    $_SESSION['cart'][$productId] = ($_SESSION['cart'][$productId] ?? 0) + $quantity;
}

function update_cart_quantity(int $productId, int $quantity): void
{
    if ($quantity <= 0) {
        unset($_SESSION['cart'][$productId]);
    } else {
        $_SESSION['cart'][$productId] = $quantity;
    }
}

function remove_from_cart(int $productId): void
{
    unset($_SESSION['cart'][$productId]);
}

function clear_cart(): void
{
    $_SESSION['cart'] = [];
}

/** Flat delivery fee, free above a threshold — tweak as needed. */
function calculate_delivery_charge(float $subtotal): float
{
    if ($subtotal <= 0) {
        return 0.0;
    }
    return $subtotal >= 5000 ? 0.0 : 100.0;
}

/**
 * ------------------------------------------------------------------
 * CSRF protection (used by the profile forms)
 * ------------------------------------------------------------------
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && $sent !== '' && hash_equals($_SESSION['csrf_token'] ?? '', $sent);
}
