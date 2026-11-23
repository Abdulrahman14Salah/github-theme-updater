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
            <?php $this->render_debug_panel(); ?>
        </div>
        <?php
    }

    /**
     * Debug panel: runs the exact same GitHub request the updater uses and
     * prints the raw result. Safe, read-only, no changes to any stored data.
     * Kept permanently so future setups (new sites/themes) can self-diagnose
     * "why isn't my update showing" without needing server/log access.
     */
    private function render_debug_panel()
    {
        $settings = get_option(GTU_OPTION_KEY, []);

        $theme_slug  = $settings['theme_slug'] ?? '';
        $github_user = $settings['github_user'] ?? '';
        $github_repo = $settings['github_repo'] ?? '';
        $visibility  = ($settings['visibility'] ?? 'public') === 'private' ? 'private' : 'public';
        $token       = $settings['github_token'] ?? '';

        echo '<hr/><h2>' . esc_html__('Debug', 'github-theme-updater') . '</h2>';
        echo '<p class="description">' . esc_html__('Live diagnostic of the exact GitHub request this plugin makes — use this to see why an update is or isn\'t detected.', 'github-theme-updater') . '</p>';

        if (empty($theme_slug) || empty($github_user) || empty($github_repo)) {
            echo '<p style="color:red;">' . esc_html__('Essential settings are empty — the updater never runs. Fill theme slug, GitHub username and repository above and save.', 'github-theme-updater') . '</p>';
            return;
        }

        $theme_data      = wp_get_theme($theme_slug);
        $current_version = $theme_data->exists() ? $theme_data->get('Version') : null;

        echo '<table class="widefat" style="max-width:900px;">';
        printf('<tr><th>theme_data->exists()</th><td>%s</td></tr>', $theme_data->exists() ? 'true' : 'FALSE — wp_get_theme("' . esc_html($theme_slug) . '") found nothing. Check the Theme slug matches the installed folder name exactly.');
        printf('<tr><th>Installed Version (from style.css)</th><td>%s</td></tr>', esc_html((string) $current_version));
        printf('<tr><th>visibility</th><td>%s</td></tr>', esc_html($visibility));
        printf('<tr><th>token present?</th><td>%s (length %d)</td></tr>', $token !== '' ? 'yes' : 'no', strlen($token));

        $api_url = $visibility === 'private'
            ? 'https://api.github.com/repos/' . $github_user . '/' . $github_repo . '/releases/latest'
            : 'https://raw.githubusercontent.com/' . $github_user . '/' . $github_repo . '/main/update-manifest.json';

        printf('<tr><th>API URL being called</th><td>%s</td></tr>', esc_html($api_url));

        $transient_key = 'gtu_github_release_' . $theme_slug;
        $cached        = get_transient($transient_key);
        printf('<tr><th>Cached transient (%s)?</th><td>%s</td></tr>', esc_html($transient_key), $cached ? 'YES — cached tag: ' . esc_html($cached->tag_name ?? '?') : 'empty');

        // Make the live request now, exactly like the updater would (bypassing cache for this debug view).
        $headers = [
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url('/'),
        ];
        if ($visibility === 'private' && $token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_safe_remote_get(esc_url_raw($api_url), ['timeout' => 15, 'headers' => $headers]);

        if (is_wp_error($response)) {
            printf('<tr><th style="color:red;">Live request result</th><td style="color:red;">WP_Error: %s</td></tr>', esc_html($response->get_error_message()));
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $decoded = json_decode($body);
            $tag  = isset($decoded->tag_name) ? $decoded->tag_name : (isset($decoded->message) ? 'ERROR: ' . $decoded->message : '(unparseable)');

            printf('<tr><th>Live HTTP status</th><td>%s</td></tr>', esc_html((string) $code));
            printf('<tr><th>Live tag_name / message</th><td>%s</td></tr>', esc_html((string) $tag));
            echo '<tr><th>Raw body (first 1500 chars)</th><td><textarea readonly style="width:100%;height:150px;">' . esc_textarea(substr($body, 0, 1500)) . '</textarea></td></tr>';

            if (isset($decoded->tag_name) && $current_version !== null) {
                $latest = ltrim((string) $decoded->tag_name, 'v');
                $cmp    = version_compare($current_version, $latest, '<');
                printf('<tr><th>version_compare(%s, %s, "&lt;")</th><td>%s</td></tr>', esc_html($current_version), esc_html($latest), $cmp ? 'TRUE (update should show)' : 'FALSE (no update — versions equal or installed is newer)');
            }
        }

        echo '</table>';
    }
}
