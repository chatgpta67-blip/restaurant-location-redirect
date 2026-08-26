<?php
/**
 * Matches a detected (city/state/country/lat/lng) geolocation result
 * against the admin-configured locations, producing a confidence-scored
 * result. Deliberately has no WordPress dependency beyond string helpers
 * so it can be unit tested in isolation (see tests/php/MatcherTest.php).
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RLR_Matcher {

	/**
	 * Confidence levels ordered weakest to strongest, used for threshold
	 * comparisons.
	 *
	 * @var string[]
	 */
	const CONFIDENCE_ORDER = array( 'none', 'low', 'medium', 'high' );

	/**
	 * Common country name variants mapped to a canonical ISO code, used to
	 * reconcile e.g. a provider's "United Kingdom" against an admin's "UK".
	 *
	 * @var array<string,string>
	 */
	private static $country_aliases = array(
		'united states'              => 'US',
		'united states of america'   => 'US',
		'usa'                        => 'US',
		'us'                         => 'US',
		'united kingdom'             => 'GB',
		'uk'                         => 'GB',
		'great britain'              => 'GB',
		'england'                    => 'GB',
		'scotland'                   => 'GB',
		'wales'                      => 'GB',
		'northern ireland'           => 'GB',
	);

	/**
	 * Determine whether confidence level A meets or exceeds threshold B.
	 *
	 * @param string $confidence 'none'|'low'|'medium'|'high'.
	 * @param string $threshold  'low'|'medium'|'high'.
	 * @return bool
	 */
	public static function meets_threshold( $confidence, $threshold ) {
		$c_index = array_search( $confidence, self::CONFIDENCE_ORDER, true );
		$t_index = array_search( $threshold, self::CONFIDENCE_ORDER, true );

		if ( false === $c_index || false === $t_index ) {
			return false;
		}

		return $c_index >= $t_index;
	}

	/**
	 * Run the full matching hierarchy.
	 *
	 * @param array $detected {
	 *     @type string $country      Detected country name.
	 *     @type string $country_code Detected ISO country code.
	 *     @type string $state        Detected state/region.
	 *     @type string $city         Detected city.
	 *     @type float|null $latitude
	 *     @type float|null $longitude
	 * }
	 * @param array[] $locations Active location rows (from RLR_Location_Manager).
	 * @param array   $options {
	 *     @type bool  $proximity_enabled
	 *     @type float $proximity_radius_km
	 * }
	 * @return array {
	 *     @type array|null $location   Matched location row, or null.
	 *     @type string     $confidence none|low|medium|high.
	 *     @type string     $method     none|city|state|country|proximity.
	 *     @type int        $candidates Number of tied top candidates.
	 *     @type string     $reason     Debug reason code.
	 * }
	 */
	public static function match( array $detected, array $locations, array $options = array() ) {
		$options = wp_parse_args(
			$options,
			array(
				'proximity_enabled'   => true,
				'proximity_radius_km' => 80,
			)
		);

		$empty_result = array(
			'location'   => null,
			'confidence' => 'none',
			'method'     => 'none',
			'candidates' => 0,
			'reason'     => 'no_locations_configured',
		);

		if ( empty( $locations ) ) {
			return $empty_result;
		}

		$d = self::normalize_detected( $detected );

		$country_candidates = array_values(
			array_filter(
				$locations,
				function ( $loc ) use ( $d ) {
					return self::country_matches( $d, $loc );
				}
			)
		);

		// Tier 1: city (requires country match too, to avoid cross-country false positives).
		if ( '' !== $d['city'] ) {
			$city_candidates = array_values(
				array_filter(
					$country_candidates,
					function ( $loc ) use ( $d ) {
						return self::city_matches( $d, $loc );
					}
				)
			);

			if ( 1 === count( $city_candidates ) ) {
				return array(
					'location'   => $city_candidates[0],
					'confidence' => 'high',
					'method'     => 'city',
					'candidates' => 1,
					'reason'     => 'ok',
				);
			}

			if ( count( $city_candidates ) > 1 ) {
				$resolved = self::resolve_by_proximity( $d, $city_candidates, $options );
				if ( $resolved ) {
					return $resolved;
				}

				return array(
					'location'   => null,
					'confidence' => 'low',
					'method'     => 'city',
					'candidates' => count( $city_candidates ),
					'reason'     => 'ambiguous_city',
				);
			}
		}

		// Tier 2: state (within matching country).
		if ( '' !== $d['state'] ) {
			$state_candidates = array_values(
				array_filter(
					$country_candidates,
					function ( $loc ) use ( $d ) {
						return self::state_matches( $d, $loc );
					}
				)
			);

			if ( 1 === count( $state_candidates ) ) {
				return array(
					'location'   => $state_candidates[0],
					'confidence' => 'medium',
					'method'     => 'state',
					'candidates' => 1,
					'reason'     => 'ok',
				);
			}

			if ( count( $state_candidates ) > 1 ) {
				$resolved = self::resolve_by_proximity( $d, $state_candidates, $options );
				if ( $resolved ) {
					return $resolved;
				}

				return array(
					'location'   => null,
					'confidence' => 'low',
					'method'     => 'state',
					'candidates' => count( $state_candidates ),
					'reason'     => 'ambiguous_state',
				);
			}
		}

		// Tier 3: country only.
		if ( 1 === count( $country_candidates ) ) {
			return array(
				'location'   => $country_candidates[0],
				'confidence' => 'low',
				'method'     => 'country',
				'candidates' => 1,
				'reason'     => 'ok',
			);
		}

		if ( count( $country_candidates ) > 1 ) {
			$resolved = self::resolve_by_proximity( $d, $country_candidates, $options );
			if ( $resolved ) {
				return $resolved;
			}

			return array(
				'location'   => null,
				'confidence' => 'low',
				'method'     => 'country',
				'candidates' => count( $country_candidates ),
				'reason'     => 'ambiguous_country',
			);
		}

		// Tier 4: pure proximity across everything (e.g. country text didn't reconcile).
		$resolved = self::resolve_by_proximity( $d, $locations, $options );
		if ( $resolved ) {
			return $resolved;
		}

		return array(
			'location'   => null,
			'confidence' => 'none',
			'method'     => 'none',
			'candidates' => 0,
			'reason'     => '' === $d['city'] && '' === $d['state'] && '' === $d['country'] ? 'no_geo_data' : 'no_match',
		);
	}

	/**
	 * Attempt to resolve a candidate set (or the full set) via lat/lng
	 * proximity to a single nearest location within the configured radius.
	 *
	 * @param array   $d         Normalized detected data.
	 * @param array[] $locations Candidate locations.
	 * @param array   $options   Matching options.
	 * @return array|null Match result array, or null if not resolvable.
	 */
	private static function resolve_by_proximity( array $d, array $locations, array $options ) {
		if ( empty( $options['proximity_enabled'] ) || null === $d['latitude'] || null === $d['longitude'] ) {
			return null;
		}

		$radius = (float) $options['proximity_radius_km'];
		$scored = array();

		foreach ( $locations as $loc ) {
			if ( null === $loc['latitude'] || null === $loc['longitude'] || '' === $loc['latitude'] || '' === $loc['longitude'] ) {
				continue;
			}

			$distance = self::haversine_km( $d['latitude'], $d['longitude'], (float) $loc['latitude'], (float) $loc['longitude'] );
			if ( $distance <= $radius ) {
				$scored[] = array(
					'location' => $loc,
					'distance' => $distance,
				);
			}
		}

		if ( empty( $scored ) ) {
			return null;
		}

		usort(
			$scored,
			function ( $a, $b ) {
				return $a['distance'] <=> $b['distance'];
			}
		);

		// If two+ candidates are essentially equidistant, treat as ambiguous.
		if ( count( $scored ) > 1 && ( $scored[1]['distance'] - $scored[0]['distance'] ) < 1.0 ) {
			return array(
				'location'   => null,
				'confidence' => 'low',
				'method'     => 'proximity',
				'candidates' => count( $scored ),
				'reason'     => 'ambiguous_proximity',
			);
		}

		$nearest      = $scored[0];
		$confidence   = $nearest['distance'] <= ( $radius / 4 ) ? 'high' : 'medium';

		return array(
			'location'   => $nearest['location'],
			'confidence' => $confidence,
			'method'     => 'proximity',
			'candidates' => 1,
			'reason'     => 'ok',
			'distance_km' => round( $nearest['distance'], 1 ),
		);
	}

	/**
	 * Great-circle distance between two points in kilometers.
	 *
	 * @param float $lat1
	 * @param float $lon1
	 * @param float $lat2
	 * @param float $lon2
	 * @return float
	 */
	public static function haversine_km( $lat1, $lon1, $lat2, $lon2 ) {
		$earth_radius_km = 6371;

		$d_lat = deg2rad( $lat2 - $lat1 );
		$d_lon = deg2rad( $lon2 - $lon1 );

		$a = sin( $d_lat / 2 ) * sin( $d_lat / 2 )
			+ cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) )
			* sin( $d_lon / 2 ) * sin( $d_lon / 2 );

		$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

		return $earth_radius_km * $c;
	}

	/**
	 * Normalize a raw detected-location array into a consistent shape.
	 *
	 * @param array $detected Raw detected data.
	 * @return array
	 */
	private static function normalize_detected( array $detected ) {
		return array(
			'country'      => self::norm( isset( $detected['country'] ) ? $detected['country'] : '' ),
			'country_code' => strtoupper( trim( (string) ( isset( $detected['country_code'] ) ? $detected['country_code'] : '' ) ) ),
			'state'        => self::norm( isset( $detected['state'] ) ? $detected['state'] : '' ),
			'city'         => self::norm( isset( $detected['city'] ) ? $detected['city'] : '' ),
			'latitude'     => isset( $detected['latitude'] ) && is_numeric( $detected['latitude'] ) ? (float) $detected['latitude'] : null,
			'longitude'    => isset( $detected['longitude'] ) && is_numeric( $detected['longitude'] ) ? (float) $detected['longitude'] : null,
		);
	}

	/**
	 * Lowercase + trim a string for loose comparison.
	 *
	 * @param string $value Raw string.
	 * @return string
	 */
	private static function norm( $value ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $value ) ) : strtolower( trim( (string) $value ) );
	}

	/**
	 * Resolve a free-text country name (or alias) to a canonical ISO code,
	 * where known. Falls back to the normalized name itself.
	 *
	 * @param string $normalized_name Lowercased, trimmed country name.
	 * @return string
	 */
	private static function resolve_country_code( $normalized_name ) {
		return isset( self::$country_aliases[ $normalized_name ] ) ? self::$country_aliases[ $normalized_name ] : '';
	}

	/**
	 * @param array $d   Normalized detected data.
	 * @param array $loc Location row.
	 * @return bool
	 */
	private static function country_matches( array $d, array $loc ) {
		$loc_country_code = strtoupper( trim( (string) $loc['country_code'] ) );
		$loc_country_name = self::norm( $loc['country'] );

		if ( '' === $d['country_code'] && '' === $d['country'] ) {
			return false;
		}

		// Direct code-to-code match.
		if ( '' !== $d['country_code'] && '' !== $loc_country_code && $d['country_code'] === $loc_country_code ) {
			return true;
		}

		// Direct name-to-name match.
		if ( '' !== $d['country'] && '' !== $loc_country_name && $d['country'] === $loc_country_name ) {
			return true;
		}

		// Alias reconciliation both directions.
		$d_alias   = self::resolve_country_code( $d['country'] );
		$loc_alias = self::resolve_country_code( $loc_country_name );

		if ( $d['country_code'] && $loc_alias && $d['country_code'] === $loc_alias ) {
			return true;
		}
		if ( $loc_country_code && $d_alias && $loc_country_code === $d_alias ) {
			return true;
		}
		if ( $d_alias && $loc_alias && $d_alias === $loc_alias ) {
			return true;
		}

		return false;
	}

	/**
	 * @param array $d   Normalized detected data.
	 * @param array $loc Location row.
	 * @return bool
	 */
	private static function state_matches( array $d, array $loc ) {
		if ( '' === $d['state'] ) {
			return false;
		}

		$loc_state = self::norm( $loc['state'] );
		if ( '' === $loc_state ) {
			return false;
		}

		return $d['state'] === $loc_state;
	}

	/**
	 * @param array $d   Normalized detected data.
	 * @param array $loc Location row.
	 * @return bool
	 */
	private static function city_matches( array $d, array $loc ) {
		if ( '' === $d['city'] ) {
			return false;
		}

		$loc_city = self::norm( $loc['city'] );
		if ( '' === $loc_city ) {
			return false;
		}

		return $d['city'] === $loc_city;
	}
}
