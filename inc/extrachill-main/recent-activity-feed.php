<?php
/**
 * Recent Activity Feed
 *
 * Community forum activity aggregation using native multisite functions.
 * Provides shortcode for displaying recent forum topics and replies.
 *
 * @package ExtraChill\Multisite
 * @since 1.0.0
 */

/**
 * Generate compact time difference strings
 *
 * @since 1.0.0
 * @param int    $from Timestamp to calculate from
 * @param string $to   Timestamp to calculate to (current time if empty)
 * @return string Compact time difference (5m ago, 2h ago, etc.)
 */
function custom_human_time_diff($from, $to = '') {
    if (empty($to)) {
        $to = time();
    }
    $diff = (int) abs($to - $from);

    if ($diff < MINUTE_IN_SECONDS) {
        $since = sprintf(_n('%ss', '%ss', $diff), $diff);
    } elseif ($diff < HOUR_IN_SECONDS) {
        $minutes = floor($diff / MINUTE_IN_SECONDS);
        $since = sprintf(_n('%sm', '%sm', $minutes), $minutes);
    } elseif ($diff < DAY_IN_SECONDS) {
        $hours = floor($diff / HOUR_IN_SECONDS);
        $since = sprintf(_n('%sh', '%sh', $hours), $hours);
    } elseif ($diff < WEEK_IN_SECONDS) {
        $days = floor($diff / DAY_IN_SECONDS);
        $since = sprintf(_n('%sd', '%sd', $days), $days);
    } elseif ($diff < MONTH_IN_SECONDS) {
        $weeks = floor($diff / WEEK_IN_SECONDS);
        $since = sprintf(_n('%sw', '%sw', $weeks), $weeks);
    } elseif ($diff < YEAR_IN_SECONDS) {
        $months = floor($diff / MONTH_IN_SECONDS);
        $since = sprintf(_n('%smon', '%smon', $months), $months);
    } else {
        $years = floor($diff / YEAR_IN_SECONDS);
        $since = sprintf(_n('%syr', '%syr', $years), $years);
    }
    return $since . __(' ago');
}

/**
 * Fetch recent activity from community site
 *
 * Uses centralized multisite search to query topics and replies.
 * Excludes private forum (ID 1494).
 *
 * @since 1.0.0
 * @param int $limit Number of activities to fetch. Default 10
 * @return array Array of activity data or empty array
 */
function ec_fetch_recent_activity_multisite( $limit = 10 ) {
	if ( ! is_multisite() ) {
		error_log( 'Recent activity multisite error: WordPress multisite not detected' );
		return array();
	}

	// Use centralized multisite search
	$results = extrachill_multisite_search(
		'',
		array( 'community.extrachill.com' ),
		array(
			'post_types'  => array( 'topic', 'reply' ),
			'post_status' => array( 'publish' ),
			'limit'       => $limit,
			'meta_query'  => array(
				array(
					'key'     => '_bbp_forum_id',
					'value'   => '1494',
					'compare' => '!=',
				),
			),
			'orderby'     => 'date',
			'order'       => 'DESC',
		)
	);

	if ( empty( $results ) ) {
		return array();
	}

	$activities = array();

	switch_to_blog( get_blog_id_from_url( 'community.extrachill.com', '/' ) );

	try {
		foreach ( $results as $result ) {
			$post_id   = $result['ID'];
			$post_type = $result['post_type'];

			$forum_id = ( 'reply' === $post_type )
				? get_post_meta( get_post_meta( $post_id, '_bbp_topic_id', true ), '_bbp_forum_id', true )
				: get_post_meta( $post_id, '_bbp_forum_id', true );

			$forum_title = get_the_title( $forum_id );
			$forum_url   = get_permalink( $forum_id );

			$topic_title = ( 'reply' === $post_type )
				? get_the_title( get_post_meta( $post_id, '_bbp_topic_id', true ) )
				: $result['post_title'];

			$topic_url = $result['permalink'];

			$author_id        = $result['post_author'];
			$username         = get_the_author_meta( 'display_name', $author_id );
			$user_profile_url = get_author_posts_url( $author_id );

			$activities[] = array(
				'type'             => ( 'reply' === $post_type ) ? 'Reply' : 'Topic',
				'username'         => $username,
				'user_profile_url' => $user_profile_url,
				'topic_title'      => $topic_title,
				'topic_url'        => $topic_url,
				'forum_title'      => $forum_title,
				'forum_url'        => $forum_url,
				'date_time'        => gmdate( 'c', strtotime( $result['post_date'] ) ),
			);
		}
	} catch ( Exception $e ) {
		error_log( 'Recent activity transformation error: ' . $e->getMessage() );
	} finally {
		restore_current_blog();
	}

	return $activities;
}

/**
 * Shortcode handler for recent activity display
 *
 * Caches results for 10 minutes to improve performance.
 *
 * @since 1.0.0
 * @return string HTML output for recent activity display
 */
function extrachill_recent_activity_shortcode() {
    $transient_name = 'extrachill_recent_activity';
    $activities = get_transient($transient_name);

    if ($activities === false) {
        $activities = ec_fetch_recent_activity_multisite( 10 );

        if ( empty( $activities ) ) {
            return 'Could not retrieve recent activity.';
        }

        set_transient($transient_name, $activities, 10 * MINUTE_IN_SECONDS);
    }

    $output = '<div class="extrachill-recent-activity">';
    if (!empty($activities)) {
        $output .= '<ul>';
        $counter = 0;
        foreach ($activities as $activity) {
            if (!is_array($activity)) {
                continue;
            }
            $dateFormatted = custom_human_time_diff(strtotime($activity['date_time']));
            $counter++;
            if ($activity['type'] === 'Reply') {
                $output .= sprintf(
                    '<li><a href="%s">%s</a> replied to <a id="topic-%d" href="%s">%s</a> in <a href="%s">%s</a> - %s</li>',
                    esc_url($activity['user_profile_url']),
                    esc_html($activity['username']),
                    $counter,
                    esc_url($activity['topic_url']),
                    esc_html($activity['topic_title']),
                    esc_url($activity['forum_url']),
                    esc_html($activity['forum_title']),
                    $dateFormatted
                );
            } else { // Topic
                $output .= sprintf(
                    '<li><a href="%s">%s</a> posted <a id="topic-%d" href="%s">%s</a> in <a href="%s">%s</a> - %s</li>',
                    esc_url($activity['user_profile_url']),
                    esc_html($activity['username']),
                    $counter,
                    esc_url($activity['topic_url']),
                    esc_html($activity['topic_title']),
                    esc_url($activity['forum_url']),
                    esc_html($activity['forum_title']),
                    $dateFormatted
                );
            }
        }
        $output .= '</ul>';
    } else {
        $output .= 'No recent activity found.';
    }

    $output .= '<a id="visit-community" href="https://community.extrachill.com"><button>Visit Community</button></a>';
    $output .= '</div>';

    return $output;
}

add_shortcode('extrachill_recent_activity', 'extrachill_recent_activity_shortcode');
