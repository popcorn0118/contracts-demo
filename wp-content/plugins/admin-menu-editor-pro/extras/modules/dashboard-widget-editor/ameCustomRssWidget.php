<?php

class ameCustomRssWidget extends ameDashboardWidget {
	protected $widgetType = 'custom-rss';

	/**
	 * @var string|null RSS feed URL.
	 */
	protected $feedUrl = null;

	protected $maxItems = 5;

	protected $showAuthor = true;
	protected $showDate = true;
	protected $showSummary = true;
	protected $openInNewTab = false;

	public static function fromArray($widgetProperties) {
		$widget = new self($widgetProperties);
		$widget->setProperties($widgetProperties);
		return $widget;
	}

	protected function setProperties(array $properties) {
		parent::setProperties($properties);

		$this->feedUrl = isset($properties['feedUrl']) ? strval($properties['feedUrl']) : null;
		$this->maxItems = isset($properties['maxItems']) ? max(1, min(intval($properties['maxItems']), 20)) : 5;

		$booleanProperties = ['showAuthor', 'showDate', 'showSummary', 'openInNewTab'];
		foreach ($booleanProperties as $name) {
			if ( isset($properties[$name]) ) {
				$this->$name = (bool)($properties[$name]);
			} else {
				$this->$name = ($name !== 'openInNewTab');
			}
		}
	}

	public function toArray() {
		$properties = parent::toArray();

		$storedProperties = ['feedUrl', 'maxItems', 'showAuthor', 'showDate', 'showSummary', 'openInNewTab'];
		foreach ($storedProperties as $name) {
			$properties[$name] = $this->$name;
		}

		return $properties;
	}

	public function getCallback() {
		return [$this, 'displayContent'];
	}

	public function displayContent() {
		if ( empty($this->feedUrl) ) {
			echo 'Error: No feed URL specified';
			return;
		}

		/*
		 * Based on the wp_widget_rss_output() function from wp-includes\widgets.php
		 *
		 * The core RSS widget doesn't support some of the features we want, like
		 * the ability to open links in a new tab, so this is a modified version.
		 */

		$rss = fetch_feed($this->feedUrl);

		if ( is_wp_error($rss) ) {
			if ( is_admin() || current_user_can('manage_options') ) {
				echo '<p><strong>' . esc_html(__('RSS Error:')) . '</strong> '
					. esc_html($rss->get_error_message()) . '</p>';
			}
			return;
		}

		$items = $this->maxItems;
		if ( ($items < 1) || ($items > 20) ) {
			$items = 10;
		}

		if ( !$rss->get_item_quantity() ) {
			echo '<ul><li>The feed appears to be empty, which probably means the feed is not working. Please try again later.</li></ul>';
			$rss->__destruct();
			unset($rss);
			return;
		}

		echo '<ul>';
		foreach ($rss->get_items(0, $items) as $item) {
			$link = $item->get_link();
			while (!empty($link) && stristr($link, 'http') !== $link) {
				$link = substr($link, 1);
			}
			$link = esc_url(wp_strip_all_tags($link));

			$title = esc_html(trim(wp_strip_all_tags($item->get_title())));
			if ( empty($title) ) {
				$title = __('Untitled');
			}

			$desc = html_entity_decode($item->get_description(), ENT_QUOTES, get_option('blog_charset'));
			$desc = esc_attr(wp_trim_words($desc, 55, ' [&hellip;]'));

			$summary = '';
			if ( $this->showSummary ) {
				$summary = $desc;

				//Change existing [...] to [&hellip;].
				if ( '[...]' === substr($summary, -5) ) {
					$summary = substr($summary, 0, -5) . '[&hellip;]';
				}

				$summary = '<div class="rssSummary">' . esc_html($summary) . '</div>';
			}

			$date = '';
			if ( $this->showDate ) {
				$date = $item->get_date('U');

				if ( $date ) {
					$date = ' <span class="rss-date">' . date_i18n(get_option('date_format'), $date) . '</span>';
				}
			}

			$author = '';
			if ( $this->showAuthor ) {
				$author = $item->get_author();
				if ( is_object($author) ) {
					$author = $author->get_name();
					$author = ' <cite>' . esc_html(wp_strip_all_tags($author)) . '</cite>';
				}
			}

			$extraAttributes = [];
			if ( $this->openInNewTab ) {
				$extraAttributes[] = 'target="_blank"';
			}

			echo '<li>';
			//All output should be escaped by now.
			//phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( $link === '' ) {
				echo $title;
			} else {
				echo "<a class='rsswidget' href='$link' " . implode(' ', $extraAttributes) . ">$title</a>";
			}
			echo $date;
			if ( $this->showSummary ) {
				echo $summary;
			}
			echo $author;
			//phpcs:enable
			echo '</li>';
		}
		echo '</ul>';
		$rss->__destruct();
		unset($rss);
	}
}