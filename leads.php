<?php
$pageTitle = 'Leads';
require_once 'config.php';
requirePermission('leads', 'view');

$action = $_GET['action'] ?? 'list';
$leadId = $_GET['id'] ?? null;
$userId = getUserId();
$userRole = getUserRole();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create' && hasPermission('leads', 'create')) {
        $fullName = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $source = $_POST['source'];
        $notes = sanitize($_POST['notes']);
        $assignedTo = $userRole === 'sales_agent' ? $userId : ($_POST['assigned_to'] ?? null);
        
        $stmt = $pdo->prepare("INSERT INTO leads (full_name, email, phone, source, notes, assigned_to) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$fullName, $email, $phone, $source, $notes, $assignedTo])) {
            $newLeadId = $pdo->lastInsertId();
            logActivity('Create Lead', "Created lead: $fullName");
            
            if ($assignedTo) {
                createNotification($assignedTo, 'New Lead Assigned', "You have been assigned a new lead: $fullName", 'info', "/leads.php?action=view&id=$newLeadId");
            }
            
            flashMessage('Lead created successfully!');
            redirect('/leads.php');
        }
    } elseif ($action === 'edit' && hasPermission('leads', 'edit')) {
        $fullName = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $source = $_POST['source'];
        $status = $_POST['status'];
        $notes = sanitize($_POST['notes']);
        $assignedTo = $_POST['assigned_to'] ?? null;
        
        $stmt = $pdo->prepare("UPDATE leads SET full_name = ?, email = ?, phone = ?, source = ?, status = ?, notes = ?, assigned_to = ? WHERE id = ?");
        if ($stmt->execute([$fullName, $email, $phone, $source, $status, $notes, $assignedTo, $leadId])) {
            logActivity('Update Lead', "Updated lead: $fullName");
            flashMessage('Lead updated successfully!');
            redirect('/leads.php');
        }
    } elseif ($action === 'add_progress' && hasPermission('leads', 'edit')) {
        $status = sanitize($_POST['status']);
        $progressNotes = sanitize($_POST['progress_notes']);
        
        $stmt = $pdo->prepare("INSERT INTO lead_progress (lead_id, status, notes, updated_by) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$leadId, $status, $progressNotes, $userId])) {
            // Update lead status
            $stmt = $pdo->prepare("UPDATE leads SET status = ? WHERE id = ?");
            $stmt->execute([$status, $leadId]);
            
            logActivity('Update Lead Progress', "Updated progress for lead ID: $leadId");
            flashMessage('Progress updated successfully!');
            redirect("/leads.php?action=view&id=$leadId");
        }
    }
}

// Get lead data
if (in_array($action, ['edit', 'view']) && $leadId) {
    $stmt = $pdo->prepare("SELECT l.*, u.full_name as agent_name FROM leads l LEFT JOIN users u ON l.assigned_to = u.id WHERE l.id = ?");
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch();
    
    if (!$lead) {
        redirect('/leads.php');
    }
    
    // Get progress history
    if ($action === 'view') {
        $stmt = $pdo->prepare("SELECT lp.*, u.full_name as updated_by_name FROM lead_progress lp JOIN users u ON lp.updated_by = u.id WHERE lp.lead_id = ? ORDER BY lp.created_at DESC");
        $stmt->execute([$leadId]);
        $progressHistory = $stmt->fetchAll();
    }
}

// Get all leads
if ($action === 'list') {
    $query = "SELECT l.*, u.full_name as agent_name FROM leads l LEFT JOIN users u ON l.assigned_to = u.id";
    
    if ($userRole === 'sales_agent') {
        $query .= " WHERE l.assigned_to = ?";
        $stmt = $pdo->prepare($query . " ORDER BY l.created_at DESC");
        $stmt->execute([$userId]);
    } else {
        $stmt = $pdo->query($query . " ORDER BY l.created_at DESC");
    }
    
    $leads = $stmt->fetchAll();
}

// Get sales agents for assignment
$stmt = $pdo->query("SELECT id, full_name FROM users WHERE role = 'sales_agent' AND status = 'active' ORDER BY full_name");
$salesAgents = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="p-4 md:p-6 pb-20 md:pb-6">
    <?php if ($action === 'list'): ?>
        <!-- Leads List -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Leads</h1>
                <p class="text-gray-600 mt-1">Manage and track potential clients</p>
            </div>
            <?php if (hasPermission('leads', 'create')): ?>
            <a href="/leads.php?action=create" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:opacity-90 transition">
                <i class="fas fa-plus mr-2"></i>
                <span>Add Lead</span>
            </a>
            <?php endif; ?>
        </div>
        
        <!-- Filter Tabs -->
        <div class="bg-white rounded-lg shadow mb-6 overflow-x-auto">
            <div class="flex border-b border-gray-200 p-2">
                <button onclick="filterLeads('all')" class="filter-tab active px-4 py-2 text-sm font-semibold rounded-lg">All</button>
                <button onclick="filterLeads('new')" class="filter-tab px-4 py-2 text-sm font-semibold rounded-lg">New</button>
                <button onclick="filterLeads('contacted')" class="filter-tab px-4 py-2 text-sm font-semibold rounded-lg">Contacted</button>
                <button onclick="filterLeads('qualified')" class="filter-tab px-4 py-2 text-sm font-semibold rounded-lg">Qualified</button>
                <button onclick="filterLeads('negotiation')" class="filter-tab px-4 py-2 text-sm font-semibold rounded-lg">Negotiation</button>
            </div>
        </div>
        
        <!-- Leads Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Contact</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Source</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                            <?php if (in_array($userRole, ['admin', 'manager'])): ?>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Assigned To</th>
                            <?php endif; ?>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($leads as $l): ?>
                        <tr class="hover:bg-gray-50 lead-row" data-status="<?php echo $l['status']; ?>">
                            <td class="px-4 py-3">
                                <p class="font-semibold"><?php echo sanitize($l['full_name']); ?></p>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <p><?php echo sanitize($l['phone']); ?></p>
                                <?php if ($l['email']): ?>
                                <p class="text-xs text-gray-500"><?php echo sanitize($l['email']); ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                    <?php echo ucfirst($l['source']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="px-2 py-1 text-xs rounded-full <?php 
                                    echo $l['status'] === 'new' ? 'bg-purple-100 text-purple-800' : 
                                        ($l['status'] === 'contacted' ? 'bg-blue-100 text-blue-800' : 
                                        ($l['status'] === 'qualified' ? 'bg-green-100 text-green-800' : 
                                        ($l['status'] === 'negotiation' ? 'bg-yellow-100 text-yellow-800' : 
                                        ($l['status'] === 'converted' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800')))); 
                                ?>">
                                    <?php echo ucfirst($l['status']); ?>
                                </span>
                            </td>
                            <?php if (in_array($userRole, ['admin', 'manager'])): ?>
                            <td class="px-4 py-3 text-sm">
                                <?php echo $l['agent_name'] ? sanitize($l['agent_name']) : '<span class="text-gray-400">Unassigned</span>'; ?>
                            </td>
                            <?php endif; ?>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <?php echo formatDate($l['created_at']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex gap-2">
                                    <a href="/leads.php?action=view&id=<?php echo $l['id']; ?>" class="text-primary hover:text-opacity-80" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (hasPermission('leads', 'edit')): ?>
                                    <a href="/leads.php?action=edit&id=<?php echo $l['id']; ?>" class="text-blue-600 hover:text-blue-800" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($leads)): ?>
                        <tr>
                            <td colspan="<?php echo in_array($userRole, ['admin', 'manager']) ? '7' : '6'; ?>" class="px-4 py-8 text-center text-gray-500">
                                No leads found
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    <?php elseif ($action === 'create' || $action === 'edit'): ?>
        <!-- Create/Edit Form -->
        <div class="max-w-2xl mx-auto">
            <div class="mb-6">
                <a href="/leads.php" class="text-primary hover:underline">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Leads
                </a>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-2xl font-bold mb-6"><?php echo $action === 'create' ? 'Add New Lead' : 'Edit Lead'; ?></h2>
                
                <form method="POST" action="">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name *</label>
                            <input type="text" name="full_name" required
                                   value="<?php echo $action === 'edit' ? sanitize($lead['full_name']) : ''; ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Phone *</label>
                                <input type="tel" name="phone" required
                                       value="<?php echo $action === 'edit' ? sanitize($lead['phone']) : ''; ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                                <input type="email" name="email"
                                       value="<?php echo $action === 'edit' ? sanitize($lead['email']) : ''; ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Source *</label>
                                <select name="source" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="">Select Source</option>
                                    <option value="facebook" <?php echo ($action === 'edit' && $lead['source'] === 'facebook') ? 'selected' : ''; ?>>Facebook</option>
                                    <option value="instagram" <?php echo ($action === 'edit' && $lead['source'] === 'instagram') ? 'selected' : ''; ?>>Instagram</option>
                                    <option value="website" <?php echo ($action === 'edit' && $lead['source'] === 'website') ? 'selected' : ''; ?>>Website</option>
                                    <option value="referral" <?php echo ($action === 'edit' && $lead['source'] === 'referral') ? 'selected' : ''; ?>>Referral</option>
                                    <option value="walk_in" <?php echo ($action === 'edit' && $lead['source'] === 'walk_in') ? 'selected' : ''; ?>>Walk In</option>
                                    <option value="other" <?php echo ($action === 'edit' && $lead['source'] === 'other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <?php if ($action === 'edit'): ?>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="new" <?php echo $lead['status'] === 'new' ? 'selected' : ''; ?>>New</option>
                                    <option value="contacted" <?php echo $lead['status'] === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                                    <option value="qualified" <?php echo $lead['status'] === 'qualified' ? 'selected' : ''; ?>>Qualified</option>
                                    <option value="negotiation" <?php echo $lead['status'] === 'negotiation' ? 'selected' : ''; ?>>Negotiation</option>
                                    <option value="converted" <?php echo $lead['status'] === 'converted' ? 'selected' : ''; ?>>Converted</option>
                                    <option value="lost" <?php echo $lead['status'] === 'lost' ? 'selected' : ''; ?>>Lost</option>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (in_array($userRole, ['admin', 'manager'])): ?>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Assign To</label>
                            <select name="assigned_to" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="">Unassigned</option>
                                <?php foreach ($salesAgents as $agent): ?>
                                <option value="<?php echo $agent['id']; ?>" <?php echo ($action === 'edit' && $lead['assigned_to'] == $agent['id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitize($agent['full_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" rows="4"
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"><?php echo $action === 'edit' ? sanitize($lead['notes']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 mt-6">
                        <button type="submit" class="flex-1 bg-primary text-white py-3 rounded-lg font-semibold hover:opacity-90 transition">
                            <?php echo $action === 'create' ? 'Add Lead' : 'Update Lead'; ?>
                        </button>
                        <a href="/leads.php" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
    <?php elseif ($action === 'view'): ?>
        <!-- View Lead Details -->
        <div class="max-w-4xl mx-auto">
            <div class="mb-6">
                <a href="/leads.php" class="text-primary hover:underline">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Leads
                </a>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Lead Info -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <h2 class="text-2xl font-bold"><?php echo sanitize($lead['full_name']); ?></h2>
                                <p class="text-gray-600 mt-1">Lead ID: #<?php echo str_pad($lead['id'], 5, '0', STR_PAD_LEFT); ?></p>
                            </div>
                            <span class="px-3 py-1 text-sm rounded-full <?php 
                                echo $lead['status'] === 'new' ? 'bg-purple-100 text-purple-800' : 
                                    ($lead['status'] === 'contacted' ? 'bg-blue-100 text-blue-800' : 
                                    ($lead['status'] === 'qualified' ? 'bg-green-100 text-green-800' : 
                                    ($lead['status'] === 'negotiation' ? 'bg-yellow-100 text-yellow-800' : 
                                    ($lead['status'] === 'converted' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800')))); 
                            ?>">
                                <?php echo ucfirst($lead['status']); ?>
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <p class="text-sm text-gray-600">Phone</p>
                                <p class="font-semibold"><?php echo sanitize($lead['phone']); ?></p>
                            </div>
                            
                            <?php if ($lead['email']): ?>
                            <div>
                                <p class="text-sm text-gray-600">Email</p>
                                <p class="font-semibold"><?php echo sanitize($lead['email']); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <div>
                                <p class="text-sm text-gray-600">Source</p>
                                <p class="font-semibold"><?php echo ucfirst($lead['source']); ?></p>
                            </div>
                            
                            <div>
                                <p class="text-sm text-gray-600">Assigned To</p>
                                <p class="font-semibold"><?php echo $lead['agent_name'] ? sanitize($lead['agent_name']) : 'Unassigned'; ?></p>
                            </div>
                            
                            <div>
                                <p class="text-sm text-gray-600">Created Date</p>
                                <p class="font-semibold"><?php echo formatDate($lead['created_at'], 'M d, Y h:i A'); ?></p>
                            </div>
                        </div>
                        
                        <?php if ($lead['notes']): ?>
                        <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-600 mb-1">Notes</p>
                            <p class="text-gray-800"><?php echo nl2br(sanitize($lead['notes'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex gap-2 mt-6">
                            <?php if (hasPermission('leads', 'edit')): ?>
                            <a href="/leads.php?action=edit&id=<?php echo $lead['id']; ?>" class="flex-1 text-center px-4 py-2 bg-primary text-white rounded-lg hover:opacity-90 transition">
                                <i class="fas fa-edit mr-2"></i>Edit Lead
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($lead['status'] !== 'converted' && hasPermission('clients', 'create')): ?>
                            <a href="/clients.php?action=create&lead_id=<?php echo $lead['id']; ?>" class="flex-1 text-center px-4 py-2 bg-secondary text-white rounded-lg hover:opacity-90 transition">
                                <i class="fas fa-user-check mr-2"></i>Convert to Client
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Progress History -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-bold mb-4">Progress History</h3>
                        
                        <div class="space-y-4">
                            <?php foreach ($progressHistory as $progress): ?>
                            <div class="flex gap-4">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center">
                                        <i class="fas fa-flag text-sm"></i>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <p class="font-semibold"><?php echo ucfirst($progress['status']); ?></p>
                                            <p class="text-sm text-gray-600"><?php echo sanitize($progress['updated_by_name']); ?></p>
                                        </div>
                                        <p class="text-xs text-gray-500"><?php echo formatDate($progress['created_at'], 'M d, Y h:i A'); ?></p>
                                    </div>
                                    <?php if ($progress['notes']): ?>
                                    <p class="text-sm text-gray-700 mt-2"><?php echo nl2br(sanitize($progress['notes'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($progressHistory)): ?>
                            <p class="text-center text-gray-500 py-4">No progress updates yet</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="lg:col-span-1">
                    <?php if (hasPermission('leads', 'edit')): ?>
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-bold mb-4">Update Progress</h3>
                        
                        <form method="POST" action="">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                                    <select name="status" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                        <option value="contacted">Contacted</option>
                                        <option value="qualified">Qualified</option>
                                        <option value="negotiation">Negotiation</option>
                                        <option value="converted">Converted</option>
                                        <option value="lost">Lost</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                                    <textarea name="progress_notes" rows="4" required
                                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                                              placeholder="Add notes about this update..."></textarea>
                                </div>
                                
                                <button type="submit" class="w-full bg-primary text-white py-2 rounded-lg font-semibold hover:opacity-90 transition">
                                    Add Update
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function filterLeads(status) {
    const rows = document.querySelectorAll('.lead-row');
    const tabs = document.querySelectorAll('.filter-tab');
    
    tabs.forEach(tab => tab.classList.remove('active', 'bg-primary', 'text-white'));
    event.target.classList.add('active', 'bg-primary', 'text-white');
    
    rows.forEach(row => {
        if (status === 'all' || row.dataset.status === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Style active filter tab
document.querySelectorAll('.filter-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.filter-tab').forEach(t => {
            t.classList.remove('bg-primary', 'text-white');
        });
        this.classList.add('bg-primary', 'text-white');
    });
});
</script>

<style>
.filter-tab.active {
    background-color: var(--primary-color);
    color: white;
}
</style>

<?php include 'includes/footer.php'; ?>