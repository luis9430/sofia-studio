<?php
/**
 * Lo mínimo de WordPress que necesitan las clases del tema para correr
 * fuera de WordPress.
 *
 * Existe para que los scripts de tests/ puedan cargar
 * Sofia_Componente/Sofia_Definicion_Generada sin levantar un WordPress
 * entero: son clases de lógica pura (validar una descripción, renderizar
 * un árbol de datos) y arrancar un sitio para probarlas costaría más de
 * lo que aporta.
 *
 * Cada función hace lo mínimo que el código bajo prueba espera, no una
 * imitación fiel. wp_kses(), por ejemplo, deja pasar las etiquetas de
 * formato básico y nada más — suficiente para que el render produzca el
 * mismo HTML, sin reimplementar el sanitizador de WordPress.
 */

if ( ! function_exists( '__' ) ) {
	function __( $t, $d = '' ) {
		return $t;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $t ) {
		return htmlspecialchars( (string) $t, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $t ) {
		return htmlspecialchars( (string) $t, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $t ) {
		return (string) $t;
	}
}

if ( ! function_exists( 'wp_kses' ) ) {
	function wp_kses( $t, $a = array() ) {
		return strip_tags( (string) $t, '<strong><em><b><i><br>' );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $t ) {
		return strip_tags( (string) $t );
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $n, $d = false ) {
		return $d;
	}
}
