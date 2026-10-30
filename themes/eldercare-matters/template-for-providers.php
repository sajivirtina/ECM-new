<?php
/**
 * Template Name: ECM — For Providers
 *
 * Provider marketing & onboarding page. All content ACF-editable with
 * prototype-faithful fallbacks. Uses for-providers.css (enqueued conditionally).
 */

get_header();

$em = [ 'em' => [] ];
$em_br = [ 'em' => [], 'br' => [] ];

// Plans & Pricing lives at /plans/ (checklist item 68). It
// doesn't yet, on staging or here, so link to the current pricing page.
$plans_url = home_url( '/plans/' );

// Hero
$h_eyebrow = ecm_get_field( 'fp_hero_eyebrow', 'For Care Providers' );
$h_title   = ecm_get_field( 'fp_hero_title', 'Help Families When They Need It Most.<br>Grow a Practice That <em>Truly Matters</em>.' );
$h_sub1    = ecm_get_field( 'fp_hero_sub1', 'Families come to ElderCareMatters after a diagnosis, a fall, or a sudden move. They pick the providers they want to hear from. We put you on that list.' );
$h_sub2    = ecm_get_field( 'fp_hero_sub2', 'If your work helps older adults and their families, you belong here.' );
$h_cta_primary = ecm_get_field( 'fp_hero_cta_primary', 'See Plans & Pricing' );
$h_cta     = ecm_get_field( 'fp_hero_cta', 'Request Information' );
$h_trust   = get_field( 'fp_hero_trust' ) ?: [ [ 'text' => 'Serving families since 2002' ], [ 'text' => 'Compassion' ], [ 'text' => 'Integrity' ], [ 'text' => 'Real Opportunity' ] ];

// Value
$v_eyebrow = ecm_get_field( 'fp_value_eyebrow', 'Why It Matters' );
$v_quote   = ecm_get_field( 'fp_value_quote', '"You Do Important Work. We Help More People Find You."' );
$v_sub     = ecm_get_field( 'fp_value_sub', "Families on ElderCareMatters are dealing with a diagnosis, a fall, or a parent who can't live alone anymore. They choose the provider they trust, and we help them find you." );
$emotions  = get_field( 'fp_emotions' ) ?: [
    [ 'who' => 'Families come with', 'feeling' => '😰', 'title' => 'Fear' ],
    [ 'who' => 'Adult children come with', 'feeling' => '💔', 'title' => 'Guilt' ],
    [ 'who' => 'Spouses come with', 'feeling' => '😔', 'title' => 'Exhaustion' ],
    [ 'who' => 'Seniors come with', 'feeling' => '❓', 'title' => 'Questions' ],
];

// Who
$w_eyebrow = ecm_get_field( 'fp_who_eyebrow', 'Who Participates' );
$w_title   = ecm_get_field( 'fp_who_title', 'Who We Work <em>With</em>' );
$w_lead    = ecm_get_field( 'fp_who_lead', 'We partner with professionals and organizations across the full spectrum of elder care support:' );
$w_items   = get_field( 'fp_who_items' );
if ( ! $w_items ) {
    $w_items = array_map( fn( $t ) => [ 'item' => $t ], [
        'Elder Law Attorneys', 'Care Managers', 'Home Care Agencies', 'Assisted Living Advisors',
        'Senior Placement Specialists', 'Financial Advisors', 'Estate Planning Professionals',
        'Medicaid Planning Experts', 'Memory Care Specialists', 'Grief & Bereavement Counselors',
        'Senior Move Managers', 'Transportation & Support Services', 'Wellness & Aging Specialists',
        'Geriatric Care Specialists', 'Social Workers & Advocates',
    ] );
}
$w_note = ecm_get_field( 'fp_who_note', 'If your work supports older adults and their families, you can participate.' );

// Why
$why_eyebrow = ecm_get_field( 'fp_why_eyebrow', 'Why Join' );
$why_title   = ecm_get_field( 'fp_why_title', 'Why Providers Join <em>ElderCareMatters</em>' );
$why_cards   = get_field( 'fp_why_cards' ) ?: [
    [ 'num' => '01', 'title' => 'Because Families Need Clear Guidance', 'desc' => "Families searching for elder care don't just need a list of providers — they need someone they can trust. ElderCareMatters puts you in front of them in your local community, at the exact moment families are looking for answers." ],
    [ 'num' => '02', 'title' => 'Because Your Expertise Deserves Visibility', 'desc' => "You've built the knowledge, the experience, and the credentials. We help you reach the families who need exactly what you offer — in the locations you actually serve — without expensive advertising or cold outreach." ],
    [ 'num' => '03', 'title' => 'Because Growth Should Feel Meaningful', 'desc' => "Growing your practice doesn't have to mean cold calls or broad marketing. It can mean connecting with families at their moment of genuine need — and making a real difference while building a practice you're proud of." ],
];

// Receive
$rec_eyebrow = ecm_get_field( 'fp_rec_eyebrow', 'Platform Benefits' );
$rec_title   = ecm_get_field( 'fp_rec_title', 'What You <em>Receive</em>' );
$rec_cards   = get_field( 'fp_rec_cards' ) ?: [
    [ 'icon' => '📬', 'title' => 'Real Family Inquiries', 'desc' => 'Every inquiry starts with a real family request in your area, beginning with the families who picked you.' ],
    [ 'icon' => '📍', 'title' => 'Geographic Visibility', 'desc' => 'Your profile is matched to the specific cities and regions where you work — so you appear when and where your community is searching for help.' ],
    [ 'icon' => '🏅', 'title' => 'Professional Credibility', 'desc' => 'A profile designed to build trust. Your credentials, specialties, and service area are presented in a context families are already using to make care decisions.' ],
    [ 'icon' => '🤝', 'title' => 'Relationship-First Matching', 'desc' => 'Families pick up to 10 providers per request, so each inquiry goes to a short list the family chose.' ],
    [ 'icon' => '👁️', 'title' => 'Ongoing Presence', 'desc' => 'Your profile stays in search results while families compare options, often over several weeks.' ],
    [ 'icon' => '📈', 'title' => 'Sustainable Practice Growth', 'desc' => 'Get a steady flow of inquiries from a directory built only for elder care.' ],
];

// How inquiries work
$hiw_eyebrow = ecm_get_field( 'fp_hiw_eyebrow', 'How Inquiries Work' );
$hiw_title   = ecm_get_field( 'fp_hiw_title', 'How Inquiries <em>Work</em>' );
$hiw_body    = ecm_get_field( 'fp_hiw_body', "Families pick up to 10 providers in one category. The providers they pick get the request first. You see the request right away, with the family's contact details hidden. Your plan decides when you can request those details: Featured in the first 24 hours, Premium from hour 24, Basic from hour 48, and Free after 72 hours. If a family opts into expedited matching and its picks don't respond, the masked request also goes to additional providers nearby." );
$hiw_link    = sprintf( '<a href="%s">See Plans &amp; Pricing</a>', esc_url( $plans_url ) );

// How
$how_eyebrow = ecm_get_field( 'fp_how_eyebrow', 'The Process' );
$how_title   = ecm_get_field( 'fp_how_title', 'How It <em>Works</em>' );
$how_steps   = get_field( 'fp_how_steps' ) ?: [
    [ 'num' => '1', 'title' => 'Submit Your Information', 'desc' => "Tell us about your practice, the services you provide, and the areas you serve. We'll use this to understand your expertise and how best to connect you with families who need it." ],
    [ 'num' => '2', 'title' => 'We Review and Build Your Profile', 'desc' => 'Our team reviews your submission and creates your provider profile — presenting your credentials, specialties, and service areas in a way that builds family trust from the first glance.' ],
    [ 'num' => '3', 'title' => 'Families Pick You', 'desc' => 'Families in your area search by type of care and pick up to 10 providers to hear from.' ],
    [ 'num' => '4', 'title' => 'You Request Their Details', 'desc' => "You see each request right away with contact details hidden. Request the family's details in your plan's window, then reach out." ],
    [ 'num' => '5', 'title' => 'Your Practice Grows', 'desc' => 'Every request comes from a family that chose you. Respond well and your reviews grow.' ],
];

// Matters
$m_eyebrow = ecm_get_field( 'fp_matters_eyebrow', 'The Bigger Picture' );
$m_copy    = ecm_get_field( 'fp_matters_copy', '"The families who need you are out there — and they don\'t always know where to look."' );
$m_sub     = ecm_get_field( 'fp_matters_sub', "America's senior population is growing faster than at any point in history. Families are being asked to make complex, urgent decisions — often without preparation, often without support. The providers who show up with clarity and compassion in these moments change lives." );
$m_items   = get_field( 'fp_matters_items' );
if ( ! $m_items ) {
    // Provider Pages Changes doc, "Bigger Picture section": cut from 8
    // general bullets to 3, and use numbers only with a cited source — none
    // of the original bullets' population/growth claims had one, so this
    // keeps the same throughline without any numeric claim.
    $m_items = array_map( fn( $t ) => [ 'item' => $t ], [
        'Families often reach out after a diagnosis, a fall, or a sudden change — with no plan in place and little time to make one',
        'Adult children managing care from a distance, and spouses stretched thin, are looking for someone they can trust to guide them',
        'Providers who care are the bridge between confusion and clarity — and every provider who joins expands the care available to families who need it',
    ] );
}

// Trusted
$t_year  = ecm_get_field( 'fp_trust_year', 'Since 2002' );
$t_quote = ecm_get_field( 'fp_trust_quote', '"For more than twenty years, ElderCareMatters has helped connect families with the professionals who guide them through one of life\'s most challenging chapters — with compassion, integrity, and genuine care."' );
$t_attrs = get_field( 'fp_trust_attrs' ) ?: [ [ 'attr' => 'Compassion' ], [ 'attr' => 'Integrity' ], [ 'attr' => 'Trust' ], [ 'attr' => 'Community' ] ];

// Form
$f_eyebrow = ecm_get_field( 'fp_form_eyebrow', 'Get Started' );
$f_title   = ecm_get_field( 'fp_form_title', 'Submit a Request for <em>Information</em>' );
$f_lead    = ecm_get_field( 'fp_form_lead', "Ready to connect with more families? Tell us about your practice and we'll reach out with next steps." );

// CTA
$cta_title   = ecm_get_field( 'fp_cta_title', 'Join a Network Built on <em>Care, Trust, and Growth</em>' );
$cta_bullets = get_field( 'fp_cta_bullets' ) ?: [ [ 'bullet' => 'Help more families' ], [ 'bullet' => 'Expand your impact' ], [ 'bullet' => 'Grow your practice with purpose' ] ];
$cta_btn     = ecm_get_field( 'fp_cta_btn', 'Become an ElderCareMatters Provider Today →' );
?>

<!-- HERO -->
<section class="fp-hero">
    <div class="fp-hero-eyebrow"><?php echo esc_html( $h_eyebrow ); ?></div>
    <h1 class="fp-hero-title"><?php echo wp_kses( $h_title, $em_br ); ?></h1>
    <p class="fp-hero-sub"><?php echo esc_html( $h_sub1 ); ?></p>
    <p class="fp-hero-sub"><?php echo esc_html( $h_sub2 ); ?></p>
    <div class="fp-hero-ctas">
        <a href="<?php echo esc_url( $plans_url ); ?>" class="fp-hero-cta fp-hero-cta--primary"><?php echo esc_html( $h_cta_primary ); ?></a>
        <a href="#join-form" class="fp-hero-cta fp-hero-cta--secondary"><?php echo esc_html( $h_cta ); ?></a>
    </div>
    <div class="fp-hero-trust">
        <?php foreach ( $h_trust as $t ) : ?>
        <span class="fp-hero-trust-item"><?php echo esc_html( $t['text'] ?? '' ); ?></span>
        <?php endforeach; ?>
    </div>
</section>

<!-- YOU DO IMPORTANT WORK -->
<section class="fp-section fp-section--white">
    <div class="fp-value-grid">
        <div>
            <span class="fp-eyebrow"><?php echo esc_html( $v_eyebrow ); ?></span>
            <p class="fp-value-quote"><?php echo esc_html( $v_quote ); ?></p>
            <p class="fp-value-sub"><?php echo wp_kses( $v_sub, $em_br ); ?></p>
        </div>
        <div class="fp-emotion-grid">
            <?php foreach ( $emotions as $e ) : ?>
            <div class="fp-emotion-card">
                <div class="fp-emotion-who"><?php echo esc_html( $e['who'] ?? '' ); ?></div>
                <div class="fp-emotion-feeling"><?php echo esc_html( $e['feeling'] ?? '' ); ?></div>
                <div class="fp-emotion-title"><?php echo esc_html( $e['title'] ?? '' ); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- WHO WE WORK WITH -->
<section class="fp-section fp-section--pale">
    <div class="fp-center">
        <span class="fp-eyebrow"><?php echo esc_html( $w_eyebrow ); ?></span>
        <h2 class="fp-h2"><?php echo wp_kses( $w_title, $em ); ?></h2>
        <p class="fp-lead"><?php echo esc_html( $w_lead ); ?></p>
    </div>
    <div class="fp-who-grid">
        <?php foreach ( $w_items as $w ) : ?>
        <div class="fp-who-item"><?php echo esc_html( $w['item'] ?? '' ); ?></div>
        <?php endforeach; ?>
    </div>
    <p class="fp-who-note"><?php echo esc_html( $w_note ); ?></p>
</section>

<!-- WHY PROVIDERS JOIN -->
<section class="fp-section fp-section--cream">
    <div class="fp-center" style="margin-bottom:0;">
        <span class="fp-eyebrow"><?php echo esc_html( $why_eyebrow ); ?></span>
        <h2 class="fp-h2"><?php echo wp_kses( $why_title, $em ); ?></h2>
    </div>
    <div class="fp-why-grid">
        <?php foreach ( $why_cards as $c ) : ?>
        <div class="fp-why-card">
            <span class="fp-why-num"><?php echo esc_html( $c['num'] ?? '' ); ?></span>
            <div class="fp-why-title"><?php echo esc_html( $c['title'] ?? '' ); ?></div>
            <p class="fp-why-desc"><?php echo esc_html( $c['desc'] ?? '' ); ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- WHAT YOU RECEIVE -->
<section class="fp-section fp-section--white">
    <div class="fp-center" style="margin-bottom:40px;">
        <span class="fp-eyebrow"><?php echo esc_html( $rec_eyebrow ); ?></span>
        <h2 class="fp-h2"><?php echo wp_kses( $rec_title, $em ); ?></h2>
    </div>
    <div class="fp-receive-grid">
        <?php foreach ( $rec_cards as $c ) : ?>
        <div class="fp-receive-card">
            <span class="fp-receive-icon"><?php echo esc_html( $c['icon'] ?? '' ); ?></span>
            <div class="fp-receive-title"><?php echo esc_html( $c['title'] ?? '' ); ?></div>
            <p class="fp-receive-desc"><?php echo esc_html( $c['desc'] ?? '' ); ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- HOW INQUIRIES WORK -->
<section class="fp-section fp-section--pale">
    <div class="fp-center" style="margin-bottom:0;">
        <span class="fp-eyebrow"><?php echo esc_html( $hiw_eyebrow ); ?></span>
        <h2 class="fp-h2"><?php echo wp_kses( $hiw_title, $em ); ?></h2>
        <p class="fp-lead" style="margin-bottom:0;">
            <?php echo esc_html( $hiw_body ); ?> <?php echo wp_kses( $hiw_link, [ 'a' => [ 'href' => [] ] ] ); ?>
        </p>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="fp-section fp-section--cream">
    <div style="margin-bottom:8px;">
        <span class="fp-eyebrow"><?php echo esc_html( $how_eyebrow ); ?></span>
        <h2 class="fp-h2"><?php echo wp_kses( $how_title, $em ); ?></h2>
    </div>
    <div class="fp-steps-list">
        <?php foreach ( $how_steps as $s ) : ?>
        <div class="fp-step-row">
            <div class="fp-step-num"><?php echo esc_html( $s['num'] ?? '' ); ?></div>
            <div class="fp-step-body">
                <div class="fp-step-title"><?php echo esc_html( $s['title'] ?? '' ); ?></div>
                <p class="fp-step-desc"><?php echo esc_html( $s['desc'] ?? '' ); ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- WHY THIS MATTERS -->
<div class="fp-matters-band">
    <div>
        <span class="fp-eyebrow" style="color:var(--sage-light);"><?php echo esc_html( $m_eyebrow ); ?></span>
        <p class="fp-matters-copy"><?php echo esc_html( $m_copy ); ?></p>
        <p class="fp-matters-sub"><?php echo esc_html( $m_sub ); ?></p>
    </div>
    <div>
        <div class="fp-matters-list">
            <?php foreach ( $m_items as $mi ) : ?>
            <div class="fp-matters-item"><?php echo esc_html( $mi['item'] ?? '' ); ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- TRUSTED SINCE -->
<section class="fp-section fp-section--white">
    <div class="fp-trust-center">
        <span class="fp-trust-year"><?php echo esc_html( $t_year ); ?></span>
        <p class="fp-trust-quote"><?php echo esc_html( $t_quote ); ?></p>
        <div class="fp-trust-attrs">
            <?php foreach ( $t_attrs as $a ) : ?>
            <span class="fp-trust-attr"><?php echo esc_html( $a['attr'] ?? '' ); ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- SUBMIT REQUEST FORM -->
<section class="fp-section fp-section--pale" id="join-form">
    <div class="fp-center" style="margin-bottom:40px;">
        <span class="fp-eyebrow"><?php echo esc_html( $f_eyebrow ); ?></span>
        <h2 class="fp-h2"><?php echo wp_kses( $f_title, $em ); ?></h2>
        <p class="fp-lead"><?php echo esc_html( $f_lead ); ?></p>
    </div>
    <div class="fp-form-wrap">
        <?php
        // Render the Constant Contact form. Suppress errors if the form ID is
        // not yet configured in this environment (local dev); it will work on staging.
        try {
            $form_shortcode = ecm_get_field( 'fp_form_shortcode', '[contact-form-7 id="f16beae" title="Contact form 1"]' );
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo do_shortcode( $form_shortcode );
        } catch ( \Throwable $e ) {
            echo '<p style="color:#888;text-align:center;padding:24px 0;">Form not available in this environment — configure the Constant Contact form on staging.</p>';
        }
        ?>
    </div>
</section>

<!-- FINAL CTA -->
<div class="fp-cta-band">
    <h2><?php echo wp_kses( $cta_title, $em ); ?></h2>
    <div class="fp-cta-bullets">
        <?php foreach ( $cta_bullets as $b ) : ?>
        <span class="fp-cta-bullet"><?php echo esc_html( $b['bullet'] ?? '' ); ?></span>
        <?php endforeach; ?>
    </div>
    <a href="#join-form" class="fp-cta-btn"><?php echo esc_html( $cta_btn ); ?></a>
</div>

<?php get_footer(); ?>
