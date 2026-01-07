<?php
$page_title = 'Menu Planner';
require_once '../../includes/header.php';
require_once '../../helpers/MenuHarianHelper.php';
// require_once '../../helpers/date_helpers.php'; // Removed: file does not exist
require_once '../../helpers/functions.php'; // Ensure functions.php is included for date formatting

// Inputs
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Navigation params
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Get Menus for this month
$start_date = sprintf('%04d-%02d-01', $year, $month);
$end_date = date('Y-m-t', strtotime($start_date));

$menuHelper = new MenuHarianHelper();
$menus = $menuHelper->getMenusByDateRange($start_date, $end_date);

// Calendar Logic
$first_day_timestamp = strtotime($start_date);
$days_in_month = date('t', $first_day_timestamp);
$day_of_week_start = date('N', $first_day_timestamp); // 1 (Mon) to 7 (Sun)

// Adjust to start from Sunday (if preferred) or Monday used here as 1
// Let's use Monday as start of week
$start_offset = $day_of_week_start - 1; 

require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<style>
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 10px;
    }
    .calendar-day-header {
        font-weight: bold;
        text-align: center;
        padding: 10px;
        background: #f8f9fc;
        border-radius: 5px;
    }
    .calendar-day {
        min-height: 150px;
        border: 1px solid #e3e6f0;
        border-radius: 5px;
        padding: 5px;
        background: white;
        transition: all 0.2s;
    }
    .calendar-day:hover {
        border-color: #4e73df;
        box-shadow: 0 0 10px rgba(0,0,0,0.05);
    }
    .calendar-day.other-month {
        background: #f8f9fc;
        color: #b7b9cc;
    }
    .calendar-day.today {
        border: 2px solid #4e73df;
    }
    .menu-item-badge {
        display: block;
        padding: 4px 8px;
        margin-bottom: 4px;
        border-radius: 4px;
        font-size: 0.75rem;
        cursor: pointer;
        text-decoration: none;
        color: white;
        transition: opacity 0.2s;
    }
    .menu-item-badge:hover {
        opacity: 0.8;
        color: white;
    }
    .menu-draft { background-color: #858796; }
    .menu-approved { background-color: #4e73df; }
    .menu-processing { background-color: #f6c23e; color: #3a3b45; }
    .menu-completed { background-color: #1cc88a; }
    .menu-cancelled { background-color: #e74a3b; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-calendar-alt me-2"></i>Menu Planner
            </h1>
            <p class="mb-0 text-muted">Perencanaan menu bulanan</p>
        </div>
        <div class="btn-group">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
            <a href="catalog.php" class="btn btn-info text-white">
                <i class="fas fa-book-open me-1"></i> Menu Catalog
            </a>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#shoppingModal">
                <i class="fas fa-shopping-basket me-1"></i> Hitung Belanja
            </button>
        </div>
    </div>

    <!-- Calendar Controls -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center bg-white">
            <a href="?month=<?= $prev_month ?>&year=<?= $prev_year ?>" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-chevron-left"></i> Bulan Lalu
            </a>
            <h5 class="m-0 font-weight-bold text-primary text-uppercase">
                <?= date('F Y', strtotime($start_date)) ?>
            </h5>
            <a href="?month=<?= $next_month ?>&year=<?= $next_year ?>" class="btn btn-sm btn-outline-primary">
                Bulan Depan <i class="fas fa-chevron-right"></i>
            </a>
        </div>
        <div class="card-body bg-light">
            <div class="calendar-grid mb-2">
                <div class="calendar-day-header text-danger">Mugg</div>
                <div class="calendar-day-header">Sen</div>
                <div class="calendar-day-header">Sel</div>
                <div class="calendar-day-header">Rab</div>
                <div class="calendar-day-header">Kam</div>
                <div class="calendar-day-header">Jum</div>
                <div class="calendar-day-header text-danger">Sab</div>
            </div>
            
            <div class="calendar-grid">
                <?php
                // Logic to draw blanks for offset
                for ($i = 0; $i < $start_offset; $i++) {
                    echo '<div class="calendar-day other-month"></div>';
                }

                // Days
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $current_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $is_today = $current_date == date('Y-m-d');
                    $day_menus = isset($menus[$current_date]) ? $menus[$current_date] : [];
                    
                    echo '<div class="calendar-day ' . ($is_today ? 'today' : '') . '">';
                    echo '<div class="d-flex justify-content-between align-items-start mb-2">';
                    echo '<span class="fw-bold ' . ($is_today ? 'text-primary' : '') . '">' . $day . '</span>';
                    echo '<div class="btn-group">';
                    echo '<a href="planner_schedule.php?date=' . $current_date . '" class="btn btn-xs btn-outline-success p-0 px-1" title="Jadwalkan dari Catalog"><i class="fas fa-calendar-plus"></i></a>';
                    echo '<a href="create.php?date=' . $current_date . '" class="btn btn-xs btn-outline-light text-muted p-0 px-1" title="Buat Menu Custom"><i class="fas fa-plus-circle"></i></a>';
                    echo '</div>';
                    echo '</div>';
                    
                    if (!empty($day_menus)) {
                        foreach ($day_menus as $m) {
                            $status_class = 'menu-' . $m['status'];
                            echo '<a href="detail.php?id=' . $m['id'] . '" class="menu-item-badge ' . $status_class . '">';
                            echo '<div class="text-truncate fw-bold">' . htmlspecialchars($m['nama_menu']) . '</div>';
                            echo '<div class="d-flex justify-content-between gap-1" style="font-size:0.65rem">';
                            echo '<span><i class="fas fa-users"></i> ' . $m['total_porsi'] . '</span>';
                            echo '<span>' . ($m['nama_kantor'] ?? 'All') . '</span>';
                            echo '</div>';
                            echo '</a>';
                        }
                    }
                    echo '</div>';
                }

                // Trailing blanks? Optional.
                ?>
            </div>
        </div>
    </div>
</div>

<!-- Shopping Calculation Modal -->
<div class="modal fade" id="shoppingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Kalkulasi Belanja</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="shopping_list.php" method="GET" target="_blank">
                    <div class="mb-3">
                        <label class="form-label">Dari Tanggal</label>
                        <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sampai Tanggal</label>
                        <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d', strtotime($start_date . ' + 1 week')) ?>" required>
                    </div>
                    <div class="alert alert-info small">
                        <i class="fas fa-info-circle me-1"></i>
                        Sistem akan menghitung total kebutuhan bahan dari semua menu yang terjadwal dalam rentang tanggal ini.
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-calculator me-1"></i> Hitung & Tampilkan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
