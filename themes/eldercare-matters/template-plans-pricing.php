<?php
/**
 * Template Name: ECM — Plans & Pricing
 *
 * Provider plan comparison / pricing page. Rebuilt from the old Listify
 * "Page: Plans and Pricing" template (which relied on the
 * wp-job-manager-wc-paid-listings widget) to use ElderCareMatters' own
 * design system instead — the .plan-card / .plans-table components
 * already defined in provider-pages.css. All content is ACF-editable
 * with prototype-faithful fallbacks, matching the pattern used by
 * template-for-providers.php.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

get_header();

$em = [ 'em' => [] ];

// ── Hero ──────────────────────────────────────────────────────────────
$h_eyebrow = ecm_get_field( 'pl_hero_eyebrow', 'For Care Providers' );
$h_title   = ecm_get_field( 'pl_hero_title', 'Elder Care Directory — <em>Plans, Benefits &amp; Pricing</em>' );
$h_sub     = ecm_get_field( 'pl_hero_sub', "List your practice on ElderCareMatters.com, serving families since 2002. Pick the plan that fits how fast you want to reach families." );

// ── Plan Cards ───────────────────────────────────────────────────────
// Cards + fallback now live in ecm_get_plan_cards() (inc/plan-cards.php)
// so the Featured Directory Listings page's plan popup can read the same
// admin-controlled data instead of keeping a second copy that can drift.
$cards          = ecm_get_plan_cards();
$cards_intro    = ecm_get_field( 'pl_cards_intro', 'Families pick who hears from them first.' );
$cards_footnote = ecm_get_field( 'pl_cards_footnote', '* Provider-submitted articles use a slot and still require editorial approval before publishing.' );

// ── Comparison Table ─────────────────────────────────────────────────
$t_title = ecm_get_field( 'pl_table_title', 'Compare <em>Every Feature</em>' );
$t_sub   = ecm_get_field( 'pl_table_sub', 'A closer look at what each plan includes, side by side.' );
$t_rows  = get_field( 'pl_table_rows' );
if ( ! $t_rows ) {
    $t_rows = [
        [ 'is_category' => true,  'label' => 'Listing Basics' ],
        [ 'is_category' => false, 'label' => 'Directory listing',        'val1' => 'check',        'val2' => 'check',          'val3' => 'check',          'val4' => 'check' ],
        [ 'is_category' => false, 'label' => 'Service categories',       'val1' => '1',            'val2' => '3',              'val3' => 'Unlimited',      'val4' => 'Unlimited' ],
        [ 'is_category' => false, 'label' => 'Photo gallery',            'val1' => 'dash',         'val2' => 'check',          'val3' => 'check',          'val4' => 'check' ],
        [ 'is_category' => true,  'label' => 'Visibility' ],
        [ 'is_category' => false, 'label' => 'Priority search placement','val1' => 'dash',         'val2' => 'check',          'val3' => 'check',          'val4' => 'check' ],
        [ 'is_category' => false, 'label' => 'Featured badge',           'val1' => 'dash',         'val2' => 'dash',           'val3' => 'check',          'val4' => 'check' ],
        [ 'is_category' => false, 'label' => 'Homepage spotlight',       'val1' => 'dash',         'val2' => 'dash',           'val3' => 'check',          'val4' => 'check' ],
        [ 'is_category' => false, 'label' => 'Top directory placement',  'val1' => 'dash',         'val2' => 'dash',           'val3' => 'dash',           'val4' => 'check' ],
        [ 'is_category' => true,  'label' => 'Family Inquiry Access' ],
        [ 'is_category' => false, 'label' => 'Can request family details', 'val1' => 'After 72 hours', 'val2' => 'From hour 48', 'val3' => 'From hour 24', 'val4' => 'Hours 0 to 24' ],
        [ 'is_category' => true,  'label' => 'Support & Management' ],
        [ 'is_category' => false, 'label' => 'Family contact channels',  'val1' => 'Email',        'val2' => 'Email + chat',   'val3' => 'Email + chat',   'val4' => 'Dedicated manager' ],
        [ 'is_category' => false, 'label' => 'Locations per account',    'val1' => '1',            'val2' => '1',              'val3' => '1',              'val4' => 'Unlimited' ],
        [ 'is_category' => true,  'label' => 'Written Articles Add-on' ],
        [ 'is_category' => false, 'label' => 'Article slots per year',   'val1' => '1',            'val2' => '1',              'val3' => '2',              'val4' => '4' ],
        [ 'is_category' => false, 'label' => 'ECM writes it — fee',      'val1' => '$350',         'val2' => '$250',           'val3' => '$200',           'val4' => '$150' ],
    ];
}

// ── FAQ ──────────────────────────────────────────────────────────────
$f_tag   = ecm_get_field( 'pl_faq_tag', 'Common Questions' );
$f_title = ecm_get_field( 'pl_faq_title', 'Plans &amp; Pricing FAQ' );
$f_sub   = ecm_get_field( 'pl_faq_sub', 'Everything providers ask us before signing up.' );
$f_items = get_field( 'pl_faq_items' );
if ( ! $f_items ) {
    $f_items = [
        [ 'question' => 'Can I switch plans later?', 'answer' => 'Yes — you can upgrade or downgrade at any time from your provider dashboard. Changes apply at the start of your next billing cycle.' ],
        [ 'question' => 'Is there a contract or commitment?', 'answer' => 'No long-term contracts. Paid plans are billed monthly and can be cancelled anytime; your listing simply reverts to the Free plan.' ],
        [ 'question' => 'When do I receive new family inquiries?', 'answer' => "You get an inquiry when a family picks you. Every provider the family picked sees the request right away, with contact details hidden. Featured providers can request the family's details in the first 24 hours, Premium from hour 24, Basic from hour 48, and Free after 72 hours. If a family opts into expedited matching and its picks don't respond, the masked request also goes to additional verified providers nearby. Upgrade to reach families sooner." ],
        [ 'question' => 'How do the written article slots work?', 'answer' => 'Every plan includes a set number of article slots per year. You can write and submit articles yourself at no cost, or pay us to research and write one for you — pricing depends on your tier ($150–$350). One round of revisions is included; additional rounds are $50 each. Turnaround is two weeks from the date you approve the topic. Each article may include one backlink to a page you choose. Provider-submitted articles still go through editorial approval before publishing.' ],
        [ 'question' => 'What counts as a "service category"?', 'answer' => 'A category is a type of care you provide — e.g. Home Care, Assisted Living, Memory Care. Higher-tier plans let your listing appear under more categories families search.' ],
        [ 'question' => 'Do you offer refunds?', 'answer' => "We don't offer refunds on partial months, but you can cancel anytime to stop future billing." ],
    ];
}

// ── Final CTA ────────────────────────────────────────────────────────
$cta_title      = ecm_get_field( 'pl_cta_title', 'Still deciding which plan is <em>right for you?</em>' );
$cta_sub        = ecm_get_field( 'pl_cta_sub', 'Our team can walk you through the options in under 5 minutes.' );
$cta_btn1_text  = ecm_get_field( 'pl_cta_btn1_text', '📋 Talk to Our Team →' );
$cta_btn1_url   = ecm_get_field( 'pl_cta_btn1_url', '#join-form' );
$cta_btn2_text  = ecm_get_field( 'pl_cta_btn2_text', '💬 Chat with us' );
$cta_btn2_url   = ecm_get_field( 'pl_cta_btn2_url', '#' );

// Map a select-field style to its badge modifier + label.
$badge_label = [ 'free' => 'Free', 'basic' => 'Basic', 'popular' => 'Premium', 'featured' => 'Featured' ];
?>

<!-- HERO -->
<section class="plans-hero">
    <span class="plans-hero-eyebrow"><?php echo esc_html( $h_eyebrow ); ?></span>
    <h1 class="plans-hero-title"><?php echo wp_kses( $h_title, $em ); ?></h1>
    <p class="plans-hero-sub"><?php echo esc_html( $h_sub ); ?></p>
</section>

<!-- PLAN CARDS -->
<section class="plans-cards-section">
    <?php if ( $cards_intro ) : ?>
        <p class="plans-pick-line"><?php echo esc_html( $cards_intro ); ?></p>
    <?php endif; ?>
    <div class="plans-cards">
        <?php foreach ( $cards as $card ) :
            $style   = $card['style'] ?? 'basic';
            $ribbon  = $card['ribbon'] ?? '';
            $price   = $card['price'] ?? '';
            $is_free = ( '' === trim( (string) $price ) || '0' === trim( (string) $price ) ) && 'free' === $style;
        ?>
        <div class="plan-card plan-card--<?php echo esc_attr( $style ); ?>">

            <?php if ( $ribbon ) : ?>
                <span class="plan-ribbon plan-ribbon--<?php echo esc_attr( 'featured' === $style ? 'featured' : 'popular' ); ?>">
                    <?php echo esc_html( $ribbon ); ?>
                </span>
            <?php endif; ?>

            <div class="plan-card-badge-row">
                <span class="plan-hero-badge plan-hero-badge--<?php echo esc_attr( $style ); ?>">
                    <?php echo esc_html( $badge_label[ $style ] ?? ucfirst( $style ) ); ?>
                </span>
            </div>

            <div class="plan-card-name"><?php echo esc_html( $card['name'] ?? '' ); ?></div>
            <div class="plan-card-tagline"><?php echo esc_html( $card['tagline'] ?? '' ); ?></div>

            <div class="plan-card-price">
                <?php if ( $is_free ) : ?>
                    <span class="plan-card-price-val plan-card-price-val--free">Free</span>
                <?php else : ?>
                    <span class="plan-card-price-val"><?php echo esc_html( $price ); ?></span>
                    <span class="plan-card-price-unit"><?php echo esc_html( $card['price_unit'] ?? '/ month' ); ?></span>
                <?php endif; ?>
            </div>

            <ul class="plan-card-features">
                <?php foreach ( ( $card['features'] ?? [] ) as $feat ) :
                    $feat_text   = $feat['text'] ?? '';
                    $is_enquiry  = false !== stripos( $feat_text, 'inquir' ) || false !== stripos( $feat_text, 'enquir' );
                    $li_class    = $is_enquiry ? 'plan-card-feature--enquiry' : ( ! empty( $feat['strong'] ) ? 'plan-card-feature--strong' : '' );
                ?>
                    <li class="<?php echo esc_attr( $li_class ); ?>">
                        <?php echo esc_html( $feat_text ); ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <a class="plan-card-cta-btn" href="<?php echo esc_url( $card['btn_url'] ?? '#' ); ?>">
                <?php echo esc_html( $card['btn_text'] ?? 'Get Started →' ); ?>
            </a>

        </div>
        <?php endforeach; ?>
    </div>

    <?php if ( $cards_footnote ) : ?>
        <p class="plans-pricing-note"><?php echo esc_html( $cards_footnote ); ?></p>
    <?php endif; ?>
</section>

<!-- COMPARISON TABLE -->
<section class="plans-table-section">
    <div class="plans-table-header">
        <h2><?php echo wp_kses( $t_title, $em ); ?></h2>
        <p><?php echo esc_html( $t_sub ); ?></p>
    </div>

    <div class="plans-table-wrap">
        <table class="plans-table">
            <thead>
                <tr>
                    <th></th>
                    <?php foreach ( $cards as $card ) :
                        $style = $card['style'] ?? 'basic';
                        $col_class = 'popular' === $style ? 'col-popular' : ( 'featured' === $style ? 'col-featured' : '' );
                    ?>
                    <th class="<?php echo esc_attr( $col_class ); ?>">
                        <?php echo esc_html( $card['name'] ?? '' ); ?>
                        <span class="col-tag"><?php echo esc_html( $badge_label[ $style ] ?? '' ); ?></span>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $t_rows as $row ) :
                    $row_label      = $row['label'] ?? '';
                    $row_is_enquiry = false !== stripos( $row_label, 'inquir' ) || false !== stripos( $row_label, 'enquir' );
                    $is_enq_cat     = ! empty( $row['is_category'] ) && $row_is_enquiry;
                    $is_enq_datarow = empty( $row['is_category'] ) && $row_is_enquiry;
                    if ( ! empty( $row['is_category'] ) ) : ?>
                        <tr class="plans-table-category<?php echo $is_enq_cat ? ' plans-table-category--enquiry' : ''; ?>">
                            <td colspan="<?php echo esc_attr( count( $cards ) + 1 ); ?>"><?php echo esc_html( $row_label ); ?></td>
                        </tr>
                    <?php else : ?>
                        <tr class="plans-table-row<?php echo $is_enq_datarow ? ' plans-table-row--enquiry' : ''; ?>">
                            <td class="plans-table-feature"><?php echo esc_html( $row_label ); ?></td>
                            <?php
                            foreach ( [ 'val1', 'val2', 'val3', 'val4' ] as $i => $key ) :
                                if ( $i >= count( $cards ) ) { continue; }
                                $val = $row[ $key ] ?? '';
                            ?>
                                <td class="plans-table-val">
                                    <?php if ( 'check' === $val ) : ?>
                                        <span class="plans-table-val--check">✓</span>
                                    <?php elseif ( 'dash' === $val || '' === $val ) : ?>
                                        <span class="plans-table-val--dash">—</span>
                                    <?php elseif ( is_numeric( $val ) ) : ?>
                                        <span class="plans-table-val--num"><?php echo esc_html( $val ); ?></span>
                                    <?php else : ?>
                                        <span class="plans-table-val--text"><?php echo esc_html( $val ); ?></span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endif;
                endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- ARTICLE ADD-ON RULES -->
<section class="plans-addon-section">
    <div class="plans-addon-inner">
        <div class="plans-addon-head">
            <span class="section-tag">Add-on</span>
            <h2>Written Articles — <em>How It Works</em></h2>
            <p>Every plan includes article slots you can use yourself or hand to our team. Higher plans include more article slots and a lower per-article rate.</p>
        </div>
        <div class="plans-addon-grid">
            <?php
            $addon_tiers = [
                [ 'name' => 'Free',     'slots' => 1, 'price' => '$350', 'style' => 'free'     ],
                [ 'name' => 'Basic',    'slots' => 1, 'price' => '$250', 'style' => 'basic'    ],
                [ 'name' => 'Premium',  'slots' => 2, 'price' => '$200', 'style' => 'popular'  ],
                [ 'name' => 'Featured', 'slots' => 4, 'price' => '$150', 'style' => 'featured' ],
            ];
            foreach ( $addon_tiers as $at ) : ?>
            <div class="plans-addon-card">
                <span class="plan-hero-badge plan-hero-badge--<?php echo esc_attr( $at['style'] ); ?>"><?php echo esc_html( $at['name'] ); ?></span>
                <div class="plans-addon-slots"><?php echo esc_html( $at['slots'] ); ?> slot<?php echo $at['slots'] > 1 ? 's' : ''; ?> / year</div>
                <div class="plans-addon-price"><?php echo esc_html( $at['price'] ); ?> <span>if we write it</span></div>
            </div>
            <?php endforeach; ?>
        </div>
        <ul class="plans-addon-rules">
            <li>Write and submit articles yourself at no cost — they use a slot and go through editorial approval before publishing.</li>
            <li>One round of revisions included when we write it. Each additional round is <strong>$50</strong>.</li>
            <li>Turnaround is <strong>two weeks</strong> from the date you approve the topic.</li>
            <li>Maximum <strong>one backlink</strong> per article, to a page of your choice.</li>
        </ul>
    </div>
</section>

<!-- FAQ -->
<section class="plans-faq">
    <div class="plans-faq-layout">
        <div class="plans-faq-left">
            <div class="section-tag"><?php echo esc_html( $f_tag ); ?></div>
            <h2><?php echo wp_kses( $f_title, $em ); ?></h2>
            <p><?php echo esc_html( $f_sub ); ?></p>
        </div>
        <div class="faq-items">
            <?php foreach ( $f_items as $item ) : ?>
            <div class="faq-item">
                <div class="faq-q">
                    <?php echo esc_html( $item['question'] ?? '' ); ?>
                    <span class="faq-icon">+</span>
                </div>
                <div class="faq-a"><?php echo esc_html( $item['answer'] ?? '' ); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- FINAL CTA -->
<div class="cta-band">
    <div>
        <h2><?php echo wp_kses( $cta_title, $em ); ?></h2>
        <p><?php echo esc_html( $cta_sub ); ?></p>
    </div>
    <div class="cta-band-actions">
        <a href="<?php echo esc_url( $cta_btn1_url ); ?>" class="btn-primary-lg"><?php echo esc_html( $cta_btn1_text ); ?></a>
        <a href="<?php echo esc_url( $cta_btn2_url ); ?>" class="btn-ghost" style="color:#fff;border-color:rgba(255,255,255,0.3);"><?php echo esc_html( $cta_btn2_text ); ?></a>
    </div>
</div>

<?php get_footer(); ?>
