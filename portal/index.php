<?php
// portal/index.php - Client Portal Dashboard
session_start();
require_once '../config.php';

// Check if client is logged in
if (!isset($_SESSION['client_id'])) {
    header('Location: /portal/login.php');
    exit;
}

$clientId = $_SESSION['client_id'];

// Get client information
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$clientId]);
$client = $stmt->fetch();

// Get client's purchases
$stmt = $pdo->prepare("
    SELECT s.*, p.plot_number, p.size, pr.project_name, pr.location,
           (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE sale_id = s.id) as paid_amount
    FROM sales s
    JOIN plots p ON s.plot_id = p.id
    JOIN projects pr ON p.project_id = pr.id
    WHERE s.client_id = ?
    ORDER BY s.sale_date DESC
");
$stmt->execute([$clientId]);
$purchases = $stmt->fetchAll();

// Get recent payments
$stmt = $pdo->prepare("
    SELECT pay.*, s.id as sale_id, p.plot_number, pr.project_name
    FROM payments pay
    JOIN sales s ON pay.sale_id = s.id
    JOIN plots p ON s.plot_id = p.id
    JOIN projects pr ON p.project_id = pr.id
    WHERE s.client_id = ?
    ORDER BY pay.payment_date DESC
    LIMIT 10
");
$stmt->execute([$clientId]);
$recentPayments = $stmt->fetchAll();

// Get documents
$stmt = $pdo->prepare("
    SELECT * FROM client_documents 
    WHERE client_id = ? 
    ORDER BY uploaded_at DESC
");
$stmt->execute([$clientId]);
$documents = $stmt->fetchAll();

// Get support tickets
$stmt = $pdo->prepare("
    SELECT * FROM support_tickets 
    WHERE client_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$clientId]);
$tickets = $stmt->fetchAll();

// Calculate total statistics
$totalPurchases = count($purchases);
$totalInvestment = array_sum(array_column($purchases, 'sale_price'));
$totalPaid = array_sum(array_column($purchases, 'paid_amount'));
$totalBalance = $totalInvestment - $totalPaid;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Portal - <?php echo sanitize($settings['site_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: <?php echo $settings['primary_color']; ?>;
            --secondary-color: <?php echo $settings['secondary_color']; ?>;
        }
        .bg-primary { background-color: var(--primary-color); }
        .text-primary { color: var(--primary-color); }
        .border-primary { border-color: var(--primary-color); }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <img src="<?php echo $settings['logo_path']; ?>" alt="Logo" class="h-10">
                    <span class="text-xl font-bold text-primary">Client Portal</span>
                </div>
                
                <div class="flex items-center space-x-6">
                    <a href="/portal/" class="text-gray-700 hover:text-primary">Dashboard</a>
                    <a href="/portal/payments.php" class="text-gray-700 hover:text-primary">Payments</a>
                    <a href="/portal/documents.php" class="text-gray-700 hover:text-primary">Documents</a>
                    <a href="/portal/support.php" class="text-gray-700 hover:text-primary">Support</a>
                    
                    <div class="relative group">
                        <button class="flex items-center space-x-2 text-gray-700 hover:text-primary">
                            <div class="w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center">
                                <?php echo strtoupper(substr($client['full_name'], 0, 1)); ?>
                            </div>
                            <span><?php echo sanitize($client['full_name']); ?></span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl hidden group-hover:block">
                            <a href="/portal/profile.php" class="block px-4 py-2 hover:bg-gray-100">
                                <i class="fas fa-user mr-2"></i> My Profile
                            </a>
                            <a href="/portal/settings.php" class="block px-4 py-2 hover:bg-gray-100">
                                <i class="fas fa-cog mr-2"></i> Settings
                            </a>
                            <hr class="my-2">
                            <a href="/portal/logout.php" class="block px-4 py-2 hover:bg-gray-100 text-red-600">
                                <i class="fas fa-sign-out-alt mr-2"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Hero Section -->
    <div class="bg-gradient-to-r from-primary to-secondary text-white py-8">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-bold">Welcome back, <?php echo sanitize(explode(' ', $client['full_name'])[0]); ?>!</h1>
            <p class="mt-2 text-white/90">Manage your property investments and track your payments</p>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-primary">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Properties</p>
                        <p class="text-2xl font-bold text-primary"><?php echo $totalPurchases; ?></p>
                    </div>
                    <div class="bg-primary/10 p-3 rounded-full">
                        <i class="fas fa-home text-primary text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Investment</p>
                        <p class="text-xl font-bold text-gray-800"><?php echo formatMoney($totalInvestment); ?></p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-full">
                        <i class="fas fa-chart-line text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Amount Paid</p>
                        <p class="text-xl font-bold text-blue-600"><?php echo formatMoney($totalPaid); ?></p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i class="fas fa-check-circle text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Balance Due</p>
                        <p class="text-xl font-bold <?php echo $totalBalance > 0 ? 'text-orange-600' : 'text-green-600'; ?>">
                            <?php echo formatMoney($totalBalance); ?>
                        </p>
                    </div>
                    <div class="bg-orange-100 p-3 rounded-full">
                        <i class="fas fa-wallet text-orange-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Properties Section -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold text-gray-800">My Properties</h2>
                        <a href="/portal/properties.php" class="text-primary hover:underline text-sm">View All →</a>
                    </div>
                    
                    <?php if (empty($purchases)): ?>
                    <p class="text-gray-500 text-center py-8">No properties purchased yet</p>
                    <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($purchases as $purchase): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="font-semibold text-lg"><?php echo sanitize($purchase['project_name']); ?></h3>
                                    <p class="text-sm text-gray-600">Plot #<?php echo sanitize($purchase['plot_number']); ?> • <?php echo number_format($purchase['size'], 2); ?> m²</p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <i class="fas fa-map-marker-alt mr-1"></i><?php echo sanitize($purchase['location']); ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-600">Purchase Price</p>
                                    <p class="font-bold text-lg text-primary"><?php echo formatMoney($purchase['sale_price']); ?></p>
                                    <div class="mt-2">
                                        <?php 
                                        $paidPercentage = $purchase['sale_price'] > 0 ? 
                                            ($purchase['paid_amount'] / $purchase['sale_price']) * 100 : 0;
                                        ?>
                                        <div class="w-32 bg-gray-200 rounded-full h-2">
                                            <div class="bg-primary h-2 rounded-full" style="width: <?php echo min(100, $paidPercentage); ?>%"></div>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1"><?php echo number_format($paidPercentage, 0); ?>% paid</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4 flex gap-2">
                                <a href="/portal/property-details.php?id=<?php echo $purchase['id']; ?>" 
                                   class="px-4 py-2 bg-primary text-white rounded-lg text-sm hover:opacity-90">
                                    View Details
                                </a>
                                <?php if ($purchase['balance'] > 0): ?>
                                <a href="/portal/make-payment.php?sale_id=<?php echo $purchase['id']; ?>" 
                                   class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:opacity-90">
                                    Make Payment
                                </a>
                                <?php endif; ?>
                                <a href="/portal/documents.php?sale_id=<?php echo $purchase['id']; ?>" 
                                   class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                                    Documents
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Payments -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold text-gray-800">Recent Payments</h2>
                        <a href="/portal/payments.php" class="text-primary hover:underline text-sm">View All →</a>
                    </div>
                    
                    <?php if (empty($recentPayments)): ?>
                    <p class="text-gray-500 text-center py-8">No payments made yet</p>
                    <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="text-left text-xs text-gray-600 uppercase">
                                <tr class="border-b">
                                    <th class="pb-2">Date</th>
                                    <th class="pb-2">Property</th>
                                    <th class="pb-2">Amount</th>
                                    <th class="pb-2">Method</th>
                                    <th class="pb-2">Receipt</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm">
                                <?php foreach ($recentPayments as $payment): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                    <td class="py-3">
                                        <p class="font-semibold"><?php echo sanitize($payment['project_name']); ?></p>
                                        <p class="text-xs text-gray-500">Plot #<?php echo sanitize($payment['plot_number']); ?></p>
                                    </td>
                                    <td class="py-3 font-semibold text-green-600"><?php echo formatMoney($payment['amount']); ?></td>
                                    <td class="py-3">
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">
                                            <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <a href="/portal/receipt.php?id=<?php echo $payment['id']; ?>" 
                                           class="text-primary hover:underline">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right Sidebar -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="font-bold text-gray-800 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <a href="/portal/make-payment.php" class="flex items-center justify-between p-3 bg-green-50 rounded-lg hover:bg-green-100 transition">
                            <div class="flex items-center">
                                <i class="fas fa-credit-card text-green-600 mr-3"></i>
                                <span class="font-semibold">Make Payment</span>
                            </div>
                            <i class="fas fa-arrow-right text-green-600"></i>
                        </a>
                        
                        <a href="/portal/book-site-visit.php" class="flex items-center justify-between p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                            <div class="flex items-center">
                                <i class="fas fa-calendar text-blue-600 mr-3"></i>
                                <span class="font-semibold">Book Site Visit</span>
                            </div>
                            <i class="fas fa-arrow-right text-blue-600"></i>
                        </a>
                        
                        <a href="/portal/refer-friend.php" class="flex items-center justify-between p-3 bg-purple-50 rounded-lg hover:bg-purple-100 transition">
                            <div class="flex items-center">
                                <i class="fas fa-user-plus text-purple-600 mr-3"></i>
                                <span class="font-semibold">Refer a Friend</span>
                            </div>
                            <i class="fas fa-arrow-right text-purple-600"></i>
                        </a>
                        
                        <a href="/portal/support.php" class="flex items-center justify-between p-3 bg-orange-50 rounded-lg hover:bg-orange-100 transition">
                            <div class="flex items-center">
                                <i class="fas fa-headset text-orange-600 mr-3"></i>
                                <span class="font-semibold">Get Support</span>
                            </div>
                            <i class="fas fa-arrow-right text-orange-600"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Documents -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-gray-800">My Documents</h3>
                        <a href="/portal/documents.php" class="text-primary hover:underline text-sm">View All</a>
                    </div>
                    
                    <?php if (empty($documents)): ?>
                    <p class="text-gray-500 text-sm">No documents uploaded yet</p>
                    <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach (array_slice($documents, 0, 5) as $doc): ?>
                        <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                            <div class="flex items-center">
                                <i class="fas fa-file-pdf text-red-500 mr-2"></i>
                                <div>
                                    <p class="text-sm font-semibold"><?php echo sanitize($doc['document_type']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?></p>
                                </div>
                            </div>
                            <a href="<?php echo $doc['file_path']; ?>" target="_blank" class="text-primary">
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Support Tickets -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-gray-800">Support Tickets</h3>
                        <a href="/portal/support.php" class="text-primary hover:underline text-sm">View All</a>
                    </div>
                    
                    <?php if (empty($tickets)): ?>
                    <p class="text-gray-500 text-sm">No support tickets</p>
                    <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($tickets as $ticket): ?>
                        <div class="p-3 border border-gray-200 rounded-lg hover:shadow">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-semibold text-sm">#<?php echo $ticket['ticket_number']; ?></p>
                                    <p class="text-xs text-gray-600 mt-1"><?php echo sanitize($ticket['subject']); ?></p>
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full <?php 
                                    echo $ticket['status'] === 'open' ? 'bg-green-100 text-green-800' :
                                        ($ticket['status'] === 'resolved' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800');
                                ?>">
                                    <?php echo ucfirst($ticket['status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> <?php echo sanitize($settings['site_name']); ?>. All rights reserved.</p>
                <div class="mt-4 space-x-6">
                    <a href="/portal/privacy.php" class="hover:underline">Privacy Policy</a>
                    <a href="/portal/terms.php" class="hover:underline">Terms of Service</a>
                    <a href="/portal/contact.php" class="hover:underline">Contact Us</a>
                </div>
            </div>
        </div>
    </footer>
    
    <script>
        // Add any JavaScript functionality here
    </script>
</body>
</html>