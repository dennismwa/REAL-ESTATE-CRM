<?php
$pageTitle = 'Plot Layout';
require_once 'config.php';
requirePermission('plots', 'view');

$projectId = $_GET['project_id'] ?? null;

if (!$projectId) {
    redirect('/projects.php');
}

// Get project details
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch();

if (!$project) {
    redirect('/projects.php');
}

// Get all plots for this project
$stmt = $pdo->prepare("SELECT * FROM plots WHERE project_id = ? ORDER BY plot_number");
$stmt->execute([$projectId]);
$plots = $stmt->fetchAll();

// Group plots by section
$sections = [];
foreach ($plots as $plot) {
    $section = $plot['section'] ?: 'Main';
    if (!isset($sections[$section])) {
        $sections[$section] = [];
    }
    $sections[$section][] = $plot;
}

include 'includes/header.php';
?>

<div class="p-4 md:p-6 pb-20 md:pb-6">
    <!-- Header -->
    <div class="mb-6">
        <a href="/projects.php" class="text-primary hover:underline mb-4 inline-block">
            <i class="fas fa-arrow-left mr-2"></i>Back to Projects
        </a>
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800"><?php echo sanitize($project['project_name']); ?></h1>
                <p class="text-gray-600 mt-1">
                    <i class="fas fa-map-marker-alt mr-1"></i>
                    <?php echo sanitize($project['location']); ?>
                </p>
            </div>
            <?php if (hasPermission('plots', 'create')): ?>
            <button onclick="openAddPlotModal()" class="mt-4 md:mt-0 px-4 py-2 bg-primary text-white rounded-lg hover:opacity-90 transition">
                <i class="fas fa-plus mr-2"></i>Add Plot
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Legend -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <h3 class="font-semibold mb-3">Legend</h3>
        <div class="flex flex-wrap gap-4">
            <div class="flex items-center">
                <div class="w-6 h-6 bg-green-500 rounded mr-2"></div>
                <span class="text-sm">Available</span>
            </div>
            <div class="flex items-center">
                <div class="w-6 h-6 bg-yellow-500 rounded mr-2"></div>
                <span class="text-sm">Booked</span>
            </div>
            <div class="flex items-center">
                <div class="w-6 h-6 bg-red-500 rounded mr-2"></div>
                <span class="text-sm">Sold</span>
            </div>
        </div>
    </div>
    
    <!-- Plot Layout by Sections -->
    <?php foreach ($sections as $sectionName => $sectionPlots): ?>
    <div class="bg-white rounded-lg shadow p-4 md:p-6 mb-6">
        <h2 class="text-xl font-bold mb-4">Section: <?php echo sanitize($sectionName); ?></h2>
        
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
            <?php foreach ($sectionPlots as $plot): ?>
            <div onclick="showPlotDetails(<?php echo htmlspecialchars(json_encode($plot)); ?>)" 
                 class="plot-card cursor-pointer p-4 rounded-lg border-2 transition hover:shadow-lg <?php 
                    echo $plot['status'] === 'available' ? 'border-green-500 bg-green-50 hover:bg-green-100' : 
                        ($plot['status'] === 'booked' ? 'border-yellow-500 bg-yellow-50 hover:bg-yellow-100' : 'border-red-500 bg-red-50 hover:bg-red-100'); 
                 ?>">
                <div class="text-center">
                    <p class="font-bold text-lg"><?php echo sanitize($plot['plot_number']); ?></p>
                    <p class="text-xs text-gray-600 mt-1"><?php echo number_format($plot['size'], 2); ?> m²</p>
                    <p class="text-sm font-semibold mt-2 <?php 
                        echo $plot['status'] === 'available' ? 'text-green-700' : 
                            ($plot['status'] === 'booked' ? 'text-yellow-700' : 'text-red-700'); 
                    ?>">
                        <?php echo formatMoney($plot['price']); ?>
                    </p>
                    <span class="text-xs px-2 py-1 rounded-full inline-block mt-2 <?php 
                        echo $plot['status'] === 'available' ? 'bg-green-200 text-green-800' : 
                            ($plot['status'] === 'booked' ? 'bg-yellow-200 text-yellow-800' : 'bg-red-200 text-red-800'); 
                    ?>">
                        <?php echo ucfirst($plot['status']); ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if (empty($plots)): ?>
    <div class="bg-white rounded-lg shadow p-12 text-center">
        <i class="fas fa-map text-6xl text-gray-300 mb-4"></i>
        <p class="text-gray-600 mb-4">No plots added yet</p>
        <?php if (hasPermission('plots', 'create')): ?>
        <button onclick="openAddPlotModal()" class="px-6 py-2 bg-primary text-white rounded-lg hover:opacity-90 transition">
            Add First Plot
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Plot Details Modal -->
<div id="plotDetailsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-md w-full p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold">Plot Details</h3>
            <button onclick="closePlotDetailsModal()" class="text-gray-600 hover:text-gray-900">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div id="plotDetailsContent" class="space-y-3">
            <!-- Content loaded via JavaScript -->
        </div>
        
        <div id="plotActions" class="mt-6 flex gap-2">
            <!-- Actions loaded via JavaScript -->
        </div>
    </div>
</div>

<!-- Add Plot Modal -->
<div id="addPlotModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-lg max-w-md w-full p-6 my-8">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold">Add New Plot</h3>
            <button onclick="closeAddPlotModal()" class="text-gray-600 hover:text-gray-900">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <form id="addPlotForm" class="space-y-4">
            <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Plot Number *</label>
                <input type="text" name="plot_number" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Section</label>
                <input type="text" name="section" placeholder="e.g., Block A"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Size (m²) *</label>
                <input type="number" name="size" required step="0.01" min="0"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Price *</label>
                <input type="number" name="price" required step="0.01" min="0"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="available">Available</option>
                    <option value="booked">Booked</option>
                    <option value="sold">Sold</option>
                </select>
            </div>
            
            <button type="submit" class="w-full bg-primary text-white py-3 rounded-lg font-semibold hover:opacity-90 transition">
                Add Plot
            </button>
        </form>
    </div>
</div>

<script>
function showPlotDetails(plot) {
    const modal = document.getElementById('plotDetailsModal');
    const content = document.getElementById('plotDetailsContent');
    const actions = document.getElementById('plotActions');
    
    const statusColors = {
        'available': 'bg-green-100 text-green-800',
        'booked': 'bg-yellow-100 text-yellow-800',
        'sold': 'bg-red-100 text-red-800'
    };
    
    content.innerHTML = `
        <div class="flex items-center justify-between">
            <p class="text-2xl font-bold">Plot ${plot.plot_number}</p>
            <span class="px-3 py-1 text-sm rounded-full ${statusColors[plot.status]}">
                ${plot.status.charAt(0).toUpperCase() + plot.status.slice(1)}
            </span>
        </div>
        ${plot.section ? `<p><strong>Section:</strong> ${plot.section}</p>` : ''}
        <p><strong>Size:</strong> ${Number(plot.size).toFixed(2)} m²</p>
        <p><strong>Price:</strong> KES ${Number(plot.price).toLocaleString()}</p>
    `;
    
    actions.innerHTML = `
        <button onclick="closePlotDetailsModal()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
            Close
        </button>
        <?php if (hasPermission('plots', 'edit')): ?>
        <button onclick="editPlot(${plot.id})" class="flex-1 px-4 py-2 bg-primary text-white rounded-lg hover:opacity-90 transition">
            Edit Plot
        </button>
        <?php endif; ?>
    `;
    
    modal.classList.remove('hidden');
}

function closePlotDetailsModal() {
    document.getElementById('plotDetailsModal').classList.add('hidden');
}

function openAddPlotModal() {
    document.getElementById('addPlotModal').classList.remove('hidden');
}

function closeAddPlotModal() {
    document.getElementById('addPlotModal').classList.add('hidden');
    document.getElementById('addPlotForm').reset();
}

document.getElementById('addPlotForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch('/api/plots/create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Plot added successfully!');
            window.location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('An error occurred. Please try again.');
        console.error(error);
    }
});

function editPlot(plotId) {
    window.location.href = '/plots.php?action=edit&id=' + plotId;
}
</script>

<?php include 'includes/footer.php'; ?>