<!-- Sidebar -->

<div class="admin-sidebar w-64 text-white flex-shrink-0 hidden md:block">

    <div class="p-5 border-b border-gray-700">

        <h1 class="text-2xl font-bold flex items-center">

            <i class="fas fa-crown mr-2 text-yellow-400"></i>

            Filezone Админ

        </h1>

        <p class="text-gray-400 text-sm mt-1">Файлын платформын удирдлага</p>

    </div>



    <nav class="mt-6">

        <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'nav-active' : ''; ?> flex items-center px-6 py-3 text-white hover:bg-gray-700">

            <i class="fas fa-tachometer-alt mr-3"></i>

            Хянах самбар

        </a>

        <a href="users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'nav-active' : ''; ?> flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700">

            <i class="fas fa-users mr-3"></i>

            Хэрэглэгчид

        </a>

        <a href="files.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'files.php' ? 'nav-active' : ''; ?> flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700">

            <i class="fas fa-file-alt mr-3"></i>

            Файлууд

        </a>

        <a href="categories.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'nav-active' : ''; ?> flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700">

            <i class="fas fa-folder mr-3"></i>

            Ангилалууд

        </a>

        <a href="guides.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'guides.php' ? 'nav-active' : ''; ?> flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700">
            <i class="fas fa-book-reader mr-3"></i>
            Зааврууд
        </a>

        <a href="transactions.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'transactions.php' ? 'nav-active' : ''; ?> flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700">

            <i class="fas fa-shopping-cart mr-3"></i>

            Гүйлгээнүүд

        </a>

        <a href="withdrawals.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'withdrawals.php' ? 'nav-active' : ''; ?> flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700">
            <i class="fas fa-wallet mr-3"></i> Мөнгө татах
        </a>

        <a href="comments.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'comments.php' ? 'nav-active' : ''; ?> flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700">

            <i class="fas fa-comments mr-3"></i>

            Сэтгэгдлүүд

        </a>

        <a href="statistics.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'statistics.php' ? 'nav-active' : ''; ?> flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700">

            <i class="fas fa-chart-line mr-3"></i>

            Статистик

        </a>

        <a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'nav-active' : ''; ?> flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700 mt-4">

            <i class="fas fa-cog mr-3"></i>

            Тохиргоо

        </a>

    </nav>



    <div class="absolute bottom-0 w-64 p-4 border-t border-gray-700">

        <div class="flex items-center">

            <img src="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=100&q=80" 

            alt="Admin" class="w-10 h-10 rounded-full">

            <div class="ml-3">

                <p class="text-white font-medium">Админ хэрэглэгч</p>

                <p class="text-gray-400 text-sm">Системийн админ</p>

            </div>

        </div>

        <a href="logout.php" class="mt-4 block text-center bg-gray-700 text-white py-2 rounded-md hover:bg-gray-600">

            <i class="fas fa-sign-out-alt mr-2"></i> Гарах

        </a>

    </div>

</div>