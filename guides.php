<?php
// guides.php

require_once 'includes/functions.php';
$conn = db_connect();

$pageTitle = "Filezone - Хэрэгтэй зааврууд";
include 'includes/header.php';
include 'includes/navigation.php';

// Хуудаслалтын тохиргоо
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 9; // Нэг хуудсанд 9 заавар харуулна (3x3 grid)
$offset = ($page - 1) * $perPage;

// Нийт нийтлэгдсэн зааврын тоог авах
$countQuery = "SELECT COUNT(id) as total FROM guides WHERE status = 'published'";
$countResult = mysqli_query($conn, $countQuery);
$totalGuides = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalGuides / $perPage);

// Тухайн хуудасны заавруудыг авах
$sql = "SELECT g.id, g.title, g.slug, g.featured_image, g.created_at, u.username as author_name
        FROM guides g
        LEFT JOIN users u ON g.author_id = u.id
        WHERE g.status = 'published'
        ORDER BY g.created_at DESC
        LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $perPage, $offset);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$guides = mysqli_fetch_all($result, MYSQLI_ASSOC);

?>

<main class="container mx-auto px-4 py-8">
    <div class="text-center mb-10">
        <h1 class="text-3xl font-bold text-gray-800 mb-4">Хэрэгтэй Зааврууд</h1>
        <p class="text-gray-600 max-w-2xl mx-auto">
            Технологи, програм хангамж болон бусад зүйлсийн талаарх дэлгэрэнгүй заавар, нийтлэлүүд.
        </p>
    </div>
    
    <div class="flex flex-col lg:flex-row gap-8">

        <div class="w-full lg:w-3/3">
            <?php if (!empty($guides)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($guides as $guide): ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden transform hover:-translate-y-1 transition-all duration-300 group">
                            <a href="guide-details.php?slug=<?= htmlspecialchars($guide['slug']) ?>" class="block">
                                <div class="h-48 bg-gray-200 flex items-center justify-center overflow-hidden">
                                    <img src="<?= htmlspecialchars($guide['featured_image'] ?? 'assets/images/default_image.jpg') ?>" 
                                         alt="<?= htmlspecialchars($guide['title']) ?>" 
                                         class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-300">
                                </div>
                                <div class="p-4">
                                    <h3 class="font-bold text-gray-800 text-md truncate" title="<?= htmlspecialchars($guide['title']) ?>"><?= htmlspecialchars($guide['title']) ?></h3>
                                    <div class="text-gray-600 text-sm mt-2">
                                        <span><i class="fas fa-user-edit mr-1"></i> <?= htmlspecialchars($guide['author_name'] ?? 'Admin') ?></span>
                                        <span class="mx-2">•</span>
                                        <span><i class="fas fa-calendar-alt mr-1"></i> <?= date('Y-m-d', strtotime($guide['created_at'])) ?></span>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="mt-8 flex justify-center">
                        <nav class="inline-flex rounded-md shadow">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?= $i ?>" class="py-2 px-4 border border-gray-300 text-sm font-medium <?= ($i == $page) ? 'bg-purple-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                        </nav>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="bg-white rounded-lg shadow-md p-12 text-center">
                    <i class="fas fa-book-reader text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-700">Заавар олдсонгүй</h3>
                    <p class="text-gray-500 mt-2">Одоогоор нийтлэгдсэн заавар байхгүй байна.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php
include 'includes/footer.php';
?>