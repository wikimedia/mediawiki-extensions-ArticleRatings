<?php

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;

class Rating {
	protected $codename;
	protected $data = [];

	public function __construct( $codename ) {
		if ( !$codename ) {
			trigger_error( 'Never seed Rating with an empty codename' );
		} else {
			$this->codename = $codename;
		}

		foreach ( RatingData::getJSON() as $data ) {
			if ( $data['codename'] == $this->codename ) {
				$this->data = $data;
				return;
			}
		}
		// trigger_error( 'No rating found for the codename ' . $this->codename );
	}

	public function getCodename() {
		return $this->codename;
	}

	public function getName() {
		return $this->data['name'] ?? '';
	}

	public function getLink() {
		return $this->data['link'] ?? '';
	}

	public function getImg() {
		return $this->data['img'] ?? '';
	}

	public function getImage() {
		$file = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $this->getImg() );
		if ( !$file ) {
			return '';
		}
		$image = $file->getCanonicalUrl();
		$pic = Html::element( 'img', [
			'class' => 'mw-rating-img',
			'src' => $image,
			'height' => '20px',
			'width' => '20px'
		] ) . wfMessage( 'word-separator' )->parse();

		return $pic;
	}

	public function getAboutLink() {
		global $wgArticlePath;

		$url = str_replace( '$1', $this->getLink(), $wgArticlePath );
		$label = '<a class="mw-rating-about-link" href="' . htmlspecialchars( $url, ENT_QUOTES ) . '" target="_blank">'
			. htmlspecialchars( $this->getName(), ENT_QUOTES ) . '</a>';

		return $label;
	}
}
