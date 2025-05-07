<?php get_header(); ?>

<h2>Вывод виджета из админки</h2>

<?
// Проверяет, активен ли сайдбар с идентификатором 'custom_sidebar'
//  и выводит его содержимое, если он активен
if (is_active_sidebar('custom_sidebar')) {
    dynamic_sidebar('custom_sidebar');
}
?>

<?php get_footer(); ?>