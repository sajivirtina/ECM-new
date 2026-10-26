<?php
/**
 * ACF fields for the For Providers page template.
 */

defined( 'ABSPATH' ) || exit;

acf_add_local_field_group( [
    'key'      => 'group_ecm_fp',
    'title'    => 'For Providers Content',
    'location' => [ [ [
        'param'    => 'page_template',
        'operator' => '==',
        'value'    => 'template-for-providers.php',
    ] ] ],
    'menu_order'      => 0,
    'position'        => 'normal',
    'label_placement' => 'top',
    'active'          => true,
    'fields'          => [

        [ 'key' => 'field_ecm_fp_tab_hero', 'label' => 'Hero', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_fp_hero_eyebrow', 'label' => 'Eyebrow', 'name' => 'fp_hero_eyebrow', 'type' => 'text', 'default_value' => 'For Care Providers' ],
        [ 'key' => 'field_ecm_fp_hero_title', 'label' => 'Title (allows <br> and <em>)', 'name' => 'fp_hero_title', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'Help Families When They Need It Most.<br>Grow a Practice That <em>Truly Matters</em>.' ],
        [ 'key' => 'field_ecm_fp_hero_sub1', 'label' => 'Subtitle Paragraph 1', 'name' => 'fp_hero_sub1', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'Families come to ElderCareMatters after a diagnosis, a fall, or a sudden move. They pick the providers they want to hear from. We put you on that list.' ],
        [ 'key' => 'field_ecm_fp_hero_sub2', 'label' => 'Subtitle Paragraph 2', 'name' => 'fp_hero_sub2', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'If your work helps older adults and their families, you belong here.' ],
        [ 'key' => 'field_ecm_fp_hero_cta_primary', 'label' => 'Primary CTA Button Text (links to Plans & Pricing)', 'name' => 'fp_hero_cta_primary', 'type' => 'text', 'default_value' => 'See Plans & Pricing' ],
        [ 'key' => 'field_ecm_fp_hero_cta', 'label' => 'Secondary CTA Button Text (links to the request form)', 'name' => 'fp_hero_cta', 'type' => 'text', 'default_value' => 'Request Information' ],
        [
            'key' => 'field_ecm_fp_hero_trust', 'label' => 'Trust Items', 'name' => 'fp_hero_trust',
            'type' => 'repeater', 'layout' => 'table', 'min' => 0, 'max' => 8,
            'sub_fields' => [ [ 'key' => 'field_ecm_fp_hero_trust_text', 'label' => 'Text', 'name' => 'text', 'type' => 'text' ] ],
        ],

        [ 'key' => 'field_ecm_fp_tab_value', 'label' => 'You Do Important Work', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_fp_value_eyebrow', 'label' => 'Eyebrow', 'name' => 'fp_value_eyebrow', 'type' => 'text', 'default_value' => 'Why It Matters' ],
        [ 'key' => 'field_ecm_fp_value_quote', 'label' => 'Quote', 'name' => 'fp_value_quote', 'type' => 'text', 'default_value' => '"You Do Important Work. We Help More People Find You."' ],
        [ 'key' => 'field_ecm_fp_value_sub', 'label' => 'Description (allows <br>)', 'name' => 'fp_value_sub', 'type' => 'textarea', 'rows' => 4, 'default_value' => 'This is not a typical marketing platform. The families who come to ElderCareMatters are in the middle of something hard — a diagnosis, a fall, a sudden transition — and they need someone they can trust. That someone is you.<br><br>We exist to make sure they find you.' ],
        [
            'key' => 'field_ecm_fp_emotions', 'label' => 'Emotion Cards', 'name' => 'fp_emotions',
            'type' => 'repeater', 'layout' => 'table', 'min' => 0, 'max' => 8,
            'sub_fields' => [
                [ 'key' => 'field_ecm_fp_emo_who', 'label' => 'Who comes with', 'name' => 'who', 'type' => 'text' ],
                [ 'key' => 'field_ecm_fp_emo_feel', 'label' => 'Emoji', 'name' => 'feeling', 'type' => 'text' ],
                [ 'key' => 'field_ecm_fp_emo_title', 'label' => 'Emotion', 'name' => 'title', 'type' => 'text' ],
            ],
        ],

        [ 'key' => 'field_ecm_fp_tab_who', 'label' => 'Who We Work With', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_fp_who_eyebrow', 'label' => 'Eyebrow', 'name' => 'fp_who_eyebrow', 'type' => 'text', 'default_value' => 'Who Participates' ],
        [ 'key' => 'field_ecm_fp_who_title', 'label' => 'Title (use <em>)', 'name' => 'fp_who_title', 'type' => 'text', 'default_value' => 'Who We Work <em>With</em>' ],
        [ 'key' => 'field_ecm_fp_who_lead', 'label' => 'Lead', 'name' => 'fp_who_lead', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'We partner with professionals and organizations across the full spectrum of elder care support:' ],
        [
            'key' => 'field_ecm_fp_who_items', 'label' => 'Provider Categories', 'name' => 'fp_who_items',
            'type' => 'repeater', 'layout' => 'table', 'min' => 0, 'max' => 30,
            'sub_fields' => [ [ 'key' => 'field_ecm_fp_who_item', 'label' => 'Category', 'name' => 'item', 'type' => 'text' ] ],
        ],
        [ 'key' => 'field_ecm_fp_who_note', 'label' => 'Note', 'name' => 'fp_who_note', 'type' => 'text', 'default_value' => 'If your work supports older adults and their families, you can participate.' ],

        [ 'key' => 'field_ecm_fp_tab_why', 'label' => 'Why Providers Join', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_fp_why_eyebrow', 'label' => 'Eyebrow', 'name' => 'fp_why_eyebrow', 'type' => 'text', 'default_value' => 'Why Join' ],
        [ 'key' => 'field_ecm_fp_why_title', 'label' => 'Title (use <em>)', 'name' => 'fp_why_title', 'type' => 'text', 'default_value' => 'Why Providers Join <em>ElderCareMatters</em>' ],
        [
            'key' => 'field_ecm_fp_why_cards', 'label' => 'Reason Cards', 'name' => 'fp_why_cards',
            'type' => 'repeater', 'layout' => 'block', 'min' => 0, 'max' => 8,
            'sub_fields' => [
                [ 'key' => 'field_ecm_fp_why_num', 'label' => 'Number', 'name' => 'num', 'type' => 'text' ],
                [ 'key' => 'field_ecm_fp_why_ctitle', 'label' => 'Title', 'name' => 'title', 'type' => 'text' ],
                [ 'key' => 'field_ecm_fp_why_desc', 'label' => 'Description', 'name' => 'desc', 'type' => 'textarea', 'rows' => 3 ],
            ],
        ],

        [ 'key' => 'field_ecm_fp_tab_receive', 'label' => 'What You Receive', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_fp_rec_eyebrow', 'label' => 'Eyebrow', 'name' => 'fp_rec_eyebrow', 'type' => 'text', 'default_value' => 'Platform Benefits' ],
        [ 'key' => 'field_ecm_fp_rec_title', 'label' => 'Title (use <em>)', 'name' => 'fp_rec_title', 'type' => 'text', 'default_value' => 'What You <em>Receive</em>' ],
        [
            'key' => 'field_ecm_fp_rec_cards', 'label' => 'Benefit Cards', 'name' => 'fp_rec_cards',
            'type' => 'repeater', 'layout' => 'block', 'min' => 0, 'max' => 12,
            'sub_fields' => [
                [ 'key' => 'field_ecm_fp_rec_icon', 'label' => 'Icon', 'name' => 'icon', 'type' => 'text' ],
                [ 'key' => 'field_ecm_fp_rec_ctitle', 'label' => 'Title', 'name' => 'title', 'type' => 'text' ],
                [ 'key' => 'field_ecm_fp_rec_desc', 'label' => 'Description', 'name' => 'desc', 'type' => 'textarea', 'rows' => 3 ],
            ],
        ],

        [ 'key' => 'field_ecm_fp_tab_hiw', 'label' => 'How Inquiries Work', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_fp_hiw_eyebrow', 'label' => 'Eyebrow', 'name' => 'fp_hiw_eyebrow', 'type' => 'text', 'default_value' => 'How Inquiries Work' ],
        [ 'key' => 'field_ecm_fp_hiw_title', 'label' => 'Title (use <em>)', 'name' => 'fp_hiw_title', 'type' => 'text', 'default_value' => 'How Inquiries <em>Work</em>' ],
        [ 'key' => 'field_ecm_fp_hiw_body', 'label' => 'Body (allows <a>; placed after Platform Benefits)', 'name' => 'fp_hiw_body', 'type' => 'textarea', 'rows' => 4, 'default_value' => 'Families pick up to 10 providers in one category. The providers they pick get the request first. You see the request right away, with the family\'s contact details hidden. Your plan decides when you can request those details: Featured in the first 24 hours, Premium from hour 24, Basic from hour 48, and Free after 72 hours. If a family opts into expedited matching and its picks don\'t respond, the masked request also goes to additional providers nearby.' ],

        [ 'key' => 'field_ecm_fp_tab_how', 'label' => 'How It Works', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_fp_how_eyebrow', 'label' => 'Eyebrow', 'name' => 'fp_how_eyebrow', 'type' => 'text', 'default_value' => 'The Process' ],
        [ 'key' => 'field_ecm_fp_how_title', 'label' => 'Title (use <em>)', 'name' => 'fp_how_title', 'type' => 'text', 'default_value' => 'How It <em>Works</em>' ],
        [
            'key' => 'field_ecm_fp_how_steps', 'label' => 'Steps', 'name' => 'fp_how_steps',
            'type' => 'repeater', 'layout' => 'block', 'min' => 0, 'max' => 10,
            'sub_fields' => [
                [ 'key' => 'field_ecm_fp_how_num', 'label' => 'Number', 'name' => 'num', 'type' => 'text' ],
                [ 'key' => 'field_ecm_fp_how_stitle', 'label' => 'Title', 'name' => 'title', 'type' => 'text' ],
                [ 'key' => 'field_ecm_fp_how_desc', 'label' => 'Description', 'name' => 'desc', 'type' => 'textarea', 'rows' => 3 ],
            ],
        ],

        [ 'key' => 'field_ecm_fp_tab_matters', 'label' => 'Why This Matters', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_fp_matters_eyebrow', 'label' => 'Eyebrow', 'name' => 'fp_matters_eyebrow', 'type' => 'text', 'default_value' => 'The Bigger Picture' ],
        [ 'key' => 'field_ecm_fp_matters_copy', 'label' => 'Quote', 'name' => 'fp_matters_copy', 'type' => 'text', 'default_value' => '"The families who need you are out there — and they don\'t always know where to look."' ],
        [ 'key' => 'field_ecm_fp_matters_sub', 'label' => 'Description', 'name' => 'fp_matters_sub', 'type' => 'textarea', 'rows' => 3, 'default_value' => "America's senior population is growing faster than at any point in history. Families are being asked to make complex, urgent decisions — often without preparation, often without support. The providers who show up with clarity and compassion in these moments change lives." ],
        [
            'key' => 'field_ecm_fp_matters_items', 'label' => 'List Items', 'name' => 'fp_matters_items',
            'type' => 'repeater', 'layout' => 'table', 'min' => 0, 'max' => 12,
            'sub_fields' => [ [ 'key' => 'field_ecm_fp_matters_item', 'label' => 'Item', 'name' => 'item', 'type' => 'textarea', 'rows' => 2 ] ],
        ],

        [ 'key' => 'field_ecm_fp_tab_trust', 'label' => 'Trusted Since', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_fp_trust_year', 'label' => 'Year Display', 'name' => 'fp_trust_year', 'type' => 'text', 'default_value' => 'Since 2002' ],
        [ 'key' => 'field_ecm_fp_trust_quote', 'label' => 'Quote', 'name' => 'fp_trust_quote', 'type' => 'textarea', 'rows' => 3, 'default_value' => '"For more than twenty years, ElderCareMatters has helped connect families with the professionals who guide them through one of life\'s most challenging chapters — with compassion, integrity, and genuine care."' ],
        [
            'key' => 'field_ecm_fp_trust_attrs', 'label' => 'Attributes', 'name' => 'fp_trust_attrs',
            'type' => 'repeater', 'layout' => 'table', 'min' => 0, 'max' => 8,
            'sub_fields' => [ [ 'key' => 'field_ecm_fp_trust_attr', 'label' => 'Attribute', 'name' => 'attr', 'type' => 'text' ] ],
        ],

        [ 'key' => 'field_ecm_fp_tab_form', 'label' => 'Submit Form', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_fp_form_eyebrow', 'label' => 'Eyebrow', 'name' => 'fp_form_eyebrow', 'type' => 'text', 'default_value' => 'Get Started' ],
        [ 'key' => 'field_ecm_fp_form_title', 'label' => 'Title (use <em>)', 'name' => 'fp_form_title', 'type' => 'text', 'default_value' => 'Submit a Request for <em>Information</em>' ],
        [ 'key' => 'field_ecm_fp_form_lead', 'label' => 'Lead', 'name' => 'fp_form_lead', 'type' => 'textarea', 'rows' => 2, 'default_value' => "Ready to connect with more families? Tell us about your practice and we'll reach out with next steps." ],
        [ 'key' => 'field_ecm_fp_form_shortcode', 'label' => 'Form Shortcode', 'name' => 'fp_form_shortcode', 'type' => 'text', 'instructions' => 'Paste the complete form shortcode, including its square brackets.', 'default_value' => '[contact-form-7 id="f16beae" title="Contact form 1"]' ],
        [ 'key' => 'field_ecm_fp_form_confirm_title', 'label' => 'Confirmation Title', 'name' => 'fp_form_confirm_title', 'type' => 'text', 'default_value' => "Thank you — we'll be in touch shortly." ],
        [ 'key' => 'field_ecm_fp_form_confirm_text', 'label' => 'Confirmation Text', 'name' => 'fp_form_confirm_text', 'type' => 'textarea', 'rows' => 2, 'default_value' => "We've received your inquiry and will reach out within 1 business day with availability and next steps for your service area." ],

        [ 'key' => 'field_ecm_fp_tab_cta', 'label' => 'Final CTA', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_fp_cta_title', 'label' => 'Title (use <em>)', 'name' => 'fp_cta_title', 'type' => 'text', 'default_value' => 'Join a Network Built on <em>Care, Trust, and Growth</em>' ],
        [
            'key' => 'field_ecm_fp_cta_bullets', 'label' => 'Bullets', 'name' => 'fp_cta_bullets',
            'type' => 'repeater', 'layout' => 'table', 'min' => 0, 'max' => 8,
            'sub_fields' => [ [ 'key' => 'field_ecm_fp_cta_bullet', 'label' => 'Bullet', 'name' => 'bullet', 'type' => 'text' ] ],
        ],
        [ 'key' => 'field_ecm_fp_cta_btn', 'label' => 'Button Text', 'name' => 'fp_cta_btn', 'type' => 'text', 'default_value' => 'Become an ElderCareMatters Provider Today →' ],
    ],
] );
