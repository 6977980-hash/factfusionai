<?php
/**
 * Site-wide changes shown on the public change log, newest first. Changes to single facts
 * (added, updated, withdrawn) and reader reports are listed from the database, not here.
 *
 * Each entry: date (Y-m-d), title, text. Plain text; keep it honest and specific.
 */

defined( 'ABSPATH' ) || exit;

return array(
	array(
		'date'  => '2026-10-13',
		'title' => 'Open data, a facts API, a quiz and this change log',
		'text'  => 'Every published, verified fact is now free to download as a dataset under the CC BY 4.0 license, in CSV and JSON. We documented our read-only facts API, added a quiz built from published facts, and started this public change log.',
	),
	array(
		'date'  => '2026-10-13',
		'title' => 'Topic and myth pages added, marked for editor review',
		'text'  => 'We added topic pages (Venus, the Sun, Saturn, the Moon, the deep sea) and myth pages with a verdict and quotes from the cited sources. They were drafted with AI help from those sources and carry an "AI-assisted, editor review pending" label until an editor reviews them.',
	),
	array(
		'date'  => '2026-10-13',
		'title' => 'Four flagged facts corrected, and one "first" claim narrowed',
		'text'  => 'We corrected the four flagged facts against their sources: the Great Red Spot is now about 1.3 times as wide as Earth (JPL, Juno-era measurements), the Moon drifts about 1.5 inches a year (JPL laser ranging), sunlight takes about 8 minutes and 20 seconds to reach Earth (Smithsonian), and the 170,000-year figure now says what NASA says: energy takes that long to get from the core to the top of the convection zone. The Colin Pitchfork fact now says it was the first murder case solved with DNA profiling, not the first criminal case.',
	),
	array(
		'date'  => '2026-10-13',
		'title' => 'Four facts flagged for re-checking',
		'text'  => 'While writing "How we know" notes we found four facts whose wording may not match the current source exactly: the size of Jupiter\'s Great Red Spot, how far the Moon drifts from Earth each year, how long sunlight takes to reach Earth, and how long energy takes to leave the Sun\'s core. They are being re-checked against their sources.',
	),
	array(
		'date'  => '2026-10-13',
		'title' => 'Real publish and update dates on every fact',
		'text'  => 'Each fact now shows the date it was first published and the date its text last changed. Before this, some pages showed the date the fact was imported.',
	),
	array(
		'date'  => '2026-10-13',
		'title' => 'Stricter checks before a fact is published',
		'text'  => 'An editor now has to open each fact\'s source and confirm it says the same thing, one fact at a time, with at most 10 new facts a day. The confidence score is now the editor\'s own rating, not a grade copied from the original dataset. 1,121 unchecked claims written by AI were taken out of the review queue and will not be published as they are.',
	),
	array(
		'date'  => '2026-10-13',
		'title' => 'Clearer description of where facts come from',
		'text'  => 'Our "How we verify facts" page and FAQ now say plainly that many newer facts come from reference works such as Wikipedia, and that AI may draft wording, which an editor checks against the source before a fact is published.',
	),
);
