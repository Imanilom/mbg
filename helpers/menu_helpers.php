<?php
/**
 * Menu Helper Functions
 * Common helper functions for menu module
 */

/**
 * Get status badge HTML for menu
 */
if (!function_exists('get_menu_status_badge')) {
    function get_menu_status_badge($status) {
        $badges = [
            'draft' => '<span class="badge bg-secondary rounded-pill px-3">DRAFT</span>',
            'approved' => '<span class="badge bg-primary rounded-pill px-3">APPROVED</span>',
            'processing' => '<span class="badge bg-warning text-dark rounded-pill px-3">PROCESSING</span>',
            'completed' => '<span class="badge bg-success rounded-pill px-3">COMPLETED</span>',
            'cancelled' => '<span class="badge bg-danger rounded-pill px-3">CANCELLED</span>'
        ];
        return $badges[$status] ?? '<span class="badge bg-light text-dark rounded-pill px-3">' . strtoupper($status) . '</span>';
    }
}
