<?php

class SpecialMassRatings extends QueryPage {
	function __construct() {
		parent::__construct( 'MassRatings' );
	}

	function getQueryInfo() {
		$where = array();
		$selectedRatings = array();

		$ratings = RatingData::getAllRatings();

		foreach ( $ratings as $data ) {
			if ( $this->getRequest()->getVal( $data ) == 'true' ) {
				$selectedRatings[] = $data;
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

		foreach ( $ratings as $data ) {
			$rating = new RatingData( $data );

			$label = $rating->getAboutLink();
			$pic = $rating->getImage();

			$attribs = array();
			if ( $this->getRequest()->getVal( $data ) == 'true' ) {
				$attribs = array( 'checked' => 'checked' );
			}

			$input = Html::input( $data, 'true', 'checkbox', $attribs );
			$input .= $this->msg( 'word-separator' )->parse();
			$output .= $input . $pic . $label . '<br />';
		}

		$output .= '<input type="submit" /></form></fieldset>';

		return $output;
	}

	function formatResult( $skin, $page ) {
		$rating = new RatingData( $page->value );

		$pic = $rating->getImage();
		$label = $rating->getAboutLink();

		$title = Title::newFromText( $page->title );
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

		foreach ( $ratings as $data ) {
			if ( $this->getRequest()->getVal( $data ) == 'true' ) {
				$params[$data] = 'true';
			}
		}

		return $params;
	}
}