<?php
if (!defined('ABSPATH')) exit;
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CSMFW_States_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'csmfw_state',
            'plural'   => 'csmfw_states',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'         => '<input type="checkbox" />',
            'title'      => 'Custom State',
            'state_code' => 'ZIP / Postcode',
            'custom_zone' => 'Zone',
            'author'     => 'Author',
            'date'       => 'Date',
        ];
    }

    public function get_sortable_columns() {
        return [
            'title' => ['title', false],
            'date'  => ['date', false],
        ];
    }

    public function get_views() {
        $counts = wp_count_posts('csmfw_state');
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_status = isset( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : '';
        $url = admin_url('admin.php?page=csmfw-states');
        $views = [];

        $views['all'] = sprintf(
            '<a href="%s"%s>All <span class="count">(%d)</span></a>',
            esc_url($url),
            ($current_status === '' || $current_status === 'all' ? ' class="current"' : ''),
            $counts->publish + $counts->draft + $counts->private
        );

        if ($counts->trash > 0) {
            $trash_url = add_query_arg(['post_status' => 'trash'], $url);
            $views['trash'] = sprintf(
                '<a href="%s"%s>Trash <span class="count">(%d)</span></a>',
                esc_url($trash_url),
                ($current_status === 'trash' ? ' class="current"' : ''),
                $counts->trash
            );
        }

        return $views;
    }

    public function get_bulk_actions() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $post_status = isset( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : '';
        if ($post_status === 'trash') {
            return [
                'restore' => 'Restore',
                'delete_permanently' => 'Delete Permanently',
            ];
        }
        return ['delete' => 'Move to Trash'];
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="state[]" value="%s" />', $item->ID);
    }

    public function column_title($item) {
        $status = get_post_status($item);
        $actions = [];

        if ($status === 'trash') {
            $actions['restore'] = '<a href="' . esc_url(admin_url('admin.php?page=csmfw-states&restore=' . $item->ID)) . '">Restore</a>';
            $actions['delete']  = '<a href="' . esc_url(admin_url('admin.php?page=csmfw-states&delete=' . $item->ID)) . '" onclick="return confirm(\'Delete permanently?\')">Delete Permanently</a>';
        } else {
            $edit_link = wp_nonce_url( admin_url( 'admin.php?page=csmfw-states-edit&id=' . $item->ID ), 'csmfw_edit_state_action' );
            $actions['edit'] = '<a href="' . esc_url( $edit_link ) . '">Edit</a>';
            $actions['trash'] = '<a href="' . esc_url(admin_url('admin.php?page=csmfw-states&trash=' . $item->ID)) . '" onclick="return confirm(\'Move to Trash?\')">Trash</a>';
        }

        return sprintf('%1$s %2$s', esc_html($item->post_title), $this->row_actions($actions));
    }

    public function column_state_code($item) {
        return esc_html(get_post_meta($item->ID, 'state_code', true));
    }

    public function column_custom_zone($item) {
        return esc_html(get_post_meta($item->ID, 'custom_zone', true));
    }

    public function column_author($item) {
        $author_id = $item->post_author;
        return esc_html(get_the_author_meta('display_name', $author_id));
    }

    public function column_date($item) {
        return esc_html(get_the_date('', $item));
    }

    public function no_items() {
        echo '<td colspan="4" class="colspanchange">No custom shipping states found.</td>';
    }

    public function prepare_items() {
        $per_page = 10;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $paged = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $search = isset($_GET['s']) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $requested_status = isset( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : '';
        $post_status = $requested_status === 'trash' ? 'trash' : 'any';

        $args = [
            'post_type'      => 'csmfw_state',
            'post_status'    => $post_status,
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            's'              => $search,
        ];

        $query = new WP_Query($args);
        $this->items = $query->posts;

        $this->set_pagination_args([
            'total_items' => $query->found_posts,
            'per_page'    => $per_page,
            'total_pages' => $query->max_num_pages,
        ]);
    }

    public function display_rows() {
        foreach ($this->items as $item) {
            echo '<tr>';
            foreach ($this->get_columns() as $column_name => $column_display_name) {
                switch ($column_name) {
                    case 'cb':
                        echo '<th scope="row" class="check-column">' . wp_kses( $this->column_cb( $item ), [
                            'input' => [
                                'type'  => true,
                                'name'  => true,
                                'value' => true,
                                'id'    => true,
                                'class' => true,
                                'checked' => true,
                            ],
                        ] ) . '</th>';
                        break;
                    case 'title':
                        echo '<td class="title column-title">' . wp_kses_post( $this->column_title( $item ) ) . '</td>';
                        break;
                    case 'state_code':
                        echo '<td>' . wp_kses_post( $this->column_state_code( $item ) ) . '</td>';
                        break;
                    case 'custom_zone':
                        echo '<td>' . wp_kses_post( $this->column_custom_zone( $item ) ) . '</td>';
                        break;
                    case 'author':
                        echo '<td>' . wp_kses_post( $this->column_author( $item ) ) . '</td>';
                        break;
                    case 'date':
                        echo '<td>' . wp_kses_post( $this->column_date( $item ) ) . '</td>';
                        break;
                    default:
                        echo '<td>' . (isset($item->$column_name) ? esc_html($item->$column_name) : '') . '</td>';
                        break;
                }
            }
            echo '</tr>';
        }
    }

    public function display() {
        $this->display_tablenav('top');

        echo '<table class="wp-list-table widefat fixed striped">';

        echo '<thead>
            <tr>
                <td class="manage-column column-cb check-column">
                    <input type="checkbox" />
                </td>
                <th scope="col" class="manage-column column-title column-primary">Custom State</th>
                <th scope="col" class="manage-column">ZIP / Postcode</th>
                <th scope="col" class="manage-column">Zone</th>
                <th scope="col" class="manage-column">Author</th>
                <th scope="col" class="manage-column">Date</th>
            </tr>
        </thead>';

        echo '<tbody id="the-list">';
        $this->display_rows_or_placeholder();
        echo '</tbody>';

        echo '<tfoot>
            <tr>
                <td class="manage-column column-cb check-column">
                    <input type="checkbox" />
                </td>
                <th scope="col" class="manage-column column-title column-primary">Custom State</th>
                <th scope="col" class="manage-column">ZIP / Postcode</th>
                <th scope="col" class="manage-column">Zone</th>
                <th scope="col" class="manage-column">Author</th>
                <th scope="col" class="manage-column">Date</th>
            </tr>
        </tfoot>';

        echo '</table>';

        $this->display_tablenav('bottom');
    }
}
