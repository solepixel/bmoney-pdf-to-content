<?php
/**
 * Plugin Name: BMoney PDF to Content
 * Plugin URI: https://github.com/solepixel/bmoney-pdf-to-content/
 * Description: Converts PDFs to Text and inserts into post content for attachments
 * Version: 1.0
 * Author: Brian DiChiara
 * Author URI: http://www.briandichiara.com
 */

define('BMPTC_VERSION', '1.0');
define('BMPTC_PI_NAME', 'PDF to Content');
define('BMPTC_PI_DESCRIPTION', 'Converts PDFs to Text and inserts into post content for attachments.');
define('BMPTC_OPT_PREFIX', 'bmptc_');
define('BMPTC_PATH', plugin_dir_path( __FILE__ ));
define('BMPTC_DIR', plugin_dir_url( __FILE__ ));

require_once(BMPTC_PATH.'classes/bm-pdf-to-content.class.php');

global $bmptc_plugin;
$bmptc_plugin = new BM_PDF_to_Content();
$bmptc_plugin->initialize();