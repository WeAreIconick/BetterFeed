/**
 * BetterFeed Episode Settings Panel for Gutenberg
 * Provides podcast episode settings in the Block Editor
 */

(function(domReady, wp, wpData, wpComponents, wpElement, wpMediaUtils, wpBlob) {
    'use strict';

    const { registerPlugin } = wp.plugins;
    const { PluginDocumentSettingPanel } = wp.editPost;
    const { useSelect, useDispatch } = wpData;
    const { useState, useEffect } = wpElement;
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
            const errors = { ...validationErrors };
            
            switch (key) {
                case 'episode_audio_url':
                    if (value && !isValidUrl(value) && !isValidAttachmentId(value)) {
                        errors[key] = 'Please enter a valid audio URL or select a media file';
                    } else {
                        delete errors[key];
                    }
                    break;
                case 'episode_duration':
                    if (value && !isValidDuration(value)) {
                        errors[key] = 'Please enter duration in HH:MM:SS format or seconds';
                    } else {
                        delete errors[key];
                    }
                    break;
                case 'episode_chapters_url':
                case 'episode_transcript_url':
                    if (value && !isValidUrl(value)) {
                        errors[key] = 'Please enter a valid URL';
                    } else {
                        delete errors[key];
                    }
                    break;
                case 'episode_subtitle':
                    if (value && value.length > 255) {
                        errors[key] = 'Subtitle must be 255 characters or less';
                    } else {
                        delete errors[key];
                    }
                    break;
            }
            
            setValidationErrors(errors);
        };

        // Validation functions
        const isValidUrl = (url) => {
            try {
                new URL(url);
                return true;
            } catch {
                return false;
            }
        };

        const isValidAttachmentId = (id) => {
            return /^\d+$/.test(id) && parseInt(id) > 0;
        };

        const isValidDuration = (duration) => {
            // Check HH:MM:SS format
            if (/^\d{1,2}:\d{2}:\d{2}$/.test(duration)) {
                return true;
            }
            // Check seconds format
            if (/^\d+$/.test(duration)) {
                return true;
            }
            return false;
        };

        // Media upload handler
        const handleMediaUpload = (field, allowedTypes = ['audio']) => {
            const mediaUploader = wp.media({
                title: 'Select Audio File',
                button: { text: 'Select Audio' },
                multiple: false,
                library: { type: allowedTypes }
            });

            mediaUploader.on('select', function() {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                updateMeta(field, attachment.id);
                validateField(field, attachment.id);
            });

            mediaUploader.open();
        };

        return (
            <PluginDocumentSettingPanel
                name="betterfeed-episode-settings"
                title="Podcast Episode Settings"
                className="betterfeed-episode-panel"
            >
                {/* Basic Information */}
                <BaseControl>
                    <h3>Basic Information</h3>
                </BaseControl>

                <TextControl
                    label="Episode Subtitle"
                    value={meta.episode_subtitle || ''}
                    onChange={(value) => {
                        updateMeta('episode_subtitle', value);
                        validateField('episode_subtitle', value);
                    }}
                    help={validationErrors.episode_subtitle || 'Short description (max 255 characters)'}
                />

                <TextControl
                    label="Episode Author"
                    value={meta.episode_author || ''}
                    onChange={(value) => updateMeta('episode_author', value)}
                    help="Episode creator/host (defaults to show author)"
                />

                {/* Audio File */}
                <BaseControl>
                    <h3>Audio File</h3>
                </BaseControl>

                <BaseControl label="Audio File" help="Select an audio file or enter a URL">
                    <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                        <Button
                            onClick={() => handleMediaUpload('episode_audio_url', ['audio'])}
                            variant="secondary"
                        >
                            Select Audio
                        </Button>
                        <span style={{ color: '#666' }}>or</span>
                    </div>
                </BaseControl>

                <TextControl
                    label="Audio URL"
                    value={meta.episode_audio_url || ''}
                    onChange={(value) => {
                        updateMeta('episode_audio_url', value);
                        validateField('episode_audio_url', value);
                    }}
                    help={validationErrors.episode_audio_url || 'Direct URL to audio file'}
                />

                <TextControl
                    label="Duration"
                    value={meta.episode_duration || ''}
                    onChange={(value) => {
                        updateMeta('episode_duration', value);
                        validateField('episode_duration', value);
                    }}
                    help={validationErrors.episode_duration || 'Format: HH:MM:SS or seconds'}
                />

                {/* Episode Details */}
                <BaseControl>
                    <h3>Episode Details</h3>
                </BaseControl>

                <div style={{ display: 'flex', gap: '16px' }}>
                    <TextControl
                        label="Season Number"
                        type="number"
                        value={meta.episode_season || ''}
                        onChange={(value) => updateMeta('episode_season', parseInt(value) || '')}
                        min="1"
                    />

                    <TextControl
                        label="Episode Number"
                        type="number"
                        value={meta.episode_number || ''}
                        onChange={(value) => updateMeta('episode_number', parseInt(value) || '')}
                        min="1"
                    />
                </div>

                <SelectControl
                    label="Episode Type"
                    value={meta.episode_type || 'full'}
                    options={[
                        { label: 'Full Episode', value: 'full' },
                        { label: 'Trailer', value: 'trailer' },
                        { label: 'Bonus', value: 'bonus' }
                    ]}
                    onChange={(value) => updateMeta('episode_type', value)}
                />

                <SelectControl
                    label="Explicit Content"
                    value={meta.episode_explicit || 'false'}
                    options={[
                        { label: 'No (Clean)', value: 'false' },
                        { label: 'Yes (Explicit)', value: 'true' }
                    ]}
                    onChange={(value) => updateMeta('episode_explicit', value)}
                />

                {/* Episode Artwork */}
                <BaseControl>
                    <h3>Episode Artwork</h3>
                </BaseControl>

                <BaseControl label="Episode Artwork" help="Optional episode-specific artwork">
                    <Button
                        onClick={() => handleMediaUpload('episode_artwork', ['image'])}
                        variant="secondary"
                    >
                        Select Episode Artwork
                    </Button>
                </BaseControl>

                {/* Advanced */}
                <BaseControl>
                    <h3>Advanced</h3>
                </BaseControl>

                <TextControl
                    label="Chapters URL"
                    value={meta.episode_chapters_url || ''}
                    onChange={(value) => {
                        updateMeta('episode_chapters_url', value);
                        validateField('episode_chapters_url', value);
                    }}
                    help={validationErrors.episode_chapters_url || 'URL to chapters JSON file'}
                />

                <TextControl
                    label="Transcript URL"
                    value={meta.episode_transcript_url || ''}
                    onChange={(value) => {
                        updateMeta('episode_transcript_url', value);
                        validateField('episode_transcript_url', value);
                    }}
                    help={validationErrors.episode_transcript_url || 'URL to transcript file'}
                />

                <ToggleControl
                    label="Block Episode"
                    checked={meta.episode_block || false}
                    onChange={(value) => updateMeta('episode_block', value)}
                    help="Prevent this episode from appearing in podcast directories"
                />

                {/* Validation Summary */}
                {Object.keys(validationErrors).length > 0 && (
                    <Notice status="warning" isDismissible={false}>
                        Please fix the validation errors above.
                    </Notice>
                )}
            </PluginDocumentSettingPanel>
        );
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
    wp.mediaUtils,
    wp.blob
);
