<?php
/**
 * SEO audit, phase 1: the old staging domain, and site wording that matches how facts are really made.
 *
 * - Requests to the Hostinger temporary domain are sent to factfusionai.com (301).
 * - Links to the temporary domain stored in the database are rewritten once.
 * - The FAQ, homepage steps, trust pages and category intros no longer say that every fact
 *   comes from NASA/NOAA or that AI writes nothing: newer facts often cite Wikipedia (Tier B),
 *   and AI drafts the headline, short answer and explanation that an editor then checks.
 * - Dataset claims that were themselves written by AI are taken out of the review queue.
 * - The facts sitemap is never page-cached, and new facts are sent to IndexNow.
 */

defined( 'ABSPATH' ) || exit;

const FFC_LIVE_HOST    = 'factfusionai.com';
const FFC_STAGING_HOST = 'floralwhite-eland-349500.hostingersite.com';

/*
 * The temporary domain serves the same WordPress, so redirect it before anything is rendered.
 * Search engines then merge it into the real domain instead of seeing a duplicate site.
 */
$ffc_request_host = strtolower( (string) ( $_SERVER['HTTP_HOST'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
if ( str_ends_with( $ffc_request_host, '.hostingersite.com' ) && 'cli' !== PHP_SAPI && ! wp_doing_cron() ) {
	header( 'Location: https://' . FFC_LIVE_HOST . ( $_SERVER['REQUEST_URI'] ?? '/' ), true, 301 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	header( 'X-Redirect-By: FactFusion' );
	exit;
}

/** Path of the current request, without query string. */
function ffc_request_path() {
	return (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
}

/*
 * The facts sitemap was served from the page cache and missed newly published facts.
 * It is cheap to build, so never cache it. The IndexNow key file is answered here too.
 */
add_action(
	'init',
	static function () {
		$path = ffc_request_path();
		if ( '/sitemap-facts.xml' === $path ) {
			do_action( 'litespeed_control_set_nocache', 'factfusion: sitemap must list new facts' );
			if ( ! headers_sent() ) {
				header( 'X-LiteSpeed-Cache-Control: no-cache' );
			}
		}
		$key = ffc_indexnow_key();
		if ( '/' . $key . '.txt' === $path ) {
			header( 'Content-Type: text/plain; charset=UTF-8' );
			echo $key; // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		}
	},
	0
);

/** IndexNow key (Bing, Yandex and others), created once. */
function ffc_indexnow_key() {
	$key = (string) get_option( 'ffc_indexnow_key', '' );
	if ( ! preg_match( '/^[a-f0-9]{32}$/', $key ) ) {
		$key = md5( wp_generate_password( 32, false ) );
		update_option( 'ffc_indexnow_key', $key, true );
	}
	return $key;
}

/**
 * Call after facts are published: clears the server page cache (so the sitemap, homepage
 * and category pages show them) and tells IndexNow about the new URLs.
 *
 * @param string[] $urls Public URLs that changed.
 */
function ffc_after_publish( $urls ) {
	do_action( 'litespeed_purge_all' );
	if ( ! headers_sent() ) {
		header( 'X-LiteSpeed-Purge: *', false ); // Works with Hostinger's server cache even without the LiteSpeed plugin.
	}
	$urls = array_values( array_filter( (array) $urls ) );
	if ( ! $urls || FFC_LIVE_HOST !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
		return;
	}
	$key = ffc_indexnow_key();
	wp_remote_post(
		'https://api.indexnow.org/indexnow',
		array(
			'blocking' => false,
			'timeout'  => 5,
			'headers'  => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'     => wp_json_encode(
				array(
					'host'        => FFC_LIVE_HOST,
					'key'         => $key,
					'keyLocation' => home_url( '/' . $key . '.txt' ),
					'urlList'     => $urls,
				)
			),
		)
	);
}

/*
 * Homepage FAQ (and its FAQPage structured data). Same questions, answers that match the process.
 */
add_filter(
	'factfusion_faq',
	static function ( $faq ) {
		$answers = array(
			'Where do the facts come from?'               => 'Every fact links to a named source, with a tier from A to D that shows what kind of source it is. Our first facts come mostly from science agencies such as NASA and NOAA (Tier A). Many newer facts come from reference works such as Wikipedia (Tier B). The tier is always shown, so you can judge the source yourself.',
			'How are the facts verified?'                 => 'Before a fact is published, an editor opens its source and checks that it says the same thing, including every number and name. The fact\'s page shows who verified it, the date it was last verified and the editor\'s confidence score out of 100.',
			'Does FactFusion AI use AI to write facts?'   => 'AI never decides whether a fact is true and never adds a source. Each claim comes from its linked source. For many facts, AI drafts the headline, short answer and explanation from that claim, and an editor checks them against the source before publishing. The fact\'s page says when AI was used.',
		);
		foreach ( $faq as $i => $pair ) {
			if ( isset( $answers[ $pair[0] ] ) ) {
				$faq[ $i ][1] = $answers[ $pair[0] ];
			}
		}
		return $faq;
	}
);

/** Old sentence => new sentence, used for shortcode output and for stored page text. */
function ffc_trust_replacements() {
	return array(
		// Homepage "three steps".
		'A named source that publishes the information first-hand: a science agency, a national archive, a museum or a peer-reviewed study. AI never supplies a fact or a source.' => 'A named source: a science agency, a museum, a peer-reviewed study or a reference work such as Wikipedia. Its tier, from A to D, shows which kind it is. AI never supplies a source.',
		'The claim says only what the source says. It is compared with the source page, and nothing is published until the editor approves it.' => 'An editor opens the source and checks that the claim says only what the source says. AI may draft the wording, but nothing is published until the editor approves it.',
		// Fact page AI disclosure.
		'The explanation on this page was drafted with AI assistance. A human editor checked the fact against its source before publishing.' => 'The headline, short answer and explanation on this page were drafted with AI assistance. A human editor checked them against the source before publishing.',
		'The explanation on this page was drafted with AI assistance and has not been verified yet.' => 'The text on this page was drafted with AI assistance and has not been verified yet.',
		// How We Verify Facts page.
		'established reference works and institutions, such as major museums and encyclopedias.' => 'established reference works and institutions, such as major museums and encyclopedias, including Wikipedia.',
		'We use Tier A or Tier B sources wherever they exist.' => 'We use Tier A or Tier B sources wherever they exist. Many of our newer facts cite Wikipedia, which is Tier B. Where a Tier A source exists, we prefer it and add it when the fact is next checked.',
		'AI never supplies a fact, a source, a link, a date or an author. Facts come only from the linked sources.' => 'AI never adds a source, a link or an author, and never decides whether a fact is true. Each claim comes from its linked source.',
		'When an explanation was drafted with AI assistance, the fact page says so.' => 'AI may draft a fact\'s headline, short answer and explanation from the claim. An editor reads them against the source before publishing, and the fact page says when AI was used.',
		'explanation drafting, and classification' => 'drafting headlines, short answers and explanations, and classification',
		// Guides.
		'museums and archives. The source is linked under every fact.' => 'museums, archives and reference works such as Wikipedia. The source and its tier are shown under every fact.',
		'It helps to draft explanations and organize content' => 'It helps to draft headlines, short answers and explanations and to organize content',
		'AI helps to draft explanations and to organize content' => 'AI helps to draft headlines, short answers and explanations and to organize content',
		'No fact is written or invented by AI.' => 'No fact is invented by AI.',
		// Category intros.
		'and each one was checked against a source that does the measuring itself:' => 'and the first ones were checked against a source that does the measuring itself:',
		'Each one was checked against' => 'Our first facts here were checked against',
		'Each fact was checked against an institution' => 'Our first facts were checked against an institution',
		'Every fact in this category was checked against a page published by NASA, the agency that runs many of the missions behind the measurements. Each card names that page and links to it, so you can compare our wording with NASA\'s own.' => 'Our first facts in this category were checked against pages published by NASA, the agency that runs many of the missions behind the measurements. Each card names its source and links to it, so you can compare our wording with the source\'s own.',
		'Every fact in this category was checked against a page published by' => 'Our first facts in this category were checked against pages published by',
		'Every fact was checked against a page published by' => 'Our first facts were checked against pages published by',
		'so every fact here comes from a scientific agency:' => 'so our first facts here came from scientific agencies:',
		'The facts in this category were checked against the agencies' => 'Our first facts in this category were checked against the agencies',
		// Category meta descriptions.
		' facts checked against ' => ' facts with sources such as ',
		'each checked against NOAA or NASA' => 'each with a source such as NOAA or NASA',
	);
}

add_filter(
	'do_shortcode_tag',
	static function ( $output, $tag ) {
		if ( in_array( $tag, array( 'factfusion_home_steps', 'factfusion_fact_trust' ), true ) && is_string( $output ) ) {
			$map    = ffc_trust_replacements();
			$output = strtr( $output, array_combine( array_map( 'esc_html', array_keys( $map ) ), array_map( 'esc_html', $map ) ) );
		}
		return $output;
	},
	10,
	2
);

/** Paragraph added to the end of every category intro. */
const FFC_CATEGORY_NOTE = 'Newer facts in this category may cite other sources, including reference works such as Wikipedia (Tier B). Every card shows its source and its tier, so you can see what stands behind each fact.';

/*
 * One-time database changes. Runs once on the first request after deploy.
 */
add_action(
	'init',
	static function () {
		if ( get_option( 'ffc_trust_fix_v1' ) || ! class_exists( 'FactFusion_DB' ) || ! add_option( 'ffc_trust_fix_lock', time(), '', false ) ) {
			return;
		}
		update_option( 'ffc_trust_fix_v1', ffc_trust_fix_run(), false );
		delete_option( 'ffc_trust_fix_lock' );
	},
	99
);

function ffc_trust_fix_run() {
	global $wpdb;
	$log   = array();
	$facts = FactFusion_DB::table( 'facts' );
	$cats  = FactFusion_DB::table( 'categories' );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	// 1. Links to the temporary domain stored anywhere in page or fact text.
	$from = array( 'https://' . FFC_STAGING_HOST, 'http://' . FFC_STAGING_HOST, FFC_STAGING_HOST );
	$to   = array( 'https://' . FFC_LIVE_HOST, 'https://' . FFC_LIVE_HOST, FFC_LIVE_HOST );
	foreach ( $from as $i => $old ) {
		$log['posts'][]    = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s', $wpdb->posts, $old, $to[ $i ], '%' . $wpdb->esc_like( $old ) . '%' ) );
		$log['postmeta'][] = $wpdb->query( $wpdb->prepare( "UPDATE %i SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_value LIKE %s AND meta_value NOT LIKE 'a:%%' AND meta_value NOT LIKE 'O:%%'", $wpdb->postmeta, $old, $to[ $i ], '%' . $wpdb->esc_like( $old ) . '%' ) );
		$log['facts'][]    = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET explanation = REPLACE(explanation, %s, %s) WHERE explanation LIKE %s', $facts, $old, $to[ $i ], '%' . $wpdb->esc_like( $old ) . '%' ) );
		$log['cats'][]     = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET intro = REPLACE(intro, %s, %s), faq = REPLACE(faq, %s, %s) WHERE intro LIKE %s OR faq LIKE %s', $cats, $old, $to[ $i ], $old, $to[ $i ], '%' . $wpdb->esc_like( $old ) . '%', '%' . $wpdb->esc_like( $old ) . '%' ) );
	}
	delete_transient( 'factfusion_footer_links' );

	// 2. Wording on published pages (trust pages and guides).
	$map = ffc_trust_replacements();
	$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM %i WHERE post_type = %s AND post_status IN (%s, %s)', $wpdb->posts, 'page', 'publish', 'draft' ) );
	foreach ( $ids as $id ) {
		$post = get_post( (int) $id );
		$new  = strtr( $post->post_content, $map );
		if ( $new !== $post->post_content ) {
			$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => (int) $id ) );
			clean_post_cache( (int) $id );
			$log['pages'][] = $post->post_name;
		}
	}
	// Guide meta descriptions. The space generator promised a NASA page for every fact; newer space facts may cite other sources.
	$map_meta = $map + array( 'Each fact is checked against a NASA page and links to it' => 'Each fact links to the source it was checked against, such as a NASA page' );
	foreach ( $ids as $id ) {
		$desc = (string) get_post_meta( (int) $id, FactFusion_Content::DESCRIPTION_META, true );
		if ( '' !== $desc && strtr( $desc, $map_meta ) !== $desc ) {
			update_post_meta( (int) $id, FactFusion_Content::DESCRIPTION_META, strtr( $desc, $map_meta ) );
		}
	}

	// 3. Category intros and descriptions.
	foreach ( FactFusion_Categories::all() as $category ) {
		$intro = strtr( (string) $category->intro, $map );
		if ( '' !== trim( $intro ) && ! str_contains( $intro, FFC_CATEGORY_NOTE ) ) {
			$intro = rtrim( $intro ) . "\n\n" . FFC_CATEGORY_NOTE;
		}
		$wpdb->update(
			$cats,
			array(
				'intro'            => $intro,
				'meta_description' => strtr( (string) $category->meta_description, $map ),
			),
			array( 'id' => (int) $category->id )
		);
	}

	// 4. Dataset claims written by AI (quality B) leave the review queue until a real source is added.
	$log['ai_claims_to_draft'] = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET status = %s WHERE status = %s AND is_ai_assisted = 1 AND confidence_score = 30 AND verification_status = %s', $facts, 'draft', 'pending', 'unverified' ) );
	// phpcs:enable

	// 5. The three facts published on 13 Oct, corrected.
	$log['facts_fixed'] = ffc_trust_fix_facts();

	FactFusion_Facts::changed();
	ffc_after_publish( $log['facts_fixed'] );
	return $log;
}

/** Corrected text for the facts published on 13 Oct 2026. Returns the URLs changed. */
function ffc_trust_fix_facts() {
	$history = FactFusion_Categories::get_by_slug( 'history' );
	$fixes   = array(
		'invention-of-dna-fingerprinting'               => array(
			'title'        => 'Alec Jeffreys Developed DNA Fingerprinting in 1984',
			'short_answer' => 'Alec Jeffreys developed DNA fingerprinting, now called DNA profiling, in 1984 while working in the Department of Genetics at the University of Leicester in England.',
			'explanation'  => "DNA fingerprinting compares the parts of a person's DNA that differ most from one person to the next. Jeffreys noticed that some stretches of DNA are made of short sequences repeated many times over, and that the number of repeats varies between people. Comparing those patterns shows whether two samples are likely to come from the same person, or from close relatives.\n\nThe method soon moved from the laboratory into court. In the Narborough murder case in Leicestershire, it was first used to clear an innocent suspect who had confessed, and then to help identify the real killer, Colin Pitchfork.\n\nToday the police, courts and disaster-victim teams around the world use DNA profiling. Modern tests look at different regions of DNA than Jeffreys did in 1984, but the idea is the same: compare the parts of DNA that vary most between people.",
		),
		'colin-pitchfork-convicted-using-dna-evidence' => array(
			'title'        => 'Colin Pitchfork Was the First Killer Caught Using DNA Profiling',
			'short_answer' => 'In 1988 Colin Pitchfork admitted two murders in Leicestershire, England, in the first criminal case solved with DNA profiling. He was caught after it came out that a coworker had given a blood sample in his place.',
			'explanation'  => "Two teenage girls were murdered near the villages of Narborough and Enderby in Leicestershire, one in 1983 and one in 1986. Police asked Alec Jeffreys, who had developed DNA fingerprinting at the nearby University of Leicester, to test samples from both crimes. His results showed that a young man who had confessed to the second murder could not have committed it.\n\nPolice then asked around 5,000 local men to give blood samples. Pitchfork persuaded a coworker, Ian Kelly, to give a sample in his name. Kelly later talked about it, another coworker reported it, and Pitchfork was arrested. His DNA matched the samples from the crime scenes, and he pleaded guilty.\n\nThe case showed for the first time that DNA could both clear an innocent suspect and identify the guilty one.",
		),
		'the-earliest-forensic-science-manual'          => array(
			'title'        => 'The First Known Forensic Science Book Was Written in China in the 1240s',
			'short_answer' => 'The first written account of using medicine and insects to solve crimes is attributed to Xi Yuan Lu (Washing Away of Wrongs), written by the Chinese official Song Ci in the 1240s. Wikipedia gives 1248; many histories give 1247.',
			'explanation'  => "Song Ci (1186 to 1249) was an official of the Song dynasty in charge of justice, prisons and the supervision of investigations. His book Xi Yuan Lu, usually translated as Washing Away of Wrongs, gathered guidance for officials who had to work out how a person had died.\n\nIts best-known case is a murder with a sickle. The investigator had the local men lay their sickles out in the sun. Flies gathered on only one of them, drawn by traces of blood too small to see, and its owner confessed. It is one of the earliest recorded uses of insects to solve a crime, the field now called forensic entomology.\n\nThe book also explains how to tell a drowning from a strangling, using marks on the body. Sources differ slightly on its date: Wikipedia's article on forensic science gives 1248, while many histories of the subject give 1247.",
		),
	);
	$urls = array();
	foreach ( $fixes as $slug => $text ) {
		$fact = FactFusion_Facts::get_by_slug( $slug );
		if ( ! $fact ) {
			continue;
		}
		$data               = array_merge( (array) $fact, $text );
		$data['slug']       = $slug; // Keep the address that is already live.
		$data['explanation'] = wpautop( esc_html( $text['explanation'] ) );
		$data['confidence_score'] = 75;
		$data['source_ids'] = FactFusion_Facts::source_ids( (int) $fact->id );
		if ( $history ) {
			$data['category_id'] = (int) $history->id;
		}
		if ( ! is_wp_error( FactFusion_Facts::save( $data, (int) $fact->id ) ) ) {
			$urls[] = FactFusion_Facts::url( $fact );
		}
	}
	return $urls;
}

/** Shows what the one-time fix changed, on the FactFusion dashboard, until dismissed by reload. */
add_action(
	'admin_notices',
	static function () {
		$log = get_option( 'ffc_trust_fix_v1' );
		if ( ! is_array( $log ) || get_option( 'ffc_trust_fix_v1_seen' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		update_option( 'ffc_trust_fix_v1_seen', 1, false );
		printf(
			'<div class="notice notice-success"><p>SEO phase 1 applied: %d pages reworded, %d AI-written dataset claims moved to Draft, %d facts corrected.</p></div>',
			count( (array) ( $log['pages'] ?? array() ) ),
			(int) ( $log['ai_claims_to_draft'] ?? 0 ),
			count( (array) ( $log['facts_fixed'] ?? array() ) )
		);
	}
);
