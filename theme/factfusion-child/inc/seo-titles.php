<?php
/**
 * Search titles and descriptions for fact pages, written from what people search in Search Console.
 *
 * Only the <title> tag and meta description change. The fact's own title, answer and text in the
 * database stay as they are, so its modified date and the Today's 5 workflow are not affected.
 * A description must say only what the fact page already states.
 *
 * 2026-11 CTR pass: these pages ranked 5-10 with almost no clicks because the title and
 * description gave the whole answer. Titles now use the searcher's question and name the
 * source; descriptions give the key context and say what the page adds (source, explanation).
 */

defined( 'ABSPATH' ) || exit;

/** Fact slug => search title and description. */
function ffc_seo_fact_overrides() {
	return array(
		'the-sun-always-lights-up-half-of-the-moon' => array(
			'title'       => 'How Much of the Moon Is Always Lit by the Sun? (NASA)',
			'description' => 'The Sun always lights one half of the Moon. So why do we see phases? See NASA\'s explanation of what changes from night to night, with the source.',
		),
		'people-have-lived-in-space-without-a-break-since-2000' => array(
			'title'       => 'How Long Have People Lived on the ISS Without a Break?',
			'description' => 'Someone has been aboard the International Space Station every day since November 2000. See what NASA says about this unbroken run, with the source.',
		),
		'your-heart-is-about-the-size-of-your-fist' => array(
			'title'       => 'Is Your Heart the Size of Your Fist? What the NIH Says',
			'description' => 'The fist rule is a real guide to heart size. See exactly what the US National Heart, Lung, and Blood Institute says, with a link to the source.',
		),
		'marss-moon-phobos-will-crash-into-mars-or-break-apart' => array(
			'title'       => 'Will Phobos Crash Into Mars? When and What Happens',
			'description' => 'Phobos is slowly moving toward Mars. NASA says it will crash or break apart in about 50 million years, maybe leaving a dusty ring. See the source.',
		),
		'no-one-died-carving-mount-rushmore' => array(
			'title'       => 'How Many People Died Making Mount Rushmore? (NPS)',
			'description' => 'Mount Rushmore took 14 years to carve, and 90 percent of the mountain was blasted with dynamite. See what the National Park Service says about deaths.',
		),
		'the-dna-in-a-single-human-cell-would-stretch-six-feet' => array(
			'title'       => 'How Long Is the DNA in One Human Cell? (NIH Answer)',
			'description' => 'Unwound and laid end to end, the DNA in one of your cells would stretch about six feet. See how the NIH genome institute puts it, with the source.',
		),
		'about-400-mg-of-caffeine-a-day-is-fine-for-most-adults' => array(
			'title'       => 'How Much Caffeine a Day Is Safe? The FDA\'s 400 mg Guide',
			'description' => 'The FDA cites about 400 mg of caffeine a day, roughly two to three 12-ounce cups of coffee, for most adults. See the FDA\'s exact wording and source.',
		),
		'the-suns-core-is-about-27-million-degrees-fahrenheit' => array(
			'title'       => 'How Hot Is the Sun\'s Core? 27 Million °F Explained',
			'description' => 'The Sun\'s core tops 27 million degrees Fahrenheit (15 million °C). See why it is that hot, in the words of NASA and the European Space Agency.',
		),
		'venus-is-the-hottest-planet-in-the-solar-system' => array(
			'title'       => 'Why Is Venus the Hottest Planet, Not Mercury? (NASA)',
			'description' => 'Venus reaches about 872°F, hot enough to melt lead, even though Mercury is closer to the Sun. See how NASA explains it, with the source.',
		),
		'neptune-is-the-only-planet-you-cannot-see-with-the-naked-eye' => array(
			'title'       => 'Which Planet Can\'t You See With the Naked Eye? (NASA)',
			'description' => 'NASA says only one planet in our solar system cannot be seen without a telescope. See which one, and how far from the Sun it is, with the source.',
		),
		'humans-have-46-chromosomes-in-23-pairs' => array(
			'title'       => 'How Many Chromosomes Do Humans Have? 46, in 23 Pairs',
			'description' => 'Humans have 46 chromosomes in 23 pairs. See how the X and Y chromosomes differ between females and males, according to the NIH genome institute.',
		),
		'the-air-you-breathe-is-78-percent-nitrogen' => array(
			'title'       => 'What Is Air Made Of? Nitrogen, Oxygen and the Rest',
			'description' => 'The air you breathe is mostly not oxygen. See NASA\'s breakdown of the air near Earth\'s surface: nitrogen, oxygen and the 1% of other gases.',
		),
		'earth-is-the-only-planet-not-named-for-a-greek-or-roman-god' => array(
			'title'       => 'Why Isn\'t Earth Named After a God? Where the Name Came From',
			'description' => 'Every other planet has a name from Greek or Roman mythology. Earth does not. See where its English name came from, according to NASA.',
		),
		'the-voyager-golden-record-carries-greetings-in-55-languages' => array(
			'title'       => 'Voyager Golden Record: How Many Languages Are on It?',
			'description' => 'Each Voyager spacecraft carries a 12-inch gold-plated record of sounds, images, music and spoken greetings from Earth. See how many languages, per NASA.',
		),
		'tsunami-waves-can-travel-as-fast-as-a-jet-plane' => array(
			'title'       => 'How Fast Can a Tsunami Travel? As Fast as a Jet (NOAA)',
			'description' => 'Over deep water a tsunami can move as fast as a jet plane. See what NOAA says about its speed and why it slows down only in shallow water.',
		),
		'the-earliest-forensic-science-manual' => array(
			'title'       => 'What Was the First Forensic Science Book? (1240s China)',
			'description' => 'Song Ci\'s Washing Away of Wrongs, from 1240s China, is the first known account of using medicine and insects to solve crimes. See why dates differ.',
		),
		'yellowstone-has-more-than-half-of-the-worlds-geysers' => array(
			'title'       => 'What Share of the World\'s Geysers Are in Yellowstone?',
			'description' => 'Yellowstone has more than 500 active geysers. See what share of all the world\'s geysers that is, according to the National Park Service.',
		),
		'jupiter-has-the-shortest-day-of-any-planet' => array(
			'title'       => 'Which Planet Has the Shortest Day? Jupiter, at 9.9 Hours',
			'description' => 'The largest planet spins the fastest. NASA says Jupiter turns once in about 9.9 hours, the shortest day in the solar system. See the source.',
		),
		'saturns-rings-are-vast-but-only-about-30-feet-thick' => array(
			'title'       => 'How Thick Are Saturn\'s Rings? Thinner Than You\'d Think',
			'description' => 'Saturn\'s rings reach up to 175,000 miles from the planet, yet NASA says the main rings are typically only about 30 feet thick. See the source.',
		),
		'a-pacific-bluefin-tuna-can-weigh-nearly-1000-pounds' => array(
			'title'       => 'How Much Does a Pacific Bluefin Tuna Weigh? Up to 990 lb',
			'description' => 'NOAA Fisheries lists the biggest reported Pacific bluefin tuna at 3 meters (9.8 feet) and 450 kilograms. See the source and the numbers in full.',
		),
		'water-pressure-rises-one-atmosphere-every-33-feet-down' => array(
			'title'       => 'How Much Does Water Pressure Increase With Depth?',
			'description' => 'Water pressure in the ocean rises by one atmosphere for every 33 feet you go down, NOAA says. See the source and what it means for the deep sea.',
		),
		'pluto-is-only-about-half-as-wide-as-the-united-states' => array(
			'title'       => 'How Big Is Pluto? Compared to the US and the Moon',
			'description' => 'Pluto is about 1,400 miles wide. NASA compares that to half the width of the United States, or two-thirds of our Moon. See the source.',
		),
		'skin-is-the-biggest-organ-of-the-human-body' => array(
			'title'       => 'What Is the Largest Organ of the Human Body? (NIH)',
			'description' => 'The US National Institutes of Health names the body\'s biggest organ and the three main layers it is made of. See the answer with its source.',
		),
		'mercury-and-venus-are-the-only-planets-without-moons' => array(
			'title'       => 'Which Planets Have No Moons? Mercury, Venus and Zoozve',
			'description' => 'Mercury and Venus are the only planets with no moons, NASA says. But Venus has a quasi-satellite named Zoozve. See what that is, with the source.',
		),
	);
}

/**
 * Guide pages whose stored meta title was set once when the page was created. Search Console
 * shows "random fact meaning" at position ~5 with no clicks, so the title now uses those words.
 */
function ffc_seo_page_overrides() {
	return array(
		'what-are-random-facts' => array(
			'title'       => 'Random Fact Meaning: What Is a Random Fact? | FactFusion AI',
			'description' => 'A random fact is a fact picked by chance. See what the term means, how it differs from a verified fact, and five questions to ask of any random fact.',
		),
	);
}

add_filter(
	'factfusion_seo_meta',
	static function ( $meta, $view ) {
		if ( 'article' !== $view || ! class_exists( 'FactFusion_Content' ) ) {
			return $meta;
		}
		$post      = FactFusion_Content::current();
		$overrides = ffc_seo_page_overrides();
		if ( $post && isset( $overrides[ $post->post_name ] ) ) {
			$meta = array_merge( $meta, $overrides[ $post->post_name ] );
		}
		return $meta;
	},
	20,
	2
);

add_filter(
	'factfusion_seo_meta',
	static function ( $meta, $view ) {
		if ( 'fact' !== $view ) {
			return $meta;
		}
		$fact      = FactFusion_Router::current_fact();
		$overrides = ffc_seo_fact_overrides();
		if ( $fact && isset( $overrides[ $fact->slug ] ) ) {
			$meta = array_merge( $meta, $overrides[ $fact->slug ] );
		}
		return $meta;
	},
	20,
	2
);
