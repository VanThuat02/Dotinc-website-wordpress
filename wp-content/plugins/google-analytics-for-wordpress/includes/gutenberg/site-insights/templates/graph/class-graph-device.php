<?php
/**
 * Class that handles the output for the Device graph.
 *
 * Class MonsterInsights_SiteInsights_Template_Graph_Device
 */
class MonsterInsights_SiteInsights_Template_Graph_Device extends MonsterInsights_SiteInsights_Metric_Template {

	protected $metric = 'device';

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

		return "<div class='monsterinsights-graph-item monsterinsights-donut-chart monsterinsights-graph-{$this->metric}'>
			<script type='application/json'>{$json_data}</script>
		</div>";
	}

	/**
	 * Get AMP-compatible output for device breakdown
	 *
	 * @return string
	 */
	protected function get_amp_output() {
		if (empty($this->data['devices'])) {
			return false;
		}

		$data = $this->data['devices'];
		$title = __( 'Device Breakdown', 'google-analytics-for-wordpress' );

		$html = "<div class='monsterinsights-amp-graph-item monsterinsights-amp-device-chart monsterinsights-amp-graph-{$this->metric}'>";
		$html .= "<div class='monsterinsights-amp-chart-title'>{$title}</div>";
		$html .= "<div class='monsterinsights-amp-device-container'>";
		
		foreach ($data as $device => $percentage) {
			$device_name = ucfirst($device);
			
			$html .= "<div class='monsterinsights-amp-device-item'>";
			$html .= "<div class='monsterinsights-amp-device-label'>{$device_name}</div>";
			$html .= "<div class='monsterinsights-amp-device-bar'>";
			$html .= "<div class='monsterinsights-amp-device-fill' style='width: {$percentage}%;'></div>";
			$html .= "</div>";
			$html .= "<div class='monsterinsights-amp-device-value'>{$percentage}%</div>";
			$html .= "</div>";
		}
		
		$html .= "</div>"; // Close device-container
		$html .= "</div>"; // Close graph-item

		return $html;
	}

	protected function get_options() {
		if (empty($this->data['devices'])) {
			return false;
		}

		$primaryColor = $this->attributes['primaryColor'];
		$secondaryColor = $this->attributes['secondaryColor'];
		$textColor = $this->attributes['textColor'];
		$data = $this->data['devices'];
		$labels = array();
		$series = array_values($data);

		foreach ($data as $key => $value){
			$labels[] = ucfirst($key);
		}

		$title = __( 'Device Breakdown', 'google-analytics-for-wordpress' );

		$options = array(
			'series' => $series,
			'chart' => array(
				'width' => "100%",
				'height' => 'auto',
				'type' => 'donut',
			),
			'colors' => array( $primaryColor, $secondaryColor ),
			'title' => array(
				'text' => $title,
				'align' => 'left',
				'style' => array(
					'color' => $this->get_color_value($textColor),
					'fontSize' => '20px'
				)
			),
			'plotOptions' => array(
				'plotOptions' => array(
					'pie' => array(
						'donut' => array( 'size' => '75%' )
					)
				)
			),
			'legend' => array(
				'position' => 'right',
				'horizontalAlign' => 'center',
				'floating' => false,
				'fontSize' => '17px',
				'height' => '100%',
				'markers' => array(
					'width' => 30,
					'height' => 30,
					'radius' => 30
				),
				'formatter' => array(
					'args' => 'seriesName, opts',
					'body' => 'return [seriesName, "<strong> " + opts.w.globals.series[opts.seriesIndex] + "%</strong>"]'
				)
			),
			'dataLabels' => array(
				'enabled' => false
			),
			'labels' => $labels,
			'responsive' => array(
				0 => array(
					'breakpoint' => 767,
					'options' => array(
						'legend' => array(
							'show' => false
						)
					)
				)
			)
		);

		return $options;
	}
}