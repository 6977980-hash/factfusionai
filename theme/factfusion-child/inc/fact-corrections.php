<?php
/**
 * One-time corrections to published facts whose wording did not match their sources
 * (flagged on the change log, 13 Oct 2026). Addresses stay the same.
 *
 * @package factfusion-child
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		if ( get_option( 'ffc_fact_fix_v2' ) || ! class_exists( 'FactFusion_Facts' ) || ! add_option( 'ffc_fact_fix_v2_lock', time(), '', false ) ) {
			return;
		}
		update_option( 'ffc_fact_fix_v2', ffc_fact_fix_v2_run(), false );
		delete_option( 'ffc_fact_fix_v2_lock' );
	},
	99
);

/** Slug => corrected text. */
function ffc_fact_fix_v2_data() {
	return array(
		'jupiters-great-red-spot-is-twice-as-wide-as-earth' => array(
			'title'        => 'Jupiter\'s Great Red Spot Is Wider Than Earth',
			'claim'        => 'Jupiter\'s Great Red Spot is about 1.3 times as wide as Earth, according to Juno-era measurements reported by NASA\'s Jet Propulsion Laboratory.',
			'short_answer' => 'Yes. NASA\'s Jet Propulsion Laboratory reports that the Great Red Spot is about 1.3 times as wide as Earth. Older NASA pages say twice as wide, because the storm has been shrinking.',
			'explanation'  => "The Great Red Spot is a giant storm in Jupiter's atmosphere, a swirling oval of clouds that has been watched for more than 300 years. JPL reports that it has been monitored since 1830 and may have existed for more than 350 years.\n\nThe storm is not a fixed size. Images taken decades apart by the Hubble Space Telescope show it shrinking. That is why NASA's general Jupiter facts page still describes it as twice as wide as Earth, while JPL, reporting measurements from the Juno mission, gives about 1.3 times as wide as Earth. We use the newer figure.\n\nJuno also used its Microwave Radiometer to look below the cloud tops and measure how deep the storm reaches, so scientists now know it is far deeper than the clouds we see.",
		),
		'the-moon-drifts-about-an-inch-farther-from-earth-every-year' => array(
			'title'        => 'The Moon Drifts About 1.5 Inches Farther From Earth Every Year',
			'claim'        => 'Laser measurements show the distance between Earth and the Moon increasing by about 1.5 inches (3.8 centimeters) a year.',
			'short_answer' => 'Yes. Laser measurements show the Moon moving about 1.5 inches (3.8 centimeters) farther from Earth each year, NASA\'s Jet Propulsion Laboratory reports. NASA\'s Moon facts page rounds this to about an inch.',
			'explanation'  => "The distance between Earth and the Moon is not fixed. Apollo astronauts left reflectors on the Moon's surface, and observatories in New Mexico, France, Italy and Germany still fire lasers at them. They time how long a pulse takes to bounce back, which fixes the Moon's distance to within a few millimeters.\n\nThese lunar laser ranging measurements show the gap growing by about 1.5 inches, or 3.8 centimeters, every year, according to NASA's Jet Propulsion Laboratory. NASA's general Moon facts page gives a rounder figure of about an inch a year. We use the measured value.\n\nThe change is far too small to notice in the night sky in a lifetime. Over millions of years, though, it adds up, and the Moon was much closer to Earth in the distant past than it is today.",
		),
		'sunlight-takes-more-than-eight-minutes-to-reach-earth' => array(
			'claim'        => 'Light from the Sun takes about 8 minutes and 20 seconds to reach Earth, across an average distance of about 93 million miles.',
			'short_answer' => 'Sunlight takes about 8 minutes and 20 seconds to reach Earth, the Smithsonian\'s National Air and Space Museum says. NASA rounds it to about eight minutes. The average distance is about 93 million miles (150 million kilometers).',
			'explanation'  => "The travel time follows from two known numbers: how far away the Sun is and how fast light moves. NASA says Earth sits an average of 93 million miles, or 150 million kilometers, from the Sun. That distance is one astronomical unit.\n\nThe Smithsonian's National Air and Space Museum gives the speed of light as about 670,600,000 miles per hour. Over the average distance, that means a photon takes about 8 minutes and 20 seconds to travel from the Sun to Earth. NASA's Earth facts page rounds this to about eight minutes.\n\nBecause of this delay, we always see the Sun as it was a little over eight minutes ago. The exact time changes slightly through the year, because Earth's orbit is not a perfect circle.",
		),
		'energy-from-the-suns-core-takes-170000-years-to-escape' => array(
			'title'        => 'Energy Takes About 170,000 Years to Travel Out From the Sun\'s Core',
			'claim'        => 'Energy made in the Sun\'s core takes about 170,000 years to get from the core to the top of the convection zone.',
			'short_answer' => 'NASA says energy made by fusion in the Sun\'s core takes about 170,000 years to get from the core to the top of the convection zone. The European Space Agency gives the same figure for light crossing the radiative zone.',
			'explanation'  => "In the Sun's core, nuclear fusion turns hydrogen into helium and releases energy. That energy does not fly straight out. It first has to cross the radiative zone, a thick layer around the core where light is absorbed and given off again countless times, so it moves outward very slowly.\n\nThe European Space Agency says photons take around 170,000 years to pass through the radiative zone. NASA gives about 170,000 years for energy to get from the core to the top of the convection zone, the outer layer where hot gas rises and sinks like boiling water.\n\nOnce the energy reaches the surface, the last step is quick: sunlight crosses the 93 million miles to Earth in a little over eight minutes. Neither agency explains how the figure was calculated, so we quote it as they give it.",
		),
		'colin-pitchfork-convicted-using-dna-evidence' => array(
			'short_answer' => 'In 1988 Colin Pitchfork admitted two murders in Leicestershire, England, the first murder case solved with DNA profiling. He was caught after it came out that a coworker had given a blood sample in his place.',
		),
	);
}

function ffc_fact_fix_v2_run() {
	$log = array();
	foreach ( ffc_fact_fix_v2_data() as $slug => $text ) {
		$fact = FactFusion_Facts::get_by_slug( $slug );
		if ( ! $fact ) {
			$log[ $slug ] = 'missing';
			continue;
		}
		$data         = array_merge( (array) $fact, $text );
		$data['slug'] = $slug; // Keep live addresses: topic pages embed these facts by slug.
		if ( isset( $text['explanation'] ) ) {
			$data['explanation'] = wpautop( esc_html( $text['explanation'] ) );
		}
		$data['source_ids'] = FactFusion_Facts::source_ids( (int) $fact->id );
		$saved              = FactFusion_Facts::save( $data, (int) $fact->id );
		$log[ $slug ]       = is_wp_error( $saved ) ? $saved->get_error_message() : 'fixed';
	}
	FactFusion_Facts::changed(); // Cache purge and IndexNow (inc/seo-data.php).
	return $log;
}
