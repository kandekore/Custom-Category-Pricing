<?php
/**
 * Plugin Name: Category Pricing Dashboard (Refined)
 * Description: Precision category discounts. Updates header cart total without affecting single product page prices.
 * Version: 2.8
 * Author: D Kandekore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ==============================================================================
// 1. DASHBOARD MENU & SETTINGS
// ==============================================================================
add_action('admin_menu', 'cpd_add_admin_menu');
function cpd_add_admin_menu() {
    add_menu_page('Category Pricing', 'Category Pricing', 'manage_options', 'cpd-pricing-rules', 'cpd_options_page_html', 'dashicons-tag', 56);
}

add_action('admin_init', 'cpd_settings_init');
function cpd_settings_init() {
    register_setting('cpd_plugin', 'cpd_pricing_rules');
}

// ==============================================================================
// 2. THE MATH ENGINE (Cart Fees for Accuracy)
// ==============================================================================
add_action('woocommerce_cart_calculate_fees', 'cpd_apply_category_discounts', 10, 1);
function cpd_apply_category_discounts($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    $rules = get_option('cpd_pricing_rules', []);
    if (empty($rules)) return;

    $cat_totals = [];
    foreach ($cart->get_cart() as $cart_item) {
        $terms = get_the_terms($cart_item['product_id'], 'product_cat');
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $cat_totals[$term->term_id] = ($cat_totals[$term->term_id] ?? 0) + $cart_item['quantity'];
            }
        }
    }

    $total_discount = 0;
    foreach ($rules as $rule) {
        $cat_id = $rule['cat_id'];
        if (isset($cat_totals[$cat_id]) && $cat_totals[$cat_id] > 0) {
            $qty = $cat_totals[$cat_id];
            $target = cpd_get_target_total_cost($qty, $rule);
            $current = $qty * floatval($rule['base_price']);
            $diff = $current - $target;
            if ($diff > 0.001) $total_discount += $diff;
        }
    }
    if ($total_discount > 0) $cart->add_fee(__('Category Bulk Discount', 'cpd'), -$total_discount);
}

function cpd_get_target_total_cost($qty, $rule) {
    $base_price = floatval($rule['base_price']);
    $tiers = $rule['tiers'] ?? [];
    if (empty($tiers)) return $qty * $base_price;
    usort($tiers, function($a, $b) { return (int)$b['qty'] - (int)$a['qty']; });
    $applied = null;
    foreach ($tiers as $tier) { if ($qty >= (int)$tier['qty']) { $applied = $tier; break; } }
    if (!$applied) return $qty * $base_price;
    $t_price = floatval($applied['price']);
    if ($applied['type'] === 'global') return $qty * $t_price;
    $rem_qty = $qty - (int)$applied['qty'];
    $rem_price = ($applied['overage'] === 'base') ? $base_price : $t_price;
    return ((int)$applied['qty'] * $t_price) + ($rem_qty * $rem_price);
}

// ==============================================================================
// 3. DISPLAY PROTECTION (Header vs. Product Page)
// ==============================================================================

/**
 * 1. SURGICAL AJAX REFRESH
 * This only updates the specific price tags in the header/mini-cart.
 */
add_filter( 'woocommerce_add_to_cart_fragments', 'cpd_fix_mini_cart_fragments', 1000 );
function cpd_fix_mini_cart_fragments( $fragments ) {
    if (!WC()->cart) return $fragments;

    $total = WC()->cart->get_total();
    
    // We target common BeTheme and WooCommerce header classes.
    // Note: We do NOT target generic 'span.amount' here to protect the page content.
    $fragments['.header-cart-total'] = '<span class="header-cart-total">' . $total . '</span>';
    $fragments['.mfn-cart-totals .amount'] = '<span class="amount">' . $total . '</span>';
    $fragments['.top-bar-right .amount'] = '<span class="amount">' . $total . '</span>';
    
    return $fragments;
}

/**
 * 2. SUBTOTAL OVERRIDE (Header Only)
 * This affects the mini-cart subtotal string but protects the product page.
 */
add_filter( 'woocommerce_get_cart_subtotal', 'cpd_force_subtotal_in_header', 999, 4 );
function cpd_force_subtotal_in_header( $cart_subtotal, $compound, $type, $cart ) {
    
    // GUARD: If we are on a Single Product page or Shop Loop, return original price.
    if ( is_product() || is_shop() || is_product_category() || is_cart() || is_checkout() ) {
        return $cart_subtotal;
    }

    // For the Header/Mini-cart, show the Final Total (including the bulk fee/discount)
    return wc_price( $cart->get_total( 'edit' ) );
}

/**
 * 3. JAVASCRIPT SCOPING
 * A targeted script to ensure the header price matches the total without leaking.
 */
add_action('wp_footer', 'cpd_targeted_js_lock');
function cpd_targeted_js_lock() {
    if (is_admin() || !WC()->cart) return;
    $total = WC()->cart->get_total();
    ?>
    <script type="text/javascript">
    (function($) {
        var updateHeaderOnly = function() {
            var correctTotal = '<?php echo $total; ?>';
            // Only search for price elements inside the Header or Mini Cart Slideout
            $('#header .amount, .mfn-cart-holder .amount, .header-cart-total').each(function() {
                // Ignore any element that is part of the main product summary
                if ($(this).closest('.summary').length === 0 && $(this).closest('.product').length === 0) {
                    $(this).html(correctTotal);
                }
            });
        };
        $(document).ready(updateHeaderOnly);
        $(document.body).on('added_to_cart removed_from_cart updated_cart_totals', updateHeaderOnly);
    })(jQuery);
    </script>
    <?php
}

// ==============================================================================
// 4. THE DASHBOARD HTML (Your existing UI code)
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
                <?php if (!empty($rules)) { foreach ($rules as $i => $rule) { cpd_render_rule_box($i, $rule, $categories); } } ?>
            </div>
            <div style="margin-top: 20px;">
                <button type="button" class="button button-primary" id="add-rule-btn">+ Add New Category Rule</button>
            </div>
            <br>
            <?php submit_button('Save Pricing Rules'); ?>
        </form>
    </div>
    <div id="template-rule" style="display:none;"><?php cpd_render_rule_box('__RULE_INDEX__', null, $categories); ?></div>
    <div id="template-tier" style="display:none;">
        <div class="tier-row" style="background: #f9f9f9; padding: 10px; margin-bottom: 5px; border: 1px solid #ddd; display: flex; gap: 10px; align-items: flex-end;">
            <div><label>Qty:</label><br><input type="number" name="cpd_pricing_rules[__RULE_INDEX__][tiers][__TIER_INDEX__][qty]" style="width: 60px;" required></div>
            <div><label>Price (£):</label><br><input type="number" step="0.01" name="cpd_pricing_rules[__RULE_INDEX__][tiers][__TIER_INDEX__][price]" style="width: 80px;" required></div>
            <div><label>Type:</label><br><select name="cpd_pricing_rules[__RULE_INDEX__][tiers][__TIER_INDEX__][type]"><option value="step">Step</option><option value="global">Global</option></select></div>
            <div><label>Overage:</label><br><select name="cpd_pricing_rules[__RULE_INDEX__][tiers][__TIER_INDEX__][overage]"><option value="base">Base Price</option><option value="tier">Tier Price</option></select></div>
            <div><button type="button" class="button" onclick="this.closest('.tier-row').remove()">X</button></div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('add-rule-btn').addEventListener('click', function() {
            var container = document.getElementById('cpd-rules-container');
            var ruleIndex = container.querySelectorAll('.cpd-rule-box').length;
            var template = document.getElementById('template-rule').innerHTML.replace(/__RULE_INDEX__/g, ruleIndex);
            var div = document.createElement('div'); div.innerHTML = template; container.appendChild(div.firstElementChild);
        });
    });
    function addTier(btn, ruleIndex) {
        var container = document.getElementById('tiers-container-' + ruleIndex);
        var tierIndex = container.querySelectorAll('.tier-row').length;
        var template = document.getElementById('template-tier').innerHTML.replace(/__RULE_INDEX__/g, ruleIndex).replace(/__TIER_INDEX__/g, tierIndex);
        var div = document.createElement('div'); div.innerHTML = template; container.appendChild(div.firstElementChild);
    }
    </script>
    <?php
}

function cpd_render_rule_box($index, $rule = null, $categories = []) {
    $selected_cat = $rule['cat_id'] ?? '';
    $base_price = $rule['base_price'] ?? '';
    $tiers = $rule['tiers'] ?? [];
    ?>
    <div class="cpd-rule-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 20px;">
        <h3>Rule #<?php echo is_numeric($index) ? $index + 1 : '{New}'; ?> <button type="button" style="float:right; color: #b32d2e;" class="button-link" onclick="this.closest('.cpd-rule-box').remove()">Delete</button></h3>
        <div style="display:flex; gap: 20px; margin-bottom: 20px;">
            <div><label>Category:</label><br><select name="cpd_pricing_rules[<?php echo $index; ?>][cat_id]" required><option value="">Select...</option><?php foreach ($categories as $cat) : ?><option value="<?php echo $cat->term_id; ?>" <?php selected($selected_cat, $cat->term_id); ?>><?php echo $cat->name; ?></option><?php endforeach; ?></select></div>
            <div><label>Base Price Override (£):</label><br><input type="number" step="0.01" name="cpd_pricing_rules[<?php echo $index; ?>][base_price]" value="<?php echo esc_attr($base_price); ?>" required></div>
        </div>
        <div id="tiers-container-<?php echo $index; ?>">
            <?php if (!empty($tiers)) { foreach ($tiers as $t_index => $tier) { ?>
                <div class="tier-row" style="background: #f9f9f9; padding: 10px; margin-bottom: 5px; border: 1px solid #ddd; display: flex; gap: 10px; align-items: flex-end;">
                    <div><label>Qty:</label><br><input type="number" name="cpd_pricing_rules[<?php echo $index; ?>][tiers][<?php echo $t_index; ?>][qty]" value="<?php echo esc_attr($tier['qty']); ?>" style="width: 60px;" required></div>
                    <div><label>Price (£):</label><br><input type="number" step="0.01" name="cpd_pricing_rules[<?php echo $index; ?>][tiers][<?php echo $t_index; ?>][price]" value="<?php echo esc_attr($tier['price']); ?>" style="width: 80px;" required></div>
                    <div><label>Type:</label><br><select name="cpd_pricing_rules[<?php echo $index; ?>][tiers][<?php echo $t_index; ?>][type]"><option value="step" <?php selected($tier['type'], 'step'); ?>>Step</option><option value="global" <?php selected($tier['type'], 'global'); ?>>Global</option></select></div>
                    <div><label>Overage:</label><br><select name="cpd_pricing_rules[<?php echo $index; ?>][tiers][<?php echo $t_index; ?>][overage]"><option value="base" <?php selected($tier['overage'], 'base'); ?>>Base Price</option><option value="tier" <?php selected($tier['overage'], 'tier'); ?>>Tier Price</option></select></div>
                    <div><button type="button" class="button" onclick="this.closest('.tier-row').remove()">X</button></div>
                </div>
            <?php } } ?>
        </div>
        <button type="button" class="button-secondary" onclick="addTier(this, '<?php echo $index; ?>')">+ Add Tier</button>
    </div>
    <?php
}