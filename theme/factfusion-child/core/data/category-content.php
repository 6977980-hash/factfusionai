<?php
/**
 * Starter text for the twelve category pages: introduction, facts to start
 * with, questions and answers, and a meta description. Used only to fill
 * empty fields (FactFusion AI > Dashboard > Category pages); the editor
 * rewrites it under Categories. Every answer restates a published fact.
 *
 * @package FactFusion_AI_Core
 */

defined( 'ABSPATH' ) || exit;

return array(
	'science'    => array(
		'meta_description' => 'Science facts with sources such as NASA, NOAA, the National Weather Service and NIST: why the sky is blue, what causes the seasons, how far away lightning is.',
		'intro'            => "Science is where everyday questions get exact answers. Why is the sky blue? What really causes the seasons? How far away was that lightning? Each fact in this category answers a question like these, and the first ones were checked against a source that does the measuring itself: NASA, the National Oceanic and Atmospheric Administration and its National Weather Service, the National Institute of Standards and Technology, and the National Institutes of Health.\n\nSeveral facts here correct something many of us were taught or simply assumed. Summer does not come from Earth being nearer the Sun. Most people struck by lightning survive. The air we breathe is mostly not oxygen. Where a source gives a range or an average, the fact keeps it, and where a measurement is a record, the fact says when and where it was made.\n\nOpen any fact for a short explanation, the link to its source and the date it was last verified. If something looks wrong, every fact page has a form for telling us.",
		'featured'         => array(
			'earths-tilt-not-its-distance-from-the-sun-causes-seasons',
			'the-sky-is-blue-because-blue-light-scatters-the-most',
			'about-90-percent-of-people-struck-by-lightning-survive',
			'lightning-to-thunder-every-5-seconds-is-about-a-mile',
			'every-snowflake-has-six-sides',
			'a-second-is-defined-by-9192631770-ticks-of-a-cesium-atom',
		),
		'faq'              => array(
			array( 'What causes the seasons?', 'The tilt of Earth\'s axis. NASA notes that in the Northern Hemisphere it is winter when Earth is closest to the Sun, so distance is not the cause.' ),
			array( 'Why is the sky blue?', 'NASA explains that blue light travels as shorter, smaller waves and is scattered more than other colors by the gases in the air.' ),
			array( 'How can I tell how far away lightning is?', 'The National Weather Service says to count the seconds between the flash and the thunder and divide by 5. The answer is the distance in miles.' ),
			array( 'What is the air made of?', 'NASA gives the atmosphere near the surface as 78 percent nitrogen, 21 percent oxygen and 1 percent other gases.' ),
		),
	),
	'animal'     => array(
		'meta_description' => 'Animal facts with sources such as NOAA Fisheries, the National Park Service and natural history museums: the biggest, fastest and longest-lived animals.',
		'intro'            => "Animal facts spread quickly and change a little each time they are retold. A speed becomes a round number, an estimate becomes a record, and a careful \"may\" disappears. The facts in this category keep the details. Our first facts here were checked against an organization that studies or looks after the animal it describes: NOAA Fisheries for whales, seals, turtles and rays, the U.S. National Park Service for bison, pronghorn, elk and bats, and institutions such as the Smithsonian's National Zoo and the Natural History Museum in London for animals such as the cheetah and the octopus.\n\nYou will find the largest animal that has ever lived, a North American runner that evolved its speed to escape a cheetah that no longer exists, and a whale that may live for more than two centuries. You will also find quieter surprises, such as why the green turtle is called green and why a narwhal's tusk is really a tooth.\n\nMany of the animals here live in the sea, because many of our first animal facts were checked against NOAA Fisheries. More land animals, birds and insects are added as they are verified.",
		'featured'         => array(
			'the-blue-whale-is-the-largest-animal-ever-to-live',
			'an-octopus-has-three-hearts',
			'a-narwhals-tusk-is-actually-a-tooth',
			'pronghorn-can-sprint-at-45-to-50-miles-per-hour',
			'bowhead-whales-may-live-for-more-than-200-years',
			'bats-are-the-only-mammals-that-can-truly-fly',
		),
		'faq'              => array(
			array( 'What is the largest animal that has ever lived?', 'The blue whale. NOAA Fisheries says blue whales reach up to about 110 feet long and up to 330,000 pounds.' ),
			array( 'Which mammals can truly fly?', 'Only bats. The National Park Service calls them the only true flying mammals.' ),
			array( 'Is a narwhal\'s tusk a horn?', 'No. NOAA Fisheries explains that it is a tooth that grows out through the front of the male narwhal\'s jaw and can reach 9.8 feet.' ),
			array( 'Which is the most endangered marine mammal?', 'The vaquita, a small porpoise. NOAA Fisheries says it lives only in the northern Gulf of California.' ),
		),
	),
	'history'    => array(
		'meta_description' => 'History facts with sources such as the National Archives, the National Park Service, the Smithsonian and NASA, including famous myths and what the records say.',
		'intro'            => "History is full of stories that are better known than the records behind them. This category sets those stories against the evidence. Our first facts were checked against an institution that holds the original document or object, or looks after the place where it happened: the U.S. National Archives, the National Park Service, the Smithsonian's National Air and Space Museum, NASA, the National Museum of Denmark and the Paul Revere House.\n\nSome facts correct a famous myth. The Declaration of Independence was not signed on July 4, the only surviving Viking helmet has no horns, and the wide crack in the Liberty Bell is a repair. Others give the exact details behind a well-known moment, from the 12-second first flight of the Wright brothers to Amelia Earhart's solo crossing of the Atlantic.\n\nDates matter here, so each fact keeps the dates its source gives. Our article on history myths explains how we checked them.",
		'featured'         => array(
			'the-declaration-of-independence-was-not-signed-on-july-4',
			'the-only-surviving-viking-helmet-has-no-horns',
			'the-liberty-bells-famous-crack-is-actually-a-repair',
			'the-wright-brothers-first-flight-lasted-just-12-seconds',
			'amelia-earhart-was-the-first-woman-to-fly-the-atlantic-solo',
			'no-one-died-carving-mount-rushmore',
		),
		'faq'              => array(
			array( 'When was the Declaration of Independence signed?', 'The National Archives says the engrossed Declaration was signed on August 2, 1776, and calls a July 4 signing one of the most widely held misconceptions.' ),
			array( 'Did Vikings wear horned helmets?', 'The National Museum of Denmark says the only preserved helmet from the Viking Age does not have horns.' ),
			array( 'How long did the Wright brothers\' first flight last?', 'The Smithsonian National Air and Space Museum says it lasted 12 seconds and covered 120 feet, on December 17, 1903.' ),
			array( 'Did anyone die building Mount Rushmore?', 'No. The National Park Service says not one fatality occurred during the 14 years of construction.' ),
		),
	),
	'space'      => array(
		'meta_description' => 'Space facts with sources such as NASA: the hottest planet, the shortest day, Saturn\'s moons and rings, the Sun, the Moon and the Milky Way.',
		'intro'            => "Space facts are full of very large numbers, and those numbers change as missions return new data. Our first facts in this category were checked against pages published by NASA, the agency that runs many of the missions behind the measurements. Each card names its source and links to it, so you can compare our wording with the source's own.\n\nThe category covers the planets and their records, from the hottest to the fastest to the one tilted on its side, along with the Sun, the Moon, comets, asteroids and our home galaxy. Some facts upset common guesses: Mercury is closest to the Sun, but Venus is hotter, and a day on Venus is longer than its year.\n\nWhere NASA attaches a date to a figure, as it does for the count of Saturn's moons, the fact keeps that date. Where a figure on the source could not be confirmed, we left it out. Our article on verified space facts explains the method in more detail.",
		'featured'         => array(
			'venus-is-the-hottest-planet-in-the-solar-system',
			'a-day-on-venus-is-longer-than-its-year',
			'saturn-has-274-confirmed-moons-more-than-any-other-planet',
			'saturns-rings-are-vast-but-only-about-30-feet-thick',
			'about-1-3-million-earths-could-fit-inside-the-sun',
			'the-moon-always-shows-earth-the-same-face',
		),
		'faq'              => array(
			array( 'Which planet is the hottest?', 'Venus. NASA gives its surface temperature as about 872 degrees Fahrenheit (467 degrees Celsius), hotter than Mercury.' ),
			array( 'How many moons does Saturn have?', 'NASA says Saturn had 274 confirmed moons as of March 2025, far more than any other planet.' ),
			array( 'Which planets have no moons?', 'Mercury and Venus. NASA notes that Venus has a quasi-satellite named Zoozve, which is not counted as a moon.' ),
			array( 'Why do we always see the same side of the Moon?', 'NASA explains that the Moon rotates at the same rate that it orbits Earth, so the same hemisphere always faces us.' ),
		),
	),
	'weird'      => array(
		'meta_description' => 'True facts that sound wrong, each with a source such as NOAA or NASA: starfish that are not fish, jellyfish with no brain, a sunrise in the west.',
		'intro'            => "This category is for facts that sound like they cannot be true, and are. A starfish is not a fish. A jellyfish manages without a brain, blood or a heart. A horseshoe crab is closer to a spider than to a crab, and its blue blood is used to test medicines. On Venus, the Sun would rise in the west.\n\nStrange claims need the strongest checking, so our first facts here came from scientific agencies: NOAA, mostly its National Ocean Service, for the sea creatures, and NASA for the oddities of the solar system. Each card links to the page the claim was compared with.\n\nWe are careful with the word weird. Nothing in this category is a rumour, a hoax or a guess that happens to be popular. If a fact cannot be traced to a reliable source, it does not appear here, however good a story it would make. Open any fact to read why it is true, in the source's own terms.",
		'featured'         => array(
			'starfish-are-not-fish-and-have-no-blood',
			'jellyfish-have-no-brain-blood-or-heart',
			'male-seahorses-get-pregnant-and-give-birth',
			'horseshoe-crabs-are-closer-to-spiders-than-to-crabs',
			'on-venus-the-sun-rises-in-the-west',
			'a-dead-whale-can-feed-deep-sea-life-for-decades',
		),
		'faq'              => array(
			array( 'Are starfish fish?', 'No. NOAA says sea stars have no gills, scales or fins, and use sea water instead of blood to pump nutrients through their bodies.' ),
			array( 'Do jellyfish have brains?', 'No. NOAA says jellyfish lack brains, blood and hearts, and are about 95 percent water.' ),
			array( 'Which animals have fathers that give birth?', 'NOAA says seahorses and their close relatives, sea dragons, are the only species in which the male gets pregnant and gives birth.' ),
		),
	),
	'human-body' => array(
		'meta_description' => 'Human body facts with sources such as the U.S. National Institutes of Health: bones, muscles, DNA, the brain, the heart, taste and hearing.',
		'intro'            => "Your body is the one thing you carry everywhere, and it is still full of surprises. You were born with more bones than you have now. The DNA in a single cell would stretch about six feet if it were unwound. New blood cells are made inside your bones.\n\nOur first facts in this category were checked against pages published by the U.S. National Institutes of Health or one of its institutes, such as the National Institute of Arthritis and Musculoskeletal and Skin Diseases, the National Heart, Lung, and Blood Institute, the National Human Genome Research Institute and the National Eye Institute. These are the government's medical research agencies, so we rank them as Tier A sources.\n\nThese facts explain how the body is built and how it works. They are not medical advice, and they cannot tell you anything about your own health. For that, talk to a doctor. Each fact links to its source, where the institute often gives more detail.",
		'featured'         => array(
			'babies-are-born-with-about-300-bones-but-adults-have-206',
			'the-dna-in-a-single-human-cell-would-stretch-six-feet',
			'skin-is-the-biggest-organ-of-the-human-body',
			'your-kidneys-filter-about-150-quarts-of-blood-a-day',
			'all-humans-are-99-9-percent-genetically-identical',
			'three-tiny-bones-in-your-ear-make-sounds-louder',
		),
		'faq'              => array(
			array( 'How many bones are in the human body?', 'Adults have 206. The National Institutes of Health says a baby is born with about 300, and many fuse together as the child grows.' ),
			array( 'What is the largest organ of the human body?', 'The skin. The National Institutes of Health calls it the biggest organ of the body, organized into three main layers.' ),
			array( 'How many chromosomes do humans have?', 'The National Human Genome Research Institute says humans have 23 pairs of chromosomes, for a total of 46.' ),
		),
	),
	'geography'  => array(
		'meta_description' => 'Geography facts with sources such as the National Park Service, NOAA and NASA: the deepest lake, the lowest point, the largest active volcano and more.',
		'intro'            => "Geography facts are about records, and records depend on how you measure. The tallest mountain from sea level is not the point farthest from Earth's center. The longest mountain range on Earth is mostly underwater. The facts in this category keep track of exactly what was measured, from where, and by whom.\n\nOur first facts here were checked against an agency that surveys or manages the place it describes: the U.S. National Park Service for parks such as Crater Lake, Death Valley, Yellowstone and the Grand Canyon, NOAA for coasts and the seafloor, and NASA for the planet as a whole. Where agencies give slightly different figures, as they do for the length of the Mississippi River, the fact says so.\n\nMany of the places here are in the United States, because U.S. agencies published the measurements we could check. Places elsewhere in the world are added as we find primary sources for them. Open any fact for its source and an explanation.",
		'featured'         => array(
			'mount-chimborazo-not-everest-is-the-farthest-point-from-earths-center',
			'the-longest-mountain-range-on-earth-is-mostly-under-the-ocean',
			'crater-lake-is-the-deepest-lake-in-the-united-states',
			'the-lowest-point-in-north-america-is-in-death-valley',
			'mauna-loa-is-the-largest-active-volcano-on-earth',
			'yellowstone-has-more-than-half-of-the-worlds-geysers',
		),
		'faq'              => array(
			array( 'What is the lowest point in North America?', 'Badwater Basin in Death Valley. The National Park Service puts it at 282 feet (86 meters) below sea level.' ),
			array( 'Which point on Earth is farthest from its center?', 'The summit of Mount Chimborazo in Ecuador. NOAA says it is over 2,072 meters farther from the center than the peak of Everest.' ),
			array( 'What is the deepest lake in the United States?', 'Crater Lake in Oregon. The National Park Service gives its depth as 1,943 feet (592 meters).' ),
		),
	),
	'technology' => array(
		'meta_description' => 'Technology facts with sources such as NASA and GPS.gov: space telescopes, Mars rovers and helicopters, the space station and GPS satellites.',
		'intro'            => "Some of the most remarkable machines ever built are far from Earth, and their numbers are easy to get wrong. This category collects facts about them: the James Webb and Hubble space telescopes, the International Space Station, the Curiosity rover and the Ingenuity helicopter on Mars, the Parker Solar Probe, the Voyager spacecraft and the satellites behind GPS.\n\nOur first facts were checked against pages published by the people who built or operate the machine, mostly NASA and, for GPS, the U.S. government's official GPS.gov. Each card names its source and links to it. Where a figure is tied to a date, such as the number of operating GPS satellites, the fact keeps the date.\n\nTechnology changes faster than most subjects, so the date each fact was last verified matters here more than anywhere. If a mission ends or a record is broken, we update the fact and its verified date, and the change is visible on its page.",
		'featured'         => array(
			'voyager-1-is-the-farthest-human-made-object-from-earth',
			'the-webb-telescope-orbits-the-sun-not-the-earth',
			'a-helicopter-made-the-first-powered-flight-on-another-planet',
			'the-space-station-is-as-long-as-a-football-field',
			'astronauts-on-the-space-station-see-16-sunrises-a-day',
			'gps-satellites-circle-the-earth-twice-a-day',
		),
		'faq'              => array(
			array( 'What is the farthest human-made object from Earth?', 'Voyager 1. NASA says it was launched on September 5, 1977, and became the first spacecraft to enter interstellar space in August 2012.' ),
			array( 'Does the Webb telescope orbit Earth?', 'No. NASA says Webb orbits the Sun, 1.5 million kilometers (1 million miles) from Earth, at the second Lagrange point.' ),
			array( 'How often do GPS satellites go around the Earth?', 'Twice a day. GPS.gov says each satellite circles the Earth twice a day at about 20,200 kilometers (12,550 miles).' ),
		),
	),
	'food'       => array(
		'meta_description' => 'Food facts with sources such as the USDA, FDA, CDC and EPA: food safety, caffeine, date labels, allergens and a few surprises.',
		'intro'            => "Food facts tend to come wrapped in advice, and a lot of that advice is passed along without a source. Our first facts in this category were checked against the agencies that set or study the rules: the U.S. Department of Agriculture and its Food Safety and Inspection Service, the Food and Drug Administration, the Centers for Disease Control and Prevention and the Environmental Protection Agency.\n\nSome facts are practical: how fast bacteria grow in food left out, how long leftovers keep, why frozen food stays safe, and what the date on a label really means. Others are just surprising, such as the trace of radioactivity in bananas and Brazil nuts, or the U.S. Supreme Court case that classed tomatoes as vegetables.\n\nThese facts describe what the agencies say. They are not personal dietary or medical advice. For questions about allergies or about what is right for you or your family, the source pages linked from each fact are the place to start, along with a doctor.",
		'featured'         => array(
			'bacteria-in-food-can-double-in-just-20-minutes',
			'honey-is-not-safe-for-babies-under-one-year-old',
			'date-labels-on-u-s-food-are-not-safety-dates',
			'frozen-food-stays-safe-to-eat-indefinitely',
			'bananas-are-slightly-radioactive',
			'the-u-s-supreme-court-ruled-that-tomatoes-are-vegetables',
		),
		'faq'              => array(
			array( 'How long do leftovers keep?', 'The U.S. Department of Agriculture says leftovers can be kept in the refrigerator for 3 to 4 days, or frozen for 3 to 4 months.' ),
			array( 'Why should babies not eat honey?', 'The CDC says honey can contain the bacteria that cause botulism and should not be fed to a child younger than 1 year old.' ),
			array( 'Is a tomato a fruit or a vegetable?', 'Botanically a fruit. In Nix v. Hedden, the U.S. Supreme Court accepted that but classed tomatoes as vegetables for the tariff act of 1883.' ),
			array( 'How much caffeine is too much?', 'For most adults, the FDA cites 400 milligrams a day, about two to three 12-ounce cups of coffee, as not generally associated with negative effects.' ),
		),
	),
	'psychology' => array(
		'meta_description' => 'Psychology facts with sources such as the U.S. National Institutes of Health: sleep and dreams, memory and aging, the teenage brain and the placebo effect.',
		'intro'            => "Psychology is the study of how people think, feel and behave, and it is a field where findings are easy to overstate. A study suggests something, a headline makes it certain, and a list of facts repeats the headline. The facts in this category keep the scientists' own caution.\n\nOur first facts here were checked against a page from the U.S. National Institutes of Health or one of its institutes: the National Institute of Neurological Disorders and Stroke, the National Institute of Mental Health, the National Institute on Aging and the National Center for Complementary and Integrative Health. Where the source says \"may\" or \"recent findings suggest\", so does the fact.\n\nMuch of the category is about sleep: how much people need at different ages, what happens to the body while we dream, and what sleep may do for the brain. Other facts cover the teenage brain, memory in later life and the placebo effect. None of this is personal medical advice.",
		'featured'         => array(
			'people-spend-about-a-third-of-their-time-asleep',
			'everyone-dreams-for-about-two-hours-a-night',
			'your-arms-and-legs-are-paralyzed-while-you-dream',
			'the-brain-keeps-maturing-until-the-mid-to-late-20s',
			'teenagers-sleep-hormone-runs-on-a-later-schedule',
			'a-placebo-may-help-even-when-you-know-it-is-a-placebo',
		),
		'faq'              => array(
			array( 'How much sleep do adults need?', 'The National Institutes of Health says most adults need 7 to 9 hours of sleep a night, even older people.' ),
			array( 'When does the brain finish developing?', 'The National Institute of Mental Health says the brain finishes developing and maturing in the mid-to-late 20s.' ),
			array( 'Why do we not act out our dreams?', 'During REM sleep, the National Institutes of Health says, arm and leg muscles become temporarily paralyzed, which prevents us from acting out dreams.' ),
		),
	),
	'ocean'      => array(
		'meta_description' => 'Ocean facts with sources such as NOAA and peer-reviewed studies: the deepest point, the largest waterfall, gold in seawater and a shark that lives 272 years.',
		'intro'            => "The ocean covers most of the planet, and most of it has never been seen. That makes it a subject where measured facts and estimates sit side by side, and where it matters to know which is which. Every fact in this category says which it is.\n\nMost of the facts were checked against the U.S. National Oceanic and Atmospheric Administration, NOAA, through its National Ocean Service and its Ocean Exploration program. Two come straight from peer-reviewed studies: one on the age of Greenland sharks, published in the journal Science, and one estimating how many ocean species are still undescribed, published in PLOS Biology.\n\nYou will find the deepest point in the ocean, a waterfall hidden beneath the sea, the gold dissolved in seawater, and the reason the sea must get colder than a lake before it freezes. Our article on ocean facts groups many of them by theme and explains how they were checked.",
		'featured'         => array(
			'the-deepest-known-point-in-the-ocean-is-nearly-11-kilometers-down',
			'the-worlds-largest-waterfall-is-under-the-ocean',
			'about-half-of-earths-oxygen-comes-from-the-ocean',
			'there-is-only-one-global-ocean',
			'the-greenland-shark-is-the-longest-lived-vertebrate-known',
			'there-is-gold-dissolved-in-the-ocean',
		),
		'faq'              => array(
			array( 'How deep is the deepest part of the ocean?', 'NOAA gives the depth of the Challenger Deep, in the Mariana Trench, as approximately 10,935 meters (35,876 feet).' ),
			array( 'How much of the ocean has been explored?', 'NOAA Ocean Exploration says explorers have seen less than 0.001 percent of the deep ocean seafloor, roughly the size of Rhode Island.' ),
			array( 'How many oceans are there?', 'One. NOAA says there is only one global ocean, divided into named regions, and most countries now recognize five of them.' ),
		),
	),
	'nature'     => array(
		'meta_description' => 'Nature facts with sources such as the National Park Service and NOAA: the largest and oldest trees, desert plants, glaciers, caves and kelp.',
		'intro'            => "Some of the most extraordinary living things on Earth stand still. This category covers the plants, landscapes and natural records of the living world: trees that have lived for thousands of years, desert plants that hold so much water that a large saguaro may weigh 80 pounds for each foot of its trunk, glaciers that are shrinking, the longest known cave system, and kelp that can grow up to 18 inches in a day.\n\nMost of the facts were checked against the U.S. National Park Service, which manages and studies many of the places they describe, from Sequoia and Great Basin to Saguaro, Joshua Tree, Glacier and Mammoth Cave. Facts about the sea's plants and algae come from NOAA.\n\nAges and sizes in nature are often estimates, and the sources say so. A grove of aspen \"is estimated\" to be 16,000 years old; a saguaro's lifespan is \"probably\" 150 to 175 years. Our facts keep those words. Open any fact for its explanation and a link to the page it was checked against.",
		'featured'         => array(
			'the-general-sherman-tree-is-the-largest-tree-in-the-world-by-volume',
			'a-coast-redwood-can-grow-to-367-feet-from-a-seed-the-size-of-a-tomato-seed',
			'bristlecone-pines-are-the-oldest-non-clonal-species-on-earth',
			'mammoth-cave-is-the-longest-known-cave-system-in-the-world',
			'kelp-can-grow-up-to-18-inches-in-a-single-day',
			'the-hottest-air-temperature-ever-recorded-was-in-death-valley',
		),
		'faq'              => array(
			array( 'What is the largest tree in the world?', 'By volume, the General Sherman Tree in Sequoia National Park. The National Park Service estimates its volume at 52,500 cubic feet.' ),
			array( 'What is the longest cave system in the world?', 'Mammoth Cave in Kentucky. The National Park Service calls it the world\'s longest known cave system.' ),
			array( 'What is the hottest temperature ever recorded?', 'The National Park Service says the world record highest air temperature, 134°F (57°C), was recorded at Furnace Creek in Death Valley on July 10, 1913.' ),
		),
	),
);
