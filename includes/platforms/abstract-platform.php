<?php
defined('ABSPATH') || exit;

/**
 * Abstract Base für alle Plattform-Integrationen.
 * Jede Plattform implementiert diese Schnittstelle.
 */
abstract class GAMI_Platform_Base {

    abstract public function get_key(): string;
    abstract public function get_name(): string;

    /**
     * Aktive Kampagnen-Stats holen (von der Plattform-API)
     */
    abstract public function fetch_campaign_stats(): array;

    /**
     * Ad/Kampagne auf der Plattform erstellen
     */
    abstract public function create_campaign(array $campaign_data): ?string;

    /**
     * Ad auf der Plattform erstellen
     */
    abstract public function create_ad(array $ad_data): ?string;

    /**
     * Kampagne pausieren
     */
    abstract public function pause_campaign(int $campaign_id): bool;

    /**
     * Budget erhöhen
     */
    abstract public function increase_budget(int $campaign_id, float $multiplier): bool;

    /**
     * Gemeinsame Methode: HTTP-Request
     */
    protected function request(string $method, string $url, array $data = [], array $headers = []): ?array {
        $args = [
            'method'  => strtoupper($method),
            'timeout' => 30,
            'headers' => array_merge(['Content-Type' => 'application/json'], $headers),
        ];
        if (!empty($data)) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            error_log('[GAMI ' . $this->get_key() . '] Request Error: ' . $response->get_error_message());
            return null;
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            error_log('[GAMI ' . $this->get_key() . "] HTTP $code: " . print_r($body, true));
            return null;
        }
        return $body;
    }

    /**
     * API-Credentials aus WP-Options
     */
    protected function get_option(string $key): string {
        return get_option('gami_' . $this->get_key() . '_' . $key, '');
    }

    protected function is_configured(): bool {
        return !empty($this->get_option('enabled')) && (bool)$this->get_option('enabled');
    }
}
