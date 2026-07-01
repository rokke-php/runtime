<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
	->in([
		__DIR__ . '/src',
		__DIR__ . '/tests',
	])
	->append([
		__FILE__,
	]);

$config = new Config();

return $config
	->setFinder($finder)
	->setRiskyAllowed(true)
	->setIndent("\t")
	->setRules([
		'@PSR12' => true,
		'@PSR12:risky' => true,
		'@PHP8x4Migration' => true,
		'nullable_type_declaration_for_default_null_value' => true,
		'declare_strict_types' => true,
		'ordered_imports' => [
			'sort_algorithm' => 'alpha',
		],
		'no_unused_imports' => true,
		'trailing_comma_in_multiline' => [
			'after_heredoc' => true,
			'elements' => ['arrays', 'arguments', 'match'],
		],
		'single_quote' => true,
		'concat_space' => [
			'spacing' => 'one',
		],
		'cast_spaces' => [
			'space' => 'single',
		],
		'magic_method_casing' => true,
		'native_function_casing' => true,
		'no_blank_lines_after_class_opening' => true,
		'no_whitespace_before_comma_in_array' => true,
		'self_accessor' => true,
		'short_scalar_cast' => true,
		'single_trait_insert_per_statement' => true,
		'standardize_not_equals' => true,
		'ternary_operator_spaces' => true,
		'unary_operator_spaces' => true,
		'array_syntax' => [
			'syntax' => 'short',
		],
		'combine_consecutive_issets' => true,
		'combine_consecutive_unsets' => true,
		'no_alias_language_construct_call' => true,
		'no_alternative_syntax' => true,
		'no_superfluous_elseif' => true,
		'no_unneeded_control_parentheses' => true,
		'no_useless_else' => true,
		'no_useless_return' => true,
		'return_assignment' => true,
		'simplified_if_return' => true,
		'simplified_null_return' => true,
		'single_line_empty_body' => true,
		'void_return' => true,
		'php_unit_construct' => true,
		'php_unit_dedicate_assert' => true,
		'php_unit_method_casing' => [
			'case' => 'camel_case',
		],
		'php_unit_set_up_tear_down_visibility' => true,
	])
	->setLineEnding("\n");
