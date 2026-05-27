<?php
if ( ! defined( 'ABSPATH' ) ) exit;
function tao_crm_page_import() {
    wp_redirect( admin_url( 'admin.php?page=tao-crm-settings&tab=workspaces#tao-import-section' ) );
    exit;
}
