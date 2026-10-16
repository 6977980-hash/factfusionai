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

/*
 * Second round (16 Oct 2026): five Today's 5 facts whose wording went beyond their sources.
 * Tier C sources get confidence 75; a fact that now rests on Wikipedia gets that source added.
 */
add_action(
	'init',
	static function () {
		if ( get_option( 'ffc_fact_fix_v3' ) || ! class_exists( 'FactFusion_Facts' ) || ! add_option( 'ffc_fact_fix_v3_lock', time(), '', false ) ) {
			return;
		}
		update_option( 'ffc_fact_fix_v3', ffc_fact_fix_v3_run(), false );
		delete_option( 'ffc_fact_fix_v3_lock' );
	},
	99
);

/** Slug => corrected text, plus 'add_source' / 'drop_source_url' for source changes. */
function ffc_fact_fix_v3_data() {
	return array(
		'male-moth-pheromone-detection' => array(
			'claim'            => 'Male moths can track a female releasing pheromones from up to about a kilometer away, using their highly sensitive antennae.',
			'short_answer'     => 'Male moths can follow the scent of a female\'s pheromones from up to about a kilometer (0.6 miles) away. They pick it up with their feathery, highly sensitive antennae.',
			'explanation'      => "Pheromones are chemical signals that animals release to communicate with others of their own species. Many female moths give off a pheromone when they are ready to mate, and the scent drifts away from them on the wind in very thin traces.\n\nMale moths detect these traces with their antennae, which in many species are large and feathery. The extra surface lets them catch a few scent molecules out of the air. Wikipedia's article on moths says a male can reliably track a female who is releasing pheromones up to a kilometer away.\n\nThat is a long way for an insect a few centimeters long, and it shows how much moths rely on smell rather than sight to find a mate, especially since many of them fly at night.",
			'confidence_score' => 90,
		),
		'meganeura-monyi-the-giant-dragonfly' => array(
			'claim'            => 'The giant dragonfly-like insect Meganeura monyi lived about 300 million years ago and had a wingspan of about 65 to 75 cm (up to about 2.5 feet). Why it grew so large is still debated.',
			'short_answer'     => 'Meganeura monyi, a dragonfly-like insect that lived about 300 million years ago, had a wingspan of about 65 to 75 cm (up to about 2.5 feet). Scientists still debate why it could grow so large.',
			'explanation'      => "Meganeura monyi lived in the Late Carboniferous period, about 300 million years ago. It looked much like a modern dragonfly, but it was far bigger: its wingspan reached about 65 to 75 centimeters, with a single wing about 32 centimeters long. It is one of the largest flying insects known.\n\nA popular explanation is that the air then held more oxygen than it does today, which would let a large insect breathe through its small air tubes. Wikipedia's article on Meganeura notes that this idea is still debated. Giant relatives of Meganeura also lived later, in the Late Permian, when oxygen levels had already dropped.\n\nSo the size is well established from fossils, while the reason for it remains an open question.",
			'confidence_score' => 90,
		),
		'cat-domestication-began-in-china' => array(
			'title'            => 'Cat Domestication Began in the Near East, Not Egypt',
			'slug'             => 'cat-domestication-began-in-the-near-east',
			'question'         => 'Where did cat domestication begin?',
			'claim'            => 'Cat domestication began in the Near East around 7500 BCE, long before ancient Egypt: the earliest known tamed wildcat was buried near a person on Cyprus. Leopard cats were tamed separately in China, but they left no trace in today\'s house cats.',
			'short_answer'     => 'Cat domestication began in the Near East about 9,500 years ago, not in ancient Egypt. The oldest sign is a wildcat buried near a person on Cyprus around 7500 BCE. Leopard cats were tamed separately in China, but today\'s house cats do not descend from them.',
			'explanation'      => "For a long time people thought cats were first domesticated in ancient Egypt, where they were honored from around 3100 BCE. Older evidence has changed that picture.\n\nOn the island of Cyprus, archaeologists found an African wildcat buried close to a human grave at Shillourokambos, dated to about 7500 to 7200 BCE. Wikipedia's article on the cat calls it the earliest known sign of a tamed wildcat, and says domestication most likely started in the Near East around that time. House cats are thought to descend from the African wildcat.\n\nIn China, a different small cat, the leopard cat, was also tamed in early farming villages. But that line left no trace in the cats people keep today. Egyptian wildcats did add to the house cat's family tree, only later.",
			'confidence_score' => 90,
			'add_source'       => array(
				'source_name' => 'Wikipedia: Cat',
				'url'         => 'https://en.wikipedia.org/wiki/Cat',
				'tier'        => 'B',
				'title'       => 'Cat',
				'publisher'   => 'Wikipedia',
			),
			'drop_source_url'  => 'mentalfloss.com/animals/cats/20-fun-facts-about-our-mysterious-feline-friends',
		),
		'what-does-space-smell-like' => array(
			'claim'            => 'Astronauts describe a distinct smell after spacewalks, likened to diesel fumes, gunpowder and barbecue. Some scientists link it to compounds made by dying stars, but the cause is not settled.',
			'short_answer'     => 'Astronauts say space has a distinct smell, like diesel fumes, gunpowder and barbecue, which they notice on suits and gear after spacewalks. Some scientists link it to compounds made by dying stars, but the cause is not settled.',
			'explanation'      => "Space itself is a near-perfect vacuum, so nobody can sniff it directly. What astronauts notice is a smell that clings to their suits, helmets and tools when they come back inside after a spacewalk. They have described it as a mix of diesel fumes, gunpowder and barbecue, or of seared steak and hot metal.\n\nMental Floss reports that the smell is linked to compounds produced by dying stars. That is one idea. Another is that the odor comes from reactions on the surface of suits and equipment once they meet the air inside the station. The cause has not been settled.\n\nWhat is clear is that the smell is real and familiar to many astronauts, even if science has not yet pinned down exactly where it comes from.",
			'confidence_score' => 75,
		),
		'volvo-gave-away-the-three-point-seat-belt-patent' => array(
			'claim'            => 'Volvo introduced the three-point seat belt, developed by Nils Bohlin, in 1959 and made the design patent open so other carmakers could use it for free.',
			'short_answer'     => 'Volvo introduced the three-point seat belt, designed by engineer Nils Bohlin, in 1959 and made the patent open so any carmaker could use it for free. It chose saving lives over profit.',
			'explanation'      => "A three-point seat belt crosses both the lap and the chest and is fixed to the car at three points. It spreads the force of a crash across the strongest parts of the body. Swedish engineer Nils Bohlin developed its modern form for Volvo, and Volvo made it standard equipment in 1959.\n\nInstead of keeping the invention to itself, Volvo made the design patent open in the interest of safety, so other car manufacturers could fit the same belt without paying. Bohlin's U.S. patent was granted in 1962, which is why that year sometimes appears in this story.\n\nBecause anyone could use it, the three-point belt spread across the car industry and became the standard design still found in cars today.",
			'confidence_score' => 75,
			'add_source'       => array(
				'source_name' => 'Wikipedia: Seat belt',
				'url'         => 'https://en.wikipedia.org/wiki/Seat_belt',
				'tier'        => 'B',
				'title'       => 'Seat belt',
				'publisher'   => 'Wikipedia',
			),
		),
	);
}

function ffc_fact_fix_v3_run() {
	global $wpdb;
	$log = array();
	foreach ( ffc_fact_fix_v3_data() as $slug => $text ) {
		$fact = FactFusion_Facts::get_by_slug( $slug );
		if ( ! $fact ) {
			$log[ $slug ] = 'missing';
			continue;
		}
		$source_ids = FactFusion_Facts::source_ids( (int) $fact->id );
		if ( ! empty( $text['drop_source_url'] ) ) {
			foreach ( FactFusion_Sources::for_fact( (int) $fact->id ) as $source ) {
				if ( false !== strpos( (string) $source->url, $text['drop_source_url'] ) ) {
					$source_ids = array_diff( $source_ids, array( (int) $source->id ) );
				}
			}
		}
		if ( ! empty( $text['add_source'] ) ) {
			$existing  = FactFusion_Sources::find_by_url( $text['add_source']['url'] );
			$source_id = $existing ? (int) $existing->id : FactFusion_Sources::save( $text['add_source'] + array( 'is_active' => 1, 'date_accessed' => '2026-10-16' ) );
			if ( ! is_wp_error( $source_id ) ) {
				$source_ids[] = (int) $source_id;
			}
		}
		$question = $text['question'] ?? '';
		unset( $text['add_source'], $text['drop_source_url'], $text['question'] );

		$data                = array_merge( (array) $fact, $text );
		$data['slug']        = $text['slug'] ?? $slug; // A changed slug gets a 301 from the old one (inc/seo-data.php).
		$data['explanation'] = wpautop( esc_html( $text['explanation'] ) );
		$data['source_ids']  = array_values( array_unique( array_map( 'intval', $source_ids ) ) );
		$saved               = FactFusion_Facts::save( $data, (int) $fact->id );
		if ( ! is_wp_error( $saved ) && '' !== $question && function_exists( 'ffc_depth_db_ready' ) && ffc_depth_db_ready() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( FactFusion_DB::table( 'facts' ), array( 'question' => $question ), array( 'id' => (int) $fact->id ) );
		}
		$log[ $slug ] = is_wp_error( $saved ) ? $saved->get_error_message() : 'fixed';
	}
	FactFusion_Facts::changed();
	return $log;
}
