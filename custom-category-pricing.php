<?php
/**
 * Plugin Name: Advanced Category Tiered Pricing
 * Description: Applies complex step-pricing and volume overrides based on categories.
 * Version: 1.0
 * Author: D Kandekore
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// ==============================================================================
// 1. CONFIGURATION DASHBOARD (EDIT YOUR RULES HERE)
// ==============================================================================
function get_pricing_rules() {
    return [
        // RULE 1: Example from your request
        [
            'category_id' => 15,       // Replace with your Category ID
            'base_price'  => 35.00,    // Fallback price if needed
            'tiers' => [
                // HIGHEST PRIORITY FIRST
                
                // Tier 2: 10 Items -> GLOBAL OVERRIDE (All items discounted)
                [
                    'qty'           => 10,
                    'price'         => 30.00,
                    'type'          => 'global_override', // 'global_override' or 'step'
                    'overage_mode'  => 'tier',            // Irrelevant for global, but good practice
                ],
                
                // Tier 1: 3 Items -> STEP PRICING (First 3 cheap, rest full price)
                [
                    'qty'           => 3,
                    'price'         => 32.00,
                    'type'          => 'step',            // Step means only the first X items get this price
                    'overage_mode'  => 'base',            // 'base' = charge remainder at £35. 'tier' = charge remainder at £32
                ]
            ]
        ],
        
        // RULE 2: Copy paste this block to add more categories...
    ];
}

// ==============================================================================
// 2. THE PRICING ENGINE
// ==============================================================================

add_action( 'woocommerce_before_calculate_totals', 'apply_complex_category_rules', 10, 1 );

function apply_complex_category_rules( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
    if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) return;

    // 1. Group Cart Quantities by Category
    $cat_totals = [];
    foreach ( $cart->get_cart() as $cart_item ) {
        $product_id = $cart_item['product_id'];
        // Get all categories for this product
        $terms = get_the_terms( $product_id, 'product_cat' );
        
        if ( $terms && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                if ( ! isset( $cat_totals[ $term->term_id ] ) ) {
                    $cat_totals[ $term->term_id ] = 0;
                }
                $cat_totals[ $term->term_id ] += $cart_item['quantity'];
            }
        }
    }

    // 2. Load Rules
    $rules = get_pricing_rules();

    // 3. Loop through cart to apply prices
    foreach ( $cart->get_cart() as $cart_item ) {
        $product = $cart_item['data'];
        $product_id = $cart_item['product_id'];
        $terms = get_the_terms( $product_id, 'product_cat' );
        
        $matched_rule = null;
        $total_category_qty = 0;

        // Find if this product belongs to a category with a rule
        if ( $terms && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                foreach ( $rules as $rule ) {
                    if ( $rule['category_id'] == $term->term_id ) {
                        $matched_rule = $rule;
                        $total_category_qty = $cat_totals[ $term->term_id ];
                        break 2; // Stop looking once a rule is found
                    }
                }
            }
        }

        if ( $matched_rule ) {
            // CALCULATE THE CUSTOM PRICE
            $new_unit_price = calculate_dynamic_price( $total_category_qty, $matched_rule );
            
            // APPLY TO CART
            // We set the price for this specific item. 
            // Note: If multiple different products share the category, they all get this unit price.
            $cart_item['data']->set_price( $new_unit_price );
        }
    }
}

// ==============================================================================
// 3. THE MATHEMATICAL FORMULA
// ==============================================================================

function calculate_dynamic_price( $qty, $rule ) {
    $base_price = $rule['base_price'];
    $applied_tier = null;

    // Sort tiers by Quantity Descending (Highest first)
    usort( $rule['tiers'], function($a, $b) {
        return $b['qty'] - $a['qty'];
    });

    // Find the highest tier met
    foreach ( $rule['tiers'] as $tier ) {
        if ( $qty >= $tier['qty'] ) {
            $applied_tier = $tier;
            break;
        }
    }

    // If no tier met, return base price
    if ( ! $applied_tier ) return $base_price;

    // SCENARIO A: Global Override (e.g., 10+ items, everything is £30)
    if ( $applied_tier['type'] === 'global_override' ) {
        return $applied_tier['price'];
    }

    // SCENARIO B: Step Pricing (e.g., First 3 @ £32, Remainder @ Base)
    if ( $applied_tier['type'] === 'step' ) {
        $tier_qty = $applied_tier['qty'];
        $tier_price = $applied_tier['price'];
        
        $remainder_qty = $qty - $tier_qty;
        
        // Cost of the first Tier items
        $tier_cost = $tier_qty * $tier_price;
        
        // Cost of the remaining items
        $remainder_cost = 0;
        if ( $applied_tier['overage_mode'] === 'base' ) {
            $remainder_cost = $remainder_qty * $base_price;
        } else {
            $remainder_cost = $remainder_qty * $tier_price;
        }

        $total_cost = $tier_cost + $remainder_cost;
        
        // Return weighted average unit price
        return $total_cost / $qty;
    }

    return $base_price;
}