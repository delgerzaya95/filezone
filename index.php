<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Include configuration and functions


// Set page title
$pageTitle = "Filezone.mn - Файлын дэлгүүр";

// Include header
include 'includes/header.php';

// Include navigation
include 'includes/navigation.php';
?>
<!-- Main Content -->
<main class="container mx-auto px-4 py-6">
    <div class="flex flex-col lg:flex-row gap-6">
        <!-- Left Sidebar - Categories Only -->
        
        <?php include 'includes/aside.php'; ?>

        <!-- Main Content Area - 3 Columns -->
        <?php include 'includes/main_area.php'; ?>
    </div>
</main>

<?php include 'includes/footer.php'; ?>

<script>
        // Simple JavaScript for interactive elements
    document.addEventListener('DOMContentLoaded', function() {
            // Add hover effect to popular categories
        const popularCategories = document.querySelectorAll('.popular-category');
        popularCategories.forEach(category => {
            category.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(5px)';
            });

            category.addEventListener('mouseleave', function() {
                this.style.transform = 'translateX(0)';
            });
        });

            // Mobile menu toggle would go here
        console.log('Filezone.mn - Файлын дэлгүүр');
    });
</script>
</body>
</html>