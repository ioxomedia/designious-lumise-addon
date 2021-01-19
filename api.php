<?php

namespace DesigniousLibrary;

use WC_Order;
use WC_Order_Item;
use WC_Order_Refund;

class Api {
	const FOLDERS_ENDPOINT = '/api/folders/search';
	const FILES_ENDPOINT = '/api/files/search';
	const TRANSACTION_ENDPOINT = '/api/transactions';
	const STATISTICS_ENDPOINT = '/api/statistics';
	const ORDER_PLACE_ENDPOINT = '/api/order/place';
	const ORDER_REFUND_ENDPOINT = '/api/order/refund';

	const METHOD_GET = 'GET';
	const METHOD_POST = 'POST';

	/**
	 * @var string $baseUrl
	 */
	private $baseUrl;

	/**
	 * @var string $apiKey
	 */
	private $apiKey;

	public function __construct(string $apiKey, string $baseUrl)
	{
		$this->apiKey = $apiKey;
		$this->baseUrl = $baseUrl;
	}

	public function getFolders()
	{
		return $this->call(self::FOLDERS_ENDPOINT);
	}

	public function getFiles(array $parameters = [])
	{
		return $this->call(self::FILES_ENDPOINT, $parameters);
	}

	public function getTransactions(string $startDate, string $endDate)
	{
		return $this->call(self::TRANSACTION_ENDPOINT, [
			'start_date' => $startDate,
			'end_date' => $endDate
		]);
	}

	public function getStatistics()
	{
		return $this->call(self::STATISTICS_ENDPOINT);
	}

	public function postOrder(WC_Order $order)
	{
		$items = [];
		/** @var WC_Order_Item[] $items */
		$orderItems = $order->get_items();
		foreach ($orderItems as $orderItem) {
			$data = $orderItem->get_meta('lumise_data');
			if (is_array($data) && isset($data['resource']) && !empty($data['resource'])) {
				$resources = $data['resource'];
				foreach ($resources as $resource) {
					if ($resource['type'] == 'designious') {
						$items[] = [
							'reference_id' => $order->get_order_key() . '_' . $orderItem->get_id(),
							'quantity' => $orderItem->get_quantity()
						];
					}
				}
			}
		}
		$payload = [
			'reference_id' => $order->get_order_key(),
			'items' => $items
		];
		return $this->call(self::ORDER_PLACE_ENDPOINT, $payload, self::METHOD_POST);
	}

	public function postRefund(WC_Order $order, WC_Order_Refund $refund)
	{
		$items = [];
		/** @var WC_Order_Item $refundItems */
		$refundItems = $refund->get_items();
		foreach ($refundItems as $refundItem) {
			$orderItemId = $refundItem->get_meta('_refunded_item_id');
			$orderItem = $order->get_item($orderItemId);
			$data = $orderItem->get_meta('lumise_data');
			if (is_array($data) && isset($data['resource']) && !empty($data['resource'])) {
				$resources = $data['resource'];
				foreach ($resources as $resource) {
					if ($resource['type'] == 'designious') {
						$items[] = [
							'reference_id' => $order->get_order_key() . '_' . $orderItem->get_id(),
							'quantity' => $refundItem->get_quantity()
						];
					}
				}
			}
		}
		$payload = [
			'reference_id' => $order->get_order_key(),
			'items' => $items
		];
		return $this->call(self::ORDER_REFUND_ENDPOINT, $payload, self::METHOD_POST);
	}

	private function call(string $endpoint, array $parameters = [], string $method = self::METHOD_GET)
	{
		$url = $this->baseUrl . $endpoint;
		if (! empty($parameters) && $method === self::METHOD_GET) {
			$url .= '?' . http_build_query($parameters);
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Authorization: Bearer ' . $this->apiKey ]);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		if ($method === self::METHOD_POST) {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
		}

		$result = curl_exec($ch);
		curl_close($ch);

		return $result;
	}
}