<?php
/**
 * Plugin Name: Category Pricing Dashboard
 * Description: Overrides WooCommerce prices based on Category, Quantity, and Tier logic via a custom Dashboard.
 * Version: 2.0
 * Author: Gemini
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ==============================================================================
// 1. CREATE THE DASHBOARD MENU
// ==============================================================================
add_action('admin_menu', 'cpd_add_admin_menu');
function cpd_add_admin_menu() {
    add_menu_page(
        'Category Pricing Rules', 
        'Category Pricing', 
        'manage_options', 
        'cpd-pricing-rules', 
        'cpd_options_page_html', 
        'dashicons-tag', 
        56
    );
}

// ==============================================================================
// 2. REGISTER SETTINGS & SAVE LOGIC
// ==============================================================================
add_action('admin_init', 'cpd_settings_init');
function cpd_settings_init() {
    register_setting('cpd_plugin', 'cpd_pricing_rules');
}

// ==============================================================================
// 3. THE DASHBOARD HTML (UI)
// ==============================================================================
function cpd_options_page_html() {
    // Get existing rules from database
    $rules = get_option('cpd_pricing_rules', []);
    // Get all product categories
    $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
    ?>
    <div class="wrap">
        <h1>Category Pricing Manager</h1>
        <p>Define tiered pricing logic per category. Rules are processed from top to bottom.</p>
        
        <form action="options.php" method="post">
            <?php settings_fields('cpd_plugin'); ?>
            
            <div id="cpd-rules-container">
                <?php 
                // If we have saved rules, loop through them. Otherwise show one blank form.
                if (!empty($rules)) {
                    foreach ($rules as $index => $rule) {
                        cpd_render_rule_box($index, $rule, $categories);
                    }
                } else {
                    cpd_render_rule_box(0, null, $categories);
                }
                ?>
            </div>

            <button type="button" class="button" onclick="addRule()">+ Add Another Category Rule</button>
            <br><br>
            <?php submit_button('Save Pricing Rules'); ?>
        </form>
    </div>

    <script>
        function addRule() {
            var container = document.getElementById('cpd-rules-container');
            var count = container.children.length;
            var template = `<?php cpd_render_rule_box('INDEX', null, $categories, true); ?>`;
            var newRow = template.replace(/INDEX/g, count);
            var div = document.createElement('div');
            div.innerHTML = newRow;
            container.appendChild(div.firstElementChild);
        }

        function addTier(ruleIndex) {
            var container = document.getElementById('tiers-container-' + ruleIndex);
            var count = container.querySelectorAll('.tier-row').length;
            
            var html = `
                <div class="tier-row" style="background: #f9f9f9; padding: 10px; margin-bottom: 5px; border: 1px solid #ddd; display: flex; gap: 10px; align-items: flex-end;">
                    <div>
                        <label>Qty Threshold:</label><br>
                        <input type="number" name="cpd_pricing_rules[${ruleIndex}][tiers][${count}][qty]" value="" placeholder="e.g. 3" style="width: 80px;" required>
                    </div>
                    <div>
                        <label>Tier Price (£):</label><br>
                        <input type="number" step="0.01" name="cpd_pricing_rules[${ruleIndex}][tiers][${count}][price]" value="" placeholder="e.g. 32.00" style="width: 80px;" required>
                    </div>
                    <div>
                        <label>Discount Type:</label><br>
                        <select name="cpd_pricing_rules[${ruleIndex}][tiers][${count}][type]">
                            <option value="step">Step (First X items only)</option>
                            <option value="global">Global Override (Apply to ALL)</option>
                        </select>
                    </div>
                    <div>
                        <label>If Exceeding Qty, Charge Extra At:</label><br>
                        <select name="cpd_pricing_rules[${ruleIndex}][tiers][${count}][overage]">
                            <option value="base">Original Base Price</option>
                            <option value="tier">This Tier Price</option>
                        </select>
                    </div>
                    <div>
                        <button type="button" class="button" onclick="this.parentElement.parentElement.remove()">Remove</button>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }
    </script>
    <?php
}

// HELPER: Render a single rule box
function cpd_render_rule_box($index, $rule = null, $categories = [], $is_js_template = false) {
    // Default values
    $selected_cat = $rule['cat_id'] ?? '';
    $base_price = $rule['base_price'] ?? '';
    $tiers = $rule['tiers'] ?? [];
    
    // For JS template, we need to return string instead of echo
    if ($is_js_template) ob_start();
    ?>
    <div class="cpd-rule-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <h3 style="margin-top:0;">Rule #<?php echo $index + 1; ?> 
            <button type="button" style="float:right; color: #b32d2e;" class="button-link" onclick="this.parentElement.parentElement.remove()">Delete Rule</button>
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
                            <label>If Exceeding Qty, Charge Extra At:</label><br>
                            <select name="cpd_pricing_rules[<?php echo $index; ?>][tiers][<?php echo $t_index; ?>][overage]">
                                <option value="base" <?php selected($tier['overage'], 'base'); ?>>Original Base Price</option>
                                <option value="tier" <?php selected($tier['overage'], 'tier'); ?>>This Tier Price</option>
                            </select>
                        </div>
                        <div>
                            <button type="button" class="button" onclick="this.parentElement.parentElement.remove()">Remove</button>
                        </div>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
        <button type="button" class="button-secondary" onclick="addTier(<?php echo $index; ?>)">+ Add Tier</button>
    </div>
    <?php
    if ($is_js_template) return ob_get_clean();
}

// ==============================================================================
// 4. THE CALCULATION ENGINE (MATH LOGIC)
// ==============================================================================
add_action('woocommerce_before_calculate_totals', 'cpd_apply_pricing_rules', 10, 1);

function cpd_apply_pricing_rules($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (did_action('woocommerce_before_calculate_totals') >= 2) return;

    // 1. Load Rules from DB
    $rules = get_option('cpd_pricing_rules', []);
    if (empty($rules)) return;

    // 2. Group Cart Quantities by Category
    $cat_totals = [];
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

    // 3. Apply Rules
    foreach ($cart->get_cart() as $cart_item) {
        $product_id = $cart_item['product_id'];
        $terms = get_the_terms($product_id, 'product_cat');
        
        $matched_rule = null;
        $total_category_qty = 0;

        // Find applicable rule for this product's category
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                foreach ($rules as $rule) {
                    if ($rule['cat_id'] == $term->term_id) {
                        $matched_rule = $rule;
                        $total_category_qty = $cat_totals[$term->term_id];
                        break 2;
                    }
                }
            }
        }

        if ($matched_rule) {
            $price = cpd_calculate_price($total_category_qty, $matched_rule);
            $cart_item['data']->set_price($price);
        }
    }
}

// ==============================================================================
// 5. THE MATH FUNCTION
// ==============================================================================
function cpd_calculate_price($qty, $rule) {
    $base_price = floatval($rule['base_price']);
    $tiers = isset($rule['tiers']) ? $rule['tiers'] : [];
    
    if (empty($tiers)) return $base_price;

    // Sort tiers by Qty Descending (Highest first)
    usort($tiers, function($a, $b) {
        return $b['qty'] - $a['qty'];
    });

    $applied_tier = null;

    // Find highest threshold met
    foreach ($tiers as $tier) {
        if ($qty >= $tier['qty']) {
            $applied_tier = $tier;
            break;
        }
    }

    // If no tier met, return base price
    if (!$applied_tier) return $base_price;

    $tier_price = floatval($applied_tier['price']);
    $tier_qty = intval($applied_tier['qty']);

    // LOGIC A: Global Override (e.g. 10 items @ £30 flat)
    if ($applied_tier['type'] === 'global') {
        return $tier_price;
    }

    // LOGIC B: Step Pricing (First 3 @ £32, rest @ Base)
    if ($applied_tier['type'] === 'step') {
        // Items within the tier get tier_price
        // Remainder gets either base_price OR tier_price depending on setting
        
        $remainder_qty = $qty - $tier_qty;
        
        $cost_first_batch = $tier_qty * $tier_price;
        $cost_remainder = 0;

        if ($applied_tier['overage'] === 'base') {
            $cost_remainder = $remainder_qty * $base_price;
        } else {
            $cost_remainder = $remainder_qty * $tier_price;
        }

        $total_cost = $cost_first_batch + $cost_remainder;
        
        // Return weighted average unit price
        return $total_cost / $qty;
    }

    return $base_price;
}