<?php
/*
	Template Name: Donation Form
*/
defined('ABSPATH') or die("Unauthorized.");
sdf_check_ssl();
wp_enqueue_script('sdf_stripe', "https://js.stripe.com/v2/");
//wp_enqueue_script('sdf_webshim', plugins_url('/sdf/js/webshim-gh-pages/js-webshim/minified/polyfiller.js'), array('jquery'));
wp_enqueue_script('sdf_validation', plugins_url('/sdf/js/jquery.h5validate.min.js'), array('jquery'));
wp_enqueue_script('sdf_spin', plugins_url('/sdf/js/spin.min.js'));

wp_enqueue_style('sdf_style', plugins_url('/sdf/css/styles.css'), false, '0.1');
//add_action('wp_head','sdf_webshim');

get_header();
// sdf_webshim();
?>

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
	//wp_enqueue_script('sdf_donate_form_js', plugins_url('/sdf/js/donate_form.min.js'), false, '0.2');
	//wp_enqueue_script('sdf_donate_form_js', plugins_url('/sdf/js/donate_form.js'));
	// Wordpress is caching like no other. Adding this hack for now. XXX.
	wp_enqueue_script('sdf_donate_form_js', plugins_url('/sdf/js/donate_form.js?t='.time()));
?>
<script type="text/javascript">
	Stripe.setPublishableKey("<?php echo get_option('stripe_api_public_key'); ?>");
</script>
<?php get_footer(); ?>
