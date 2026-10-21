<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Settings → GitHub Theme Updater
 *
 * Stores one option array (GTU_OPTION_KEY) with:
 *  - theme_slug   (string)  folder name of the theme to update, no dot
 *  - github_user  (string)
 *  - github_repo  (string)
 *  - visibility   ('public'|'private')
 *  - github_token (string)  only used/shown when visibility = private
 *  - force_auto   ('1'|'')  force WP's background cron to auto-install
 */
class GTU_Settings
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_settings_page()
    {
        add_options_page(
            __('GitHub Theme Updater', 'github-theme-updater'),
            __('GitHub Theme Updater', 'github-theme-updater'),
            'manage_options',
            'gtu-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings()
    {
        register_setting('gtu_settings_group', GTU_OPTION_KEY, [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);

        add_settings_section(
            'gtu_main_section',
            __('Repository', 'github-theme-updater'),
            function () {
                echo '<p>' . esc_html__('Point this site at the GitHub repository that builds your theme. Each release must attach a .zip named after the theme slug below (the auto-release.yml workflow from the theme template does this automatically).', 'github-theme-updater') . '</p>';
            },
            'gtu-settings'
        );

        $fields = [
            'theme_slug'   => __('Theme slug (folder name, no dots)', 'github-theme-updater'),
            'github_user'  => __('GitHub username / organization', 'github-theme-updater'),
            'github_repo'  => __('GitHub repository name', 'github-theme-updater'),
            'visibility'   => __('Repository visibility', 'github-theme-updater'),
            'github_token' => __('GitHub token (private repos only)', 'github-theme-updater'),
            'force_auto'   => __('Force automatic background updates', 'github-theme-updater'),
        ];

        foreach ($fields as $key => $label) {
            add_settings_field(
                $key,
                $label,
                [$this, 'render_field_' . $key],
                'gtu-settings',
                'gtu_main_section'
            );
        }
    }

    public function sanitize_settings($input)
    {
        $output = [];

        $output['theme_slug']  = isset($input['theme_slug']) ? sanitize_title($input['theme_slug']) : '';
        $output['github_user'] = isset($input['github_user']) ? sanitize_text_field($input['github_user']) : '';
        $output['github_repo'] = isset($input['github_repo']) ? sanitize_text_field($input['github_repo']) : '';
        $output['visibility']  = (isset($input['visibility']) && $input['visibility'] === 'private') ? 'private' : 'public';
        $output['force_auto']  = empty($input['force_auto']) ? '' : '1';

        // Keep the previously saved token if the field was left blank
        // (so the masked placeholder doesn't wipe it on every save).
        $existing = get_option(GTU_OPTION_KEY, []);
        $new_token = isset($input['github_token']) ? trim($input['github_token']) : '';
        $output['github_token'] = ($new_token !== '') ? $new_token : ($existing['github_token'] ?? '');

        if (! empty($output['theme_slug']) && strpos($output['theme_slug'], '.') !== false) {
            add_settings_error(
                GTU_OPTION_KEY,
                'gtu_dot_in_slug',
                __('Warning: the theme slug contains a dot. WordPress strips dots when sanitizing theme slugs internally, which silently breaks both manual and automatic updates. Rename the theme folder to remove the dot.', 'github-theme-updater'),
                'error'
            );
        }

        return $output;
    }

    private function get($key, $default = '')
    {
        $settings = get_option(GTU_OPTION_KEY, []);
        return $settings[$key] ?? $default;
    }

    public function render_field_theme_slug()
    {
        printf(
            '<input type="text" name="%1$s[theme_slug]" value="%2$s" class="regular-text" placeholder="%3$s" />',
            esc_attr(GTU_OPTION_KEY),
            esc_attr($this->get('theme_slug')),
            esc_attr(get_option('template'))
        );
        echo '<p class="description">' . esc_html__('Must match the installed theme folder exactly. No dots allowed.', 'github-theme-updater') . '</p>';
    }

    public function render_field_github_user()
    {
        printf(
            '<input type="text" name="%1$s[github_user]" value="%2$s" class="regular-text" />',
            esc_attr(GTU_OPTION_KEY),
            esc_attr($this->get('github_user'))
        );
    }

    public function render_field_github_repo()
    {
        printf(
            '<input type="text" name="%1$s[github_repo]" value="%2$s" class="regular-text" />',
            esc_attr(GTU_OPTION_KEY),
            esc_attr($this->get('github_repo'))
        );
    }

    public function render_field_visibility()
    {
        $visibility = $this->get('visibility', 'public');
        ?>
        <label>
            <input type="radio" name="<?php echo esc_attr(GTU_OPTION_KEY); ?>[visibility]" value="public" <?php checked($visibility, 'public'); ?> />
            <?php esc_html_e('Public — reads a small manifest via raw.githubusercontent.com (no token needed, effectively no rate limit).', 'github-theme-updater'); ?>
        </label><br />
        <label>
            <input type="radio" name="<?php echo esc_attr(GTU_OPTION_KEY); ?>[visibility]" value="private" <?php checked($visibility, 'private'); ?> />
            <?php esc_html_e('Private — calls the GitHub API with a token (5000 req/hour, required to access a private repo at all).', 'github-theme-updater'); ?>
        </label>
        <?php
    }

    public function render_field_github_token()
    {
        $has_token = $this->get('github_token') !== '';
        printf(
            '<input type="password" name="%1$s[github_token]" value="" class="regular-text" autocomplete="new-password" placeholder="%2$s" />',
            esc_attr(GTU_OPTION_KEY),
            $has_token ? esc_attr__('•••••••• (saved — leave blank to keep it)', 'github-theme-updater') : esc_attr__('ghp_... or github_pat_...', 'github-theme-updater')
        );
        echo '<p class="description">' . esc_html__('Fine-grained token scoped read-only to Contents + Releases on this one repo. Only required when visibility is Private. Stored in the database, never shown again in full.', 'github-theme-updater') . '</p>';
    }

    public function render_field_force_auto()
    {
        printf(
            '<label><input type="checkbox" name="%1$s[force_auto]" value="1" %2$s /> %3$s</label>',
            esc_attr(GTU_OPTION_KEY),
            checked($this->get('force_auto'), '1', false),
            esc_html__('Install updates automatically in the background, without waiting for the admin to click "Update now" (WordPress does not always show the auto-update toggle for themes).', 'github-theme-updater')
        );
    }

    public function render_settings_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('GitHub Theme Updater', 'github-theme-updater'); ?></h1>
            <p><?php esc_html_e('Built by Abdulrahman Salah — a-salah.dev', 'github-theme-updater'); ?></p>
            <form action="options.php" method="post">
                <?php
                settings_fields('gtu_settings_group');
                do_settings_sections('gtu-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
