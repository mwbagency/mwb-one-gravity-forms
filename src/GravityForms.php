<?php

namespace MWBDigital\GravityForms;

use \App\Theme;
use \App\Site;

/**
 * Custom "Gravity Forms" functionality
 */
class GravityForms
{

    protected $required_plugins = ['gravityforms/gravityforms.php'];

    /**
     * Setup hooks for MWB plugin
     */
    public function __construct()
    {
        // Check required WP plugins are enabled
        if (!self::has_required_plugins(get_class($this), $this->required_plugins)) {
            return;
        }

        // Allow MWB plugin to setup their specific hooks
        $this->init();
    }

    /**
     * Setup hooks and filters
     *
     * @return void
     */
    public function init()
    {
        add_filter('gform_form_settings_fields', [__CLASS__, 'add_button_class_fields'], 10, 2);
        add_filter('gform_submit_button', [__CLASS__, 'apply_submit_button_classes'], 10, 2);
        add_filter('gform_disable_css', '__return_true');
    }

    /**
     * Whether given WP plugins are enabled
     *
     * @param string $class_name MWB plugin class name
     * @param array $required_plugins List of WP plugin main file paths
     * @return boolean
     */
    private static function has_required_plugins(string $class_name, array $required_plugins = []): bool
    {
        // Skip if there's no missing plugins
        if (false == ($missing_plugins = Site::get_missing_plugins($required_plugins))) {
            return true;
        }

        // Log missing plugins
        if(wp_get_environment_type() !== 'production') {
            foreach ($missing_plugins as $missing_plugin) {
                error_log("Warning: '$class_name' plugin requires inactive WP '$missing_plugin' plugin");
            }
        }

        return false;
    }

    /**
     * Add fields to allow styling of submit button
     *
     * @param array $fields Form settings fields
     * @return array Altered settings fields
     */
    public static function add_button_class_fields(array $fields, $form): array
    {
        $colours = [];
        $theme_colours = Theme::get_theme_colour_choices();
        foreach ($theme_colours as $key => $label) {
            $colours[] = [
                'label' => $label,
                'value' => $key
            ];
        }

        $styles = [];
        $theme_styles = Theme::get_button_styles();
        foreach ($theme_styles as $key => $label) {
            $styles[] = [
                'label' => $label,
                'name' => 'button_styles_' . $key,
                'value' => $key
            ];
        }

        $fields['form_button']['fields'][] = [
            'label' => 'Button Colour',
            'name' => 'button_colour',
            'type' => 'select',
            'choices' => $colours,
            'default_value' => 'secondary',
        ];

        $fields['form_button']['fields'][] = [
            'label' => 'Button Styles',
            'name' => 'button_styles',
            'type' => 'checkbox',
            'choices' => $styles,
        ];

        return $fields;
    }

    /**
     * Apply button classes
     *
     * @param string $button Button HTML
     * @param array $form Form instance array
     * @return string   Updated $button with additional button classes
     */
    public static function apply_submit_button_classes($button, $form)
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $button);
        $input = $dom->getElementsByTagName('input')->item(0);
        $new_button = $dom->createElement('button');
        $new_button->appendChild($dom->createTextNode($input->getAttribute('value')));
        $input->removeAttribute('value');

        foreach ($input->attributes as $attribute) {
            $new_button->setAttribute($attribute->name, $attribute->value);
        }

        $input->parentNode->replaceChild($new_button, $input);
        $classes = $new_button->getAttribute('class');

        if ($colour = $form['button_colour'] ?? false) {
            $classes .= ' ' . $colour;
        }

        if ($styles = Theme::get_button_styles()) {
            foreach ($styles as $key => $label) {
                if ($form['button_styles_' . $key] ?? false) {
                    $classes .= ' ' . $key;
                }
            }
        }

        $new_button->setAttribute('class', $classes);
        return $dom->saveHtml($new_button);
    }

}