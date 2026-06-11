<?php
$file = 'admin/admin.php';
$content = file_get_contents($file);

// 1. Replace the hamster wheel loaders
$hamster_pattern = '/<div aria-label="Orange and tan hamster running in a metal wheel" role="img" class="wheel-and-hamster">.*?<div class="loader-text">Loading, please wait...<\/div>/s';

$new_loader = <<<HTML
<div class="modern-spinner" style="transform: scale(0.5);">
    <div></div><div></div><div></div><div></div>
</div>
<div class="loader-text" style="font-size: 0.85rem; margin-top: 15px; color: var(--primary-color);">Loading...</div>
HTML;

$content = preg_replace($hamster_pattern, $new_loader, $content);

// 2. Remove the datatable completely
// We will look for the specific header "Transactions (" and remove that entire row container
$table_pattern = '/<div class="row">\s*<div class="col-lg-12">\s*<div class="card shadow mb-4">\s*<!-- Card Loader -->.*?<\/table>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>/s';
// Actually, let's use a safer regex. It starts at '<div class="row">\s*<div class="col-lg-12">\s*<div class="card shadow mb-4">'
// and ends at '</table>\s*</div>\s*</div>\s*</div>\s*</div>\s*</div>\s*</div>'
$table_pattern = '/<div class="row">\s*<div class="col-lg-12">\s*<div class="card shadow mb-4">\s*<!-- Card Loader -->.*?<\/table>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>/s';
// Let's see if this matches
if (preg_match($table_pattern, $content)) {
    $content = preg_replace($table_pattern, '', $content);
    echo "Removed datatable.\n";
} else {
    // try alternative pattern if spacing is different
    $alt_pattern = '/<div class="row">\s*<div class="col-lg-12">\s*<div class="card shadow mb-4">.*?<\/table>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>/s';
    $content = preg_replace($alt_pattern, '', $content);
    echo "Removed datatable using alt pattern.\n";
}


// 3. Update the filter form
$form_pattern = '/<form method="GET" class="mb-4" id="dashboardFilterForm">.*?<\/form>/s';

$new_form = <<<'HTML'
<form method="GET" class="mb-4" id="dashboardFilterForm">
    <div class="card bg-white border-0" style="border-radius: var(--radius-lg); box-shadow: var(--shadow-soft);">
        <div class="card-body p-4">
            <div class="row align-items-end">
                <div class="col-12 col-md-3 mb-3 mb-md-0">
                    <div class="form-group mb-0">
                        <label class="text-xs font-weight-bold text-uppercase mb-2" style="color: var(--text-muted); letter-spacing: 0.5px;">
                            <i class="fas fa-calendar-alt mr-2" style="color: var(--primary-color);"></i>Start Date
                        </label>
                        <input type="date" name="start_date" id="start_date" class="form-control border-0"
                            value="<?php echo htmlspecialchars($start_date); ?>" 
                            style="background-color: #F4F7FE; border-radius: var(--radius-md); height: 50px; color: var(--text-main); font-weight: 600;">
                    </div>
                </div>
                <div class="col-12 col-md-3 mb-3 mb-md-0">
                    <div class="form-group mb-0">
                        <label class="text-xs font-weight-bold text-uppercase mb-2" style="color: var(--text-muted); letter-spacing: 0.5px;">
                            <i class="fas fa-calendar-alt mr-2" style="color: var(--primary-color);"></i>End Date
                        </label>
                        <input type="date" name="end_date" id="end_date" class="form-control border-0"
                            value="<?php echo htmlspecialchars($end_date); ?>" 
                            style="background-color: #F4F7FE; border-radius: var(--radius-md); height: 50px; color: var(--text-main); font-weight: 600;">
                    </div>
                </div>
                <div class="col-12 col-md-4 mb-3 mb-md-0">
                    <div class="form-group mb-0">
                        <label class="text-xs font-weight-bold text-uppercase mb-2" style="color: var(--text-muted); letter-spacing: 0.5px;">
                            <i class="fas fa-building mr-2" style="color: var(--primary-color);"></i>Branch
                        </label>
                        <select name="branch" id="branch" class="form-control border-0" 
                            style="background-color: #F4F7FE; border-radius: var(--radius-md); height: 50px; color: var(--text-main); font-weight: 600;">
                            <option value="">All Branches</option>
                            <?php foreach ($branches as $branch_option): ?>
                                <option value="<?php echo htmlspecialchars($branch_option); ?>" 
                                        <?php echo (isset($_GET['branch']) && $_GET['branch'] === $branch_option) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($branch_option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center" 
                        style="height: 50px; border-radius: var(--radius-md); font-weight: 700; letter-spacing: 0.5px;">
                        <i class="fas fa-search mr-2"></i> Filter
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
HTML;

$content = preg_replace($form_pattern, $new_form, $content);

file_put_contents($file, $content);
echo "Successfully updated admin UI phase 2.\n";
?>
