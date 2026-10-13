<?php
/**
 * Questions for the homepage "Myth or fact?" quiz.
 *
 * Each question points at a published fact by its slug. The answer the
 * reader sees is that fact's claim and source, read from the fact tables,
 * so nothing here is a fact in itself. A question whose fact is not
 * published is left out.
 *
 * "statement" is what the reader judges. For a myth it is the popular
 * belief, worded as in the "History Myths Debunked" article. For a fact it
 * may be empty, and the fact's own title is used.
 *
 * @package FactFusion_AI_Core
 */

defined( 'ABSPATH' ) || exit;

return array(
	array(
		'statement' => 'The Great Wall of China can be seen from the Moon.',
		'answer'    => 'myth',
		'slug'      => 'the-great-wall-of-china-is-not-visible-from-the-moon',
	),
	array(
		'statement' => '',
		'answer'    => 'fact',
		'slug'      => 'a-day-on-venus-is-longer-than-its-year',
	),
	array(
		'statement' => 'Vikings wore horned helmets.',
		'answer'    => 'myth',
		'slug'      => 'the-only-surviving-viking-helmet-has-no-horns',
	),
	array(
		'statement' => '',
		'answer'    => 'fact',
		'slug'      => 'saturn-is-less-dense-than-water',
	),
	array(
		'statement' => 'The Declaration of Independence was signed on July 4, 1776.',
		'answer'    => 'myth',
		'slug'      => 'the-declaration-of-independence-was-not-signed-on-july-4',
	),
	array(
		'statement' => '',
		'answer'    => 'fact',
		'slug'      => 'jellyfish-have-no-brain-blood-or-heart',
	),
);
