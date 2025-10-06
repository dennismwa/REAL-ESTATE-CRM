<?php
$pageTitle = 'Site Visits';
require_once 'config.php';
requirePermission('site_visits', 'view');

$action = $_GET['action'] ?? 'list';
$visitId = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create' && hasPermission('site_visits', 'create')) {
    $projectId = intval($_POST['project_id']);
    $visitDate = $_POST['visit_date'];
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    
    $stmt = $pdo->prepare("INSERT INTO site_visits (project_id, visit_date, title, description, created_by) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$projectId, $visitDate, $title, $description, getUserId()])) {
        $newVisitId = $pdo->lastInsertId();
        
        // Add attendees
        if (!empty($_POST['staff'])) {
            foreach ($_POST['staff'] as $staffId) {
                $stmt = $pdo->prepare("INSERT INTO site_visit_attendees (site_visit_id, user_id) VALUES (?, ?)");
                $stmt->execute([$newVisitId, $staffId]);
            }
        }
        
        if (!empty($_POST['clients'])) {
            foreach ($_POST['clients'] as $clientId) {
                $stmt = $pdo->prepare("INSERT INTO site_visit_attendees (site_visit_id, client_id) VALUES (?, ?)");
                $stmt->execute([$newVisitId, $clientId]);
            }
        }
        
        logActivity('Create Site Visit', "Created site visit: $title");
        flashMessage('Site visit scheduled successfully!');
        redirect('/site-visits.php');
    }
}

// Get all site visits
$stmt = $pdo->query("
    SELECT sv.*, pr.project_name, pr.location, u.full_name as created_by_name
    FROM site_visits sv
    JOIN projects pr ON sv.project_id = pr.id
    JOIN users u ON sv.created_by = u.id
    ORDER BY sv.visit_date DESC
");
$siteVisits = $stmt->fetchAll();

// Get projects for dropdown
$stmt = $pdo->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name");
$projects = $stmt->fetchAll();

// Get staff for dropdown
$stmt = $pdo->query("SELECT id, full_name FROM users WHERE status = 'active' ORDER BY full_name");
$staffList = $stmt->fetchAll();

// Get clients for dropdown
$stmt = $pdo->query("SELECT id, full_name FROM clients ORDER BY full_name");
$clientsList = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="p-4 md:p-6 pb-20 md:pb-6">
    <?php if ($action === 'list'): ?>
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Site Visits</h1>
                <p class="text-gray-600 mt-1">Schedule and manage site visits</p>
            </div>
            <?php if (hasPermission('site_visits', 'create')): ?>
            <a href="/site-visits.php?action=create" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:opacity-90 transition">
                <i class="fas fa-plus mr-2"></i>
                <span>Schedule Visit</span>
            </a>
            <?php endif; ?>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($siteVisits as $sv): ?>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <h3 class="font-bold text-gray-800"><?php echo sanitize($sv['title']); ?></h3>
                        <p class="text-sm text-gray-600 mt-1"><?php echo sanitize($sv['project_name']); ?></p>
                    </div>
                    <span class="px-2 py-1 text-xs rounded-full <?php 
                        echo $sv['status'] === 'scheduled' ? 'bg-blue-100 text-blue-800' : 
                            ($sv['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); 
                    ?>">
                        <?php echo ucfirst($sv['status']); ?>
                    </span>
                </div>
                
                <div class="space-y-2 mb-4">
                    <p class="text-sm">
                        <i class="fas fa-calendar w-4 text-gray-400"></i>
                        <span class="ml-2"><?php echo formatDate($sv['visit_date'], 'M d, Y h:i A'); ?></span>
                    </p>
                    <p class="text-sm">
                        <i class="fas fa-map-marker-alt w-4 text-gray-400"></i>
                        <span class="ml-2"><?php echo sanitize($sv['location']); ?></span>
                    </p>
                </div>
                
                <?php if ($sv['description']): ?>
                <p class="text-sm text-gray-600 mb-4 line-clamp-2"><?php echo sanitize($sv['description']); ?></p>
                <?php endif; ?>
                
                <div class="text-xs text-gray-500">
                    Created by <?php echo sanitize($sv['created_by_name']); ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($siteVisits)): ?>
            <div class="col-span-full text-center py-12">
                <i class="fas fa-calendar-check text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-600">No site visits scheduled</p>
                <?php if (hasPermission('site_visits', 'create')): ?>
                <a href="/site-visits.php?action=create" class="inline-block mt-4 px-6 py-2 bg-primary text-white rounded-lg hover:opacity-90 transition">
                    Schedule First Visit
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
    <?php elseif ($action === 'create'): ?>
        <div class="max-w-2xl mx-auto">
            <div class="mb-6">
                <a href="/site-visits.php" class="text-primary hover:underline">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Site Visits
                </a>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-2xl font-bold mb-6">Schedule Site Visit</h2>
                
                <form method="POST" action="">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Project *</label>
                            <select name="project_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="">Select Project</option>
                                <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>"><?php echo sanitize($project['project_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Visit Date & Time *</label>
                            <input type="datetime-local" name="visit_date" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Title *</label>
                            <input type="text" name="title" required placeholder="e.g., Client Site Tour"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                            <textarea name="description" rows="3" placeholder="Visit details and agenda"
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Staff Attendees</label>
                            <select name="staff[]" multiple size="5" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                <?php foreach ($staffList as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>"><?php echo sanitize($staff['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Hold Ctrl/Cmd to select multiple</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Client Attendees</label>
                            <select name="clients[]" multiple size="5" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                <?php foreach ($clientsList as $client): ?>
                                <option value="<?php echo $client['id']; ?>"><?php echo sanitize($client['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Hold Ctrl/Cmd to select multiple</p>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 mt-6">
                        <button type="submit" class="flex-1 bg-primary text-white py-3 rounded-lg font-semibold hover:opacity-90 transition">
                            Schedule Visit
                        </button>
                        <a href="/site-visits.php" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>