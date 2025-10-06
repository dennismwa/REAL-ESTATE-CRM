<?php
$pageTitle = 'Plots';
require_once 'config.php';
requirePermission('plots', 'view');

$action = $_GET['action'] ?? 'list';
$plotId = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'edit' && hasPermission('plots', 'edit')) {
        $plotNumber = sanitize($_POST['plot_number']);
        $section = sanitize($_POST['section']);
        $size = floatval($_POST['size']);
        $price = floatval($_POST['price']);
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE plots SET plot_number = ?, section = ?, size = ?, price = ?, status = ? WHERE id = ?");
        if ($stmt->execute([$plotNumber, $section, $size, $price, $status, $plotId])) {
            logActivity('Update Plot', "Updated plot: $plotNumber");
            flashMessage('Plot updated successfully!');
            redirect('/plots.php');
        }
    } elseif ($action === 'delete' && hasPermission('plots', 'delete')) {
        $stmt = $pdo->prepare("DELETE FROM plots WHERE id = ?");
        if ($stmt->execute([$plotId])) {
            logActivity('Delete Plot', "Deleted plot ID: $plotId");
            flashMessage('Plot deleted successfully!');
            redirect('/plots.php');
        }
    }
}

// Get plot data for edit
if ($action === 'edit' && $plotId) {
    $stmt = $pdo->prepare("SELECT p.*, pr.project_name FROM plots p JOIN projects pr ON p.project_id = pr.id WHERE p.id = ?");
    $stmt->execute([$plotId]);
    $plot = $stmt->fetch();
    
    if (!$plot) {
        redirect('/plots.php');
    }
}

// Get all plots with project info
$stmt = $pdo->query("
    SELECT p.*, pr.project_name, pr.location
    FROM plots p
    JOIN projects pr ON p.project_id = pr.id
    ORDER BY pr.project_name, p.plot_number
");
$plots = $stmt->fetchAll();

// Group by project
$plotsByProject = [];
foreach ($plots as $plot) {
    $projectName = $plot['project_name'];
    if (!isset($plotsByProject[$projectName])) {
        $plotsByProject[$projectName] = [];
    }
    $plotsByProject[$projectName][] = $plot;
}

include 'includes/header.php';
?>

<div class="p-4 md:p-6 pb-20 md:pb-6">
    <?php if ($action === 'list'): ?>
        <!-- Plots List -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800">All Plots</h1>
                <p class="text-gray-600 mt-1">Manage plot inventory across projects</p>
            </div>
        </div>
        
        <!-- Filter -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <div class="flex flex-wrap gap-2">
                <button onclick="filterByStatus('all')" class="status-filter active px-4 py-2 rounded-lg text-sm font-semibold">All</button>
                <button onclick="filterByStatus('available')" class="status-filter px-4 py-2 rounded-lg text-sm font-semibold">Available</button>
                <button onclick="filterByStatus('booked')" class="status-filter px-4 py-2 rounded-lg text-sm font-semibold">Booked</button>
                <button onclick="filterByStatus('sold')" class="status-filter px-4 py-2 rounded-lg text-sm font-semibold">Sold</button>
            </div>
        </div>
        
        <!-- Plots by Project -->
        <?php foreach ($plotsByProject as $projectName => $projectPlots): ?>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold mb-4"><?php echo sanitize($projectName); ?></h2>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Plot #</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Section</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Size</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Price</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($projectPlots as $p): ?>
                        <tr class="hover:bg-gray-50 plot-row" data-status="<?php echo $p['status']; ?>">
                            <td class="px-4 py-3 font-semibold"><?php echo sanitize($p['plot_number']); ?></td>
                            <td class="px-4 py-3 text-sm"><?php echo $p['section'] ? sanitize($p['section']) : '-'; ?></td>
                            <td class="px-4 py-3 text-sm"><?php echo number_format($p['size'], 2); ?> m²</td>
                            <td class="px-4 py-3 font-semibold text-primary"><?php echo formatMoney($p['price']); ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full <?php 
                                    echo $p['status'] === 'available' ? 'bg-green-100 text-green-800' : 
                                        ($p['status'] === 'booked' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); 
                                ?>">
                                    <?php echo ucfirst($p['status']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <?php if (hasPermission('plots', 'edit')): ?>
                                    <a href="/plots.php?action=edit&id=<?php echo $p['id']; ?>" class="text-blue-600 hover:text-blue-800" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('plots', 'delete') && $p['status'] === 'available'): ?>
                                    <button onclick="deletePlot(<?php echo $p['id']; ?>)" class="text-red-600 hover:text-red-800" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($plots)): ?>
        <div class="bg-white rounded-lg shadow p-12 text-center">
            <i class="fas fa-map text-6xl text-gray-300 mb-4"></i>
            <p class="text-gray-600">No plots found. Add plots to your projects to get started.</p>
        </div>
        <?php endif; ?>
        
    <?php elseif ($action === 'edit'): ?>
        <!-- Edit Form -->
        <div class="max-w-2xl mx-auto">
            <div class="mb-6">
                <a href="/plots.php" class="text-primary hover:underline">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Plots
                </a>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-2xl font-bold mb-6">Edit Plot</h2>
                <p class="text-gray-600 mb-6">Project: <?php echo sanitize($plot['project_name']); ?></p>
                
                <form method="POST" action="">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Plot Number *</label>
                            <input type="text" name="plot_number" required
                                   value="<?php echo sanitize($plot['plot_number']); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Section</label>
                            <input type="text" name="section"
                                   value="<?php echo sanitize($plot['section']); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Size (m²) *</label>
                            <input type="number" name="size" required step="0.01" min="0"
                                   value="<?php echo $plot['size']; ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Price *</label>
                            <input type="number" name="price" required step="0.01" min="0"
                                   value="<?php echo $plot['price']; ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                            <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="available" <?php echo $plot['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                                <option value="booked" <?php echo $plot['status'] === 'booked' ? 'selected' : ''; ?>>Booked</option>
                                <option value="sold" <?php echo $plot['status'] === 'sold' ? 'selected' : ''; ?>>Sold</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 mt-6">
                        <button type="submit" class="flex-1 bg-primary text-white py-3 rounded-lg font-semibold hover:opacity-90 transition">
                            Update Plot
                        </button>
                        <a href="/plots.php" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function filterByStatus(status) {
    const rows = document.querySelectorAll('.plot-row');
    const buttons = document.querySelectorAll('.status-filter');
    
    buttons.forEach(btn => btn.classList.remove('active', 'bg-primary', 'text-white'));
    event.target.classList.add('active', 'bg-primary', 'text-white');
    
    rows.forEach(row => {
        if (status === 'all' || row.dataset.status === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function deletePlot(id) {
    if (confirm('Are you sure you want to delete this plot?')) {
        window.location.href = '/plots.php?action=delete&id=' + id;
    }
}
</script>

<style>
.status-filter.active {
    background-color: var(--primary-color);
    color: white;
}
</style>

<?php include 'includes/footer.php'; ?>