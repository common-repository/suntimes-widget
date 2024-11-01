<?php
/*
Plugin Name: SunTimes
Plugin URI: http://www.jonlynch.co.uk/wordpress-plugins/suntimes/
Description: Widget to display sunrise and sunset times.
Author: Jon Lynch
Version: 0.9.2
Author URI: http://www.jonlynch.co.uk
*/

class SunTimesWidget extends WP_Widget
{
  function SunTimesWidget()
  {
    $widget_ops = array('classname' => 'SunTimesWidget', 'description' => 'Displays the sunrise and sunset times' );
    $this->WP_Widget('SunTimesWidget', 'SunTimes', $widget_ops);
  }
 
  function form($instance)
  {
    $instance = wp_parse_args( (array) $instance, array( 'title' => '', 'lat'=>54, 'lon' => -3 ) );
    $title = $instance['title'];
    $lat = $instance['lat'];
    $lon = $instance['lon'];
?>
  <p><label for="<?php echo $this->get_field_id('title'); ?>">Title: <em>(eg Suntimes for London)</em> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo attribute_escape($title); ?>" /></label></p>
  <p>To find the latitude and longitude of the location you would like the suntimes for visit <a href="http://maps.google.com">Google Maps</a>, right click and choose <em>what's here?</em></p>
  <p>  <label for="<?php echo $this->get_field_id('lat'); ?>">Latitude: <input id="<?php echo $this->get_field_id('lat'); ?>" name="<?php echo $this->get_field_name('lat'); ?>" type="text" value="<?php echo attribute_escape($lat); ?>" size="8"/></label></p>
  <p><label for="<?php echo $this->get_field_id('lon'); ?>">Longitude: <input id="<?php echo $this->get_field_id('lon'); ?>" name="<?php echo $this->get_field_name('lon'); ?>" type="text" value="<?php echo attribute_escape($lon); ?>" size="8"/></label></p>
<?php
  }
 
  function update($new_instance, $old_instance)
  {
    $instance = $old_instance;
    $instance['title'] = $new_instance['title'];
    $instance['lat'] = $new_instance['lat'];
    $instance['lon'] = $new_instance['lon'];
    delete_transient( 'suntime' );
    return $instance;
  }
 
  function widget($args, $instance)
  {
    extract($args, EXTR_SKIP);
    $title = empty($instance['title']) ? ' ' : apply_filters('widget_title', $instance['title']);
    $lat = $instance['lat'];
    $lon = $instance['lon'];
    echo $before_widget;
    
    if (!empty($title))
      echo $before_title . $title . $after_title;  
  
    jl_sun_times_display( $lat, $lon );
  
    echo $after_widget;
  }
 
}
add_action( 'widgets_init', create_function('', 'return register_widget("SunTimesWidget");') );


// --- Fetches and displayes the sunrise and sunset times ---
function jl_sun_times_display( $lat, $lon) {
	/*http://www.earthtools.org/sun/<latitude>/<longitude>/<day>/<month>/<timezone>/<dst>
  where: 
    latitude - decimal latitude of the point to query (from -90 to 90).
    longitude - decimal longitude of the point to query (from -180 to 180).
    day - day to query (from 1 through 31).
    month - month to query (from 1 through 12).
    timezone - hours offset from UTC (from -12 to 14). Alternatively, use '99' as the timezone in order to automatically work out the timezone based on the given latitude/longitude.
    dst - whether daylight saving time should be taken into account (either 0 for no or 1 for yes).
*/
  $timezone=get_option('gmt_offset');
  date_default_timezone_set(get_option('timezone_string')); //sets php timezone to make sure dst works correctly
	$day = date ('d'); //gets todays date
	$month = date ('m'); // gets the current month
  // set suntimes to be the array stored in the transients table if it exists
  if (  false === ( $suntimes = get_transient( 'suntime' ) ) ) {
    $suntimes = jl_suntimes_generate_data($lat, $lon, $day, $month, $timezone);
  }
  //if it is not up to date regenerate
  if ($suntimes['day'].$suntimes['month'] != $day.$month ) {
    $suntimes = jl_suntimes_generate_data($lat, $lon, $day, $month, $timezone);
	}
  // if we have the suntimes then we add the table contained in a div
  if ($suntimes) {
    ?> <div id="suntimes"><ul><li>Sunrise: <?php echo $suntimes['rise'] ?></li><li>Sunset: <?php echo $suntimes['set']; ?></li></ul></div>
    <?php
  }
}

function jl_suntimes_generate_data($lat, $lon, $day, $month, $timezone) {
  //$dst = date('I'); //finds out if we are in dst 1 if dst 0 otherwise
  $url = "http://www.earthtools.org/sun/$lat/$lon/$day/$month/$timezone/0" ; //fetches an xml page for west cumbria
  $request = new WP_Http;
  $result = $request->request($url);
  if (isset($result->errors)) {    
  // nothing happens if there is an error so further attempts will be made to populate the transient later
  } 
  else {  // there is no error getting the content from earthtools
    $xml=simplexml_load_string($result['body']);  
    $suntimes['day'] = $day;
    $suntimes['month'] = $month;
    $suntimes['rise'] = substr($xml->morning->sunrise, 0, -3);
    $suntimes['set'] = substr($xml->evening->sunset, 0, -3);   
    set_transient( 'suntime', $suntimes, 60*60*24 );
    return $suntimes;
  }   
}

