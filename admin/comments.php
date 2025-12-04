<?php
// Database connection
$conn = mysqli_connect("localhost", "filezone_mn", "099da7e85a2688", "filezone_mn");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Approve comment
    if (isset($_POST['approve_comment'])) {
        $comment_id = intval($_POST['comment_id']);
        $sql = "UPDATE comments SET status = 'approved' WHERE id = $comment_id";
        
        if (mysqli_query($conn, $sql)) {
            header("Location: comments.php");
            exit();
        } else {
            $error = "Error approving comment: " . mysqli_error($conn);
        }
    }
    
    // Reject comment
    if (isset($_POST['reject_comment'])) {
        $comment_id = intval($_POST['comment_id']);
        $sql = "UPDATE comments SET status = 'rejected' WHERE id = $comment_id";
        
        if (mysqli_query($conn, $sql)) {
            header("Location: comments.php");
            exit();
        } else {
            $error = "Error rejecting comment: " . mysqli_error($conn);
        }
    }
    
    // Delete comment
    if (isset($_POST['delete_comment'])) {
        $comment_id = intval($_POST['comment_id']);
        $sql = "DELETE FROM comments WHERE id = $comment_id";
        
        if (mysqli_query($conn, $sql)) {
            header("Location: comments.php");
            exit();
        } else {
            $error = "Error deleting comment: " . mysqli_error($conn);
        }
    }
    
    // Add reply
    if (isset($_POST['add_reply'])) {
        $comment_id = intval($_POST['comment_id']);
        $reply_text = mysqli_real_escape_string($conn, $_POST['reply_text']);
        $user_id = 1; // Assuming admin user ID is 1
        
        // Get file_id from the parent comment
        $file_sql = "SELECT file_id FROM comments WHERE id = $comment_id";
        $file_result = mysqli_query($conn, $file_sql);
        $file_row = mysqli_fetch_assoc($file_result);
        $file_id = $file_row['file_id'];
        
        $sql = "INSERT INTO comments (user_id, file_id, comment, parent_comment_id, status) 
                VALUES ($user_id, $file_id, '$reply_text', $comment_id, 'approved')";
        
        if (mysqli_query($conn, $sql)) {
            header("Location: comments.php");
            exit();
        } else {
            $error = "Error adding reply: " . mysqli_error($conn);
        }
    }
}

// Search and filter functionality
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

// Base query to get comments with user and file info
$sql = "SELECT c.*, 
               u.username, u.full_name as user_full_name, u.avatar_url as user_avatar,
               f.title as file_title
        FROM comments c
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN files f ON c.file_id = f.id
        WHERE c.parent_comment_id IS NULL";

// Apply filters
if (!empty($search)) {
    $sql .= " AND (c.comment LIKE '%$search%' OR u.username LIKE '%$search%' OR f.title LIKE '%$search%')";
}

if ($filter_status === 'approved') {
    $sql .= " AND c.status = 'approved'";
} elseif ($filter_status === 'pending') {
    $sql .= " AND c.status = 'pending'";
} elseif ($filter_status === 'rejected') {
    $sql .= " AND c.status = 'rejected'";
}

$sql .= " ORDER BY c.comment_date DESC";

// Get total count for pagination
$count_result = mysqli_query($conn, $sql);
$total_comments = mysqli_num_rows($count_result);

// Pagination
$per_page = 10;
$total_pages = ceil($total_comments / $per_page);
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

$sql .= " LIMIT $offset, $per_page";
$result = mysqli_query($conn, $sql);

// Get all comments for stats
$stats_sql = "SELECT 
                COUNT(*) as total_comments,
                SUM(status = 'approved') as approved_comments,
                SUM(status = 'pending') as pending_comments,
                SUM(parent_comment_id IS NOT NULL) as reply_comments
              FROM comments";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Function to get replies for a comment
function getReplies($conn, $comment_id) {
    $sql = "SELECT c.*, 
                   u.username, u.full_name as user_full_name, u.avatar_url as user_avatar
            FROM comments c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.parent_comment_id = $comment_id
            ORDER BY c.comment_date ASC";
    $result = mysqli_query($conn, $sql);
    $replies = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $replies[] = $row;
    }
    return $replies;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>НАРХАН - Сэтгэгдлийн удирдлага</title>
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
        <!-- Sidebar -->
        <?php include 'sidebar.php' ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Mobile Header -->
            <header class="admin-header text-white py-4 px-6 flex items-center justify-between md:hidden">
                <button class="text-white">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h1 class="text-xl font-bold">НАРХАН Админ</h1>
                <div>
                    <i class="fas fa-bell"></i>
                </div>
            </header>
            
            <!-- Admin Header -->
            <header class="bg-white shadow-sm py-4 px-6 hidden md:flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Сэтгэгдлийн удирдлага</h2>
                    <p class="text-gray-600">Өнөөдөр: <?php echo date('Y оны m сарын d'); ?></p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <i class="fas fa-bell text-gray-600 text-xl"></i>
                        <span class="absolute top-0 right-0 bg-red-500 text-white rounded-full w-4 h-4 text-xs flex items-center justify-center"><?php echo $stats['pending_comments']; ?></span>
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
                
                <!-- Stats Overview -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div class="stat-card bg-white rounded-lg shadow-md p-6">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-gray-500">Нийт сэтгэгдэл</p>
                                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['total_comments']); ?></p>
                            </div>
                            <div class="bg-blue-100 text-blue-600 p-3 rounded-full">
                                <i class="fas fa-comments text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card bg-white rounded-lg shadow-md p-6">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-gray-500">Зөвшөөрөгдсөн</p>
                                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['approved_comments']); ?></p>
                            </div>
                            <div class="bg-green-100 text-green-600 p-3 rounded-full">
                                <i class="fas fa-check-circle text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card bg-white rounded-lg shadow-md p-6">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-gray-500">Хариу сэтгэгдэл</p>
                                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['reply_comments']); ?></p>
                            </div>
                            <div class="bg-purple-100 text-purple-600 p-3 rounded-full">
                                <i class="fas fa-reply text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card bg-white rounded-lg shadow-md p-6">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-gray-500">Хүлээгдэж буй</p>
                                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['pending_comments']); ?></p>
                            </div>
                            <div class="bg-yellow-100 text-yellow-600 p-3 rounded-full">
                                <i class="fas fa-clock text-2xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Comments Management -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-800">Сэтгэгдлүүд</h3>
                        <div class="flex space-x-2">
                            <form method="GET" action="comments.php" class="relative">
                                <input type="text" name="search" placeholder="Сэтгэгдэл хайх..." 
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       class="border border-gray-300 rounded-md py-2 px-4 pl-10 w-64 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            </form>
                            <select name="status" onchange="this.form.submit()" class="border border-gray-300 rounded-md py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Бүгд</option>
                                <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Зөвшөөрөгдсөн</option>
                                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Хүлээгдэж буй</option>
                                <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Хасагдсан</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Comments List -->
                    <div class="space-y-4">
                        <?php while ($comment = mysqli_fetch_assoc($result)): ?>
                        <div class="comment-card bg-white border border-gray-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <img src="css/images/default_avatar.png" 
                                     alt="User" class="w-10 h-10 rounded-full mr-3">
                                <div class="flex-1">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-semibold text-gray-800"><?php echo htmlspecialchars($comment['user_full_name'] ?: $comment['username']); ?></h4>
                                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($comment['file_title']); ?></p>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-xs text-gray-500"><?php echo date('Y-m-d H:i', strtotime($comment['comment_date'])); ?></span>
                                            <?php if ($comment['status'] === 'approved'): ?>
                                                <span class="badge-approved px-2 py-1 text-xs rounded-full">Зөвшөөрөгдсөн</span>
                                            <?php elseif ($comment['status'] === 'pending'): ?>
                                                <span class="badge-pending px-2 py-1 text-xs rounded-full">Хүлээгдэж буй</span>
                                            <?php else: ?>
                                                <span class="badge-rejected px-2 py-1 text-xs rounded-full">Хасагдсан</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <p class="text-gray-700 mt-2"><?php echo htmlspecialchars($comment['comment']); ?></p>
                                    <div class="flex space-x-3 mt-3">
                                        <button onclick="openReplyModal(<?php echo $comment['id']; ?>)" 
                                                class="text-blue-600 hover:text-blue-800 text-sm">
                                            <i class="fas fa-reply mr-1"></i> Хариулах
                                        </button>
                                        
                                        <?php if ($comment['status'] !== 'approved'): ?>
                                        <form method="POST" action="comments.php" class="inline">
                                            <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                            <button type="submit" name="approve_comment" 
                                                    class="text-green-600 hover:text-green-800 text-sm">
                                                <i class="fas fa-check mr-1"></i> Зөвшөөрөх
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($comment['status'] !== 'rejected'): ?>
                                        <form method="POST" action="comments.php" class="inline">
                                            <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                            <button type="submit" name="reject_comment" 
                                                    class="text-red-600 hover:text-red-800 text-sm">
                                                <i class="fas fa-times mr-1"></i> Татгалзах
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        
                                        <form method="POST" action="comments.php" class="inline" 
                                              onsubmit="return confirm('Та энэ сэтгэгдлийг устгахдаа итгэлтэй байна уу?');">
                                            <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                            <button type="submit" name="delete_comment" 
                                                    class="text-red-600 hover:text-red-800 text-sm">
                                                <i class="fas fa-trash mr-1"></i> Устгах
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <!-- Replies -->
                                    <?php 
                                    $replies = getReplies($conn, $comment['id']);
                                    foreach ($replies as $reply): ?>
                                    <div class="mt-4 ml-10 pl-4 border-l-2 border-gray-200">
                                        <div class="flex items-start">
                                            <img src="<?php echo htmlspecialchars($reply['user_avatar'] ?: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=100&q=80'); ?>" 
                                                 alt="User" class="w-8 h-8 rounded-full mr-2">
                                            <div class="flex-1">
                                                <div class="flex justify-between items-start">
                                                    <h4 class="font-semibold text-gray-800"><?php echo htmlspecialchars($reply['user_full_name'] ?: $reply['username']); ?></h4>
                                                    <span class="text-xs text-gray-500"><?php echo date('Y-m-d H:i', strtotime($reply['comment_date'])); ?></span>
                                                </div>
                                                <p class="text-gray-700 mt-1"><?php echo htmlspecialchars($reply['comment']); ?></p>
                                                <div class="mt-2">
                                                    <form method="POST" action="comments.php" class="inline" 
                                                          onsubmit="return confirm('Та энэ хариуг устгахдаа итгэлтэй байна уу?');">
                                                        <input type="hidden" name="comment_id" value="<?php echo $reply['id']; ?>">
                                                        <button type="submit" name="delete_comment" 
                                                                class="text-red-600 hover:text-red-800 text-xs">
                                                            <i class="fas fa-trash mr-1"></i> Устгах
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="mt-6 flex justify-center">
                        <nav class="flex items-center space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="comments.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filter_status); ?>" 
                                   class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Өмнөх
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="comments.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filter_status); ?>" 
                                   class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium <?php echo $i == $page ? 'text-white pagination-active' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="comments.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filter_status); ?>" 
                                   class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Дараагийн
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Reply Modal -->
    <div id="replyModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Хариу бичих</h3>
                <button onclick="document.getElementById('replyModal').classList.add('hidden')" 
                        class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" action="comments.php">
                <input type="hidden" name="comment_id" id="reply_comment_id">
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="reply_text">Хариу</label>
                    <textarea name="reply_text" id="reply_text" rows="3" required
                              class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                </div>
                
                <div class="flex justify-end">
                    <button type="button" onclick="document.getElementById('replyModal').classList.add('hidden')" 
                            class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2">
                        Цуцлах
                    </button>
                    <button type="submit" name="add_reply" 
                            class="gradient-bg text-white font-bold py-2 px-4 rounded hover:bg-purple-700">
                        Илгээх
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Simple JavaScript for interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileMenuBtn = document.querySelector('.md\\:hidden button');
            const sidebar = document.querySelector('.admin-sidebar');
            
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('hidden');
                });
            }
        });
        
        function openReplyModal(commentId) {
            document.getElementById('reply_comment_id').value = commentId;
            document.getElementById('replyModal').classList.remove('hidden');
            document.getElementById('reply_text').focus();
        }
    </script>
</body>
</html>
<?php
mysqli_close($conn);
?>