<?php

namespace MWBDigital\GravityForms;

/**
 * Custom "Gravity Forms" functionality
 */
class GravityForms
{
    /**
     * Setup integration
     */
    public function __construct()
    {
        // Check required WP plugins are enabled
        if (!self::has_required_plugins(get_class($this), ['gravityforms/gravityforms.php'])) {
            return;
        }

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
     * @param string $class_name Class name
     * @param array $required_plugins List of WP plugin main file paths
     * @return boolean
     */
    private static function has_required_plugins(string $class_name, array $required_plugins = []): bool
    {
        $missing_plugins = array_diff($required_plugins, (array)get_option('active_plugins', []));
        // Skip if there's no missing plugins
        if (empty($missing_plugins)) {
            return true;
        }

        // Log missing plugins
        if(wp_get_environment_type() !== 'production') {
            foreach ($missing_plugins as $missing_plugin) {
                error_log("Warning: '$class_name' package requires inactive '$missing_plugin' plugin");
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
        $theme_colours = self::get_theme_colour_choices();
        foreach ($theme_colours as $key => $label) {
            $colours[] = [
                'label' => $label,
                'value' => $key
            ];
        }

        $styles = [];
        $theme_styles = self::get_button_styles();
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

    /**
     * Get button style choices
     *
     * @return array List of button styles (e.g. [ 'outlined' => 'Outlined' ])
     */
    public static function get_button_styles(): array
    {
        return apply_filters('one/theme/button_styles', [
            'clear' => 'Clear',
            'expanded' => 'Expanded',
            'outlined' => 'Outlined',
            'big' => 'Big',
        ]);
    }

    /**
     * Get theme colours (while allowing for child theme to alter them)
     *
     * @param boolean $label Use this to retrieve a specific colour if present
     * @return (array|string)
     */
    public static function get_theme_colours($label = false)
    {
        // get theme colours (or use child's ones if given)
        $colours = apply_filters('one/theme/colours', [
            'primary' => get_theme_mod('primary_colour', '#000'),
            'secondary' => get_theme_mod('secondary_colour', '#666'),
            'tertiary' => get_theme_mod('tertiary_colour', '#b5b5b5'),
            'dark' => get_theme_mod('dark_colour', '#21111E'),
            'light' => get_theme_mod('light_colour', '#fff'),
            'bg' => get_theme_mod('bg_colour', '#fff'),
        ]);

        if (!!$label) {
            if (array_key_exists($label, $colours)) {
                return $colours[$label];
            }
            return '';
        }

        return $colours;
    }

    /**
     * Get theme colours for a dropdown
     *
     * @return array List of theme colours by key and label
     */
    public static function get_theme_colour_choices(): array
    {
        $colours = self::get_theme_colours();

        // Convert colours into dropdown choices
        $choices = [];
        foreach ($colours as $key => $colour) {
            // Convert slug string to pretty title
            $label = ucfirst(str_replace('-', ' ', $key));

            $choices[$key] = $label;
        }

        return $choices;
    }

}