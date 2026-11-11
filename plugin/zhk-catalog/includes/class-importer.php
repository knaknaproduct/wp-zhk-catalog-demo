<?php
/**
 * Импорт и экспорт квартир в CSV (Квартиры → Импорт / экспорт).
 *
 * Сценарий менеджера: выгрузить CSV, поправить цены и статусы в Excel, загрузить обратно.
 * Квартира ищется по паре «корпус + номер»: есть — обновляется, нет — создаётся.
 */

defined( 'ABSPATH' ) || exit;

final class ZHK_Importer {

	const PAGE     = 'zhk-import';
	const MAX_SIZE = 5 * MB_IN_BYTES;

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_zhk_import', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_zhk_export', array( __CLASS__, 'handle_export' ) );
	}

	public static function menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . ZHK_Apartment::POST_TYPE,
			'Импорт / экспорт квартир',
			'Импорт / экспорт',
			'edit_others_posts',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page(): void {
		$report = get_transient( 'zhk_import_report_' . get_current_user_id() );
		delete_transient( 'zhk_import_report_' . get_current_user_id() );
		$columns = implode( ';', array_keys( ZHK_Apartment::fields() ) );
		?>
		<div class="wrap">
			<h1>Импорт / экспорт квартир</h1>

			<?php if ( $report ) : ?>
				<div class="notice notice-<?php echo esc_attr( $report['errors'] ? 'warning' : 'success' ); ?>">
					<p>Создано: <?php echo (int) $report['created']; ?>, обновлено: <?php echo (int) $report['updated']; ?>, пропущено строк: <?php echo count( $report['errors'] ); ?>.</p>
					<?php if ( $report['errors'] ) : ?>
						<ul style="list-style:disc;padding-left:20px">
							<?php foreach ( array_slice( $report['errors'], 0, 20 ) as $error ) : ?>
								<li><?php echo esc_html( $error ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<h2>1. Выгрузить текущие квартиры</h2>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zhk_export' ), 'zhk_export' ) ); ?>">Скачать CSV</a>
			</p>

			<h2>2. Загрузить файл</h2>
			<p>CSV с разделителем «;» или «,», первая строка — названия колонок:<br><code><?php echo esc_html( $columns ); ?></code></p>
			<p>Статус: <code>free</code> — свободна, <code>reserved</code> — бронь, <code>sold</code> — продана. Отделка: <code>none</code>, <code>whitebox</code>, <code>full</code>. Студия — 0 комнат.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="zhk_import">
				<?php wp_nonce_field( 'zhk_import' ); ?>
				<input type="file" name="csv" accept=".csv,text/csv" required>
				<?php submit_button( 'Импортировать', 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_import(): void {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( 'Недостаточно прав.', 403 );
		}
		check_admin_referer( 'zhk_import' );

		$file = $_FILES['csv'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- проверяется ниже.
		if ( ! $file || UPLOAD_ERR_OK !== $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			self::finish( array( 'created' => 0, 'updated' => 0, 'errors' => array( 'Файл не загружен.' ) ) );
		}
		if ( $file['size'] > self::MAX_SIZE ) {
			self::finish( array( 'created' => 0, 'updated' => 0, 'errors' => array( 'Файл больше 5 МБ.' ) ) );
		}

		$text = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- временный файл загрузки.
		self::finish( self::import_text( (string) $text ) );
	}

	/**
	 * Импортирует CSV из строки. Возвращает отчёт: created, updated, errors[].
	 */
	public static function import_text( string $text ): array {
		$report = array( 'created' => 0, 'updated' => 0, 'errors' => array() );

		$text = preg_replace( '/^\xEF\xBB\xBF/', '', $text ); // BOM.
		if ( ! mb_check_encoding( $text, 'UTF-8' ) ) {
			$text = mb_convert_encoding( $text, 'UTF-8', 'Windows-1251' ); // Excel на Windows.
		}
		$lines = preg_split( '/\R/', trim( $text ) );
		if ( count( $lines ) < 2 ) {
			$report['errors'][] = 'В файле нет строк с квартирами.';
			return $report;
		}

		$delimiter = substr_count( $lines[0], ';' ) >= substr_count( $lines[0], ',' ) ? ';' : ',';
		$header    = array_map( static fn( $h ) => strtolower( trim( $h ) ), str_getcsv( $lines[0], $delimiter ) );
		$missing   = array_diff( array( 'number', 'building' ), $header );
		if ( $missing ) {
			$report['errors'][] = 'Нет обязательных колонок: ' . implode( ', ', $missing ) . '.';
			return $report;
		}

		$known = ZHK_Apartment::fields();
		foreach ( array_slice( $lines, 1 ) as $i => $line ) {
			$row_num = $i + 2;
			if ( '' === trim( $line ) ) {
				continue;
			}
			$cells = str_getcsv( $line, $delimiter );
			if ( count( $cells ) !== count( $header ) ) {
				$report['errors'][] = "Строка {$row_num}: число колонок не совпадает с заголовком.";
				continue;
			}
			$row = array_intersect_key( array_combine( $header, $cells ), $known );

			$bad = array();
			foreach ( $row as $key => $value ) {
				if ( '' !== trim( $value ) && null === ZHK_Apartment::sanitize( $key, $value ) ) {
					$bad[] = $key;
				}
			}
			$number   = ZHK_Apartment::sanitize( 'number', $row['number'] );
			$building = ZHK_Apartment::sanitize( 'building', $row['building'] );
			if ( '' === $number || '' === $building ) {
				$bad[] = 'number/building';
			}
			if ( $bad ) {
				$report['errors'][] = "Строка {$row_num}: неверные значения в колонках " . implode( ', ', $bad ) . '.';
				continue;
			}

			$row     = array_filter( $row, static fn( $v ) => '' !== trim( $v ) );
			$post_id = self::find( $building, $number );
			if ( ! $post_id ) {
				$post_id = wp_insert_post(
					array(
						'post_type'   => ZHK_Apartment::POST_TYPE,
						'post_status' => 'publish',
						'post_title'  => "Квартира №{$number}, корпус {$building}",
						'post_name'   => sanitize_title( "k{$building}-{$number}" ),
					),
					true
				);
				if ( is_wp_error( $post_id ) ) {
					$report['errors'][] = "Строка {$row_num}: не удалось создать квартиру.";
					continue;
				}
				$row += array( 'status' => 'free', 'finish' => 'none' );
				++$report['created'];
			} else {
				++$report['updated'];
			}
			ZHK_Apartment::save_fields( $post_id, $row );
		}

		return $report;
	}

	private static function find( string $building, string $number ): int {
		$ids = get_posts(
			array(
				'post_type'      => ZHK_Apartment::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array( 'key' => 'zhk_building', 'value' => $building ),
					array( 'key' => 'zhk_number', 'value' => $number ),
				),
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	private static function finish( array $report ): void {
		set_transient( 'zhk_import_report_' . get_current_user_id(), $report, 300 );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . ZHK_Apartment::POST_TYPE . '&page=' . self::PAGE ) );
		exit;
	}

	public static function handle_export(): void {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( 'Недостаточно прав.', 403 );
		}
		check_admin_referer( 'zhk_export' );

		$keys = array_keys( ZHK_Apartment::fields() );
		$ids  = get_posts(
			array(
				'post_type'      => ZHK_Apartment::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="kvartiry-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- BOM, чтобы Excel открыл кириллицу.
		fputcsv( $out, $keys, ';' );
		foreach ( $ids as $id ) {
			$row = array();
			foreach ( $keys as $key ) {
				$value = ZHK_Apartment::get( $id, $key );
				// Защита от формул в Excel (CSV injection).
				$row[] = preg_match( '/^[=+\-@]/', (string) $value ) ? "'" . $value : $value;
			}
			fputcsv( $out, $row, ';' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
}
