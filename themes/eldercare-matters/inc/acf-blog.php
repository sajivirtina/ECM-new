<?php
/**
 * ACF fields for the Blog / Resources listing page template.
 */

defined( 'ABSPATH' ) || exit;

acf_add_local_field_group( [
    'key'      => 'group_ecm_blog',
    'title'    => 'Blog / Resources Content',
    'location' => [ [ [
        'param'    => 'page_template',
        'operator' => '==',
        'value'    => 'template-blog.php',
    ] ] ],
    'menu_order'      => 0,
    'position'        => 'normal',
    'label_placement' => 'top',
    'active'          => true,
    'fields'          => [

        [ 'key' => 'field_ecm_blog_tab_hero', 'label' => 'Hero', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_blog_eyebrow', 'label' => 'Eyebrow', 'name' => 'blog_eyebrow', 'type' => 'text', 'default_value' => '📚 Elder Care Resources' ],
        [ 'key' => 'field_ecm_blog_title', 'label' => 'Title (use <em>)', 'name' => 'blog_title', 'type' => 'text', 'default_value' => 'Resources &amp; <em>Guides</em>' ],
        [ 'key' => 'field_ecm_blog_sub', 'label' => 'Subtitle', 'name' => 'blog_sub', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'Expert-written articles to help families navigate every stage of elder care, from the first conversation to finding the right provider.' ],
        [
            'key' => 'field_ecm_blog_stats', 'label' => 'Stat Bar', 'name' => 'blog_stats',
            'type' => 'repeater', 'layout' => 'table', 'min' => 0, 'max' => 6,
            'sub_fields' => [
                [ 'key' => 'field_ecm_blog_stat_val', 'label' => 'Value', 'name' => 'value', 'type' => 'text' ],
                [ 'key' => 'field_ecm_blog_stat_label', 'label' => 'Label', 'name' => 'label', 'type' => 'text' ],
            ],
        ],

        [ 'key' => 'field_ecm_blog_tab_filters', 'label' => 'Filter Bar', 'type' => 'tab' ],
        [
            'key' => 'field_ecm_blog_filters', 'label' => 'Filter Chips', 'name' => 'blog_filters',
            'type' => 'repeater', 'layout' => 'table', 'min' => 0, 'max' => 12,
            'sub_fields' => [
                [ 'key' => 'field_ecm_blog_filter_label', 'label' => 'Label', 'name' => 'label', 'type' => 'text' ],
                [ 'key' => 'field_ecm_blog_filter_slug', 'label' => 'Filter Slug', 'name' => 'slug', 'type' => 'text' ],
            ],
        ],

        [ 'key' => 'field_ecm_blog_tab_featured', 'label' => 'Featured Article', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_blog_feat_icon', 'label' => 'Icon/Emoji', 'name' => 'blog_feat_icon', 'type' => 'text', 'default_value' => '🏠' ],
        [ 'key' => 'field_ecm_blog_feat_badge', 'label' => 'Badge', 'name' => 'blog_feat_badge', 'type' => 'text', 'default_value' => "⭐ Editor's Pick" ],
        [ 'key' => 'field_ecm_blog_feat_title', 'label' => 'Title', 'name' => 'blog_feat_title', 'type' => 'text', 'default_value' => 'The Complete Guide to Home Care in 2026' ],
        [ 'key' => 'field_ecm_blog_feat_excerpt', 'label' => 'Excerpt', 'name' => 'blog_feat_excerpt', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'Everything a family needs to know before hiring a home care agency, from vetting credentials to understanding Medicare coverage and what to expect on day one.' ],
        [ 'key' => 'field_ecm_blog_feat_meta', 'label' => 'Meta (date · read · category)', 'name' => 'blog_feat_meta', 'type' => 'text', 'default_value' => 'March 15, 2026 · 8 min read · Home Care' ],
        [ 'key' => 'field_ecm_blog_feat_cta', 'label' => 'CTA Text', 'name' => 'blog_feat_cta', 'type' => 'text', 'default_value' => 'Read the guide →' ],
        [ 'key' => 'field_ecm_blog_feat_link', 'label' => 'Link URL', 'name' => 'blog_feat_link', 'type' => 'text' ],

        [ 'key' => 'field_ecm_blog_tab_grid', 'label' => 'Article Grid', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_blog_grid_sub', 'label' => 'Section Subtitle', 'name' => 'blog_grid_sub', 'type' => 'text', 'default_value' => 'Practical answers on care options, costs, legal planning and daily support.' ],
        [
            'key' => 'field_ecm_blog_cards', 'label' => 'Article Cards', 'name' => 'blog_cards',
            'type' => 'repeater', 'layout' => 'block', 'min' => 0, 'max' => 30,
            'sub_fields' => [
                [ 'key' => 'field_ecm_blog_card_icon', 'label' => 'Icon/Emoji', 'name' => 'icon', 'type' => 'text' ],
                [ 'key' => 'field_ecm_blog_card_bg', 'label' => 'Icon Background (hex)', 'name' => 'icon_bg', 'type' => 'text', 'default_value' => '#e6f4ea' ],
                [ 'key' => 'field_ecm_blog_card_cat', 'label' => 'Category Label', 'name' => 'category', 'type' => 'text' ],
                [
                    'key' => 'field_ecm_blog_card_catclass', 'label' => 'Category Color Class', 'name' => 'cat_class', 'type' => 'select',
                    'choices' => [
                        'blog-cat--home-care' => 'Home Care (green)',
                        'blog-cat--assisted-living' => 'Assisted Living (blue)',
                        'blog-cat--memory-care' => 'Memory Care (orange)',
                        'blog-cat--legal' => 'Legal (purple)',
                        'blog-cat--hospice' => 'Hospice (pink)',
                        'blog-cat--checklist' => 'Checklist (teal)',
                    ],
                    'default_value' => 'blog-cat--home-care',
                ],
                [ 'key' => 'field_ecm_blog_card_title', 'label' => 'Title', 'name' => 'title', 'type' => 'text' ],
                [ 'key' => 'field_ecm_blog_card_excerpt', 'label' => 'Excerpt', 'name' => 'excerpt', 'type' => 'textarea', 'rows' => 2 ],
                [ 'key' => 'field_ecm_blog_card_meta', 'label' => 'Footer Meta (date · read)', 'name' => 'meta', 'type' => 'text' ],
                [ 'key' => 'field_ecm_blog_card_link', 'label' => 'Link URL', 'name' => 'link', 'type' => 'text' ],
            ],
        ],

        [ 'key' => 'field_ecm_blog_tab_cities', 'label' => 'Resources by City', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_blog_cities_sub', 'label' => 'Subtitle', 'name' => 'blog_cities_sub', 'type' => 'text', 'default_value' => 'Find elder care guides, provider directories, and local resources near you.' ],
        [
            'key' => 'field_ecm_blog_cities', 'label' => 'City Links', 'name' => 'blog_cities',
            'type' => 'repeater', 'layout' => 'table', 'min' => 0, 'max' => 50,
            'sub_fields' => [
                [ 'key' => 'field_ecm_blog_city_label', 'label' => 'Label', 'name' => 'label', 'type' => 'text' ],
                [ 'key' => 'field_ecm_blog_city_url', 'label' => 'URL', 'name' => 'url', 'type' => 'text' ],
            ],
        ],

        [ 'key' => 'field_ecm_blog_tab_news', 'label' => 'Newsletter', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_blog_news_title', 'label' => 'Title (use <em>)', 'name' => 'blog_news_title', 'type' => 'text', 'default_value' => 'Get guides delivered to your <em>inbox</em>' ],
        [ 'key' => 'field_ecm_blog_news_sub', 'label' => 'Subtitle', 'name' => 'blog_news_sub', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'Join 12,000+ families receiving our free weekly elder care digest. No spam, unsubscribe any time.' ],
        [ 'key' => 'field_ecm_blog_news_btn', 'label' => 'Button Text', 'name' => 'blog_news_btn', 'type' => 'text', 'default_value' => 'Subscribe Free' ],
        [ 'key' => 'field_ecm_blog_news_fine', 'label' => 'Fine Print', 'name' => 'blog_news_fine', 'type' => 'text', 'default_value' => 'We respect your privacy. Unsubscribe anytime.' ],

        [ 'key' => 'field_ecm_blog_tab_ctaband', 'label' => 'CTA Band', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_blog_cb_title', 'label' => 'Title (use <em>)', 'name' => 'blog_cb_title', 'type' => 'text', 'default_value' => "Ready to find care? <em>We'll match you.</em>" ],
        [ 'key' => 'field_ecm_blog_cb_sub', 'label' => 'Subtitle', 'name' => 'blog_cb_sub', 'type' => 'text', 'default_value' => "Tell us what you need, we'll hand-pick up to 3 verified providers near you." ],
        [ 'key' => 'field_ecm_blog_cb_primary', 'label' => 'Primary Button', 'name' => 'blog_cb_primary', 'type' => 'text', 'default_value' => '📋 Start Your Free Match →' ],
        [ 'key' => 'field_ecm_blog_cb_secondary', 'label' => 'Secondary Button', 'name' => 'blog_cb_secondary', 'type' => 'text', 'default_value' => '💬 Chat with advisor' ],
    ],
] );
