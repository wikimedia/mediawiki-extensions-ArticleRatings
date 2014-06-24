<?php

function wfRatingRender( $input, array $args, Parser $parser, PPFrame $frame ) {
	global $wgAREUseInitialRatings, $wgARENamespaces;

	$out = '';

	if ( isset( $args['page'] ) && $args['page'] ) {
		$page = $parser->recursiveTagParse( $args['page'], $frame ); // parse variables like {{{1}}}

		$title = Title::newFromText( $page );

		if ( $title && $title->exists() ) {
			$out .= '<span class="mw-rating-tag-page">';
		} else {
			return wfMessage( 'are-no-such-page', $page )->parse();
		}

		if ( $title->isRedirect() ) { // follow redirects
			$wikipage = WikiPage::factory( $title );
			$content = $wikipage->getContent( Revision::FOR_PUBLIC );
			$title = $content->getUltimateRedirectTarget();
		}

		$showAboutLink = false;
	} else {
		$title = $parser->getTitle();
		$out .= '<span class="mw-rating-tag">';

		$showAboutLink = true;
	}

	if ( !in_array( $title->getNamespace(), $wgARENamespaces ) ) {
		return wfMessage( 'are-disallowed' )->parse();
	}

	if ( isset( $args['initial-rating'] ) && $wgAREUseInitialRatings ) {
		$initRating = $args['initial-rating'];
	}

	$dbr = wfGetDB( DB_SLAVE );

	$field = $dbr->selectField(
		'ratings',
		'ratings_rating',
		array(
			'ratings_title' => $title->getDBkey(),
			'ratings_namespace' => $title->getNamespace(),
		),
		__METHOD__
	);

	if ( $field ) {
		$useRating = new Rating( $field );

	} else { // create rating
		$ratings = RatingData::getAllRatings();

		$useRating = RatingData::getDefaultRating();

		if ( isset( $args['initial-rating'] ) ) {
			foreach ( $ratings as $rating ) {
				if ( $args['initial-rating'] == $rating->getCodename() ) { // check if the rating actually exists
					$useRating = $rating;
				}
			}
		}

		$dbw = wfGetDB( DB_MASTER );

		$dbw->insert(
			'ratings',
			array(
				'ratings_rating' => $useRating->getCodename(),
				'ratings_title' => $title->getDBkey(),
				'ratings_namespace' => $title->getNamespace()
			),
			__METHOD__
		);
	}

	$aboutLink = '';

	if ( $showAboutLink ) {
		$aboutLink = $useRating->getAboutLink();
	}

	$out .= $aboutLink . $useRating->getImage() . '</span>';

	return $out;
}