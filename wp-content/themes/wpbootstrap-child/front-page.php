<?php get_header(); ?>

<div class="row-fluid">
  <div class="span10 offset1">
		<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
	
		  	<div class="the-content"><?php the_content(); ?></div>

		<?php endwhile; else: ?>
			<p><?php _e('Sorry, this page does not exist.'); ?></p>
		<?php endif; ?>

	</div>
</div>


<?php get_footer(); ?>