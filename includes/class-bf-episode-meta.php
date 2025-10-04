<?php
/**
 * Episode Meta Fields Registration
 * Handles episode-specific meta fields for podcast RSS feeds
 *
 * @package BetterFeed
 * @since 1.0.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BF_Episode_Meta {
    
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
        add_action('init', array($this, 'register_episode_meta'));
    }
    
    /**
     * Register episode meta fields
     */
    public function register_episode_meta() {
        // Check if BetterFeed is enabled
        $general_options = get_option('bf_general_options', array());
        if (empty($general_options['enable_betterfeed'])) {
            return;
        }
        // Audio file URL or attachment ID
        register_post_meta('post', 'episode_audio_url', array(
            'type' => 'string',
            'description' => __('Audio file URL or attachment ID', 'betterfeed'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => array($this, 'sanitize_audio_url'),
            'auth_callback' => array($this, 'auth_callback'),
        ));
        
        // Audio file length in bytes
        register_post_meta('post', 'episode_audio_length', array(
            'type' => 'integer',
            'description' => __('Audio file length in bytes', 'betterfeed'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => array($this, 'sanitize_audio_length'),
            'auth_callback' => array($this, 'auth_callback'),
        ));
        
        // Audio MIME type
        register_post_meta('post', 'episode_audio_type', array(
            'type' => 'string',
            'description' => __('Audio MIME type', 'betterfeed'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => array($this, 'sanitize_audio_type'),
            'auth_callback' => array($this, 'auth_callback'),
        ));
        
        // Episode duration (HH:MM:SS or seconds)
        register_post_meta('post', 'episode_duration', array(
            'type' => 'string',
            'description' => __('Episode duration in HH:MM:SS format or seconds', 'betterfeed'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => array($this, 'sanitize_duration'),
            'auth_callback' => array($this, 'auth_callback'),
        ));
        
        // Episode artwork (attachment ID)
        register_post_meta('post', 'episode_artwork', array(
            'type' => 'integer',
            'description' => __('Episode artwork attachment ID', 'betterfeed'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => array($this, 'sanitize_attachment_id'),
            'auth_callback' => array($this, 'auth_callback'),
        ));
        
        // Episode explicit flag
        register_post_meta('post', 'episode_explicit', array(
            'type' => 'string',
            'description' => __('Episode explicit content flag', 'betterfeed'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => array($this, 'sanitize_explicit'),
            'auth_callback' => array($this, 'auth_callback'),
        ));
        
        // Episode number
        register_post_meta('post', 'episode_number', array(
            'type' => 'integer',
            'description' => __('Episode number within season', 'betterfeed'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => array($this, 'sanitize_episode_number'),
            'auth_callback' => array($this, 'auth_callback'),
        ));
        
        // Season number
        register_post_meta('post', 'episode_season', array(
            'type' => 'integer',
            'description' => __('Season number', 'betterfeed'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => array($this, 'sanitize_season_number'),
            'auth_callback' => array($this, 'auth_callback'),
        ));
        
        // Episode type
        register_post_meta('post', 'episode_type', array(
            'type' => 'string',
            'description' => __('Episode type (full, trailer, bonus)', 'betterfeed'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => array($this, 'sanitize_episode_type'),
            'auth_callback' => array($this, 'auth_callback'),
        ));
        
        // Episode subtitle
        register_post_meta('post', 'episode_subtitle', array(
            'type' => 'string',
            'description' => __('Episode subtitle', 'betterfeed'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => array($this, 'sanitize_subtitle'),
            'auth_callback' => array($this, 'auth_callback'),
        ));
        
        // Episode author
        register_post_meta('post', 'episode_author', array(
            'type' => 'string',
            'description' => __('Episode author', 'betterfeed'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => array($this, 'sanitize_author'),
            'auth_callback' => array($this, 'auth_callback'),
        ));
        
        // Episode GUID
        register_post_meta('post', 'episode_guid', array(
            'type' => 'string',
            'description' => __('Episode GUID', 'betterfeed'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => array($this, 'sanitize_guid'),
            'auth_callback' => array($this, 'auth_callback'),
        ));
        
        // Chapters URL
        register_post_meta('post', 'episode_chapters_url', array(
            'type' => 'string',
            'description' => __('Chapters file URL', 'betterfeed'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => array($this, 'sanitize_url'),
            'auth_callback' => array($this, 'auth_callback'),
        ));
        
        // Transcript URL
        register_post_meta('post', 'episode_transcript_url', array(
            'type' => 'string',
            'description' => __('Transcript file URL', 'betterfeed'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => array($this, 'sanitize_url'),
            'auth_callback' => array($this, 'auth_callback'),
        ));
        
        // Block episode flag
        register_post_meta('post', 'episode_block', array(
            'type' => 'boolean',
            'description' => __('Block episode from appearing in directories', 'betterfeed'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => array($this, 'sanitize_boolean'),
            'auth_callback' => array($this, 'auth_callback'),
        ));
        
        // Keywords (legacy)
        register_post_meta('post', 'episode_keywords', array(
            'type' => 'string',
            'description' => __('Episode keywords (comma-separated)', 'betterfeed'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => array($this, 'sanitize_keywords'),
            'auth_callback' => array($this, 'auth_callback'),
        ));
    }
    
    /**
     * Authentication callback
     */
    public function auth_callback($allowed, $meta_key, $object_id, $user_id, $cap, $caps) {
        return current_user_can('edit_post', $object_id);
    }
    
    /**
     * Sanitize audio URL
     */
    public function sanitize_audio_url($value) {
        if (empty($value)) {
            return '';
        }
        
        // If it's a numeric value, treat as attachment ID
        if (is_numeric($value)) {
            $attachment_id = absint($value);
            if ($attachment_id > 0 && wp_attachment_is('audio', $attachment_id)) {
                return $attachment_id;
            }
        }
        
        // Otherwise, treat as URL
        $url = esc_url_raw($value);
        if (empty($url)) {
            return '';
        }
        
        // Validate URL
        if (!wp_http_validate_url($url)) {
            return '';
        }
        
        return $url;
    }
    
    /**
     * Sanitize audio length
     */
    public function sanitize_audio_length($value) {
        return max(0, absint($value));
    }
    
    /**
     * Sanitize audio type
     */
    public function sanitize_audio_type($value) {
        $allowed_types = array(
            'audio/mpeg',
            'audio/mp3',
            'audio/x-m4a',
            'audio/mp4',
            'audio/wav',
            'audio/ogg',
            'audio/aac'
        );
        
        $type = sanitize_mime_type($value);
        return in_array($type, $allowed_types) ? $type : 'audio/mpeg';
    }
    
    /**
     * Sanitize duration
     */
    public function sanitize_duration($value) {
        if (empty($value)) {
            return '';
        }
        
        // Check if it's in HH:MM:SS format
        if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $value)) {
            return sanitize_text_field($value);
        }
        
        // Check if it's just seconds
        if (is_numeric($value)) {
            $seconds = absint($value);
            return max(0, $seconds);
        }
        
        return '';
    }
    
    /**
     * Sanitize attachment ID
     */
    public function sanitize_attachment_id($value) {
        $attachment_id = absint($value);
        if ($attachment_id > 0 && wp_attachment_is_image($attachment_id)) {
            return $attachment_id;
        }
        return '';
    }
    
    /**
     * Sanitize explicit flag
     */
    public function sanitize_explicit($value) {
        return in_array($value, array('true', 'false', 'yes', 'no', 'clean')) ? $value : 'false';
    }
    
    /**
     * Sanitize episode number
     */
    public function sanitize_episode_number($value) {
        return max(1, absint($value));
    }
    
    /**
     * Sanitize season number
     */
    public function sanitize_season_number($value) {
        return max(1, absint($value));
    }
    
    /**
     * Sanitize episode type
     */
    public function sanitize_episode_type($value) {
        return in_array($value, array('full', 'trailer', 'bonus')) ? $value : 'full';
    }
    
    /**
     * Sanitize subtitle
     */
    public function sanitize_subtitle($value) {
        return sanitize_text_field(substr($value, 0, 255));
    }
    
    /**
     * Sanitize author
     */
    public function sanitize_author($value) {
        return sanitize_text_field($value);
    }
    
    /**
     * Sanitize GUID
     */
    public function sanitize_guid($value) {
        return sanitize_text_field($value);
    }
    
    /**
     * Sanitize URL
     */
    public function sanitize_url($value) {
        if (empty($value)) {
            return '';
        }
        
        $url = esc_url_raw($value);
        return wp_http_validate_url($url) ? $url : '';
    }
    
    /**
     * Sanitize boolean
     */
    public function sanitize_boolean($value) {
        return (bool) $value;
    }
    
    /**
     * Sanitize keywords
     */
    public function sanitize_keywords($value) {
        return sanitize_text_field($value);
    }
    
    /**
     * Get episode audio URL (handles both attachment ID and URL)
     */
    public static function get_episode_audio_url($post_id) {
        $audio_url = get_post_meta($post_id, 'episode_audio_url', true);
        
        if (empty($audio_url)) {
            return '';
        }
        
        // If it's numeric, treat as attachment ID
        if (is_numeric($audio_url)) {
            $attachment_url = wp_get_attachment_url($audio_url);
            return $attachment_url ? $attachment_url : '';
        }
        
        // Otherwise, return as URL
        return $audio_url;
    }
    
    /**
     * Get episode audio length (auto-detect if not set)
     */
    public static function get_episode_audio_length($post_id) {
        $length = get_post_meta($post_id, 'episode_audio_length', true);
        
        if (empty($length)) {
            $audio_url = self::get_episode_audio_url($post_id);
            if (!empty($audio_url)) {
                // Try to get file size via HEAD request
                $response = wp_remote_head($audio_url);
                if (!is_wp_error($response)) {
                    $content_length = wp_remote_retrieve_header($response, 'content-length');
                    if ($content_length) {
                        return absint($content_length);
                    }
                }
            }
        }
        
        return absint($length);
    }
    
    /**
     * Get episode audio type (auto-detect if not set)
     */
    public static function get_episode_audio_type($post_id) {
        $type = get_post_meta($post_id, 'episode_audio_type', true);
        
        if (empty($type)) {
            $audio_url = get_post_meta($post_id, 'episode_audio_url', true);
            
            if (is_numeric($audio_url)) {
                // Get MIME type from attachment
                $mime_type = get_post_mime_type($audio_url);
                if ($mime_type && strpos($mime_type, 'audio/') === 0) {
                    return $mime_type;
                }
            } elseif (!empty($audio_url)) {
                // Try to get MIME type from URL extension
                $path_info = pathinfo($audio_url);
                $extension = strtolower($path_info['extension'] ?? '');
                
                $extension_map = array(
                    'mp3' => 'audio/mpeg',
                    'm4a' => 'audio/x-m4a',
                    'mp4' => 'audio/mp4',
                    'wav' => 'audio/wav',
                    'ogg' => 'audio/ogg',
                    'aac' => 'audio/aac'
                );
                
                if (isset($extension_map[$extension])) {
                    return $extension_map[$extension];
                }
            }
        }
        
        return !empty($type) ? $type : 'audio/mpeg';
    }
}
