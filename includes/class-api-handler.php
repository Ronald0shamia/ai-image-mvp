<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AAG_API_Handler {

    public static function generate_alt( string $image_url, string $prompt ): string {
        $opts = get_option( AAG_OPTION, [] );
        $img  = self::fetch_image_base64( $image_url );
        return self::dispatch( $img['data'], $img['mime'], $prompt, $opts );
    }

    public static function generate_alt_from_base64( string $b64, string $mime, string $prompt ): string {
        $opts = get_option( AAG_OPTION, [] );
        return self::dispatch( $b64, $mime, $prompt, $opts );
    }

    public static function generate_text( string $prompt, int $max_tokens = 400, float $temperature = 0.4 ): string {
        $opts = get_option( AAG_OPTION, [] );
        return self::dispatch_text( $prompt, $opts, $max_tokens, $temperature );
    }

    private static function dispatch( string $b64, string $mime, string $prompt, array $opts ): string {
        $provider = $opts['provider'] ?? 'gemini';
        switch ( $provider ) {
            case 'openai': return self::call_openai( $b64, $mime, $prompt, $opts );
            case 'claude': return self::call_claude( $b64, $mime, $prompt, $opts );
            case 'gemini':
            default:       return self::call_gemini( $b64, $mime, $prompt, $opts );
        }
    }

    private static function dispatch_text( string $prompt, array $opts, int $max_tokens, float $temperature ): string {
        $provider = $opts['provider'] ?? 'gemini';
        switch ( $provider ) {
            case 'openai': return self::call_openai_text( $prompt, $opts, $max_tokens, $temperature );
            case 'claude': return self::call_claude_text( $prompt, $opts, $max_tokens, $temperature );
            case 'gemini':
            default:       return self::call_gemini_text( $prompt, $opts, $max_tokens, $temperature );
        }
    }

    private static function call_gemini( string $b64, string $mime, string $prompt, array $opts ): string {
        $api_key = $opts['gemini_key'] ?? '';
        $model   = $opts['gemini_model'] ?? 'gemini-2.5-flash';

        if ( empty( $api_key ) ) {
            throw new Exception( 'Gemini API-Key fehlt.' );
        }

        $body = array(
            'system_instruction' => array( 'parts' => array( array( 'text' => $prompt ) ) ),
            'contents' => array( array(
                'parts' => array(
                    array( 'inline_data' => array( 'mime_type' => $mime, 'data' => $b64 ) ),
                    array( 'text' => 'Generate the alt text now.' ),
                )
            ) ),
            'generationConfig' => array( 'maxOutputTokens' => 200, 'temperature' => 0.3 ),
        );

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . urlencode( $model ) . ':generateContent?key=' . urlencode( $api_key );

        $res  = self::post( $url, $body, array() );
        $text = isset( $res['candidates'][0]['content']['parts'][0]['text'] )
            ? $res['candidates'][0]['content']['parts'][0]['text']
            : null;

        if ( null === $text ) {
            throw new Exception( 'Gemini: Keine Antwort erhalten.' );
        }
        return $text;
    }

    private static function call_openai( string $b64, string $mime, string $prompt, array $opts ): string {
        $api_key  = $opts['openai_key'] ?? '';
        $model    = $opts['openai_model'] ?? 'gpt-4o-mini';

        if ( empty( $api_key ) ) {
            throw new Exception( 'OpenAI API-Key fehlt.' );
        }

        $data_url = 'data:' . $mime . ';base64,' . $b64;

        $body = array(
            'model'      => $model,
            'max_tokens' => 200,
            'messages'   => array( array(
                'role'    => 'user',
                'content' => array(
                    array( 'type' => 'text', 'text' => $prompt . "\n\nGenerate the alt text now." ),
                    array( 'type' => 'image_url', 'image_url' => array( 'url' => $data_url, 'detail' => 'low' ) ),
                ),
            ) ),
        );

        $res  = self::post(
            'https://api.openai.com/v1/chat/completions',
            $body,
            array( 'Authorization' => 'Bearer ' . $api_key )
        );
        $text = isset( $res['choices'][0]['message']['content'] )
            ? $res['choices'][0]['message']['content']
            : null;

        if ( null === $text ) {
            throw new Exception( 'OpenAI: Keine Antwort erhalten.' );
        }
        return $text;
    }

    private static function call_claude( string $b64, string $mime, string $prompt, array $opts ): string {
        $api_key = $opts['claude_key'] ?? '';
        $model   = $opts['claude_model'] ?? 'claude-haiku-4-5-20251001';

        if ( empty( $api_key ) ) {
            throw new Exception( 'Claude API-Key fehlt.' );
        }

        $body = array(
            'model'      => $model,
            'max_tokens' => 200,
            'system'     => $prompt,
            'messages'   => array( array(
                'role'    => 'user',
                'content' => array(
                    array( 'type' => 'image', 'source' => array(
                        'type'       => 'base64',
                        'media_type' => $mime,
                        'data'       => $b64,
                    ) ),
                    array( 'type' => 'text', 'text' => 'Generate the alt text now.' ),
                ),
            ) ),
        );

        $res  = self::post(
            'https://api.anthropic.com/v1/messages',
            $body,
            array(
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
            )
        );
        $text = isset( $res['content'][0]['text'] )
            ? $res['content'][0]['text']
            : null;

        if ( null === $text ) {
            throw new Exception( 'Claude: Keine Antwort erhalten.' );
        }
        return $text;
    }

    private static function call_gemini_text( string $prompt, array $opts, int $max_tokens, float $temperature ): string {
        $api_key = $opts['gemini_key'] ?? '';
        $model   = $opts['gemini_model'] ?? 'gemini-2.5-flash';

        if ( empty( $api_key ) ) {
            throw new Exception( 'Gemini API-Key fehlt.' );
        }

        $body = array(
            'contents' => array( array(
                'parts' => array( array( 'text' => $prompt ) ),
            ) ),
            'generationConfig' => array(
                'maxOutputTokens' => $max_tokens,
                'temperature'     => $temperature,
            ),
        );

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . urlencode( $model ) . ':generateContent?key=' . urlencode( $api_key );

        $res  = self::post( $url, $body, array() );
        $text = isset( $res['candidates'][0]['content']['parts'][0]['text'] )
            ? $res['candidates'][0]['content']['parts'][0]['text']
            : null;

        if ( null === $text ) {
            throw new Exception( 'Gemini: Keine Antwort erhalten.' );
        }
        return $text;
    }

    private static function call_openai_text( string $prompt, array $opts, int $max_tokens, float $temperature ): string {
        $api_key = $opts['openai_key'] ?? '';
        $model   = $opts['openai_model'] ?? 'gpt-4o-mini';

        if ( empty( $api_key ) ) {
            throw new Exception( 'OpenAI API-Key fehlt.' );
        }

        $body = array(
            'model'       => $model,
            'max_tokens'  => $max_tokens,
            'temperature' => $temperature,
            'messages'    => array( array(
                'role'    => 'user',
                'content' => $prompt,
            ) ),
        );

        $res  = self::post(
            'https://api.openai.com/v1/chat/completions',
            $body,
            array( 'Authorization' => 'Bearer ' . $api_key )
        );
        $text = isset( $res['choices'][0]['message']['content'] )
            ? $res['choices'][0]['message']['content']
            : null;

        if ( null === $text ) {
            throw new Exception( 'OpenAI: Keine Antwort erhalten.' );
        }
        return $text;
    }

    private static function call_claude_text( string $prompt, array $opts, int $max_tokens, float $temperature ): string {
        $api_key = $opts['claude_key'] ?? '';
        $model   = $opts['claude_model'] ?? 'claude-haiku-4-5-20251001';

        if ( empty( $api_key ) ) {
            throw new Exception( 'Claude API-Key fehlt.' );
        }

        $body = array(
            'model'       => $model,
            'max_tokens'  => $max_tokens,
            'temperature' => $temperature,
            'messages'    => array( array(
                'role'    => 'user',
                'content' => $prompt,
            ) ),
        );

        $res  = self::post(
            'https://api.anthropic.com/v1/messages',
            $body,
            array(
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
            )
        );
        $text = isset( $res['content'][0]['text'] )
            ? $res['content'][0]['text']
            : null;

        if ( null === $text ) {
            throw new Exception( 'Claude: Keine Antwort erhalten.' );
        }
        return $text;
    }

    private static function post( string $url, array $body, array $extra_headers ): array {
        $headers = array_merge(
            array( 'Content-Type' => 'application/json' ),
            $extra_headers
        );

        $response = wp_remote_post( $url, array(
            'timeout' => 60,
            'headers' => $headers,
            'body'    => wp_json_encode( $body ),
        ) );

        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }

        $code    = wp_remote_retrieve_response_code( $response );
        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = isset( $decoded['error']['message'] )
                ? $decoded['error']['message']
                : 'API Fehler (HTTP ' . $code . ')';
            throw new Exception( $msg );
        }

        return $decoded;
    }

    private static function fetch_image_base64( string $url ): array {
        $response = wp_remote_get( $url, array( 'timeout' => 30 ) );
        if ( is_wp_error( $response ) ) {
            throw new Exception( 'Bild konnte nicht geladen werden: ' . $response->get_error_message() );
        }
        $body = wp_remote_retrieve_body( $response );
        $type = wp_remote_retrieve_header( $response, 'content-type' );
        $mime = strtok( $type ? $type : 'image/jpeg', ';' );
        return array(
            'data' => base64_encode( $body ),
            'mime' => $mime,
        );
    }
}
