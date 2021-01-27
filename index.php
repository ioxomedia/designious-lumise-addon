<?php
/*
Name: Designious Library
Version: 1.0
Compatible: 1.7
*/

use \DesigniousLibrary\Api;

class lumise_addon_designious_library extends lumise_addons {
	private $api;

	public function __construct() {
		global $lumise;

		$this->access_corejs('lumise_addon_designiousLibrary');

		$lumise->add_filter('editor_menus', [ &$this, 'editor_menus'  ], 20);
		$lumise->add_filter('print-nav', [ &$this, 'disable_print_nav' ], 20);
		$lumise->add_action('editor-header', [ &$this, 'editor_header' ], 20);
		$lumise->add_action('editor-footer', [ &$this, 'editor_footer' ], 20);

		$lumise->add_action('addon-ajax', [ &$this, 'ajax_action' ]);

		if ($lumise->connector->platform == 'woocommerce') {
			$role = get_role('administrator');
			$role->add_cap('lumise_admin_designious');
		}

		$lumise->cfg->ex_settings([
			'designious_base_url' => '',
			'designious_api_key' => '',
			'designious_show_in_cliparts' => 'no',
			'designious_show_in_images' => 'no',
		]);

		add_action('woocommerce_checkout_order_processed', [&$this, 'on_order_process'], 20);
		add_action('woocommerce_order_refunded', [&$this, 'on_order_refund'], 20, 2);
		add_action('woocommerce_refund_deleted', [&$this, 'on_order_refund_delete'], 20, 2);
	}

	/**
	 * @return Api
	 */
	private function getApi()
	{
		global $lumise;

		if ($this->api === null) {
			require_once( __DIR__ . '/api.php' );

			$apiKey = isset($lumise->cfg->settings['designious_api_key'])
				? $lumise->cfg->settings['designious_api_key']
				: '';
			$baseUrl = isset($lumise->cfg->settings['designious_base_url'])
				? $lumise->cfg->settings['designious_base_url']
				: '';

			$this->api = new Api($apiKey, $baseUrl);
		}

		return $this->api;
	}

	public function editor_menus($args)
	{
		global $lumise;

		$showInCliparts = isset($lumise->cfg->settings['designious_show_in_cliparts'])
			? $lumise->cfg->settings['designious_show_in_cliparts']
			: false;

		$showInImages = isset($lumise->cfg->settings['designious_show_in_images'])
			? $lumise->cfg->settings['designious_show_in_images']
			: false;

		if ($showInImages) {
			$html = new DOMDocument();
			libxml_use_internal_errors( true );
			$html->loadHtml(
				'<div id="temporary-wrapper">' . $args['uploads']['content'] . '</div>',
				LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
			);
			$linkHtml = new DOMDocument();
			$linkHtml->loadHTML(
				'<button id="designious-data-nav">
				<i class="lumise-icon-feed"></i>
				' . $lumise->lang( 'Designious' ) . '
			</button>',
				LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
			);
			libxml_use_internal_errors( false );

			$args['uploads']['content'] = '';

			foreach ( $html->getElementById( 'temporary-wrapper' )->childNodes as $node ) {
				if ( $node->tagName === 'header' ) {
					$linkNode = $html->importNode( $linkHtml->getElementsByTagName( 'button' )[0], true );
					$node->appendChild( $linkNode );
				}
				$args['uploads']['content'] .= $html->saveHTML( $node );
			}

			$args['uploads']['content'] .= '<div data-tab="designious" id="lumise-designious-library">' . $this->render_xitems( array(
					"component" => "designious",
					"search"    => true,
					"category"  => true,
					"preview"   => true,
					"price"     => true
				) ) . '</div>';
		}

		if ($showInCliparts) {
			$args['cliparts']['content'] = '<header class="cliparts-tab-buttons lumise_form_group">
						<button class="active" data-cliparts-nav="internal">
							<i class="lumise-icon-cloud-upload"></i>
							Upload
						</button>
					<button id="designious-cliparts-data-nav" data-cliparts-nav="designious">
						<i class="lumise-icon-feed"></i>
						Designious
					</button>
			</header>
			<div data-cliparts-tab="internal" class="cliparts-tab active">' . $args['cliparts']['content'] . '</div>
			<div data-cliparts-tab="designious" class="cliparts-tab">' . $this->render_xitems( array(
					"component" => "designious-cliparts",
					"search"    => true,
					"category"  => true,
					"preview"   => true,
					"price"     => true
				) ) . '</div>';
		}

		return $args;
	}

	public function editor_header()
	{
		global $lumise;

		if (!$this->is_backend()) {
			$showInCliparts = isset($lumise->cfg->settings['designious_show_in_cliparts'])
				? $lumise->cfg->settings['designious_show_in_cliparts']
				: false;

			$showInImages = isset($lumise->cfg->settings['designious_show_in_images'])
				? $lumise->cfg->settings['designious_show_in_images']
				: false;

			if ($showInImages) {
				$file = 'library-images';
				if ( array_key_exists( 'images', $lumise->addons->actives ) ) {
					$file = 'library-images-with-images-addon';
				}
				echo sprintf(
					'<link rel="stylesheet" href="%s" type="text/css" media="all" />',
					$this->get_url( 'assets/css/' . $file . '.css?ver=1' )
				);
			}

			if ($showInCliparts) {
				echo sprintf(
					'<link rel="stylesheet" href="%s" type="text/css" media="all" />',
					$this->get_url( 'assets/css/library-cliparts.css?ver=1' )
				);
			}
		}
	}

	public function editor_footer()
	{
		global $lumise;

		if (!$this->is_backend()){
			echo '<script type="text/javascript" src="'.$this->get_url('assets/js/library.js?ver=1').'"></script>';
		}
	}

	public function ajax_action()
	{
		if (isset($_POST['component']) && in_array($_POST['component'], ['designious', 'designious-cliparts'])) {
			$this->load_files_from_remote();
		}
	}

	private function load_files_from_remote()
	{
		global $lumise;

		$category = htmlspecialchars(isset($_POST['category']) ? $_POST['category'] : 0);
		$index = (int)htmlspecialchars(isset($_POST['index']) ? $_POST['index'] : 0);
		$q = htmlspecialchars(isset($_POST['q']) ? $_POST['q'] : '');
		$limit = (int)htmlspecialchars(isset($_POST['limit']) ? $_POST['limit'] : 50);
		$cate_name = '';

		$hierarchy = get_transient('_transient_designious_library_folders');
		if ($hierarchy === false) {
			$hierarchy = $this->getApi()->getFolders();
			set_transient('_transient_designious_library_folders', $hierarchy, 300);
		}

		$folders = json_decode($hierarchy, true);
		$categories = [];
		foreach ($folders as $folder) {
			$categories[] = [
				'id' => $folder['path'],
				'name' => $lumise->lang($folder['name']),
				'thumbnail' => ! empty($folder['secure_image']) ? $folder['secure_image'] : ''
			];
		}
		array_unshift($categories, array(
			"id" => "featured",
			"name" => "&star; ".$lumise->lang('Featured'),
			"thumbnail" => $lumise->cfg->assets_url.'assets/images/featured_thumbn.jpg'
		));

		$queryParameters = [
			'keyword' => ! empty($q)
				? $q
				: (! $category || $category === 'featured' ? 'popular' : '*'),
			'per_page' => $limit,
			'page' => ($index / $limit) + 1
		];
		if ($category && $category !== 'featured') {
			$queryParameters['folder'] = $category;
		}

		$files = $this->getApi()->getFiles($queryParameters);

		$data = json_decode($files, true);
		$upstreamItems = $data['items'];
		$items = [];
		foreach ($upstreamItems as $idx => $upstreamItem) {
			$items[] = [
				'active' => 1,
				'author' => 0,
				'featured' => 0,
				'name' => $upstreamItem['filename'],
				'order' => $idx,
				'price' => null,
				'resource' => 'designious',
				'tags' => '',
				'thumbnail_url' => $upstreamItem['secure_url'],
				'use_count' => null,
				'id' => $upstreamItem['etag'],
				'upload' => $upstreamItem['secure_url']
			];
		}
		$total = $data['total'];

		header('Content-Type: application/json');

		echo json_encode(array(
			"category" => $category,
			"category_name" => $lumise->lang($cate_name),
			"category_parents" => [ [ 'id' => '', 'name' => 'All categories' ] ],
			"categories" => $categories,
			"categories_full" => '',
			"items" => $items,
			"q" => $q,
			"total" => $total - 1,
			"index" => $index,
			"page" => 1,
			"limit" => $limit
		));
	}

	public function settings() {

		global $lumise;

		return array(
			array(
				'type' => 'input',
				'name' => 'designious_base_url',
				'label' => $lumise->lang('Designious base URL'),
				'desc' => $lumise->lang('Supplied by Designious.'),
			),
			array(
				'type' => 'input',
				'name' => 'designious_api_key',
				'label' => $lumise->lang('Designious API key'),
				'desc' => $lumise->lang('Supplied by Designious.'),
			),
			array(
				'type' => 'toggle',
				'name' => 'designious_show_in_cliparts',
				'label' => $lumise->lang('Show in Cliparts'),
				'desc' => $lumise->lang('Load assets from Designious Library inside Cliparts area.'),
				'default' => 'no',
				'value' => 'yes'
			),
			array(
				'type' => 'toggle',
				'name' => 'designious_show_in_images',
				'label' => $lumise->lang('Show in Images'),
				'desc' => $lumise->lang('Load assets from Designious Library inside Images area.'),
				'default' => 'no',
				'value' => 'yes'
			),
		);
	}

	static function active() {

		global $lumise;

		$lumise->db->rawQuery("CREATE TABLE IF NOT EXISTS `".$lumise->db->prefix."designious` (
			`id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`name` varchar(255) CHARACTER SET utf8 NOT NULL,
			`price` float DEFAULT '0',
			`author` int(11) DEFAULT NULL,
			`created` datetime DEFAULT NULL,
			`updated` datetime DEFAULT NULL
			) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;");
	}

	function on_order_process($orderId)
	{
		$order = wc_get_order($orderId);

		$response = $this->getApi()->postOrder($order);
		$result = json_decode($response, true);

		if ($result['success'] === false) {
			$order->update_status('cancelled', 'Designious Library API: ' . $result['error_message']);
			throw new Exception( 'Designious Library API: ' . $result['error_message']);
		}
	}

	function on_order_refund($orderId, $refundId)
	{
		$order = wc_get_order($orderId);
		$refund = wc_get_order($refundId);

		$response = $this->getApi()->postRefund($order, $refund);
		$result = json_decode($response, true);

		if ($result['success'] === false) {
			throw new Exception( 'Designious Library API: ' . $result['error_message']);
		}
	}

	function on_order_refund_delete($refundId, $orderId)
	{
		$order = wc_get_order($orderId);
		$refund = wc_get_order($refundId);
	}

	function disable_print_nav($nav)
	{
		return '';
	}

	function help()
	{
		$response = get_transient('_transient_designious_library_statistics');
		if ($response === false) {
			$response = $this->getApi()->getStatistics();
			set_transient('_transient_designious_library_statistics', $response, 60);
		}

		$statistics = json_decode($response, true);
		$callsThisMonth = 0;
		$callsLastMonth = 0;
		if (! empty($statistics['success']) && ! empty($statistics['data']) && ! empty($statistics['data']['items'])) {
			$items = $statistics['data']['items'];
			$callsThisMonth = array_sum(array_column(array_filter($items, function($item) {
				return $item['month'] == (int) date('m');
			}), 'hits'));
			$callsLastMonth = array_sum(array_column(array_filter($items, function($item) {
				return $item['month'] == (int) date('m', '-1 month');
			}), 'hits'));
		}

		echo '<div class="lumise_form_submit left" style="width: 100%">
			<h3>API Statistics</h3>
			<p>Calls this month: <strong>' . $callsThisMonth . '</strong></p>
			<p>Calls last month: <strong>' . $callsLastMonth . '</strong></p>
		</div>';
	}
}