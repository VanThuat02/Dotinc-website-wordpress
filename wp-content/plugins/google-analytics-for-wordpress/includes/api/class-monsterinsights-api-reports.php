<?php
/**
 * Reports API Client class for MonsterInsights.
 *
 * @since 8.0.0
 *
 * @package MonsterInsights
 */

class MonsterInsights_API_Reports extends MonsterInsights_API_Client {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->base_url = ''; // TODO: Plug in the correct URL
		parent::__construct();
	}
} 