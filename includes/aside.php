<aside class="w-full lg:w-1/4">

    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">АНГИЛАЛ ХАРАХ</h3>
        <div class="space-y-3">
            <?php
            // functions.php доторх db_connect() функц ашиглагдана
            if (!isset($conn) || !$conn) {
                $host = 'localhost';
                $username = 'filezone_mn';
                $password = '099da7e85a2688';
                $dbname = 'filezone_mn';
                $conn = mysqli_connect($host, $username, $password, $dbname);
                mysqli_set_charset($conn, "utf8mb4");
            }

            // URL параметрүүдийг авах
            $current_category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
            $current_subcategory_id = isset($_GET['subcategory_id']) ? intval($_GET['subcategory_id']) : 0;
            $current_child_category_id = isset($_GET['child_category_id']) ? intval($_GET['child_category_id']) : 0;

            // Анхны төлөвт бүх ангиллууд нээлттэй эсэхийг шалгах
            $any_category_selected = ($current_category_id > 0 || $current_subcategory_id > 0 || $current_child_category_id > 0);

            // Бүх үндсэн ангиллыг авах
            $cat_query = "SELECT id, name, icon_class FROM categories ORDER BY name ASC";
            $cat_result = mysqli_query($conn, $cat_query);

            if (mysqli_num_rows($cat_result) > 0) {
                while ($category = mysqli_fetch_assoc($cat_result)) {
                    $is_current_category = ($current_category_id == $category['id']);
                    
                    echo '<div class="category-group">';
                    echo '<div class="flex items-center text-purple-600 font-medium px-2 py-2 ' . ($is_current_category ? 'bg-purple-50 rounded-lg' : '') . '">';
                    echo '<a href="categories.php?category_id=' . $category['id'] . '" class="flex items-center w-full">';
                    
                    $icon_class = !empty($category['icon_class']) ? $category['icon_class'] : 'fas fa-folder';
                    echo '<i class="' . htmlspecialchars($icon_class) . ' mr-2 text-purple-500"></i>';
                    echo htmlspecialchars($category['name']);
                    echo '</a>';
                    // Сум нэмэх
                    echo '<button class="category-toggle ml-2 text-purple-500 hover:text-purple-700">';
                    echo '<i class="fas fa-chevron-down text-sm"></i>';
                    echo '</button>';
                    echo '</div>';

                    // Тухайн ангиллын дэд ангиллуудыг авах
                    $subcat_query = "SELECT id, name FROM subcategories WHERE category_id = " . (int)$category['id'] . " ORDER BY name ASC";
                    $subcat_result = mysqli_query($conn, $subcat_query);

                    if (mysqli_num_rows($subcat_result) > 0) {
                        // Анхны төлөв: ямар ч ангилал сонгогдоогүй бол бүгд нээлттэй, сонгогдсон бол зөвхөн сонгогдсон нь нээлттэй
                        $show_subcategories = !$any_category_selected || $is_current_category || $current_subcategory_id > 0;
                        
                        echo '<div class="subcategories-container mt-2 pl-8 space-y-2 ' . ($show_subcategories ? '' : 'hidden') . '">';
                        while ($subcat = mysqli_fetch_assoc($subcat_result)) {
                            $is_current_subcategory = ($current_subcategory_id == $subcat['id']);
                            
                            echo '<div class="subcategory-item">';
                            echo '<div class="flex justify-between items-center">';
                            echo '<a href="categories.php?category_id=' . $category['id'] . '&subcategory_id=' . $subcat['id'] . '" class="flex-1 text-sm py-1 pl-4 ' . ($is_current_subcategory ? 'text-purple-700 font-bold bg-purple-50 rounded' : 'text-gray-600 hover:text-purple-600') . '">';
                            echo htmlspecialchars($subcat['name']);
                            echo '</a>';
                            // Дэд ангиллын сум
                            echo '<button class="subcategory-toggle mr-2 text-gray-400 hover:text-purple-500">';
                            echo '<i class="fas fa-chevron-down text-xs"></i>';
                            echo '</button>';
                            echo '</div>';
                            
                            // Тухайн дэд ангиллын child категориудыг авах
                            $child_query = "SELECT id, name FROM child_category WHERE subcategory_id = " . (int)$subcat['id'] . " ORDER BY name ASC";
                            $child_result = mysqli_query($conn, $child_query);
                            
                            if (mysqli_num_rows($child_result) > 0) {
                                // Анхны төлөв: ямар ч ангилал сонгогдоогүй бол бүгд нээлттэй, сонгогдсон бол зөвхөн сонгогдсон нь нээлттэй
                                $show_child_categories = $is_current_subcategory || $current_child_category_id > 0;
                                
                                echo '<div class="child-categories-container mt-1 pl-4 space-y-1 ' . ($show_child_categories ? '' : 'hidden') . '">';
                                while ($child = mysqli_fetch_assoc($child_result)) {
                                    $is_current_child_category = ($current_child_category_id == $child['id']);
                                    
                                    echo '<a href="categories.php?category_id=' . $category['id'] . '&subcategory_id=' . $subcat['id'] . '&child_category_id=' . $child['id'] . '" class="block text-xs py-1 pl-4 border-l border-gray-200 ' . ($is_current_child_category ? 'text-purple-600 font-bold border-purple-400' : 'text-gray-500 hover:text-purple-500') . '">';
                                    echo '<i class="fas fa-caret-right mr-1 text-gray-400"></i>';
                                    echo htmlspecialchars($child['name']);
                                    echo '</a>';
                                }
                                echo '</div>';
                            }
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                    echo '</div>';
                }
            }
            ?>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Хамгийн их татагдсан 4 файл</h3>
        <div class="space-y-4">
            <?php
            // download_count-аар эрэмбэлж хамгийн их татагдсан 4 файлыг авна
            $downloads_query = "SELECT f.id, f.title, f.file_type
                                FROM files f
                                ORDER BY f.download_count DESC
                                LIMIT 4";
            $downloads_result = mysqli_query($conn, $downloads_query);

            if (mysqli_num_rows($downloads_result) > 0) {
                while ($file = mysqli_fetch_assoc($downloads_result)) {
                    $icon_class = get_file_icon($file['file_type']); // functions.php-с icon авах
                    echo '<div class="flex items-start">';
                    echo '<div class="flex-shrink-0 bg-purple-100 text-purple-600 p-2 rounded-md">';
                    echo '<i class="' . $icon_class . '"></i>';
                    echo '</div>';
                    echo '<div class="ml-3">';
                    echo '<a href="file-details.php?id=' . $file['id'] . '" class="text-sm font-medium text-gray-800 hover:text-purple-600">' . htmlspecialchars($file['title']) . '</a>';
                    echo '</div>';
                    echo '</div>';
                }
            } else {
                echo '<p class="text-sm text-gray-500">Одоогоор татагдсан файл байхгүй</p>';
            }
            ?>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Сүүлд файл нэмсэн гишүүд</h3>
        <div class="space-y-4">
            <?php
            // Сүүлд файл нэмсэн хэрэглэгчдийн мэдээллийг авах (avatar_url нэмэгдсэн)
            $uploaders_query = "SELECT u.id, u.username, u.avatar_url, f.title
                                FROM users u
                                JOIN files f ON f.user_id = u.id
                                ORDER BY f.upload_date DESC
                                LIMIT 3";
            $uploaders_result = mysqli_query($conn, $uploaders_query);

            if (mysqli_num_rows($uploaders_result) > 0) {
                while ($uploader = mysqli_fetch_assoc($uploaders_result)) {
                    // Хэрэглэгч зураггүй бол default зураг харуулах
                    $avatar = !empty($uploader['avatar_url']) ? $uploader['avatar_url'] : 'assets/images/default-avatar.png';

                    echo '<div class="flex items-center">';
                    echo '<img src="' . htmlspecialchars($avatar) . '" alt="' . htmlspecialchars($uploader['username']) . '" class="w-10 h-10 rounded-full object-cover">';
                    echo '<div class="ml-3">';
                    echo '<a href="profile.php?id=' . $uploader['id'] . '" class="text-sm font-medium text-gray-800 hover:text-purple-600">' . htmlspecialchars($uploader['username']) . '</a>';
                    echo '<p class="text-xs text-gray-500">Сүүлд нэмсэн: ' . htmlspecialchars($uploader['title']) . '</p>';
                    echo '</div>';
                    echo '</div>';
                }
            } else {
                echo '<p class="text-sm text-gray-500">Сүүлд файл нэмсэн гишүүн байхгүй</p>';
            }
            ?>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="bg-purple-100 rounded-lg p-4 text-center">
            <p class="text-sm text-purple-800 mb-2">СУРТАЛЧИЛГАА</p>
            <div class="bg-white p-4 rounded-md">
                <p class="text-sm text-gray-600">Таны зарлал энд гарч болно</p>
                <a href="advertise.php" class="inline-block mt-2 text-sm text-purple-600 hover:underline">Дэлгэрэнгүй</a>
            </div>
        </div>
    </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Ангилал тоглогч функц
    function setupCategoryToggles() {
        // Ангилал сумнууд
        const categoryToggles = document.querySelectorAll('.category-toggle');
        
        categoryToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const categoryGroup = this.closest('.category-group');
                const subcategoriesContainer = categoryGroup.querySelector('.subcategories-container');
                const icon = this.querySelector('i');
                
                if (subcategoriesContainer) {
                    // Харуулах/нуух
                    subcategoriesContainer.classList.toggle('hidden');
                    
                    // Сумны чиглэл өөрчлөх
                    if (subcategoriesContainer.classList.contains('hidden')) {
                        icon.classList.remove('fa-chevron-up');
                        icon.classList.add('fa-chevron-down');
                    } else {
                        icon.classList.remove('fa-chevron-down');
                        icon.classList.add('fa-chevron-up');
                    }
                }
            });
        });
        
        // Дэд ангилал сумнууд
        const subcategoryToggles = document.querySelectorAll('.subcategory-toggle');
        
        subcategoryToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const subcategoryItem = this.closest('.subcategory-item');
                const childCategoriesContainer = subcategoryItem.querySelector('.child-categories-container');
                const icon = this.querySelector('i');
                
                if (childCategoriesContainer) {
                    // Харуулах/нуух
                    childCategoriesContainer.classList.toggle('hidden');
                    
                    // Сумны чиглэл өөрчлөх
                    if (childCategoriesContainer.classList.contains('hidden')) {
                        icon.classList.remove('fa-chevron-up');
                        icon.classList.add('fa-chevron-down');
                    } else {
                        icon.classList.remove('fa-chevron-down');
                        icon.classList.add('fa-chevron-up');
                    }
                }
            });
        });
        
        // Анхны төлөвт сумны чиглэлийг тохируулах
        updateToggleIcons();
    }
    
    // Сумны чиглэлийг шинэчлэх функц
    function updateToggleIcons() {
        // Ангилал сумнууд
        document.querySelectorAll('.category-group').forEach(group => {
            const toggle = group.querySelector('.category-toggle');
            const subcategoriesContainer = group.querySelector('.subcategories-container');
            const icon = toggle?.querySelector('i');
            
            if (icon && subcategoriesContainer) {
                if (subcategoriesContainer.classList.contains('hidden')) {
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                } else {
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                }
            }
        });
        
        // Дэд ангилал сумнууд
        document.querySelectorAll('.subcategory-item').forEach(item => {
            const toggle = item.querySelector('.subcategory-toggle');
            const childCategoriesContainer = item.querySelector('.child-categories-container');
            const icon = toggle?.querySelector('i');
            
            if (icon && childCategoriesContainer) {
                if (childCategoriesContainer.classList.contains('hidden')) {
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                } else {
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                }
            }
        });
    }
    
    // Анхны ачааллалт
    setupCategoryToggles();
});
</script>