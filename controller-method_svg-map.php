<?php
/**
 * Laravel controller method sample
 * 
 * Copyright 2016-2022 Brandon Williams (updated in 2022)
 * 
 * Accepts a user-defined location (or point) in New York State
 * Google Maps API to get the latitude & longitude of the point
 * Calculates the pixel position on a NYS SVG map
 * SVG map and point are drawn using Blade + SVG syntax in blade file
 * 
 * Github: https://github.com/brandonwilliams
 * LinkedIn: https://www.linkedin.com/in/brandonpwilliams/
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ExampleController extends Controller {

    public function getSvg(Request $request) {
        if(!empty($request->input('place'))) {

            /* Header partial view */

            $data = array();
            
            /**
             * Address to map is set
             */
            $place = $request->input('place') ?? null;
            if(!empty($place)) {
                $data['place'] = urldecode(filter_var(trim($place), FILTER_SANITIZE_FULL_SPECIAL_CHARS));
                unset($place);
            } else {
                $data['place'] = "buffalo, ny";
            }
        
            $api_key = "YOUR GOOGLE MAPS API KEY";
            
            // $url = "https://maps.googleapis.com/maps/api/geocode/json?sensor=false&address=".urlencode($map_address) . "&key=" . $api_key;
            /**
             * Get Latitude and Longitude of point from Google Maps API
             */
            $url = "https://maps.googleapis.com/maps/api/geocode/json?sensor=false&address=" . urlencode($data['place']). "&key=" . $api_key;
            $lat_long = get_object_vars(json_decode(file_get_contents($url)));

            $lat = $lat_long['results'][0]->geometry->location->lat;  // (φ)
            $long = $lat_long['results'][0]->geometry->location->lng; // (λ)

            $data['formatted_address'] = $lat_long['results'][0]->formatted_address;
            
            /**
             * Get State / county
             */
            foreach ($lat_long['results'][0]->address_components as $component) {

                if ($component->types[0] == "administrative_area_level_1") {
                    $state = $component->long_name;
                } 

                if ($component->types[0] == "administrative_area_level_2" || $component->types[0] == "locality") {
                    $data['county'] = $component->long_name;
                }
            }
            
            /**
             * Use external call if not NY
             * Avoid unnecessary API call to Google Maps API
             */
            //$stateurl = "https://maps.googleapis.com/maps/api/geocode/json?sensor=false&address=".urlencode($state)."%20state" . "&key=" . $api_key;
            //$stateInfo = get_object_vars(json_decode(file_get_contents($stateurl)));
            
            $stateInfo = get_object_vars(json_decode('{"results":[{"address_components":[{"long_name":"New York","short_name":"NY","types":["administrative_area_level_1","political"]},{"long_name":"United States","short_name":"US","types":["country","political"]}],"formatted_address":"New York, USA","geometry":{"bounds":{"northeast":{"lat":45.015861000000001013177097775042057037353515625,"lng":-71.77749099999999771171133033931255340576171875},"southwest":{"lat":40.4773990999999995210600900463759899139404296875,"lng":-79.7625900999999970508724800311028957366943359375}},"location":{"lat":43.29942849999999765486791147850453853607177734375,"lng":-74.217932600000011689189705066382884979248046875},"location_type":"APPROXIMATE","viewport":{"northeast":{"lat":45.015861000000001013177097775042057037353515625,"lng":-71.77749099999999771171133033931255340576171875},"southwest":{"lat":40.4773990999999995210600900463759899139404296875,"lng":-79.7625900999999970508724800311028957366943359375}}},"place_id":"ChIJqaUj8fBLzEwRZ5UY3sHGz90","types":["administrative_area_level_1","political"]}],"status":"OK"}'));

            /**
             * North, South, East, West state max Latitudes and Longitudes
             */
            $northLat = $stateInfo['results'][0]->geometry->viewport->northeast->lat;
            $southLat = $stateInfo['results'][0]->geometry->viewport->southwest->lat;
            $eastLong = $stateInfo['results'][0]->geometry->viewport->northeast->lng;
            $westLong = $stateInfo['results'][0]->geometry->viewport->southwest->lng;

            /**
             * Absolute value as the map (NYS) should be in the same global quadrant
             */
            $westLongABS = abs($westLong);
            $eastLongABS = abs($eastLong);
            $northLatABS = abs($northLat);
            $southLatABS = abs($southLat);

            /**
             * Get width/height of map in Latitude and Longitude points
             */
            $LNGwidth = $westLongABS - $eastLongABS;
            $LATheight = $northLatABS - $southLatABS;

            /**
             * Pre-determined map (svg viewBox) size
             */
            $mapWidth    = 618.1;
            $mapHeight   = 484.53;

            /**
             * Get lat/long to pixel multiples for NYS
             * (what to multiply lat/long by to get equivalent pixel points on map)
             */
            $xMultiple = $mapWidth / $LNGwidth;
            $yMultiple = $mapHeight / $LATheight;

            /**
             * Absolute values of Longitude and Latitude (for Point)
             * Makes math simpler and works because NYS is entirely in 1 global quadrant
             * (North of Equator, West of Prime Meridian)
             */
            $longABS = abs($long);
            $latABS = abs($lat);

            /**
             * Subtract the distance to prime meridian and equator from point
             * We're only working with 1 state, not the whole earth
             * Then multiply by multiples to get pixels
             * Using east and south as they are the minimum distance points 
             * from Equator/Prime Meridian
             */
            $xA = ($longABS - $eastLongABS) * $xMultiple;
            $yA = ($latABS - $southLatABS) * $yMultiple;

            /**
             * Get exact X (lat) and Y (long) point on map
             * SVG anchor is on top left and lat/long 0:0 is bottom right
             * This flips the point to use the SVG anchor
             */
            $x = $mapWidth - $xA;
            $y = $mapHeight - $yA;

            /**
             * Final X/Y position on map
             */
            $data['x'] = $x;
            $data['y'] = $y;

            /**
             * Map view (blade file) with data
             */
            return view ('pages.project.svg-map')->withData($data);
        } else {
            return view ('pages.project.svg');
        }
    }
}
?>