<?php
/*
Template Name: Cities Weather Table
*/

global $wpdb;

$cities = $wpdb->get_results("
    SELECT p.ID, p.post_title AS city, t.name AS country
    FROM {$wpdb->prefix}posts p
    LEFT JOIN {$wpdb->prefix}term_relationships tr ON (p.ID = tr.object_id)
    LEFT JOIN {$wpdb->prefix}term_taxonomy tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'countries')
    LEFT JOIN {$wpdb->prefix}terms t ON (tt.term_id = t.term_id)
    WHERE p.post_type = 'cities' AND p.post_status = 'publish'
");
?>

<?php get_header(); ?>

<div class="cities-table">
    <form id="city-search-form" action="#" method="POST">
        <div id="city-search-container" style="margin-bottom:20px;">
            <input type="text" name="city_search" id="city-search" placeholder="Введите город..." autocomplete="off">
            <div id="city-search-results"></div>
        </div>
    </form>

    <?php do_action('before_city_table'); ?>

    <table>
        <thead>
            <tr>
                <th>Страна</th>
                <th>Город</th>
                <th>Температура</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($cities) {
                foreach ($cities as $city) {
                    $city_name = $city->city;
                    $temp_data = get_city_weather_data($city_name);
                    $temp = $temp_data ? $temp_data['temp'] . '°C' : 'н/д';

                    echo '<tr>';
                    echo '<td>' . esc_html($city->country) . '</td>';
                    echo '<td>' . esc_html($city_name) . '</td>';
                    echo '<td>' . esc_html($temp) . '</td>';
                    echo '</tr>';
                }
            }
            ?>
        </tbody>
    </table>

    <?php do_action('after_city_table'); ?>
</div>

<?php get_footer(); ?>