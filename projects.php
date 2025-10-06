<?php
$pageTitle = 'Projects';
require_once 'config.php';
requirePermission('projects', 'view');

$action = $_GET['action'] ?? 'list';
$projectId = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create' && hasPermission('projects', 'create')) {
        $projectName = sanitize($_POST['project_name']);
        $location = sanitize($_POST['location']);
        $totalPlots = intval($_POST['total_plots']);
        $description = sanitize($_POST['description']);
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("INSERT INTO projects (project_name, location, total_plots, description, status, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$projectName, $location, $totalPlots, $description, $status, getUserId()])) {
            logActivity('Create Project', "Created project: $projectName");
            flashMessage('Project created successfully!');
            redirect('/projects.php');
        }
    } elseif ($action === 'edit' && hasPermission('projects', 'edit')) {
        $projectName = sanitize($_POST['project_name']);
        $location = sanitize($_POST['location']);
        $totalPlots = intval($_POST['total_plots']);
        $description = sanitize($_POST['description']);
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE projects SET project_name = ?, location = ?, total_plots = ?, description = ?, status = ? WHERE id = ?");
        if ($stmt->execute([$projectName, $location, $totalPlots, $description, $status, $projectId])) {
            logActivity('Update Project', "Updated project: $projectName");
            flashMessage('Project updated successfully!');
            redirect('/projects.php');
        }
    } elseif ($action === 'delete' && hasPermission('projects', 'delete')) {
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
        if ($stmt->execute([$projectId])) {
            logActivity('Delete Project', "Deleted project ID: $projectId");
            flashMessage('Project deleted successfully!');
            redirect('/projects.php');
        }
    }
}

// Get project data for edit
if ($action === 'edit' && $projectId) {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
    
    if (!$project) {
        redirect('/projects.php');
    }
}

// Get all projects with statistics
$stmt = $pdo->query("
    SELECT p.*, 
           u.full_name as created_by_name,
           (SELECT COUNT(*) FROM plots WHERE project_id = p.id) as plot_count,
           (SELECT COUNT(*) FROM plots WHERE project_id = p.id AND status = 'sold') as plots_sold,
           (SELECT COUNT(*) FROM plots WHERE project_id = p.id AND status = 'available') as plots_available
    FROM projects p
    LEFT JOIN users u ON p.created_by = u.id
    ORDER BY p.created_at DESC
");
$projects = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="p-4 md:p-6 pb-20 md:pb-6">
    <?php if ($action === 'list'): ?>
        <!-- Projects List -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Projects</h1>
                <p class="text-gray-600 mt-1">Manage all real estate projects</p>
            </div>
            <?php if (hasPermission('projects', 'create')): ?>
            <a href="/projects.php?action=create" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:opacity-90 transition">
                <i class="fas fa-plus mr-2"></i>
                <span>Add Project</span>
            </a>
            <?php endif; ?>
        </div>
        
        <!-- Projects Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($projects as $proj): ?>
            <div class="bg-white rounded-lg shadow hover:shadow-lg transition">
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-gray-800"><?php echo sanitize($proj['project_name']); ?></h3>
                            <p class="text-sm text-gray-600 mt-1">
                                <i class="fas fa-map-marker-alt mr-1"></i>
                                <?php echo sanitize($proj['location']); ?>
                            </p>
                        </div>
                        <span class="px-3 py-1 text-xs rounded-full <?php 
                            echo $proj['status'] === 'active' ? 'bg-green-100 text-green-800' : 
                                ($proj['status'] === 'completed' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'); 
                        ?>">
                            <?php echo ucfirst($proj['status']); ?>
                        </span>
                    </div>
                    
                    <?php if ($proj['description']): ?>
                    <p class="text-sm text-gray-600 mb-4 line-clamp-2"><?php echo sanitize($proj['description']); ?></p>
                    <?php endif; ?>
                    
                    <!-- Statistics -->
                    <div class="grid grid-cols-3 gap-2 mb-4 p-3 bg-gray-50 rounded-lg">
                        <div class="text-center">
                            <p class="text-xl font-bold text-primary"><?php echo $proj['plot_count']; ?></p>
                            <p class="text-xs text-gray-600">Total Plots</p>
                        </div>
                        <div class="text-center">
                            <p class="text-xl font-bold text-green-600"><?php echo $proj['plots_sold']; ?></p>
                            <p class="text-xs text-gray-600">Sold</p>
                        </div>
                        <div class="text-center">
                            <p class="text-xl font-bold text-secondary"><?php echo $proj['plots_available']; ?></p>
                            <p class="text-xs text-gray-600">Available</p>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex items-center gap-2">
                        <a href="/plot-layout.php?project_id=<?php echo $proj['id']; ?>" class="flex-1 text-center px-3 py-2 bg-primary text-white rounded-lg text-sm hover:opacity-90 transition">
                            View Layout
                        </a>
                        <?php if (hasPermission('projects', 'edit')): ?>
                        <a href="/projects.php?action=edit&id=<?php echo $proj['id']; ?>" class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('projects', 'delete')): ?>
                        <button onclick="deleteProject(<?php echo $proj['id']; ?>)" class="px-3 py-2 bg-red-100 text-red-600 rounded-lg text-sm hover:bg-red-200 transition">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($projects)): ?>
            <div class="col-span-full text-center py-12">
                <i class="fas fa-building text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-600">No projects found</p>
                <?php if (hasPermission('projects', 'create')): ?>
                <a href="/projects.php?action=create" class="inline-block mt-4 px-6 py-2 bg-primary text-white rounded-lg hover:opacity-90 transition">
                    Create Your First Project
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
    <?php elseif ($action === 'create' || $action === 'edit'): ?>
        <!-- Create/Edit Form -->
        <div class="max-w-2xl mx-auto">
            <div class="mb-6">
                <a href="/projects.php" class="text-primary hover:underline">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Projects
                </a>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-2xl font-bold mb-6"><?php echo $action === 'create' ? 'Create New Project' : 'Edit Project'; ?></h2>
                
                <form method="POST" action="">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Project Name *</label>
                            <input type="text" name="project_name" required
                                   value="<?php echo $action === 'edit' ? sanitize($project['project_name']) : ''; ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Location *</label>
                            <input type="text" name="location" required
                                   value="<?php echo $action === 'edit' ? sanitize($project['location']) : ''; ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Total Plots *</label>
                            <input type="number" name="total_plots" required min="1"
                                   value="<?php echo $action === 'edit' ? $project['total_plots'] : ''; ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                            <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="active" <?php echo ($action === 'edit' && $project['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="on_hold" <?php echo ($action === 'edit' && $project['status'] === 'on_hold') ? 'selected' : ''; ?>>On Hold</option>
                                <option value="completed" <?php echo ($action === 'edit' && $project['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                            <textarea name="description" rows="4"
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"><?php echo $action === 'edit' ? sanitize($project['description']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 mt-6">
                        <button type="submit" class="flex-1 bg-primary text-white py-3 rounded-lg font-semibold hover:opacity-90 transition">
                            <?php echo $action === 'create' ? 'Create Project' : 'Update Project'; ?>
                        </button>
                        <a href="/projects.php" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function deleteProject(id) {
    if (confirm('Are you sure you want to delete this project? This will also delete all associated plots.')) {
        window.location.href = '/projects.php?action=delete&id=' + id;
    }
}
</script>

<?php include 'includes/footer.php'; ?>