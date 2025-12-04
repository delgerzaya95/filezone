<?php
require_once 'includes/header.php';
require_once 'includes/navigation.php';
?>

<main class="container mx-auto px-4 py-6">
    <div class="flex flex-col lg:flex-row gap-6">
        <?php 
        // Хэрэв танд sidebar.php гэж файл байдаг бол aside.php-г түүгээр солиорой.
        // Өмнөх файлуудад aside.php байсан тул үүнийг ашиглав.
        if (file_exists('includes/aside.php')) {
            require_once 'includes/aside.php';
        } elseif (file_exists('includes/sidebar.php')) {
            require_once 'includes/sidebar.php';
        }
        ?>
        
        <div class="w-full lg:w-3/4">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Бидэнтэй холбогдох</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    
                    <div class="md:col-span-2 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Шуурхай холбоо</h3>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                             <div class="flex flex-col sm:flex-row items-center text-center sm:text-left">
                                 <div class="flex-shrink-0 text-blue-600 mb-4 sm:mb-0">
                                     <i class="fab fa-facebook-messenger text-5xl"></i>
                                 </div>
                                 <div class="ml-0 sm:ml-5 flex-grow">
                                     <p class="font-semibold text-gray-800 text-lg">Facebook Messenger</p>
                                     <p class="text-gray-600 text-sm mt-1">Асуултаа шууд илгээж, бидэнтэй чатаар холбогдоорой.</p>
                                 </div>
                                 <div class="ml-0 sm:ml-4 mt-4 sm:mt-0">
                                      <a href="https://m.me/396730021162964" target="_blank" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-5 rounded-lg inline-flex items-center transition-transform transform hover:scale-105 shadow-lg">
                                          <i class="fas fa-paper-plane mr-2"></i> Чат бичих
                                      </a>
                                 </div>
                             </div>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Холбоо барих мэдээлэл</h3>
                        <div class="space-y-4">
                            <div class="flex items-start">
                                <div class="flex-shrink-0 text-purple-600 mt-1">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-gray-600">Улаанбаатар хот, БЗД, 15-р хороо, 72-36</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <div class="flex-shrink-0 text-purple-600 mt-1">
                                    <i class="fas fa-phone-alt"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-gray-600">+976 5514-5313</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <div class="flex-shrink-0 text-purple-600 mt-1">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-gray-600">info@filezone.mn</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <div class="flex-shrink-0 text-purple-600 mt-1">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-gray-600">Даваа - Баасан: 09:00 - 18:00</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Санал хүсэлт илгээх</h3>
                        <form action="pages/process-contact.php" method="POST" class="space-y-4">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Нэр</label>
                                <input type="text" id="name" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-600">
                            </div>
                            
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Имэйл</label>
                                <input type="email" id="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-600">
                            </div>
                            
                            <div>
                                <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Гарчиг</label>
                                <input type="text" id="subject" name="subject" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-600">
                            </div>
                            
                            <div>
                                <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Агуулга</label>
                                <textarea id="message" name="message" rows="4" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-600"></textarea>
                            </div>
                            
                            <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-paper-plane mr-1"></i> Илгээх
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Бидний байршил</h3>
                <div class="aspect-w-16 aspect-h-9">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2674.123456789012!2d106.12345678901234!3d47.12345678901234!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zNDfCsDA3JzI0LjQiTiAxMDbCsDA3JzI0LjQiRQ!5e0!3m2!1sen!2smn!4v1234567890123!5m2!1sen!2smn" 
                            width="100%" height="400" style="border:0;" allowfullscreen="" loading="lazy" class="rounded-md"></iframe>
                </div>
            </div> -->
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>