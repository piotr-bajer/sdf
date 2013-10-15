<?php
/*
	Template Name: Donation Form
*/
get_header(); ?>
<link href="<?php echo plugins_url('/sdf/css/styles.css?v=0.1'); ?>" rel="stylesheet">
<script type="text/javascript" src="https://js.stripe.com/v2/"></script>
<div id="main" role="main">
	<div id="left-content">
		<div class="alert"></div>
		<?php if(have_posts()):
			while(have_posts()): the_post(); ?>
				<?php the_content(); ?>
			<?php endwhile; ?>
		<?php endif; ?>
		<?php if(!post_password_required()): ?>
			<?php sdf_get_form(); ?>
		<?php endif; ?>
	</div><!-- #left-content -->
	<?php get_sidebar(); ?>
</div><!-- #main -->
<?php wp_enqueue_script('sdf_donate_form_js', plugins_url('/sdf/js/donate_form.js'), array('jquery')); ?>
<script type="text/javascript">
	Stripe.setPublishableKey("<?php echo get_option('stripe_api_public_key'); ?>");
</script>
<?php get_footer(); ?>