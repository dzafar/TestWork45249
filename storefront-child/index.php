<?php get_header(); ?>

<h2>Вывод виджета из админки</h2>
<?
if (is_active_sidebar('custom_sidebar')) {
    dynamic_sidebar('custom_sidebar');
}
?>

<?php get_footer(); ?>