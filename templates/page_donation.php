<?php
/*
	Template Name: Donation Form
*/
defined('ABSPATH') or die("Unauthorized.");
sdf_check_ssl();
get_header(); ?>
<link href="<?php echo plugins_url('/sdf/css/styles.css?v=0.1'); ?>" rel="stylesheet">
<script type="text/javascript" src="https://js.stripe.com/v2/"></script>
<div id="main" role="main">
	<div id="left-content">
		<?php if(have_posts()):
			while(have_posts()): the_post(); ?>
				<?php the_content(); ?>
			<?php endwhile; ?>
		<?php endif; ?>
		<?php if(!post_password_required()): ?>
			<div class="alert"></div>
			<?php sdf_get_form(); ?>
		<?php endif; ?>
	</div><!-- #left-content -->
	<?php get_sidebar(); ?>
</div><!-- #main -->
<?php
	// not putting jquery as a requirement because then spark wp is including it twice.
	wp_enqueue_script('sdf_validation', plugins_url('/sdf/js/jquery.h5validate.min.js'));
	wp_enqueue_script('sdf_spin', plugins_url('/sdf/js/spin.min.js'));
	wp_enqueue_script('sdf_donate_form_js', plugins_url('/sdf/js/donate_form.min.js')); 
?>
<script type="text/javascript">
	Stripe.setPublishableKey("<?php echo get_option('stripe_api_public_key'); ?>");
</script>
<?php get_footer(); ?>