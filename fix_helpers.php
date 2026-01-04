<?php
$files = ['helpers/functions.php', 'helpers/constants.php'];
$functions = [
    'generate_number',
    'format_tanggal',
    'format_datetime',
    'format_rupiah',
    'format_number',
    'clean_input',
    'upload_file',
    'generate_qr_code',
    'set_flash',
    'get_flash',
    'show_flash',
    'get_status_badge',
    'generate_pagination',
    'logActivity'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    
    $content = file_get_contents($file);
    
    foreach ($functions as $func) {
        // Find function declaration
        $pattern = '/^function\s+' . $func . '\s*\(/m';
        if (preg_match($pattern, $content)) {
            // Check if already wrapped
            $wrap_pattern = '/if\s*\(!function_exists\(\'' . $func . '\'\)\)\s*\{/i';
            if (!preg_match($wrap_pattern, $content)) {
                // Find the function block and wrap it
                // This is slightly complex with regex, let's use a simpler marker-based approach if possible
                // Actually, let's just use string replacement for the header and then find the closing brace
                
                // Better approach: wrap the whole function block
                // Since our files are well-structured, we can try to find the end brace
                
                // Let's just do a very safe replacement of the header
                $content = preg_replace($pattern, "if (!function_exists('$func')) {\nfunction $func(", $content);
                
                // Now we need to find the closing brace. This is the tricky part.
                // Since we know the structure of these files, most functions end with a brace at start of line
                // or after some indentation.
                
                // Simplified: Let's assume the functions are in the same relative order and structure.
                // Actually, I'll just write a more robust parser or just use a simpler marker.
                
                echo "Wrapping $func in $file\n";
            }
        }
    }
    // file_put_contents($file, $content);
}
?>
