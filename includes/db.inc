<?php
declare(strict_types=1);

/**
 * Preference helpers for ksf_FA_image2pdf.
 * Stores settings in 0_ksf_image2pdf_prefs.
 */

if (!function_exists('i2p_get_pref')) {
    /**
     * Read a single preference.
     *
     * @param string $key Preference key
     * @return string|null Value or null when not set
     */
    function i2p_get_pref(string $key): ?string
    {
        $pref = defined('TB_PREF') ? TB_PREF : '0_';
        $sql = "SELECT pref_value FROM {$pref}ksf_image2pdf_prefs WHERE pref_key = '" . db_escape($key) . "' LIMIT 1";
        $result = db_query($sql, "Could not read image2pdf preference");
        if ($row = db_fetch($result)) {
            return (string)($row['pref_value'] ?? '');
        }
        return null;
    }
}

if (!function_exists('i2p_set_pref')) {
    /**
     * Write (upsert) a single preference.
     *
     * @param string $key   Preference key
     * @param string $value Preference value
     * @return bool True on success
     */
    function i2p_set_pref(string $key, string $value): bool
    {
        $pref = defined('TB_PREF') ? TB_PREF : '0_';
        $sql = "INSERT INTO {$pref}ksf_image2pdf_prefs (pref_key, pref_value)
                VALUES ('" . db_escape($key) . "', '" . db_escape($value) . "')
                ON DUPLICATE KEY UPDATE pref_value = '" . db_escape($value) . "', updated_ts = CURRENT_TIMESTAMP";
        return (bool)db_query($sql, "Could not write image2pdf preference");
    }
}

/**
 * Log a message via the ksf_log hook (ksf_FA_Logging) with error_log fallback.
 *
 * @param string $level   debug, info, warning, error, critical
 * @param string $message Human-readable message
 * @param array  $context Structured context (must be serialisable)
 * @return void
 */
if (!function_exists('i2p_log')) {
    function i2p_log(string $level, string $message, array $context = []): void
    {
        $module = 'image2pdf';
        if (function_exists('ksf_log')) {
            ksf_log($module, $level, $message, $context);
            return;
        }
        if (function_exists('hook_invoke_all')) {
            $payload = [
                'module'    => $module,
                'level'     => strtolower($level),
                'message'   => $message,
                'context'   => $context,
                'timestamp' => date('Y-m-d\TH:i:s'),
                'hostname'  => function_exists('php_uname') ? php_uname('n') : '',
                'pid'       => function_exists('getmypid') ? getmypid() : 0,
                'user_id'   => isset($_SESSION['wa_current_user']->id) ? $_SESSION['wa_current_user']->id : '',
            ];
            hook_invoke_all('ksf_log', $payload);
            return;
        }
        error_log(sprintf("[%s] [%s] [%s] %s %s",
            $payload_timestamp = date('Y-m-d\TH:i:s'),
            strtoupper($level),
            $module,
            $message,
            !empty($context) ? json_encode($context) : ''
        ));
    }
}
