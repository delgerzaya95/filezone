<?php
/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/
// Include functions and connect to database
require_once 'functions.php';
$conn = db_connect();
?>

<main class="w-full lg:w-2/3 p-4">
    <div class="mb-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-800">ШИНЭЭР НИЙТЛЭГДСЭН ФАЙЛУУД</h2>
            <a href="../browse-files.php" class="text-sm text-purple-600 hover:underline">Бүгдийг харах</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php
            // Get recent files from database
            $files_query = "SELECT f.*, u.username, fp.preview_url, 
                       c.name as category_name,
                       sc.name as subcategory_name
                FROM files f
                JOIN users u ON f.user_id = u.id
                LEFT JOIN file_previews fp ON f.id = fp.file_id AND fp.order_index = 1
                LEFT JOIN file_categories fc ON f.id = fc.file_id  
                LEFT JOIN subcategories sc ON fc.subcategory_id = sc.id
                LEFT JOIN categories c ON sc.category_id = c.id
                WHERE f.status = 'approved'
                ORDER BY f.upload_date DESC
                LIMIT 6";
            $files_result = mysqli_query($conn, $files_query);

            if (mysqli_num_rows($files_result) > 0) {
                while ($file = mysqli_fetch_assoc($files_result)) {
                    // Энэ хэсгийг хуучин кодын оронд тавина уу
                        $file_size = format_file_size($file['file_size']); // file_size хэвээрээ үлдэнэ

                        echo '<div class="bg-white rounded-lg shadow-md overflow-hidden file-card transition duration-300 relative">';
                            echo '<div class="relative">';
                                echo '<a href="file-details.php?id=' . $file['id'] . '">';
                                    
                                    // ... while давталтын дотор ...
                                    if (!empty($file['preview_url'])) {
                                        // Энэ хэсэг хэвээрээ
                                        echo '<a href="file-details.php?id=' . $file['id'] . '">';
                                        echo '<img src="' . htmlspecialchars($file['preview_url']) . '" alt="' . htmlspecialchars($file['title']) . '" class="w-full h-40 object-cover bg-gray-100">';
                                        echo '</a>';
                                    } else {
                                        // Хэрэв preview зураг БАЙХГҮЙ БОЛ...
                                        
                                        // 1. Өнгөний классыг авах (энэ функц `functions.php`-д байгаа)
                                        $color_class = getFilePreviewClass($file['file_url']);
                                        
                                        // 2. Icon-ий классыг шинэ функцээс авах
                                        $icon_class = getFileIconClass($file['file_url']);
                                        
                                        // 3. Эцсийн HTML-г угсарч хэвлэх
                                        echo '<a href="file-details.php?id=' . $file['id'] . '" class="file-preview-box ' . $color_class . '">';
                                        echo '<i class="' . $icon_class . '"></i>';
                                        echo '</a>';
                                    }

                                echo '</a>';
                           echo '<span class="file-type-badge bg-black bg-opacity-80 text-white px-2 py-1 rounded text-xs font-bold">' . strtoupper($file['file_type']) . '</span>';
                        echo '</div>';
                        echo '<div class="p-4">';
                        // АНГИЛАЛЫН МЭДЭЭЛЭЛ НЭМЭХ
                            echo '<div class="flex items-center text-xs text-gray-500 mb-2">';
                                echo '<span class="bg-purple-100 text-purple-700 px-2 py-1 rounded mr-2">';
                                echo htmlspecialchars($file['category_name'] ?? 'Ангилалгүй');
                                echo '</span>';
                                if (!empty($file['subcategory_name'])) {
                                    echo '<span class="bg-gray-100 text-gray-600 px-2 py-1 rounded">';
                                    echo htmlspecialchars($file['subcategory_name']);
                                    echo '</span>';
                                }
                            echo '</div>';
                            echo '<h3 class="font-semibold text-gray-800 mb-2">';
                                echo '<a href="file-details.php?id=' . $file['id'] . '" class="hover:text-purple-600">' . htmlspecialchars($file['title']) . '</a>';
                            echo '</h3>';
                            echo '<div class="flex items-center text-sm text-gray-500 mb-2">';
                                echo '<span><i class="fas fa-eye mr-1"></i> ' . $file['view_count'] . '</span>';
                                echo '<span class="mx-2">•</span>';
                                echo '<span>' . strtoupper($file['file_type']) . ', ' . $file_size . '</span>';
                            echo '</div>';
                            echo '<div class="flex justify-between items-center mt-3 pt-3 border-t border-gray-100">';
                                // --- ҮНИЙН ХЭСЭГ ---
                                echo '<div>';
                                if ($file['price'] > 0) {
                                    // Хэрэв үнэ 0-ээс их бол үнийг форматлаж харуулна
                                    echo '<span class="text-purple-600 font-bold text-lg">' . number_format($file['price'], 0) . '₮</span>';
                                } else {
                                    // Хэрэв үнэ 0 бол "Үнэгүй" гэж харуулна
                                    echo '<span class="text-green-600 font-bold text-lg">Үнэгүй</span>';
                                }
                                echo '</div>';

                                // --- ДЭЛГЭРЭНГҮЙ ТОВЧ ---
                                echo '<a href="file-details.php?id=' . $file['id'] . '" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md text-xs font-medium flex items-center">';
                                    echo '<i class="fas fa-info-circle mr-2"></i> Дэлгэрэнгүй';
                                echo '</a>';
                            echo '</div>';
                        echo '</div>';
                    echo '</div>';
                }
            } else {
                echo '<p class="col-span-3 text-center text-gray-500">No files found</p>';
            }
            ?>
        </div>

        <div class="mt-6 flex justify-center">
            <?php
            // Simple pagination
            $total_files_query = "SELECT COUNT(*) as total FROM files WHERE status = 'approved'";
            $total_files_result = mysqli_query($conn, $total_files_query);
            $total_files = mysqli_fetch_assoc($total_files_result)['total'];
            $total_pages = ceil($total_files / 6);

            if ($total_pages > 1) {
                echo '<nav class="flex items-center space-x-2">';
                echo '<a href="../browse-files.php?page=1" class="px-3 py-1 bg-purple-600 text-white rounded-md">1</a>';
                
                if ($total_pages > 2) {
                    echo '<a href="../browse-files.php?page=2" class="px-3 py-1 text-gray-700 hover:bg-gray-100 rounded-md">2</a>';
                }
                
                if ($total_pages > 3) {
                    echo '<a href="../browse-files.php?page=3" class="px-3 py-1 text-gray-700 hover:bg-gray-100 rounded-md">3</a>';
                }
                
                if ($total_pages > 1) {
                    echo '<a href="../browse-files.php?page=2" class="px-3 py-1 text-gray-700 hover:bg-gray-100 rounded-md">дараагийн</a>';
                }
                
                if ($total_pages > 3) {
                    echo '<a href="../browse-files.php?page=' . $total_pages . '" class="px-3 py-1 text-gray-700 hover:bg-gray-100 rounded-md">Төгсгөлд очих</a>';
                }
                
                echo '</nav>';
            }
            ?>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex flex-col md:flex-row items-center">
            <img src="../icons/info_img.png" alt="Туслахуй" class="w-24 h-24 object-cover rounded-full mb-4 md:mb-0 md:mr-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">ТАНД ТУСАЛЪЯ!</h3>
                <p class="text-gray-600 mb-4">Асууж тодорхойгүй зүйлс байвал бидэнтэй холбогдоорой. Манай мэргэжилтнүүд танд туслахдаа баяртай байх болно.</p>
                <a href="contact.php" class="inline-block gradient-bg text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-purple-700">
                    <i class="fas fa-paper-plane mr-1"></i> ХОЛБОО БАРИХ
                </a>
            </div>
        </div>
    </div>
</div>