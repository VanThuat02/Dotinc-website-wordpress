<?php
/**
 * Class that handles the output for the New vs Returning graph.
 *
 * Class MonsterInsights_SiteInsights_Template_Graph_Gender
 */
class MonsterInsights_SiteInsights_Template_Graph_Gender extends MonsterInsights_SiteInsights_Metric_Template {

	protected $metric = 'gender';

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
	 * Get AMP-compatible output for gender breakdown
	 *
	 * @return string
	 */
	protected function get_amp_output() {
		if (empty($this->data['gender'])) {
			return false;
		}

		$data = $this->data['gender'];
		$title = __( 'Gender Breakdown', 'google-analytics-for-wordpress' );

		$html = "<div class='monsterinsights-amp-graph-item monsterinsights-amp-gender-chart monsterinsights-amp-graph-{$this->metric}'>";
		$html .= "<div class='monsterinsights-amp-chart-title'>{$title}</div>";
		$html .= "<div class='monsterinsights-amp-gender-container'>";
		
		foreach ($data as $gender_data) {
			$gender = $gender_data['gender'];
			$percent = $gender_data['percent'];
			
			$html .= "<div class='monsterinsights-amp-gender-item'>";
			$html .= "<div class='monsterinsights-amp-gender-label'>{$gender}</div>";
			$html .= "<div class='monsterinsights-amp-gender-bar'>";
			$html .= "<div class='monsterinsights-amp-gender-fill' style='width: {$percent}%;'></div>";
			$html .= "</div>";
			$html .= "<div class='monsterinsights-amp-gender-value'>{$percent}%</div>";
			$html .= "</div>";
		}
		
		$html .= "</div>"; // Close gender-container
		$html .= "</div>"; // Close graph-item

		return $html;
	}

	protected function get_options() {
		if (empty($this->data['gender'])) {
			return false;
		}

		$primaryColor = $this->attributes['primaryColor'];
		$secondaryColor = $this->attributes['secondaryColor'];
		$textColor = $this->attributes['textColor'];
		$title = __( 'Gender Breakdown', 'google-analytics-for-wordpress' );

		$data = $this->data['gender'];
		$series = array_column($data, 'percent');
		$labels = array_column($data, 'gender');

		$options = array(
			'series' => $series,
			'chart' => array(
				'height' => 'auto',
				'type' => 'donut',
			),
			'colors' => array( '#ebebeb', $primaryColor, $secondaryColor ),
			'title' => array(
				'text' => $title,
				'align' => 'left',
				'style' => array(
					'color' => $textColor,
					'fontSize' => '20px'
				)
			),
			'labels' => $labels,
			'legend' => array(
				'position' => 'right',
				'horizontalAlign' => 'center',
				'floating' => false,
				'fontSize' => '17px',
				'height' => '100%',
				'markers' => array(
					'width' => 30,
					'height' => 30,
					'radius' => 30,
				),
				'formatter' => array(
					'args' => 'seriesName, opts',
					'body' => 'return [seriesName, "<strong> " + opts.w.globals.series[opts.seriesIndex] + "%</strong>"];'
				)
			),
			'dataLabels' => array(
				'enabled' => false
			),
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