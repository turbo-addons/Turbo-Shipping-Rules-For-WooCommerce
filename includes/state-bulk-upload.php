<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) return;

$message = '';

// Handle form submission
if (
    isset($_SERVER['REQUEST_METHOD']) &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['_wpnonce']) &&
    wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'csmfw_bulk_upload_action')
) {
    if (!empty($_FILES['csv_file']['tmp_name'])) {

        // ✅ File type validation
        $allowed_mimes = ['text/csv', 'text/plain', 'application/vnd.ms-excel'];
        $file_tmp_path = sanitize_text_field($_FILES['csv_file']['tmp_name']);
        $mime = mime_content_type($file_tmp_path);

        if (!in_array($mime, $allowed_mimes, true)) {
            $message = "<div class='notice notice-error is-dismissible'><p>Invalid file type. Please upload a valid CSV file.</p></div>";
        } else {
            // ✅ Use WP_Filesystem to read file
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }

            $file_contents = $wp_filesystem->get_contents($file_tmp_path);

            if ($file_contents === false) {
                $message = "<div class='notice notice-error is-dismissible'><p>Failed to read the uploaded CSV file.</p></div>";
            } else {
                // ✅ Sanitize & Normalize CSV
                $file_contents = preg_replace('/^\xEF\xBB\xBF/', '', $file_contents); // Remove BOM
                $lines = preg_split('/\r\n|\r|\n/', $file_contents);

                $added = 0;
                $skipped = 0;
                $row = 0;

                foreach ($lines as $line) {
                    $row++;
                    if (empty(trim($line))) continue;
                    if ($row === 1) continue; // Skip header

                    $data = str_getcsv($line); // Parse as CSV line

                    $name    = isset($data[0]) ? sanitize_text_field($data[0]) : '';
                    $code    = !empty($data[1]) ? strtoupper(sanitize_key($data[1])) : strtoupper(sanitize_key($name));
                    $country = !empty($data[2]) ? strtoupper(sanitize_key($data[2])) : 'BD';
                    $custom_zone = !empty($data[3]) ? sanitize_text_field($data[3]) : ''; // <-- added custom_zone

                    if (empty($name) || empty($country)) {
                        $skipped++;
                        continue;
                    }

                    // ✅ Check for duplicate
                    $existing = new WP_Query([
                        'post_type'      => 'csmfw_state',
                        'post_status'    => 'publish',
                        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This query runs on a small dataset, acceptable here.
                        'meta_query'     => [
                            'relation' => 'AND',
                            [
                                'key'   => 'state_code',
                                'value' => $code,
                            ],
                            [
                                'key'   => 'country_code',
                                'value' => $country,
                            ],
                        ],
                        'posts_per_page' => 1,
                        'fields'         => 'ids',
                    ]);

                    if ($existing->have_posts()) {
                        $skipped++;
                        continue;
                    }

                    // ✅ Insert new state
                    $post_id = wp_insert_post([
                        'post_type'   => 'csmfw_state',
                        'post_title'  => $name,
                        'post_status' => 'publish',
                    ]);

                    if (!is_wp_error($post_id)) {
                        update_post_meta($post_id, 'state_code', $code);
                        update_post_meta($post_id, 'state_name', $name);
                        update_post_meta($post_id, 'country_code', $country);
                        update_post_meta($post_id, 'custom_zone', $custom_zone); // <-- added custom_zone
                        $added++;
                    } else {
                        $skipped++;
                    }
                }

                $message = "<div class='notice notice-success is-dismissible'><p>Bulk upload complete. <strong>{$added}</strong> added, <strong>{$skipped}</strong> skipped.</p></div>";
            }
        }
    } else {
        $message = "<div class='notice notice-error is-dismissible'><p>Please upload a valid CSV file.</p></div>";
    }
}
?>

<div class="wrap">
    <h1>Bulk Upload Shipping States</h1>

    <?php echo wp_kses_post($message); ?>

    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('csmfw_bulk_upload_action'); ?>

        <table class="form-table">
            <tr>
                <th><label for="csv_file">Upload CSV</label></th>
                <td>
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                    <p class="description">CSV must have columns: <code>state_name</code>, <code>state_code</code> (optional), <code>country_code</code> (optional, default: BD)</p>
                </td>
            </tr>
        </table>

        <?php submit_button('Upload States'); ?>
    </form>
</div>
