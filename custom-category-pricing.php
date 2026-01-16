<?php
/**
 * Plugin Name: Category Pricing Dashboard (Fixed)
 * Description: Overrides WooCommerce prices based on Category, Quantity, and Tier logic.
 * Version: 2.1
 * Author: D Kandekore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ==============================================================================
// 1. DASHBOARD MENU
// ==============================================================================
add_action('admin_menu', 'cpd_add_admin_menu');
function cpd_add_admin_menu() {
    add_menu_page(
        'Category Pricing', 
        'Category Pricing', 
        'manage_options', 
        'cpd-pricing-rules', 
        'cpd_options_page_html', 
        'dashicons-tag', 
        56
    );
}

// ==============================================================================
// 2. REGISTER SETTINGS
// ==============================================================================
add_action('admin_init', 'cpd_settings_init');
function cpd_settings_init() {
    register_setting('cpd_plugin', 'cpd_pricing_rules');
}

// ==============================================================================
// 3. THE DASHBOARD HTML
// ==============================================================================
function cpd_options_page_html() {
    $rules = get_option('cpd_pricing_rules', []);
    $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
    ?>
    <div class="wrap">
        <h1>Category Pricing Manager</h1>
        <form action="options.php" method="post">
            <?php settings_fields('cpd_plugin'); ?>
            
            <div id="cpd-rules-container">
                <?php 
                if (!empty($rules)) {
                    foreach ($rules as $i => $rule) {
                        cpd_render_rule_box($i, $rule, $categories);
                    }
                }
                ?>
            </div>

            <div style="margin-top: 20px;">
                <button type="button" class="button button-primary" id="add-rule-btn">+ Add New Category Rule</button>
            </div>
            
            <br>
            <?php submit_button('Save Pricing Rules'); ?>
        </form>
    </div>

    <div id="template-rule" style="display:none;">
        <?php cpd_render_rule_box('__RULE_INDEX__', null, $categories); ?>
    </div>

    <div id="template-tier" style="display:none;">
        <div class="tier-row" style="background: #f9f9f9; padding: 10px; margin-bottom: 5px; border: 1px solid #ddd; display: flex; gap: 10px; align-items: flex-end;">
            <div>
                <label>Qty Threshold:</label><br>
                <input type="number" name="cpd_pricing_rules[__RULE_INDEX__][tiers][__TIER_INDEX__][qty]" placeholder="e.g. 3" style="width: 80px;" required>
            </div>
            <div>
                <label>Tier Price (£):</label><br>
                <input type="number" step="0.01" name="cpd_pricing_rules[__RULE_INDEX__][tiers][__TIER_INDEX__][price]" placeholder="e.g. 32.00" style="width: 80px;" required>
            </div>
            <div>
                <label>Discount Type:</label><br>
                <select name="cpd_pricing_rules[__RULE_INDEX__][tiers][__TIER_INDEX__][type]">
                    <option value="step">Step (First X items only)</option>
                    <option value="global">Global Override (Apply to ALL)</option>
                </select>
            </div>
            <div>
                <label>If Exceeding Qty:</label><br>
                <select name="cpd_pricing_rules[__RULE_INDEX__][tiers][__TIER_INDEX__][overage]">
                    <option value="base">Charge Remainder at Base Price</option>
                    <option value="tier">Charge Remainder at Tier Price</option>
                </select>
            </div>
            <div>
                <button type="button" class="button" onclick="this.closest('.tier-row').remove()">Remove</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // ADD RULE CLICK
        document.getElementById('add-rule-btn').addEventListener('click', function() {
            var container = document.getElementById('cpd-rules-container');
            var ruleIndex = container.querySelectorAll('.cpd-rule-box').length;
            
            // Get template HTML
            var template = document.getElementById('template-rule').innerHTML;
            
            // Replace placeholder with actual index
            template = template.replace(/__RULE_INDEX__/g, ruleIndex);
            
            // Append to container
            var div = document.createElement('div');
            div.innerHTML = template;
            container.appendChild(div.firstElementChild);
        });
    });

    // ADD TIER CLICK (Must be global function)
    function addTier(btn, ruleIndex) {
        var container = document.getElementById('tiers-container-' + ruleIndex);
        var tierIndex = container.querySelectorAll('.tier-row').length;
        
        // Get template HTML
        var template = document.getElementById('template-tier').innerHTML;
        
        // Replace placeholders
        template = template.replace(/__RULE_INDEX__/g, ruleIndex);
        template = template.replace(/__TIER_INDEX__/g, tierIndex);
        
        // Append
        var div = document.createElement('div');
        div.innerHTML = template;
        container.appendChild(div.firstElementChild);
    }
    </script>
    <?php
}

// HELPER: Render a single rule box
function cpd_render_rule_box($index, $rule = null, $categories = []) {
    $selected_cat = $rule['cat_id'] ?? '';
    $base_price = $rule['base_price'] ?? '';
    $tiers = $rule['tiers'] ?? [];
    ?>
    <div class="cpd-rule-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <h3 style="margin-top:0;">Rule #<span class="rule-number"><?php echo is_numeric($index) ? $index + 1 : '{New}'; ?></span> 
            <button type="button" style="float:right; color: #b32d2e;" class="button-link" onclick="this.closest('.cpd-rule-box').remove()">Delete Rule</button>
        </h3>
        
        <div style="display:flex; gap: 20px; margin-bottom: 20px;">
            <div>
                <label><b>Category:</b></label><br>
                <select name="cpd_pricing_rules[<?php echo $index; ?>][cat_id]" required>
                    <option value="">Select Category...</option>
                    <?php foreach ($categories as $cat) : ?>
                        <option value="<?php echo $cat->term_id; ?>" <?php selected($selected_cat, $cat->term_id); ?>>
                            <?php echo $cat->name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label><b>Base Price Override (£):</b></label><br>
                <input type="number" step="0.01" name="cpd_pricing_rules[<?php echo $index; ?>][base_price]" value="<?php echo esc_attr($base_price); ?>" placeholder="e.g. 35.00" required>
            </div>
        </div>

        <label><b>Discount Tiers:</b></label>
        <div id="tiers-container-<?php echo $index; ?>">
            <?php 
            if (!empty($tiers)) {
                foreach ($tiers as $t_index => $tier) {
                    ?>
                    <div class="tier-row" style="background: #f9f9f9; padding: 10px; margin-bottom: 5px; border: 1px solid #ddd; display: flex; gap: 10px; align-items: flex-end;">
                        <div>
                            <label>Qty Threshold:</label><br>
                            <input type="number" name="cpd_pricing_rules[<?php echo $index; ?>][tiers][<?php echo $t_index; ?>][qty]" value="<?php echo esc_attr($tier['qty']); ?>" style="width: 80px;" required>
                        </div>
                        <div>
                            <label>Tier Price (£):</label><br>
                            <input type="number" step="0.01" name="cpd_pricing_rules[<?php echo $index; ?>][tiers][<?php echo $t_index; ?>][price]" value="<?php echo esc_attr($tier['price']); ?>" style="width: 80px;" required>
                        </div>
                        <div>
                            <label>Discount Type:</label><br>
                            <select name="cpd_pricing_rules[<?php echo $index; ?>][tiers][<?php echo $t_index; ?>][type]">
                                <option value="step" <?php selected($tier['type'], 'step'); ?>>Step (First X items only)</option>
                                <option value="global" <?php selected($tier['type'], 'global'); ?>>Global Override (Apply to ALL)</option>
                            </select>
                        </div>
                        <div>
                            <label>If Exceeding Qty:</label><br>
                            <select name="cpd_pricing_rules[<?php echo $index; ?>][tiers][<?php echo $t_index; ?>][overage]">
                                <option value="base" <?php selected($tier['overage'], 'base'); ?>>Charge Remainder at Base Price</option>
                                <option value="tier" <?php selected($tier['overage'], 'tier'); ?>>Charge Remainder at Tier Price</option>
                            </select>
                        </div>
                        <div>
                            <button type="button" class="button" onclick="this.closest('.tier-row').remove()">Remove</button>
                        </div>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
        <button type="button" class="button-secondary" onclick="addTier(this, '<?php echo $index; ?>')">+ Add Tier</button>
    </div>
    <?php
}

/**
 * 4. THE UPDATED MATH ENGINE
 * Instead of set_price, we calculate the total "Saving" and apply it as a discount.
 */

// 1. Remove the old price override hook
// add_action('woocommerce_before_calculate_totals', 'cpd_apply_pricing_rules', 10, 1); 

// 2. Add the Discount as a Cart Fee (This is much more accurate)
add_action('woocommerce_cart_calculate_fees', 'cpd_apply_category_discounts', 10, 1);

function cpd_apply_category_discounts($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    $rules = get_option('cpd_pricing_rules', []);
    if (empty($rules)) return;

    $cat_totals = [];
    $cat_base_prices = [];

    // First Pass: Get totals per category
    foreach ($cart->get_cart() as $cart_item) {
        $product_id = $cart_item['product_id'];
        $terms = get_the_terms($product_id, 'product_cat');
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                if (!isset($cat_totals[$term->term_id])) $cat_totals[$term->term_id] = 0;
                $cat_totals[$term->term_id] += $cart_item['quantity'];
            }
        }
    }

    $total_discount = 0;

    // Second Pass: Calculate how much should be "saved" per category
    foreach ($rules as $rule) {
        $cat_id = $rule['cat_id'];
        if (isset($cat_totals[$cat_id]) && $cat_totals[$cat_id] > 0) {
            
            $qty = $cat_totals[$cat_id];
            $base_price = floatval($rule['base_price']);
            
            // Calculate what the cost SHOULD be
            $actual_target_price = cpd_get_target_total_cost($qty, $rule);
            
            // Calculate what the cost IS currently (Standard Base Price * Qty)
            $current_standard_cost = $qty * $base_price;
            
            // The difference is our discount
            $discount_amount = $current_standard_cost - $actual_target_price;
            
            if ($discount_amount > 0) {
                $total_discount += $discount_amount;
            }
        }
    }

    // Apply the discount to the cart
    if ($total_discount > 0) {
        $cart->add_fee(__('Category Bulk Discount', 'cpd'), -$total_discount);
    }
}

// Helper to calculate the EXACT total cost for the category volume
function cpd_get_target_total_cost($qty, $rule) {
    $base_price = floatval($rule['base_price']);
    $tiers = isset($rule['tiers']) ? $rule['tiers'] : [];
    
    if (empty($tiers)) return $qty * $base_price;

    // Sort tiers: Highest Quantity first
    usort($tiers, function($a, $b) {
        return (int)$b['qty'] - (int)$a['qty'];
    });

    $applied_tier = null;
    foreach ($tiers as $tier) {
        if ($qty >= (int)$tier['qty']) {
            $applied_tier = $tier;
            break;
        }
    }

    if (!$applied_tier) return $qty * $base_price;

    $tier_price = floatval($applied_tier['price']);
    $tier_qty = intval($applied_tier['qty']);

    // 1. GLOBAL OVERRIDE
    if ($applied_tier['type'] === 'global') {
        return $qty * $tier_price;
    }

    // 2. STEP PRICING
    if ($applied_tier['type'] === 'step') {
        $remainder_qty = $qty - $tier_qty;
        $cost_first_batch = $tier_qty * $tier_price;
        
        $remainder_price = ($applied_tier['overage'] === 'base') ? $base_price : $tier_price;
        $cost_remainder = $remainder_qty * $remainder_price;

        return $cost_first_batch + $cost_remainder;
    }

    return $qty * $base_price;
}