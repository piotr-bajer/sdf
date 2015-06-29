<?php
/*
	Template Name: Donation Form
*/
defined('ABSPATH') or die("Unauthorized.");
sdf_check_ssl();

wp_enqueue_script('sdf_stripe', "https://js.stripe.com/v2/");
wp_enqueue_script('sdf_spin', plugins_url('/sdf/js/spin.min.js'));

wp_enqueue_script('jquery-ui', 
		plugins_url('/sdf/js/jquery-ui.min.js'), 
		array('jquery'));

wp_enqueue_script('select-to-autocomplete', 
		plugins_url('/sdf/js/jquery.select-to-autocomplete.js'), 
		array('jquery', 'jquery-ui'));

if(LIVEMODE) {
	wp_enqueue_style('sdf_style',
			plugins_url('/sdf/css/styles.css'));
} else {
	wp_enqueue_style('sdf_style',
			plugins_url('/sdf/css/styles.css?t='.time()),
			array(),
			'0.2');	
}

get_header(); ?>

<div id="main" role="main">
	<div id="left-content">
		<?php if(have_posts()):
			while(have_posts()): the_post(); ?>
				<?php the_content(); ?>
			<?php endwhile; ?>
		<?php endif; ?>
		<?php if(!post_password_required()): ?>
			<div class="alert"></div>
			<?php require 'form.html'; ?>
		<?php endif; ?>
	</div><!-- #left-content -->
	<?php get_sidebar(); ?>
</div><!-- #main -->

<?php
	if(LIVEMODE) {
		wp_enqueue_script('sdf_donate_form_js',
				plugins_url('/sdf/js/donate_form.js'));
	} else {
		wp_enqueue_script('sdf_donate_form_js',
				plugins_url('/sdf/js/donate_form.js?t='.time()));
	}
?>
<script type="text/javascript">
	Stripe.setPublishableKey("<?php echo get_option('stripe_api_public_key'); ?>");

	(function($){
		$('#country').selectToAutocomplete();
	})(jQuery);
</script>
<?php get_footer(); ?>
