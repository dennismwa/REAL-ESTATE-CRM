<?php
$pageTitle = 'Clients';
require_once 'config.php';
requirePermission('clients', 'view');

$action = $_GET['action'] ?? 'list';
$clientId = $_GET['id'] ?? null;
$leadId = $_GET['lead_id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create' && hasPermission('clients', 'create')) {
        $fullName = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $idNumber = sanitize($_POST['id_number']);
        $address = sanitize($_POST['address']);
        $assignedAgent = !empty($_POST['assigned_agent']) ? intval($_POST['assigned_agent']) : null;
        $fromLeadId = !empty($_POST['lead_id']) ? intval($_POST['lead_id']) : null;
        
        try {
            $stmt = $pdo->prepare("INSERT INTO clients (full_name, email, phone, id_number, address, assigned_agent, lead_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$fullName, $email, $phone, $idNumber, $address, $assignedAgent, $fromLeadId])) {
                // If converted from lead, update lead status
                if ($fromLeadId) {
                    $stmt = $pdo->prepare("UPDATE leads SET status = 'converted' WHERE id = ?");
                    $stmt->execute([$fromLeadId]);
                }
                
                logActivity('Create Client', "Created client: $fullName");
                flashMessage('Client created successfully!');
                redirect('/clients.php');
            }
        } catch (PDOException $e) {
            error_log("Client creation error: " . $e->getMessage());
            flashMessage('Error creating client. Please try again.', 'error');
        }
    } elseif ($action === 'edit' && hasPermission('clients', 'edit')) {
        $fullName = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $idNumber = sanitize($_POST['id_number']);
        $address = sanitize($_POST['address']);
        $assignedAgent = !empty($_POST['assigned_agent']) ? intval($_POST['assigned_agent']) : null;
        
        try {
            $stmt = $pdo->prepare("UPDATE clients SET full_name = ?, email = ?, phone = ?, id_number = ?, address = ?, assigned_agent = ? WHERE id = ?");
            if ($stmt->execute([$fullName, $email, $phone, $idNumber, $address, $assignedAgent, $clientId])) {
                logActivity('Update Client', "Updated client: $fullName");
                flashMessage('Client updated successfully!');
                redirect('/clients.php');
            }
        } catch (PDOException $e) {
            error_log("Client update error: " . $e->getMessage());
            flashMessage('Error updating client. Please try again.', 'error');
        }
    }
}

// Get client data
if (in_array($action, ['edit', 'view']) && $clientId) {
    $stmt = $pdo->prepare("SELECT c.*, u.full_name as agent_name FROM clients c LEFT JOIN users u ON c.assigned_agent = u.id WHERE c.id = ?");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch();
    
    if (!$client) {
        redirect('/clients.php');
    }
    
    if ($action === 'view') {
        // Get client's sales
        $stmt = $pdo->prepare("
            SELECT s.*, p.plot_number, pr.project_name
            FROM sales s
            JOIN plots p ON s.plot_id = p.id
            JOIN projects pr ON p.project_id = pr.id
            WHERE s.client_id = ?
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$clientId]);
        $clientSales = $stmt->fetchAll();
    }
}

// Get lead data if converting from lead
if ($leadId && $action === 'create') {
    $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([$leadId]);
    $leadData = $stmt->fetch();
}

// Get all clients
$stmt = $pdo->query("SELECT c.*, u.full_name as agent_name FROM clients c LEFT JOIN users u ON c.assigned_agent = u.id ORDER BY c.created_at DESC");
$clients = $stmt->fetchAll();

// Get sales agents
$stmt = $pdo->query("SELECT id, full_name FROM users WHERE role = 'sales_agent' AND status = 'active' ORDER BY full_name");
$salesAgents = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="p-4 md:p-6 pb-20 md:pb-6">
    <?php if ($action === 'list'): ?>
        <!-- Clients List -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Clients</h1>
                <p class="text-gray-600 mt-1">Manage your customer database</p>
            </div>
            <?php if (hasPermission('clients', 'create')): ?>
            <a href="/clients.php?action=create" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:opacity-90 transition">
                <i class="fas fa-plus mr-2"></i>
                <span>Add Client</span>
            </a>
            <?php endif; ?>
        </div>
        
        <!-- Clients Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($clients as $c): ?>
            <div class="bg-white rounded-lg shadow hover:shadow-lg transition p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 rounded-full bg-primary text-white flex items-center justify-center text-xl font-bold">
                            <?php echo strtoupper(substr($c['full_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800"><?php echo sanitize($c['full_name']); ?></h3>
                            <p class="text-xs text-gray-500">ID: #<?php echo str_pad($c['id'], 5, '0', STR_PAD_LEFT); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-2 mb-4">
                    <p class="text-sm">
                        <i class="fas fa-phone w-4 text-gray-400"></i>
                        <span class="ml-2"><?php echo sanitize($c['phone']); ?></span>
                    </p>
                    <?php if ($c['email']): ?>
                    <p class="text-sm">
                        <i class="fas fa-envelope w-4 text-gray-400"></i>
                        <span class="ml-2 truncate"><?php echo sanitize($c['email']); ?></span>
                    </p>
                    <?php endif; ?>
                    <?php if ($c['agent_name']): ?>
                    <p class="text-sm">
                        <i class="fas fa-user-tie w-4 text-gray-400"></i>
                        <span class="ml-2"><?php echo sanitize($c['agent_name']); ?></span>
                    </p>
                    <?php endif; ?>
                </div>
                
                <div class="flex gap-2">
                    <a href="/clients.php?action=view&id=<?php echo $c['id']; ?>" class="flex-1 text-center px-3 py-2 bg-primary text-white rounded-lg text-sm hover:opacity-90 transition">
                        View Details
                    </a>
                    <?php if (hasPermission('clients', 'edit')): ?>
                    <a href="/clients.php?action=edit&id=<?php echo $c['id']; ?>" class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">
                        <i class="fas fa-edit"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($clients)): ?>
            <div class="col-span-full text-center py-12">
                <i class="fas fa-users text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-600">No clients found</p>
                <?php if (hasPermission('clients', 'create')): ?>
                <a href="/clients.php?action=create" class="inline-block mt-4 px-6 py-2 bg-primary text-white rounded-lg hover:opacity-90 transition">
                    Add Your First Client
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
    <?php elseif ($action === 'create' || $action === 'edit'): ?>
        <!-- Create/Edit Form -->
        <div class="max-w-2xl mx-auto">
            <div class="mb-6">
                <a href="/clients.php" class="text-primary hover:underline">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Clients
                </a>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-2xl font-bold mb-6">
                    <?php echo $action === 'create' ? ($leadId ? 'Convert Lead to Client' : 'Add New Client') : 'Edit Client'; ?>
                </h2>
                
                <form method="POST" action="">
                    <?php if ($leadId): ?>
                    <input type="hidden" name="lead_id" value="<?php echo $leadId; ?>">
                    <?php endif; ?>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name *</label>
                            <input type="text" name="full_name" required
                                   value="<?php echo $action === 'edit' ? sanitize($client['full_name']) : (isset($leadData) ? sanitize($leadData['full_name']) : ''); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Phone *</label>
                                <input type="tel" name="phone" required
                                       value="<?php echo $action === 'edit' ? sanitize($client['phone']) : (isset($leadData) ? sanitize($leadData['phone']) : ''); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                                <input type="email" name="email"
                                       value="<?php echo $action === 'edit' ? sanitize($client['email']) : (isset($leadData) ? sanitize($leadData['email']) : ''); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">ID Number</label>
                            <input type="text" name="id_number"
                                   value="<?php echo $action === 'edit' ? sanitize($client['id_number']) : ''; ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Address</label>
                            <textarea name="address" rows="3"
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"><?php echo $action === 'edit' ? sanitize($client['address']) : ''; ?></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Assign Agent</label>
                            <select name="assigned_agent" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="">None</option>
                                <?php foreach ($salesAgents as $agent): ?>
                                <option value="<?php echo $agent['id']; ?>" <?php echo ($action === 'edit' && $client['assigned_agent'] == $agent['id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitize($agent['full_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 mt-6">
                        <button type="submit" class="flex-1 bg-primary text-white py-3 rounded-lg font-semibold hover:opacity-90 transition">
                            <?php echo $action === 'create' ? ($leadId ? 'Convert to Client' : 'Add Client') : 'Update Client'; ?>
                        </button>
                        <a href="/clients.php" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
    <?php elseif ($action === 'view'): ?>
        <!-- View Client Details -->
        <div class="max-w-4xl mx-auto">
            <div class="mb-6">
                <a href="/clients.php" class="text-primary hover:underline">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Clients
                </a>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <div class="flex items-start justify-between mb-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-16 h-16 rounded-full bg-primary text-white flex items-center justify-center text-2xl font-bold">
                            <?php echo strtoupper(substr($client['full_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold"><?php echo sanitize($client['full_name']); ?></h2>
                            <p class="text-gray-600">Client ID: #<?php echo str_pad($client['id'], 5, '0', STR_PAD_LEFT); ?></p>
                        </div>
                    </div>
                    <?php if (hasPermission('clients', 'edit')): ?>
                    <a href="/clients.php?action=edit&id=<?php echo $client['id']; ?>" class="px-4 py-2 bg-primary text-white rounded-lg hover:opacity-90 transition">
                        <i class="fas fa-edit mr-2"></i>Edit
                    </a>
                    <?php endif; ?>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-semibold mb-3">Contact Information</h3>
                        <div class="space-y-2">
                            <p class="text-sm">
                                <span class="text-gray-600">Phone:</span>
                                <span class="ml-2 font-semibold"><?php echo sanitize($client['phone']); ?></span>
                            </p>
                            <?php if ($client['email']): ?>
                            <p class="text-sm">
                                <span class="text-gray-600">Email:</span>
                                <span class="ml-2 font-semibold"><?php echo sanitize($client['email']); ?></span>
                            </p>
                            <?php endif; ?>
                            <?php if ($client['id_number']): ?>
                            <p class="text-sm">
                                <span class="text-gray-600">ID Number:</span>
                                <span class="ml-2 font-semibold"><?php echo sanitize($client['id_number']); ?></span>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="font-semibold mb-3">Additional Details</h3>
                        <div class="space-y-2">
                            <?php if ($client['address']): ?>
                            <p class="text-sm">
                                <span class="text-gray-600">Address:</span>
                                <span class="ml-2"><?php echo sanitize($client['address']); ?></span>
                            </p>
                            <?php endif; ?>
                            <p class="text-sm">
                                <span class="text-gray-600">Assigned Agent:</span>
                                <span class="ml-2 font-semibold"><?php echo $client['agent_name'] ? sanitize($client['agent_name']) : 'Not assigned'; ?></span>
                            </p>
                            <p class="text-sm">
                                <span class="text-gray-600">Registered:</span>
                                <span class="ml-2"><?php echo formatDate($client['created_at'], 'M d, Y'); ?></span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sales History -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold">Sales History</h3>
                    <?php if (hasPermission('sales', 'create')): ?>
                    <a href="/sales.php?action=create&client_id=<?php echo $client['id']; ?>" class="px-4 py-2 bg-secondary text-white rounded-lg hover:opacity-90 transition text-sm">
                        <i class="fas fa-plus mr-2"></i>New Sale
                    </a>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($clientSales)): ?>
                <div class="space-y-3">
                    <?php foreach ($clientSales as $sale): ?>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div>
                            <p class="font-semibold"><?php echo sanitize($sale['project_name']); ?> - Plot <?php echo sanitize($sale['plot_number']); ?></p>
                            <p class="text-sm text-gray-600"><?php echo formatDate($sale['sale_date']); ?></p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-primary"><?php echo formatMoney($sale['sale_price']); ?></p>
                            <span class="text-xs px-2 py-1 rounded-full <?php echo $sale['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                <?php echo ucfirst($sale['status']); ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-center text-gray-500 py-8">No sales recorded yet</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>