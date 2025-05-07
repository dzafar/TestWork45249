<?
// Подключение скрипта и передача ajaxurl + nonce
function enqueue_city_search_script()
{
    wp_enqueue_script('main', get_stylesheet_directory_uri() . '/assets/js/main.js', array('jquery'), null, true);

    wp_localize_script('main', 'citySearchData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('city_search_nonce')
    ));
}

add_action('wp_enqueue_scripts', 'enqueue_city_search_script');

// Регистрирует post type Cities с поддержкой REST, архива, миниатюр и т.д.
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

// Регистрирует метабокс для записей типа cities с названием City Coordinates
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

// Функция для отображения полей метабокса "City Coordinates"
function city_coordinates_metabox($post)
{
    wp_nonce_field('save_city_coordinates', 'city_coordinates_nonce');
    // проверяем строки на xss или вредоностные вставки 
    $latitude = sanitize_text_field(get_post_meta($post->ID, 'latitude', true));
    $longitude = sanitize_text_field(get_post_meta($post->ID, 'longitude', true));
?>
    <label for="latitude">Latitude (Широта):</label>
    <input type="number" name="latitude" id="latitude" value="<?php echo esc_attr($latitude); ?>" style="width:100%;" />

    <label for="longitude">Longitude (Долгота):</label>
    <input type="number" name="longitude" id="longitude" value="<?php echo esc_attr($longitude); ?>" style="width:100%;" />
<?php
}

// Функция для сохранения широты и долготы из метабокса City Coordinates
add_action('save_post', function ($post_id) {
    // Проверяем, не является ли сохранение автоматическим
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    // Проверяем nonce для предотвращения подделки запроса
    if (!isset($_POST['city_coordinates_nonce']) || !wp_verify_nonce($_POST['city_coordinates_nonce'], 'save_city_coordinates')) {
        return;
    }

    // Проверяем, имеет ли пользователь право редактировать этот пост
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Обрабатываем широту: удаляем лишние пробелы и проверяем, соответствует ли она допустимому формату координат
    if (isset($_POST['latitude'])) {
        $lat = trim($_POST['latitude']);
        if (preg_match('/^-?\d{1,2}(\.\d+)?$/', $lat)) {
            update_post_meta($post_id, 'latitude', $lat);
        }
    }

    // Обрабатываем долготу: удаляем лишние пробелы и проверяем, соответствует ли она допустимому формату координат
    if (isset($_POST['longitude'])) {
        $lng = trim($_POST['longitude']);
        if (preg_match('/^-?\d{1,3}(\.\d+)?$/', $lng)) {
            update_post_meta($post_id, 'longitude', $lng);
        }
    }
});

// Функция для сохранения полей метабокса City Coordinates
add_action('save_post', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['city_coordinates_nonce']) || !wp_verify_nonce($_POST['city_coordinates_nonce'], 'save_city_coordinates')) return;

    // проверка прав
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['latitude'])) {
        update_post_meta($post_id, 'latitude', sanitize_text_field($_POST['latitude']));
    }

    if (isset($_POST['longitude'])) {
        update_post_meta($post_id, 'longitude', sanitize_text_field($_POST['longitude']));
    }
});

// Регистрирует кастомную таксономию "countries" для типа записи cities
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

    // привязка к cities
    register_taxonomy('countries', 'cities', $args);
}
add_action('init', 'create_countries_taxonomy', 0);


// получние погоды через апи openweathermap
function get_city_weather_data($city = '')
{
    $apiKey = '5df5946157fdc7921c13204ba070d414';
    // Да, я оставил ключ тут, чтобы вам было проще запускать =)
    // В боевом проекте я бы вынес его в файл config.php и спрятал бы через gitignore

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

// Регистрация виджета погоды с выбором города из записей cities
class Simple_Weather_Widget extends WP_Widget
{
    // Конструктор: задаёт ID и имя виджета
    public function __construct()
    {
        parent::__construct(
            'simple_weather_widget',
            'Simple Weather Widget'
        );
    }

    // Отображение виджета на фронте
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

    // Форма настройки виджета в админке
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

    // Сохранение настроек виджета
    public function update($new_instance, $old_instance)
    {
        $instance = [];
        $instance['city'] = strip_tags($new_instance['city']);
        return $instance;
    }
}

// Регистрирует виджет Simple_Weather_Widget
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
    if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce($_POST['_ajax_nonce'], 'city_search_nonce')) {
        wp_die('Доступ запрещён');
    }

    $ip = $_SERVER['REMOTE_ADDR'];
    $key = 'city_search_limit_' . md5($ip);

    // от DDoS 
    if (false !== get_transient($key)) {
        wp_die('Попробуйте позже');
    }
    set_transient($key, 1, 1);

    if (!empty($_POST['city_search'])) {
        $search_term = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', sanitize_text_field($_POST['city_search']));
        $city = get_city_weather_data($search_term);

        if (!empty($city) && isset($city['temp'])) {
            echo esc_html($search_term) . ' ' . esc_html($city['temp']) . '°C';
        } else {
            echo "Не нашли";
        }
    }

    wp_die();
}

add_action('wp_ajax_city_search', 'city_search_ajax_handler');
add_action('wp_ajax_nopriv_city_search', 'city_search_ajax_handler');
