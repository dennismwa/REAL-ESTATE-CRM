<?php
// includes/footer.php
?>
    <!-- Mobile Bottom Navigation -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white shadow-lg mobile-bottom-nav z-30">
        <div class="flex justify-around items-center h-16">
            <a href="/dashboard.php" class="flex flex-col items-center justify-center flex-1 text-gray-600 hover:text-primary <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'text-primary' : ''; ?>">
                <i class="fas fa-home text-xl"></i>
                <span class="text-xs mt-1">Home</span>
            </a>
            
            <?php if (hasPermission('projects', 'view')): ?>
            <a href="/projects.php" class="flex flex-col items-center justify-center flex-1 text-gray-600 hover:text-primary <?php echo basename($_SERVER['PHP_SELF']) == 'projects.php' ? 'text-primary' : ''; ?>">
                <i class="fas fa-building text-xl"></i>
                <span class="text-xs mt-1">Projects</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('sales', 'view')): ?>
            <a href="/sales.php" class="flex flex-col items-center justify-center flex-1 text-gray-600 hover:text-primary <?php echo basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'text-primary' : ''; ?>">
                <i class="fas fa-handshake text-xl"></i>
                <span class="text-xs mt-1">Sales</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('clients', 'view')): ?>
            <a href="/clients.php" class="flex flex-col items-center justify-center flex-1 text-gray-600 hover:text-primary <?php echo basename($_SERVER['PHP_SELF']) == 'clients.php' ? 'text-primary' : ''; ?>">
                <i class="fas fa-users text-xl"></i>
                <span class="text-xs mt-1">Clients</span>
            </a>
            <?php endif; ?>
            
            <a href="/profile.php" class="flex flex-col items-center justify-center flex-1 text-gray-600 hover:text-primary <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'text-primary' : ''; ?>">
                <i class="fas fa-user text-xl"></i>
                <span class="text-xs mt-1">Profile</span>
            </a>
        </div>
    </nav>
</main>
</body>
</html>