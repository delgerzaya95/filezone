<?php
// guide-details.php

require_once 'includes/functions.php';
$conn = db_connect();

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header("Location: guides.php");
    exit();
}

// Зааврын мэдээллийг slug-аар нь авах
$sql = "SELECT g.*, u.username as author_name, u.avatar_url
        FROM guides g
        LEFT JOIN users u ON g.author_id = u.id
        WHERE g.slug = ? AND g.status = 'published'
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $slug);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$guide = mysqli_fetch_assoc($result);

if (!$guide) {
    header("Location: guides.php");
    exit();
}

// Үзсэн тоог нэмэх
mysqli_query($conn, "UPDATE guides SET view_count = view_count + 1 WHERE id = " . $guide['id']);

// Зааврын зургуудыг авах
$img_sql = "SELECT * FROM guide_images WHERE guide_id = ? ORDER BY order_index ASC";
$img_stmt = mysqli_prepare($conn, $img_sql);
mysqli_stmt_bind_param($img_stmt, "i", $guide['id']);
mysqli_stmt_execute($img_stmt);
$img_result = mysqli_stmt_get_result($img_stmt);
$guide_images = mysqli_fetch_all($img_result, MYSQLI_ASSOC);


$pageTitle = "Заавар - " . htmlspecialchars($guide['title']);
include 'includes/header.php';
include 'includes/navigation.php';
?>

<main class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-lg p-6 md:p-8">
        
        <div class="border-b pb-4 mb-6">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4"><?= htmlspecialchars($guide['title']) ?></h1>
            <div class="flex items-center text-gray-500 text-sm">
                <img src="<?= htmlspecialchars($guide['avatar_url'] ?? 'assets/images/default-avatar.png') ?>" class="w-8 h-8 rounded-full mr-2 object-cover">
                <span>Нийтэлсэн: <b><?= htmlspecialchars($guide['author_name'] ?? 'Admin') ?></b></span>
                <span class="mx-2">•</span>
                <span>Огноо: <?= date('Y-m-d', strtotime($guide['created_at'])) ?></span>
                <span class="mx-2">•</span>
                <span><i class="fas fa-eye mr-1"></i> <?= $guide['view_count'] ?></span>
            </div>
        </div>

        <?php if ($guide['guide_type'] === 'pdf'): // Хэрэв PDF төрлийн заавар бол ?>
            
            <div class="text-center py-12">
                <i class="fas fa-file-pdf text-purple-500 text-6xl mb-4"></i>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">PDF Заавар</h2>
                <p class="text-gray-600 mb-6">Энэхүү заавар нь PDF файл хэлбэрээр бэлтгэгдсэн байна.</p>
                <a href="<?= htmlspecialchars($guide['pdf_url']) ?>" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-8 rounded-lg inline-flex items-center" download>
                    <i class="fas fa-download mr-2"></i> PDF Файл Татах
                </a>
            </div>

        <?php else: // Хэрэв НИЙТЛЭЛ төрлийн заавар бол ?>

            <?php // Видеог онцолсон загвар бол видеог дээр нь харуулах
            if ($guide['layout_type'] === 'video_focused' && !empty($guide['youtube_url'])): 
                preg_match('/[\\?\\&]v=([^\\?\\&]+)/', $guide['youtube_url'], $matches);
                $video_id = $matches[1] ?? '';
            ?>
                <div class="aspect-w-16 aspect-h-9 rounded-lg overflow-hidden mb-6">
                    <iframe src="https://www.youtube.com/embed/<?= $video_id ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                </div>
            <?php endif; ?>

            <div class="prose lg:prose-xl max-w-none text-gray-700 leading-relaxed">
                <?php
                    // Админ HTML код оруулсан байж болох тул энд htmlspecialchars ашиглахгүй
                    echo $guide['content']; 
                ?>
            </div>

            <?php // Стандарт загвар бол видеог доор нь харуулах
            if ($guide['layout_type'] === 'standard' && !empty($guide['youtube_url'])): 
                preg_match('/[\\?\\&]v=([^\\?\\&]+)/', $guide['youtube_url'], $matches);
                $video_id = $matches[1] ?? '';
            ?>
                <h3 class="text-2xl font-bold text-gray-800 mt-8 mb-4 border-t pt-6">Холбогдох видео</h3>
                <div class="aspect-w-16 aspect-h-9 rounded-lg overflow-hidden">
                    <iframe src="https://www.youtube.com/embed/<?= $video_id ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</main>

<?php
include 'includes/footer.php';
?>