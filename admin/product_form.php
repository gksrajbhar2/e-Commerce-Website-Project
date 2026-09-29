<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$product = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $product = $stmt->fetch();
    if (!$product) {
        set_flash('error', 'Product not found.');
        redirect('admin/products.php');
    }
}

$categories = get_categories();
$errors = [];

$name = $product['name'] ?? '';
$categoryId = $product['category_id'] ?? '';
$description = $product['description'] ?? '';
$price = $product['price'] ?? '';
$stock = $product['stock'] ?? '';
$isActive = $product['is_active'] ?? 1;
$currentImage = $product['image'] ?? 'no-image.svg';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $categoryId = $_POST['category_id'] !== '' ? (int) $_POST['category_id'] : null;
    $description = trim($_POST['description'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $stock = (int) ($_POST['stock'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $imageFilename = $currentImage;

    if ($name === '') $errors[] = 'Product name is required.';
    if ($price <= 0) $errors[] = 'Price must be greater than 0.';
    if ($stock < 0) $errors[] = 'Stock cannot be negative.';

    // Optional image upload.
    if (!empty($_FILES['image_file']['name'])) {
        $file = $_FILES['image_file'];
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload failed. Please try again.';
        } elseif ($file['size'] > 4 * 1024 * 1024) {
            $errors[] = 'Image must be smaller than 4MB.';
        } else {
            $mime = mime_content_type($file['tmp_name']);
            if (!isset($allowed[$mime])) {
                $errors[] = 'Image must be a JPG, PNG, WEBP or GIF file.';
            } else {
                $ext = $allowed[$mime];
                $newFilename = bin2hex(random_bytes(8)) . '.' . $ext;
                $destination = __DIR__ . '/../assets/images/products/' . $newFilename;
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $imageFilename = $newFilename;
                } else {
                    $errors[] = 'Could not save the uploaded image.';
                }
            }
        }
    }

    // Auto-generate a unique slug from the name.
    $baseSlug = slugify($name);
    $slug = $baseSlug;
    if ($baseSlug !== '') {
        $i = 1;
        while (true) {
            $check = db()->prepare('SELECT id FROM products WHERE slug = :slug AND id != :id');
            $check->execute(['slug' => $slug, 'id' => $id ?: 0]);
            if (!$check->fetch()) break;
            $slug = $baseSlug . '-' . (++$i);
        }
    }

    if (empty($errors)) {
        if ($product) {
            $stmt = db()->prepare(
                'UPDATE products SET category_id=:cat, name=:name, slug=:slug, description=:desc,
                    price=:price, stock=:stock, image=:image, is_active=:active WHERE id=:id'
            );
            $stmt->execute([
                'cat' => $categoryId, 'name' => $name, 'slug' => $slug, 'desc' => $description,
                'price' => $price, 'stock' => $stock, 'image' => $imageFilename, 'active' => $isActive, 'id' => $id,
            ]);
            set_flash('success', 'Product updated.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO products (category_id, name, slug, description, price, stock, image, is_active)
                 VALUES (:cat, :name, :slug, :desc, :price, :stock, :image, :active)'
            );
            $stmt->execute([
                'cat' => $categoryId, 'name' => $name, 'slug' => $slug, 'desc' => $description,
                'price' => $price, 'stock' => $stock, 'image' => $imageFilename, 'active' => $isActive,
            ]);
            set_flash('success', 'Product created.');
        }
        redirect('admin/products.php');
    }
    $currentImage = $imageFilename;
}

$pageTitle = $product ? 'Edit Product' : 'Add Product';
$activeNav = 'products';
include __DIR__ . '/includes/admin_header.php';
?>

<?php if ($errors): ?>
  <div class="alert alert-error">
    <ul style="margin:0;padding-left:18px;">
      <?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="admin-card">
  <div class="card-head"><h2><?= h($pageTitle) ?></h2></div>
  <div class="card-body">
    <form method="post" enctype="multipart/form-data">
      <div class="form-grid-2">
        <div class="form-group">
          <label for="name">Product name</label>
          <input type="text" id="name" name="name" value="<?= h($name) ?>" required>
        </div>
        <div class="form-group">
          <label for="category_id">Category</label>
          <select id="category_id" name="category_id">
            <option value="">— None —</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>" <?= (string) $categoryId === (string) $cat['id'] ? 'selected' : '' ?>><?= h($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="price">Price (Rs.)</label>
          <input type="number" id="price" name="price" step="0.01" min="0.01" value="<?= h((string) $price) ?>" required>
        </div>
        <div class="form-group">
          <label for="stock">Stock quantity</label>
          <input type="number" id="stock" name="stock" min="0" value="<?= h((string) $stock) ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4"><?= h($description) ?></textarea>
      </div>

      <div class="form-group">
        <label>Current image</label>
        <img class="thumb" style="width:80px;height:80px;margin-bottom:8px;" src="<?= h(product_image_url($currentImage)) ?>" alt="">
        <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif">
      </div>

      <div class="form-group">
        <label><input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?> style="width:auto;display:inline-block;"> Visible in store</label>
      </div>

      <button type="submit" class="btn btn-primary"><?= $product ? 'Save changes' : 'Create product' ?></button>
      <a href="<?= base_path('admin/products.php') ?>" class="btn btn-outline">Cancel</a>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
