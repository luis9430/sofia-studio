<?php
/**
 * Header mínimo — en v1 no hay header/footer globales sincronizados (ver
 * la memoria de producto: eso es Nivel 2, fuera de alcance). Este es un
 * esqueleto HTML fijo, no editable todavía.
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
