<?php defined( 'ABSPATH' ) || exit; ?>
</main>

<footer class="site-footer" id="contacts">
	<div class="container site-footer__grid">
		<div>
			<a class="logo logo--light" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<span class="logo__mark" aria-hidden="true"></span>
				<span class="logo__text">Демо-квартал</span>
			</a>
			<p class="site-footer__note">Демонстрационный проект: каталог квартир на WordPress. Названия, цены и адреса вымышлены.</p>
		</div>
		<div>
			<h2 class="site-footer__title">Офис продаж</h2>
			<p><?php echo esc_html( zhk_option( 'address' ) ); ?><br><?php echo esc_html( zhk_option( 'hours' ) ); ?></p>
			<p><a class="site-footer__phone" href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', zhk_option( 'phone' ) ) ); ?>"><?php echo esc_html( zhk_option( 'phone' ) ); ?></a></p>
		</div>
		<div>
			<h2 class="site-footer__title">Разделы</h2>
			<?php zhk_fallback_menu(); ?>
		</div>
	</div>
	<div class="container site-footer__bottom">
		<span>© <?php echo esc_html( gmdate( 'Y' ) ); ?> Демо-квартал</span>
		<a href="<?php echo esc_url( get_privacy_policy_url() ); ?>">Политика обработки персональных данных</a>
	</div>
</footer>

<?php get_template_part( 'template-parts/lead-modal' ); ?>
<?php wp_footer(); ?>
</body>
</html>
