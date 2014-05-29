<?php

/**
 * bs_grid, helper class for jquery.bs_grid plugin, handles server operations (mainly through AJAX requests).
 *
 * see ajax_page_data.dist.php for usage instructions
 *
 * Da Capo database wrapper is required https://github.com/pontikis/dacapo
 * jui_filter_rules is required  https://github.com/pontikis/jui_filter_rules
 *
 * @version 0.9.2 (28 May 2014)
 * @author Christos Pontikis http://www.pontikis.net
 * @license  http://opensource.org/licenses/MIT MIT license
 **/
class bs_grid {

	/** @var string Last error occured */
	private $last_error;
	/** @var string Last filter error occured */
	private $last_filter_error;
	/** @var string Debug message */
	private $debug_message;

	/**
	 * Constructor
	 *
	 * @param dacapo $ds
	 * @param jui_filter_rules $jfr
	 * @param $page_settings
	 * @param bool $debug_mode
	 */
	public function __construct(dacapo $ds, jui_filter_rules $jfr, $page_settings, $debug_mode = false) {
		// initialize
		$this->ds = $ds;
		$this->jfr = $jfr;
		$this->page_settings = $page_settings;
		$this->debug_mode = $debug_mode;

		$this->last_error = null;
		$this->last_filter_error = array();
		$this->debug_message = array();
	}

	/**
	 * Get last error
	 *
	 * @return null|string
	 */
	public function get_last_error() {
		return $this->last_error;
	}

	/**
	 * Get last filter error
	 *
	 * @return array|string
	 */
	public function get_last_filter_error() {
		return $this->last_filter_error;
	}

	/**
	 * Get debug message
	 *
	 * @return array|string
	 */
	public function get_debug_message() {
		return $this->debug_message;
	}

	/**
	 * Gets whereSQL and bind_params array using jui_filter_rules class
	 *
	 * @param $filter_rules
	 * @return array
	 */
	public function get_whereSQL($filter_rules) {

		$ds = $this->ds;
		$jfr = $this->jfr;
		$last_jfr_error = array(
			'element_rule_id' => null,
			'error_message' => null
		);

		if(count($filter_rules) == 0) {
			$result = array('sql' => '', 'bind_params' => array());
		} else {
			$result = $jfr->parse_rules($filter_rules);

			$last_jfr_error = $jfr->get_last_error();
			if(!is_null($last_jfr_error['error_message'])) {
				$result = $last_jfr_error;
			}
		}

		if($this->debug_mode) {
			array_push($this->debug_message, 'WHERE  SQL: ' . $result['sql']);
			array_push($this->debug_message, 'BIND PARAMS: ' . print_r($result['bind_params'], true));
			if($ds->use_pst) {
				$bind_params_type = '';
				foreach($result["bind_params"] as $bind_param) {
					$bind_params_type .= gettype($bind_param) . ' ';
				}
				array_push($this->debug_message, 'BIND PARAMS TYPE: ' . $bind_params_type);
			}
			array_push($this->debug_message, 'PREPARED STATEMENTS: ' . ($ds->use_pst ? "yes" : "no"));
			if(count($filter_rules) > 0) {
				if(!is_null($last_jfr_error['error_message'])) {
					array_push($this->debug_message, 'FILTER ERROR: ' . print_r($last_jfr_error['error_message'], true));
				}
			}
		}

		return $result;
	}

	/**
	 * Get sorting SQL (ORDER BY clause)
	 *
	 * @param array $sorting
	 * @return string
	 */
	private function get_sortingSQL($sorting) {
		$sortingSQL = '';
		foreach($sorting as $sort) {
			if($sort['order'] == 'ascending') {
				$sortingSQL .= $sort['field'] . ' ASC, ';
			} else if($sort['order'] == 'descending') {
				$sortingSQL .= ' ' . $sort['field'] . ' DESC, ';
			}
		}
		$len = mb_strlen($sortingSQL);
		if($len > 0) {
			$sortingSQL = ' ORDER BY ' . substr($sortingSQL, 0, $len - 2) . ' ';
		}

		if($this->debug_mode) {
			array_push($this->debug_message, 'sortingSQL: ' . $sortingSQL);
		}
		return $sortingSQL;
	}

	/**
	 * Gets total rows count
	 *
	 * @param string $selectCountSQL
	 * @param string $whereSQL
	 * @param array $a_bind_params
	 * @return int|bool Total rows or false
	 */
	public function get_total_rows($selectCountSQL, $whereSQL, $a_bind_params) {

		$ds = $this->ds;

		$sql = $selectCountSQL . ' ' . $whereSQL;
		$query_options = array(
			'get_row' => true
		);
		$res = $ds->select($sql, $a_bind_params, $query_options);
		if(!$res) {
			$this->last_error = $ds->last_error;
			$total_rows = false;
		} else {
			$rs = $ds->data;
			$total_rows = $rs['totalrows'];
		}

		if($this->debug_mode) {
			array_push($this->debug_message, 'RDBMS: ' . $ds->rdbms);
			array_push($this->debug_message, 'selectCountSQL: ' . $selectCountSQL);
			array_push($this->debug_message, 'total_rows: ' . $total_rows);
		}

		return $total_rows;
	}


	/**
	 * @param $selectSQL
	 * @param $whereSQL
	 * @param $a_bind_params
	 * @param $sortingSQL
	 * @param $page_num
	 * @param $rows_per_page
	 * @return bool|null
	 */
	public function fetch_page_data($selectSQL, $whereSQL, $a_bind_params, $sortingSQL, $page_num, $rows_per_page) {

		$ds = $this->ds;

		$limitSQL = $ds->limit($rows_per_page, ($page_num - 1) * $rows_per_page);

		$sql = $selectSQL . ' ' . $whereSQL . ' ' . $sortingSQL . ' ' . $limitSQL;
		$res = $ds->select($sql, $a_bind_params);
		if(!$res) {
			$this->last_error = $ds->last_error;
			$a_data = false;
		} else {
			$a_data = $ds->data;
		}

		if($this->debug_mode) {
			array_push($this->debug_message, 'selectSQL: ' . $selectSQL);
		}
		return $a_data;

	}


	/**
	 * Get page data
	 *
	 * @return array
	 */
	public function get_page_data() {

		$total_rows = null;
		$a_data = null;

		// initialize
		$result = array(
			'total_rows' => null,
			'page_data' => null,
			'error' => null,
			'filter_error' => array(),
			'debug_message' => array()
		);

		$where = $this->get_whereSQL($this->page_settings['filter_rules']);
		if(array_key_exists('error_message', $where)) {
			$this->last_filter_error = $where;
		} else {

			$total_rows = $this->get_total_rows($this->page_settings['selectCountSQL'],
				$where['sql'], $where['bind_params']);

			if($total_rows !== false) {

				// calculate sortingSQL
				$sortingSQL = $this->get_sortingSQL($this->page_settings['sorting']);

				$a_data = $this->fetch_page_data(
					$this->page_settings['selectSQL'],
					$where['sql'],
					$where['bind_params'],
					$sortingSQL,
					$this->page_settings['page_num'],
					$this->page_settings['rows_per_page']);
			}
		}

		$result['total_rows'] = $total_rows;
		$result['page_data'] = $a_data;
		$result['error'] = $this->get_last_error();
		$result['filter_error'] = $this->get_last_filter_error();
		$result['debug_message'] = $this->get_debug_message();

		return $result;
	}

}