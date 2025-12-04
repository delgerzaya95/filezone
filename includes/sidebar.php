<?php
/**
 * Sidebar with categories and other widgets
 */
?>
<!-- Left Sidebar - Categories Only -->
<aside class="w-full lg:w-1/4">
    <!-- Categories Section - Expanded -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">АНГИЛАЛ ХАРАХ</h3>
        <div class="space-y-3">
            <details class="group">
                <summary class="flex justify-between items-center cursor-pointer text-purple-600 font-medium px-2 py-2 category-summary">
                    <span>ХИЧЭЭЛ, СУРЛАГА</span>
                    <i class="fas fa-chevron-down group-open:rotate-180 transition-transform"></i>
                </summary>
                <div class="mt-2 pl-4 space-y-2">
                    <a href="categories.php#education" class="block text-sm text-gray-600 hover:text-purple-600 py-1">- ТӨСӨЛ ТАТАХ (бүх төрлийн)</a>
                    <a href="categories.php#education" class="block text-sm text-gray-600 hover:text-purple-600 py-1">- ДАДЛАГЫН ТАЙЛАНГУУД</a>
                    <a href="categories.php#education" class="block text-sm text-gray-600 hover:text-purple-600 py-1">- Курсын ажил</a>
                    <a href="categories.php#education" class="block text-sm text-gray-600 hover:text-purple-600 py-1">- Дипломын ажил</a>
                    <a href="categories.php#education" class="block text-sm text-gray-600 hover:text-purple-600 py-1">- Лекцийн тэмдэглэл</a>
                </div>
            </details>
            
            <!-- Other categories would follow the same pattern -->
        </div>
    </div>
    
    <!-- Recently Downloaded -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Хамгийн сүүлд татагдсан 4 файл</h3>
        <div class="space-y-4">
            <?php
            // Example of dynamic content - you would replace this with actual database queries
            $recentFiles = [
                ['title' => 'Захиргааны актын тухай ойлголт, төрөл, ангилал', 'type' => 'word'],
                ['title' => 'CV загвар', 'type' => 'word'],
                ['title' => 'Нэхэмжлэх загвар - Word', 'type' => 'word'],
                ['title' => 'Эдийн засгийн математик загварчлалын лекц', 'type' => 'word']
            ];
            
            foreach ($recentFiles as $file) {
                echo '<div class="flex items-start">
                    <div class="flex-shrink-0 bg-purple-100 text-purple-600 p-2 rounded-md">
                        <i class="fas fa-file-word"></i>
                    </div>
                    <div class="ml-3">
                        <a href="file-details.php" class="text-sm font-medium text-gray-800 hover:text-purple-600">' . htmlspecialchars($file['title']) . '</a>
                    </div>
                </div>';
            }
            ?>
        </div>
    </div>
    
    <!-- Other sidebar widgets would follow -->
</aside>