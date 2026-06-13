<!DOCTYPE html>
<html lang="en" x-data="{ mobileMenuOpen: false, showTopButton: false }" 
      @scroll.window="showTopButton = (window.pageYOffset > 200)">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/css/main.css">
    <title><?php echo $__env->yieldContent('title', 'Manage Home'); ?></title>
    
    <script defer src="/js/alpine.min.js"></script>
    
    <?php echo $__env->yieldPushContent('styles'); ?>
</head>
<body class="antialiased text-slate-900 bg-white">
    <nav class="relative container mx-auto p-6">
        <div class="flex items-center justify-between">
            <a href="/" class="pt-2">
                <img src="/img/logo.svg" alt="Logo">
            </a>
            
            <div class="hidden md:flex space-x-6 items-center">
                <a href="/" class="hover:text-sky-900">Home</a>
                <a href="/calculator" class="hover:text-sky-900">Calculator</a>
                <a href="/buyers-guide" class="hover:text-sky-900">Buyer's Guide</a>
                <a href="/property-secrets" class="hover:text-sky-900">Property Secrets</a>
            </div>

            <a href="/apply" class="hidden md:block p-3 px-6 pt-2 text-white bg-red-600 rounded-full baseline hover:bg-red-500">Apply Now</a>

            <button @click="mobileMenuOpen = !mobileMenuOpen" class="block hamburger md:hidden focus:outline-none" :class="{ 'open': mobileMenuOpen }">
                <span class="hamburger-top"></span>
                <span class="hamburger-middle"></span>
                <span class="hamburger-bottom"></span>
            </button>
        </div>

        <div x-show="mobileMenuOpen" x-transition class="md:hidden">
            <div id="menu" class="absolute flex flex-col items-center py-8 mt-10 space-y-6 font-bold bg-white left-6 right-6 drop-shadow-md z-50">
                <a href="/" @click="mobileMenuOpen = false">Home</a>
                <a href="/calculator" @click="mobileMenuOpen = false">Calculator</a>
                <a href="/buyers-guide" @click="mobileMenuOpen = false">Buyer's Guide</a>
                <a href="/property-secrets" @click="mobileMenuOpen = false">Property Secrets</a>
                <a href="/apply" class="p-3 px-6 text-white bg-red-600 rounded-full text-center w-48" @click="mobileMenuOpen = false">Apply Now</a>
            </div>
        </div>
    </nav>

    <main>
        <?php echo $__env->yieldContent('content'); ?>
    </main>

    <button x-show="showTopButton" @click="window.scrollTo({top: 0, behavior: 'smooth'})" x-transition
        class="fixed z-50 bottom-8 right-8 flex items-center justify-center w-14 h-14 rounded-full shadow-lg bg-red-600 hover:bg-red-500 text-white transition duration-300 focus:outline-none"
        aria-label="Scroll back to top">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
        </svg>
    </button>

    <footer class="bg-sky-900">
        <div class="container flex flex-col-reverse justify-between px-6 py-10 mx-auto space-y-8 md:flex-row md:space-y-0">
            <div class="flex flex-col-reverse items-center justify-between space-y-12 md:flex-col md:space-y-0 md:items-start">
                <div class="mx-auto my-6 text-center text-white md:hidden">
                    Copyright &copy; <?php echo e(date('Y')); ?>, All Rights Reserved
                </div>
                <div>
                    <a href="/">
                        <img src="/img/logo-white.svg" class="h-8" alt="Manage Logo">
                    </a>
                </div>
                <div class="flex justify-center space-x-4">
                    <a href="#" class="hover:opacity-80"><img src="/img/icon-facebook.svg" alt="Facebook" class="h-8"></a>
                    <a href="#" class="hover:opacity-80"><img src="/img/icon-youtube.svg" alt="YouTube" class="h-8"></a>
                    <a href="#" class="hover:opacity-80"><img src="/img/icon-twitter.svg" alt="Twitter" class="h-8"></a>
                    <a href="#" class="hover:opacity-80"><img src="/img/icon-pinterest.svg" alt="Pinterest" class="h-8"></a>
                    <a href="#" class="hover:opacity-80"><img src="/img/icon-instagram.svg" alt="Instagram" class="h-8"></a>
                </div>
            </div>

            <div class="flex justify-around space-x-20">
                <div class="flex flex-col space-y-3 text-white">
                    <a href="/" class="hover:text-red-600">Home</a>
                    <a href="/calculator" class="hover:text-red-600">Calculator</a>
                    <a href="/buyers-guide" class="hover:text-red-600">Buyer's Guide</a>
                    <a href="/property-secrets" class="hover:text-red-600">Property Secrets</a>
                </div>
                <div class="flex flex-col space-y-3 text-white">
                    <a href="https://github.com/monatemedia/bond-originator" target="_blank" class="hover:text-red-600">Github</a>
                    <a href="https://www.monatemedia.com/" target="_blank" class="hover:text-red-600">Developer</a>
                    <a href="https://www.linkedin.com/in/edwardbaitsewe/" target="_blank" class="hover:text-red-600">LinkedIn</a>
                </div>
            </div>

            <div class="flex flex-col justify-between">
                <form name="subscriberForm" id="subscriberForm" method="POST" action="/subscribe">
                    <div class="inline-flex md:flex justify-items-center space-x-3 pb-8">
                        <input type="email" name="email" id="subscriber" class="flex-1 px-4 rounded-full focus:outline-none text-slate-900" placeholder="Updates in your inbox" required />
                        <button class="px-5 md:px-6 py-2 text-white rounded-full bg-red-600 hover:bg-red-500 focus:outline-none" type="submit">Go</button>
                    </div>
                </form>
                <div class="hidden text-white md:block">
                    Copyright &copy; <?php echo e(date('Y')); ?>, All Rights Reserved
                </div>
            </div>
        </div>
    </footer>

    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html><?php /**PATH C:\xampp\htdocs\slim-killer\resources\views/layouts/main.blade.php ENDPATH**/ ?>