<?php
/**
 * Class that handles the output for the Top 10 Countries graph.
 *
 * Class MonsterInsights_SiteInsights_Template_Graph_Top10countries
 */
class MonsterInsights_SiteInsights_Template_Graph_Top10countries extends MonsterInsights_SiteInsights_Metric_Template {

	protected $metric = 'top10countries';

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

	protected function get_options() {
		if (empty($this->data['countries'])) {
			return false;
		}

		$primaryColor = $this->attributes['primaryColor'];
		$secondaryColor = $this->attributes['secondaryColor'];
		$textColor = $this->attributes['textColor'];

		$data = $this->data['countries'];

		$title = __( 'Top 10 countries', 'google-analytics-for-wordpress' );
		$series = array();
		$labels = array_column($data, 'name');

		foreach ($data as $key => $country) {
			$series[$key] = (int) $country['sessions'];
		}

		$options = array(
			'series' => array(
				array(
					'name' => $title,
					'data' => $series,
				)
			),
			'chart' => array(
				'height' => 430,
				'type' => 'bar',
				'zoom' => array( 'enabled' => false ),
				'toolbar' => array( 'show' => false )
			),
			'dataLabels' => array(
				'enabled' => true,
				'style' => array(
					'fontSize' => '12px',
					'colors' => array( $textColor )
				)
			),
			'colors' => array( $primaryColor, $secondaryColor ),
			'title' => array(
				'text' => $title,
				'align' => 'left',
				'style' => array(
					'color' => $textColor,
					'fontSize' => '20px'
				)
			),
			'plotOptions' => array(
				'bar' => array(
					'horizontal' => true,
					'borderRadius' => 5,
					'borderRadiusApplication' => 'end',
					'dataLabels' => array(
						'position' => 'center',
					)
				)
			),
			'xaxis' => array(
				'categories' => $labels,
			)
		);

		return $options;
	}

	/**
	 * Get AMP-compatible output for top10countries graph.
	 *
	 * @return string
	 */
	protected function get_amp_output() {
		if ( empty( $this->data['countries'] ) ) {
			return $this->get_amp_placeholder();
		}

		$data = $this->data['countries'];
		$primaryColor = $this->attributes['primaryColor'];
		$textColor = $this->attributes['textColor'];

		// Get top 5 countries for AMP display
		$top_countries = array_slice( $data, 0, 5 );
		
		$output = "<div class='monsterinsights-amp-countries-block'>";
		$output .= "<div class='monsterinsights-amp-header'>";
		$output .= "<h3>" . __( 'Top Countries', 'google-analytics-for-wordpress' ) . "</h3>";
		$output .= "</div>";
		
		$output .= "<div class='monsterinsights-amp-countries-list'>";
		foreach ( $top_countries as $index => $country ) {
			$percentage = ( $country['sessions'] / array_sum( array_column( $data, 'sessions' ) ) ) * 100;
			$output .= "<div class='monsterinsights-amp-country-item'>";
			$output .= "<div class='monsterinsights-amp-country-name'>" . esc_html( $country['name'] ) . "</div>";
			$output .= "<div class='monsterinsights-amp-country-bar'>";
			$output .= "<div class='monsterinsights-amp-country-bar-fill' style='width: " . round( $percentage ) . "%; background-color: " . esc_attr( $primaryColor ) . ";'></div>";
			$output .= "</div>";
			$output .= "<div class='monsterinsights-amp-country-sessions'>" . number_format( $country['sessions'] ) . "</div>";
			$output .= "</div>";
		}
		$output .= "</div>";
		
		$output .= "</div>";
		
		return $output;
	}

	/**
	 * Get AMP placeholder when no data is available.
	 *
	 * @return string
	 */
	protected function get_amp_placeholder() {
		return "<div class='monsterinsights-amp-countries-block'>";
		$output .= "<div class='monsterinsights-amp-header'>";
		$output .= "<h3>" . __( 'Top Countries', 'google-analytics-for-wordpress' ) . "</h3>";
		$output .= "</div>";
		$output .= "<div class='monsterinsights-amp-no-data'>" . __( 'No country data available', 'google-analytics-for-wordpress' ) . "</div>";
		$output .= "</div>";
	}
}