<?php
/**
 * Class that handles the output for the Scroll Depth graph.
 *
 * Class MonsterInsights_SiteInsights_Template_Graph_Scrolldepth
 */
class MonsterInsights_SiteInsights_Template_Graph_Scrolldepth extends MonsterInsights_SiteInsights_Metric_Template {

	protected $metric = 'scrolldepth';

	protected $type = 'graph';

	public function output(){
		// If we're in AMP, return AMP-compatible output
		if ( $this->is_amp() ) {
			return $this->get_amp_output();
		}

		$json_data = $this->get_json_data();

		if (empty($json_data)) {
			return false;
		}

		return "<div class='monsterinsights-graph-item monsterinsights-graph-{$this->metric}'>
			<script type='application/json'>{$json_data}</script>
		</div>";
	}

	/**
	 * Get AMP-compatible output for scroll depth
	 *
	 * @return string
	 */
	protected function get_amp_output() {
		if (!isset($this->data['scroll']) || empty($this->data['scroll']['average'])) {
			return false;
		}

		$value = $this->data['scroll']['average'];
		$title = __( 'Average Scroll Depth', 'google-analytics-for-wordpress' );

		$html = "<div class='monsterinsights-amp-graph-item monsterinsights-amp-scroll-chart monsterinsights-amp-graph-{$this->metric}'>";
		$html .= "<div class='monsterinsights-amp-chart-title'>{$title}</div>";
		$html .= "<div class='monsterinsights-amp-scroll-container'>";
		$html .= "<div class='monsterinsights-amp-scroll-circle'>";
		$html .= "<div class='monsterinsights-amp-scroll-value'>{$value}%</div>";
		$html .= "</div>";
		$html .= "<div class='monsterinsights-amp-scroll-label'>" . __( 'Average Scroll Depth', 'google-analytics-for-wordpress' ) . "</div>";
		$html .= "</div>"; // Close scroll-container
		$html .= "</div>"; // Close graph-item

		return $html;
	}

	protected function get_options() {
		if (!isset($this->data['scroll']) || empty($this->data['scroll']['average'])) {
			return false;
		}

		$primaryColor = $this->attributes['primaryColor'];
		$secondaryColor = $this->attributes['secondaryColor'];

		$value = $this->data['scroll']['average'];

		$title = __( 'Average Scroll Depth', 'google-analytics-for-wordpress' );

		$options = array(
			'series' => array( $value ),
			'chart' => array(
				'height' => 350,
				'type' => 'radialBar',
			),
			'plotOptions' => array(
				'radialBar' => array(
					'size' => $value . '%',
				)
			),
			'colors' => array( $primaryColor, $secondaryColor ),
			'labels' => array( $title ),
		);

		return $options;
	}
}