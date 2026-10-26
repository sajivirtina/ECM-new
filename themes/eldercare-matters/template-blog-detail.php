<?php
/**
 * Template Name: ECM — Blog Article (Detail)
 *
 * Single article. Header + body + related guides + CTA, all ACF-editable.
 * Body is a WYSIWYG field; falls back to the prototype sample article.
 */

get_header();

$cat       = ecm_get_field( 'bd_category', 'Home Care' );
$cat_class = ecm_get_field( 'bd_cat_class', 'blog-cat--home-care' );
$title     = ecm_get_field( 'bd_title', 'The Complete Guide to Home Care in 2026' );
$meta      = ecm_get_field( 'bd_meta', '✍️ ECM Editorial Team · March 15, 2026 · ⏱ 8 min read · 🔖 Free guide' );
$body      = get_field( 'bd_body' );
$related   = get_field( 'bd_related' );
$rel_sub   = ecm_get_field( 'bd_related_sub', 'Continue reading to make the most informed decision for your family.' );
$cb_title     = ecm_get_field( 'bd_cb_title', 'Ready to find home care?' );
$cb_sub       = ecm_get_field( 'bd_cb_sub', 'Tell us what you need and choose the providers you want to hear from. Takes about 2 minutes.' );
$cb_primary   = ecm_get_field( 'bd_cb_primary', '📋 Find Care Now →' );
$cb_secondary = ecm_get_field( 'bd_cb_secondary', '💬 Contact our team' );
$em_kses = [ 'em' => [] ];

$posts_page_id = (int) get_option( 'page_for_posts' );
$blog_url = $posts_page_id ? get_permalink( $posts_page_id ) : home_url( '/' );
$blog_label = $posts_page_id ? get_the_title( $posts_page_id ) : __( 'Blog', 'eldercare-matters' );
$search_url = home_url( '/find-care/' );

// Build meta spans
$meta_parts = array_map( 'trim', explode( '·', $meta ) );
?>

<!-- Breadcrumb -->
<div class="breadcrumb" role="navigation" aria-label="<?php esc_attr_e( 'Breadcrumb', 'eldercare-matters' ); ?>">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
    <span class="breadcrumb-sep" aria-hidden="true">›</span>
    <a href="<?php echo esc_url( $blog_url ); ?>"><?php echo esc_html( $blog_label ); ?></a>
    <span class="breadcrumb-sep" aria-hidden="true">›</span>
    <span class="breadcrumb-current" aria-current="page"><?php echo esc_html( $title ); ?></span>
</div>

<!-- Article Header -->
<div class="blog-article-header">
    <span class="blog-card-cat <?php echo esc_attr( $cat_class ); ?>"><?php echo esc_html( $cat ); ?></span>
    <h1 class="blog-article-title"><?php echo esc_html( $title ); ?></h1>
    <div class="blog-article-meta">
        <?php foreach ( $meta_parts as $i => $p ) :
            if ( $i > 0 ) echo '<span class="blog-article-meta-sep">·</span>';
            $cls = ( $i === 0 ) ? ' class="blog-article-meta-author"' : '';
            echo '<span' . $cls . '>' . esc_html( $p ) . '</span>';
        endforeach; ?>
    </div>
</div>

<!-- Article Body -->
<div class="blog-article-body">
<?php if ( $body ) :
    echo wp_kses_post( $body );
else : ?>
    <p>Deciding to bring a home care aide into your parent's life is one of the most meaningful — and most stressful — decisions a family can make. You're balancing your parent's desire for independence with their safety, your own schedule, and a budget that never quite feels large enough. This guide gives you a clear framework so you can move from overwhelmed to confident in a single afternoon of reading.</p>

    <h2>What Is Home Care?</h2>
    <p>Home care is non-medical assistance provided inside a person's own home. A trained aide helps with activities of daily living (ADLs) such as bathing, dressing, medication reminders, meal preparation, and light housekeeping. The goal is to help older adults remain safely at home for as long as possible, preserving their dignity and independence.</p>
    <p>Home care is different from <em>home health care</em>, which involves skilled nursing or physical therapy and is usually prescribed by a doctor after a hospitalization. Home care is primarily custodial — it keeps someone comfortable and safe, but does not treat medical conditions.</p>

    <blockquote>
        "The best home care arrangement is one where your parent still feels like it's their home, not a care facility that happens to be in their living room."
        <br><br>
        — Maria T., RN and elder care manager, Dallas TX
    </blockquote>

    <h2>Types of Home Care Services</h2>
    <p>Not all home care is the same. Services vary widely by agency, and understanding the categories helps you know what to ask for:</p>
    <ul>
        <li><strong>Companion care</strong> — social interaction, light errands, and transportation. Good for seniors who are mostly independent but benefit from company.</li>
        <li><strong>Personal care</strong> — help with bathing, grooming, dressing, and toileting. Requires a trained aide and is more hands-on than companion care.</li>
        <li><strong>Homemaker services</strong> — meal prep, laundry, grocery shopping, and light cleaning. Often bundled with personal care.</li>
        <li><strong>Respite care</strong> — temporary relief for a family caregiver. Can be a few hours per week or a longer block while you travel.</li>
        <li><strong>Live-in care</strong> — an aide stays overnight or around the clock. Suitable for seniors with more complex needs who can't safely be alone.</li>
    </ul>

    <h2>How to Choose the Right Agency</h2>
    <p>There are thousands of home care agencies across the United States, and quality varies enormously. The most important step is to verify that an agency is properly licensed, insured, and screens its caregivers before placing them in a home.</p>

    <div class="tip-box">
        <div class="tip-box-label">✅ Checklist: What to ask every agency</div>
        <ul style="margin-bottom:0">
            <li>Are you licensed in this state? Can you provide proof?</li>
            <li>Do you carry general liability and workers' compensation insurance?</li>
            <li>What does your caregiver background screening include?</li>
            <li>How do you handle caregiver absences or last-minute cancellations?</li>
            <li>Do you have a registered nurse on staff for care plan oversight?</li>
            <li>What is your minimum hours-per-visit requirement?</li>
            <li>Can I speak directly with the caregiver before their first day?</li>
        </ul>
    </div>

    <p>A reputable agency will answer these questions without hesitation. Be cautious of any agency that is evasive about licensing or insurance, as those gaps can leave your family financially exposed if a caregiver is injured in your parent's home.</p>

    <h2>Understanding Costs &amp; Medicare Coverage</h2>
    <p>Home care costs vary by geography, level of care, and whether you hire through an agency or independently. In 2026, the national median for home care aides runs approximately $28–$34 per hour through a licensed agency. In high-cost cities like New York or San Francisco, rates can reach $45+ per hour. Texas and other southern states tend to be closer to $22–$28 per hour.</p>
    <p><strong>Does Medicare cover home care?</strong> Traditional Medicare (Part A and Part B) covers skilled home health care only if ordered by a doctor following a hospitalization or clinical assessment. It does <em>not</em> cover ongoing custodial home care such as bathing assistance or meal preparation. Medicaid, long-term care insurance, and Veterans' benefits are often the primary funding sources for non-skilled home care. Check with your state's Medicaid office or a certified benefits counselor to understand what you qualify for.</p>

    <h2>Questions to Ask Before You Hire</h2>
    <p>Once you've narrowed your list to two or three agencies, schedule a home assessment. Most reputable agencies provide this free. During the visit, prepare to ask:</p>
    <ol>
        <li>Who specifically will be assigned to care for my parent, and can we meet them first?</li>
        <li>What happens if that caregiver calls in sick on a day we need coverage?</li>
        <li>How is the care plan documented, and how often is it reviewed?</li>
        <li>What training do your aides have for dementia or mobility challenges?</li>
        <li>How do I report a concern or request a different caregiver if the fit isn't right?</li>
    </ol>
    <p>Finding the right home care provider takes a little research, but it pays off enormously in peace of mind. When you're ready to start comparing agencies in your area, <a href="<?php echo esc_url( $search_url ); ?>" style="color:var(--sage);font-weight:600">browse verified home care providers on ElderCareMatters →</a></p>
<?php endif; ?>
</div>

<!-- Related Articles -->
<section class="inner-section inner-section--cream">
    <div class="inner-section-header">
        <div>
            <h2>Related <em>Guides</em></h2>
            <p><?php echo esc_html( $rel_sub ); ?></p>
        </div>
        <div class="inner-section-aside"><a href="<?php echo esc_url( $blog_url ); ?>">All guides →</a></div>
    </div>
    <div class="blog-grid">
        <?php
        if ( ! $related ) {
            $related = [
                [ 'icon' => '🏢', 'icon_bg' => '#e8f0fe', 'category' => 'Assisted Living', 'cat_class' => 'blog-cat--assisted-living', 'title' => "Signs It's Time for Assisted Living", 'excerpt' => 'Recognizing the signs that a parent needs more support than home care can provide, and how to start that conversation.', 'meta' => 'Mar 8, 2026 · 5 min read', 'link' => '' ],
                [ 'icon' => '🧠', 'icon_bg' => '#fce8d5', 'category' => 'Memory Care', 'cat_class' => 'blog-cat--memory-care', 'title' => 'Understanding Dementia Stages', 'excerpt' => 'A plain-language overview of the seven stages of dementia and how care needs change over time.', 'meta' => 'Mar 5, 2026 · 7 min read', 'link' => '' ],
                [ 'icon' => '⚖️', 'icon_bg' => '#f3e8fd', 'category' => 'Legal &amp; Financial', 'cat_class' => 'blog-cat--legal', 'title' => 'Medicare vs Medicaid Explained', 'excerpt' => 'The key differences between Medicare and Medicaid, what each covers for elder care, and how to apply.', 'meta' => 'Mar 3, 2026 · 8 min read', 'link' => '' ],
            ];
        }
        foreach ( $related as $r ) :
            $r_link = $r['link'] ?: $blog_url;
        ?>
        <a class="blog-card" href="<?php echo esc_url( $r_link ); ?>">
            <div class="blog-card-icon" style="background:<?php echo esc_attr( $r['icon_bg'] ?? '#e6f4ea' ); ?>"><?php echo esc_html( $r['icon'] ?? '' ); ?></div>
            <div class="blog-card-body">
                <span class="blog-card-cat <?php echo esc_attr( $r['cat_class'] ?? 'blog-cat--home-care' ); ?>"><?php echo wp_kses( $r['category'] ?? '', $em_kses ); ?></span>
                <div class="blog-card-title"><?php echo esc_html( $r['title'] ?? '' ); ?></div>
                <p class="blog-card-excerpt"><?php echo esc_html( $r['excerpt'] ?? '' ); ?></p>
            </div>
            <div class="blog-card-footer">
                <span><?php echo esc_html( $r['meta'] ?? '' ); ?></span>
                <span class="blog-card-footer-link">Read more →</span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- CTA band -->
<div class="cta-band">
    <div>
        <h2><?php echo wp_kses( $cb_title, $em_kses ); ?></h2>
        <p><?php echo esc_html( $cb_sub ); ?></p>
    </div>
    <div class="cta-band-actions">
        <button type="button" class="btn-primary-lg" data-open-form-modal><?php echo esc_html( $cb_primary ); ?></button>
    </div>
</div>

<?php get_footer(); ?>
