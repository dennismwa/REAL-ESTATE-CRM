<?php
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config.php';
}

$settings = getSettings();
$userRole = getUserRole();
$userName = getUserName();

// Get unread notifications count
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([getUserId()]);
$unreadNotifications = $stmt->fetch()['count'];

// Check if user is clocked in today
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND DATE(clock_in) = CURDATE() AND clock_out IS NULL");
$stmt->execute([getUserId()]);
$isClockedIn = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? sanitize($pageTitle) . ' - ' : ''; ?><?php echo sanitize($settings['site_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: <?php echo $settings['primary_color']; ?>;
            --secondary-color: <?php echo $settings['secondary_color']; ?>;
        }
        
        .bg-primary { background-color: var(--primary-color); }
        .bg-secondary { background-color: var(--secondary-color); }
        .text-primary { color: var(--primary-color); }
        .text-secondary { color: var(--secondary-color); }
        .border-primary { border-color: var(--primary-color); }
        .hover\:bg-primary:hover { background-color: var(--primary-color); }
        
        .sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        
        .sidebar.active {
            transform: translateX(0);
        }
        
        @media (min-width: 768px) {
            .sidebar {
                transform: translateX(0);
            }
        }
        
        .mobile-bottom-nav {
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        }
        
        html {
            scroll-behavior: smooth;
        }
        
        .dropdown {
            display: none;
        }
        
        .dropdown.show {
            display: block;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Top Header -->
    <header class="bg-white shadow-sm fixed top-0 left-0 right-0 z-40">
        <div class="flex items-center justify-between px-4 py-3">
            <!-- Mobile Menu Toggle & Logo -->
            <div class="flex items-center space-x-3">
                <button onclick="toggleSidebar()" class="md:hidden text-gray-600 hover:text-gray-900">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <div class="flex items-center space-x-2">
                    <img src="<?php echo $settings['logo_path']; ?>" alt="Logo" class="h-8 w-8">
                    <span class="font-bold text-lg hidden sm:block" style="color: var(--primary-color);">
                        <?php echo sanitize($settings['site_name']); ?>
                    </span>
                </div>
            </div>
            
            <!-- Right Section -->
            <div class="flex items-center space-x-3">
                <!-- Clock In/Out Status -->
                <?php if (hasPermission('attendance', 'create')): ?>
                <div class="hidden sm:block">
                    <?php if ($isClockedIn): ?>
                        <span class="text-xs px-3 py-1 rounded-full bg-green-100 text-green-800 font-semibold">
                            <i class="fas fa-clock mr-1"></i> Clocked In
                        </span>
                    <?php else: ?>
                        <span class="text-xs px-3 py-1 rounded-full bg-gray-100 text-gray-600">
                            <i class="fas fa-clock mr-1"></i> Not Clocked In
                        </span>
                    <?php endif; ?>

<script>
// Toggle Sidebar
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    sidebar.classList.toggle('active');
    overlay.classList.toggle('hidden');
}

// Toggle User Menu
function toggleUserMenu(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('userDropdown');
    const notifDropdown = document.getElementById('notificationsDropdown');
    
    // Close notifications if open
    notifDropdown.classList.remove('show');
    
    // Toggle user menu
    dropdown.classList.toggle('show');
}

// Toggle Notifications
function toggleNotifications(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('notificationsDropdown');
    const userDropdown = document.getElementById('userDropdown');
    
    // Close user menu if open
    userDropdown.classList.remove('show');
    
    // Toggle notifications
    dropdown.classList.toggle('show');
    
    if (dropdown.classList.contains('show')) {
        loadNotifications();
    }
}

// Load Notifications
function loadNotifications() {
    const list = document.getElementById('notificationsList');
    list.innerHTML = '<p class="p-4 text-center text-gray-500 text-sm">Loading...</p>';
    
    fetch('/api/notifications.php')
        .then(res => {
            if (!res.ok) {
                throw new Error('HTTP error ' + res.status);
            }
            return res.json();
        })
        .then(data => {
            console.log('Notifications response:', data); // Debug log
            
            if (!data.success) {
                list.innerHTML = '<p class="p-4 text-center text-red-500 text-sm">Error: ' + (data.message || 'Unknown error') + '</p>';
                return;
            }
            
            if (!data.notifications || data.notifications.length === 0) {
                list.innerHTML = '<p class="p-4 text-center text-gray-500 text-sm">No notifications</p>';
                return;
            }
            
            list.innerHTML = data.notifications.map(notif => `
                <a href="${notif.link || '#'}" class="block p-3 hover:bg-gray-50 border-b border-gray-100 ${!notif.is_read ? 'bg-blue-50' : ''}" onclick="markAsRead(${notif.id})">
                    <p class="font-semibold text-sm">${notif.title}</p>
                    <p class="text-xs text-gray-600 mt-1">${notif.message}</p>
                    <p class="text-xs text-gray-500 mt-1">${notif.time_ago}</p>
                </a>
            `).join('');
        })
        .catch(err => {
            console.error('Error loading notifications:', err);
            list.innerHTML = '<p class="p-4 text-center text-red-500 text-sm">Error loading notifications. Check console.</p>';
        });
}

// Mark Notification as Read
function markAsRead(notificationId) {
    fetch('/api/notifications/mark-read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id: notificationId })
    });
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    const userContainer = document.querySelector('.user-container');
    const notifContainer = document.querySelector('.notification-container');
    const userDropdown = document.getElementById('userDropdown');
    const notifDropdown = document.getElementById('notificationsDropdown');
    
    if (!userContainer.contains(event.target)) {
        userDropdown.classList.remove('show');
    }
    
    if (!notifContainer.contains(event.target)) {
        notifDropdown.classList.remove('show');
    }
});

// Auto-hide flash messages
setTimeout(() => {
    const flash = document.getElementById('flashMessage');
    if (flash) {
        flash.style.transition = 'opacity 0.5s';
        flash.style.opacity = '0';
        setTimeout(() => flash.remove(), 500);
    }
}, 5000);
</script>
                </div>
                <?php endif; ?>
                
                <!-- Notifications -->
                <div class="relative notification-container">
                    <button onclick="toggleNotifications(event)" class="relative text-gray-600 hover:text-gray-900">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <?php if ($unreadNotifications > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                <?php echo $unreadNotifications > 9 ? '9+' : $unreadNotifications; ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    
                    <!-- Notifications Dropdown -->
                    <div id="notificationsDropdown" class="dropdown absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200">
                        <div class="p-3 border-b border-gray-200 flex items-center justify-between">
                            <h3 class="font-semibold">Notifications</h3>
                            <a href="/notifications.php" class="text-xs text-primary hover:underline">View All</a>
                        </div>
                        <div id="notificationsList" class="max-h-96 overflow-y-auto">
                            <div class="p-4 text-center text-gray-500">Loading...</div>
                        </div>
                    </div>
                </div>
                
                <!-- User Menu -->
                <div class="relative user-container">
                    <button onclick="toggleUserMenu(event)" class="flex items-center space-x-2">
                        <div class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-semibold">
                            <?php echo strtoupper(substr($userName, 0, 1)); ?>
                        </div>
                        <svg class="w-4 h-4 text-gray-600 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    
                    <!-- User Dropdown -->
                    <div id="userDropdown" class="dropdown absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200">
                        <div class="p-3 border-b border-gray-200">
                            <p class="font-semibold text-sm"><?php echo sanitize($userName); ?></p>
                            <p class="text-xs text-gray-600"><?php echo ucfirst(str_replace('_', ' ', $userRole)); ?></p>
                        </div>
                        <a href="/profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-user mr-2"></i> My Profile
                        </a>
                        <?php if (hasPermission('settings', 'view')): ?>
                        <a href="/settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-cog mr-2"></i> Settings
                        </a>
                        <?php endif; ?>
                        <a href="/logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-50 border-t border-gray-200">
                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar fixed left-0 top-14 bottom-0 w-64 bg-white shadow-lg z-30 md:top-14 overflow-y-auto">
        <nav class="p-4 space-y-1">
            <a href="/dashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-gray-100 font-semibold' : ''; ?>">
                <i class="fas fa-home w-5"></i>
                <span>Dashboard</span>
            </a>
            
            <?php if (hasPermission('projects', 'view')): ?>
            <a href="/projects.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 <?php echo basename($_SERVER['PHP_SELF']) == 'projects.php' ? 'bg-gray-100 font-semibold' : ''; ?>">
                <i class="fas fa-building w-5"></i>
                <span>Projects</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('plots', 'view')): ?>
            <a href="/plots.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 <?php echo basename($_SERVER['PHP_SELF']) == 'plots.php' ? 'bg-gray-100 font-semibold' : ''; ?>">
                <i class="fas fa-map w-5"></i>
                <span>Plots</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('leads', 'view')): ?>
            <a href="/leads.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 <?php echo basename($_SERVER['PHP_SELF']) == 'leads.php' ? 'bg-gray-100 font-semibold' : ''; ?>">
                <i class="fas fa-user-plus w-5"></i>
                <span>Leads</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('clients', 'view')): ?>
            <a href="/clients.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 <?php echo basename($_SERVER['PHP_SELF']) == 'clients.php' ? 'bg-gray-100 font-semibold' : ''; ?>">
                <i class="fas fa-users w-5"></i>
                <span>Clients</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('sales', 'view')): ?>
            <a href="/sales.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 <?php echo basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'bg-gray-100 font-semibold' : ''; ?>">
                <i class="fas fa-handshake w-5"></i>
                <span>Sales</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('payments', 'view')): ?>
            <a href="/payments.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'bg-gray-100 font-semibold' : ''; ?>">
                <i class="fas fa-money-bill-wave w-5"></i>
                <span>Payments</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('site_visits', 'view')): ?>
            <a href="/site-visits.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 <?php echo basename($_SERVER['PHP_SELF']) == 'site-visits.php' ? 'bg-gray-100 font-semibold' : ''; ?>">
                <i class="fas fa-calendar-check w-5"></i>
                <span>Site Visits</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('users', 'view')): ?>
            <a href="/users.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'bg-gray-100 font-semibold' : ''; ?>">
                <i class="fas fa-user-tie w-5"></i>
                <span>Staff</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('attendance', 'view')): ?>
            <a href="/attendance.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 <?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'bg-gray-100 font-semibold' : ''; ?>">
                <i class="fas fa-clock w-5"></i>
                <span>Attendance</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('payroll', 'view')): ?>
            <a href="/payroll.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 <?php echo basename($_SERVER['PHP_SELF']) == 'payroll.php' ? 'bg-gray-100 font-semibold' : ''; ?>">
                <i class="fas fa-wallet w-5"></i>
                <span>Payroll</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('documents', 'view')): ?>
            <a href="/documents.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 <?php echo basename($_SERVER['PHP_SELF']) == 'documents.php' ? 'bg-gray-100 font-semibold' : ''; ?>">
                <i class="fas fa-file-alt w-5"></i>
                <span>Documents</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('reports', 'view')): ?>
            <a href="/reports.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'bg-gray-100 font-semibold' : ''; ?>">
                <i class="fas fa-chart-bar w-5"></i>
                <span>Reports</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('settings', 'view')): ?>
            <a href="/settings.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'bg-gray-100 font-semibold' : ''; ?>">
                <i class="fas fa-cog w-5"></i>
                <span>Settings</span>
            </a>
            <?php endif; ?>
        </nav>
    </aside>
    
    <!-- Sidebar Overlay (Mobile) -->
    <div id="sidebarOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-20 md:hidden" onclick="toggleSidebar()"></div>
    
    <!-- Main Content -->
    <main class="pt-14 md:ml-64">
        <?php
        $flash = getFlashMessage();
        if ($flash):
        ?>
        <div id="flashMessage" class="mx-4 mt-4 px-4 py-3 rounded-lg <?php echo $flash['type'] === 'success' ? 'bg-green-100 border border-green-200 text-green-800' : ($flash['type'] === 'error' ? 'bg-red-100 border border-red-200 text-red-800' : 'bg-blue-100 border border-blue-200 text-blue-800'); ?>">
            <div class="flex items-center justify-between">
                <span><?php echo sanitize($flash['message']); ?></span>
                <button onclick="this.parentElement.parentElement.remove()" class="text-gray-600 hover:text-gray-900">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
        <script>
            setTimeout(() => {
                const msg = document.getElementById('flashMessage');
                if (msg) msg.remove();
            }, 5000);
        </script>
        <?php endif; ?>