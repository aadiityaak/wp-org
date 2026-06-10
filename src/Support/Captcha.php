<?php

namespace WpOrg\Support;

class Captcha
{
    public function get_settings()
    {
        $settings = get_option('captcha_velocity', []);

        if (!is_array($settings)) {
            $settings = [];
        }

        return [
            'enabled' => !empty($settings['aktif']),
            'provider' => sanitize_key($settings['provider'] ?? 'google'),
            'site_key' => sanitize_text_field($settings['sitekey'] ?? ''),
            'secret_key' => sanitize_text_field($settings['secretkey'] ?? ''),
        ];
    }

    public function register()
    {
        add_action('wp_enqueue_scripts', [$this, 'register_google_script']);
    }

    public function register_google_script()
    {
        $settings = $this->get_settings();
        if (empty($settings['enabled']) || $settings['provider'] !== 'google' || empty($settings['site_key'])) {
            return;
        }

        wp_register_script('wp-org-recaptcha', 'https://www.google.com/recaptcha/api.js?render=explicit', [], null, true);
    }

    public function is_enabled()
    {
        $settings = $this->get_settings();

        if (empty($settings['enabled'])) {
            return false;
        }

        if ($settings['provider'] === 'google') {
            return !empty($settings['site_key']) && !empty($settings['secret_key']);
        }

        return $settings['provider'] === 'image';
    }

    public function render($form_id)
    {
        $settings = $this->get_settings();
        if (!$this->is_enabled()) {
            return '';
        }

        if ($settings['provider'] === 'image') {
            $token = wp_generate_password(20, false, false);
            return '<div class="wp-org-captcha-image"><input type="hidden" name="vd_captcha_token" value="' . esc_attr($token) . '"><img src="' . esc_url(admin_url('admin-ajax.php?action=vd_captcha_image&token=' . urlencode($token))) . '" alt="captcha" style="border:1px solid #dcdcde;border-radius:8px;height:44px"><p><input type="text" name="vd_captcha_input" placeholder="Masukkan captcha" autocomplete="off"></p></div>';
        }

        wp_enqueue_script('wp-org-recaptcha');

        return '<div class="wp-org-recaptcha" data-form-id="' . esc_attr($form_id) . '" data-site-key="' . esc_attr($settings['site_key']) . '"></div>';
    }

    public function verify_submission()
    {
        if (!$this->is_enabled()) {
            return true;
        }

        $settings = $this->get_settings();

        if ($settings['provider'] === 'image') {
            $token = isset($_POST['vd_captcha_token']) ? sanitize_text_field(wp_unslash($_POST['vd_captcha_token'])) : '';
            $input = isset($_POST['vd_captcha_input']) ? sanitize_text_field(wp_unslash($_POST['vd_captcha_input'])) : '';

            if ($token === '' || $input === '') {
                return new \WP_Error('captcha_required', 'Captcha wajib diisi.');
            }

            $stored = get_transient('vd_captcha_' . $token);
            if (!$stored || strtolower($stored) !== strtolower($input)) {
                return new \WP_Error('captcha_invalid', 'Captcha tidak valid.');
            }

            delete_transient('vd_captcha_' . $token);

            return true;
        }

        $response = isset($_POST['g-recaptcha-response']) ? sanitize_text_field(wp_unslash($_POST['g-recaptcha-response'])) : '';
        if ($response === '') {
            return new \WP_Error('captcha_required', 'Captcha wajib divalidasi.');
        }

        $remote_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $result = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'timeout' => 15,
            'body' => [
                'secret' => $settings['secret_key'],
                'response' => $response,
                'remoteip' => $remote_ip,
            ],
        ]);

        if (is_wp_error($result)) {
            return new \WP_Error('captcha_verify_failed', 'Verifikasi captcha gagal.');
        }

        $body = json_decode(wp_remote_retrieve_body($result), true);

        return !empty($body['success']) ? true : new \WP_Error('captcha_invalid', 'Captcha tidak valid.');
    }
}
