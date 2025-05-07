/**
 * Обработчик поиска города с задержкой (debounce) и AJAX-запросом к backend'у WordPress.
 * Выводит результат в блок #city-search-results.
 */

jQuery(document).ready(function ($) {
    let typingTimer;
    const delay = 1000;

    $('#city-search').on('input', function () {
        $('#city-search-results').text("вникаем...");
        clearTimeout(typingTimer);
        let value = $(this).val();

        // setTimeout для оптимизации запросов 
        typingTimer = setTimeout(function () {
            $('#city-search-results').text("ищем...");
            $.ajax({
                url: citySearchData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'city_search',
                    city_search: value,
                    _ajax_nonce: citySearchData.nonce
                },
                success: function (response) {
                    $('#city-search-results').text(response);
                }
            });
        }, delay);
    });
});
