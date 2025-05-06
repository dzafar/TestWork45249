<?
add_action('init', function () {
    register_post_type('cities', [
        'labels' => [
            'name'          => 'Cities',
            'singular_name' => 'City',
        ],
        'public'       => true,
        'has_archive'  => true,
        'rewrite'      => ['slug' => 'cities'],
        'show_in_rest' => true,
        'supports'     => ['title', 'editor', 'thumbnail'],
        'menu_position' => 5,
        'menu_icon'    => 'dashicons-location',
    ]);
});

function create_countries_taxonomy()
{
    $args = array(
        'hierarchical' => true,
        'labels' => array(
            'name'              => 'Countries',
            'singular_name'     => 'Country',
            'search_items'      => 'Search Countries',
            'all_items'         => 'All Countries',
            'parent_item'       => 'Parent Country',
            'parent_item_colon' => 'Parent Country:',
            'edit_item'         => 'Edit Country',
            'update_item'       => 'Update Country',
            'add_new_item'      => 'Add New Country',
            'new_item_name'     => 'New Country Name',
            'menu_name'         => 'Countries',
        ),
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'country'),
    );
    register_taxonomy('countries', 'cities', $args);
}
add_action('init', 'create_countries_taxonomy', 0);

add_action('add_meta_boxes', function () {
    add_meta_box(
        'city_coordinates',
        'City Coordinates',
        'city_coordinates_metabox',
        'cities',
        'normal',
        'high'
    );
});

function city_coordinates_metabox($post)
{
    $latitude = get_post_meta($post->ID, 'latitude', true);
    $longitude = get_post_meta($post->ID, 'longitude', true);
?>
    <label for="latitude">Latitude (Широта):</label>
    <input type="text" name="latitude" id="latitude" value="<?php echo esc_attr($latitude); ?>" style="width:100%;" />

    <label for="longitude">Longitude (Долгота):</label>
    <input type="text" name="longitude" id="longitude" value="<?php echo esc_attr($longitude); ?>" style="width:100%;" />
<?php
}

add_action('save_post', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    if (isset($_POST['latitude'])) {
        update_post_meta($post_id, 'latitude', sanitize_text_field($_POST['latitude']));
    }

    if (isset($_POST['longitude'])) {
        update_post_meta($post_id, 'longitude', sanitize_text_field($_POST['longitude']));
    }
});


// получние погоды
function get_city_weather_data($city = 'Moscow')
{
    $apiKey = '5df5946157fdc7921c13204ba070d414';
    $url = "https://api.openweathermap.org/data/2.5/weather?q={$city}&appid={$apiKey}&units=metric&lang=ru";

    $response = wp_remote_get($url);
    if (is_wp_error($response)) return false;

    $data = json_decode(wp_remote_retrieve_body($response));
    if (!isset($data->main->temp)) return false;

    return [
        'city' => $city,
        'temp' => round($data->main->temp)
    ];
}

// регистрация виджета 
class Simple_Weather_Widget extends WP_Widget
{
    public function __construct()
    {
        parent::__construct(
            'simple_weather_widget',
            'Simple Weather Widget'
        );
    }

    public function widget($args, $instance)
    {
        $city_id = !empty($instance['city']) ? $instance['city'] : '';
        if (!$city_id) return;

        $city_name = get_the_title($city_id);
        $data = get_city_weather_data($city_name);
        if (!$data) return;

        echo $args['before_widget'];
        echo '<p>Город: ' . esc_html($data['city']) . '</p>';
        echo '<p>Температура: ' . esc_html($data['temp']) . '°C</p>';
        echo $args['after_widget'];
    }

    public function form($instance)
    {
        $selected = !empty($instance['city']) ? $instance['city'] : '';
        $cities = get_posts([
            'post_type' => 'cities',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);

        echo '<p><label for="' . esc_attr($this->get_field_id('city')) . '">Выберите город:</label>';
        echo '<select class="widefat" id="' . esc_attr($this->get_field_id('city')) . '" name="' . esc_attr($this->get_field_name('city')) . '">';
        foreach ($cities as $city) {
            $selected_attr = $selected == $city->ID ? 'selected' : '';
            echo '<option value="' . esc_attr($city->ID) . '" ' . $selected_attr . '>' . esc_html($city->post_title) . '</option>';
        }
        echo '</select></p>';
    }

    public function update($new_instance, $old_instance)
    {
        $instance = [];
        $instance['city'] = strip_tags($new_instance['city']);
        return $instance;
    }
}

add_action('widgets_init', function () {
    register_widget('Simple_Weather_Widget');
});


// регистрация места вывода виджета 
function register_custom_sidebar()
{
    register_sidebar([
        'name' => 'Custom Sidebar',
        'id' => 'custom_sidebar',
        'before_widget' => '<div class="widget %2$s">',
        'after_widget' => '</div>',
        'before_title' => '<h3>',
        'after_title' => '</h3>',
    ]);
}
add_action('widgets_init', 'register_custom_sidebar');


// ajax вывод поиска
function city_search_ajax_handler()
{
    if (!empty($_POST['city_search'])) {
        $city = get_city_weather_data(sanitize_text_field($_POST['city_search']));

        if (!empty($city) && isset($city['temp'])) {
            echo $_POST['city_search'] . ' ' . $city['temp'] . '°C';
        } else {
            echo "не нашли";
        }
    }
    wp_die();
}


add_action('wp_ajax_city_search', 'city_search_ajax_handler');
add_action('wp_ajax_nopriv_city_search', 'city_search_ajax_handler');

// подключение скриптов
function enqueue_city_search_script()
{
    wp_enqueue_script('main', get_stylesheet_directory_uri() . '/assets//js/main.js', array('jquery'), null, true);
    wp_localize_script('main', 'ajaxurl', admin_url('admin-ajax.php'));
}

add_action('wp_enqueue_scripts', 'enqueue_city_search_script');
