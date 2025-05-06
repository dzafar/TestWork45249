jQuery(document).ready(function ($) {
    $('#city-search').on('input', function () {
        $('#city-search-results').text("ищем...");
        let value = $(this).val();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'city_search',
                city_search: value
            },
            success: function (response) {
                $('#city-search-results').text(response);
            }
        });
    });
});
