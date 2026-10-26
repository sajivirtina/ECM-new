<?php
/**
 * ACF fields for the Plans & Pricing page template.
 */

defined( 'ABSPATH' ) || exit;

// The Plans & Pricing template is forced onto the WooCommerce Shop page by
// the is_shop() check in the template_include filter (functions.php), not
// by that page's own _wp_page_template meta — WordPress resets that meta
// back to "default" on every save, because template-plans-pricing.php isn't
// a template its own Template dropdown knows about. Matching on the
// page_template meta alone means this field group silently disappears from
// the editor the next time anyone saves the page. Also match the actual
// Shop page by ID — a stable WooCommerce option, untouched by saving this
// post — so the fields stay visible either way.
$ecm_plans_location = [
    [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'template-plans-pricing.php' ] ],
];
if ( function_exists( 'wc_get_page_id' ) ) {
    $ecm_shop_page_id = wc_get_page_id( 'shop' );
    if ( $ecm_shop_page_id > 0 ) {
        $ecm_plans_location[] = [ [ 'param' => 'page', 'operator' => '==', 'value' => (string) $ecm_shop_page_id ] ];
    }
}

acf_add_local_field_group( [
    'key'      => 'group_ecm_plans',
    'title'    => 'Plans & Pricing Content',
    'location' => $ecm_plans_location,
    'menu_order'      => 0,
    'position'        => 'normal',
    'label_placement' => 'top',
    'active'          => true,
    'fields'          => [

        [ 'key' => 'field_ecm_pl_tab_hero', 'label' => 'Hero', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_pl_hero_eyebrow', 'label' => 'Eyebrow', 'name' => 'pl_hero_eyebrow', 'type' => 'text', 'default_value' => 'For Care Providers' ],
        [ 'key' => 'field_ecm_pl_hero_title', 'label' => 'Title (use <em>)', 'name' => 'pl_hero_title', 'type' => 'text', 'default_value' => 'Elder Care Directory — <em>Plans, Benefits &amp; Pricing</em>' ],
        [ 'key' => 'field_ecm_pl_hero_sub', 'label' => 'Subtitle', 'name' => 'pl_hero_sub', 'type' => 'textarea', 'rows' => 3, 'default_value' => "List your practice on ElderCareMatters.com, serving families since 2002. Pick the plan that fits how fast you want to reach families." ],

        [ 'key' => 'field_ecm_pl_tab_cards', 'label' => 'Plan Cards', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_pl_cards_note', 'label' => 'Note', 'name' => 'pl_cards_note', 'type' => 'message', 'message' => 'Add up to 4 plan cards. The 2nd/3rd cards are typically marked Popular / Featured.' ],
        [
            'key' => 'field_ecm_pl_cards', 'label' => 'Plans', 'name' => 'pl_cards',
            'type' => 'repeater', 'layout' => 'block', 'min' => 0, 'max' => 4,
            'sub_fields' => [
                [ 'key' => 'field_ecm_pl_card_name', 'label' => 'Plan Name', 'name' => 'name', 'type' => 'text' ],
                [ 'key' => 'field_ecm_pl_card_tagline', 'label' => 'Tagline', 'name' => 'tagline', 'type' => 'textarea', 'rows' => 2 ],
                [
                    'key' => 'field_ecm_pl_card_style', 'label' => 'Card Style', 'name' => 'style', 'type' => 'select',
                    'choices' => [ 'basic' => 'Basic', 'standard' => 'Standard', 'popular' => 'Popular (highlighted)', 'featured' => 'Featured', 'free' => 'Free' ],
                    'default_value' => 'basic',
                ],
                [ 'key' => 'field_ecm_pl_card_ribbon', 'label' => 'Ribbon Text (optional, e.g. "Most Popular")', 'name' => 'ribbon', 'type' => 'text' ],
                [ 'key' => 'field_ecm_pl_card_price', 'label' => 'Price (number only, e.g. 79)', 'name' => 'price', 'type' => 'text' ],
                [ 'key' => 'field_ecm_pl_card_price_unit', 'label' => 'Price Unit', 'name' => 'price_unit', 'type' => 'text', 'default_value' => '/ month' ],
                [
                    'key' => 'field_ecm_pl_card_features', 'label' => 'Features', 'name' => 'features',
                    'type' => 'repeater', 'layout' => 'table', 'min' => 0, 'max' => 12,
                    'sub_fields' => [
                        [ 'key' => 'field_ecm_pl_feat_text', 'label' => 'Feature', 'name' => 'text', 'type' => 'text' ],
                        [ 'key' => 'field_ecm_pl_feat_strong', 'label' => 'Emphasize?', 'name' => 'strong', 'type' => 'true_false', 'ui' => 1 ],
                    ],
                ],
                [ 'key' => 'field_ecm_pl_card_btn_text', 'label' => 'Button Text', 'name' => 'btn_text', 'type' => 'text', 'default_value' => 'Get Started →' ],
                [ 'key' => 'field_ecm_pl_card_btn_url', 'label' => 'Button URL', 'name' => 'btn_url', 'type' => 'url' ],
            ],
        ],
        [ 'key' => 'field_ecm_pl_cards_footnote', 'label' => 'Footnote Below Cards', 'name' => 'pl_cards_footnote', 'type' => 'text', 'default_value' => '* All plans allow additional blogs, conditions apply.' ],

        [ 'key' => 'field_ecm_pl_tab_table', 'label' => 'Comparison Table', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_pl_table_title', 'label' => 'Title (use <em>)', 'name' => 'pl_table_title', 'type' => 'text', 'default_value' => 'Compare <em>Every Feature</em>' ],
        [ 'key' => 'field_ecm_pl_table_sub', 'label' => 'Subtitle', 'name' => 'pl_table_sub', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'A closer look at what each plan includes, side by side.' ],
        [
            'key' => 'field_ecm_pl_table_rows', 'label' => 'Rows', 'name' => 'pl_table_rows',
            'type' => 'repeater', 'layout' => 'table', 'min' => 0,
            'sub_fields' => [
                [ 'key' => 'field_ecm_pl_row_is_category', 'label' => 'This row is a category divider', 'name' => 'is_category', 'type' => 'true_false', 'ui' => 1 ],
                [ 'key' => 'field_ecm_pl_row_label', 'label' => 'Feature / Category Label', 'name' => 'label', 'type' => 'text' ],
                [ 'key' => 'field_ecm_pl_row_val1', 'label' => 'Plan 1 Value', 'name' => 'val1', 'type' => 'text', 'instructions' => 'Use "check", "dash", or any text/number.' ],
                [ 'key' => 'field_ecm_pl_row_val2', 'label' => 'Plan 2 Value', 'name' => 'val2', 'type' => 'text' ],
                [ 'key' => 'field_ecm_pl_row_val3', 'label' => 'Plan 3 Value', 'name' => 'val3', 'type' => 'text' ],
                [ 'key' => 'field_ecm_pl_row_val4', 'label' => 'Plan 4 Value', 'name' => 'val4', 'type' => 'text' ],
            ],
        ],

        [ 'key' => 'field_ecm_pl_tab_faq', 'label' => 'FAQ', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_pl_faq_tag', 'label' => 'Eyebrow', 'name' => 'pl_faq_tag', 'type' => 'text', 'default_value' => 'Common Questions' ],
        [ 'key' => 'field_ecm_pl_faq_title', 'label' => 'Title', 'name' => 'pl_faq_title', 'type' => 'text', 'default_value' => 'Plans &amp; Pricing FAQ' ],
        [ 'key' => 'field_ecm_pl_faq_sub', 'label' => 'Subtitle', 'name' => 'pl_faq_sub', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'Everything providers ask us before signing up.' ],
        [
            'key' => 'field_ecm_pl_faq_items', 'label' => 'FAQ Items', 'name' => 'pl_faq_items',
            'type' => 'repeater', 'layout' => 'block', 'min' => 0,
            'sub_fields' => [
                [ 'key' => 'field_ecm_pl_faq_q', 'label' => 'Question', 'name' => 'question', 'type' => 'text' ],
                [ 'key' => 'field_ecm_pl_faq_a', 'label' => 'Answer', 'name' => 'answer', 'type' => 'textarea', 'rows' => 3 ],
            ],
        ],

        [ 'key' => 'field_ecm_pl_tab_cta', 'label' => 'Final CTA', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_pl_cta_title', 'label' => 'Title (use <em>)', 'name' => 'pl_cta_title', 'type' => 'text', 'default_value' => 'Still deciding which plan is <em>right for you?</em>' ],
        [ 'key' => 'field_ecm_pl_cta_sub', 'label' => 'Subtitle', 'name' => 'pl_cta_sub', 'type' => 'text', 'default_value' => "Our team can walk you through the options in under 5 minutes." ],
        [ 'key' => 'field_ecm_pl_cta_btn1_text', 'label' => 'Primary Button Text', 'name' => 'pl_cta_btn1_text', 'type' => 'text', 'default_value' => '📋 Talk to Our Team →' ],
        [ 'key' => 'field_ecm_pl_cta_btn1_url', 'label' => 'Primary Button URL', 'name' => 'pl_cta_btn1_url', 'type' => 'url' ],
        [ 'key' => 'field_ecm_pl_cta_btn2_text', 'label' => 'Secondary Button Text', 'name' => 'pl_cta_btn2_text', 'type' => 'text', 'default_value' => '💬 Chat with us' ],
        [ 'key' => 'field_ecm_pl_cta_btn2_url', 'label' => 'Secondary Button URL', 'name' => 'pl_cta_btn2_url', 'type' => 'url' ],
    ],
] );
