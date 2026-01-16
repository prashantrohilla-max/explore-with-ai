<?php
/**
 * Plugin Name: Explore with AI
 * Plugin URI: https://github.com/prashantrohilla-max/explore-with-ai-wp-plugin
 * Description: Add links to explore your content with various AI assistants (ChatGPT, Claude, DeepSeek, Grok, Perplexity)
 * Version: 1.0.0
 * Author: Prashant R
 * License: GPL v2 or later
 * Text Domain: explore-with-ai
 */

if (!defined('ABSPATH')) {
    exit;
}

class Explore_With_AI {

    private static $instance = null;

    private $ai_providers = array(
        'chatgpt' => array(
            'name' => 'ChatGPT',
            'url_template' => 'https://chatgpt.com/?q=Read+%s&hints=search',
            'icon' => 'chatgpt',
            'color' => '#10a37f'
        ),
        'claude' => array(
            'name' => 'Claude',
            'url_template' => 'https://claude.ai/new?q=Read+%s',
            'icon' => 'claude',
            'color' => '#d97706'
        ),
        'deepseek' => array(
            'name' => 'DeepSeek',
            'url_template' => 'https://chat.deepseek.com/?q=Read+%s',
            'icon' => 'deepseek',
            'color' => '#4f46e5'
        ),
        'grok' => array(
            'name' => 'Grok',
            'url_template' => 'https://x.com/i/grok?text=Read+%s',
            'icon' => 'grok',
            'color' => '#000000'
        ),
        'perplexity' => array(
            'name' => 'Perplexity',
            'url_template' => 'https://www.perplexity.ai/?q=Read+%s',
            'icon' => 'perplexity',
            'color' => '#20b2aa'
        )
    );

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_filter('the_content', array($this, 'add_ai_buttons_to_content'));
        add_shortcode('explore_with_ai', array($this, 'shortcode_handler'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=explore-with-ai') . '">' . __('Settings', 'explore-with-ai') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function enqueue_styles() {
        wp_enqueue_style(
            'explore-with-ai',
            plugin_dir_url(__FILE__) . 'assets/css/explore-with-ai.css',
            array(),
            '1.0.0'
        );
    }

    public function add_admin_menu() {
        add_options_page(
            __('Explore with AI Settings', 'explore-with-ai'),
            __('Explore with AI', 'explore-with-ai'),
            'manage_options',
            'explore-with-ai',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('explore_with_ai_options', 'explore_with_ai_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));

        add_settings_section(
            'explore_with_ai_providers',
            __('AI Providers', 'explore-with-ai'),
            array($this, 'providers_section_callback'),
            'explore-with-ai'
        );

        foreach ($this->ai_providers as $key => $provider) {
            add_settings_field(
                'enable_' . $key,
                $provider['name'],
                array($this, 'render_checkbox_field'),
                'explore-with-ai',
                'explore_with_ai_providers',
                array('key' => $key, 'provider' => $provider)
            );
        }

        add_settings_section(
            'explore_with_ai_display',
            __('Display Settings', 'explore-with-ai'),
            array($this, 'display_section_callback'),
            'explore-with-ai'
        );

        add_settings_field(
            'auto_display',
            __('Auto Display', 'explore-with-ai'),
            array($this, 'render_auto_display_field'),
            'explore-with-ai',
            'explore_with_ai_display'
        );

        add_settings_field(
            'display_position',
            __('Position', 'explore-with-ai'),
            array($this, 'render_position_field'),
            'explore-with-ai',
            'explore_with_ai_display'
        );

        add_settings_field(
            'button_label',
            __('Section Label', 'explore-with-ai'),
            array($this, 'render_label_field'),
            'explore-with-ai',
            'explore_with_ai_display'
        );
    }

    public function sanitize_settings($input) {
        $sanitized = array();

        foreach ($this->ai_providers as $key => $provider) {
            $sanitized['enable_' . $key] = isset($input['enable_' . $key]) ? 1 : 0;
        }

        $sanitized['auto_display'] = isset($input['auto_display']) ? 1 : 0;
        $sanitized['display_position'] = isset($input['display_position']) ? sanitize_text_field($input['display_position']) : 'before';
        $sanitized['button_label'] = isset($input['button_label']) ? sanitize_text_field($input['button_label']) : '';

        return $sanitized;
    }

    public function providers_section_callback() {
        echo '<p>' . esc_html__('Enable or disable AI providers to display.', 'explore-with-ai') . '</p>';
    }

    public function display_section_callback() {
        echo '<p>' . esc_html__('Configure how and where the buttons appear.', 'explore-with-ai') . '</p>';
    }

    public function render_checkbox_field($args) {
        $options = get_option('explore_with_ai_settings', $this->get_default_settings());
        $key = $args['key'];
        $checked = isset($options['enable_' . $key]) ? $options['enable_' . $key] : 1;
        ?>
        <label>
            <input type="checkbox"
                   name="explore_with_ai_settings[enable_<?php echo esc_attr($key); ?>]"
                   value="1"
                   <?php checked(1, $checked); ?>>
            <?php echo esc_html__('Enable', 'explore-with-ai'); ?>
        </label>
        <?php
    }

    public function render_auto_display_field() {
        $options = get_option('explore_with_ai_settings', $this->get_default_settings());
        $checked = isset($options['auto_display']) ? $options['auto_display'] : 1;
        ?>
        <label>
            <input type="checkbox"
                   name="explore_with_ai_settings[auto_display]"
                   value="1"
                   <?php checked(1, $checked); ?>>
            <?php echo esc_html__('Automatically display on single posts', 'explore-with-ai'); ?>
        </label>
        <p class="description">
            <?php echo esc_html__('You can also use the shortcode [explore_with_ai] to display manually.', 'explore-with-ai'); ?>
        </p>
        <?php
    }

    public function render_position_field() {
        $options = get_option('explore_with_ai_settings', $this->get_default_settings());
        $position = isset($options['display_position']) ? $options['display_position'] : 'before';
        ?>
        <select name="explore_with_ai_settings[display_position]">
            <option value="before" <?php selected('before', $position); ?>>
                <?php echo esc_html__('Before content', 'explore-with-ai'); ?>
            </option>
            <option value="after" <?php selected('after', $position); ?>>
                <?php echo esc_html__('After content', 'explore-with-ai'); ?>
            </option>
        </select>
        <?php
    }

    public function render_label_field() {
        $options = get_option('explore_with_ai_settings', $this->get_default_settings());
        $label = isset($options['button_label']) ? $options['button_label'] : 'Explore this article with AI:';
        ?>
        <input type="text"
               name="explore_with_ai_settings[button_label]"
               value="<?php echo esc_attr($label); ?>"
               class="regular-text">
        <p class="description">
            <?php echo esc_html__('Leave empty to hide the label.', 'explore-with-ai'); ?>
        </p>
        <?php
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Explore with AI Settings', 'explore-with-ai'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('explore_with_ai_options');
                do_settings_sections('explore-with-ai');
                submit_button();
                ?>
            </form>

            <div class="explore-with-ai-preview">
                <h2><?php echo esc_html__('Preview', 'explore-with-ai'); ?></h2>
                <p><?php echo esc_html__('This is how the buttons will appear:', 'explore-with-ai'); ?></p>
                <?php echo wp_kses_post($this->render_buttons(home_url())); ?>
            </div>

            <div class="explore-with-ai-shortcode">
                <h2><?php echo esc_html__('Shortcode Usage', 'explore-with-ai'); ?></h2>
                <p><?php echo esc_html__('Use the following shortcode to manually place the buttons:', 'explore-with-ai'); ?></p>
                <code>[explore_with_ai]</code>
                <p><?php echo esc_html__('Optional parameters:', 'explore-with-ai'); ?></p>
                <ul>
                    <li><code>[explore_with_ai url="https://example.com"]</code> - <?php echo esc_html__('Custom URL', 'explore-with-ai'); ?></li>
                    <li><code>[explore_with_ai providers="chatgpt,claude"]</code> - <?php echo esc_html__('Specific providers only', 'explore-with-ai'); ?></li>
                </ul>
            </div>
        </div>
        <style>
            .explore-with-ai-preview {
                background: #fff;
                padding: 20px;
                margin-top: 20px;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }
            .explore-with-ai-preview .explore-with-ai-buttons {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }
            .explore-with-ai-preview .explore-with-ai-label {
                margin: 0 0 10px 0;
            }
            .explore-with-ai-shortcode {
                margin-top: 20px;
            }
            .explore-with-ai-shortcode code {
                display: inline-block;
                padding: 5px 10px;
                background: #f0f0f1;
                margin: 5px 0;
            }
            .explore-with-ai-shortcode ul {
                list-style: disc;
                margin-left: 20px;
            }
        </style>
        <?php
    }

    private function get_default_settings() {
        $defaults = array(
            'auto_display' => 1,
            'display_position' => 'before',
            'button_label' => 'Explore this article with AI:'
        );

        foreach ($this->ai_providers as $key => $provider) {
            $defaults['enable_' . $key] = 1;
        }

        return $defaults;
    }

    public function add_ai_buttons_to_content($content) {
        if (!is_singular('post')) {
            return $content;
        }

        $options = get_option('explore_with_ai_settings', $this->get_default_settings());

        if (empty($options['auto_display'])) {
            return $content;
        }

        $buttons = $this->render_buttons();
        $position = isset($options['display_position']) ? $options['display_position'] : 'before';

        if ($position === 'before') {
            return $buttons . $content;
        } else {
            return $content . $buttons;
        }
    }

    public function shortcode_handler($atts) {
        $atts = shortcode_atts(array(
            'url' => '',
            'providers' => ''
        ), $atts, 'explore_with_ai');

        $url = !empty($atts['url']) ? $atts['url'] : '';
        $providers = !empty($atts['providers']) ? array_map('trim', explode(',', $atts['providers'])) : array();

        return $this->render_buttons($url, $providers);
    }

    public function render_buttons($custom_url = '', $specific_providers = array()) {
        $options = get_option('explore_with_ai_settings', $this->get_default_settings());

        $current_url = !empty($custom_url) ? $custom_url : get_permalink();
        $encoded_url = rawurlencode($current_url);

        $label = isset($options['button_label']) ? $options['button_label'] : 'Explore this article with AI:';

        $output = '<div class="explore-with-ai-container">';

        if (!empty($label)) {
            $output .= '<p class="explore-with-ai-label">' . esc_html($label) . '</p>';
        }

        $output .= '<div class="explore-with-ai-buttons">';

        foreach ($this->ai_providers as $key => $provider) {
            // Check if provider is enabled in settings
            if (empty($options['enable_' . $key])) {
                continue;
            }

            // Check if specific providers filter is applied
            if (!empty($specific_providers) && !in_array($key, $specific_providers)) {
                continue;
            }

            $url = sprintf($provider['url_template'], $encoded_url);

            /* translators: %s: AI provider name (e.g., ChatGPT, Claude) */
            $output .= sprintf(
                '<a href="%s" class="explore-with-ai-button explore-with-ai-%s" target="_blank" rel="noopener noreferrer" title="%s">%s</a>',
                esc_url($url),
                esc_attr($key),
                esc_attr(sprintf(__('Explore with %s', 'explore-with-ai'), $provider['name'])),
                esc_html($provider['name'])
            );
        }

        $output .= '</div></div>';

        return $output;
    }
}

// Initialize the plugin
function explore_with_ai_init() {
    return Explore_With_AI::get_instance();
}
add_action('plugins_loaded', 'explore_with_ai_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    $defaults = array(
        'auto_display' => 1,
        'display_position' => 'before',
        'button_label' => 'Explore this article with AI:',
        'enable_chatgpt' => 1,
        'enable_claude' => 1,
        'enable_deepseek' => 1,
        'enable_grok' => 1,
        'enable_perplexity' => 1
    );

    if (false === get_option('explore_with_ai_settings')) {
        add_option('explore_with_ai_settings', $defaults);
    }
});
