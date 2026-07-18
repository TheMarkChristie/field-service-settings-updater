<?php
/**
 * Admin meta boxes for portal post types.
 *
 * @package FanOwnership
 */

defined( 'ABSPATH' ) || exit;

class FO_Meta_Boxes {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
	}

	public static function register() {
		add_meta_box( 'fo_video_details', __( 'Video Details', 'fan-ownership' ), array( __CLASS__, 'render_video' ), 'fo_video', 'normal', 'high' );
		add_meta_box( 'fo_meeting_details', __( 'Meeting Details', 'fan-ownership' ), array( __CLASS__, 'render_meeting' ), 'fo_meeting', 'normal', 'high' );
		add_meta_box( 'fo_vote_details', __( 'Ballot Details', 'fan-ownership' ), array( __CLASS__, 'render_vote' ), 'fo_vote', 'normal', 'high' );
		add_meta_box( 'fo_idea_details', __( 'Idea Status', 'fan-ownership' ), array( __CLASS__, 'render_idea' ), 'fo_idea', 'side', 'high' );
		add_meta_box( 'fo_question_details', __( 'Official Answer', 'fan-ownership' ), array( __CLASS__, 'render_question' ), 'fo_question', 'normal', 'high' );
		add_meta_box( 'fo_document_details', __( 'Document File', 'fan-ownership' ), array( __CLASS__, 'render_document' ), 'fo_document', 'normal', 'high' );
	}

	public static function render_video( $post ) {
		wp_nonce_field( 'fo_meta', 'fo_meta_nonce' );
		$url     = get_post_meta( $post->ID, '_fo_video_url', true );
		$file_id = (int) get_post_meta( $post->ID, '_fo_video_file', true );
		$live    = get_post_meta( $post->ID, '_fo_video_live', true );
		?>
		<p>
			<label for="fo_video_url"><strong><?php esc_html_e( 'Embed URL (YouTube, Vimeo or live stream link)', 'fan-ownership' ); ?></strong></label><br>
			<input type="url" class="widefat" id="fo_video_url" name="fo_video_url" value="<?php echo esc_attr( $url ); ?>" placeholder="https://youtu.be/…">
		</p>
		<p>
			<label for="fo_video_file"><strong><?php esc_html_e( 'OR self-hosted video: Media Library attachment ID (MP4)', 'fan-ownership' ); ?></strong></label><br>
			<input type="number" id="fo_video_file" name="fo_video_file" value="<?php echo esc_attr( $file_id ? $file_id : '' ); ?>" min="0">
			<?php if ( $file_id && wp_get_attachment_url( $file_id ) ) : ?>
				— <a href="<?php echo esc_url( wp_get_attachment_url( $file_id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview file', 'fan-ownership' ); ?></a>
			<?php endif; ?>
		</p>
		<p>
			<label>
				<input type="checkbox" name="fo_video_live" value="1" <?php checked( $live, '1' ); ?>>
				<?php esc_html_e( 'This is a live stream (shown with a LIVE badge while published)', 'fan-ownership' ); ?>
			</label>
		</p>
		<p class="description"><?php esc_html_e( 'If both are set, the self-hosted file wins. Unlisted YouTube / private Vimeo links are recommended so video stays members-only.', 'fan-ownership' ); ?></p>
		<?php
	}

	public static function render_meeting( $post ) {
		wp_nonce_field( 'fo_meta', 'fo_meta_nonce' );
		$date     = get_post_meta( $post->ID, '_fo_meeting_date', true );
		$location = get_post_meta( $post->ID, '_fo_meeting_location', true );
		$join_url = get_post_meta( $post->ID, '_fo_meeting_join_url', true );
		?>
		<p>
			<label for="fo_meeting_date"><strong><?php esc_html_e( 'Date and time', 'fan-ownership' ); ?></strong></label><br>
			<input type="datetime-local" id="fo_meeting_date" name="fo_meeting_date" value="<?php echo esc_attr( $date ? gmdate( 'Y-m-d\TH:i', strtotime( $date ) ) : '' ); ?>">
		</p>
		<p>
			<label for="fo_meeting_location"><strong><?php esc_html_e( 'Venue / location', 'fan-ownership' ); ?></strong></label><br>
			<input type="text" class="widefat" id="fo_meeting_location" name="fo_meeting_location" value="<?php echo esc_attr( $location ); ?>" placeholder="<?php esc_attr_e( 'e.g. Stadium boardroom, or Online', 'fan-ownership' ); ?>">
		</p>
		<p>
			<label for="fo_meeting_join_url"><strong><?php esc_html_e( 'Online join link (Teams/Zoom/YouTube)', 'fan-ownership' ); ?></strong></label><br>
			<input type="url" class="widefat" id="fo_meeting_join_url" name="fo_meeting_join_url" value="<?php echo esc_attr( $join_url ); ?>">
		</p>
		<?php
	}

	public static function render_vote( $post ) {
		wp_nonce_field( 'fo_meta', 'fo_meta_nonce' );
		$options = get_post_meta( $post->ID, '_fo_vote_options', true );
		$closes  = get_post_meta( $post->ID, '_fo_vote_closes', true );
		$type    = get_post_meta( $post->ID, '_fo_vote_type', true );
		$counts  = FO_Voting::get_counts( $post->ID );
		?>
		<p>
			<label for="fo_vote_options"><strong><?php esc_html_e( 'Options (one per line)', 'fan-ownership' ); ?></strong></label><br>
			<textarea class="widefat" rows="5" id="fo_vote_options" name="fo_vote_options"><?php echo esc_textarea( is_array( $options ) ? implode( "\n", $options ) : '' ); ?></textarea>
		</p>
		<p class="description"><?php esc_html_e( 'Changing options after voting has started will reset the ballot.', 'fan-ownership' ); ?></p>
		<p>
			<label for="fo_vote_closes"><strong><?php esc_html_e( 'Voting closes', 'fan-ownership' ); ?></strong></label><br>
			<input type="datetime-local" id="fo_vote_closes" name="fo_vote_closes" value="<?php echo esc_attr( $closes ? gmdate( 'Y-m-d\TH:i', strtotime( $closes ) ) : '' ); ?>">
		</p>
		<p>
			<label for="fo_vote_type"><strong><?php esc_html_e( 'Ballot type', 'fan-ownership' ); ?></strong></label><br>
			<select id="fo_vote_type" name="fo_vote_type">
				<option value="poll" <?php selected( $type, 'poll' ); ?>><?php esc_html_e( 'Fan poll (advisory)', 'fan-ownership' ); ?></option>
				<option value="decision" <?php selected( $type, 'decision' ); ?>><?php esc_html_e( 'Club decision (binding)', 'fan-ownership' ); ?></option>
			</select>
		</p>
		<?php if ( array_sum( $counts ) > 0 && is_array( $options ) ) : ?>
			<h4><?php esc_html_e( 'Results so far', 'fan-ownership' ); ?></h4>
			<ul>
				<?php foreach ( $options as $i => $option ) : ?>
					<li><?php echo esc_html( $option ); ?> — <strong><?php echo esc_html( isset( $counts[ $i ] ) ? $counts[ $i ] : 0 ); ?></strong></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php
	}

	public static function render_idea( $post ) {
		wp_nonce_field( 'fo_meta', 'fo_meta_nonce' );
		$status     = get_post_meta( $post->ID, '_fo_idea_status', true );
		$supporters = get_post_meta( $post->ID, '_fo_idea_supporters', true );
		$statuses   = self::idea_statuses();
		?>
		<p>
			<label for="fo_idea_status"><strong><?php esc_html_e( 'Status', 'fan-ownership' ); ?></strong></label><br>
			<select id="fo_idea_status" name="fo_idea_status">
				<?php foreach ( $statuses as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status ? $status : 'new', $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<?php
			printf(
				/* translators: %d: number of supporters. */
				esc_html( _n( '%d member supports this idea.', '%d members support this idea.', is_array( $supporters ) ? count( $supporters ) : 0, 'fan-ownership' ) ),
				is_array( $supporters ) ? count( $supporters ) : 0
			);
			?>
		</p>
		<?php
	}

	public static function render_question( $post ) {
		wp_nonce_field( 'fo_meta', 'fo_meta_nonce' );
		$answer = get_post_meta( $post->ID, '_fo_question_answer', true );
		?>
		<p>
			<label for="fo_question_answer"><strong><?php esc_html_e( "The club's answer (shown to members once saved)", 'fan-ownership' ); ?></strong></label>
		</p>
		<textarea class="widefat" rows="6" id="fo_question_answer" name="fo_question_answer"><?php echo esc_textarea( $answer ); ?></textarea>
		<?php
	}

	public static function render_document( $post ) {
		wp_nonce_field( 'fo_meta', 'fo_meta_nonce' );
		$file_id = (int) get_post_meta( $post->ID, '_fo_document_file', true );
		$file    = $file_id ? wp_get_attachment_url( $file_id ) : '';
		?>
		<p>
			<label for="fo_document_file"><strong><?php esc_html_e( 'Attachment ID of the uploaded file (PDF recommended)', 'fan-ownership' ); ?></strong></label><br>
			<input type="number" id="fo_document_file" name="fo_document_file" value="<?php echo esc_attr( $file_id ? $file_id : '' ); ?>" min="0">
		</p>
		<?php if ( $file ) : ?>
			<p><a href="<?php echo esc_url( $file ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview current file', 'fan-ownership' ); ?></a></p>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'Upload the file via Media Library, then paste its attachment ID here. The download button on the front end uses this file.', 'fan-ownership' ); ?></p>
		<?php
	}

	/**
	 * Idea lifecycle statuses.
	 *
	 * @return array<string,string>
	 */
	public static function idea_statuses() {
		return array(
			'new'          => __( 'New', 'fan-ownership' ),
			'under-review' => __( 'Under review', 'fan-ownership' ),
			'planned'      => __( 'Planned', 'fan-ownership' ),
			'delivered'    => __( 'Delivered', 'fan-ownership' ),
			'declined'     => __( 'Declined', 'fan-ownership' ),
		);
	}

	/**
	 * Persist all meta box fields.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['fo_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['fo_meta_nonce'] ), 'fo_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		switch ( $post->post_type ) {
			case 'fo_video':
				update_post_meta( $post_id, '_fo_video_url', isset( $_POST['fo_video_url'] ) ? esc_url_raw( wp_unslash( $_POST['fo_video_url'] ) ) : '' );
				update_post_meta( $post_id, '_fo_video_file', isset( $_POST['fo_video_file'] ) ? absint( $_POST['fo_video_file'] ) : 0 );
				update_post_meta( $post_id, '_fo_video_live', isset( $_POST['fo_video_live'] ) ? '1' : '' );
				break;

			case 'fo_meeting':
				update_post_meta( $post_id, '_fo_meeting_date', self::sanitize_datetime( isset( $_POST['fo_meeting_date'] ) ? wp_unslash( $_POST['fo_meeting_date'] ) : '' ) );
				update_post_meta( $post_id, '_fo_meeting_location', isset( $_POST['fo_meeting_location'] ) ? sanitize_text_field( wp_unslash( $_POST['fo_meeting_location'] ) ) : '' );
				update_post_meta( $post_id, '_fo_meeting_join_url', isset( $_POST['fo_meeting_join_url'] ) ? esc_url_raw( wp_unslash( $_POST['fo_meeting_join_url'] ) ) : '' );
				break;

			case 'fo_vote':
				$raw     = isset( $_POST['fo_vote_options'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fo_vote_options'] ) ) : '';
				$options = array_values( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) );
				$old     = get_post_meta( $post_id, '_fo_vote_options', true );
				update_post_meta( $post_id, '_fo_vote_options', $options );
				if ( is_array( $old ) && $old !== $options ) {
					// Options changed: existing tallies no longer line up, so reset the ballot.
					delete_post_meta( $post_id, '_fo_vote_counts' );
					delete_post_meta( $post_id, '_fo_vote_voters' );
				}
				update_post_meta( $post_id, '_fo_vote_closes', self::sanitize_datetime( isset( $_POST['fo_vote_closes'] ) ? wp_unslash( $_POST['fo_vote_closes'] ) : '' ) );
				$type = isset( $_POST['fo_vote_type'] ) ? sanitize_key( $_POST['fo_vote_type'] ) : 'poll';
				update_post_meta( $post_id, '_fo_vote_type', in_array( $type, array( 'poll', 'decision' ), true ) ? $type : 'poll' );
				break;

			case 'fo_idea':
				$status = isset( $_POST['fo_idea_status'] ) ? sanitize_key( $_POST['fo_idea_status'] ) : 'new';
				update_post_meta( $post_id, '_fo_idea_status', array_key_exists( $status, self::idea_statuses() ) ? $status : 'new' );
				break;

			case 'fo_question':
				update_post_meta( $post_id, '_fo_question_answer', isset( $_POST['fo_question_answer'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fo_question_answer'] ) ) : '' );
				break;

			case 'fo_document':
				update_post_meta( $post_id, '_fo_document_file', isset( $_POST['fo_document_file'] ) ? absint( $_POST['fo_document_file'] ) : 0 );
				break;
		}
	}

	/**
	 * Normalise a datetime-local value to "Y-m-d H:i:s" or empty string.
	 *
	 * @param string $value Raw input.
	 * @return string
	 */
	private static function sanitize_datetime( $value ) {
		$value     = sanitize_text_field( $value );
		$timestamp = $value ? strtotime( $value ) : false;
		return $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : '';
	}
}
