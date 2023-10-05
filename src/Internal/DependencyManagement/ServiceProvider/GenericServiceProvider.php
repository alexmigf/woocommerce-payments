<?php
/**
 * GenericServiceProvider class.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\DependencyManagement\ServiceProvider;

use WC_Payments_Order_Service;
use WCPay\Internal\DependencyManagement\AbstractServiceProvider;
use WCPay\Internal\Service\OrderService;

/**
 * WCPay payments generic service provider.
 */
class GenericServiceProvider extends AbstractServiceProvider {
	/**
	 * Contains all provided classes/aliases.
	 *
	 * @var string[]
	 */
	protected $provides = [
		OrderService::class,
	];

	/**
	 * Registers all provided classes.
	 */
	public function register(): void {
		$container = $this->getContainer();

		$container->addShared( OrderService::class )
			->addArgument( WC_Payments_Order_Service::class );
	}
}
