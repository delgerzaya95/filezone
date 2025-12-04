<?php
// footer.php

// Database connection
require_once __DIR__ . '/functions.php';
$conn = db_connect();

// Fetch all categories with their subcategories
$categories = [];
$sql = "SELECT c.id, c.name, c.slug, c.icon_class, 
               s.id AS subcat_id, s.name AS subcat_name 
        FROM categories c
        LEFT JOIN subcategories s ON c.id = s.category_id
        ORDER BY c.name ASC, s.name ASC";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $cat_id = $row['id'];
        if (!isset($categories[$cat_id])) {
            $categories[$cat_id] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'slug' => $row['slug'],
                'icon_class' => $row['icon_class'],
                'subcategories' => []
            ];
        }
        
        if ($row['subcat_id']) {
            $categories[$cat_id]['subcategories'][] = [
                'id' => $row['subcat_id'],
                'name' => $row['subcat_name']
            ];
        }
    }
}


// Select the first 3 categories for the footer columns
$footerCategories = array_slice($categories, 0, 3);
?>

<!-- Footer -->
<footer class="bg-gray-900 text-white py-8">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <?php foreach ($footerCategories as $category): ?>
                <div>
                    <h3 class="text-lg font-semibold mb-4"><?= htmlspecialchars($category['name']) ?></h3>
                    <ul class="space-y-2">
                        <?php foreach ($category['subcategories'] as $subcategory): ?>
                            <li>
                                <a href="categories.php?subcategory_id=<?= $subcategory['id'] ?>" 
                                   class="text-gray-400 hover:text-white">
                                    <?= htmlspecialchars($subcategory['name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>

            <div>
                <h3 class="text-lg font-semibold mb-4">ХОЛБОО БАРИХ</h3>
                <address class="text-gray-400 not-italic">
                    Улаанбаатар хот, БЗД, 15-р хороо,<br>
                     72-92<br><br>
                    <i class="fas fa-clock mr-2"></i> 09:00AM - 08:00PM<br>
                    <i class="fas fa-envelope mr-2"></i> info@filezone.mn <br>
                    <i class="fas fa-phone-alt mr-2"></i> +976 55145313
                </address>
            </div>
        </div>

        <div style="text-align: center; margin-top: 10px; font-size: 14px;">
            <a href="privacy.php" style="color: #666; text-decoration: none; margin-right: 15px;">Нууцлалын бодлого</a>
            <a href="terms.php" style="color: #666; text-decoration: none;">Үйлчилгээний нөхцөл</a>
        </div>

        <div class="border-t border-gray-800 mt-8 pt-6 text-center text-gray-400 text-sm">
            <p>Copyright © <?= date('Y') ?> "FILEZONE"</p>
        </div>
    </div>
</footer>
<script>
    document.addEventListener('DOMContentLoaded', function() {
            // Эхлээд body-г нууж, зөвхөн loader харагдуулна
            document.body.classList.add('content-hidden');
        });

        window.addEventListener('load', function() {
            const loader = document.getElementById('loader-wrapper');
            
            // Loading дэлгэцийг зөөлөн алга болгох
            loader.style.opacity = '0';
            
            // 0.5 секундын дараа loader-г бүрмөсөн устгах (display: none)
            setTimeout(function() {
                loader.style.display = 'none';
            }, 500); // Энэ хугацаа CSS-ийн transition хугацаатай ижил байх ёстой

            // Үндсэн контентыг харуулах
            document.body.classList.remove('content-hidden');
            document.body.style.visibility = 'visible';
            document.body.style.opacity = '1';
            document.body.style.transition = 'opacity 0.5s ease';
        });
</script>
<script src="https://cdn.tiny.cloud/1/g492qv0cyczptbbzcso4exirfkhg3l20o9z13ujy2i0arcw5/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>