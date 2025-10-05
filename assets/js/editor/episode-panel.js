/**
 * BetterFeed Episode Settings Panel for Gutenberg
 * Provides podcast episode settings in the Block Editor
 */

(function(domReady, wp, wpData, wpComponents, wpElement, wpMediaUtils) {
    'use strict';

    const { registerPlugin } = wp.plugins;
    const { PluginDocumentSettingPanel } = wp.editor;
    const { useSelect, useDispatch } = wpData;
    const { useState, useEffect, createElement } = wpElement;
    const { 
        TextControl, 
        TextareaControl, 
        SelectControl, 
        ToggleControl,
        Button,
        Notice,
        Spinner,
        BaseControl,
        FormFileUpload
    } = wpComponents;
    const { 
        mediaUpload, 
        uploadMedia 
    } = wpMediaUtils;

    /**
     * Episode Settings Panel Component
     */
    function EpisodeSettingsPanel() {
        const [isValidating, setIsValidating] = useState(false);
        const [validationErrors, setValidationErrors] = useState({});

        // Get post data
        const { postType, postId, meta } = useSelect(select => ({
            postType: select('core/editor').getCurrentPostType(),
            postId: select('core/editor').getCurrentPostId(),
            meta: select('core/editor').getEditedPostAttribute('meta') || {}
        }));

        // Get dispatch functions
        const { editPost } = useDispatch('core/editor');

        // Only show for posts (episodes)
        if (postType !== 'post') {
            return null;
        }

        // Update meta helper
        const updateMeta = (key, value) => {
            editPost({ meta: { ...meta, [key]: value } });
        };

        // Validation helper
        const validateField = (key, value) => {
            // Simple validation logic
            const errors = { ...validationErrors };
            
            if (key === 'episode_subtitle' && value && value.length > 255) {
                errors[key] = 'Subtitle must be 255 characters or less';
            } else if (key === 'episode_audio_url' && value && !value.match(/^https?:\/\//)) {
                errors[key] = 'Please enter a valid URL';
            } else if (key === 'episode_chapters_url' && value && !value.match(/^https?:\/\//)) {
                errors[key] = 'Please enter a valid URL';
            } else if (key === 'episode_transcript_url' && value && !value.match(/^https?:\/\//)) {
                errors[key] = 'Please enter a valid URL';
            } else {
                delete errors[key];
            }
            
            setValidationErrors(errors);
        };

        // Media upload handler
        const handleMediaUpload = (field, allowedTypes) => {
            const mediaUploader = wp.media({
                title: 'Select Audio File',
                button: {
                    text: 'Use this file'
                },
                library: {
                    type: allowedTypes
                },
                multiple: false
            });

            mediaUploader.on('select', function() {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                updateMeta(field, attachment.id);
                validateField(field, attachment.id);
            });

            mediaUploader.open();
        };

        return createElement(PluginDocumentSettingPanel, {
            name: "betterfeed-episode-settings",
            title: "Podcast Episode Settings",
            className: "betterfeed-episode-panel"
        }, [
            // Basic Information
            createElement(BaseControl, { key: 'basic-info' },
                createElement('h3', null, 'Basic Information')
            ),
            
            createElement(TextControl, {
                key: 'episode-subtitle',
                label: "Episode Subtitle",
                value: meta.episode_subtitle || '',
                onChange: (value) => {
                    updateMeta('episode_subtitle', value);
                    validateField('episode_subtitle', value);
                },
                help: validationErrors.episode_subtitle || 'Short description (max 255 characters)'
            }),

            createElement(TextControl, {
                key: 'episode-author',
                label: "Episode Author",
                value: meta.episode_author || '',
                onChange: (value) => updateMeta('episode_author', value),
                help: "Episode creator/host (defaults to show author)"
            }),

            // Audio File
            createElement(BaseControl, { key: 'audio-header' },
                createElement('h3', null, 'Audio File')
            ),

            createElement(BaseControl, {
                key: 'audio-file',
                label: "Audio File",
                help: "Select an audio file or enter a URL"
            }, createElement('div', { 
                style: { display: 'flex', gap: '8px', alignItems: 'center' } 
            }, [
                createElement(Button, {
                    key: 'select-audio',
                    onClick: () => handleMediaUpload('episode_audio_url', ['audio']),
                    variant: "secondary"
                }, "Select Audio"),
                createElement('span', { 
                    key: 'or-text',
                    style: { color: '#666' } 
                }, "or")
            ])),

            createElement(TextControl, {
                key: 'audio-url',
                label: "Audio URL",
                value: meta.episode_audio_url || '',
                onChange: (value) => {
                    updateMeta('episode_audio_url', value);
                    validateField('episode_audio_url', value);
                },
                help: validationErrors.episode_audio_url || 'Direct URL to audio file'
            }),

            createElement(TextControl, {
                key: 'duration',
                label: "Duration (HH:MM:SS)",
                value: meta.episode_duration || '',
                onChange: (value) => updateMeta('episode_duration', value),
                help: "Episode duration in HH:MM:SS format"
            }),

            createElement(TextControl, {
                key: 'file-size',
                label: "File Size (bytes)",
                value: meta.episode_audio_length || '',
                onChange: (value) => updateMeta('episode_audio_length', value),
                help: "Audio file size in bytes"
            }),

            // Episode Details
            createElement(BaseControl, { key: 'details-header' },
                createElement('h3', null, 'Episode Details')
            ),

            createElement(SelectControl, {
                key: 'episode-type',
                label: "Episode Type",
                value: meta.episode_type || 'full',
                options: [
                    { label: 'Full Episode', value: 'full' },
                    { label: 'Trailer', value: 'trailer' },
                    { label: 'Bonus', value: 'bonus' }
                ],
                onChange: (value) => updateMeta('episode_type', value)
            }),

            createElement(TextControl, {
                key: 'season',
                label: "Season Number",
                value: meta.episode_season || '',
                onChange: (value) => updateMeta('episode_season', value),
                help: "Season number (optional)"
            }),

            createElement(TextControl, {
                key: 'episode-num',
                label: "Episode Number",
                value: meta.episode_number || '',
                onChange: (value) => updateMeta('episode_number', value),
                help: "Episode number within the season"
            }),

            createElement(TextControl, {
                key: 'chapters',
                label: "Chapters URL",
                value: meta.episode_chapters_url || '',
                onChange: (value) => {
                    updateMeta('episode_chapters_url', value);
                    validateField('episode_chapters_url', value);
                },
                help: validationErrors.episode_chapters_url || 'URL to chapters JSON file'
            }),

            createElement(TextControl, {
                key: 'transcript',
                label: "Transcript URL",
                value: meta.episode_transcript_url || '',
                onChange: (value) => {
                    updateMeta('episode_transcript_url', value);
                    validateField('episode_transcript_url', value);
                },
                help: validationErrors.episode_transcript_url || 'URL to transcript file'
            }),

            createElement(ToggleControl, {
                key: 'block-episode',
                label: "Block Episode",
                checked: meta.episode_block || false,
                onChange: (value) => updateMeta('episode_block', value),
                help: "Prevent this episode from appearing in podcast directories"
            }),

            // Validation Summary
            Object.keys(validationErrors).length > 0 && createElement(Notice, {
                key: 'validation-notice',
                status: "warning",
                isDismissible: false
            }, "Please fix the validation errors above.")
        ]);
    }

    // Register the plugin
    domReady(function() {
        if (wp.plugins && wp.plugins.registerPlugin) {
            registerPlugin('betterfeed-episode-settings', {
                render: EpisodeSettingsPanel,
                icon: 'microphone'
            });
        }
    });

})(
    wp.domReady,
    wp,
    wp.data,
    wp.components,
    wp.element,
    wp.mediaUtils
);