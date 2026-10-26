<?php
/**
 * ACF Local Field Group Definitions — ElderCareMatters Homepage
 *
 * Registers all homepage content fields programmatically.
 * Applies to: the WordPress Front Page (homepage).
 *
 * Admin panel organisation: one group, tab-per-section.
 */

defined( 'ABSPATH' ) || exit;

acf_add_local_field_group( [
    'key'      => 'group_ecm_homepage',
    'title'    => 'Homepage Content',
    'location' => [ [ [
        'param'    => 'page_type',
        'operator' => '==',
        'value'    => 'front_page',
    ] ] ],
    'menu_order'            => 0,
    'position'              => 'normal',
    'style'                 => 'default',
    'label_placement'       => 'top',
    'instruction_placement' => 'label',
    'active'                => true,
    'fields'                => [

        // ═══════════════════════════════════════════════════════════════════
        // TAB: Trust Bar
        // ═══════════════════════════════════════════════════════════════════
        [
            'key'   => 'field_ecm_tab_trust',
            'label' => 'Trust Bar',
            'name'  => '',
            'type'  => 'tab',
        ],
        [
            'key'               => 'field_ecm_trust_bar_text',
            'label'             => 'Trust Bar Text',
            'name'              => 'trust_bar_text',
            'type'              => 'text',
            'instructions'      => 'Top banner strip — short trust statement.',
            'default_value'     => '✦ Choose your own providers · Free for families',
            'placeholder'       => '✦ Choose your own providers · Free for families',
        ],
        [
            'key'           => 'field_ecm_trust_bar_enabled',
            'label'         => 'Show Trust Bar',
            'name'          => 'trust_bar_enabled',
            'type'          => 'true_false',
            'default_value' => 1,
            'ui'            => 1,
        ],

        // ═══════════════════════════════════════════════════════════════════
        // TAB: Navigation
        // ═══════════════════════════════════════════════════════════════════
        [
            'key'   => 'field_ecm_tab_nav',
            'label' => 'Navigation',
            'name'  => '',
            'type'  => 'tab',
        ],
        [
            'key'           => 'field_ecm_nav_logo',
            'label'         => 'Logo',
            'name'          => 'nav_logo',
            'type'          => 'image',
            'instructions'  => 'Upload the site logo. Displayed at 44px height.',
            'return_format' => 'array',
            'preview_size'  => 'thumbnail',
            'library'       => 'all',
        ],
        [
            'key'           => 'field_ecm_nav_logo_alt',
            'label'         => 'Logo Alt Text',
            'name'          => 'nav_logo_alt',
            'type'          => 'text',
            'default_value' => 'ElderCareMatters',
        ],
        [
            'key'          => 'field_ecm_nav_links',
            'label'        => 'Navigation Links',
            'name'         => 'nav_links',
            'type'         => 'repeater',
            'instructions' => 'Add/remove navigation links. "Is CTA Button" makes it styled as a button.',
            'min'          => 1,
            'max'          => 10,
            'layout'       => 'table',
            'sub_fields'   => [
                [
                    'key'           => 'field_ecm_nav_link_label',
                    'label'         => 'Label',
                    'name'          => 'label',
                    'type'          => 'text',
                    'column_width'  => '30',
                ],
                [
                    'key'          => 'field_ecm_nav_link_url',
                    'label'        => 'URL',
                    'name'         => 'url',
                    'type'         => 'text',
                    'column_width' => '40',
                ],
                [
                    'key'           => 'field_ecm_nav_link_is_cta',
                    'label'         => 'CTA Button?',
                    'name'          => 'is_cta',
                    'type'          => 'true_false',
                    'default_value' => 0,
                    'ui'            => 1,
                    'column_width'  => '15',
                ],
                [
                    'key'           => 'field_ecm_nav_link_new_tab',
                    'label'         => 'New Tab?',
                    'name'          => 'new_tab',
                    'type'          => 'true_false',
                    'default_value' => 0,
                    'ui'            => 1,
                    'column_width'  => '15',
                ],
            ],
        ],

        // ═══════════════════════════════════════════════════════════════════
        // TAB: Hero Section
        // ═══════════════════════════════════════════════════════════════════
        [
            'key'   => 'field_ecm_tab_hero',
            'label' => 'Hero Section',
            'name'  => '',
            'type'  => 'tab',
        ],
        // Stamp / badge
        [
            'key'           => 'field_ecm_hero_stamp_arc_top',
            'label'         => 'Stamp — Top Arc Text',
            'name'          => 'hero_stamp_arc_top',
            'type'          => 'text',
            'default_value' => '· ALL 50 STATES ·',
        ],
        [
            'key'           => 'field_ecm_hero_stamp_arc_bottom',
            'label'         => 'Stamp — Bottom Arc Text',
            'name'          => 'hero_stamp_arc_bottom',
            'type'          => 'text',
            'default_value' => '· TRUSTED CARE MATCHING ·',
        ],
        [
            'key'           => 'field_ecm_hero_stamp_year',
            'label'         => 'Stamp — Center Year',
            'name'          => 'hero_stamp_year',
            'type'          => 'text',
            'default_value' => '2002',
        ],
        // Headline
        [
            'key'           => 'field_ecm_hero_title_prefix',
            'label'         => 'Headline — Text Before Location',
            'name'          => 'hero_title_prefix',
            'type'          => 'text',
            'instructions'  => 'E.g. "Find Trusted Elder Care in" — the city name is auto-appended dynamically.',
            'default_value' => 'Find Trusted Elder Care in',
        ],
        [
            'key'           => 'field_ecm_hero_subtitle',
            'label'         => 'Hero Subtitle',
            'name'          => 'hero_subtitle',
            'type'          => 'textarea',
            'rows'          => 3,
            'default_value' => 'Tell us what you need and choose up to 10 verified local providers to hear from. Free, no spam, no obligation.',
        ],
        // Primary CTA
        [
            'key'           => 'field_ecm_hero_cta_primary_icon',
            'label'         => 'Primary CTA — Icon/Emoji',
            'name'          => 'hero_cta_primary_icon',
            'type'          => 'text',
            'default_value' => '📋',
        ],
        [
            'key'           => 'field_ecm_hero_cta_primary_text',
            'label'         => 'Primary CTA — Button Text',
            'name'          => 'hero_cta_primary_text',
            'type'          => 'text',
            'default_value' => 'Start Your Free Match',
        ],
        // Secondary CTA
        [
            'key'           => 'field_ecm_hero_cta_secondary_icon',
            'label'         => 'Secondary CTA — Icon/Emoji',
            'name'          => 'hero_cta_secondary_icon',
            'type'          => 'text',
            'default_value' => '💬',
        ],
        [
            'key'           => 'field_ecm_hero_cta_secondary_text',
            'label'         => 'Secondary CTA — Text',
            'name'          => 'hero_cta_secondary_text',
            'type'          => 'text',
            'default_value' => 'Prefer to chat? Talk to Carrie',
        ],
        // Trust badges row
        [
            'key'          => 'field_ecm_hero_badges',
            'label'        => 'Hero Trust Badges',
            'name'         => 'hero_badges',
            'type'         => 'repeater',
            'instructions' => 'Small trust indicators below the CTA buttons.',
            'min'          => 1,
            'max'          => 6,
            'layout'       => 'table',
            'sub_fields'   => [
                [
                    'key'   => 'field_ecm_hero_badge_text',
                    'label' => 'Badge Text',
                    'name'  => 'text',
                    'type'  => 'text',
                ],
            ],
        ],
        // Quick category shortcuts
        [
            'key'          => 'field_ecm_hero_quick_cats_label',
            'label'        => 'Quick Start — Label',
            'name'         => 'hero_quick_cats_label',
            'type'         => 'text',
            'default_value'=> 'Quick start:',
        ],
        [
            'key'          => 'field_ecm_hero_quick_cats',
            'label'        => 'Quick Start Categories',
            'name'         => 'hero_quick_cats',
            'type'         => 'repeater',
            'instructions' => 'Category shortcut chips shown below the hero CTA.',
            'min'          => 1,
            'max'          => 8,
            'layout'       => 'table',
            'sub_fields'   => [
                [
                    'key'   => 'field_ecm_hero_qcat_emoji',
                    'label' => 'Emoji/Icon',
                    'name'  => 'emoji',
                    'type'  => 'text',
                ],
                [
                    'key'   => 'field_ecm_hero_qcat_label',
                    'label' => 'Label',
                    'name'  => 'label',
                    'type'  => 'text',
                ],
                [
                    'key'          => 'field_ecm_hero_qcat_slug',
                    'label'        => 'Category Slug',
                    'name'         => 'slug',
                    'type'         => 'text',
                    'instructions' => 'E.g. home-care, elder-law, memory-care',
                ],
            ],
        ],
        // Trust line
        [
            'key'           => 'field_ecm_hero_trust_year',
            'label'         => 'Trust Line — Since Year',
            'name'          => 'hero_trust_year',
            'type'          => 'text',
            'default_value' => '2002',
        ],
        [
            'key'           => 'field_ecm_hero_trust_text',
            'label'         => 'Trust Line — Text',
            'name'          => 'hero_trust_text',
            'type'          => 'text',
            'default_value' => "America's oldest & most respected elder care directory · Operating in all 50 states",
        ],
        // Hero image
        [
            'key'           => 'field_ecm_hero_image',
            'label'         => 'Hero Photo',
            'name'          => 'hero_image',
            'type'          => 'image',
            'instructions'  => 'Main editorial photo on the right side of the hero.',
            'return_format' => 'array',
            'preview_size'  => 'medium',
        ],
        // Floating card 1 — Rating
        [
            'key'   => 'field_ecm_tab_hero_cards',
            'label' => 'Hero Floating Cards',
            'name'  => '',
            'type'  => 'tab',
        ],
        [
            'key'           => 'field_ecm_hero_fc_rating_score',
            'label'         => 'Floating Card 1 — Rating Score',
            'name'          => 'hero_fc_rating_score',
            'type'          => 'text',
            'default_value' => '4.9',
        ],
        [
            'key'           => 'field_ecm_hero_fc_rating_label',
            'label'         => 'Floating Card 1 — Label',
            'name'          => 'hero_fc_rating_label',
            'type'          => 'text',
            'default_value' => '12k+ families matched',
        ],
        [
            'key'          => 'field_ecm_hero_fc_avatars',
            'label'        => 'Floating Card 1 — Avatars',
            'name'         => 'hero_fc_avatars',
            'type'         => 'repeater',
            'min'          => 1,
            'max'          => 4,
            'layout'       => 'table',
            'instructions' => 'Small circular avatar images.',
            'sub_fields'   => [
                [
                    'key'           => 'field_ecm_hero_fc_avatar_img',
                    'label'         => 'Avatar Image',
                    'name'          => 'image',
                    'type'          => 'image',
                    'return_format' => 'array',
                    'preview_size'  => 'thumbnail',
                ],
            ],
        ],
        [
            'key'           => 'field_ecm_hero_fc_avatar_count',
            'label'         => 'Floating Card 1 — Count Label',
            'name'          => 'hero_fc_avatar_count',
            'type'          => 'text',
            'default_value' => '+12k',
            'instructions'  => 'Text shown in the +N overflow avatar chip.',
        ],
        // Floating card 2 — Verified
        [
            'key'           => 'field_ecm_hero_fc_verified_title',
            'label'         => 'Floating Card 2 — Verified Title',
            'name'          => 'hero_fc_verified_title',
            'type'          => 'text',
            'default_value' => '500+ Verified Providers',
        ],
        [
            'key'           => 'field_ecm_hero_fc_verified_sub',
            'label'         => 'Floating Card 2 — Subtitle',
            'name'          => 'hero_fc_verified_sub',
            'type'          => 'text',
            'default_value' => 'Pre-screened & trusted',
        ],
        // Floating card 3 — Time
        [
            'key'           => 'field_ecm_hero_fc_time_value',
            'label'         => 'Floating Card 3 — Time Value',
            'name'          => 'hero_fc_time_value',
            'type'          => 'text',
            'default_value' => '< 2 min',
        ],
        [
            'key'           => 'field_ecm_hero_fc_time_label',
            'label'         => 'Floating Card 3 — Label',
            'name'          => 'hero_fc_time_label',
            'type'          => 'text',
            'default_value' => 'to your first match · always free',
        ],

        // ═══════════════════════════════════════════════════════════════════
        // TAB: Stats Bar
        // ═══════════════════════════════════════════════════════════════════
        [
            'key'   => 'field_ecm_tab_stats',
            'label' => 'Stats Bar',
            'name'  => '',
            'type'  => 'tab',
        ],
        [
            'key'          => 'field_ecm_stats',
            'label'        => 'Stats',
            'name'         => 'stats',
            'type'         => 'repeater',
            'min'          => 1,
            'max'          => 6,
            'layout'       => 'table',
            'sub_fields'   => [
                [
                    'key'   => 'field_ecm_stat_number',
                    'label' => 'Number',
                    'name'  => 'number',
                    'type'  => 'text',
                ],
                [
                    'key'   => 'field_ecm_stat_label',
                    'label' => 'Label',
                    'name'  => 'label',
                    'type'  => 'text',
                ],
            ],
        ],

        // ═══════════════════════════════════════════════════════════════════
        // TAB: Care Categories
        // ═══════════════════════════════════════════════════════════════════
        [
            'key'   => 'field_ecm_tab_cats',
            'label' => 'Care Categories',
            'name'  => '',
            'type'  => 'tab',
        ],
        [
            'key'           => 'field_ecm_cats_section_tag',
            'label'         => 'Section Tag',
            'name'          => 'cats_section_tag',
            'type'          => 'text',
            'default_value' => 'One need at a time',
        ],
        [
            'key'           => 'field_ecm_cats_section_title',
            'label'         => 'Section Title',
            'name'          => 'cats_section_title',
            'type'          => 'text',
            'instructions'  => 'The city name is automatically appended after this text.',
            'default_value' => 'What kind of help do you need in',
        ],
        [
            'key'           => 'field_ecm_cats_section_subtitle',
            'label'         => 'Section Subtitle',
            'name'          => 'cats_section_subtitle',
            'type'          => 'textarea',
            'rows'          => 2,
            'default_value' => "Select one category and we'll show verified local providers near you. Free, with no obligation.",
        ],
        [
            'key'           => 'field_ecm_cats_verified_strip',
            'label'         => 'Verified Strip Text',
            'name'          => 'cats_verified_strip',
            'type'          => 'text',
            'default_value' => 'All providers ECM Verified — credential-reviewed & locally licensed since 2002',
        ],
        [
            'key'          => 'field_ecm_categories',
            'label'        => 'Category Cards',
            'name'         => 'categories',
            'type'         => 'repeater',
            'instructions' => 'Each card links to a category page. Slug used in URL: category.html?type={slug}',
            'min'          => 1,
            'max'          => 12,
            'layout'       => 'block',
            'sub_fields'   => [
                [
                    'key'   => 'field_ecm_cat_name',
                    'label' => 'Category Name',
                    'name'  => 'name',
                    'type'  => 'text',
                ],
                [
                    'key'          => 'field_ecm_cat_slug',
                    'label'        => 'Category Slug',
                    'name'         => 'slug',
                    'type'         => 'text',
                    'instructions' => 'URL-safe slug, e.g. home-care',
                ],
                [
                    'key'   => 'field_ecm_cat_icon',
                    'label' => 'Icon / Emoji',
                    'name'  => 'icon',
                    'type'  => 'text',
                ],
                [
                    'key'           => 'field_ecm_cat_image',
                    'label'         => 'Card Image',
                    'name'          => 'image',
                    'type'          => 'image',
                    'return_format' => 'array',
                    'preview_size'  => 'thumbnail',
                ],
                [
                    'key'   => 'field_ecm_cat_description',
                    'label' => 'Description',
                    'name'  => 'description',
                    'type'  => 'textarea',
                    'rows'  => 2,
                ],
                [
                    'key'          => 'field_ecm_cat_cta_text',
                    'label'        => 'CTA Text Prefix',
                    'name'         => 'cta_text',
                    'type'         => 'text',
                    'instructions' => 'E.g. "See providers in" — city name appended automatically.',
                    'default_value'=> 'See providers in',
                ],
                [
                    'key'           => 'field_ecm_cat_link_url',
                    'label'         => 'Link URL (optional override)',
                    'name'          => 'link_url',
                    'type'          => 'text',
                    'instructions'  => 'Leave blank to auto-generate: /category/?type={slug}',
                ],
            ],
        ],

        // ═══════════════════════════════════════════════════════════════════
        // TAB: How It Works
        // ═══════════════════════════════════════════════════════════════════
        [
            'key'   => 'field_ecm_tab_hiw',
            'label' => 'How It Works',
            'name'  => '',
            'type'  => 'tab',
        ],
        [
            'key'           => 'field_ecm_hiw_tag',
            'label'         => 'Section Tag',
            'name'          => 'hiw_tag',
            'type'          => 'text',
            'default_value' => 'Simple Process',
        ],
        [
            'key'           => 'field_ecm_hiw_title',
            'label'         => 'Section Title',
            'name'          => 'hiw_title',
            'type'          => 'text',
            'default_value' => 'How It Works',
        ],
        [
            'key'           => 'field_ecm_hiw_subtitle',
            'label'         => 'Section Subtitle',
            'name'          => 'hiw_subtitle',
            'type'          => 'textarea',
            'rows'          => 2,
            'default_value' => 'From your first question to a confirmed match — in under 2 minutes.',
        ],
        [
            'key'          => 'field_ecm_hiw_steps',
            'label'        => 'Steps',
            'name'         => 'hiw_steps',
            'type'         => 'repeater',
            'min'          => 1,
            'max'          => 6,
            'layout'       => 'block',
            'sub_fields'   => [
                [
                    'key'   => 'field_ecm_hiw_step_num',
                    'label' => 'Step Number',
                    'name'  => 'number',
                    'type'  => 'text',
                ],
                [
                    'key'   => 'field_ecm_hiw_step_title',
                    'label' => 'Title',
                    'name'  => 'title',
                    'type'  => 'text',
                ],
                [
                    'key'   => 'field_ecm_hiw_step_desc',
                    'label' => 'Description',
                    'name'  => 'description',
                    'type'  => 'textarea',
                    'rows'  => 2,
                ],
            ],
        ],

        // ═══════════════════════════════════════════════════════════════════
        // TAB: Why ECM
        // ═══════════════════════════════════════════════════════════════════
        [
            'key'   => 'field_ecm_tab_why',
            'label' => 'Why ECM',
            'name'  => '',
            'type'  => 'tab',
        ],
        [
            'key'           => 'field_ecm_why_tag',
            'label'         => 'Section Tag',
            'name'          => 'why_tag',
            'type'          => 'text',
            'default_value' => 'Why Families Choose Us',
        ],
        [
            'key'           => 'field_ecm_why_title',
            'label'         => 'Section Title',
            'name'          => 'why_title',
            'type'          => 'text',
            'default_value' => 'Built around your urgent need',
        ],
        [
            'key'          => 'field_ecm_why_cards',
            'label'        => 'Why Cards',
            'name'         => 'why_cards',
            'type'         => 'repeater',
            'min'          => 1,
            'max'          => 8,
            'layout'       => 'block',
            'sub_fields'   => [
                [
                    'key'   => 'field_ecm_why_icon',
                    'label' => 'Icon / Emoji',
                    'name'  => 'icon',
                    'type'  => 'text',
                ],
                [
                    'key'   => 'field_ecm_why_card_title',
                    'label' => 'Title',
                    'name'  => 'title',
                    'type'  => 'text',
                ],
                [
                    'key'   => 'field_ecm_why_card_desc',
                    'label' => 'Description',
                    'name'  => 'description',
                    'type'  => 'textarea',
                    'rows'  => 2,
                ],
            ],
        ],

        // ═══════════════════════════════════════════════════════════════════
        // TAB: Testimonials
        // ═══════════════════════════════════════════════════════════════════
        [
            'key'   => 'field_ecm_tab_testi',
            'label' => 'Testimonials',
            'name'  => '',
            'type'  => 'tab',
        ],
        [
            'key'           => 'field_ecm_testi_tag',
            'label'         => 'Section Tag',
            'name'          => 'testi_tag',
            'type'          => 'text',
            'default_value' => 'Trusted by Families',
        ],
        [
            'key'           => 'field_ecm_testi_title',
            'label'         => 'Section Title',
            'name'          => 'testi_title',
            'type'          => 'text',
            'default_value' => 'What Families Are Saying',
        ],
        [
            'key'           => 'field_ecm_testi_subtitle',
            'label'         => 'Section Subtitle',
            'name'          => 'testi_subtitle',
            'type'          => 'textarea',
            'rows'          => 2,
            'default_value' => 'Real experiences from caregivers and families across the United States.',
        ],
        [
            'key'          => 'field_ecm_testimonials',
            'label'        => 'Testimonials',
            'name'         => 'testimonials',
            'type'         => 'repeater',
            'min'          => 1,
            'max'          => 9,
            'layout'       => 'block',
            'sub_fields'   => [
                [
                    'key'          => 'field_ecm_testi_stars',
                    'label'        => 'Stars',
                    'name'         => 'stars',
                    'type'         => 'select',
                    'choices'      => [
                        '★★★★★' => '5 Stars',
                        '★★★★'  => '4 Stars',
                        '★★★'   => '3 Stars',
                    ],
                    'default_value'=> '★★★★★',
                ],
                [
                    'key'   => 'field_ecm_testi_quote',
                    'label' => 'Quote',
                    'name'  => 'quote',
                    'type'  => 'textarea',
                    'rows'  => 3,
                ],
                [
                    'key'   => 'field_ecm_testi_name',
                    'label' => 'Name',
                    'name'  => 'name',
                    'type'  => 'text',
                ],
                [
                    'key'          => 'field_ecm_testi_location',
                    'label'        => 'Location',
                    'name'         => 'location',
                    'type'         => 'text',
                    'instructions' => 'E.g. Austin, TX',
                ],
                [
                    'key'           => 'field_ecm_testi_avatar',
                    'label'         => 'Avatar Photo',
                    'name'          => 'avatar',
                    'type'          => 'image',
                    'return_format' => 'array',
                    'preview_size'  => 'thumbnail',
                ],
            ],
        ],

        // ═══════════════════════════════════════════════════════════════════
        // TAB: FAQ
        // ═══════════════════════════════════════════════════════════════════
        [
            'key'   => 'field_ecm_tab_faq',
            'label' => 'FAQ',
            'name'  => '',
            'type'  => 'tab',
        ],
        [
            'key'           => 'field_ecm_faq_tag',
            'label'         => 'Section Tag',
            'name'          => 'faq_tag',
            'type'          => 'text',
            'default_value' => 'Common Questions',
        ],
        [
            'key'           => 'field_ecm_faq_title',
            'label'         => 'Section Title',
            'name'          => 'faq_title',
            'type'          => 'text',
            'default_value' => 'Frequently Asked Questions',
        ],
        [
            'key'           => 'field_ecm_faq_subtitle',
            'label'         => 'Section Subtitle',
            'name'          => 'faq_subtitle',
            'type'          => 'textarea',
            'rows'          => 2,
            'default_value' => 'Everything you need to know before getting started — no pressure, no commitment.',
        ],
        [
            'key'           => 'field_ecm_faq_link_text',
            'label'         => 'Browse All Link — Text',
            'name'          => 'faq_link_text',
            'type'          => 'text',
            'default_value' => 'Browse all FAQs →',
        ],
        [
            'key'           => 'field_ecm_faq_link_url',
            'label'         => 'Browse All Link — URL',
            'name'          => 'faq_link_url',
            'type'          => 'text',
            'default_value' => '/resources/',
        ],
        [
            'key'          => 'field_ecm_faq_items',
            'label'        => 'FAQ Items',
            'name'         => 'faq_items',
            'type'         => 'repeater',
            'min'          => 1,
            'max'          => 20,
            'layout'       => 'block',
            'sub_fields'   => [
                [
                    'key'   => 'field_ecm_faq_q',
                    'label' => 'Question',
                    'name'  => 'question',
                    'type'  => 'text',
                ],
                [
                    'key'   => 'field_ecm_faq_a',
                    'label' => 'Answer',
                    'name'  => 'answer',
                    'type'  => 'textarea',
                    'rows'  => 3,
                ],
            ],
        ],

        // ═══════════════════════════════════════════════════════════════════
        // TAB: Provider CTA
        // ═══════════════════════════════════════════════════════════════════
        [
            'key'   => 'field_ecm_tab_provider_cta',
            'label' => 'Provider CTA',
            'name'  => '',
            'type'  => 'tab',
        ],
        [
            'key'           => 'field_ecm_pcta_tag',
            'label'         => 'Section Tag',
            'name'          => 'pcta_tag',
            'type'          => 'text',
            'default_value' => 'For Providers',
        ],
        [
            'key'           => 'field_ecm_pcta_title',
            'label'         => 'Headline',
            'name'          => 'pcta_title',
            'type'          => 'text',
            'default_value' => 'Are you an elder care provider?',
        ],
        [
            'key'           => 'field_ecm_pcta_subtitle',
            'label'         => 'Subtitle',
            'name'          => 'pcta_subtitle',
            'type'          => 'textarea',
            'rows'          => 3,
            'default_value' => 'Join 500+ verified providers already using ElderCareMatters to receive scored, geo-matched leads from families actively searching for your services. Measurable ROI. No guesswork.',
        ],
        [
            'key'           => 'field_ecm_pcta_primary_text',
            'label'         => 'Primary Button — Text',
            'name'          => 'pcta_primary_text',
            'type'          => 'text',
            'default_value' => 'See Plans & Pricing →',
        ],
        [
            'key'           => 'field_ecm_pcta_primary_url',
            'label'         => 'Primary Button — URL',
            'name'          => 'pcta_primary_url',
            'type'          => 'text',
            'default_value' => '/for-providers/',
        ],
        [
            'key'           => 'field_ecm_pcta_secondary_text',
            'label'         => 'Secondary Button — Text',
            'name'          => 'pcta_secondary_text',
            'type'          => 'text',
            'default_value' => 'Provider Login',
        ],
        [
            'key'           => 'field_ecm_pcta_secondary_url',
            'label'         => 'Secondary Button — URL',
            'name'          => 'pcta_secondary_url',
            'type'          => 'text',
            'default_value' => '/provider-dashboard/',
        ],
        [
            'key'          => 'field_ecm_pcta_stats',
            'label'        => 'Stats',
            'name'         => 'pcta_stats',
            'type'         => 'repeater',
            'min'          => 1,
            'max'          => 6,
            'layout'       => 'table',
            'sub_fields'   => [
                [
                    'key'   => 'field_ecm_pcta_stat_val',
                    'label' => 'Value',
                    'name'  => 'value',
                    'type'  => 'text',
                ],
                [
                    'key'   => 'field_ecm_pcta_stat_label',
                    'label' => 'Label',
                    'name'  => 'label',
                    'type'  => 'text',
                ],
            ],
        ],

        // ═══════════════════════════════════════════════════════════════════
        // TAB: Footer
        // ═══════════════════════════════════════════════════════════════════
        [
            'key'   => 'field_ecm_tab_footer',
            'label' => 'Footer',
            'name'  => '',
            'type'  => 'tab',
        ],
        [
            'key'           => 'field_ecm_footer_logo',
            'label'         => 'Footer Logo',
            'name'          => 'footer_logo',
            'type'          => 'image',
            'return_format' => 'array',
            'preview_size'  => 'thumbnail',
        ],
        [
            'key'           => 'field_ecm_footer_brand_name',
            'label'         => 'Brand Name',
            'name'          => 'footer_brand_name',
            'type'          => 'text',
            'default_value' => 'ElderCareMatters.com',
        ],
        [
            'key'           => 'field_ecm_footer_tagline',
            'label'         => 'Tagline',
            'name'          => 'footer_tagline',
            'type'          => 'textarea',
            'rows'          => 2,
            'default_value' => 'Connecting families with elder care providers across the United States.',
        ],
        [
            'key'          => 'field_ecm_footer_trust_badges',
            'label'        => 'Footer Trust Badges',
            'name'         => 'footer_trust_badges',
            'type'         => 'repeater',
            'min'          => 1,
            'max'          => 6,
            'layout'       => 'table',
            'sub_fields'   => [
                [
                    'key'   => 'field_ecm_ftb_icon',
                    'label' => 'Icon / Emoji',
                    'name'  => 'icon',
                    'type'  => 'text',
                ],
                [
                    'key'   => 'field_ecm_ftb_text',
                    'label' => 'Text',
                    'name'  => 'text',
                    'type'  => 'text',
                ],
            ],
        ],
        [
            'key'          => 'field_ecm_footer_columns',
            'label'        => 'Footer Link Columns',
            'name'         => 'footer_columns',
            'type'         => 'repeater',
            'instructions' => 'Add/edit footer navigation columns.',
            'min'          => 1,
            'max'          => 5,
            'layout'       => 'block',
            'sub_fields'   => [
                [
                    'key'   => 'field_ecm_fcol_title',
                    'label' => 'Column Title',
                    'name'  => 'title',
                    'type'  => 'text',
                ],
                [
                    'key'          => 'field_ecm_fcol_links',
                    'label'        => 'Links',
                    'name'         => 'links',
                    'type'         => 'repeater',
                    'min'          => 1,
                    'max'          => 10,
                    'layout'       => 'table',
                    'sub_fields'   => [
                        [
                            'key'   => 'field_ecm_fcol_link_text',
                            'label' => 'Link Text',
                            'name'  => 'text',
                            'type'  => 'text',
                        ],
                        [
                            'key'   => 'field_ecm_fcol_link_url',
                            'label' => 'URL',
                            'name'  => 'url',
                            'type'  => 'text',
                        ],
                    ],
                ],
            ],
        ],
        [
            'key'           => 'field_ecm_footer_copyright',
            'label'         => 'Copyright Text',
            'name'          => 'footer_copyright',
            'type'          => 'text',
            'default_value' => '© 2026 ElderCareMatters. All rights reserved.',
        ],
        [
            'key'          => 'field_ecm_footer_bottom_links',
            'label'        => 'Bottom Bar Links',
            'name'         => 'footer_bottom_links',
            'type'         => 'repeater',
            'min'          => 0,
            'max'          => 6,
            'layout'       => 'table',
            'sub_fields'   => [
                [
                    'key'   => 'field_ecm_fbl_text',
                    'label' => 'Text',
                    'name'  => 'text',
                    'type'  => 'text',
                ],
                [
                    'key'   => 'field_ecm_fbl_url',
                    'label' => 'URL',
                    'name'  => 'url',
                    'type'  => 'text',
                ],
            ],
        ],

        // ═══════════════════════════════════════════════════════════════════
        // TAB: Location Modal & UI
        // ═══════════════════════════════════════════════════════════════════
        [
            'key'   => 'field_ecm_tab_location',
            'label' => 'Location Modal & UI',
            'name'  => '',
            'type'  => 'tab',
        ],
        [
            'key'           => 'field_ecm_location_badge_detecting',
            'label'         => 'Location Badge — Detecting Text',
            'name'          => 'location_badge_detecting',
            'type'          => 'text',
            'default_value' => 'Detecting…',
            'instructions'  => 'Shown in the nav location badge while geolocation is running.',
        ],
        [
            'key'           => 'field_ecm_location_badge_change',
            'label'         => 'Location Badge — Change Suffix',
            'name'          => 'location_badge_change',
            'type'          => 'text',
            'default_value' => '· Change',
            'instructions'  => 'Text appended after the city name in the nav badge.',
        ],
        [
            'key'           => 'field_ecm_location_modal_title',
            'label'         => 'Location Modal — Title',
            'name'          => 'location_modal_title',
            'type'          => 'text',
            'default_value' => 'Set your location',
        ],
        [
            'key'           => 'field_ecm_location_modal_sub',
            'label'         => 'Location Modal — Subtitle',
            'name'          => 'location_modal_sub',
            'type'          => 'text',
            'default_value' => 'Searching for a parent in a different city? Enter their location.',
        ],
        [
            'key'           => 'field_ecm_location_modal_city_placeholder',
            'label'         => 'Location Modal — City Input Placeholder',
            'name'          => 'location_modal_city_placeholder',
            'type'          => 'text',
            'default_value' => 'City or ZIP code…',
        ],
        [
            'key'           => 'field_ecm_location_modal_divider',
            'label'         => 'Location Modal — Divider Text',
            'name'          => 'location_modal_divider',
            'type'          => 'text',
            'default_value' => 'or',
        ],
        [
            'key'           => 'field_ecm_location_modal_cancel',
            'label'         => 'Location Modal — Cancel Button',
            'name'          => 'location_modal_cancel',
            'type'          => 'text',
            'default_value' => 'Cancel',
        ],
        [
            'key'           => 'field_ecm_location_modal_submit',
            'label'         => 'Location Modal — Submit Button',
            'name'          => 'location_modal_submit',
            'type'          => 'text',
            'default_value' => 'Update Location',
        ],

        // ═══════════════════════════════════════════════════════════════════
        // TAB: Modals & Intake
        // ═══════════════════════════════════════════════════════════════════
        [
            'key'   => 'field_ecm_tab_modals',
            'label' => 'Modals & Intake',
            'name'  => '',
            'type'  => 'tab',
        ],
        [
            'key'           => 'field_ecm_dotiq_enabled',
            'label'         => 'Enable Dotiq Feedback Widget',
            'name'          => 'dotiq_enabled',
            'type'          => 'true_false',
            'default_value' => 1,
            'ui'            => 1,
        ],
        [
            'key'           => 'field_ecm_dotiq_app_id',
            'label'         => 'Dotiq App ID',
            'name'          => 'dotiq_app_id',
            'type'          => 'text',
            'default_value' => 'ecm',
            'instructions'  => 'The data-app attribute value for the Dotiq widget.',
        ],
        [
            'key'           => 'field_ecm_form_modal_close_icon',
            'label'         => 'Form Modal — Close Icon',
            'name'          => 'form_modal_close_icon',
            'type'          => 'text',
            'default_value' => '×',
            'instructions'  => 'Icon on the × close button of the 7-step intake form modal.',
        ],
        [
            'key'           => 'field_ecm_chat_steps_total',
            'label'         => 'Intake — Total Steps',
            'name'          => 'chat_steps_total',
            'type'          => 'number',
            'default_value' => 7,
            'instructions'  => 'Total step count shown in "Step 1 of N" labels in the intake form modal.',
            'min'           => 1,
            'max'           => 20,
        ],

    ], // end fields
] ); // end acf_add_local_field_group
