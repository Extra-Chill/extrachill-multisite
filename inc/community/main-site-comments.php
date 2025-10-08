<?php
/**
 * Main Site Comments Integration
 *
 * Cross-domain comment display using WordPress multisite functions.
 * Uses get_blog_id_from_url() with WordPress native blog-id-cache for performance.
 *
 * @package ExtraChillCommunity
 * @subpackage Core\Multisite
 */

/**
 * Display main site comments for a specific user
 *
 * @since 1.0.0
 * @param int $community_user_id User ID
 * @return string HTML markup or error message
 */
if (!function_exists('display_main_site_comments_for_user')) {
    function display_main_site_comments_for_user($community_user_id) {
        if (empty($community_user_id) || !is_numeric($community_user_id)) {
            return '<div class="bbpress-comments-error">Invalid user ID provided.</div>';
        }

        $user_info = get_userdata($community_user_id);
        $user_nicename = $user_info ? $user_info->user_nicename : 'Unknown User';

        switch_to_blog( get_blog_id_from_url( 'extrachill.com', '/' ) );

        $user_comments = get_comments(array(
            'user_id' => $community_user_id,
            'status' => 'approve',
            'order' => 'DESC',
            'orderby' => 'comment_date_gmt'
        ));

        $comments = array();
        if (!empty($user_comments)) {
            foreach ($user_comments as $comment) {
                $post = get_post($comment->comment_post_ID);
                if ($post) {
                    $comments[] = array(
                        'comment_ID' => $comment->comment_ID,
                        'post_permalink' => get_permalink($post->ID),
                        'post_title' => $post->post_title,
                        'comment_date_gmt' => $comment->comment_date_gmt,
                        'comment_content' => $comment->comment_content
                    );
                }
            }
        }

        restore_current_blog();

        if (empty($comments)) {
            return '<div class="bbpress-comments-list"><h3>Comments Feed for <span class="comments-feed-user">' . esc_html($user_nicename) . '</span></h3><p>No comments found for this user.</p></div>';
        }

        $output = "<div class=\"bbpress-comments-list\">";
        $output .= "<h3>Comments Feed for <span class='comments-feed-user'>{$user_nicename}</span></h3>";

        foreach ($comments as $comment) {
            $comment_permalink = esc_url($comment['post_permalink'] . '#comment-' . $comment['comment_ID']);

            $output .= sprintf(
                '<div class="bbpress-comment">
                    <div class="comment-title"><b>Commented on: <a href="%s">%s</a></b></div>
                    <div class="comment-meta">%s</div>
                    <div class="comment-content">%s</div>
                    <div class="comment-permalink"><a href="%s">Reply</a></div>
                </div>',
                esc_url($comment['post_permalink']),
                esc_html($comment['post_title']),
                esc_html(date('F j, Y, g:i a', strtotime($comment['comment_date_gmt']))),
                esc_html($comment['comment_content']),
                $comment_permalink
            );
        }
        $output .= '</div>';

        return $output;
    }
}

if (!function_exists('get_user_main_site_comment_count')) {
    function get_user_main_site_comment_count($user_id) {
        if (empty($user_id) || !is_numeric($user_id)) {
            return 0;
        }

        switch_to_blog( get_blog_id_from_url( 'extrachill.com', '/' ) );
        $count = get_comments(array(
            'user_id' => $user_id,
            'count' => true,
            'status' => 'approve'
        ));
        restore_current_blog();

        return intval($count);
    }
}

if (!function_exists('extrachill_add_query_vars_filter')) {
    function extrachill_add_query_vars_filter($vars){
        $vars[] = "user_id";
        return $vars;
    }
}
add_filter('query_vars', 'extrachill_add_query_vars_filter');