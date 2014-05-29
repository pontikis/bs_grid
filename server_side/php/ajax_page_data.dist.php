<?php
/**
 * ajax_page_data.dist.php, bs_grid ajax fetch page data template script
 *
 * Sample php file getting totalrows and page data
 *
 * Da Capo database wrapper is required https://github.com/pontikis/dacapo
 *
 * @version 0.9.2 (28 May 2014)
 * @author Christos Pontikis http://pontikis.net
 * @license  http://opensource.org/licenses/MIT MIT license
 **/

// PREVENT DIRECT ACCESS (OPTIONAL) --------------------------------------------
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) AND
strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if(!$isAjax) {
	print 'Access denied - not an AJAX request...' . ' (' . __FILE__ . ')';
	exit;
}

// REQUIRED --------------------------------------------------------------------
require_once '/path/to/dacapo.php';                                 // CONFIGURE
require_once '/path/to/jui_filter_rules.php';                       // CONFIGURE
require_once '/path/to/bs_grid.php';                                // CONFIGURE

// create new datasource                                            // CONFIGURE
$db_settings = array(
	'rdbms' => 'MYSQLi',
	'db_server' => 'localhost',
	'db_user' => 'DB_USER_HERE',
	'db_passwd' => 'DB_PASS_HERE',
	'db_name' => 'DB_NAME',
	'db_port' => '3306',
	'charset' => 'utf8',
	'use_pst' => true,
	'pst_placeholder' => 'question_mark'
);
$ds = new dacapo($db_settings, null);

$page_settings = array(
	"selectCountSQL" => "SQL_HERE",                                 // CONFIGURE
	"selectSQL" => "SQL_HERE",                                      // CONFIGURE
	"page_num" => $_POST['page_num'],
	"rows_per_page" => $_POST['rows_per_page'],
	"columns" => $_POST['columns'],
	"sorting" =>  isset($_POST['sorting']) ? $_POST['sorting'] : array(),
	"filter_rules" => isset($_POST['filter_rules']) ? $_POST['filter_rules'] : array()
);

$jfr = new jui_filter_rules($ds);
$jdg = new bs_grid($ds, $jfr, $page_settings, $_POST['debug_mode'] == "yes" ? true : false);

$data = $jdg->get_page_data();

// data conversions (if necessary)
foreach($data['page_data'] as $key => $row) {
	// your code here
}

echo json_encode($data);