<?php
/**
 * Content changes tool - one place to preview, apply and revert the saved
 * content edits listed in the page change documents.
 *
 * Open (administrators only):
 *     /wp-admin/?ecm_content_changes=1
 *
 * Nothing runs on a normal page load and nothing is written until a button
 * is pressed. The screen is the dry run: it lists every page it found (ID,
 * slug, title, template), the current value of each field and what it would
 * become. Every change is safe to run twice:
 *   - already the new value  -> "Already applied", skipped
 *   - matches the old value  -> "Pending", can be applied
 *   - anything else          -> "Needs review", left alone (hand-edited)
 * Each apply stores the previous value so it can be reverted. The log and the
 * stored previous values are kept until cleared by hand on the same screen.
 *
 * Lives in content-migration/ so it can be removed as a unit: delete that folder and
 * the matching block in functions.php once the changes are confirmed.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

const ECM_CC_LOG    = 'ecm_cc_log';
const ECM_CC_BACKUP = 'ecm_cc_backup';
const ECM_CC_TARGETS = 'ecm_cc_targets';

add_action( 'admin_init', 'ecm_cc_boot' );

function ecm_cc_boot() : void {
	if ( empty( $_GET['ecm_content_changes'] ) || wp_doing_ajax() ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['ecm_cc_action'] ) ) {
		ecm_cc_handle_post();
	}
	ecm_cc_render();
	exit;
}

/* ───────────────────────── Change list ───────────────────────── */

/**
 * Pages and their changes. Local page IDs go in 'local_id' so the dry run can
 * show whether this site resolved the same page.
 *
 * Change types:
 *   meta            set one meta/ACF value (match: contains | equals | any)
 *   meta_scan       set every meta value whose key matches 'key_regex' and
 *                   whose value matches; 'siblings' also sets sibling keys
 *   repeater_remove remove rows from an ACF repeater and renumber it
 *   content         find/replace in the page body ('regex' => true or 'loose')
 *   content_set     replace the whole page body (never bulk-applied)
 */
function ecm_cc_definitions() : array {
	$cta_secondary_note = 'The secondary "Chat with advisor" button text is not changed here: decide the replacement first.';

	return [
		[
			'id'      => 'home',
			'label'   => 'Homepage',
			'resolve' => [ 'front' => true ],
			'local_id' => 25,
			'changes' => [
				[ 'id' => 'home-chat-advisor-name', 'section' => 'Chat widget name', 'type' => 'meta', 'key' => 'chat_advisor_name', 'bulk' => false,
					'match' => [ 'contains' => 'Advisor' ], 'to' => 'ECM Care Team',
					'note' => 'The chat name uses "advisor" in ECM\'s own voice. Replace with a team term.' ],
				[ 'id' => 'home-footer-remove-0', 'section' => 'Footer', 'type' => 'footer_link_remove', 'text_contains' => 'Elder Care Locator',
					'note' => 'Addendum 1, Decision 1: remove "Elder Care Locator" from the footer.' ],
				[ 'id' => 'home-footer-remove-1', 'section' => 'Footer', 'type' => 'footer_link_remove', 'text_contains' => 'Featured Directory',
					'note' => 'Addendum 1, Decision 2: remove "Featured Directory Listings" from the footer.' ],
				[ 'id' => 'home-footer-remove-2', 'section' => 'Footer', 'type' => 'footer_link_remove', 'text_contains' => 'Sponsored Listings',
					'note' => 'Sponsored Listings page is retired (redirects to /plans/); remove its footer link.' ],
				[ 'id' => 'home-footer-link-0', 'section' => 'Footer', 'type' => 'link_url', 'text' => 'Cost of Care', 'url' => '/resources/financial-planning-insurance/',
					'note' => 'Footer link target. Resources Changes doc, footer links table.' ],
				[ 'id' => 'home-footer-link-1', 'section' => 'Footer', 'type' => 'link_url', 'text' => 'Dementia Support', 'url' => '/resources/memory-special-needs-care/',
					'note' => 'Footer link target. Resources Changes doc, footer links table.' ],
				[ 'id' => 'home-footer-link-2', 'section' => 'Footer', 'type' => 'link_url', 'text' => 'Legal Planning', 'url' => '/resources/legal-estate-planning/',
					'note' => 'Footer link target. Resources Changes doc, footer links table.' ],
				[ 'id' => 'home-strip', 'type' => 'meta', 'key' => 'trust_bar_text', 'field_key' => 'field_ecm_trust_bar_text',
					'match' => [ 'contains' => 'Trusted Care Matching' ],
					'to' => '✦ Choose your own providers · Free for families',
					'note' => 'Header strip. Checklist item 107.' ],
				[ 'id' => 'home-hero-sub', 'type' => 'meta', 'key' => 'hero_subtitle', 'field_key' => 'field_ecm_hero_subtitle',
					'match' => [ 'contains' => 'care advisor' ],
					'to' => 'Tell us what you need and choose up to 10 verified local providers to hear from. Free, no spam, no obligation.',
					'note' => 'Hero subhead. Checklist item 75 (outranks the Homepage Changes table row).' ],
				[ 'id' => 'home-footer-tagline', 'type' => 'meta', 'key' => 'footer_tagline', 'field_key' => 'field_ecm_footer_tagline',
					'match' => [ 'contains' => 'trusted' ],
					'to' => 'Connecting families with elder care providers across the United States.',
					'note' => 'Footer tagline. Checklist item 108.' ],
				[ 'id' => 'home-footer-badge-verified', 'type' => 'repeater_remove', 'base' => 'footer_trust_badges',
					'match_sub' => 'text', 'match' => [ 'equals' => 'Verified Providers' ],
					'note' => 'Footer trust row: remove "Verified Providers". Checklist item 108.' ],
				[ 'id' => 'home-footer-badge-privacy', 'type' => 'meta_scan', 'key_regex' => '/^footer_trust_badges_\d+_text$/',
					'match' => [ 'equals' => 'Privacy Protected' ],
					'to' => 'Your details stay private until you choose',
					'note' => 'Footer trust row: replace "Privacy Protected". Checklist item 108.' ],
				[ 'id' => 'home-footer-plans-text', 'type' => 'meta_scan', 'key_regex' => '/^footer_columns_\d+_links_\d+_text$/',
					'match' => [ 'equals' => 'Plans & Benefits' ],
					'to' => 'Plans & Pricing',
					'siblings' => [ 'url' => '/plans/' ],
					'note' => 'Footer link name and target. Checklist item 116. The page must exist before this goes live.' ],
				[ 'id' => 'home-footer-plans-url', 'section' => 'Footer', 'type' => 'link_url', 'text' => 'Plans & Pricing', 'url' => '/plans/', 'bulk' => false,
					'note' => 'Footer link target. Apply only after the /plans/ page exists (checklist items 68 and 116).' ],
				[ 'id' => 'home-footer-alliance', 'type' => 'footer_move',
					'text_contains' => 'Alliance', 'from_column' => 'Find Care', 'to_column' => 'For Providers',
					'note' => 'Move the Alliance link from Find Care to For Providers. Item 115.' ],
			],
		],
		[
			'id'      => 'category',
			'label'   => 'Category pages',
			'resolve' => [ 'template' => 'template-category.php', 'has_meta' => [ 'catp_ctaband_title', 'catp_ctaband_sub', 'catp_providers_subtitle' ] ],
			'changes' => [
				[ 'id' => 'cat-cta-title', 'type' => 'meta', 'key' => 'catp_ctaband_title', 'field_key' => 'field_ecm_catp_ctaband_title',
					'match' => [ 'contains' => "guide you" ],
					'to' => 'Not sure who to pick?',
					'note' => 'Bottom block heading. Category Pages doc.' ],
				[ 'id' => 'cat-cta-sub', 'type' => 'meta', 'key' => 'catp_ctaband_sub', 'field_key' => 'field_ecm_catp_ctaband_sub',
					'match' => [ 'contains' => 'advisor' ],
					'to' => "Answer a few questions and we'll show you verified providers near your loved one. You choose up to 10.",
					'note' => 'Bottom block text. Removes "advisor" (item 77).' ],
				[ 'id' => 'cat-providers-sub', 'type' => 'meta', 'key' => 'catp_providers_subtitle', 'field_key' => 'field_ecm_catp_providers_subtitle',
					'match' => [ 'contains' => 'Sorted by tier' ],
					'to' => "Choose up to 10 providers you'd like to hear from.",
					'note' => 'List subhead. Tiers stay hidden from families.' ],
			],
		],
		[
			'id'      => 'article',
			'label'   => 'Article pages (CTA template)',
			'resolve' => [ 'template' => 'template-blog-detail.php', 'has_meta' => [ 'bd_cb_title', 'bd_cb_sub' ] ],
			'changes' => [
				[ 'id' => 'art-cta-title', 'type' => 'meta', 'key' => 'bd_cb_title', 'field_key' => 'field_ecm_bd_cb_title',
					'match' => [ 'contains' => "guide you" ],
					'to' => 'Ready to find home care? <em>Tell us what you need.</em>',
					'note' => 'Sitewide CTA template, advisor audit item 4.' ],
				[ 'id' => 'art-cta-sub', 'type' => 'meta', 'key' => 'bd_cb_sub', 'field_key' => 'field_ecm_bd_cb_sub',
					'match' => [ 'contains' => 'advisor' ],
					'to' => 'Tell us what you need and choose the providers you want to hear from. Takes about 2 minutes.',
					'note' => 'Advisor audit item 4. ' . $cta_secondary_note ],
			],
		],
		[
			'id'      => 'blog',
			'label'   => 'Resources page',
			'resolve' => [ 'slug' => [ 'resources' ] ],
			'local_id' => 56,
			'changes' => [
				[ 'id' => 'blog-seo-title', 'section' => 'SEO title', 'type' => 'meta', 'key' => '_yoast_wpseo_title', 'create' => true,
					'match' => [],
					'to' => 'Elder Care Guides for Families | ElderCareMatters',
					'note' => 'Yoast SEO title. Resources Changes doc, copy table.' ],
				[ 'id' => 'blog-grid-sub', 'type' => 'meta', 'key' => 'blog_grid_sub', 'field_key' => 'field_ecm_blog_grid_sub',
					'match' => [ 'contains' => 'advisors' ],
					'to' => 'Practical answers on care options, costs, legal planning and daily support.',
					'note' => 'Resources subhead. Resources Changes doc.' ],
			],
		],
		[
			'id'      => 'plans',
			'label'   => 'Plans & Pricing',
			'resolve' => [ 'template' => 'template-plans-pricing.php', 'shop' => true ],
			'local_id' => 76,
			'changes' => [
				[ 'id' => 'pl-card-0', 'section' => 'Plan cards', 'type' => 'meta_text_replace', 'key_regex' => '/^pl_cards_/',
					'find' => 'Family enquiries released after 5 days', 'replace' => 'Request family details after 72 hours',
					'note' => 'Plan card text. Provider Pages doc, copy changes and fixes.' ],
				[ 'id' => 'pl-card-1', 'section' => 'Plan cards', 'type' => 'meta_text_replace', 'key_regex' => '/^pl_cards_/',
					'find' => 'Family enquiries released after 48 hours', 'replace' => 'Request family details from hour 48',
					'note' => 'Plan card text. Provider Pages doc, copy changes and fixes.' ],
				[ 'id' => 'pl-card-2', 'section' => 'Plan cards', 'type' => 'meta_text_replace', 'key_regex' => '/^pl_cards_/',
					'find' => 'Family enquiries released after 24 hours', 'replace' => 'Request family details from hour 24',
					'note' => 'Plan card text. Provider Pages doc, copy changes and fixes.' ],
				[ 'id' => 'pl-card-3', 'section' => 'Plan cards', 'type' => 'meta_text_replace', 'key_regex' => '/^pl_cards_/',
					'find' => 'Family enquiries same day', 'replace' => 'First 24 hours to request family details',
					'note' => 'Plan card text. Provider Pages doc, copy changes and fixes.' ],
				[ 'id' => 'pl-card-4', 'section' => 'Plan cards', 'type' => 'meta_text_replace', 'key_regex' => '/^pl_cards_/',
					'find' => 'Top-tier placement and same-day family enquiry access.', 'replace' => 'Top placement and first access to every family who picks you.',
					'note' => 'Plan card text. Provider Pages doc, copy changes and fixes.' ],
				[ 'id' => 'pl-card-5', 'section' => 'Plan cards', 'type' => 'meta_text_replace', 'key_regex' => '/^pl_cards_/',
					'find' => '$Free', 'replace' => 'Free',
					'note' => 'Plan card text. Provider Pages doc, copy changes and fixes.' ],
				[ 'id' => 'pl-card-6', 'section' => 'Plan cards', 'type' => 'meta_text_replace', 'key_regex' => '/^pl_cards_/',
					'find' => '9-city coverage', 'replace' => '8 cities included',
					'note' => 'Plan card text. Provider Pages doc, copy changes and fixes.' ],
				[ 'id' => 'pl-enquiries', 'section' => 'Spelling', 'type' => 'meta_text_replace', 'key_regex' => '/^pl_/',
					'find' => 'enquir', 'replace' => 'inquir',
					'note' => 'Change "enquiries" to "inquiries" in saved Plans & Pricing text.' ],
				[ 'id' => 'pl-seo-title', 'section' => 'SEO title', 'type' => 'meta', 'key' => '_yoast_wpseo_title', 'create' => true,
					'match' => [],
					'to' => 'Elder Care Directory Plans & Pricing | ElderCareMatters',
					'note' => 'Yoast SEO title. Provider Pages doc, Plans & Pricing page title.' ],
				[ 'id' => 'pl-hero-sub', 'type' => 'meta', 'key' => 'pl_hero_sub', 'field_key' => 'field_ecm_pl_hero_sub',
					'match' => [ 'contains' => 'oldest and most respected' ],
					'to' => 'List your practice on ElderCareMatters.com, serving families since 2002. Pick the plan that fits how fast you want to reach families.',
					'note' => 'Provider Pages doc, intro.' ],
				[ 'id' => 'pl-faq-answer', 'type' => 'meta_scan', 'key_regex' => '/^pl_faq_items_\d+_answer$/',
					'match' => [ 'contains' => 'same day' ],
					'to' => "You get an inquiry when a family picks you. Every provider the family picked sees the request right away, with contact details hidden. Featured providers can request the family's details in the first 24 hours, Premium from hour 24, Basic from hour 48, and Free after 72 hours. If a family opts into expedited matching and its picks don't respond, the masked request also goes to additional verified providers nearby. Upgrade to reach families sooner.",
					'note' => 'FAQ: When do I receive new family inquiries?' ],
				[ 'id' => 'pl-t-free', 'type' => 'meta_scan', 'key_regex' => '/^pl_table_rows_\d+_val\d$/',
					'match' => [ 'equals' => 'After 5 days' ], 'to' => 'After 72 hours', 'note' => 'Compare table, Free column.' ],
				[ 'id' => 'pl-t-basic', 'type' => 'meta_scan', 'key_regex' => '/^pl_table_rows_\d+_val\d$/',
					'match' => [ 'equals' => 'After 48 hours' ], 'to' => 'From hour 48', 'note' => 'Compare table, Basic column.' ],
				[ 'id' => 'pl-t-premium', 'type' => 'meta_scan', 'key_regex' => '/^pl_table_rows_\d+_val\d$/',
					'match' => [ 'equals' => 'After 24 hours' ], 'to' => 'From hour 24', 'note' => 'Compare table, Premium column.' ],
				[ 'id' => 'pl-t-featured', 'type' => 'meta_scan', 'key_regex' => '/^pl_table_rows_\d+_val\d$/',
					'match' => [ 'equals' => 'Same day' ], 'to' => 'Hours 0 to 24', 'note' => 'Compare table, Featured column.' ],
				[ 'id' => 'pl-t-label', 'type' => 'meta_scan', 'key_regex' => '/^pl_table_rows_\d+_label$/',
					'match' => [ 'contains' => 'released' ], 'to' => 'Can request family details', 'note' => 'Compare table row label ("New enquiry released").' ],
				[ 'id' => 'pl-t-cities', 'type' => 'meta_scan', 'key_regex' => '/^pl_table_rows_\d+_label$/',
					'match' => [ 'equals' => 'Locations per account' ], 'to' => 'Cities included',
					'siblings' => [ 'val1' => '1', 'val2' => '2', 'val3' => '4', 'val4' => '8' ],
					'note' => 'Replaces "Locations per account" with 1, 2, 4, 8 (Free, Basic, Premium, Featured).' ],
				[ 'id' => 'pl-t-badge', 'type' => 'repeater_remove', 'base' => 'pl_table_rows',
					'match_sub' => 'label', 'match' => [ 'equals' => 'Featured badge' ],
					'note' => 'Remove the "Featured badge" row. The extra-cities row is added by hand.' ],
			],
		],
		[
			'id'      => 'for-providers',
			'label'   => 'For Providers',
			'resolve' => [ 'template' => 'template-for-providers.php' ],
			'local_id' => 283310,
			'changes' => [
				[ 'id' => 'fp-hero-sub1', 'section' => 'Hero', 'type' => 'meta', 'key' => 'fp_hero_sub1', 'field_key' => 'field_ecm_fp_hero_sub1',
					'match' => [ 'contains' => 'compassionate providers' ],
					'to' => 'Families come to ElderCareMatters after a diagnosis, a fall, or a sudden move. They pick the providers they want to hear from. We put you on that list.',
					'note' => 'Provider Pages doc, For Providers hero subhead.' ],
				[ 'id' => 'fp-hero-sub2', 'section' => 'Hero', 'type' => 'meta', 'key' => 'fp_hero_sub2', 'field_key' => 'field_ecm_fp_hero_sub2',
					'match' => [ 'contains' => 'If you care about the work' ],
					'to' => 'If your work helps older adults and their families, you belong here.',
					'note' => 'Provider Pages doc, hero second line.' ],
				[ 'id' => 'fp-form-title', 'section' => 'Provider request form', 'type' => 'meta', 'key' => 'fp_form_title', 'field_key' => 'field_ecm_fp_form_title',
					'match' => [ 'contains' => 'Submit a Request' ],
					'to' => 'Tell us about your <em>practice</em>',
					'note' => 'Provider Pages doc, request form heading.' ],
				[ 'id' => 'fp-trust-since', 'section' => 'Banned words', 'type' => 'meta_text_replace', 'key_regex' => '/^fp_/',
					'find' => 'Trusted since 2002', 'replace' => 'Serving families since 2002',
					'note' => 'Risk Rules: no "trusted" in ECM\'s own text.' ],
				[ 'id' => 'fp-trusted-guidance', 'section' => 'Banned words', 'type' => 'meta_text_replace', 'key_regex' => '/^fp_/',
					'find' => 'Because Families Need Trusted Guidance', 'replace' => 'Because Families Need Clear Guidance',
					'note' => 'Risk Rules: no "trusted" in ECM\'s own text.' ],
				[ 'id' => 'fp-trusted-expert', 'section' => 'Banned words', 'type' => 'meta_text_replace', 'key_regex' => '/^fp_/',
					'find' => 'positions you as that trusted expert in your local community', 'replace' => 'puts you in front of them in your local community',
					'note' => 'Risk Rules: no "trusted" in ECM\'s own text.' ],
				[ 'id' => 'fp-trusted-qualified', 'section' => 'Banned words', 'type' => 'meta_text_replace', 'key_regex' => '/^fp_/',
					'find' => 'Trusted, qualified providers are the bridge', 'replace' => 'Providers who care are the bridge',
					'note' => 'Risk Rules: no "trusted" or "qualified" in ECM\'s own text.' ],
				[ 'id' => 'fp-qualified-title', 'section' => 'Banned words', 'type' => 'meta_text_replace', 'key_regex' => '/^fp_/',
					'find' => 'Qualified Family Inquiries', 'replace' => 'Real Family Inquiries',
					'note' => 'Risk Rules: no "qualified" in ECM\'s own text.' ],
				[ 'id' => 'fp-enquiries', 'section' => 'Spelling', 'type' => 'meta_text_replace', 'key_regex' => '/^fp_/',
					'find' => 'enquir', 'replace' => 'inquir',
					'note' => 'Change "enquiries" to "inquiries" in saved For Providers text.' ],
			],
		],
		[
			'id'      => 'alliance',
			'label'   => 'ElderCare Matters Alliance',
			'resolve' => [ 'template' => 'template-elder-care-alliance.php' ],
			'local_id' => 86409,
			'changes' => [
				[ 'id' => 'alc-reminder', 'section' => 'Wording', 'type' => 'meta_text_replace', 'key_regex' => '/^alc_/',
					'find' => 'when you get a Featured Listing on ElderCareMatters.com (details below)', 'replace' => 'when you upgrade to our top plan (details below)',
					'note' => 'No "Featured" as a public label for paid placement.' ],
				[ 'id' => 'alc-cta-sub', 'section' => 'Wording', 'type' => 'meta_text_replace', 'key_regex' => '/^alc_/',
					'find' => 'Get a Featured Listing to unlock free Alliance membership, priority placement, and more.', 'replace' => 'Upgrade your listing to unlock free Alliance membership, priority placement, and more.',
					'note' => 'No "Featured" as a public label for paid placement.' ],
			],
		],
		[
			'id'      => 'privacy',
			'label'   => 'Privacy Policy',
			'resolve' => [ 'slug' => [ 'privacy-policy', 'privacy' ] ],
			'local_id' => 9141,
			'changes' => [
				[ 'id' => 'privacy-body', 'section' => 'Page body', 'type' => 'content_set', 'bulk' => false,
					'html' => ecm_cc_read_html( 'privacy-policy-body.html' ),
					'note' => 'Replaces the whole Privacy Policy body with the current document (effective date and version already set to October 15, 2026). Saving archives a new version row.' ],
				[ 'id' => 'privacy-date', 'section' => 'Effective date', 'type' => 'content', 'bulk' => false, 'regex' => true, 'all' => true,
					'find' => '#\bOctober 1, 2026#u', 'replace' => 'October 15, 2026',
					'note' => 'Addendum 2: effective date is October 15, 2026.' ],
				[ 'id' => 'privacy-contact-pref', 'section' => 'Section 2 text wording', 'type' => 'content', 'bulk' => false, 'regex' => true,
					'find' => '#Contact preferences:[^<]*\btext\b[^<]*#iu',
					'replace' => 'Contact preferences: email and phone. We do not send text messages. See Section 6.',
					'note' => 'Addendum 1, Part 3 item 4: Section 2 must not list "text" as a contact preference.' ],
			],
		],
		[
			'id'      => 'chdn',
			'label'   => 'Consumer Health Data Notice',
			'resolve' => [ 'slug' => [ 'consumer-health-data-notice', 'consumer-health-data-privacy-notice' ] ],
			'local_id' => 284190,
			'changes' => [
				[ 'id' => 'chdn-date', 'section' => 'Effective date', 'type' => 'content', 'bulk' => false, 'regex' => true, 'all' => true,
					'find' => '#\bOctober 1, 2026#u', 'replace' => 'October 15, 2026',
					'note' => 'Addendum 2: effective date is October 15, 2026.' ],
				[ 'id' => 'chdn-impl-notes', 'type' => 'content', 'bulk' => false, 'regex' => true,
					'find' => '#<h[1-6][^>]*>\s*Implementation notes\s*</h[1-6]>.*$#isu',
					'replace' => '',
					'note' => 'Delete the "Implementation notes" section (for counsel and developer) before publishing.' ],
			],
		],
		[
			'id'       => 'findcare',
			'label'    => 'Find Care',
			'resolve'  => [ 'slug' => [ 'find-care' ] ],
			'local_id' => 283030,
			'changes'  => [
				[ 'id' => 'fc-title-prefix', 'section' => 'Headline', 'type' => 'meta', 'key' => 'srch_title_prefix', 'field_key' => 'field_ecm_srch_title_prefix',
					'match' => [ 'contains' => 'Find the right care' ],
					'to' => 'Find care for someone you love in',
					'note' => 'Headline becomes "Find care for someone you love in {city}."' ],
				[ 'id' => 'fc-sub', 'section' => 'Headline', 'type' => 'meta', 'key' => 'srch_sub', 'field_key' => 'field_ecm_srch_sub',
					'match' => [ 'contains' => 'Search verified elder care providers' ],
					'to' => 'Search verified providers by type of care, city, or name.',
					'note' => 'Subhead.' ],
				[ 'id' => 'fc-cb-title', 'section' => 'Bottom block', 'type' => 'meta', 'key' => 'srch_cb_title', 'field_key' => 'field_ecm_srch_cb_title',
					'match' => [ 'contains' => 'match you' ],
					'to' => 'Not sure who to pick?',
					'note' => 'Bottom block heading.' ],
				[ 'id' => 'fc-cb-sub', 'section' => 'Bottom block', 'type' => 'meta', 'key' => 'srch_cb_sub', 'field_key' => 'field_ecm_srch_cb_sub',
					'match' => [ 'contains' => 'hand-pick' ],
					'to' => "Answer a few questions and we'll show you verified providers near your loved one. You choose up to 10.",
					'note' => 'Bottom block text. Removes the old "up to 3" model.' ],
				[ 'id' => 'fc-cb-primary', 'section' => 'Bottom block', 'type' => 'meta', 'key' => 'srch_cb_primary', 'field_key' => 'field_ecm_srch_cb_primary',
					'match' => [ 'contains' => 'Start Your Free Match' ],
					'to' => '📋 Find Care Now →',
					'note' => 'Bottom block button.' ],
			],
		],
		[
			'id'      => 'about',
			'label'   => 'About Us',
			'resolve' => [ 'slug' => [ 'about', 'about-us' ] ],
			'local_id' => 63677,
			'changes' => [
				[ 'id' => 'about-seo-title', 'section' => 'SEO title', 'type' => 'meta', 'key' => '_yoast_wpseo_title', 'create' => true,
					'match' => [],
					'to' => 'About ElderCareMatters | Helping Families Find Elder Care Since 2002',
					'note' => 'Yoast SEO title. About and Contact doc, About Us problems table.' ],
				[ 'id' => 'about-body', 'type' => 'content_set', 'bulk' => false,
					'html' => ecm_cc_about_html(),
					'note' => 'Replaces the whole page body with the new About copy. The page title (SEO) is changed by hand: "About ElderCareMatters | Helping Families Find Elder Care Since 2002".' ],
			],
		],
		[
			'id'      => 'what-is',
			'label'   => 'What is Elder Care?',
			'resolve' => [ 'slug' => [ 'what-is-elder-care', 'elder-care' ] ],
			'local_id' => 74853,
			'changes' => [
				[ 'id' => 'wi-oldest', 'type' => 'content', 'regex' => true,
					'find' => '#America.s oldest and most respected( Elder Care)? Directory#u',
					'replace' => 'a directory of elder care providers',
					'note' => 'Remove the unsubstantiated claim. Review the sentence after applying.' ],
				[ 'id' => 'wi-qualified', 'type' => 'content', 'regex' => true,
					'find' => '#\bqualified local#iu',
					'replace' => 'local',
					'note' => 'ECM does not assess qualification.' ],
			],
		],
		[
			'id'      => 'terms',
			'label'   => 'Terms of Service',
			'resolve' => [ 'slug' => [ 'terms', 'terms-of-service' ] ],
			'local_id' => 34,
			'changes' => [
				[ 'id' => 'terms-date', 'section' => 'Effective date', 'type' => 'content', 'bulk' => false, 'regex' => true, 'all' => true,
					'find' => '#\bOctober 1, 2026#u', 'replace' => 'October 15, 2026',
					'note' => 'Addendum 2: effective date is October 15, 2026.' ],
				[ 'id' => 'terms-version', 'section' => 'Effective date', 'type' => 'content', 'bulk' => false, 'regex' => true, 'all' => true,
					'find' => '#2026-10-01#u', 'replace' => '2026-10-15',
					'note' => 'Addendum 2: version identifier is 2026-10-15.' ],
				[ 'id' => 'terms-updated', 'section' => 'Effective date', 'type' => 'content', 'bulk' => false, 'regex' => true,
					'find' => '#Last updated September 27, 2026#u', 'replace' => 'Last updated September 28, 2026',
					'note' => 'Addendum 2, Part 1: Last updated moves to September 28.' ],
				[ 'id' => 'terms-internal', 'section' => 'Internal notes', 'type' => 'content', 'bulk' => false, 'regex' => true,
					'find' => '#<h[1-6][^>]*>\s*Before publishing \(internal[^<]*</h[1-6]>.*?(?=<h[1-6][^>]*>)#isu',
					'replace' => '',
					'note' => 'Terms redline: delete the internal "Before publishing" block.' ],
				[ 'id' => 'terms-attorney-notes', 'section' => 'Internal notes', 'type' => 'content', 'bulk' => false, 'regex' => true,
					'find' => '#<h[1-6][^>]*>\s*Attorney notes\s*</h[1-6]>.*$#isu',
					'replace' => '',
					'note' => 'Terms redline: delete the "Attorney notes" section at the end.' ],
				[ 'id' => 'terms-e2', 'type' => 'content', 'bulk' => false, 'loose' => true,
					'find' => 'ElderCareMatters is an online directory and request service. Families search by care category and city, choose up to 10 providers per request (one category per request), and providers respond. We are not a care provider, home care agency, medical practice, law firm or financial adviser, and we give no medical, legal or financial advice. Providers are independent businesses. They are not our employees, agents or partners, and we do not supervise or control the services they deliver. In an emergency, call 911.',
					'replace' => "ElderCareMatters is an online directory and request service. Families search by care category and city, choose up to 10 providers per request (one category per request), and providers respond. We are not a care provider, home care agency, medical practice, law firm or financial adviser, and we give no medical, legal or financial advice.</p>\n<p>Providers are independent businesses. They are not our employees, agents, partners or joint venturers, and we do not supervise, direct or control the services they deliver. We are not a party to any agreement between you and a provider. Any contract for care is between you and that provider.</p>\n<p>In an emergency, call 911.",
					'note' => 'Terms redline, Edit 2 of 6. Saving archives a new version row.' ],
				[ 'id' => 'terms-e3', 'type' => 'content', 'bulk' => false, 'regex' => true,
					'find' => '#<p[^>]*>\s*\[Attorney: the checkout screen must satisfy ROSCA[^<]*</p>\s*#u',
					'replace' => '', 'note' => 'Terms redline, Edit 3 of 6: delete the editorial note in section 7.' ],
				[ 'id' => 'terms-e4', 'type' => 'content', 'bulk' => false, 'regex' => true,
					'find' => '#<p[^>]*>\s*\[Attorney: this section is what preserves[^<]*</p>\s*#u',
					'replace' => '', 'note' => 'Terms redline, Edit 4 of 6: delete the editorial note in section 8.' ],
				[ 'id' => 'terms-e5', 'type' => 'content', 'bulk' => false, 'regex' => true,
					'find' => '#<p[^>]*>\s*\[Verified September 25, 2026: the U\.S\. Copyright Office[^<]*</p>\s*#u',
					'replace' => '', 'note' => 'Terms redline, Edit 5 of 6: delete the verification note in section 10.' ],
				[ 'id' => 'terms-e6', 'type' => 'content', 'bulk' => false, 'loose' => true,
					'find' => "We give providers at least 30 days' email notice of material changes, which take effect at the next renewal. Continued use is acceptance. If a term is unenforceable, the rest stand. We may assign these Terms in a sale of the business. Notices go to Help@ElderCareMatters.com.",
					'replace' => ecm_cc_terms_section_17(),
					'note' => 'Terms redline, Edit 6 of 6: section 17 becomes 17.1 to 17.8.' ],
			],
		],
	];
}

/** HTML body kept in this folder (generated from the shared document). */
function ecm_cc_read_html( string $file ) : string {
	$path = __DIR__ . '/' . $file;
	return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
}

function ecm_cc_about_html() : string {
	return '<h2>Helping families find elder care since 2002</h2>' . "\n"
		. '<p>When a parent falls or gets a hard diagnosis, families often need home care, a lawyer or a memory care community fast, sometimes in a city where they don\'t live. ElderCareMatters helps families find verified local providers and reach them on their own terms.</p>' . "\n"
		. '<h2>How it works</h2>' . "\n"
		. '<ul>' . "\n"
		. '<li><strong>Search by need and city.</strong> Care is sorted into 8 categories, from in-home care to legal and estate planning.</li>' . "\n"
		. '<li><strong>Choose up to 10 providers.</strong> You decide who hears from you.</li>' . "\n"
		. '<li><strong>Your details stay private.</strong> Your phone and email stay hidden until a provider asks to help with your request.</li>' . "\n"
		. '</ul>' . "\n"
		. '<p><strong>Free for families.</strong> Families never pay. Providers pay for listing plans, which keeps the site running.</p>' . "\n"
		. '<p><strong>For providers:</strong> If your work helps older adults and their families, <a href="/for-providers/">see how listing works</a>.</p>' . "\n"
		. '<p>Questions? Email <a href="mailto:Help@ElderCareMatters.com">Help@ElderCareMatters.com</a>.</p>' . "\n"
		. '<p><a class="btn-primary-lg" href="/find-care/">Find Care Now</a></p>';
}

function ecm_cc_terms_section_17() : string {
	$p = [
		'17.1 Changes. We may update these Terms. Each version carries an effective date and a version identifier.',
		'For providers, we give at least 30 days\' email notice of a material change, and it takes effect at the next renewal.',
		'For families, we post the updated Terms with the new effective date. The version in force when you submit a request governs that request. Continued use of the site after the effective date is acceptance of the updated Terms.',
		'17.2 Changes to Section 16. A material change to Section 16 does not apply to any dispute that arose before that change\'s effective date. A material change to Section 16 binds you only when you accept the updated Terms, which happens when you submit a new request after its effective date.',
		'17.3 Severability. If any provision of these Terms is unenforceable, it is severed and the remainder stays in effect.',
		'17.4 Assignment. We may assign these Terms, in whole or in part, in connection with a merger, acquisition or sale of the business or its assets. You may not assign them without our written consent.',
		'17.5 No third-party beneficiaries. These Terms are for the benefit of you and ElderCare Matters, LLC only. No provider has any right under a provision written for families, and no family has any right under a provision written for providers, except where a provision says otherwise in its own words. No other person has any right under these Terms.',
		'17.6 Entire agreement. These Terms, together with the Privacy Policy and any plan terms shown at checkout, are the entire agreement between you and us about the service, and they supersede any earlier version.',
		'17.7 Notices. Notices to us go to Help@ElderCareMatters.com, or by mail to ElderCare Matters, LLC, 630 E Fort King St, #48, Ocala, FL 34471. Notices to you go to the email address on your request or your provider account.',
		'17.8 Survival. Sections 13, 14, 15 and 16 survive termination of these Terms and the closing of any account.',
	];
	return implode( "</p>\n<p>", $p );
}

/* ───────────────────────── Resolving pages ───────────────────────── */

/** Every post type that can hold content (pages, posts, job_listing and any other registered type). */
function ecm_cc_post_types() : array {
	$types = array_keys( get_post_types( [], 'names' ) );
	return array_values( array_diff( $types, [ 'revision', 'nav_menu_item', 'attachment', 'customize_changeset', 'oembed_cache', 'user_request', 'acf-field', 'acf-field-group' ] ) );
}

/** @return WP_Post[] */
function ecm_cc_resolve( array $resolve ) : array {
	global $wpdb;
	$found = [];

	if ( ! empty( $resolve['front'] ) ) {
		$id = (int) get_option( 'page_on_front' );
		$p  = $id ? get_post( $id ) : null;
		return $p ? [ $p ] : [];
	}
	if ( ! empty( $resolve['slug'] ) ) {
		foreach ( (array) $resolve['slug'] as $slug ) {
			$p = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $p ) {
				return [ $p ];
			}
		}
		return [];
	}
	if ( ! empty( $resolve['template'] ) ) {
		$found = array_merge( $found, get_posts( [
			'post_type'   => ecm_cc_post_types(),
			'post_status' => [ 'publish', 'draft', 'private' ],
			'numberposts' => 100,
			'meta_key'    => '_wp_page_template',
			'meta_value'  => $resolve['template'],
			'orderby'     => 'ID',
			'order'       => 'ASC',
		] ) );
	}
	if ( ! empty( $resolve['shop'] ) && function_exists( 'wc_get_page_id' ) ) {
		// Plans & Pricing is the WooCommerce Shop page; its own template meta
		// is "default", so the template lookup above can't find it.
		$shop = get_post( (int) wc_get_page_id( 'shop' ) );
		if ( $shop && 'trash' !== $shop->post_status ) {
			$found[] = $shop;
		}
	}
	if ( ! empty( $resolve['has_meta'] ) ) {
		$keys = array_values( (array) $resolve['has_meta'] );
		$ph   = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$ids  = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ($ph) LIMIT 100", $keys ) );
		foreach ( $ids as $id ) {
			$p = get_post( (int) $id );
			if ( $p && 'trash' !== $p->post_status && 'revision' !== $p->post_type ) {
				$found[] = $p;
			}
		}
	}
	$out = [];
	foreach ( $found as $p ) {
		$out[ $p->ID ] = $p;
	}
	return array_values( $out );
}

/** Pages for a group: the built-in lookup plus any page added by hand. */
function ecm_cc_group_posts( array $page ) : array {
	$posts = ecm_cc_resolve( $page['resolve'] );
	$have  = wp_list_pluck( $posts, 'ID' );
	$extra = (array) ( get_option( ECM_CC_TARGETS, [] )[ $page['id'] ] ?? [] );
	foreach ( $extra as $id ) {
		$p = get_post( (int) $id );
		if ( $p && ! in_array( $p->ID, $have, true ) && 'trash' !== $p->post_status ) {
			$posts[] = $p;
			$have[]  = $p->ID;
		}
	}
	return $posts;
}

/** Where a field's built-in default text lives in the theme (shown for groups with no saved page). */
function ecm_cc_default_source( string $key ) : string {
	$map = [
		'catp_'  => 'inc/acf-category.php',
		'bd_cb_' => 'inc/acf-blog-detail.php',
		'blog_'  => 'inc/acf-blog.php',
		'srch_'  => 'inc/acf-search.php',
		'pl_'    => 'inc/acf-plans-pricing.php',
		'fp_'    => 'inc/acf-for-providers.php',
	];
	foreach ( $map as $prefix => $file ) {
		if ( 0 === strpos( $key, $prefix ) ) {
			return $file;
		}
	}
	return 'inc/acf-fields.php';
}

/**
 * Shown after each group. When no page was found it lists what the group asks
 * for (field, old text, new text) so it can be done by hand or in code, and
 * says what is missing. Nothing is guessed or replaced on a similar page.
 */
function ecm_cc_render_add_box( array $page, array $posts, string $url ) : void {
	if ( ! empty( $posts ) ) {
		return;
	}
	$tpl = $page['resolve']['template'] ?? '';
	echo '<div style="background:#fff;border:1px dashed #b91c1c;margin:0 0 1.25rem;max-width:1100px;padding:.75rem 1rem">';
	echo '<strong>' . esc_html( $page['label'] ) . '</strong> <span style="color:#b91c1c">No page found: nothing can be applied from here.</span>';
	echo '<p style="margin:.5rem 0">What is missing: ';
	if ( $tpl ) {
		echo 'no page on this site uses the <code>' . esc_html( $tpl ) . '</code> template, so none of these fields has a saved value. ';
	} else {
		echo 'no page was found for this group. ';
	}
	global $wpdb;
	$keys = [];
	foreach ( $page['changes'] as $c ) {
		if ( ! empty( $c['key'] ) ) {
			$keys[] = $c['key'];
		}
	}
	if ( $keys ) {
		$ph      = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$n_post  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN ($ph)", $keys ) );
		$n_term  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_key IN ($ph)", $keys ) );
		$n_terms = taxonomy_exists( 'job_listing_category' ) ? (int) wp_count_terms( [ 'taxonomy' => 'job_listing_category', 'hide_empty' => false ] ) : 0;
		echo '<p style="margin:.5rem 0">Checked every post type (' . esc_html( implode( ', ', ecm_cc_post_types() ) ) . '). Saved values for these fields: ' . $n_post . ' in post meta, ' . $n_term . ' in term meta. Job listing category terms on this site: ' . $n_terms . '.</p>';
	}
	echo 'The text shown on the site comes from the theme default listed below. Change it in the theme file, or assign the template to a page and run the tool again.</p>';

	echo '<table class="widefat striped"><thead><tr><th>Change</th><th>Field</th><th>Default lives in</th><th>Expected old text</th><th>New text</th></tr></thead><tbody>';
	foreach ( $page['changes'] as $c ) {
		$key = (string) ( $c['key'] ?? $c['base'] ?? '' );
		$old = $c['match']['contains'] ?? $c['match']['equals'] ?? '';
		$new = $c['to'] ?? ( isset( $c['replace'] ) ? $c['replace'] : '' );
		echo '<tr><td><code>' . esc_html( $c['id'] ) . '</code><br><small>' . esc_html( $c['note'] ?? '' ) . '</small>' . ( ecm_cc_source( $c ) ? '<br><small><em>Source: ' . esc_html( ecm_cc_source( $c ) ) . '</em></small>' : '' ) . '</td>';
		echo '<td><code>' . esc_html( $key ) . '</code></td>';
		echo '<td>' . esc_html( $key ? ecm_cc_default_source( $key ) : '' ) . '</td>';
		echo '<td>' . esc_html( (string) $old ) . '</td>';
		echo '<td>' . esc_html( wp_strip_all_tags( (string) $new ) ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
}

/* ───────────────────────── Evaluating a change ───────────────────────── */

function ecm_cc_matches( $current, array $match ) : bool {
	$current = (string) $current;
	if ( isset( $match['equals'] ) ) {
		return trim( $current ) === (string) $match['equals'];
	}
	if ( isset( $match['contains'] ) ) {
		return false !== stripos( $current, (string) $match['contains'] );
	}
	return true;
}

function ecm_cc_all_meta( int $post_id ) : array {
	$out = [];
	foreach ( (array) get_post_meta( $post_id ) as $k => $v ) {
		$out[ $k ] = is_array( $v ) ? ( $v[0] ?? '' ) : $v;
	}
	return $out;
}

/**
 * Work out what a change would do on a post, without writing anything.
 *
 * @return array{status:string,current:string,new:string,ops:array,message:string}
 *   status: pending | applied | review | missing
 *   ops:    ['meta'=>[key=>value|null], 'content'=>string|null, 'delete'=>[keys]]
 */
function ecm_cc_evaluate( array $c, WP_Post $post ) : array {
	$res = [ 'status' => 'missing', 'current' => '', 'new' => '', 'ops' => [], 'message' => '' ];
	$id  = $post->ID;
	$all = ecm_cc_all_meta( $id );
	$type = $c['type'];

	if ( 'meta' === $type ) {
		$key = $c['key'];
		$res['new'] = (string) $c['to'];
		if ( ! array_key_exists( $key, $all ) ) {
			if ( ! empty( $c['create'] ) ) {
				$res['status']  = 'pending';
				$res['current'] = '(not set: the default title pattern is used)';
				$res['ops']     = [ 'meta' => [ $key => $c['to'] ] ];
				return $res;
			}
			$res['message'] = 'Not saved on this page: the theme default applies, nothing to change.';
			return $res;
		}
		$cur = (string) $all[ $key ];
		$res['current'] = $cur;
		if ( $cur === $c['to'] || trim( wp_strip_all_tags( $cur ) ) === trim( wp_strip_all_tags( (string) $c['to'] ) ) ) {
			$res['status'] = 'applied';
		} elseif ( ecm_cc_matches( $cur, $c['match'] ?? [] ) ) {
			$res['status'] = 'pending';
			$res['ops']    = [ 'meta' => [ $key => $c['to'] ] ];
		} else {
			$res['status']  = 'review';
			$res['message'] = 'Saved value is different from the expected old value. Edit by hand if needed.';
		}
		return $res;
	}

	if ( 'meta_scan' === $type ) {
		$res['new'] = (string) $c['to'];
		$targets = [];
		$done    = 0;
		foreach ( $all as $k => $v ) {
			if ( ! preg_match( $c['key_regex'], $k ) ) {
				continue;
			}
			if ( ecm_cc_matches( $v, $c['match'] ?? [] ) && (string) $v !== (string) $c['to'] ) {
				$targets[ $k ] = $v;
			} elseif ( (string) $v === (string) $c['to'] ) {
				$done++;
			}
		}
		if ( $targets ) {
			$ops = [];
			foreach ( $targets as $k => $v ) {
				$ops[ $k ] = $c['to'];
				foreach ( (array) ( $c['siblings'] ?? [] ) as $sub => $val ) {
					$ops[ preg_replace( '/_[A-Za-z0-9]+$/', '_' . $sub, $k ) ] = $val;
				}
			}
			$res['status']  = 'pending';
			$res['current'] = implode( ' | ', array_map( 'strval', array_values( $targets ) ) );
			$res['ops']     = [ 'meta' => $ops ];
			$res['message'] = count( $targets ) . ' value(s): ' . implode( ', ', array_keys( $targets ) );
		} elseif ( $done ) {
			$res['status']  = 'applied';
			$res['current'] = (string) $c['to'];
		} else {
			$res['message'] = 'No matching saved value: already changed, not saved, or different text.';
		}
		return $res;
	}

	if ( 'repeater_remove' === $type ) {
		$base  = $c['base'];
		$count = (int) ( $all[ $base ] ?? 0 );
		$sub   = $c['match_sub'];
		$res['new'] = 'Row removed';
		$hit = [];
		for ( $i = 0; $i < $count; $i++ ) {
			$k = $base . '_' . $i . '_' . $sub;
			if ( isset( $all[ $k ] ) && ecm_cc_matches( $all[ $k ], $c['match'] ) ) {
				$hit[] = $i;
			}
		}
		if ( ! $hit ) {
			$res['message'] = 'No such row saved: already removed or not present.';
			$res['status']  = $count ? 'applied' : 'missing';
			return $res;
		}
		$res['status']  = 'pending';
		$res['current'] = 'Row ' . implode( ', ', $hit ) . ' of ' . $count . ': ' . (string) $all[ $base . '_' . $hit[0] . '_' . $sub ];
		$res['ops']     = [ 'meta' => ecm_cc_repeater_without( $all, $base, $count, $hit ), 'group' => $base ];
		return $res;
	}

	if ( 'meta_text_replace' === $type ) {
		$ops = [];
		$cur = '';
		$new = '';
		foreach ( $all as $k => $v ) {
			if ( ! preg_match( $c['key_regex'], $k ) || ! is_string( $v ) || '' === $v ) {
				continue;
			}
			if ( false !== stripos( $v, $c['find'] ) ) {
				$ops[ $k ] = str_ireplace( $c['find'], $c['replace'], $v );
				if ( '' === $cur ) {
					$cur = $v;
					$new = $ops[ $k ];
				}
			}
		}
		$res['current'] = $cur;
		$res['new']     = $new ?: (string) $c['replace'];
		if ( $ops ) {
			$res['status']  = 'pending';
			$res['message'] = count( $ops ) . ' value(s): ' . implode( ', ', array_slice( array_keys( $ops ), 0, 6 ) );
			$res['ops']     = [ 'meta' => $ops ];
		} else {
			$res['message'] = 'Text not found in saved values: already changed or not saved.';
		}
		return $res;
	}

	if ( 'link_url' === $type ) {
		$ops = [];
		$res['new'] = (string) $c['url'];
		foreach ( $all as $k => $v ) {
			if ( preg_match( '/^footer_columns_\d+_links_\d+_text$/', $k ) && trim( (string) $v ) === $c['text'] ) {
				$uk = preg_replace( '/_text$/', '_url', $k );
				$cu = (string) ( $all[ $uk ] ?? '' );
				$res['current'] = $cu;
				if ( $cu === $c['url'] ) {
					$res['status'] = 'applied';
				} else {
					$ops[ $uk ] = $c['url'];
				}
			}
		}
		if ( $ops ) {
			$res['status'] = 'pending';
			$res['ops']    = [ 'meta' => $ops ];
		} elseif ( 'applied' !== $res['status'] ) {
			$res['message'] = 'Link not found in the footer.';
		}
		return $res;
	}

	if ( 'footer_link_remove' === $type ) {
		$res['new'] = 'Link removed';
		$cols = (int) ( $all['footer_columns'] ?? 0 );
		for ( $i = 0; $i < $cols; $i++ ) {
			$n = (int) ( $all[ "footer_columns_{$i}_links" ] ?? 0 );
			for ( $j = 0; $j < $n; $j++ ) {
				$t = (string) ( $all[ "footer_columns_{$i}_links_{$j}_text" ] ?? '' );
				if ( '' !== $t && false !== stripos( $t, $c['text_contains'] ) ) {
					$res['status']  = 'pending';
					$res['current'] = '"' . $t . '" in column ' . (string) ( $all[ "footer_columns_{$i}_title" ] ?? $i );
					$res['ops']     = [ 'meta' => ecm_cc_repeater_without( $all, "footer_columns_{$i}_links", $n, [ $j ] ), 'group' => 'footer_columns' ];
					return $res;
				}
			}
		}
		$res['status']  = 'applied';
		$res['message'] = 'Link not in the footer: already removed or not present.';
		return $res;
	}

	if ( 'footer_move' === $type ) {
		$res['new'] = 'Link moved to the ' . $c['to_column'] . ' column';
		$cols = (int) ( $all['footer_columns'] ?? 0 );
		$from = $to = null;
		for ( $i = 0; $i < $cols; $i++ ) {
			$t = (string) ( $all[ "footer_columns_{$i}_title" ] ?? '' );
			if ( 0 === strcasecmp( $t, $c['from_column'] ) ) {
				$from = $i;
			}
			if ( 0 === strcasecmp( $t, $c['to_column'] ) ) {
				$to = $i;
			}
		}
		if ( null === $from ) {
			$res['status']  = 'applied';
			$res['message'] = 'No "' . $c['from_column'] . '" column in the footer: the link is not there.';
			return $res;
		}
		if ( null === $to ) {
			$res['message'] = 'The "' . $c['to_column'] . '" footer column was not found, so the link cannot be moved here.';
			return $res;
		}
		$n_from = (int) ( $all[ "footer_columns_{$from}_links" ] ?? 0 );
		$idx = null;
		for ( $j = 0; $j < $n_from; $j++ ) {
			if ( false !== stripos( (string) ( $all[ "footer_columns_{$from}_links_{$j}_text" ] ?? '' ), $c['text_contains'] ) ) {
				$idx = $j;
			}
		}
		if ( null === $idx ) {
			$res['message'] = 'Link not in the ' . $c['from_column'] . ' column: already moved or not present.';
			$res['status']  = 'applied';
			return $res;
		}
		$text = (string) $all[ "footer_columns_{$from}_links_{$idx}_text" ];
		$url  = (string) ( $all[ "footer_columns_{$from}_links_{$idx}_url" ] ?? '' );
		$ops  = ecm_cc_repeater_without( $all, "footer_columns_{$from}_links", $n_from, [ $idx ] );
		$n_to = (int) ( $all[ "footer_columns_{$to}_links" ] ?? 0 );
		$ops[ "footer_columns_{$to}_links" ]                 = $n_to + 1;
		$ops[ "footer_columns_{$to}_links_{$n_to}_text" ]    = $text;
		$ops[ "footer_columns_{$to}_links_{$n_to}_url" ]     = $url;
		$res['status']  = 'pending';
		$res['current'] = '"' . $text . '" in ' . $c['from_column'];
		$res['ops']     = [ 'meta' => $ops, 'group' => 'footer_columns' ];
		return $res;
	}

	if ( 'content' === $type || 'content_set' === $type ) {
		$body = (string) $post->post_content;
		$res['current'] = wp_strip_all_tags( $body );
		if ( 'content_set' === $type ) {
			$res['new'] = wp_strip_all_tags( $c['html'] );
			if ( trim( $body ) === trim( $c['html'] ) ) {
				$res['status'] = 'applied';
			} else {
				$res['status']  = 'pending';
				$res['message'] = 'Replaces the whole page body (' . strlen( $body ) . ' characters now).';
				$res['ops']     = [ 'content' => $c['html'] ];
			}
			return $res;
		}
		$replace = (string) $c['replace'];
		$regex   = null;
		if ( ! empty( $c['regex'] ) ) {
			$regex = $c['find'];
		} elseif ( ! empty( $c['loose'] ) ) {
			$q     = preg_quote( $c['find'], '#' );
			$q     = str_replace( [ ' ', "'" ], [ '\s+', "['’]" ], $q );
			$regex = '#' . $q . '#u';
		}
		$res['new'] = '' === $replace ? '(deleted)' : wp_strip_all_tags( $replace );
		if ( null !== $regex ) {
			if ( preg_match( $regex, $body, $m ) ) {
				$after = preg_replace_callback( $regex, static function () use ( $replace ) { return $replace; }, $body, ! empty( $c['all'] ) ? -1 : 1 );
				$res['current'] = wp_strip_all_tags( $m[0] );
				if ( $after === $body ) {
					$res['status']  = 'applied';
					$res['message'] = 'Already reads this way.';
					return $res;
				}
				$res['status'] = 'pending';
				$res['ops']    = [ 'content' => $after ];
				return $res;
			}
		} elseif ( false !== strpos( $body, $c['find'] ) ) {
			$res['status']  = 'pending';
			$res['current'] = wp_strip_all_tags( $c['find'] );
			$res['ops']     = [ 'content' => str_replace( $c['find'], $replace, $body ) ];
			return $res;
		}
		$plain_body = preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $body ), ENT_QUOTES, 'UTF-8' ) );
		$plain_new  = preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $replace ), ENT_QUOTES, 'UTF-8' ) );
		$probe      = trim( mb_substr( $plain_new, 0, 60 ) );
		if ( '' !== $replace && ( false !== strpos( $body, $replace ) || ( '' !== $probe && false !== mb_stripos( $plain_body, $probe ) ) ) ) {
			$res['status']  = 'applied';
			$res['message'] = 'The new text is already on the page.';
		} elseif ( '' === $replace ) {
			$res['status']  = 'applied';
			$res['message'] = 'Not in the page body: already removed (or the wording differs).';
		} else {
			$res['message'] = 'Text not found in the page body: already changed, or the wording differs.';
		}
		return $res;
	}

	return $res;
}

/** Repeater meta after removing rows; keys that no longer exist map to null. */
function ecm_cc_repeater_without( array $all, string $base, int $count, array $remove ) : array {
	$rows = [];
	$subs = [];
	for ( $i = 0; $i < $count; $i++ ) {
		if ( in_array( $i, $remove, true ) ) {
			continue;
		}
		$row = [];
		foreach ( $all as $k => $v ) {
			if ( preg_match( '/^' . preg_quote( $base, '/' ) . '_' . $i . '_(.+)$/', $k, $m ) ) {
				$row[ $m[1] ] = $v;
				$subs[ $m[1] ] = true;
			}
		}
		$rows[] = $row;
	}
	$ops = [];
	// Clear every old row key, then write the survivors renumbered.
	foreach ( $all as $k => $v ) {
		if ( preg_match( '/^' . preg_quote( $base, '/' ) . '_\d+_/', $k ) ) {
			$ops[ $k ] = null;
		}
	}
	foreach ( $rows as $n => $row ) {
		foreach ( $row as $sub => $val ) {
			$ops[ $base . '_' . $n . '_' . $sub ] = $val;
		}
	}
	// Reference rows ("_base_i_sub") for surviving rows.
	$kept = array_values( array_diff( range( 0, max( 0, $count - 1 ) ), $remove ) );
	foreach ( $kept as $n => $old ) {
		foreach ( array_keys( $subs ) as $sub ) {
			$ref_old = '_' . $base . '_' . $old . '_' . $sub;
			if ( isset( $all[ $ref_old ] ) ) {
				$ops[ '_' . $base . '_' . $n . '_' . $sub ] = $all[ $ref_old ];
			}
		}
	}
	// Order matters: nulls first (done above by assignment order), count last.
	$ops[ $base ] = count( $rows );
	return $ops;
}

/* ───────────────────────── Applying and reverting ───────────────────────── */

function ecm_cc_find_change( string $change_id ) : ?array {
	foreach ( ecm_cc_definitions() as $page ) {
		foreach ( $page['changes'] as $c ) {
			if ( $c['id'] === $change_id ) {
				return $c;
			}
		}
	}
	return null;
}

function ecm_cc_backup_key( string $change_id, int $post_id ) : string {
	return $change_id . '|' . $post_id;
}

function ecm_cc_log( string $action, int $post_id, string $change_id, string $detail = '' ) : void {
	$log = get_option( ECM_CC_LOG, [] );
	if ( ! is_array( $log ) ) {
		$log = [];
	}
	$user = wp_get_current_user();
	$log[] = [
		't'      => gmdate( 'Y-m-d H:i:s' ),
		'user'   => $user ? $user->user_login : '',
		'action' => $action,
		'post'   => $post_id,
		'change' => $change_id,
		'detail' => $detail,
	];
	update_option( ECM_CC_LOG, $log, false );
}

function ecm_cc_apply( array $c, WP_Post $post ) : string {
	$ev = ecm_cc_evaluate( $c, $post );
	if ( 'pending' !== $ev['status'] ) {
		return 'skipped (' . $ev['status'] . ')';
	}
	$backups = get_option( ECM_CC_BACKUP, [] );
	$bkey    = ecm_cc_backup_key( $c['id'], $post->ID );
	if ( isset( $backups[ $bkey ] ) ) {
		return 'skipped (already applied, previous value still stored: revert it first)';
	}
	$rec = [ 'post' => $post->ID, 'change' => $c['id'], 'time' => gmdate( 'Y-m-d H:i:s' ), 'meta_before' => [], 'meta_after' => [], 'touched' => [] ];

	if ( isset( $ev['ops']['content'] ) ) {
		$rec['content_before'] = (string) $post->post_content;
		$rec['content_after']  = (string) $ev['ops']['content'];
		$r = wp_update_post( [ 'ID' => $post->ID, 'post_content' => wp_slash( $ev['ops']['content'] ) ], true );
		if ( is_wp_error( $r ) ) {
			return 'failed: ' . $r->get_error_message();
		}
	}

	if ( isset( $ev['ops']['meta'] ) ) {
		$all     = ecm_cc_all_meta( $post->ID );
		$ops     = $ev['ops']['meta'];
		$touched = array_keys( $ops );
		if ( ! empty( $ev['ops']['group'] ) ) {
			// Everything under the repeater prefix may be rewritten.
			$grp = preg_quote( $ev['ops']['group'], '/' );
			foreach ( $all as $k => $v ) {
				if ( preg_match( '/^_?' . $grp . '(_|$)/', $k ) ) {
					$touched[] = $k;
				}
			}
			$touched = array_values( array_unique( $touched ) );
		}
		foreach ( $touched as $k ) {
			if ( array_key_exists( $k, $all ) ) {
				$rec['meta_before'][ $k ] = $all[ $k ];
			}
		}
		// Rewrite: clear nulls, then write values.
		foreach ( $ops as $k => $v ) {
			if ( null === $v ) {
				delete_post_meta( $post->ID, $k );
			}
		}
		foreach ( $ops as $k => $v ) {
			if ( null !== $v ) {
				update_post_meta( $post->ID, $k, $v );
				if ( isset( $c['field_key'] ) && $k === ( $c['key'] ?? '' ) && ! metadata_exists( 'post', $post->ID, '_' . $k ) ) {
					update_post_meta( $post->ID, '_' . $k, $c['field_key'] );
					$touched[] = '_' . $k;
				}
			}
		}
		$after = ecm_cc_all_meta( $post->ID );
		foreach ( array_unique( $touched ) as $k ) {
			$rec['touched'][] = $k;
			if ( array_key_exists( $k, $after ) ) {
				$rec['meta_after'][ $k ] = $after[ $k ];
			}
		}
	}

	$backups[ $bkey ] = $rec;
	update_option( ECM_CC_BACKUP, $backups, false );
	$what = [];
	if ( isset( $rec['content_after'] ) ) {
		$what[] = 'post_content';
	}
	foreach ( (array) $rec['touched'] as $k ) {
		if ( ( $rec['meta_before'][ $k ] ?? null ) !== ( $rec['meta_after'][ $k ] ?? null ) ) {
			$what[] = $k;
		}
	}
	$what = array_slice( array_unique( $what ), 0, 12 );
	ecm_cc_log( 'apply', $post->ID, $c['id'], 'changed: ' . implode( ', ', $what ) );
	return 'applied (' . implode( ', ', $what ) . ')';
}

function ecm_cc_revert( array $c, WP_Post $post ) : string {
	$backups = get_option( ECM_CC_BACKUP, [] );
	$bkey    = ecm_cc_backup_key( $c['id'], $post->ID );
	if ( ! isset( $backups[ $bkey ] ) ) {
		return 'skipped (no stored previous value)';
	}
	$rec = $backups[ $bkey ];

	// Refuse when someone edited the value after the apply.
	if ( isset( $rec['content_after'] ) ) {
		$fresh = get_post( $post->ID );
		if ( trim( (string) $fresh->post_content ) !== trim( (string) $rec['content_after'] ) ) {
			return 'skipped (page body changed since the apply)';
		}
	}
	$all = ecm_cc_all_meta( $post->ID );
	foreach ( (array) $rec['touched'] as $k ) {
		$now  = array_key_exists( $k, $all ) ? (string) $all[ $k ] : null;
		$want = array_key_exists( $k, $rec['meta_after'] ) ? (string) $rec['meta_after'][ $k ] : null;
		if ( $now !== $want ) {
			return 'skipped (' . $k . ' changed since the apply)';
		}
	}

	if ( isset( $rec['content_before'] ) ) {
		$r = wp_update_post( [ 'ID' => $post->ID, 'post_content' => wp_slash( $rec['content_before'] ) ], true );
		if ( is_wp_error( $r ) ) {
			return 'failed: ' . $r->get_error_message();
		}
	}
	foreach ( (array) $rec['touched'] as $k ) {
		if ( array_key_exists( $k, $rec['meta_before'] ) ) {
			update_post_meta( $post->ID, $k, $rec['meta_before'][ $k ] );
		} else {
			delete_post_meta( $post->ID, $k );
		}
	}
	unset( $backups[ $bkey ] );
	update_option( ECM_CC_BACKUP, $backups, false );
	ecm_cc_log( 'revert', $post->ID, $c['id'], 'ok' );
	return 'reverted';
}

/** Document and section a change comes from, shown on the screen for cross-checking. */
function ecm_cc_source( array $c ) : string {
	if ( ! empty( $c['doc'] ) ) {
		return (string) $c['doc'];
	}
	$id = $c['id'];
	$exact = [
		'home-strip'          => 'Remaining Pages Changes → Sitewide #1 (header strip), checklist item 107',
		'home-hero-sub'       => 'Developer Build Checklist item 75 (outranks Homepage Changes → Page copy table, "Hero subhead")',
		'home-footer-tagline' => 'Remaining Pages Changes → Sitewide #3 (footer tagline), checklist item 108',
		'home-footer-badge-verified' => 'Remaining Pages Changes → Sitewide #2 (footer trust row), checklist item 108',
		'home-footer-badge-privacy'  => 'Remaining Pages Changes → Sitewide #2 (footer trust row), checklist item 108',
		'home-footer-plans-text'     => 'Remaining Pages Changes → Sitewide #5 ("Plans & Benefits"), checklist item 116',
		'home-chat-advisor-name' => 'Developer Build Checklist item 79 (no "advisor" in ECM\'s own voice); Homepage Changes → advisor audit',
		'fc-title-prefix'     => 'Find Care Changes → Page copy table, "Headline"',
		'fc-sub'              => 'Find Care Changes → Page copy table, "Subhead"',
		'fc-cb-title'         => 'Find Care Changes → Page copy table, "Bottom block heading"',
		'fc-cb-sub'           => 'Find Care Changes → Page copy table, "Bottom block text"',
		'fc-cb-primary'       => 'Find Care Changes → Page copy table, "Bottom block button"',
		'home-footer-plans-url' => 'Developer Build Checklist items 68 and 116, Remaining Pages Changes → Sitewide #5',
		'home-footer-alliance'       => 'Remaining Pages Changes → Page 3 (Alliance), "Move to For Providers", checklist item 115',
		'cat-cta-title'       => 'Category Pages Changes → Page copy table, "Bottom block"',
		'cat-cta-sub'         => 'Category Pages Changes → Page copy table, "Bottom block"; advisor audit',
		'cat-providers-sub'   => 'Category Pages Changes → Page copy table, "List subhead"',
		'art-cta-title'       => 'Homepage Changes → "The advisor audit" #4 (sitewide CTA template)',
		'art-cta-sub'         => 'Homepage Changes → "The advisor audit" #4 (sitewide CTA template)',
		'blog-grid-sub'       => 'Resources Changes → Resources page → Copy table, "Subhead"',
		'blog-seo-title'      => 'Resources Changes → Resources page → Copy table, "Page title"',
		'pl-hero-sub'         => 'Provider Pages Changes → Plans & Pricing page → Copy changes, "Intro"',
		'pl-seo-title'        => 'Provider Pages Changes → Plans & Pricing page → Copy changes, "Page title"',
		'pl-faq-answer'       => 'Provider Pages Changes → Plans & Pricing page → FAQ "When do I receive new family inquiries?"',
		'pl-t-label'          => 'Provider Pages Changes → Copy changes, "Compare table row label"',
		'pl-t-cities'         => 'Provider Pages Changes → "City coverage and add-on pricing"',
		'pl-t-badge'          => 'Provider Pages Changes → Fixes, "Premium includes a Featured badge"',
		'pl-enquiries'        => 'Provider Pages Changes → Fixes, "enquiries" to "inquiries"; Remaining Pages item 104',
		'fp-hero-sub1'        => 'Provider Pages Changes → For Providers page → table, "Hero subhead"',
		'fp-hero-sub2'        => 'Provider Pages Changes → For Providers page → table, "Hero second line"',
		'fp-form-title'       => 'Provider Pages Changes → "Provider request form" table, heading',
		'fp-enquiries'        => 'Provider Pages Changes → Fixes, "enquiries" to "inquiries"',
		'chdn-impl-notes'     => 'Consumer Health Data Notice → "Implementation notes" (delete before publishing)',
		'loc-verified'        => 'Remaining Pages Changes → "Two sentences to delete" #1, checklist item 105',
		'loc-verified-def'    => 'Remaining Pages Changes → "Two sentences to delete" #1, checklist item 105',
		'about-body'          => 'About and Contact Changes → About Us → "New page"',
		'about-seo-title'     => 'About and Contact Changes → About Us → Problems table, "Page title"',
		'wi-oldest'           => 'Remaining Pages Changes → Page 1 (What is Elder Care?) table, first row',
		'wi-qualified'        => 'Remaining Pages Changes → Page 1 (What is Elder Care?) table, "qualified"',
		'terms-e2'            => 'Terms of Service Finalization Redline → Edit 2 of 6 (Section 2)',
		'terms-e3'            => 'Terms of Service Finalization Redline → Edit 3 of 6 (Section 7)',
		'terms-e4'            => 'Terms of Service Finalization Redline → Edit 4 of 6 (Section 8)',
		'terms-e5'            => 'Terms of Service Finalization Redline → Edit 5 of 6 (Section 10)',
		'terms-e6'            => 'Terms of Service Finalization Redline → Edit 6 of 6 (Section 17)',
		'terms-internal'      => 'Terms of Service Finalization Redline → Edit 1 and "Before the page goes live"',
		'terms-attorney-notes' => 'Terms of Service Finalization Redline → "Before the page goes live"',
		'terms-date'          => 'Addendum 2 → Part 1, Terms of Service Draft Section 1 and redline header',
		'terms-version'       => 'Addendum 2 → Part 2, version identifier 2026-10-15',
		'terms-updated'       => 'Addendum 2 → Part 1, "Last updated" moves to September 28',
		'chdn-date'           => 'Addendum 2 → Part 1, Consumer Health Data Notice (line under the title)',
		'privacy-body'        => 'ElderCareMatters Privacy Policy (2) → full text; Addendum 2 dates; Addendum 1 Part 3 item 4',
		'privacy-date'        => 'Addendum 2 → Part 1, Privacy Policy (under the title and Section 1)',
		'privacy-contact-pref' => 'Addendum 1 → Part 3 item 4, Privacy Policy Section 2',
		'home-footer-remove-0' => 'Addendum 1 → Decision 1 (Elder Care Locator retired)',
		'home-footer-remove-1' => 'Addendum 1 → Decision 2 (Featured Directory Listings retired)',
	];
	if ( isset( $exact[ $id ] ) ) {
		return $exact[ $id ];
	}
	if ( 0 === strpos( $id, 'home-footer-link-' ) ) {
		return 'Resources Changes → "Footer links" table';
	}
	if ( 0 === strpos( $id, 'pl-card-' ) ) {
		return 'Provider Pages Changes → Plans & Pricing → Copy changes (card timing and subhead) and Fixes';
	}
	if ( 0 === strpos( $id, 'pl-t-' ) ) {
		return 'Provider Pages Changes → Plans & Pricing → Copy changes, "Compare table values"';
	}
	return '';
}

/** Section a change belongs to (each section gets its own Apply button). */
function ecm_cc_section( array $c ) : string {
	if ( ! empty( $c['section'] ) ) {
		return (string) $c['section'];
	}
	$id  = $c['id'];
	$map = [
		'home-strip'          => 'Header strip',
		'home-hero-sub'       => 'Hero',
		'cat-cta-title'       => 'Bottom call-to-action block',
		'cat-cta-sub'         => 'Bottom call-to-action block',
		'cat-providers-sub'   => 'Provider list heading',
		'art-cta-title'       => 'Call-to-action block',
		'art-cta-sub'         => 'Call-to-action block',
		'blog-grid-sub'       => 'Resources subhead',
		'pl-hero-sub'         => 'Hero',
		'pl-faq-answer'       => 'FAQ',
		'about-body'          => 'Page body',
		'wi-oldest'           => 'Page body',
		'wi-qualified'        => 'Page body',
		'terms-e2'            => 'Section 2: The service',
		'terms-e3'            => 'Section 7: editorial note',
		'terms-e4'            => 'Section 8: editorial note',
		'terms-e5'            => 'Section 10: verification note',
		'terms-e6'            => 'Section 17: Changes',
	];
	if ( isset( $map[ $id ] ) ) {
		return $map[ $id ];
	}
	if ( 0 === strpos( $id, 'home-footer' ) ) {
		return 'Footer';
	}
	if ( 0 === strpos( $id, 'pl-t-' ) ) {
		return 'Compare table';
	}
	return 'Other';
}

/* ───────────────────────── POST handling ───────────────────────── */

function ecm_cc_handle_post() : void {
	check_admin_referer( 'ecm_cc', 'ecm_cc_nonce' );
	$action = sanitize_key( $_POST['ecm_cc_action'] );
	$scope  = isset( $_POST['scope'] ) ? sanitize_text_field( wp_unslash( $_POST['scope'] ) ) : 'all';
	$out    = [];

	if ( 'clear_log' === $action ) {
		delete_option( ECM_CC_LOG );
		$GLOBALS['ecm_cc_notice'] = [ 'Log cleared.' ];
		return;
	}
	if ( 'add_target' === $action || 'remove_target' === $action ) {
		$parts = explode( ':', $scope );
		$gid   = sanitize_key( $parts[1] ?? '' );
		$pid   = (int) ( $parts[2] ?? 0 );
		if ( ! $pid && ! empty( $_POST['target_id'] ) ) {
			$pid = (int) $_POST['target_id'];
		}
		$known = wp_list_pluck( ecm_cc_definitions(), 'id' );
		$post  = $pid ? get_post( $pid ) : null;
		if ( ! in_array( $gid, $known, true ) || ! $post || false ) {
			$GLOBALS['ecm_cc_notice'] = [ 'That page or post ID was not found.' ];
			return;
		}
		$t    = get_option( ECM_CC_TARGETS, [] );
		$t    = is_array( $t ) ? $t : [];
		$list = array_map( 'intval', (array) ( $t[ $gid ] ?? [] ) );
		if ( 'add_target' === $action ) {
			$list[] = $pid;
		} else {
			$list = array_diff( $list, [ $pid ] );
		}
		$t[ $gid ] = array_values( array_unique( $list ) );
		update_option( ECM_CC_TARGETS, $t, false );
		ecm_cc_log( $action, $pid, $gid, 'group ' . $gid );
		$GLOBALS['ecm_cc_notice'] = [ ( 'add_target' === $action ? 'Added ' : 'Removed ' ) . $post->post_type . ' #' . $pid . ' (' . $post->post_title . ') ' . ( 'add_target' === $action ? 'to' : 'from' ) . ' group "' . $gid . '".' ];
		return;
	}
	if ( 'clear_backup' === $action ) {
		delete_option( ECM_CC_BACKUP );
		$GLOBALS['ecm_cc_notice'] = [ 'Stored previous values cleared. Applied changes can no longer be reverted from here.' ];
		return;
	}

	$scope_parts = explode( ':', $scope );
	$scope_kind  = $scope_parts[0];
	$scope_group = in_array( $scope_kind, [ 'page', 'section' ], true ) ? ( $scope_parts[1] ?? '' ) : '';
	$scope_post  = in_array( $scope_kind, [ 'page', 'section' ], true ) ? (int) ( $scope_parts[2] ?? 0 ) : 0;
	if ( 'change' === $scope_kind ) {
		$scope_post = (int) ( $scope_parts[2] ?? 0 );
	}
	if ( ! in_array( $scope_kind, [ 'all', 'page', 'section', 'change' ], true ) ) {
		$GLOBALS['ecm_cc_notice'] = [ 'Unknown scope. Nothing was changed.' ];
		return;
	}

	foreach ( ecm_cc_definitions() as $page ) {
		if ( $scope_group && $scope_group !== $page['id'] ) {
			continue;
		}
		foreach ( ecm_cc_group_posts( $page ) as $post ) {
			if ( $scope_post && $scope_post !== (int) $post->ID ) {
				continue;
			}
			foreach ( $page['changes'] as $c ) {
				$hit = false;
				if ( 'all' === $scope ) {
					$hit = ( 'revert' === $action ) || ( $c['bulk'] ?? true );
				} elseif ( 0 === strpos( $scope, 'page:' ) ) {
					$hit = ( $scope === 'page:' . $page['id'] . ':' . $post->ID );
				} elseif ( 0 === strpos( $scope, 'section:' ) ) {
					$hit = ( $scope === 'section:' . $page['id'] . ':' . $post->ID . ':' . sanitize_title( ecm_cc_section( $c ) ) );
				} elseif ( 0 === strpos( $scope, 'change:' ) ) {
					$hit = ( $scope === 'change:' . $c['id'] . ':' . $post->ID );
				}
				if ( ! $hit ) {
					continue;
				}
				$msg = 'revert' === $action ? ecm_cc_revert( $c, $post ) : ecm_cc_apply( $c, $post );
				if ( 0 !== strpos( $msg, 'skipped (applied' ) ) {
					$out[] = '#' . $post->ID . ' ' . $c['id'] . ': ' . $msg;
				}
			}
		}
	}
	$GLOBALS['ecm_cc_notice'] = $out ?: [ 'Nothing to do.' ];
}

/* ───────────────────────── Screen ───────────────────────── */

function ecm_cc_render() : void {
	global $title;
	$title = 'ECM content changes';
	require_once ABSPATH . 'wp-admin/admin-header.php';

	$url    = esc_url( add_query_arg( 'ecm_content_changes', '1', admin_url( 'index.php' ) ) );
	$labels = [ 'pending' => 'Pending', 'applied' => 'Already applied', 'review' => 'Needs review', 'missing' => 'Not found' ];
	$colors = [ 'pending' => '#b45309', 'applied' => '#15803d', 'review' => '#b91c1c', 'missing' => '#6b7280' ];
	$backups = get_option( ECM_CC_BACKUP, [] );
	$defs    = ecm_cc_definitions();

	echo '<div class="wrap"><h1>ECM content changes</h1>';
	echo '<p>This screen is the dry run. Nothing is written until you press an Apply or Revert button. Check the page ID, slug and title of each page against the local site before applying.</p>';

	foreach ( (array) ( $GLOBALS['ecm_cc_notice'] ?? [] ) as $n ) {
		echo '<div class="notice notice-info"><p>' . esc_html( $n ) . '</p></div>';
	}

	// Page map: confirms the same page was found.
	echo '<h2>Pages found on this site</h2><p>Each row is a page the tool <strong>found on this site</strong> for that group. A red "Page not found on this site" row means that page does not exist here, so nothing can be applied to it. "Found" means the page exists and can be changed. "Local ID" is only for comparing with your local site and stays "-" until the local IDs are recorded.</p><table class="widefat striped" style="max-width:1100px"><thead><tr><th>Group</th><th>Local ID</th><th>This site: ID</th><th>Type</th><th>Slug</th><th>Title</th><th>Template</th><th>Check</th></tr></thead><tbody>';
	$totals = [ 'pending' => 0, 'applied' => 0, 'review' => 0, 'missing' => 0 ];
	$resolved = [];
	foreach ( $defs as $page ) {
		$posts = ecm_cc_group_posts( $page );
		$resolved[ $page['id'] ] = $posts;
		if ( ! $posts ) {
			echo '<tr><td>' . esc_html( $page['label'] ) . '</td><td>' . esc_html( (string) ( $page['local_id'] ?? '-' ) ) . '</td><td colspan="6" style="color:#b91c1c">Page not found on this site</td></tr>';
			continue;
		}
		foreach ( $posts as $p ) {
			$local = $page['local_id'] ?? null;
			$check = null === $local ? 'Found' : ( (int) $local === (int) $p->ID ? 'Found, same ID as local' : 'Found, but ID differs from local (local ' . (int) $local . ')' );
			echo '<tr><td>' . esc_html( $page['label'] ) . '</td><td>' . esc_html( (string) ( $local ?? '-' ) ) . '</td><td><a href="' . esc_url( get_edit_post_link( $p->ID ) ) . '">' . (int) $p->ID . '</a></td><td>' . esc_html( $p->post_type ) . '</td><td>' . esc_html( $p->post_name ) . '</td><td>' . esc_html( $p->post_title ) . '</td><td>' . esc_html( (string) get_post_meta( $p->ID, '_wp_page_template', true ) ) . '</td><td>' . esc_html( $check ) . '</td></tr>';
		}
	}
	echo '</tbody></table>';

	// Global buttons.
	echo '<form method="post" action="' . $url . '" style="margin:1rem 0">';
	wp_nonce_field( 'ecm_cc', 'ecm_cc_nonce' );
	echo '<input type="hidden" name="scope" value="all">';
	echo '<button class="button button-primary" name="ecm_cc_action" value="apply" onclick="return confirm(\'Apply every pending change on every page (bulk-safe ones only)?\')">Apply all pending</button> ';
	echo '<button class="button" name="ecm_cc_action" value="revert" onclick="return confirm(\'Revert every change applied from this screen?\')">Revert all applied</button>';
	echo '</form>';

	echo '<h2>Pages and changes</h2>';
	foreach ( $defs as $page ) {
		foreach ( $resolved[ $page['id'] ] as $post ) {
			$evs      = [];
			$can_rev  = [];
			$n_pend   = 0;
			$n_back   = 0;
			foreach ( $page['changes'] as $c ) {
				$evs[ $c['id'] ]     = ecm_cc_evaluate( $c, $post );
				$can_rev[ $c['id'] ] = isset( $backups[ ecm_cc_backup_key( $c['id'], $post->ID ) ] );
				$n_pend += ( 'pending' === $evs[ $c['id'] ]['status'] ) ? 1 : 0;
				$n_back += $can_rev[ $c['id'] ] ? 1 : 0;
			}
			echo '<div style="background:#fff;border:1px solid #c3c4c7;margin:0 0 1.25rem;max-width:1100px">';
			echo '<div style="padding:.75rem 1rem;border-bottom:1px solid #c3c4c7;display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">';
			echo '<strong>' . esc_html( $page['label'] ) . '</strong> <span>ID ' . (int) $post->ID . ' &middot; /' . esc_html( $post->post_name ) . '/ &middot; ' . esc_html( $post->post_title ) . ' &middot; <a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">Edit</a> &middot; <a href="' . esc_url( get_permalink( $post->ID ) ) . '" target="_blank" rel="noopener">View</a></span>';
			echo '<form method="post" action="' . $url . '" style="margin:0">';
			wp_nonce_field( 'ecm_cc', 'ecm_cc_nonce' );
			echo '<input type="hidden" name="scope" value="page:' . esc_attr( $page['id'] . ':' . $post->ID ) . '">';
			if ( $n_pend ) {
				echo '<button class="button button-primary" name="ecm_cc_action" value="apply" onclick="return confirm(\'Apply every pending change on this page?\')">Apply this page (' . (int) $n_pend . ')</button> ';
			}
			if ( $n_back ) {
				echo '<button class="button" name="ecm_cc_action" value="revert">Revert this page</button>';
			}
			if ( ! $n_pend && ! $n_back ) {
				echo '<span style="color:#6b7280">Nothing to apply</span>';
			}
			echo '</form></div>';

			$sections = [];
			foreach ( $page['changes'] as $c ) {
				$sections[ ecm_cc_section( $c ) ][] = $c;
			}
			echo '<table class="widefat striped" style="border:0"><thead><tr><th style="width:14%">Change</th><th style="width:11%">Status</th><th>Current</th><th>New</th><th style="width:130px"></th></tr></thead><tbody>';
			foreach ( $sections as $section_name => $section_changes ) {
				$sec_pend = 0;
				$sec_back = 0;
				foreach ( $section_changes as $sc ) {
					$sec_pend += ( 'pending' === $evs[ $sc['id'] ]['status'] ) ? 1 : 0;
					$sec_back += $can_rev[ $sc['id'] ] ? 1 : 0;
				}
				echo '<tr style="background:#f0f0f1"><td colspan="4"><strong>' . esc_html( $section_name ) . '</strong></td><td>';
				if ( $sec_pend || $sec_back ) {
					echo '<form method="post" action="' . $url . '" style="margin:0;white-space:nowrap">';
					wp_nonce_field( 'ecm_cc', 'ecm_cc_nonce' );
					echo '<input type="hidden" name="scope" value="section:' . esc_attr( $page['id'] . ':' . $post->ID . ':' . sanitize_title( $section_name ) ) . '">';
					if ( $sec_pend ) {
						echo '<button class="button button-primary button-small" name="ecm_cc_action" value="apply" onclick="return confirm(\'Apply every pending change in this section?\')">Apply section</button> ';
					}
					if ( $sec_back ) {
						echo '<button class="button button-small" name="ecm_cc_action" value="revert">Revert</button>';
					}
					echo '</form>';
				} else {
					echo '<span style="color:#6b7280">Nothing to apply</span>';
				}
				echo '</td></tr>';
			foreach ( $section_changes as $c ) {
				$ev = $evs[ $c['id'] ];
				$has_backup = $can_rev[ $c['id'] ];
				$status = $ev['status'];
				$totals[ $status ]++;
				$label = $labels[ $status ];
				if ( $has_backup ) {
					$label .= ' (can revert)';
				}
				echo '<tr><td><code>' . esc_html( $c['id'] ) . '</code><br><small>' . esc_html( $c['note'] ?? '' ) . '</small>' . ( ecm_cc_source( $c ) ? '<br><small><em>Source: ' . esc_html( ecm_cc_source( $c ) ) . '</em></small>' : '' ) . '</td>';
				echo '<td style="color:' . esc_attr( $colors[ $status ] ) . ';font-weight:600">' . esc_html( $label ) . ( ( $c['bulk'] ?? true ) ? '' : '<br><small>Not in bulk apply</small>' ) . '</td>';
				echo '<td>' . esc_html( mb_strimwidth( $ev['current'], 0, 260, '…' ) ) . ( $ev['message'] ? '<br><small>' . esc_html( $ev['message'] ) . '</small>' : '' ) . '</td>';
				echo '<td>' . esc_html( mb_strimwidth( $ev['new'], 0, 260, '…' ) ) . '</td><td>';
				echo '<form method="post" action="' . $url . '" style="margin:0">';
				wp_nonce_field( 'ecm_cc', 'ecm_cc_nonce' );
				echo '<input type="hidden" name="scope" value="change:' . esc_attr( $c['id'] . ':' . $post->ID ) . '">';
				if ( 'pending' === $status ) {
					echo '<button class="button button-small" name="ecm_cc_action" value="apply">Apply</button> ';
				}
				if ( $has_backup ) {
					echo '<button class="button button-small" name="ecm_cc_action" value="revert">Revert</button>';
				}
				echo '</form></td></tr>';
			}
			}
			echo '</tbody></table></div>';
		}
		ecm_cc_render_add_box( $page, $resolved[ $page['id'] ], $url );
	}

	echo '<p><strong>Totals:</strong> ' . (int) $totals['pending'] . ' pending, ' . (int) $totals['applied'] . ' already applied, ' . (int) $totals['review'] . ' need review, ' . (int) $totals['missing'] . ' not found.</p>';

	// Log and stored values.
	$log = get_option( ECM_CC_LOG, [] );
	echo '<h2>Log</h2>';
	if ( empty( $log ) ) {
		echo '<p>No entries.</p>';
	} else {
		echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>Time (UTC)</th><th>User</th><th>Action</th><th>Page ID</th><th>Change</th><th>Result</th></tr></thead><tbody>';
		foreach ( array_reverse( array_slice( (array) $log, -300 ) ) as $row ) {
			echo '<tr><td>' . esc_html( $row['t'] ) . '</td><td>' . esc_html( $row['user'] ) . '</td><td>' . esc_html( $row['action'] ) . '</td><td>' . (int) $row['post'] . '</td><td><code>' . esc_html( $row['change'] ) . '</code></td><td>' . esc_html( $row['detail'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '<p>' . count( (array) $backups ) . ' change(s) have a stored previous value.</p>';
	echo '<form method="post" action="' . $url . '" style="margin:1rem 0">';
	wp_nonce_field( 'ecm_cc', 'ecm_cc_nonce' );
	echo '<input type="hidden" name="scope" value="all">';
	echo '<button class="button" name="ecm_cc_action" value="clear_log" onclick="return confirm(\'Clear the log?\')">Clear log</button> ';
	echo '<button class="button" name="ecm_cc_action" value="clear_backup" onclick="return confirm(\'Clear the stored previous values? Applied changes can no longer be reverted from here. Do this only after you have confirmed them.\')">Clear stored previous values</button>';
	echo '</form></div>';

	require_once ABSPATH . 'wp-admin/admin-footer.php';
}
