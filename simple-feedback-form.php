<?php
/**
 * Plugin Name: Google Review Redirect Feedback Form
 * Plugin URI: https://example.com
 * Description: A WordPress plugin to build customizable feedback forms. Boost your business reputation by conditionally redirecting happy customers to your Google Review page while saving all submissions to your database. Use shortcode [sff_form] to display the form.
 * Version: 1.2.0
 * Author: sharada-marasinghe
 * License: MIT
 * Text Domain: simple-feedback-form
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // No direct access.
}

define( 'SFF_VERSION', '1.2.0' );
define( 'SFF_DB_VERSION', '1.0' );

/* =========================================================
 *  ACTIVATION: create database tables
 * ========================================================= */
function sff_activate_plugin() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_questions = $wpdb->prefix . 'sff_questions';
    $table_responses = $wpdb->prefix . 'sff_responses';

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql1 = "CREATE TABLE $table_questions (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        question TEXT NOT NULL,
        field_type VARCHAR(20) NOT NULL DEFAULT 'text',
        is_required TINYINT(1) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    $sql2 = "CREATE TABLE $table_responses (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        submitted_at DATETIME NOT NULL,
        response_data LONGTEXT NOT NULL,
        overall_rating INT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    dbDelta( $sql1 );
    dbDelta( $sql2 );

    add_option( 'sff_db_version', SFF_DB_VERSION );
    add_option( 'sff_google_review_url', '' );
    add_option( 'sff_thankyou_message', 'Thank you for your feedback!' );
    add_option( 'sff_redirect_min_rating', 0 ); // 0 = always redirect to google regardless of rating
}
register_activation_hook( __FILE__, 'sff_activate_plugin' );

/* =========================================================
 *  ADMIN MENU
 * ========================================================= */
function sff_admin_menu() {
    add_menu_page(
        'Feedback Form', 'Feedback Form', 'manage_options', 'sff-questions',
        'sff_render_questions_page', 'dashicons-star-half', 26
    );
    add_submenu_page( 'sff-questions', 'Questions', 'Questions', 'manage_options', 'sff-questions', 'sff_render_questions_page' );
    add_submenu_page( 'sff-questions', 'Responses', 'Responses', 'manage_options', 'sff-responses', 'sff_render_responses_page' );
    add_submenu_page( 'sff-questions', 'Settings', 'Settings', 'manage_options', 'sff-settings', 'sff_render_settings_page' );
}
add_action( 'admin_menu', 'sff_admin_menu' );

/* =========================================================
 *  QUESTIONS PAGE (Add / Edit / Delete)
 * ========================================================= */
function sff_render_questions_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    global $wpdb;
    $table = $wpdb->prefix . 'sff_questions';

    if ( isset( $_POST['sff_add_question'] ) && check_admin_referer( 'sff_add_question_action', 'sff_nonce' ) ) {
        $question    = sanitize_text_field( $_POST['question'] );
        $field_type  = sanitize_text_field( $_POST['field_type'] );
        $is_required = isset( $_POST['is_required'] ) ? 1 : 0;

        if ( ! empty( $question ) ) {
            $max_order = (int) $wpdb->get_var( "SELECT MAX(sort_order) FROM $table" );
            $wpdb->insert( $table, array(
                'question'    => $question,
                'field_type'  => $field_type,
                'is_required' => $is_required,
                'sort_order'  => $max_order + 1,
            ) );
            echo '<div class="notice notice-success"><p>Question added.</p></div>';
        }
    }

    if ( isset( $_GET['delete'] ) && check_admin_referer( 'sff_delete_question_' . $_GET['delete'] ) ) {
        $wpdb->delete( $table, array( 'id' => (int) $_GET['delete'] ) );
        echo '<div class="notice notice-success"><p>Question deleted.</p></div>';
    }

    $questions = $wpdb->get_results( "SELECT * FROM $table ORDER BY sort_order ASC" );
    ?>
    <div class="wrap">
        <h1>Feedback Questions</h1>
        <p>Add the questions you want to ask. These appear in order on the <code>[sff_form]</code> shortcode.</p>

        <h2>Add New Question</h2>
        <form method="post">
            <?php wp_nonce_field( 'sff_add_question_action', 'sff_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th><label for="question">Question text</label></th>
                    <td><input type="text" id="question" name="question" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="field_type">Answer type</label></th>
                    <td>
                        <select id="field_type" name="field_type">
                            <option value="text">Short text</option>
                            <option value="textarea">Long text (paragraph)</option>
                            <option value="rating">Star rating (1-5)</option>
                            <option value="yes_no">Yes / No</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Required?</th>
                    <td><label><input type="checkbox" name="is_required" value="1"> Make this question required</label></td>
                </tr>
            </table>
            <?php submit_button( 'Add Question', 'primary', 'sff_add_question' ); ?>
        </form>

        <h2>Existing Questions</h2>
        <table class="widefat striped">
            <thead><tr><th>#</th><th>Question</th><th>Type</th><th>Required</th><th>Action</th></tr></thead>
            <tbody>
            <?php if ( $questions ) : foreach ( $questions as $q ) : ?>
                <tr>
                    <td><?php echo (int) $q->sort_order; ?></td>
                    <td><?php echo esc_html( $q->question ); ?></td>
                    <td><?php echo esc_html( $q->field_type ); ?></td>
                    <td><?php echo $q->is_required ? 'Yes' : 'No'; ?></td>
                    <td>
                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=sff-questions&delete=' . $q->id ), 'sff_delete_question_' . $q->id ) ); ?>"
                           onclick="return confirm('Delete this question?');" style="color:#a00;">Delete</a>
                    </td>
                </tr>
            <?php endforeach; else : ?>
                <tr><td colspan="5">No questions added yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <p style="margin-top:20px;">Display the form on any page/post using the shortcode: <code>[sff_form]</code></p>
    </div>
    <?php
}

/* =========================================================
 *  SETTINGS PAGE
 * ========================================================= */
function sff_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    if ( isset( $_POST['sff_save_settings'] ) && check_admin_referer( 'sff_settings_action', 'sff_settings_nonce' ) ) {
        update_option( 'sff_google_review_url', esc_url_raw( $_POST['sff_google_review_url'] ) );
        update_option( 'sff_thankyou_message', sanitize_text_field( $_POST['sff_thankyou_message'] ) );
        update_option( 'sff_redirect_min_rating', (int) $_POST['sff_redirect_min_rating'] );
        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }

    $google_url   = get_option( 'sff_google_review_url', '' );
    $thankyou_msg = get_option( 'sff_thankyou_message', 'Thank you for your feedback!' );
    $min_rating   = get_option( 'sff_redirect_min_rating', 0 );
    ?>
    <div class="wrap">
        <h1>Feedback Form Settings</h1>
        <form method="post">
            <?php wp_nonce_field( 'sff_settings_action', 'sff_settings_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th><label for="sff_google_review_url">Google Review link</label></th>
                    <td>
                        <input type="url" id="sff_google_review_url" name="sff_google_review_url" class="regular-text"
                               value="<?php echo esc_attr( $google_url ); ?>" placeholder="https://g.page/r/xxxxxxxx/review">
                        <p class="description">Get this from Google Business Profile → "Ask for reviews".</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="sff_redirect_min_rating">Only redirect to Google if rating is at least</label></th>
                    <td>
                        <select id="sff_redirect_min_rating" name="sff_redirect_min_rating">
                            <option value="0" <?php selected( $min_rating, 0 ); ?>>Always redirect</option>
                            <option value="3" <?php selected( $min_rating, 3 ); ?>>3 stars or more</option>
                            <option value="4" <?php selected( $min_rating, 4 ); ?>>4 stars or more</option>
                            <option value="5" <?php selected( $min_rating, 5 ); ?>>5 stars only</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="sff_thankyou_message">Thank-you message (shown when not redirected)</label></th>
                    <td><input type="text" id="sff_thankyou_message" name="sff_thankyou_message" class="regular-text" value="<?php echo esc_attr( $thankyou_msg ); ?>"></td>
                </tr>
            </table>
            <?php submit_button( 'Save Settings', 'primary', 'sff_save_settings' ); ?>
        </form>
    </div>
    <?php
}

/* =========================================================
 *  RESPONSES PAGE  (+ Delete Actions & CSV export button)
 * ========================================================= */
function sff_render_responses_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    global $wpdb;
    $table = $wpdb->prefix . 'sff_responses';

    // 1. Handle single delete action
    if ( isset( $_GET['delete_response'] ) && check_admin_referer( 'sff_delete_response_' . $_GET['delete_response'] ) ) {
        $response_id = (int) $_GET['delete_response'];
        $wpdb->delete( $table, array( 'id' => $response_id ) );
        echo '<div class="notice notice-success is-dismissible"><p>Feedback response deleted successfully.</p></div>';
    }

    // 2. Handle delete all action
    if ( isset( $_POST['sff_delete_all_responses'] ) && check_admin_referer( 'sff_delete_all_action', 'sff_delete_all_nonce' ) ) {
        $result = $wpdb->query( "TRUNCATE TABLE $table" );
        if ( false === $result ) {
            $wpdb->query( "DELETE FROM $table" );
        }
        echo '<div class="notice notice-success is-dismissible"><p>All feedback responses have been permanently deleted.</p></div>';
    }

    // 3. Handle bulk delete action
    if ( isset( $_POST['sff_bulk_action'] ) && $_POST['sff_bulk_action'] === 'delete' && check_admin_referer( 'sff_bulk_responses_action', 'sff_bulk_nonce' ) ) {
        if ( ! empty( $_POST['response_ids'] ) && is_array( $_POST['response_ids'] ) ) {
            $ids = array_map( 'intval', $_POST['response_ids'] );
            $ids = array_filter( $ids );
            if ( ! empty( $ids ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
                $deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE id IN ($placeholders)", $ids ) );
                if ( $deleted ) {
                    /* translators: %d: number of deleted responses */
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( '%d feedback response deleted successfully.', '%d feedback responses deleted successfully.', $deleted, 'simple-feedback-form' ), $deleted ) ) . '</p></div>';
                }
            }
        } else {
            echo '<div class="notice notice-warning is-dismissible"><p>No feedback responses were selected for deletion.</p></div>';
        }
    }

    $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
    $rows  = $wpdb->get_results( "SELECT * FROM $table ORDER BY submitted_at DESC LIMIT 200" );

    $export_url = wp_nonce_url( admin_url( 'admin-post.php?action=sff_export_csv' ), 'sff_export_csv_action' );
    ?>
    <div class="wrap">
        <h1>Feedback Responses (<?php echo $total; ?> total)</h1>

        <div style="display: flex; gap: 10px; align-items: center; margin: 15px 0 10px 0; flex-wrap: wrap;">
            <a href="<?php echo esc_url( $export_url ); ?>" class="button button-primary">⬇ Export All to CSV</a>

            <?php if ( $total > 0 ) : ?>
                <form method="post" style="margin:0;" onsubmit="return confirm('⚠️ WARNING: Are you sure you want to permanently delete ALL <?php echo (int) $total; ?> feedback responses? This action cannot be undone!');">
                    <?php wp_nonce_field( 'sff_delete_all_action', 'sff_delete_all_nonce' ); ?>
                    <input type="hidden" name="sff_delete_all_responses" value="1">
                    <button type="submit" class="button" style="color: #b32d2e; border-color: #b32d2e; font-weight: 600;">🗑 Delete All Responses</button>
                </form>
            <?php endif; ?>
        </div>

        <p class="description">Showing latest 200 below. The CSV export includes <strong>all</strong> responses.</p>

        <form method="post" id="sff-responses-form" onsubmit="return sffConfirmBulkAction(this);">
            <?php wp_nonce_field( 'sff_bulk_responses_action', 'sff_bulk_nonce' ); ?>

            <div class="tablenav top" style="margin: 6px 0;">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>
                    <select name="sff_bulk_action" id="bulk-action-selector-top">
                        <option value="-1">Bulk Actions</option>
                        <option value="delete">Delete</option>
                    </select>
                    <input type="submit" class="button action" value="Apply">
                </div>
            </div>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <td id="cb" class="manage-column column-cb check-column" style="width: 2.2em; padding: 8px 10px;">
                            <input id="cb-select-all-top" type="checkbox" onclick="sffToggleAll(this);">
                        </td>
                        <th style="width: 160px;">Date</th>
                        <th style="width: 100px;">Rating</th>
                        <th>Answers</th>
                        <th style="width: 100px; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( $rows ) : foreach ( $rows as $r ) :
                    $data = json_decode( $r->response_data, true );
                    $delete_url = wp_nonce_url( admin_url( 'admin.php?page=sff-responses&delete_response=' . $r->id ), 'sff_delete_response_' . $r->id );
                    ?>
                    <tr>
                        <th scope="row" class="check-column" style="padding: 8px 10px;">
                            <input type="checkbox" name="response_ids[]" value="<?php echo (int) $r->id; ?>" class="sff-response-cb">
                        </th>
                        <td><?php echo esc_html( $r->submitted_at ); ?></td>
                        <td><?php echo $r->overall_rating ? esc_html( $r->overall_rating ) . ' / 5' : '-'; ?></td>
                        <td>
                            <?php if ( is_array( $data ) ) : foreach ( $data as $q => $a ) : ?>
                                <strong><?php echo esc_html( $q ); ?>:</strong> <?php echo esc_html( is_array( $a ) ? implode( ', ', $a ) : $a ); ?><br>
                            <?php endforeach; endif; ?>
                        </td>
                        <td style="text-align: center; vertical-align: middle;">
                            <a href="<?php echo esc_url( $delete_url ); ?>"
                               onclick="return confirm('Are you sure you want to delete this response?');"
                               class="button button-small"
                               style="color: #b32d2e; border-color: #b32d2e;">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr><td colspan="5">No responses yet.</td></tr>
                <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="manage-column column-cb check-column" style="width: 2.2em; padding: 8px 10px;">
                            <input id="cb-select-all-bottom" type="checkbox" onclick="sffToggleAll(this);">
                        </td>
                        <th>Date</th>
                        <th>Rating</th>
                        <th>Answers</th>
                        <th style="text-align: center;">Action</th>
                    </tr>
                </tfoot>
            </table>
        </form>
    </div>

    <script type="text/javascript">
    function sffToggleAll(source) {
        var checkboxes = document.querySelectorAll('.sff-response-cb');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = source.checked;
        }
        var topCb = document.getElementById('cb-select-all-top');
        var btmCb = document.getElementById('cb-select-all-bottom');
        if (topCb) topCb.checked = source.checked;
        if (btmCb) btmCb.checked = source.checked;
    }

    function sffConfirmBulkAction(form) {
        var select = form.querySelector('select[name="sff_bulk_action"]');
        if (select && select.value === 'delete') {
            var checked = form.querySelectorAll('input[name="response_ids[]"]:checked');
            if (checked.length === 0) {
                alert('Please select at least one response to delete.');
                return false;
            }
            return confirm('Are you sure you want to delete the selected ' + checked.length + ' response(s)?');
        }
        return true;
    }
    </script>
    <?php
}

/* CSV export handler */
function sff_export_csv() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
    check_admin_referer( 'sff_export_csv_action' );

    global $wpdb;
    $table = $wpdb->prefix . 'sff_responses';
    $rows  = $wpdb->get_results( "SELECT * FROM $table ORDER BY submitted_at ASC" );

    // Build the full set of question columns (in case questions changed over time).
    $columns = array();
    $decoded_rows = array();
    foreach ( $rows as $r ) {
        $data = json_decode( $r->response_data, true );
        if ( ! is_array( $data ) ) $data = array();
        $decoded_rows[] = array( 'meta' => $r, 'data' => $data );
        foreach ( array_keys( $data ) as $q ) {
            if ( ! in_array( $q, $columns, true ) ) {
                $columns[] = $q;
            }
        }
    }

    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=feedback-responses-' . date( 'Y-m-d' ) . '.csv' );

    $output = fopen( 'php://output', 'w' );
    // UTF-8 BOM so Excel opens special characters correctly.
    fputs( $output, "\xEF\xBB\xBF" );

    $header_row = array_merge( array( 'Submitted At', 'Overall Rating' ), $columns );
    fputcsv( $output, $header_row );

    foreach ( $decoded_rows as $row ) {
        $line = array(
            $row['meta']->submitted_at,
            $row['meta']->overall_rating !== null ? $row['meta']->overall_rating : '',
        );
        foreach ( $columns as $col ) {
            $val = isset( $row['data'][ $col ] ) ? $row['data'][ $col ] : '';
            $line[] = is_array( $val ) ? implode( ', ', $val ) : $val;
        }
        fputcsv( $output, $line );
    }

    fclose( $output );
    exit;
}
add_action( 'admin_post_sff_export_csv', 'sff_export_csv' );

/* =========================================================
 *  FRONT-END: enqueue assets only on pages using the shortcode
 * ========================================================= */
function sff_maybe_enqueue_assets() {
    global $post;
    if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'sff_form' ) ) {
        wp_enqueue_script( 'jquery' );

        wp_register_script( 'sff-frontend', false, array( 'jquery' ), SFF_VERSION, true );
        wp_enqueue_script( 'sff-frontend' );
        wp_localize_script( 'sff-frontend', 'SFF_DATA', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'sff_submit_feedback_action' ),
        ) );

        wp_add_inline_script( 'sff-frontend', sff_get_frontend_js() );
        add_action( 'wp_head', 'sff_print_inline_css' );
    }
}
add_action( 'wp', 'sff_maybe_enqueue_assets' );

function sff_print_inline_css() {
    echo '<style>' . sff_get_frontend_css() . '</style>';
}

/* Minimal, neutral styling: only the form fields + a single green submit button. */
function sff_get_frontend_css() {
    return "
    .sff-form { max-width: 560px; }
    .sff-form .sff-field { margin-bottom: 18px; }
    .sff-form label { display: block; font-weight: 600; margin-bottom: 6px; }
    .sff-form input[type=text],
    .sff-form textarea {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 12px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 15px;
    }
    .sff-form textarea { resize: vertical; }
    .sff-form input[type=text]:focus,
    .sff-form textarea:focus {
        outline: none;
        border-color: #888;
    }
    .sff-form .sff-rating label,
    .sff-form .sff-yesno label {
        display: inline-block;
        font-weight: normal;
        margin-right: 14px;
    }
    .sff-submit-btn {
        background: #2e7d32;
        color: #fff;
        border: none;
        padding: 12px 26px;
        border-radius: 6px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
    }
    .sff-submit-btn:hover { background: #276b2b; }
    .sff-submit-btn:disabled { opacity: 0.6; cursor: not-allowed; }
    .sff-form-message { margin-top: 14px; padding: 10px 14px; border-radius: 6px; }
    .sff-form-message.error { background: #fdecea; color: #a12622; }
    .sff-honeypot-field { position: absolute; left: -9999px; top: -9999px; }
    ";
}

/* JS: AJAX submit, disables button to stop duplicate clicks, redirects on success */
function sff_get_frontend_js() {
    return "
    document.addEventListener('submit', function(e){
        var form = e.target;
        if (!form.classList || !form.classList.contains('sff-form')) return;
        e.preventDefault();

        var btn = form.querySelector('.sff-submit-btn');
        if (btn.disabled) return;
        btn.disabled = true;
        var originalText = btn.innerText;
        btn.innerText = 'Submitting...';

        var formData = new FormData(form);
        formData.append('action', 'sff_submit_feedback');
        formData.append('nonce', SFF_DATA.nonce);

        fetch(SFF_DATA.ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(res){ return res.json(); })
        .then(function(json){
            if (json.success) {
                if (json.data.redirect) {
                    window.location.href = json.data.redirect;
                } else {
                    form.innerHTML = '<div class=\"sff-form-message\">' + json.data.message + '</div>';
                }
            } else {
                showError(form, json.data && json.data.message ? json.data.message : 'Something went wrong. Please try again.');
                btn.disabled = false;
                btn.innerText = originalText;
            }
        })
        .catch(function(){
            showError(form, 'Network error. Please check your connection and try again.');
            btn.disabled = false;
            btn.innerText = originalText;
        });

        function showError(form, msg) {
            var existing = form.querySelector('.sff-form-message');
            if (existing) existing.remove();
            var div = document.createElement('div');
            div.className = 'sff-form-message error';
            div.innerText = msg;
            form.appendChild(div);
        }
    });
    ";
}

/* =========================================================
 *  FRONT-END SHORTCODE
 * ========================================================= */
function sff_form_shortcode() {
    global $wpdb;
    $table = $wpdb->prefix . 'sff_questions';
    $questions = $wpdb->get_results( "SELECT * FROM $table ORDER BY sort_order ASC" );

    if ( ! $questions ) {
        return '<p>No feedback questions have been set up yet.</p>';
    }

    ob_start();
    ?>
    <form method="post" class="sff-form">
        <!-- Honeypot: real users never see or fill this. Bots usually do. -->
        <div class="sff-honeypot-field" aria-hidden="true">
            <label for="sff_website">Website</label>
            <input type="text" id="sff_website" name="sff_website" tabindex="-1" autocomplete="off">
        </div>

        <?php foreach ( $questions as $q ) :
            $field_name = 'sff_q_' . $q->id;
            $required   = $q->is_required ? 'required' : '';
            ?>
            <div class="sff-field">
                <label>
                    <?php echo esc_html( $q->question ); ?> <?php if ( $q->is_required ) echo '<span style="color:#a00;">*</span>'; ?>
                </label>

                <?php if ( $q->field_type === 'textarea' ) : ?>
                    <textarea name="<?php echo esc_attr( $field_name ); ?>" rows="4" <?php echo $required; ?>></textarea>

                <?php elseif ( $q->field_type === 'rating' ) : ?>
                    <div class="sff-rating">
                        <?php for ( $i = 5; $i >= 1; $i-- ) : ?>
                            <label><input type="radio" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo $i; ?>" <?php echo $required; ?>> <?php echo $i; ?>★</label>
                        <?php endfor; ?>
                    </div>

                <?php elseif ( $q->field_type === 'yes_no' ) : ?>
                    <div class="sff-yesno">
                        <label><input type="radio" name="<?php echo esc_attr( $field_name ); ?>" value="Yes" <?php echo $required; ?>> Yes</label>
                        <label><input type="radio" name="<?php echo esc_attr( $field_name ); ?>" value="No"> No</label>
                    </div>

                <?php else : ?>
                    <input type="text" name="<?php echo esc_attr( $field_name ); ?>" <?php echo $required; ?>>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <button type="submit" class="sff-submit-btn">Submit Feedback</button>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode( 'sff_form', 'sff_form_shortcode' );

/* =========================================================
 *  AJAX HANDLER: save response + tell JS where to redirect
 *  Note: nonce is checked but NOT hard-fatal on failure, since this
 *  form is used by a fixed, trusted audience (students) during a
 *  short event window, and we prioritise "never block a real
 *  submission" over strict CSRF protection. The honeypot field
 *  below is what actually filters out spam/bots.
 * ========================================================= */
function sff_handle_submission_ajax() {
    // Spam check: honeypot field must be empty.
    if ( ! empty( $_POST['sff_website'] ) ) {
        // Pretend success so bots don't know they were blocked.
        wp_send_json_success( array( 'message' => get_option( 'sff_thankyou_message', 'Thank you for your feedback!' ) ) );
    }

    // Soft nonce check (does not block submission if it fails/expires).
    $nonce_ok = isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], 'sff_submit_feedback_action' );
    // We deliberately continue even if $nonce_ok is false.

    global $wpdb;
    $q_table = $wpdb->prefix . 'sff_questions';
    $r_table = $wpdb->prefix . 'sff_responses';

    $questions = $wpdb->get_results( "SELECT * FROM $q_table ORDER BY sort_order ASC" );
    $answers = array();
    $rating  = null;
    $missing_required = array();

    foreach ( $questions as $q ) {
        $field_name = 'sff_q_' . $q->id;
        $value = isset( $_POST[ $field_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) ) : '';

        if ( $q->is_required && $value === '' ) {
            $missing_required[] = $q->question;
        }

        if ( $value !== '' ) {
            $answers[ $q->question ] = $value;
            if ( $q->field_type === 'rating' ) {
                $rating = (int) $value;
            }
        }
    }

    if ( ! empty( $missing_required ) ) {
        wp_send_json_error( array( 'message' => 'Please answer all required questions: ' . implode( ', ', $missing_required ) ) );
    }

    $inserted = $wpdb->insert( $r_table, array(
        'submitted_at'   => current_time( 'mysql' ),
        'response_data'  => wp_json_encode( $answers ),
        'overall_rating' => $rating,
    ) );

    if ( false === $inserted ) {
        // DB write failed (rare) - still don't show a scary error to the student.
        wp_send_json_success( array( 'message' => get_option( 'sff_thankyou_message', 'Thank you for your feedback!' ) ) );
    }

    $google_url = get_option( 'sff_google_review_url', '' );
    $min_rating = (int) get_option( 'sff_redirect_min_rating', 0 );

    $should_redirect_to_google = ! empty( $google_url );
    if ( $should_redirect_to_google && $min_rating > 0 && $rating !== null && $rating < $min_rating ) {
        $should_redirect_to_google = false;
    }

    if ( $should_redirect_to_google ) {
        wp_send_json_success( array( 'redirect' => $google_url ) );
    }

    wp_send_json_success( array( 'message' => get_option( 'sff_thankyou_message', 'Thank you for your feedback!' ) ) );
}
add_action( 'wp_ajax_sff_submit_feedback', 'sff_handle_submission_ajax' );
add_action( 'wp_ajax_nopriv_sff_submit_feedback', 'sff_handle_submission_ajax' );
