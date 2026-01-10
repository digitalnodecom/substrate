<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Tools\WordPress;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class WooCommerceInfo extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'woocommerce-info';

    /**
     * The tool's description.
     */
    protected string $description = 'Get WooCommerce store information including settings, product types, and order statuses. Only available if WooCommerce is installed and active.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        // Check if WooCommerce is available
        if (! class_exists('WooCommerce') || ! function_exists('WC')) {
            return Response::error('WooCommerce plugin is not installed or not active.');
        }

        $wc = WC();

        $result = [
            'version' => defined('WC_VERSION') ? WC_VERSION : 'unknown',
            'currency' => get_woocommerce_currency(),
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'store' => [
                'country' => $wc->countries->get_base_country(),
                'state' => $wc->countries->get_base_state(),
                'city' => $wc->countries->get_base_city(),
                'postcode' => $wc->countries->get_base_postcode(),
            ],
            'settings' => [
                'prices_include_tax' => wc_prices_include_tax(),
                'tax_enabled' => wc_tax_enabled(),
                'shipping_enabled' => wc_shipping_enabled(),
                'coupons_enabled' => wc_coupons_enabled(),
                'reviews_enabled' => wc_reviews_enabled(),
            ],
            'product_types' => wc_get_product_types(),
            'order_statuses' => wc_get_order_statuses(),
            'payment_gateways' => $this->getPaymentGateways(),
            'shipping_methods' => $this->getShippingMethods(),
            'endpoints' => $this->getEndpoints(),
        ];

        // Get counts
        $result['counts'] = [
            'products' => $this->getProductCount(),
            'orders' => $this->getOrderCount(),
            'customers' => $this->getCustomerCount(),
        ];

        return Response::json($result);
    }

    /**
     * Get available payment gateways.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getPaymentGateways(): array
    {
        $gateways = WC()->payment_gateways()->payment_gateways();
        $result = [];

        foreach ($gateways as $id => $gateway) {
            $result[$id] = [
                'title' => $gateway->get_title(),
                'enabled' => $gateway->is_available(),
                'description' => $gateway->get_description(),
            ];
        }

        return $result;
    }

    /**
     * Get available shipping methods.
     *
     * @return array<string, string>
     */
    protected function getShippingMethods(): array
    {
        $methods = WC()->shipping()->get_shipping_methods();
        $result = [];

        foreach ($methods as $id => $method) {
            $result[$id] = $method->get_method_title();
        }

        return $result;
    }

    /**
     * Get WooCommerce endpoints.
     *
     * @return array<string, string>
     */
    protected function getEndpoints(): array
    {
        return [
            'cart' => wc_get_cart_url(),
            'checkout' => wc_get_checkout_url(),
            'myaccount' => wc_get_page_permalink('myaccount'),
            'shop' => wc_get_page_permalink('shop'),
            'terms' => wc_get_page_permalink('terms'),
        ];
    }

    /**
     * Get product count.
     */
    protected function getProductCount(): int
    {
        $counts = wp_count_posts('product');

        return (int) ($counts->publish ?? 0);
    }

    /**
     * Get order count.
     */
    protected function getOrderCount(): int
    {
        $counts = wp_count_posts('shop_order');
        $total = 0;

        foreach ($counts as $status => $count) {
            if (str_starts_with($status, 'wc-')) {
                $total += (int) $count;
            }
        }

        return $total;
    }

    /**
     * Get customer count.
     */
    protected function getCustomerCount(): int
    {
        $customerQuery = new \WP_User_Query([
            'role' => 'customer',
            'count_total' => true,
        ]);

        return $customerQuery->get_total();
    }
}
