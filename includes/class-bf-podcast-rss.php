<?php
/**
 * Podcast RSS Feed Emission
 * Handles podcast-specific RSS tags and namespaces
 *
 * @package BetterFeed
 * @since 1.0.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BF_Podcast_RSS {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'init_hooks'));
    }
    
    /**
     * Initialize hooks
     */
    public function init_hooks() {
        // Always register the hooks, but check settings in each callback
        add_action('rss2_ns', array($this, 'add_rss_namespaces'));
        add_action('rss2_head', array($this, 'add_channel_tags'));
        add_action('rss2_item', array($this, 'add_item_tags'));
        add_filter('the_content_feed', array($this, 'modify_feed_content'), 10, 2);
    }
    
    /**
     * Check if podcast functionality should be enabled
     */
    private function is_podcast_enabled() {
        // Check if BetterFeed is enabled
        $general_options = get_option('bf_general_options', array());
        if (empty($general_options['enable_betterfeed'])) {
            return false;
        }
        
        // Check if ANY podcast integration is enabled
        $integrations = get_option('bf_podcast_integrations', array());
        foreach ($integrations as $integration => $enabled) {
            if (!empty($enabled)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Add podcast RSS namespaces
     */
    public function add_rss_namespaces() {
        if (!$this->is_podcast_enabled()) {
            return;
        }
        
        $integrations = get_option('bf_podcast_integrations', array());
        
        // iTunes namespace (required for most podcast directories)
        if (!empty($integrations['apple_itunes'])) {
            echo 'xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"' . "\n";
        }
        
        // Podcast Index namespace (modern features)
        if (!empty($integrations['podcast_index'])) {
            echo 'xmlns:podcast="https://podcastindex.org/namespace/1.0"' . "\n";
        }
        
        // Google Play namespace
        if (!empty($integrations['google_youtube_music'])) {
            echo 'xmlns:googleplay="http://www.google.com/schemas/play-podcasts/1.0"' . "\n";
        }
        
        // Spotify namespace (if needed)
        if (!empty($integrations['spotify'])) {
            echo 'xmlns:spotify="http://www.spotify.com/ns/rss"' . "\n";
        }
    }
    
    /**
     * Add channel-level podcast tags
     */
    public function add_channel_tags() {
        if (!$this->is_podcast_enabled()) {
            return;
        }
        
        $integrations = get_option('bf_podcast_integrations', array());
        $show_settings = get_option('bf_podcast_show', array());
        
        if (empty($show_settings)) {
            return;
        }
        
        // Basic channel tags (always add these)
        echo '<language>' . esc_html($show_settings['language'] ?? get_locale()) . '</language>' . "\n";
        echo '<copyright>' . esc_html($show_settings['copyright'] ?? '') . '</copyright>' . "\n";
        
        // iTunes tags
        if (!empty($integrations['apple_itunes'])) {
            $this->add_itunes_channel_tags($show_settings);
        }
        
        // Podcast Index tags
        if (!empty($integrations['podcast_index'])) {
            $this->add_podcast_index_channel_tags($show_settings);
        }
    }
    
    /**
     * Add iTunes channel tags
     */
    private function add_itunes_channel_tags($show_settings) {
        // Required iTunes tags
        echo '<itunes:author>' . esc_html($show_settings['author'] ?? get_bloginfo('name')) . '</itunes:author>' . "\n";
        echo '<itunes:explicit>' . esc_html($show_settings['explicit'] ?? 'false') . '</itunes:explicit>' . "\n";
        echo '<itunes:category text="' . esc_attr($show_settings['category'] ?? 'Arts') . '"></itunes:category>' . "\n";
        
        // Optional iTunes tags
        if (!empty($show_settings['subtitle'])) {
            echo '<itunes:subtitle>' . esc_html($show_settings['subtitle']) . '</itunes:subtitle>' . "\n";
        }
        
        if (!empty($show_settings['artwork'])) {
            $artwork_url = $this->get_attachment_url($show_settings['artwork']);
            if ($artwork_url) {
                echo '<itunes:image href="' . esc_url($artwork_url) . '"></itunes:image>' . "\n";
            }
        }
        
        // Owner information
        if (!empty($show_settings['owner_name']) || !empty($show_settings['owner_email'])) {
            echo '<itunes:owner>' . "\n";
            if (!empty($show_settings['owner_name'])) {
                echo '<itunes:name>' . esc_html($show_settings['owner_name']) . '</itunes:name>' . "\n";
            }
            if (!empty($show_settings['owner_email'])) {
                echo '<itunes:email>' . esc_html($show_settings['owner_email']) . '</itunes:email>' . "\n";
            }
            echo '</itunes:owner>' . "\n";
        }
        
        // Podcast type
        if (!empty($show_settings['type'])) {
            echo '<itunes:type>' . esc_html($show_settings['type']) . '</itunes:type>' . "\n";
        }
        
        // Complete status
        if (!empty($show_settings['complete'])) {
            echo '<itunes:complete>' . ($show_settings['complete'] ? 'yes' : 'no') . '</itunes:complete>' . "\n";
        }
        
        // New feed URL
        if (!empty($show_settings['new_feed_url'])) {
            echo '<itunes:new-feed-url>' . esc_url($show_settings['new_feed_url']) . '</itunes:new-feed-url>' . "\n";
        }
        
        // Block status
        if (!empty($show_settings['block'])) {
            echo '<itunes:block>yes</itunes:block>' . "\n";
        }
    }
    
    /**
     * Add Podcast Index channel tags
     */
    private function add_podcast_index_channel_tags($show_settings) {
        // Podcast GUID
        if (!empty($show_settings['guid'])) {
            echo '<podcast:guid>' . esc_html($show_settings['guid']) . '</podcast:guid>' . "\n";
        }
        
        // Funding information
        if (!empty($show_settings['funding_url'])) {
            $funding_text = $show_settings['funding_text'] ?? 'Support this podcast';
            echo '<podcast:funding url="' . esc_url($show_settings['funding_url']) . '">' . esc_html($funding_text) . '</podcast:funding>' . "\n";
        }
    }
    
    /**
     * Add item-level podcast tags
     */
    public function add_item_tags() {
        global $post;
        
        if (!$post) {
            return;
        }
        
        if (!$this->is_podcast_enabled()) {
            return;
        }
        
        // Get episode meta
        $episode_meta = $this->get_episode_meta($post->ID);
        
        // Audio enclosure (required for podcast feeds)
        $audio_url = $episode_meta['audio_url'] ?? '';
        if (!empty($audio_url)) {
            $audio_length = $episode_meta['audio_length'] ?? 0;
            $audio_type = $episode_meta['audio_type'] ?? 'audio/mpeg';
            
            echo '<enclosure url="' . esc_url($audio_url) . '" length="' . esc_attr($audio_length) . '" type="' . esc_attr($audio_type) . '"></enclosure>' . "\n";
        }
        
        // iTunes item tags
        if (!empty($integrations['apple_itunes'])) {
            $this->add_itunes_item_tags($post, $episode_meta);
        }
        
        // Podcast Index item tags
        if (!empty($integrations['podcast_index'])) {
            $this->add_podcast_index_item_tags($post, $episode_meta);
        }
    }
    
    /**
     * Add iTunes item tags
     */
    private function add_itunes_item_tags($post, $episode_meta) {
        // Duration
        if (!empty($episode_meta['duration'])) {
            echo '<itunes:duration>' . esc_html($episode_meta['duration']) . '</itunes:duration>' . "\n";
        }
        
        // Explicit flag
        if (!empty($episode_meta['explicit'])) {
            echo '<itunes:explicit>' . esc_html($episode_meta['explicit']) . '</itunes:explicit>' . "\n";
        }
        
        // Episode number
        if (!empty($episode_meta['episode_number'])) {
            echo '<itunes:episode>' . esc_html($episode_meta['episode_number']) . '</itunes:episode>' . "\n";
        }
        
        // Season number
        if (!empty($episode_meta['season'])) {
            echo '<itunes:season>' . esc_html($episode_meta['season']) . '</itunes:season>' . "\n";
        }
        
        // Episode type
        if (!empty($episode_meta['episode_type'])) {
            echo '<itunes:episodeType>' . esc_html($episode_meta['episode_type']) . '</itunes:episodeType>' . "\n";
        }
        
        // Episode artwork
        if (!empty($episode_meta['artwork'])) {
            $artwork_url = $this->get_attachment_url($episode_meta['artwork']);
            if ($artwork_url) {
                echo '<itunes:image href="' . esc_url($artwork_url) . '"></itunes:image>' . "\n";
            }
        }
        
        // Subtitle
        if (!empty($episode_meta['subtitle'])) {
            echo '<itunes:subtitle><![CDATA[' . esc_html($episode_meta['subtitle']) . ']]></itunes:subtitle>' . "\n";
        }
        
        // Author
        if (!empty($episode_meta['author'])) {
            echo '<itunes:author>' . esc_html($episode_meta['author']) . '</itunes:author>' . "\n";
        }
        
        // Summary (use post excerpt or content)
        $summary = get_the_excerpt($post);
        if (empty($summary)) {
            $summary = wp_strip_all_tags(get_the_content($post));
        }
        if (!empty($summary)) {
            echo '<itunes:summary><![CDATA[' . esc_html($summary) . ']]></itunes:summary>' . "\n";
        }
        
        // Block episode
        if (!empty($episode_meta['block'])) {
            echo '<itunes:block>yes</itunes:block>' . "\n";
        }
        
        // Keywords (legacy)
        if (!empty($episode_meta['keywords'])) {
            echo '<itunes:keywords>' . esc_html($episode_meta['keywords']) . '</itunes:keywords>' . "\n";
        }
    }
    
    /**
     * Add Podcast Index item tags
     */
    private function add_podcast_index_item_tags($post, $episode_meta) {
        // Chapters
        if (!empty($episode_meta['chapters_url'])) {
            echo '<podcast:chapters url="' . esc_url($episode_meta['chapters_url']) . '" type="application/json+chapters"></podcast:chapters>' . "\n";
        }
        
        // Transcripts
        if (!empty($episode_meta['transcript_url'])) {
            $transcript_type = $this->get_transcript_type($episode_meta['transcript_url']);
            echo '<podcast:transcript url="' . esc_url($episode_meta['transcript_url']) . '" type="' . esc_attr($transcript_type) . '"></podcast:transcript>' . "\n";
        }
        
        // Soundbites (if implemented)
        if (!empty($episode_meta['soundbites'])) {
            foreach ($episode_meta['soundbites'] as $soundbite) {
                if (!empty($soundbite['startTime']) && !empty($soundbite['duration'])) {
                    echo '<podcast:soundbite startTime="' . esc_attr($soundbite['startTime']) . '" duration="' . esc_attr($soundbite['duration']) . '">' . esc_html($soundbite['title'] ?? '') . '</podcast:soundbite>' . "\n";
                }
            }
        }
        
        // Persons (if implemented)
        if (!empty($episode_meta['persons'])) {
            foreach ($episode_meta['persons'] as $person) {
                if (!empty($person['name']) && !empty($person['role'])) {
                    $img_attr = !empty($person['img']) ? ' img="' . esc_attr($person['img']) . '"' : '';
                    $href_attr = !empty($person['href']) ? ' href="' . esc_attr($person['href']) . '"' : '';
                    $group_attr = !empty($person['group']) ? ' group="' . esc_attr($person['group']) . '"' : '';
                    
                    echo '<podcast:person' . esc_attr($img_attr) . esc_attr($href_attr) . esc_attr($group_attr) . '>' . esc_html($person['name']) . '</podcast:person>' . "\n";
                }
            }
        }
    }
    
    /**
     * Modify feed content for podcast compatibility
     */
    public function modify_feed_content($content, $feed_type) {
        if ($feed_type !== 'rss2') {
            return $content;
        }
        
        // Ensure content is properly formatted for RSS
        // This could include stripping certain HTML tags, adding CDATA sections, etc.
        
        return $content;
    }
    
    /**
     * Get episode meta data
     */
    private function get_episode_meta($post_id) {
        return array(
            'audio_url' => BF_Episode_Meta::get_episode_audio_url($post_id),
            'audio_length' => BF_Episode_Meta::get_episode_audio_length($post_id),
            'audio_type' => BF_Episode_Meta::get_episode_audio_type($post_id),
            'duration' => get_post_meta($post_id, 'episode_duration', true),
            'artwork' => get_post_meta($post_id, 'episode_artwork', true),
            'explicit' => get_post_meta($post_id, 'episode_explicit', true),
            'episode_number' => get_post_meta($post_id, 'episode_number', true),
            'season' => get_post_meta($post_id, 'episode_season', true),
            'episode_type' => get_post_meta($post_id, 'episode_type', true),
            'subtitle' => get_post_meta($post_id, 'episode_subtitle', true),
            'author' => get_post_meta($post_id, 'episode_author', true),
            'guid' => get_post_meta($post_id, 'episode_guid', true),
            'chapters_url' => get_post_meta($post_id, 'episode_chapters_url', true),
            'transcript_url' => get_post_meta($post_id, 'episode_transcript_url', true),
            'block' => get_post_meta($post_id, 'episode_block', true),
            'keywords' => get_post_meta($post_id, 'episode_keywords', true),
            'soundbites' => get_post_meta($post_id, 'episode_soundbites', true),
            'persons' => get_post_meta($post_id, 'episode_persons', true),
        );
    }
    
    /**
     * Get attachment URL from ID
     */
    private function get_attachment_url($attachment_id) {
        if (empty($attachment_id)) {
            return '';
        }
        
        $url = wp_get_attachment_url($attachment_id);
        return $url ? $url : '';
    }
    
    /**
     * Get transcript MIME type from URL
     */
    private function get_transcript_type($url) {
        $extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));
        
        $type_map = array(
            'txt' => 'text/plain',
            'html' => 'text/html',
            'htm' => 'text/html',
            'srt' => 'application/srt',
            'vtt' => 'text/vtt',
            'json' => 'application/json',
        );
        
        return $type_map[$extension] ?? 'text/plain';
    }
}
