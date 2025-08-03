<?php
// This file is included in other PHP files, so session_start() and database connection
// should be handled by the parent file.
// $admin_info should be passed or fetched in the parent file.
?>

<style>
/* Enhanced Header Design */
.header-nav {
    background: linear-gradient(135deg, #0A3167 0%, #082850 100%);
    backdrop-filter: blur(10px);
}

.logo-container {
    transition: transform 0.3s ease;
}



.logo-img {
    border: 2px solid rgba(255, 255, 255, 0.2);
}



.company-name {
    background: linear-gradient(45deg, #ffffff, #e5e7eb);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.user-menu-button {
    transition: all 0.3s ease;
    border-radius: 12px;
    padding: 8px 12px;
    position: relative;
    overflow: hidden;
}

.user-menu-button::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    transition: left 0.6s ease;
}

.user-menu-button:hover::before {
    left: 100%;
}

.user-menu-button:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.user-avatar {
    background: linear-gradient(135deg, #0A3167, #082850);
    border: 2px solid rgba(255, 255, 255, 0.3);
    transition: all 0.3s ease;
}

.user-menu-button:hover .user-avatar {
    border-color: rgba(255, 255, 255, 0.6);
    transform: scale(1.1);
}

.dropdown-arrow {
    transition: transform 0.3s ease;
}


.user-dropdown {
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.dropdown-item {
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.dropdown-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(10, 49, 103, 0.3), transparent);
    transition: left 0.5s ease;
}

.dropdown-item:hover::before {
    left: 100%;
}

.dropdown-item:hover {
    background: #0A3167 !important;
    transform: translateX(5px);
}

.dropdown-item i {
    transition: transform 0.3s ease;
}


.header-shadow {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}
</style>

<!-- Header -->
<nav class="header-nav header-shadow bg-primary text-white p-2 fixed w-full top-0 z-50">
    <div class="container mx-auto flex justify-between items-center">
        <!-- Logo & Title -->
        <a href="/dashboard.php" class="logo-container flex items-center text-xl font-bold">
            <img src="https://nutrinexas.shop/logo.svg" alt="Logo" class="logo-img h-10 w-10 mr-3 rounded-full">
            <span class="company-name italic">JNK Suppliers</span>
        </a>
        
        <!-- User Menu -->
        <div class="relative">
            <button id="userMenuButton" class="user-menu-button flex items-center text-gray-300 hover:text-white focus:outline-none">
                <div class="user-avatar w-8 h-8 rounded-full flex items-center justify-center mr-2">
                    <i class="fas fa-user text-white"></i>
                </div>
                <span class="font-medium"><?php echo htmlspecialchars($admin_info['name'] ?? 'Guest'); ?></span>
                <svg class="dropdown-arrow w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                           d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            
            <!-- Dropdown -->
            <ul id="userMenuDropdown" class="user-dropdown absolute right-0 mt-2 w-48 rounded-lg shadow-xl py-2 hidden">
                <li>
                    <a href="#" class="dropdown-item block px-4 py-3 text-gray-300 hover:text-white">
                        <i class="fas fa-user mr-3"></i> Profile
                    </a>
                </li>
                <li>
                    <a href="#" class="dropdown-item block px-4 py-3 text-gray-300 hover:text-white">
                        <i class="fas fa-cog mr-3"></i> Settings
                    </a>
                </li>
                <li><hr class="border-gray-600 my-2 mx-2"></li>
                <li>
                    <a href="/logout.php" class="dropdown-item block px-4 py-3 text-gray-300 hover:text-white">
                        <i class="fas fa-sign-out-alt mr-3"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Tailwind Config -->
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: {
                        DEFAULT: '#0A3167',
                        dark: '#082850'
                    },
                    accent: {
                        DEFAULT: '#C5A572',
                        dark: '#B89355'
                    }
                },
                fontFamily: {
                    heading: ['Playfair Display', 'serif'],
                    body: ['Jalla One', 'sans-serif'], // corrected font name
                }
            }
        }
    }
</script>

<!-- Dropdown Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const userButton = document.getElementById('userMenuButton');
    const dropdown = document.getElementById('userMenuDropdown');
    
    userButton.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.toggle('hidden');
    });
    
    document.addEventListener('click', function(e) {
        if (!dropdown.contains(e.target) && !userButton.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
});
</script>

<!-- Font Awesome -->
<link rel="stylesheet"
       href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">