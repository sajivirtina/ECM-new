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

// Hero
$h_eyebrow = ecm_get_field( 'fp_hero_eyebrow', 'For Care Providers' );
$h_title   = ecm_get_field( 'fp_hero_title', 'Help Families When They Need It Most.<br>Grow a Practice That <em>Truly Matters</em>.' );
$h_sub1    = ecm_get_field( 'fp_hero_sub1', 'ElderCareMatters connects compassionate providers with families navigating one of the most difficult chapters of their lives.' );
$h_sub2    = ecm_get_field( 'fp_hero_sub2', 'If you care about the work you do — and want more families to find you — you belong here.' );
$h_cta     = ecm_get_field( 'fp_hero_cta', 'Join ElderCareMatters →' );
$h_trust   = get_field( 'fp_hero_trust' ) ?: [ [ 'text' => 'Trusted since 2002' ], [ 'text' => 'Compassion' ], [ 'text' => 'Integrity' ], [ 'text' => 'Real Opportunity' ] ];

// Value
$v_eyebrow = ecm_get_field( 'fp_value_eyebrow', 'Why It Matters' );
$v_quote   = ecm_get_field( 'fp_value_quote', '"You Do Important Work. We Help More People Find You."' );
$v_sub     = ecm_get_field( 'fp_value_sub', 'This is not a typical marketing platform. The families who come to ElderCareMatters are in the middle of something hard — a diagnosis, a fall, a sudden transition — and they need someone they can trust. That someone is you.<br><br>We exist to make sure they find you.' );
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
    [ 'num' => '01', 'title' => 'Because Families Need Trusted Guidance', 'desc' => "Families searching for elder care don't just need a list of providers — they need someone they can trust. ElderCareMatters positions you as that trusted expert in your local community, at the exact moment families are looking for answers." ],
    [ 'num' => '02', 'title' => 'Because Your Expertise Deserves Visibility', 'desc' => "You've built the knowledge, the experience, and the credentials. We help you reach the families who need exactly what you offer — in the locations you actually serve — without expensive advertising or cold outreach." ],
    [ 'num' => '03', 'title' => 'Because Growth Should Feel Meaningful', 'desc' => "Growing your practice doesn't have to mean cold calls or broad marketing. It can mean connecting with families at their moment of genuine need — and making a real difference while building a practice you're proud of." ],
];

// Receive
$rec_eyebrow = ecm_get_field( 'fp_rec_eyebrow', 'Platform Benefits' );
$rec_title   = ecm_get_field( 'fp_rec_title', 'What You <em>Receive</em>' );
$rec_cards   = get_field( 'fp_rec_cards' ) ?: [
    [ 'icon' => '📬', 'title' => 'Qualified Family Inquiries', 'desc' => 'Families actively seeking elder care support — not cold leads or purchased lists. Every inquiry is connected to a real family with a real need in your area.' ],
    [ 'icon' => '📍', 'title' => 'Geographic Visibility', 'desc' => 'Your profile is matched to the specific cities and regions where you work — so you appear when and where your community is searching for help.' ],
    [ 'icon' => '🏅', 'title' => 'Professional Credibility', 'desc' => 'A profile designed to build trust. Your credentials, specialties, and service area are presented in a context families are already using to make care decisions.' ],
    [ 'icon' => '🤝', 'title' => 'Relationship-First Matching', 'desc' => 'We focus on fit, not volume. Matching is designed around your category, geography, and the nature of family needs — so your time is spent on the right conversations.' ],
    [ 'icon' => '👁️', 'title' => 'Ongoing Presence', 'desc' => "You remain visible throughout the family's care search journey — not just at one moment. We keep you in front of families as they make decisions over time." ],
    [ 'icon' => '📈', 'title' => 'Sustainable Practice Growth', 'desc' => 'Build a consistent pipeline through a channel designed specifically for eldercare — without guesswork, cold outreach, or wasted advertising spend.' ],
];

// How
$how_eyebrow = ecm_get_field( 'fp_how_eyebrow', 'The Process' );
$how_title   = ecm_get_field( 'fp_how_title', 'How It <em>Works</em>' );
$how_steps   = get_field( 'fp_how_steps' ) ?: [
    [ 'num' => '1', 'title' => 'Submit Your Information', 'desc' => "Tell us about your practice, the services you provide, and the areas you serve. We'll use this to understand your expertise and how best to connect you with families who need it." ],
    [ 'num' => '2', 'title' => 'We Review and Build Your Profile', 'desc' => 'Our team reviews your submission and creates your provider profile — presenting your credentials, specialties, and service areas in a way that builds family trust from the first glance.' ],
    [ 'num' => '3', 'title' => 'Families Discover You', 'desc' => 'When families in your area search for elder care support, you appear. Your visibility is tied to your geography and specialty — so you show up at the exact moment families are making decisions.' ],
    [ 'num' => '4', 'title' => 'You Connect Directly', 'desc' => 'No middlemen. No call centers. Families reach out to you directly — and you engage them on your terms. The relationship is yours from the very first conversation.' ],
    [ 'num' => '5', 'title' => 'Your Practice Grows With Purpose', 'desc' => 'Every engagement starts from a place of genuine need. You help more families. Your reputation grows. And your practice expands — not through pressure, but through trust.' ],
];

// Matters
$m_eyebrow = ecm_get_field( 'fp_matters_eyebrow', 'The Bigger Picture' );
$m_copy    = ecm_get_field( 'fp_matters_copy', '"The families who need you are out there — and they don\'t always know where to look."' );
$m_sub     = ecm_get_field( 'fp_matters_sub', "America's senior population is growing faster than at any point in history. Families are being asked to make complex, urgent decisions — often without preparation, often without support. The providers who show up with clarity and compassion in these moments change lives." );
$m_items   = get_field( 'fp_matters_items' );
if ( ! $m_items ) {
    $m_items = array_map( fn( $t ) => [ 'item' => $t ], [
        'Millions of older Americans need care support — and most families have no plan when a crisis hits',
        'Adult children are often managing care from a distance, overwhelmed and unsure where to turn',
        'Spouses and caregivers are stretched to their limits, looking for someone who can guide them',
        "Seniors themselves are navigating health, legal, and financial decisions they've never faced before",
        'Trusted, qualified providers are the bridge between confusion and clarity for these families',
        'The demand for elder care professionals is growing — and the families who need help deserve to find you',
        'Every provider who joins expands the network of care available to families in need',
        'Your work matters more now than it ever has — we want to help the world know about it',
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
$f_confirm_title = ecm_get_field( 'fp_form_confirm_title', "Thank you — we'll be in touch shortly." );
$f_confirm_text  = ecm_get_field( 'fp_form_confirm_text', "We've received your inquiry and will reach out within 1 business day with availability and next steps for your service area." );

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
    <a href="#join-form" class="fp-hero-cta"><?php echo esc_html( $h_cta ); ?></a>
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
        <form id="fp-provider-form" onsubmit="return fpSubmitForm(event);">
            <div class="fp-form-grid">
                <div class="fp-form-group">
                    <label for="fp-fullname">Full Name *</label>
                    <input id="fp-fullname" name="fullname" type="text" placeholder="Your full name" required>
                </div>
                <div class="fp-form-group">
                    <label for="fp-business">Business Name *</label>
                    <input id="fp-business" name="business" type="text" placeholder="Your practice or organization" required>
                </div>
                <div class="fp-form-group">
                    <label for="fp-email">Email Address *</label>
                    <input id="fp-email" name="email" type="email" placeholder="you@example.com" required>
                </div>
                <div class="fp-form-group">
                    <label for="fp-phone">Phone Number</label>
                    <input id="fp-phone" name="phone" type="tel" placeholder="(555) 000-0000">
                </div>
                <div class="fp-form-group">
                    <label for="fp-category">Service Category *</label>
                    <select id="fp-category" name="category" required>
                        <option value="">Select your category…</option>
                        <option>Elder Law Attorney</option>
                        <option>Care Manager</option>
                        <option>Home Care Agency</option>
                        <option>Assisted Living Advisor</option>
                        <option>Senior Placement Specialist</option>
                        <option>Financial Advisor</option>
                        <option>Estate Planning Professional</option>
                        <option>Medicaid Planning Expert</option>
                        <option>Memory Care Specialist</option>
                        <option>Grief &amp; Bereavement Counselor</option>
                        <option>Senior Move Manager</option>
                        <option>Transportation &amp; Support Services</option>
                        <option>Wellness &amp; Aging Specialist</option>
                        <option>Geriatric Care Specialist</option>
                        <option>Social Worker &amp; Advocate</option>
                        <option>Other</option>
                    </select>
                </div>
                <div class="fp-form-group">
                    <label for="fp-location">City / State *</label>
                    <input id="fp-location" name="location" type="text" placeholder="e.g. Austin, TX" required>
                </div>
                <div class="fp-form-group fp-form-full">
                    <label for="fp-website">Website</label>
                    <input id="fp-website" name="website" type="url" placeholder="https://yourwebsite.com">
                </div>
                <div class="fp-form-group fp-form-full">
                    <label for="fp-message">Message</label>
                    <textarea id="fp-message" name="message" rows="4" placeholder="Tell us about your practice, service area, and what you're looking for…"></textarea>
                </div>
            </div>
            <button type="submit" class="fp-form-submit">Submit Your Request →</button>
        </form>
        <div class="fp-form-confirm" id="fp-form-confirm">
            <div class="fp-form-confirm-icon">✅</div>
            <h4><?php echo esc_html( $f_confirm_title ); ?></h4>
            <p><?php echo esc_html( $f_confirm_text ); ?></p>
        </div>
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

<script>
function fpSubmitForm(e) {
    e.preventDefault();
    document.getElementById('fp-provider-form').style.display = 'none';
    document.getElementById('fp-form-confirm').style.display = 'block';
    return false;
}
</script>

<?php get_footer(); ?>
