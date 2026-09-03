<?php
/**
 * Generic Archive / Fallback Template
 */

if (!defined('ABSPATH')) { exit; }

get_header();
?>
<div class="ezev-container" style="padding-top: var(--ezev-space-12); padding-bottom: var(--ezev-space-16);">
  <h1><?php the_title(); ?></h1>
  <div style="margin-top: var(--ezev-space-6);">
    <?php
    if (have_posts()) {
        while (have_posts()) {
            the_post();
            the_content();
        }
    }
    ?>
  </div>
</div>
<?php
get_footer();