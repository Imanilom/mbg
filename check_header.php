<?php
$page_title = 'Form Menu Catalog';
require_once '../../helpers/MenuCatalogHelper.php';

// We need session started and user checked before headers.
// But header.php usually starts session.
// So we will do a manual session start and auth check here for the redirect logic,
// OR simply move the POST logic to be handled BEFORE displaying anything.

// Let's assume header.php handles session start. 
// However, we want to process POST before header.php output.
// So we must manually include config/auth dependencies if possible, OR
// rely on the user having a valid session.

// But wait, if header.php outputs, we can't redirect.
// Best practice:
// 1. Init session/auth
// 2. Process logic (POST/Redirects)
// 3. Output View (require header.php, HTML)

// Since I don't want to duplicate all auth logic from header.php, 
// I will look at what header.php does.
// Usually header.php does: session_start(), check login.

// STRATEGY: 
// Move POST handling to top.
// Inside POST handling, we might need $user array.
// If $user is not available until header.php, we have a problem.
// Let's check header.php content quickly if needed, but safer to assume standard pattern.

// Let's try to just move the POST block up.
// But we need $conn and $user. 
// $conn comes from config/database.php (likely included in header.php or init.php)
// $user comes from auth check.

// I'll grab database connection manually if needed, or better:
// Include 'functions.php' or 'config.php' if they exist, but header.php is the main entry.

// Let's check `includes/header.php` to see what it requires.
?>
