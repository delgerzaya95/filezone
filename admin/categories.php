<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Хэрэглэгч нэвтэрсэн эсэхийг шалгах
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit();
}

// Database connection
$conn = mysqli_connect("localhost", "filezone_mn", "099da7e85a2688", "filezone_mn");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new category
    if (isset($_POST['add_category']) && $_POST['add_category'] == '1') {
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $slug = mysqli_real_escape_string($conn, $_POST['slug']);
        $icon_class = mysqli_real_escape_string($conn, $_POST['icon_class']);

        // Check if slug already exists
        $check_sql = "SELECT id FROM categories WHERE slug = '$slug'";
        $check_result = mysqli_query($conn, $check_sql);

        if (mysqli_num_rows($check_result) > 0) {
            $error = "Энэ slug бүртгэлтэй байна. Өөр slug сонгоно уу.";
        } else {
            $sql = "INSERT INTO categories (name, slug, icon_class) VALUES ('$name', '$slug', '$icon_class')";

            if (mysqli_query($conn, $sql)) {
                header("Location: categories.php");
                exit();
            } else {
                $error = "Ангилал нэмэхэд алдаа гарлаа: " . mysqli_error($conn);
            }
        }
    }
    
    // Add new subcategory
    if (isset($_POST['add_subcategory']) && $_POST['add_subcategory'] == '1') {
        $category_id = intval($_POST['category_id']);
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        
        $sql = "INSERT INTO subcategories (category_id, name) VALUES ($category_id, '$name')";
        
        if (mysqli_query($conn, $sql)) {
            header("Location: categories.php");
            exit();
        } else {
            $error = "Дэд ангилал нэмэхэд алдаа гарлаа: " . mysqli_error($conn);
        }
    }
    
    // Add new child category
    if (isset($_POST['add_child_category']) && $_POST['add_child_category'] == '1') {
        $subcategory_id = intval($_POST['subcategory_id']);
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        
        $sql = "INSERT INTO child_category (subcategory_id, name) VALUES ($subcategory_id, '$name')";
        
        if (mysqli_query($conn, $sql)) {
            header("Location: categories.php");
            exit();
        } else {
            $error = "Жижиг ангилал нэмэхэд алдаа гарлаа: " . mysqli_error($conn);
        }
    }
    
    // Update category
    if (isset($_POST['update_category']) && $_POST['update_category'] == '1') {
        $id = intval($_POST['id']);
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $slug = mysqli_real_escape_string($conn, $_POST['slug']);
        $icon_class = mysqli_real_escape_string($conn, $_POST['icon_class']);

        // Check if slug already exists (excluding current category)
        $check_sql = "SELECT id FROM categories WHERE slug = '$slug' AND id != $id";
        $check_result = mysqli_query($conn, $check_sql);

        if (mysqli_num_rows($check_result) > 0) {
            $error = "Энэ slug бүртгэлтэй байна. Өөр slug сонгоно уу.";
        } else {
            $sql = "UPDATE categories SET name='$name', slug='$slug', icon_class='$icon_class' WHERE id=$id";

            if (mysqli_query($conn, $sql)) {
                header("Location: categories.php");
                exit();
            } else {
                $error = "Ангилал шинэчлэхэд алдаа гарлаа: " . mysqli_error($conn);
            }
        }
    }
    
    // Update subcategory
    if (isset($_POST['update_subcategory']) && $_POST['update_subcategory'] == '1') {
        $id = intval($_POST['id']);
        $category_id = intval($_POST['category_id']);
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        
        $sql = "UPDATE subcategories SET category_id=$category_id, name='$name' WHERE id=$id";
        
        if (mysqli_query($conn, $sql)) {
            header("Location: categories.php");
            exit();
        } else {
            $error = "Дэд ангилал шинэчлэхэд алдаа гарлаа: " . mysqli_error($conn);
        }
    }
    
    // Update child category - ДАРЫН КОД
    if (isset($_POST['update_child_category']) && $_POST['update_child_category'] == '1') {
        $id = intval($_POST['id']);
        $subcategory_id = intval($_POST['subcategory_id']);
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        
        $sql = "UPDATE child_category SET subcategory_id=$subcategory_id, name='$name' WHERE id=$id";
        
        if (mysqli_query($conn, $sql)) {
            header("Location: categories.php");
            exit();
        } else {
            $error = "Жижиг ангилал шинэчлэхэд алдаа гарлаа: " . mysqli_error($conn);
        }
    }
    
    // Delete category
    if (isset($_POST['delete_category'])) {
        $id = intval($_POST['id']);
        
        // Check if category has subcategories
        $check_sql = "SELECT COUNT(*) as count FROM subcategories WHERE category_id = $id";
        $check_result = mysqli_query($conn, $check_sql);
        $row = mysqli_fetch_assoc($check_result);
        
        if ($row['count'] > 0) {
            $error = "Энэ ангилалд дэд ангилал байна. Эхлээд дэд ангилуудыг устгана уу.";
        } else {
            $sql = "DELETE FROM categories WHERE id=$id";
            
            if (mysqli_query($conn, $sql)) {
                header("Location: categories.php");
                exit();
            } else {
                $error = "Ангилал устгахад алдаа гарлаа: " . mysqli_error($conn);
            }
        }
    }
    
    // Delete subcategory
    if (isset($_POST['delete_subcategory'])) {
        $id = intval($_POST['id']);
        
        // Check if subcategory has child categories
        $check_sql = "SELECT COUNT(*) as count FROM child_category WHERE subcategory_id = $id";
        $check_result = mysqli_query($conn, $check_sql);
        $row = mysqli_fetch_assoc($check_result);
        
        if ($row['count'] > 0) {
            $error = "Энэ дэд ангилалд жижиг ангилал байна. Эхлээд жижиг ангилуудыг устгана уу.";
        } else {
            $sql = "DELETE FROM subcategories WHERE id=$id";
            
            if (mysqli_query($conn, $sql)) {
                header("Location: categories.php");
                exit();
            } else {
                $error = "Дэд ангилал устгахад алдаа гарлаа: " . mysqli_error($conn);
            }
        }
    }
    
    // Delete child category
    if (isset($_POST['delete_child_category'])) {
        $id = intval($_POST['id']);
        
        $sql = "DELETE FROM child_category WHERE id=$id";
        
        if (mysqli_query($conn, $sql)) {
            header("Location: categories.php");
            exit();
        } else {
            $error = "Жижиг ангилал устгахад алдаа гарлаа: " . mysqli_error($conn);
        }
    }
}

// Get all categories with counts
$categories_sql = "SELECT c.*, 
(SELECT COUNT(*) FROM subcategories WHERE category_id = c.id) as subcategory_count,
(SELECT COUNT(*) FROM file_categories fc JOIN subcategories sc ON fc.subcategory_id = sc.id WHERE sc.category_id = c.id) as file_count
FROM categories c";
$categories_result = mysqli_query($conn, $categories_sql);
$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $row;
}

// Get all subcategories with category info
$subcategories_sql = "SELECT sc.*, c.name as category_name, c.icon_class as category_icon
FROM subcategories sc
JOIN categories c ON sc.category_id = c.id";
$subcategories_result = mysqli_query($conn, $subcategories_sql);
$subcategories = [];
while ($row = mysqli_fetch_assoc($subcategories_result)) {
    $subcategories[] = $row;
}

// Get all child categories with subcategory info
$child_categories_sql = "SELECT cc.*, sc.name as subcategory_name, sc.category_id, c.name as category_name
FROM child_category cc
JOIN subcategories sc ON cc.subcategory_id = sc.id
JOIN categories c ON sc.category_id = c.id";
$child_categories_result = mysqli_query($conn, $child_categories_sql);
$child_categories = [];
while ($row = mysqli_fetch_assoc($child_categories_result)) {
    $child_categories[] = $row;
}

// Get file counts for subcategories
$subcategory_file_counts = [];
$file_counts_sql = "SELECT subcategory_id, COUNT(*) as file_count FROM file_categories GROUP BY subcategory_id";
$file_counts_result = mysqli_query($conn, $file_counts_sql);
while ($row = mysqli_fetch_assoc($file_counts_result)) {
    $subcategory_file_counts[$row['subcategory_id']] = $row['file_count'];
}

// Get file counts for child categories
$child_category_file_counts = [];
$child_file_counts_sql = "SELECT child_category_id, COUNT(*) as file_count FROM file_categories WHERE child_category_id IS NOT NULL GROUP BY child_category_id";
$child_file_counts_result = mysqli_query($conn, $child_file_counts_sql);
while ($row = mysqli_fetch_assoc($child_file_counts_result)) {
    $child_category_file_counts[$row['child_category_id']] = $row['file_count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filezone - Админ Панел | Ангилал</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="css/styles.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4f46e5',
                        secondary: '#7c3aed',
                        dark: '#1e293b',
                        light: '#f8fafc',
                        admin: '#2d3748'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 font-sans">
    <!-- Admin Layout -->
    <div class="flex h-screen">
        <?php include 'sidebar.php' ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Mobile Header -->
            <header class="admin-header text-white py-4 px-6 flex items-center justify-between md:hidden">
                <button class="text-white">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h1 class="text-xl font-bold">Filezone Админ</h1>
                <div>
                    <i class="fas fa-bell"></i>
                </div>
            </header>
            
            <!-- Admin Header -->
            <header class="bg-white shadow-sm py-4 px-6 hidden md:flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Ангилалын удирдлага</h2>
                    <p class="text-gray-600">
                        Нийт: <?php echo count($categories); ?> үндсэн ангилал, 
                        <?php echo count($subcategories); ?> дэд ангилал,
                        <?php echo count($child_categories); ?> жижиг ангилал
                    </p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <i class="fas fa-bell text-gray-600 text-xl"></i>
                        <span class="absolute top-0 right-0 bg-red-500 text-white rounded-full w-4 h-4 text-xs flex items-center justify-center">3</span>
                    </div>
                    <div class="flex items-center">
                        <img src="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=100&q=80" 
                        alt="Admin" class="w-10 h-10 rounded-full">
                        <span class="ml-3 text-gray-700">Админ хэрэглэгч</span>
                    </div>
                </div>
            </header>
            
            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto p-6 bg-gray-50 admin-content">
                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo $error; ?></span>
                    </div>
                <?php endif; ?>
                
                <!-- Categories Management -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-800">Үндсэн ангилалууд</h3>
                        <button onclick="openCategoryModal()" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        <i class="fas fa-plus mr-1"></i> Шинэ ангилал
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 data-table">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">№</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Нэр</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Товч тодорхойлолт англи үг</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дэд ангилал</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Файлууд</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Үйлдэл</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200" id="categories-list">
                            <?php foreach ($categories as $index => $category): ?>
                                <tr class="category-card">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <i class="fas fa-grip-lines text-gray-400 mr-2 sort-handle"></i>
                                        <span class="text-gray-900"><?php echo $index + 1; ?></span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="bg-purple-100 text-purple-600 p-2 rounded-full mr-3">
                                                <i class="<?php echo $category['icon_class']; ?>"></i>
                                            </div>
                                            <div>
                                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($category['name']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($category['slug']); ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900"><?php echo $category['subcategory_count']; ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900"><?php echo $category['file_count']; ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <button onclick="openEditCategoryModal(
                                            <?php echo $category['id']; ?>, 
                                            '<?php echo addslashes($category['name']); ?>', 
                                            '<?php echo addslashes($category['slug']); ?>', 
                                            '<?php echo addslashes($category['icon_class']); ?>'
                                            )" class="text-blue-600 hover:text-blue-900 mr-2">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" action="categories.php" class="inline" onsubmit="return confirm('Та энэ ангиллыг устгахдаа итгэлтэй байна уу? Дэд ангилалууд мөн устгагдана.');">
                                            <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                            <input type="hidden" name="delete_category" value="1">
                                            <button type="submit" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Subcategories Management -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">Дэд ангилалууд</h3>
                    <button onclick="openSubcategoryModal()" 
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <i class="fas fa-plus mr-1"></i> Шинэ дэд ангилал
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 data-table">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Үндсэн ангилал</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дэд ангилал</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Файлууд</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Үйлдэл</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200" id="subcategories-list">
                        <?php foreach ($subcategories as $subcategory): ?>
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="bg-purple-100 text-purple-600 p-2 rounded-full mr-3">
                                            <i class="<?php echo $subcategory['category_icon']; ?>"></i>
                                        </div>
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($subcategory['category_name']); ?></div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($subcategory['name']); ?></td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $subcategory_file_counts[$subcategory['id']] ?? 0; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <button onclick="openEditSubcategoryModal(<?php echo $subcategory['id']; ?>, <?php echo $subcategory['category_id']; ?>, '<?php echo htmlspecialchars($subcategory['name']); ?>')" 
                                        class="text-blue-600 hover:text-blue-900 mr-2">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" action="categories.php" class="inline" onsubmit="return confirm('Та энэ дэд ангиллыг устгахдаа итгэлтэй байна уу?');">
                                        <input type="hidden" name="id" value="<?php echo $subcategory['id']; ?>">
                                        <input type="hidden" name="delete_subcategory" value="1">
                                        <button type="submit" class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Child Categories Management -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Жижиг ангилалууд</h3>
                <button onclick="openChildCategoryModal()" 
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                <i class="fas fa-plus mr-1"></i> Шинэ жижиг ангилал
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 data-table">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Үндсэн ангилал</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дэд ангилал</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Жижиг ангилал</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Файлууд</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Үйлдэл</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200" id="child-categories-list">
                    <?php foreach ($child_categories as $child_category): ?>
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($child_category['category_name']); ?></div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($child_category['subcategory_name']); ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($child_category['name']); ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $child_category_file_counts[$child_category['id']] ?? 0; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <button onclick="openEditChildCategoryModal(<?php echo $child_category['id']; ?>, <?php echo $child_category['subcategory_id']; ?>, '<?php echo htmlspecialchars($child_category['name']); ?>')" 
                                    class="text-blue-600 hover:text-blue-900 mr-2">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" action="categories.php" class="inline" onsubmit="return confirm('Та энэ жижиг ангиллыг устгахдаа итгэлтэй байна уу?');">
                                    <input type="hidden" name="id" value="<?php echo $child_category['id']; ?>">
                                    <input type="hidden" name="delete_child_category" value="1">
                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</div>
</div>

<!-- Category Modal -->
<div id="category-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <div class="flex justify-between items-center border-b px-6 py-4">
            <h3 class="text-lg font-semibold text-gray-800" id="category-modal-title">Шинэ ангилал</h3>
            <button onclick="closeCategoryModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-6">
            <form id="category-form" method="POST" action="categories.php">
                <input type="hidden" name="id" id="category-id">
                <input type="hidden" name="add_category" id="add-category" value="1">
                <input type="hidden" name="update_category" id="update-category" value="0">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ангиллын нэр</label>
                    <input type="text" name="name" id="category-name" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Товч тодорхойлолт англи үг</label>
                    <input type="text" name="slug" id="category-slug" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Icon</label>
                    <input type="hidden" name="icon_class" id="category-icon" value="fas fa-folder">

                    <div class="grid grid-cols-8 gap-2 mb-2" id="icon-grid">
                        <!-- Common Categories -->
                        <button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
                        onclick="selectIcon('fas fa-graduation-cap')" title="Education">
                        <i class="fas fa-graduation-cap text-xl mb-1"></i>
                        <span class="text-xs">Edu</span>
                    </button>
                    <button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
                    onclick="selectIcon('fas fa-project-diagram')" title="Projects">
                    <i class="fas fa-project-diagram text-xl mb-1"></i>
                    <span class="text-xs">Project</span>
                </button>
                <button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
                onclick="selectIcon('fas fa-palette')" title="Design">
                <i class="fas fa-palette text-xl mb-1"></i>
                <span class="text-xs">Design</span>
            </button>
            <button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
            onclick="selectIcon('fas fa-code')" title="Programming">
            <i class="fas fa-code text-xl mb-1"></i>
            <span class="text-xs">Code</span>
        </button>
        <button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
        onclick="selectIcon('fas fa-file-contract')" title="Documents">
        <i class="fas fa-file-contract text-xl mb-1"></i>
        <span class="text-xs">Doc</span>
    </button>
    <button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
    onclick="selectIcon('fas fa-language')" title="Language">
    <i class="fas fa-language text-xl mb-1"></i>
    <span class="text-xs">Language</span>
</button>
<button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
onclick="selectIcon('fas fa-book')" title="Books">
<i class="fas fa-book text-xl mb-1"></i>
<span class="text-xs">Book</span>
</button>
<button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
onclick="selectIcon('fas fa-music')" title="Music">
<i class="fas fa-music text-xl mb-1"></i>
<span class="text-xs">Music</span>
</button>

<!-- Expanded Set -->
<button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
onclick="selectIcon('fas fa-film')" title="Movies">
<i class="fas fa-film text-xl mb-1"></i>
<span class="text-xs">Movie</span>
</button>
<button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
onclick="selectIcon('fas fa-laptop-code')" title="Development">
<i class="fas fa-laptop-code text-xl mb-1"></i>
<span class="text-xs">Dev</span>
</button>
<button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
onclick="selectIcon('fas fa-running')" title="Sports">
<i class="fas fa-running text-xl mb-1"></i>
<span class="text-xs">Sports</span>
</button>
<button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
onclick="selectIcon('fas fa-microchip')" title="Technology">
<i class="fas fa-microchip text-xl mb-1"></i>
<span class="text-xs">Tech</span>
</button>
<button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
onclick="selectIcon('fas fa-heartbeat')" title="Health">
<i class="fas fa-heartbeat text-xl mb-1"></i>
<span class="text-xs">Health</span>
</button>
<button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
onclick="selectIcon('fas fa-briefcase')" title="Business">
<i class="fas fa-briefcase text-xl mb-1"></i>
<span class="text-xs">Business</span>
</button>
<button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
onclick="selectIcon('fas fa-home')" title="Home">
<i class="fas fa-home text-xl mb-1"></i>
<span class="text-xs">Home</span>
</button>
<button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
onclick="selectIcon('fas fa-utensils')" title="Food">
<i class="fas fa-utensils text-xl mb-1"></i>
<span class="text-xs">Food</span>
</button>

<!-- More Specialized -->
<button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
onclick="selectIcon('fas fa-car')" title="Automotive">
<i class="fas fa-car text-xl mb-1"></i>
<span class="text-xs">Auto</span>
</button>
<button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
onclick="selectIcon('fas fa-plane')" title="Travel">
<i class="fas fa-plane text-xl mb-1"></i>
<span class="text-xs">Travel</span>
</button>
<button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
onclick="selectIcon('fas fa-gamepad')" title="Games">
<i class="fas fa-gamepad text-xl mb-1"></i>
<span class="text-xs">Games</span>
</button>
<button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
onclick="selectIcon('fas fa-shopping-cart')" title="Shopping">
<i class="fas fa-shopping-cart text-xl mb-1"></i>
<span class="text-xs">Shop</span>
</button>
<button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
onclick="selectIcon('fas fa-chart-line')" title="Finance">
<i class="fas fa-chart-line text-xl mb-1"></i>
<span class="text-xs">Finance</span>
</button>
<button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
onclick="selectIcon('fas fa-flask')" title="Science">
<i class="fas fa-flask text-xl mb-1"></i>
<span class="text-xs">Science</span>
</button>
<button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
onclick="selectIcon('fas fa-camera')" title="Photography">
<i class="fas fa-camera text-xl mb-1"></i>
<span class="text-xs">Photo</span>
</button>
<button type="button" class="p-2 rounded hover:bg-gray-100 flex flex-col items-center" 
onclick="selectIcon('fas fa-users')" title="Social">
<i class="fas fa-users text-xl mb-1"></i>
<span class="text-xs">Social</span>
</button>
</div>

<div class="flex items-center p-2 bg-gray-100 rounded mt-2">
    <div class="mr-3 text-purple-600">
        <i id="selected-icon-preview" class="fas fa-folder text-xl"></i>
    </div>
    <div>
        <span id="selected-icon-name" class="font-medium">Folder</span>
        <span id="selected-icon-class" class="text-xs text-gray-500 block">fas fa-folder</span>
    </div>
</div>
</div>
<div class="flex justify-end space-x-3">
    <button type="button" onclick="closeCategoryModal()" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">
        Цуцлах
    </button>
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
        Хадгалах
    </button>
</div>
</form>
</div>
</div>
</div>

<!-- Subcategory Modal -->
<div id="subcategory-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <div class="flex justify-between items-center border-b px-6 py-4">
            <h3 class="text-lg font-semibold text-gray-800" id="subcategory-modal-title">Шинэ дэд ангилал</h3>
            <button onclick="closeSubcategoryModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-6">
            <form id="subcategory-form" method="POST" action="categories.php">
                <input type="hidden" name="id" id="subcategory-id">
                <input type="hidden" name="add_subcategory" id="add-subcategory" value="1">
                <input type="hidden" name="update_subcategory" id="update-subcategory" value="0">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Үндсэн ангилал</label>
                    <select name="category_id" id="subcategory-category" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="">Сонгох</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Дэд ангиллын нэр</label>
                    <input type="text" name="name" id="subcategory-name" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeSubcategoryModal()" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">
                        Цуцлах
                    </button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        Хадгалах
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Child Category Modal -->
<div id="child-category-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <div class="flex justify-between items-center border-b px-6 py-4">
            <h3 class="text-lg font-semibold text-gray-800" id="child-category-modal-title">Шинэ жижиг ангилал</h3>
            <button onclick="closeChildCategoryModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-6">
            <form id="child-category-form" method="POST" action="categories.php">
                <input type="hidden" name="id" id="child-category-id">
                <input type="hidden" name="add_child_category" id="add-child-category" value="1">
                <input type="hidden" name="update_child_category" id="update-child-category" value="0">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Дэд ангилал</label>
                    <select name="subcategory_id" id="child-category-subcategory" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="">Сонгох</option>
                        <?php foreach ($subcategories as $subcategory): ?>
                            <option value="<?php echo $subcategory['id']; ?>"><?php echo htmlspecialchars($subcategory['category_name']); ?> - <?php echo htmlspecialchars($subcategory['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Жижиг ангиллын нэр</label>
                    <input type="text" name="name" id="child-category-name" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeChildCategoryModal()" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">
                        Цуцлах
                    </button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        Хадгалах
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Modal functions
    function openCategoryModal() {
        document.getElementById('category-modal').classList.remove('hidden');
        resetCategoryForm();
    }

    function closeCategoryModal() {
        document.getElementById('category-modal').classList.add('hidden');
    }

    function openSubcategoryModal() {
        document.getElementById('subcategory-modal').classList.remove('hidden');
        resetSubcategoryForm();
    }

    function closeSubcategoryModal() {
        document.getElementById('subcategory-modal').classList.add('hidden');
    }

    function openChildCategoryModal() {
        document.getElementById('child-category-modal').classList.remove('hidden');
        resetChildCategoryForm();
    }

    function closeChildCategoryModal() {
        document.getElementById('child-category-modal').classList.add('hidden');
    }

    // Edit functions
    function openEditCategoryModal(id, name, slug, iconClass) {
        document.getElementById('category-modal').classList.remove('hidden');
        document.getElementById('category-modal-title').textContent = 'Ангилал засах';
        document.getElementById('category-id').value = id;
        document.getElementById('category-name').value = name;
        document.getElementById('category-slug').value = slug;
        document.getElementById('category-icon').value = iconClass;
        document.getElementById('add-category').value = '0';
        document.getElementById('update-category').value = '1';
        
        // Update icon preview
        document.getElementById('selected-icon-preview').className = iconClass + ' text-xl';
        document.getElementById('selected-icon-class').textContent = iconClass;
        
        // Extract icon name for display
        const iconName = iconClass.split('fa-')[1]?.replace(/-/g, ' ') || 'Folder';
        document.getElementById('selected-icon-name').textContent = iconName.charAt(0).toUpperCase() + iconName.slice(1);
    }

    function openEditSubcategoryModal(id, categoryId, name) {
        document.getElementById('subcategory-modal').classList.remove('hidden');
        document.getElementById('subcategory-modal-title').textContent = 'Дэд ангилал засах';
        document.getElementById('subcategory-id').value = id;
        document.getElementById('subcategory-category').value = categoryId;
        document.getElementById('subcategory-name').value = name;
        document.getElementById('add-subcategory').value = '0';
        document.getElementById('update-subcategory').value = '1';
    }

    function openEditChildCategoryModal(id, subcategoryId, name) {
        document.getElementById('child-category-modal').classList.remove('hidden');
        document.getElementById('child-category-modal-title').textContent = 'Жижиг ангилал засах';
        document.getElementById('child-category-id').value = id;
        document.getElementById('child-category-subcategory').value = subcategoryId;
        document.getElementById('child-category-name').value = name;
        document.getElementById('add-child-category').value = '0';
        document.getElementById('update-child-category').value = '1';
    }

    // Reset form functions
    function resetCategoryForm() {
        document.getElementById('category-form').reset();
        document.getElementById('category-id').value = '';
        document.getElementById('add-category').value = '1';
        document.getElementById('update-category').value = '0';
        document.getElementById('category-modal-title').textContent = 'Шинэ ангилал';
        
        // Reset icon to default
        document.getElementById('category-icon').value = 'fas fa-folder';
        document.getElementById('selected-icon-preview').className = 'fas fa-folder text-xl';
        document.getElementById('selected-icon-name').textContent = 'Folder';
        document.getElementById('selected-icon-class').textContent = 'fas fa-folder';
    }

    function resetSubcategoryForm() {
        document.getElementById('subcategory-form').reset();
        document.getElementById('subcategory-id').value = '';
        document.getElementById('add-subcategory').value = '1';
        document.getElementById('update-subcategory').value = '0';
        document.getElementById('subcategory-modal-title').textContent = 'Шинэ дэд ангилал';
    }

    function resetChildCategoryForm() {
        document.getElementById('child-category-form').reset();
        document.getElementById('child-category-id').value = '';
        document.getElementById('add-child-category').value = '1';
        document.getElementById('update-child-category').value = '0';
        document.getElementById('child-category-modal-title').textContent = 'Шинэ жижиг ангилал';
    }

    // Icon selection function
    function selectIcon(iconClass) {
        document.getElementById('category-icon').value = iconClass;
        document.getElementById('selected-icon-preview').className = iconClass + ' text-xl';
        document.getElementById('selected-icon-class').textContent = iconClass;
        
        // Extract icon name for display
        const iconName = iconClass.split('fa-')[1]?.replace(/-/g, ' ') || 'Folder';
        document.getElementById('selected-icon-name').textContent = iconName.charAt(0).toUpperCase() + iconName.slice(1);
    }

    // Close modals when clicking outside
    document.addEventListener('click', function(event) {
        const categoryModal = document.getElementById('category-modal');
        const subcategoryModal = document.getElementById('subcategory-modal');
        const childCategoryModal = document.getElementById('child-category-modal');
        
        if (event.target === categoryModal) {
            closeCategoryModal();
        }
        if (event.target === subcategoryModal) {
            closeSubcategoryModal();
        }
        if (event.target === childCategoryModal) {
            closeChildCategoryModal();
        }
    });
</script>

</body>
</html>