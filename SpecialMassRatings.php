<?php

class SpecialMassRatings extends QueryPage {
	function __construct() {
		parent::__construct( 'MassRatings' );
	}

	function getQueryInfo() {
		$where = array();
		$selectedRatings = array();

		$ratings = RatingData::getAllRatings();

		foreach ( $ratings as $rating ) {
			if ( $this->getRequest()->getVal( $rating->getCodename() ) == 'true' ) {
				$selectedRatings[] = $rating->getCodename();
			}
		}

		if ( $selectedRatings ) {
			$where = array( 'ratings_rating' => $selectedRatings );
		}

		return array(
			'tables' => 'ratings',
			'fields' => array(
				'namespace' => 'ratings_namespace',
				'title' => 'ratings_title',
				'value' => 'ratings_rating'
			),
			'conds' => $where
		);
	}

	function getOrderFields() {
		return array( 'ratings_title' );
	}

	function sortDescending() {
		return false;
	}

	function getPageHeader() {
		$output = '';

		$output .= '<fieldset><legend>' . $this->msg( 'massratings-legend' )->plain();
		$output .= '</legend><form action="" method="get">';

		$ratings = RatingData::getAllRatings();

		foreach ( $ratings as $rating ) {
			$label = $rating->getAboutLink();
			$pic = $rating->getImage();

			$attribs = array();
			if ( $this->getRequest()->getVal( $rating->getCodename() ) == 'true' ) {
				$attribs = array( 'checked' => 'checked' );
			}

			$input = Html::input( $rating->getCodename(), 'true', 'checkbox', $attribs );
			$input .= $this->msg( 'word-separator' )->parse();
			$output .= $input . $pic . $label . '<br />';
		}

		$output .= '<input type="submit" /></form></fieldset>';

		return $output;
	}

	function formatResult( $skin, $page ) {
		$rating = new Rating( $page->value );

		$pic = $rating->getImage();
		$label = $rating->getAboutLink();

		$title = Title::newFromText( $page->title );

		if ( !$title->isKnown() ) { // remove redlinks from results
			return false;
		}

		$link = Linker::link( $title );

		return $pic . $label . ' - ' . $link;
	}

	/**
	 * Ensure rating paramters in URL are passed if the user does a "next 500" or whatever
	 *
	 * @see QueryPage::linkParameters()
	 */
	function linkParameters() {
		$params = array();

		$ratings = RatingData::getAllRatings();

		foreach ( $ratings as $rating ) {
			if ( $this->getRequest()->getVal( $rating->getCodename() ) == 'true' ) {
				$params[$rating->getCodename()] = 'true';
			}
		}

		return $params;
	}
}