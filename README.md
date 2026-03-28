# Category Pricing Dashboard (Refined)

A precision WooCommerce bulk pricing plugin designed to apply category-level discounts that update the header cart and mini-cart totals without altering individual product page prices. This ensures a clean "bulk discount at checkout" experience while keeping the shop interface consistent.

## Features

* **Category-Based Rules**: Create specific pricing rules for entire product categories rather than managing products individually.
* **Tiered Pricing Engine**: Supports multiple quantity tiers with two distinct calculation methods:
    * **Global**: Applies the tier price to all items in the category once the threshold is met.
    * **Step**: Applies the tier price to the threshold amount and allows configurable pricing for "overage" items.
* **Overage Control**: For step-based pricing, choose whether items exceeding the tier quantity are charged at the **Base Price** or the **Tier Price**.
* **Visual Protection**: Dynamically updates the header cart total, BeTheme cart fragments, and mini-carts via AJAX and scoped JavaScript.
* **Selective Filtering**: Specifically protects single product pages and shop loops from showing discounted prices prematurely.
* **Fee-Based Logic**: Uses the WooCommerce `add_fee` method to apply discounts, ensuring accurate calculations and clear line items in the cart.

## Installation

1.  Upload the `custom-category-pricing` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Navigate to the **Category Pricing** menu in your WordPress dashboard.

## How to Configure

1.  **Add a Rule**: Click "+ Add New Category Rule" in the dashboard.
2.  **Select Category**: Choose the product category you wish to target.
3.  **Set Base Price**: Define the standard price override used for initial calculations.
4.  **Add Tiers**:
    * **Qty**: The minimum quantity required to trigger the tier.
    * **Price**: The discounted price per unit for that tier.
    * **Type**: Choose between "Step" (progressive) or "Global" (flat discount).
    * **Overage**: Decide if additional items use the "Base Price" or the "Tier Price".
5.  **Save**: Click "Save Pricing Rules" to apply changes.

## Technical Details

* **Version**: 2.8
* **Author**: D Kandekore
* **Minimum Requirements**: WordPress with WooCommerce installed.
* **Key Hooks**: 
    * `woocommerce_cart_calculate_fees`
    * `woocommerce_add_to_cart_fragments`
    * `woocommerce_get_cart_subtotal`
